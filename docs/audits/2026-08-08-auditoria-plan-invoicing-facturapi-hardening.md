# Auditoría del plan — Invoicing Facturapi production hardening

**Fecha:** 2026-08-08  
**Plan objetivo:** [`docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md`](../superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md)  
**Origen:** PR [#101](https://github.com/Parzival2103/Lebytek_Framework/pull/101) (mergeado en `main` @ `f07c6d1`)  
**Auditoría de producto previa:** [`2026-08-08-auditoria-invoicing-facturapi.md`](2026-08-08-auditoria-invoicing-facturapi.md)  
**Veredicto (plan #101 tal cual):** **BLOCKED** — H1/H2.  
**Veredicto (tras A21/A22, primera pasada):** BLOCKED — la enmienda cerraba el truncado pero abría H7–H12 (revisión de este PR).  
**Veredicto (tras A23–A27):** **READY** para ejecución desde Task 1.

---

## Resumen

El plan cubre bien el núcleo fiscal (A11 no-release, `idempotency_key` remoto, excepción tipada, retrieve/reconcile, cancel, secrets, RBAC, webhooks). **No está listo para ejecutar tal cual** por un defecto de contrato en `external_id` y por dejar opcional la única vía de recuperación cuando el create es ambiguo y no hay `provider_invoice_id` observado.

---

## Hallazgos

### H1 — P0 — `external_id = substr(sourceRef, 0, 100)` puede colisionar

| Campo | Valor |
|-------|--------|
| Dónde | Amendment **A12**; Task 3 «Contract — payload»; Coverage matrix fila 1 |
| Evidencia Facturapi | Create: *«Facturapi does not validate that this field is unique»*. List filter: `external_id` exact match, `<= 100 characters` ([API docs](https://docs.facturapi.io/en/api/)). |
| Riesgo | Dos `sourceRef` distintos que compartan el prefijo de 100 chars reciben el mismo `external_id`. `list?external_id=` puede devolver **varias** facturas o la incorrecta. El residual D11 («list ambiguous → manual») se vuelve el camino feliz bajo truncado, no un edge raro. |
| Ejemplo | `evento:cliente:reservacion:XXXXXXXX…AAA` vs `…BBB` → mismo `substr(0,100)`. |
| Mitigación | Valor **determinista y acotado** de longitud fija: `lebytek:invoice:{hex(sha256(preimagen))[0:40]}` (56 chars ≤100; **hex**, no bytes crudos — un `external_id` binario rompería el filtro exact-match aunque pasara el test de longitud). Guardar el valor enviado en `meta.external_id` para ops. **No** truncar el `sourceRef` crudo. **La preimagen la corrige H7: es `providerKey + idempotencyKey`, no `sourceRef`.** |

`idempotency_key` (local issue key → Facturapi) sigue siendo la defensa anti-doble-timbrado en retry; `external_id` es la llave de **lookup** post-timeout. Mezclar “caber en 100” con “ser el sourceRef” rompe el lookup.

### H2 — P0 — `listByExternalId` no puede ser opcional en Task 5

| Campo | Valor |
|-------|--------|
| Dónde | Task 5 «Optional: listByExternalId»; D11 «optional … if cheap»; File Structure «optional» |
| Contraste | A11 deja claim sin id tras timeout. Task 4 solo tipa id cuando el create **observó** `IssuedInvoice`. Sin list-by-external_id, el path ambiguo sin id observado **no tiene recuperación automatizada** antes de intervención manual — contradice el objetivo de cerrar E1/D11 en hardening. |
| Mitigación | **Required** en Task 5 (transport + provider): recuperar 0/1 factura por `external_id` computado; si 1 → `attachProviderInvoiceId` + reconcile/retrieve; si 0 → seguir `InvoiceAmbiguousCreate` (remoto aún no visible / create nunca llegó); si >1 → fallo tipado (no debería ocurrir con H1 cerrado). |

### H3 — P1 — Contrato de Reconcile omite el path claimed-without-id

El bloque «Contract — Reconcile» de Task 5 solo contempla `NeedsReconcile` con id presente. Falta el flujo:

```text
claimed / AmbiguousCreate, provider_invoice_id null
  → row = findClaimByIdempotencyKey(...)        // H8: NO findByIdempotencyKey
  → si edad(row) < umbral → "claim too fresh", salir     // H10
  → external_id = row.meta.external_id ?? externalIdForIssue(idempotencyKey)   // H7
  → listByExternalId
  → 1 hit → attach condicional + retrieve + markIssued|markCanceled
  → 0 hits → keep claim, no create (salida ops explícita: H12)
  → >1 → InvoiceAmbiguousCreate / ops (no inventar id)
```

Sin eso, un subagente puede implementar retrieve y darse por Done when sin cerrar D11.

### H4 — P1 — Ownership `markCanceled` entre Task 5 y Task 6 es ambiguo

Task 5 permite “interim repo API” o “implement thin markCanceled here first”. Eso invita a dos shapes distintos o a saltarse Task 6. **Decisión:** Task 5 implementa `markCanceled` mínimo en el port (PDO + InMemory); Task 6 solo amplía claim-before cancel + motives sobre esa API. **Ampliado por H11:** la firma debe quedar escrita literalmente en Task 5 y los dos call sites deben coincidir en la llave de búsqueda.

### H5 — P2 — Residuales y matriz de cobertura desalineados tras H1/H2

- D11 debe pasar de “optional list / manual si ambiguous” a “list required; residual solo 0 hits o fallo de attach”.
- Coverage matrix fila 2 (last-resort + reconcile) debe incluir aceptación: *orphan claim sin id observado se recupera por `external_id` sin segundo create*.
- Task 10 runbook debe documentar el algoritmo de `external_id` (no el `sourceRef` truncado).

### H6 — OK (no bloquean)

- A11 keep-claim y taxonomía de catch en Task 3.
- A13/A14 excepción tipada + last-resort attach cuando sí hay id observado.
- A15 retrieve antes de promover; A16 pending hydrate.
- A18 single key; A19/A20 ownership webhook/RBAC vs consumer.
- Orden de tareas y TDD por task.
- Prohibición de adivinar id desde mensajes de excepción (nota § Deviations).

---

## Segunda pasada — revisión de la enmienda A21/A22 (PR #103)

A21/A22 cierran el truncado de H1, pero la enmienda se contrastó contra el código real de `src/` y abre seis defectos de contrato. Corregidos con **A23–A27**.

### H7 — P0 — La preimagen `sha256(sourceRef)` codifica un invariante falso → **A23**

| Campo | Valor |
|-------|--------|
| Dónde | A21 «`hex(sha256(sourceRef))`»; A22 «`listByExternalId(A21(sourceRef))`»; nota de desviación 7 |
| Evidencia en código | `IssueInvoiceFromSource::handle(string $sourceRef, string $idempotencyKey, ...)` llavea por `idempotencyKey`; `inv_events` es `UNIQUE (provider, idempotency_key)` → **N filas por `sourceRef`**. Task 6 (motivo `01`) emite una factura sustituta para el **mismo** `sourceRef` |
| Riesgo | Un `sourceRef` con varias facturas legítimas (sustitución, re-emisión) hace que `listByExternalId` devuelva >1 → fail-closed permanente; o devuelva 1 y **adjunte el id de una factura cancelada previa** a un claim nuevo. La nota 7 afirmaba que >1 = corrupción: falso bajo esa preimagen |
| Mitigación | Preimagen **per-attempt**: `providerKey + "\x1f" + idempotencyKey`. La 1:1 la garantiza el `idempotency_key` remoto. Encoder `forIssueClaim(providerKey, idempotencyKey)`; **eliminar** `fromSourceRef` |

### H8 — P0 — La rama huérfana de A22 es código muerto → **A24**

`PdoInvoiceEventLogRepository::findByIdempotencyKey` filtra `AND provider_invoice_id IS NOT NULL` (~L107). Un huérfano devuelve **`null`**, así que dispara primero la línea `null → InvoiceSourceNotFound` del contrato y **ninguna** rama de A22 es alcanzable. `findNeedsReconcile` (~L153) filtra igual: el barrido tampoco ve huérfanos. Ni Task 5 ni la tabla de File Structure añadían lookup de fila de claim ni accessor de `sourceRef`. Requiere read model `InvoiceClaimRow` + `findClaimByIdempotencyKey` / `findIssueByProviderInvoiceId` / `findOrphanClaims`.

### H9 — P1 — `listByExternalId` fuera del port rompe a Reconcile → **A24**

A22 la declara en «transport + provider», pero `ReconcileIssuedInvoice` la invoca vía `InvoiceProviderRegistry::get()`, que devuelve `InvoiceProviderInterface`. La línea contigua sí añade `retrieveInvoice` al port; `listByExternalId` no. Mismo problema con el encoder: A22 hacía que un caso de uso de Application llamara `FacturapiExternalId` de Infrastructure — hoy solo `InvoicingFactory` cruza esa frontera. Se resuelve con `externalIdForIssue()` en el port.

### H10 — P1 — `Pending`/`Unknown` sin rama + claim en vuelo indistinguible → **A27**

Dos defectos en el mismo bloque: (a) A16 hace que `IssuedInvoice::status()` refleje el **provider status**, así que enumerar `Issued/Canceled` deja `Pending` (introducido por A16 en la misma task) y `Unknown` sin rama — hay que ramificar por `ledgerStatus`; (b) `tryClaim` corre **antes** de `createInvoice` (L31 vs L52), luego un claim recién creado es idéntico a un huérfano: un reconcile concurrente puede lanzar excepción espuria o adjuntar un id que después colisiona en `assertCanMark`. Se cierra con guarda de edad + attach condicional que re-lee al perder la carrera.

### H11 — P1 — `markCanceled` sin firma y con dos llaves de búsqueda → **A24**

La firma se borró de Task 6 y nunca se re-declaró en Task 5, que solo dice «implement thin markCanceled here», mientras Task 6 dice «do not invent a second shape». Además los call sites discrepan: Task 5 sugiere `idempotencyKey`, Task 6 «markCanceled on issue row(s) with that provider_invoice_id». Firma única `markCanceled(provider, idempotencyKey, invoice)` + `findIssueByProviderInvoiceId` para resolver la llave desde el id.

### H12 — P2 — «keep `meta.external_id`» inalcanzable y huérfano de 0 hits inemitible → **A25 / A26**

`mark()` reemplaza la columna `meta` entera con `encodeMeta($invoice->meta())`; no hay merge, y `tryClaim` hoy se llama **sin meta** — el valor nunca llega a existir. Requiere política de merge explícita + escribir `meta.external_id` en el claim. Por separado: con A11 (no release) + A22 (no create), un claim cuyo create nunca llegó a Facturapi queda **permanentemente inemitible**, sin API de remediación ni runbook, aun cuando 0 hits + el mismo `idempotency_key` remoto hacen el re-create demostrablemente seguro.

---

## Veredicto y autorización

| Pregunta | Respuesta |
|----------|-----------|
| ¿Ejecutar el plan mergeado en #101 sin cambios? | **No** |
| ¿Bloqueantes en #101? | H1, H2 (H3–H5 se corrigen con la misma enmienda) |
| ¿Bloqueantes en la primera pasada A21/A22? | H7, H8 (P0); H9–H11 (P1) — ambigüedades que un subagente resolvería inventando |
| ¿Enmienda aplicada aquí? | A21 + A22 + **A23–A27** + Tasks 3/4/5/6/10 + File Structure + Verified code facts + D11 + matriz + desviaciones 7–10 |
| ¿Ejecutar plan enmendado? | **Sí** — Task 1 primero; Task 5 Done when incluye A22/A24/A26/A27 |

---

## Evidencia de preflight (este reporte)

- `origin/main` @ `f07c6d1` (merge #101)
- `git merge-base --is-ancestor origin/main HEAD` → 0
- Working tree limpio antes de escribir
- Legacy `feature/backoffice-api-integration`: no usado como base

### Segunda pasada (revisión PR #103 → A23–A27)

- Rama `cursor/audit-plan-facturapi-external-id-9e1c` @ `8509b87`
- Contraste contra código real, no solo contra el texto del plan:
  - `src/Infrastructure/Invoicing/PdoInvoiceEventLogRepository.php` (`findByIdempotencyKey` L99–121, `findNeedsReconcile` L141–166, `mark` L168–206, `assertCanMark` L208–247)
  - `src/Application/Invoicing/IssueInvoiceFromSource.php` (`tryClaim` L31 vs `createInvoice` L52)
  - `src/Application/Invoicing/ReconcileIssuedInvoice.php` (L25–38), `InvoiceProviderRegistry::get` (L34–46)
  - `src/Domain/Invoicing/InvoiceProviderInterface.php`, `InvoiceStatus.php`, `InvoiceEventLogRepositoryInterface.php`
  - `database/schema/modules/invoicing.sql` (`UNIQUE (provider, idempotency_key)`, `created_at` disponible para la guarda de edad de A27)
