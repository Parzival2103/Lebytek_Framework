# Auditoría del plan — Invoicing Facturapi production hardening

**Fecha:** 2026-08-08  
**Plan objetivo:** [`docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md`](../superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md)  
**Origen:** PR [#101](https://github.com/Parzival2103/Lebytek_Framework/pull/101) (mergeado en `main` @ `f07c6d1`)  
**Auditoría de producto previa:** [`2026-08-08-auditoria-invoicing-facturapi.md`](2026-08-08-auditoria-invoicing-facturapi.md)  
**Veredicto (plan #101 tal cual):** **BLOCKED** — H1/H2.  
**Veredicto (tras A21/A22 en este cambio):** **READY** para ejecución desde Task 1.

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
| Mitigación | Valor **determinista y acotado** que preserve 1:1 con `sourceRef`, p. ej. `lebytek:invoice:{sha256(sourceRef)[0:40]}` (≤100). Guardar el valor enviado en `meta.external_id` para ops. **No** truncar el `sourceRef` crudo. |

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
  → external_id = hash(sourceRef)
  → listByExternalId
  → 1 hit → attach + retrieve + markIssued|markCanceled
  → 0 hits → keep claim, no create
  → >1 → InvoiceAmbiguousCreate / ops (no inventar id)
```

Sin eso, un subagente puede implementar retrieve y darse por Done when sin cerrar D11.

### H4 — P1 — Ownership `markCanceled` entre Task 5 y Task 6 es ambiguo

Task 5 permite “interim repo API” o “implement thin markCanceled here first”. Eso invita a dos shapes distintos o a saltarse Task 6. **Decisión:** Task 5 implementa `markCanceled` mínimo en el port (PDO + InMemory); Task 6 solo amplía claim-before cancel + motives sobre esa API.

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

## Veredicto y autorización

| Pregunta | Respuesta |
|----------|-----------|
| ¿Ejecutar el plan mergeado en #101 sin cambios? | **No** |
| ¿Bloqueantes en #101? | H1, H2 (H3–H5 se corrigen con la misma enmienda) |
| ¿Enmienda aplicada aquí? | A21 + A22 + Tasks 3/5/6/10 + D11 + matriz |
| ¿Ejecutar plan enmendado? | **Sí** — Task 1 primero; Task 5 Done when incluye A22 |

---

## Evidencia de preflight (este reporte)

- `origin/main` @ `f07c6d1` (merge #101)
- `git merge-base --is-ancestor origin/main HEAD` → 0
- Working tree limpio antes de escribir
- Legacy `feature/backoffice-api-integration`: no usado como base
)
