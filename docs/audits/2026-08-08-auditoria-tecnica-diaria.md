# Auditoría técnica diaria — 2026-08-08

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `89a1a8901d3f868ac869a60e3c6a0f1d34f73136` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/audit-2026-08-08` |
| Timestamp UTC | trigger cron `2026-08-08T12:02:40Z` / corrida agente `2026-08-08T12:03:05Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0; origin/main resuelve)

$ git rev-parse --verify origin/main
89a1a8901d3f868ac869a60e3c6a0f1d34f73136

$ git merge-base --is-ancestor origin/main HEAD
(exit 0)

$ git status --porcelain   # antes de escribir
(vacío)
```

### `<LEGACY_REF>`

Primer candidato que resolvió:

```console
$ git rev-parse --verify --quiet 'refs/tags/archive/backoffice-api-integration^{commit}'
4789f953ef746d17bae2e6b50c85504782d306e3
```

- Tag canónico FQ: `refs/tags/archive/backoffice-api-integration` @ `4789f95`.
- `refs/remotes/origin/feature/backoffice-api-integration` no fue necesario (primer candidato ya resolvió).
- Commits exclusivos del legacy: **53** (`git rev-list origin/main..refs/tags/archive/backoffice-api-integration`).
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`LEGACY_CHECK=OK` / PASS).

---

## Resumen ejecutivo

`origin/main` avanzó desde la auditoría diaria 2026-08-07 (`22a2053` → `89a1a89`): **10 commits**. Tip de código declara semver **`1.2.7`** (trío sincronizado), pero el **único tag de release publicado sigue siendo `v1.2.3`** @ `041e402`. No existen tags `v1.2.4`…`v1.2.7`.

**Cierres materiales en tip `main` (código):**

1. **CRUD-C1 / C2 / C5 RESUELTOS** — PR `#95` AuthZ multi-canal (scope_handler, acciones fail-closed, Reportes `{prefix}.ver`). Bump declarado `1.2.6`.
2. **CRUD-C3 RESUELTO** — PR `#100` P02: lock de columna states en formularios, allowlist de select options, eliminación demo toggle. Bump declarado `1.2.7`.
3. **Vertical Invoicing/Facturapi shippeado** — PR `#99` (scaffold CFDI I, OFF by default). Auditoría pre-prod `#101` + plan hardening `#103` (0/50 steps) documentan que **no está listo para producción fiscal**.

**Brecha dominante del día:** parches AuthZ/states están en `main` y CI tip es **success**, pero **no hay tag Composer** → Portal/CRM **no pueden** consumir el fix vía `composer.lock` hasta `v1.2.6`+ (idealmente tip `v1.2.7` o el semver que se publique).

**Fronteras FPS:** intactas (sin Marketing/`LebytekApiClient`/`mkt_*` en `src/`). Invoicing es plataforma genérica + adapter Facturapi; negocio fiscal del consumidor via `InvoiceableSourceInterface`.

**Conteo esta corrida:** **5 hallazgos críticos abiertos** (CRUD-C4, CRUD-C6, REL-C1 tags ausentes, INV-E1, INV-E2); **0 medios nuevos**; deuda media arrastrada M3–M6, M10, D6 + drift de tags reservados en planes M3/M4. Suites verdes salvo 7 Integrations (MySQL ausente = entorno).

---

## Hallazgos críticos

### REL-C1 — Tip `1.2.7` sin tags `v1.2.6` / `v1.2.7` (nuevo)

| Campo | Valor |
|-------|-------|
| Evidencia | `composer.json` / `config/app.php` / `skeleton/config/app.php` = `1.2.7`; `git tag -l 'v1.2.*'` → sólo hasta `v1.2.3`; `git tag --contains 64a6877` / `60477dc` → vacío |
| Impacto | Fixes AuthZ (`#95`) y states (`#100`) **no son alcanzables** por consumidores que instalan por tag semver + `composer.lock`. Product truth rota en el eslabón release. |
| Owner | Framework / release ops |
| Estado | **Abierto** — cortar tag(s) desde tip (mínimo publicar el semver que se quiera consumir; documentar skip de `1.2.4`/`1.2.5` si M4/M3 van después) |

### CRUD-C4 — Transitions sin CAS / TOCTOU (arrastrado)

| Campo | Valor |
|-------|-------|
| Fuente | `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (#90); tip `89a1a89` sin PR de remediación |
| Owner | Framework |
| Estado | **Abierto** — fuera de p01/p02 |

### CRUD-C6 — Uploads sin allowlist obligatoria + `public_path` sin normalizar (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia tip | `UploadValidator::assertValid` acepta cuando `allowedExtensions` es null/vacío (test explícito «acepta cuando no hay lista blanca»); `FileUploadService` concatena `PUBLIC_PATH . '/' . trim($cfg->directorio)` sin `realpath`/bloqueo de `..` |
| Owner | Framework |
| Estado | **Abierto** — orden remediación 3 del programa CRUD |

### INV-E1 — Doble timbrado posible tras timeout post-create (elevado desde #101)

| Campo | Valor |
|-------|-------|
| Fuente | `docs/audits/2026-08-08-auditoria-invoicing-facturapi.md` E1; plan hardening `2026-08-08-invoicing-facturapi-production-hardening.md` **0/50** |
| Archivo | `IssueInvoiceFromSource::handle` — `releaseClaim` si create remoto ambiguo |
| Mitigación actual | `vertical.invoicing=false`, `FACTURAPI_ENABLED=false` |
| Owner | Framework |
| Estado | **Abierto** — no habilitar Facturapi en prod hasta hardening |

### INV-E2 — Fallo dual markIssued/markNeedsReconcile deja id irrecuperable (elevado desde #101)

| Campo | Valor |
|-------|-------|
| Fuente | audit invoicing E2; mismo plan hardening 0/50 |
| Owner | Framework |
| Estado | **Abierto** |

### CRUD-C1 / C2 / C5 / C3 — resueltos en tip (código)

| ID | Título | Estado tip `89a1a89` | Release |
|----|--------|----------------------|---------|
| CRUD-C1 | IDOR `scope_handler` | **RESUELTO** `#95` | pendiente tag (REL-C1) |
| CRUD-C2 | Acción sin `permission` | **RESUELTO** `#95` | pendiente tag |
| CRUD-C5 | `CrudReporteDataSource` sin `{resource}.ver` | **RESUELTO** `#95` | pendiente tag |
| CRUD-C3 | Columna states editable + demo toggle | **RESUELTO** `#100` | pendiente tag |

**Nota:** «resuelto en `main`» ≠ «consumible en Portal». Portal/CRM **dependen** de un tag Framework nuevo + bump de `composer.lock` para AuthZ/states.

### Deuda crítica histórica (estado actualizado)

| ID | Hallazgo | Estado 2026-08-08 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` | **RESUELTO** — #36 | Framework |
| C2 / #21 | Stripe subscription | **Framework RESUELTO** (`v1.2.3`+); ops Portal no verificable (M6) | Framework ✅ / Portal ⏳ |
| C3 / #23 | Bootstrap marketing | Portal; issue Framework CLOSED | Portal |

---

## Hallazgos medios

### M1 / M2 / M7 / M8 / M9 / D7 — históricos resueltos (sin regresión)

| ID | Estado |
|----|--------|
| M1 semver trío | **RESUELTO** en tip (`1.2.7` sync); `PlatformVersionSemverTest` parte de Docs PASS — **pero** falta tag (ver REL-C1) |
| M2 `.env.example` Portal keys | **RESUELTO** — #62; tip añade keys Facturapi OFF (`FACTURAPI_ENABLED=false`) — frontera OK |
| M7 audit PR sin merge | **RESUELTO** — #54 |
| M8 / D5 docs ops legacy | **RESUELTO** |
| M9 dompdf | **RESUELTO** — `composer audit --no-dev` limpio |
| D7 CI Actions | **RESUELTO** — tip run `31235093747` **success** @ `89a1a89` |

Hygiene arrastrada: `composer validate --no-check-publish` advierte content-hash del lock (exit 0 en esta corrida con warnings). CI usa `--no-check-lock`.

### M3 — CRUD/Calendario sin `RbacMiddleware` router (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Rutas `/crud/{resource}` y `/calendario/{key}` sólo `AuthMiddleware`; plan `2026-08-06-audit-crud-rbac-router.md` **0/5** tasks |
| Drift | Plan aún cita tag `1.2.5`; tip ya está en `1.2.7` sin ese feature — retarget semver al cortar release |
| Owner | Framework |
| Estado | **Abierto** |

### M4 — API `/api/*` sesión / sin health público (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | `routes/api.php`: grupo `/api` + `AuthMiddleware`; sólo `GET /api/ping`; sin `GET /api/health` público. Plan 08-05 **0/5**. |
| Drift | Plan citaba `v1.2.4` — número saltado por bumps AuthZ/states |
| Owner | Framework |
| Estado | **Abierto** |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Workaround `administracion.ver` en `routes/web.php` / skeleton; `rg permisos.gestionar database/` → 0 |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh` → 404 / GraphQL fail sobre `Lebytek_Portal` |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** |

### M10 — Hueco auditorías 2026-08-03..05 (arrastrado / proceso)

| Campo | Valor |
|-------|-------|
| Evidencia | Siguen ausentes `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`; cadena 06–08 restaurada |
| Owner | Ops / automation |
| Estado | **Abierto** |

### D6 — `skeleton.lebytek.com` pendiente (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | `docs/ENVIRONMENTS.md` — skeleton pendiente; crm.lebytek.com documentado live |
| Owner | Ops |
| Estado | **Abierto** |

### INV (medios de #101, no re-enumerados como nuevos)

Carry: S1/S2 mode↔key, S3/S6 redacción/meta, S7 RBAC vacío en manifiesto, E3 cancel no idempotente, M7 reconcile sólo local, plan hardening **0/50**. Owner Framework. Blast radius mitigado mientras vertical OFF.

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-07-auditoria-tecnica-diaria.md` (PR `#96`) + audit crítica CRUD `#90` + audit invoicing `#101`.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO; ops Portal N/V |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal |
| CRUD-C1 / C2 / C5 AuthZ | #90 | **RESUELTO en tip** `#95` — release bloqueado por REL-C1 |
| CRUD-C3 states form | #90 | **RESUELTO en tip** `#100` — release bloqueado por REL-C1 |
| CRUD-C4 CAS/TOCTOU | #90 | **Abierto** |
| CRUD-C6 uploads | #90 | **Abierto** |
| REL-C1 tags `v1.2.6`/`v1.2.7` | **2026-08-08** | **Abierto** (nuevo) |
| INV-E1 / INV-E2 | #101 | **Abierto** (elevados a críticos diarios) |
| M1–M2 / M7–M9 / D7 | previos | RESUELTOS |
| M3 CRUD RBAC router | 2026-07-27 | **Abierto** — plan 0/5; tag target obsoleto |
| M4 API health | 2026-07-27 | **Abierto** — plan 0/5; tag target obsoleto |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M10 hueco audits 03–05 | 2026-08-06 | **Abierto** (proceso) |
| D6 skeleton.lebytek.com | inventario | **Abierto** |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `89a1a89` | Auth, RBAC, CRUD Engine, Invoicing vertical, Payments genérico, install, skeleton, CI |
| Release semver | Tag publicado `v1.2.3` @ `041e402`; tip declara `1.2.7` **sin tag** | Cortar `v1.2.7` (o política explícita) — REL-C1 |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, wiring Facturapi routes/RBAC, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` |
| CRM | `Lebytek_CRM` (doc) | Consume Framework vía lock |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API / lifecycle |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Dependencias cruzadas:**

- AuthZ/states: **Framework tip** ya parcheado → **falta tag** ≥ declarado → bump `composer.lock` en Portal/CRM. Sin tag, Portal **no** recibe el fix aunque `main` lo tenga.
- Invoicing: código plataforma Framework; habilitación + rutas RBAC + `InvoiceableSource` = consumidor. **No** activar en Portal hasta tag post-hardening.
- `mkt_leads` afterListRows: Portal (plan 0/5) depende Framework ≥ `v1.2.2` (ya en `v1.2.3`); confirmación lock bloqueada por M6.
- M3/M4: Framework; retarget tags a `1.2.8`+ tras REL-C1.
- Stripe QA: Portal/VPS.

---

## Cambios recientes en `main` (desde auditoría 2026-08-07 @ `22a2053`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `da3ab58` / #96 | Auditoría diaria 2026-08-07 | Ancla audit anterior |
| `64a6877` / #95 | AuthZ C1+C2+C5 | **Cierra CRUD-C1/C2/C5** en tip; bump `1.2.6` |
| `b57bd60` / #94 | Restructure plan Facturapi | Docs |
| `6158f7d` / #98 | Plan CRUD p02 | Input `#100` |
| `21edf26` / #99 | feat invoicing Facturapi | Nueva vertical plataforma OFF |
| `60477dc` / #100 | States P02 C3/G15/G6 | **Cierra CRUD-C3**; bump `1.2.7` |
| `7fd06c5`+`f07c6d1` / #101 | Audit + plan hardening Facturapi | Eleva INV-E1/E2 |
| `89a1a89` / #103 | Enmienda plan external_id / orphan | Docs plan |

PRs abiertos Framework: **0**. Issues abiertos Framework: **0**.

Delta código relevante: `src/**/Invoicing/**`, `src/Application/Services/Crud{ScopeResolver,ActionService,ConfigValidator,DataService,FieldValidationService}.php`, `src/Application/Reporte/CrudReporteDataSource.php`, `database/schema/modules/invoicing.sql`, `config/invoicing.php`, `config/modules/invoicing.php`, tests Crud/Invoicing/Security/Reporte.

---

## Fronteras del paquete

- `src/` — sin `LebytekApiClient`, sin `App\Domain\Marketing`, sin `mkt_leads`.
- `config/vertical.php` / skeleton — `marketing=false`, `payments=false`, `invoicing=false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`.
- Payments genérico: `src/Domain/Payments/` intacto.
- Invoicing: capas Domain/Application/Infrastructure + SQL módulo — plataforma; consumidor aporta source/RBAC/UI.
- Bootstrap SQL módulos resuelve vía `PackagePaths` desde el paquete (`database/schema/modules/*.sql`); ausencia de espejo bajo `skeleton/database/schema/modules/` es el modelo path-repo, no regresión de paridad harness↔routes (Install **52/52**).
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Root `.env.example`: keys Facturapi presentes pero OFF — no keys Marketing Portal.

**Conclusión:** no se coló negocio Portal. Hallazgos dominantes = release sin tag + CRUD residual (C4/C6) + invoicing pre-prod.

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| AuthZ/states en `main` sin tag Composer | **Alta** | **REL-C1 abierto** |
| CRUD-C4 TOCTOU / CRUD-C6 uploads | Alta | **Abierto** en tip |
| Doble timbrado Facturapi (INV-E1/E2) | Alta si se habilita | Mitigado por vertical OFF; plan 0/50 |
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — 53 commits no ancestros |
| Stripe sin QA Portal | Alta | Mitigado Framework OFF |
| Portal sin bump post-tag AuthZ | Alta (post-release) | Depende REL-C1 + M6 |
| Sin `/api/health` público | Media | M4 |
| `skeleton.lebytek.com` | Media | D6 |
| Hueco audits 03–05 | Media (proceso) | M10 |
| Portal prod SHA desconocido | Media | M6 |
| Planes M3/M4 con tags obsoletos | Baja–Media | Retarget al implementar |
| `composer` content-hash warning | Baja | Hygiene |

---

## Archivos involucrados

Delta `22a2053..89a1a89` (relevantes):

- `src/Application/Services/CrudScopeResolver.php`, `CrudActionService.php`, `CrudConfigValidator.php`, `CrudDataService.php`, `CrudFieldValidationService.php`
- `src/Application/Reporte/CrudReporteDataSource.php`
- `src/{Domain,Application,Infrastructure}/Invoicing/**` (nuevo)
- `config/invoicing.php`, `config/modules/invoicing.php`, `config/vertical.php`, `.env.example`
- `database/schema/modules/invoicing.sql` (+ espejos config skeleton)
- `tests/Crud/**`, `tests/Invoicing/**`, `tests/Security/CrudActionOwnershipTest.php`, `tests/Reporte/*Authz*`
- `docs/audits/2026-08-07-auditoria-tecnica-diaria.md`, `2026-08-08-auditoria-invoicing-facturapi.md`, `2026-08-08-auditoria-plan-invoicing-facturapi-hardening.md`
- Specs/planes invoicing + CRUD p01/p02
- Artefacto nuevo: `docs/audits/2026-08-08-auditoria-tecnica-diaria.md` (este archivo)

Re-inspeccionados abiertos: `routes/api.php`, `routes/web.php`, `UploadValidator.php`, `FileUploadService.php`, `src/Domain/Payments/*`.

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Ausente al inicio → instalado ad-hoc `php8.3-cli` (+ xml, mbstring, curl, zip, sqlite, mysql) — **PHP 8.3.6** |
| Composer / vendor | Ausente → `/tmp/composer.phar` 2.10.2 + `composer install`; **sin** `composer.phar` en el árbol del repo |
| `ext-pdo_mysql` | Presente tras install |
| Servidor MySQL | **Ausente** — 7 tests Integrations `Connection refused` |
| Acceso Portal (`gh`) | **404** — bloqueador entorno (M6) |
| GitHub Actions tip | **success** @ `89a1a89` (run `31235093747`) |
| Issues / PRs abiertos Framework | **0** / **0** |

### Comandos ejecutados

```console
$ php tests/run.php
701 passed, 7 failed
exit code: 1

$ php tests/run.php Kernel
47 passed, 0 failed
exit code: 0

$ php tests/run.php Payments
21 passed, 0 failed
exit code: 0

$ php tests/run.php SkeletonPurity
13 passed, 0 failed
exit code: 0

$ php tests/run.php Install
52 passed, 0 failed
exit code: 0

$ php tests/run.php Docs
33 passed, 0 failed
exit code: 0

$ php tests/run.php Crud
218 passed, 0 failed
exit code: 0

$ php tests/run.php Invoicing
60 passed, 0 failed
exit code: 0

$ php tests/run.php Security
35 passed, 0 failed
exit code: 0

$ php tests/run.php Reporte
56 passed, 0 failed
exit code: 0

$ php /tmp/composer.phar audit --no-dev
No security vulnerability advisories found.
exit code: 0

$ php /tmp/composer.phar validate --no-check-publish
./composer.json is valid, but with a few warnings
# Lock file errors — The lock file is not up to date…
exit code: 0
```

Contadores: suite completa **701 passed / 7 failed** (vs 591/7 el 08-07: + Invoicing 60, + Crud AuthZ/states, + Docs). Suites Kernel/Payments/SkeletonPurity/Install/Docs/Crud/Invoicing/Security/Reporte **verdes**. Ningún comando descubrió cero tests.

### Análisis de fallos

**0 fails de código** en tip para suites no-Integrations.

**7 fails de entorno (Integrations / MySQL ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `Connection refused`

**Clasificación:** bloqueador de entorno local. CI `platform-integration-gates` cubre el hueco (tip green).

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Prioridad release inmediata:** publicar tag Composer desde tip (`v1.2.7` recomendado, o política explícita `v1.2.6`+`v1.2.7`) — cierra REL-C1 y desbloquea consumo Portal/CRM de AuthZ/states. Actualizar locks consumidores.
3. **No habilitar** `vertical.invoicing` / `FACTURAPI_ENABLED` en ningún deploy hasta ejecutar plan hardening (`#103`, 0/50) — INV-E1/E2.
4. **Siguiente lote CRUD:** C6 uploads (allowlist obligatoria + path normalize), luego C4 CAS — según programa `#90`.
5. **AUTOMATION-07 / humano:** retarget planes M4/M3 a semver ≥`1.2.8` (reservas `1.2.4`/`1.2.5` ya consumidas/saltadas); implementar health público y router RBAC.
6. **Portal/ops:** conceder lectura `gh` a `Lebytek_Portal` (M6); tras tag, bump lock; plan mkt_leads; QA Stripe. D6 skeleton host sigue pendiente.
7. **Automation:** MySQL local o confiar en CI integration; no omitir paso 00 (M10).

**Veredicto:** fronteras FPS sanas; AuthZ C1/C2/C5 y states C3 **cerrados en tip**; **release roto** (REL-C1: tip `1.2.7` sin tag — consumidores atrapados en `v1.2.3`); **2 críticos CRUD residuales** (C4/C6); **2 críticos invoicing** pre-prod (E1/E2, vertical OFF); **0 medios nuevos**; deuda M3–M6/M10/D6 arrastrada; Integrations locales bloqueadas por MySQL (CI las cubre); Portal SHA no inspeccionable.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
