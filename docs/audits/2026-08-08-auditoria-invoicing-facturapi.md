# Auditoría — Invoicing / Facturapi (pre-producción)

**Alcance:** módulo vertical `invoicing` del paquete `lebytek/framework` (`src/{Domain,Application,Infrastructure}/Invoicing`, `config/invoicing.php`, `config/modules/invoicing.php`, `database/schema/modules/invoicing.sql`, skeleton mirrors, `tests/Invoicing/**`, `docs/modules/modulo-invoicing.md`).  
**Tipo:** auditoría funcional + seguridad + gaps de producción sobre el scaffold CFDI I Facturapi (PR #99 / plan v1).  
**No incluye:** implementación de fixes. El plan ejecutable de hardening va en un **segundo commit** en esta misma rama (ver § Handoff).

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `60477dc1c8d311902dc44547d1a962dc30cd0af3` |
| Rama | `cursor/invoicing-facturapi-prod-hardening-3229` |
| Timestamp UTC | `2026-08-08T01:13:00Z` |
| Spec / plan v1 | `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`, `docs/superpowers/plans/2026-08-07-invoicing-facturapi.md` |
| Módulo shippeado | `feat(invoicing): Facturapi CFDI vertical` (#99 / `21edf26`) |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
$ git rev-parse --verify origin/main
60477dc1c8d311902dc44547d1a962dc30cd0af3

$ git merge-base --is-ancestor origin/main HEAD
(exit 0)

$ git rev-parse --verify --quiet 'refs/tags/archive/backoffice-api-integration^{commit}'
(resolvió)

$ git rev-list --count origin/main..refs/tags/archive/backoffice-api-integration
53
LEGACY_ANCESTOR_CHECK=PASS

$ git status --porcelain   # antes de escribir
(vacío)
```

---

## Resumen ejecutivo

El módulo `invoicing` es un **scaffold CFDI tipo I** bien alineado con la arquitectura del framework (Domain → Application → Infrastructure, vertical OFF, negocio en el consumidor vía `InvoiceableSourceInterface`). Credenciales no se persisten en `inv_*`, hay claim/idempotencia A1 en el camino feliz, y create/cancel/PDF/XML/email existen detrás del SDK oficial.

**No está listo para producción fiscal** hasta cerrar: doble timbrado por timeout ambiguo, reconcile sin retrieve remoto, desacople `FACTURAPI_MODE`/prefijo de key, cancel incompleto en ledger, redacción/denylist incompletas, RBAC vacío, ausencia de webhooks firmados, y validaciones CFDI mínimas incompletas.

**Contexto de alcance v1:** webhooks, multi-RFC, catálogo SAT completo y UI están fuera del plan v1 (D10 / fuera de alcance). Eso no mitiga los bugs de idempotencia/seguridad listados abajo.

**Owner:** Framework (`Lebytek_Framework`). Consumidor (Portal/tenant) solo para rutas RBAC y wiring de webhook HTTP cuando el plan lo indique.

---

## Cómo está construido hoy

```
Consumer InvoiceableSourceInterface
  → IssueInvoiceFromSource (claim → validate → create → markIssued | needs_reconcile)
  → InvoiceProviderRegistry → FacturapiInvoiceProvider → SdkFacturapiTransport → facturapi-php
  → inv_events (ledger) + inv_organizations (mode cache, external_org_id='')
```

| Pieza | Ruta |
|-------|------|
| Puertos / VOs | `src/Domain/Invoicing/` |
| Use cases | `src/Application/Invoicing/` |
| Adapter Facturapi | `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php` |
| Transport SDK | `src/Infrastructure/Invoicing/Facturapi/` |
| SQL | `database/schema/modules/invoicing.sql` |
| Config | `config/invoicing.php`, vertical OFF |
| Docs conexión | `docs/modules/modulo-invoicing.md` |

---

## 1. Correctamente implementado

- Separación de capas y ownership Framework vs consumidor documentada.
- Vertical y provider OFF by default (`vertical.modules.invoicing=false`, `FACTURAPI_ENABLED=false`).
- Secret en env → config → SDK; **no** se escribe en `inv_organizations` / `inv_events` por los writers actuales (`SyncOrganizationSettingsFromConfig` solo mode + `external_org_id=''`).
- Idempotencia A1 camino feliz: si hay `IssuedInvoice` observado y falla `markIssued` → `markNeedsReconcile` + `InvoiceNeedsReconcile` (no `releaseClaim`).
- A2 fail-closed: `InvoiceIdResolver` exige id directo o exactamente una fila issued por `source_ref`.
- Operaciones v1: create / cancel (+ substitution opcional) / PDF / XML / email.
- Cliente fiscal inline en payload (`mapCustomer`); sin CRM Customers (acorde al plan v1).
- Validación mínima pre-SDK: RFC shape, CP 5 dígitos, MXN, ítems, `product_key` 8 dígitos, taxes o exento (`InvoiceDraftValidator`).
- Redacción parcial `sk_test_*` / `sk_live_*` en excepciones + test de no-leak.
- Sin UI/rutas/menú en el paquete (`permisos`/`menu`/`cruds` vacíos a propósito en v1).
- Ledger guarda `provider_invoice_id`, `uuid`, `folio_number`, `source_ref`, `idempotency_key`.
- Fixtures golden IVA 16% / Exento; suite `tests/Invoicing/**` cubre A1/A2/smoke/schema/docs.

---

## 2. Mejorable

| ID | Problema | Archivo / método | Solución concreta |
|----|----------|------------------|-------------------|
| M1 | Clientes solo inline; sin sync Facturapi Customers | (sin método; plan v1) | Opcional post-hardening: Customers API + cache id en consumidor |
| M2 | Sin catálogo SAT; `tax_system` / `unit_key` / `payment_method` no validados ni mapeados | `InvoiceDraftValidator`, `mapDraft` | Validaciones mínimas + enum/campo `payment_method` (PUE/PPD); sin catálogo completo |
| M3 | Totales 100% delegados; `taxability='02'` fijo; precio vía `float` | `FacturapiInvoiceProvider::mapItem` / `majorAmount` | String decimal; ampliar taxes si se necesita |
| M4 | Sin `retrieve` / refresh de estado | `InvoiceProviderInterface`, transport | Añadir `retrieve` + use case refresh |
| M5 | Ledger hidrata `issued` → siempre `Valid` | `PdoInvoiceEventLogRepository::domainStatus` | Persistir/usar `provider_status` |
| M6 | Cancel no marca issue row `canceled` | `CancelIssuedInvoice::auditCancelClaim` | UPDATE issue → `canceled` tras éxito |
| M7 | Reconcile solo local | `ReconcileIssuedInvoice::handle` | Retrieve remoto antes de promover |
| M8 | `pdfUrl`/`xmlUrl` no se hidratan desde ledger | `hydrate` vs `mapIssuedInvoice` | Guardar en meta/columnas |
| M9 | `FACTURAPI_MODE` no enforcea key | `InvoicingFactory::buildProviders` | Fail-fast mode ↔ prefijo |
| M10 | Motivo cancel sin validar | `InvoiceCancellation` | `01`–`04`; substitution obligatoria si `01` |
| M11 | `permisos => []` | `config/modules/invoicing.php` | Slugs + docs RBAC consumidor |
| M12 | Sin actor en auditoría | Application use cases | Callback redacted (`actor_id`, keys, no draft) |
| M13 | `external_org_id` siempre `''` | `SyncOrganizationSettingsFromConfig` | Env no-secreto u org me |

---

## 3. Errores o riesgos funcionales

| ID | Severidad | Problema | Archivo / método | Solución |
|----|-----------|----------|------------------|----------|
| E1 | **Alta** | Timeout/red tras create remoto: `$observedInvoice=null` → `releaseClaim` → retry puede **doble-timbrar**. Contradice A3 / docs (“no blind retry”). | `IssueInvoiceFromSource::handle` catch | No liberar claim tras create *intentado* sin verificar ausencia remota; estado `needs_ops` / excepción ambigua tipada |
| E2 | **Alta** | Si fallan `markIssued` y `markNeedsReconcile`, id solo en mensaje; claim sin id → `InvoiceAlreadyProcessed` bloquea reconcile | `IssueInvoiceFromSource`; `InvoiceNeedsReconcile` sin propiedad tipada | Last-resort UPDATE del id; excepción con `providerInvoiceId()` |
| E3 | **Media** | Cancel llama Facturapi primero; audit best-effort → no idempotente | `CancelIssuedInvoice::handle` | Claim antes del remoto; replay seguro |
| E4 | **Media** | Sin clasificación 429/timeout/Retry-After | `FacturapiInvoiceProvider::fail` | Errores tipados `retryable` + timeout config |
| E5 | **Media** | Facturapi/SAT caídos: excepción + release si no hay id; reconcile no verifica remoto | Provider + `ReconcileIssuedInvoice` | Retrieve + runbook; no re-emitir ciego |

---

## 4. Riesgos de seguridad

| ID | Riesgo | Dónde | Solución |
|----|--------|-------|----------|
| S1 | `MODE=test` + `sk_live_*` no se rechaza | `InvoicingFactory::buildProviders` | Enforce prefijo ↔ mode |
| S2 | `enabled=true` + secret vacío registra provider | `buildProviders` / `fromSecretKey` | Fail-fast formato `sk_(test\|live)_` |
| S3 | Redacción omite `sk_user_*` y `Bearer` | `sanitizeSecretTokens` | Ampliar regex + tests |
| S4 | Secret en `Config` alcanzable por vistas consumidor (`ViewHelper::config`) | path `invoicing.providers.facturapi.config.secret_key` | Binding/secret store no expuesto a views; docs |
| S5 | Sin webhooks ni `Facturapi-Signature` | módulo (D10) | Validador + apply-event; no endpoint público sin firma |
| S6 | `meta` JSON sin denylist | `PdoInvoiceEventLogRepository::encodeMeta` (y org repo) | Strip secret/token/password/api_key/authorization |
| S7 | Sin RBAC en use cases | manifest + Application | Slugs + middleware obligatorio en rutas consumidor |
| S8 | Single-org por deploy; sin `empresa_id` en `inv_*` | schema | Documentar invariante; multi-empresa solo con scope + secrets no plaintext |

---

## 5. Pruebas faltantes (prioridad CI)

Ya existen: VOs/status, golden payloads, A1 paths, A2 resolver, cancel/download/email happy path, ledger contract, smoke, secret leak parcial, schema/docs/config.

**Faltan (mínimo pre-prod, puntos 1–7 + secretos):**

1. Timeout post-create: claim **no** se libera; no segundo create.
2. Doble fallo `markIssued` + `markNeedsReconcile`: id tipado recuperable / reconcile posible.
3. Status `pending` round-trip tras persist/hydrate (no forzar `Valid`).
4. Cancel actualiza fila issue a `canceled`.
5. Cancel idempotente (sin doble llamada remota indebida / replay).
6. Motivo `01` exige `substitution`.
7. Prefijo key ↔ `FACTURAPI_MODE` + key vacía con enabled.
8. Redacción `sk_user_*` / Bearer + denylist meta.
9. (Siguiente oleada) retrieve+reconcile remoto, 429/timeout tipados, email/download error paths, MySQL concurrent claim.

---

## 6. Cambios prioritarios antes de producción (input del plan)

Estos puntos son el **alcance obligatorio** del plan de hardening en el segundo commit de esta rama:

1. Cerrar agujero de doble timbrado por timeout en `IssueInvoiceFromSource` (A3 real).
2. Persistencia last-resort de `provider_invoice_id` + excepción tipada con id; reconcile con **retrieve remoto**.
3. Enforcement `mode` ↔ `sk_test_` / `sk_live_` y rechazo de key vacía.
4. Cancel completo: claim previo, status local `canceled`, validar motivo/sustitución.
5. Ampliar redacción de secretos + denylist en `meta`.
6. Slugs RBAC + regla dura: rutas consumidor siempre autorizadas.
7. Antes de async “bonito”: endpoint/flujo con `Facturapi-Signature`; nunca payload fiscal completo en logs.
8. Validaciones CFDI mínimas adicionales (`tax_system`, `unit_key`, `payment_method`); catálogo SAT completo fuera.
9. Tests CI de los puntos 1–7 de §5 (+ secretos/meta).

---

## Matriz requisito → evidencia

| Prioridad | Evidencia principal | Destino en plan |
|-----------|---------------------|-----------------|
| E1 doble stamp | `IssueInvoiceFromSource.php` L56–74 | Task hardening A3 |
| E2 last-resort id | mismo + `InvoiceNeedsReconcile.php` | Task excepción tipada + persist |
| M7/E5 reconcile remoto | `ReconcileIssuedInvoice.php`; sin `retrieve` en interface | Task retrieve + reconcile |
| S1/S2 mode/key | `InvoicingFactory.php` L67–75; `config/invoicing.php` | Task factory fail-fast |
| E3/M6/M10 cancel | `CancelIssuedInvoice.php`; `InvoiceCancellation.php`; schema `canceled` | Task cancel completo |
| S3/S6 secrets/meta | `FacturapiInvoiceProvider.php` L233–238; `encodeMeta` | Task redact + denylist |
| S7 RBAC | `config/modules/invoicing.php` L15 | Task slugs + docs |
| S5 webhooks | ausente; D10 en plan v1 | Task signature-first webhook |
| M2 CFDI mínimos | `InvoiceDraftValidator.php`; `mapDraft` | Task validator + mapping |
| Tests 1–7 | `tests/Invoicing/**` gaps | Tasks TDD por feature |

---

## Handoff — siguiente agente (plan writer)

**Objetivo del siguiente commit en esta misma rama:** crear únicamente

`docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md`

y commitearlo solo (artifact-only). Luego actualizar el PR para incluir **auditoría + plan**.

### Prompt para el plan-writer (copiar íntegro)

```markdown
# Prompt — Plan de hardening pre-producción: Invoicing / Facturapi

## Rol

Eres el agente de **planificación técnica** del repo `Parzival2103/Lebytek_Framework`
(paquete Composer `lebytek/framework`).

**Tu única entrega:** un plan de implementación ejecutable por agentes, en el estilo de
`docs/superpowers/plans/2026-08-07-invoicing-facturapi.md` (tareas con Mission / Owns /
Contract / Do not / steps checkbox / Done when).

**NO implementes código. NO abras un segundo PR. Trabaja en la rama existente**
`cursor/invoicing-facturapi-prod-hardening-3229` (ya contiene la auditoría).
Haz **un segundo commit** solo con el plan y actualiza el PR abierto de esa rama.

## Fuente de requisitos (no inventes alcance)

Lee íntegramente y trata como contrato:

`docs/audits/2026-08-08-auditoria-invoicing-facturapi.md`

Cubre **obligatoriamente** la §6 “Cambios prioritarios antes de producción” y los
tests §5 puntos 1–7 (+ redacción/denylist). Cada ítem → ≥1 tarea o out-of-scope
justificado con evidencia.

También lee:
- `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`
- `docs/superpowers/plans/2026-08-07-invoicing-facturapi.md` (A1–A10, D1–D10)
- Código real bajo `src/**/Invoicing/**`, configs, SQL, `tests/Invoicing/**`
- Patrón Payments webhooks (`StripeGateway`) si propones Facturapi-Signature

## Alcance 🔥 (del audit §6)

1. Cerrar doble timbrado por timeout en `IssueInvoiceFromSource` (A3 real).
2. Last-resort `provider_invoice_id` + excepción tipada; reconcile con retrieve remoto.
3. Enforcement `FACTURAPI_MODE` ↔ `sk_test_`/`sk_live_` + rechazo key vacía.
4. Cancel completo: claim previo, status local `canceled`, motivo/sustitución.
5. Redacción secretos ampliada + denylist `meta`.
6. Slugs RBAC + regla dura rutas consumidor autorizadas.
7. Webhooks: `Facturapi-Signature` primero; nunca payload fiscal completo en logs.
8. Validaciones CFDI mínimas (`tax_system`, `unit_key`, `payment_method`); sin catálogo SAT completo.
9. Tests CI de audit §5 puntos 1–7 (+ secretos/meta).

## Constraints

- Solo artefactos de plan en este commit (más docs de plan si el formato lo exige).
- Plataforma en `src/`, `database/`, `skeleton/`, `config/`, `tests/`, `docs/`.
- Prohibido negocio Portal / `dom_*` / editar `vendor/`.
- Domain sin tipos `Facturapi\*`.
- Vertical `invoicing` OFF by default.
- Prefijo SQL `inv_`.
- No estimar días/semanas; usa invasividad/riesgos/dependencias.
- Si el schema necesita columnas, planifica bootstrap idempotente + tests schema.
- Separar Framework vs wiring consumidor (RBAC/webhook HTTP) con claridad.

## Archivo de salida

`docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md`

Incluye: Goal, Architecture, Design amendments (A11+ si aplica), Technical debt
register, Global Constraints, File Structure, tareas numeradas dependientes,
matriz `prioridad → archivos → tarea → test → criterio`, comandos
`php tests/run.php Invoicing` (+ SkeletonPurity/Payments si toca gates).

## Git / PR

1. Preflight: ancestry `origin/main`, working tree limpia salvo tu plan.
2. Commit artifact-only, p. ej.
   `docs(plan): invoicing Facturapi production hardening`
3. `git push -u origin cursor/invoicing-facturapi-prod-hardening-3229`
4. Actualizar el PR existente (mismo branch) para que el body cite auditoría + plan.
5. No implementar código de producto.
```

---

## Definition of Done de este commit (auditoría)

- [x] Auditoría documentada en `docs/audits/2026-08-08-auditoria-invoicing-facturapi.md`
- [x] Preflight contra `origin/main` registrado
- [x] Handoff + prompt para plan-writer incluidos
- [ ] Plan (segundo commit, otro agente)
- [ ] PR con ambos artefactos
```
