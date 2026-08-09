# Auditoría técnica diaria — 2026-08-09

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `5bf0863f45116b3e574a085c0dca2bed46ed983a` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde 2026-08-02) |
| Rama generada | `automation/audit-2026-08-09` |
| Timestamp UTC | trigger cron `2026-08-09T12:01:01Z` / corrida agente `2026-08-09T12:02:18Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0; origin/main resuelve)

$ git rev-parse --verify origin/main
5bf0863f45116b3e574a085c0dca2bed46ed983a

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

`origin/main` avanzó desde la auditoría diaria 2026-08-08 (`89a1a89` → `5bf0863`): **1 commit** — merge del propio artefacto de auditoría `#104`. **Sin delta de código plataforma** en tip.

Tip declara semver **`1.2.7`** (trío `composer.json` / `config/app.php` / `skeleton/config/app.php` sincronizado). **Único tag de release publicado sigue siendo `v1.2.3`** @ `041e402`. No existen tags `v1.2.4`…`v1.2.7`. AuthZ (`#95`) y states (`#100`) siguen en tip **sin tag Composer** → consumidores atrapados en `v1.2.3` (**REL-C1**).

**Progreso de pipeline (fuera de `main` aún):** PR abierto `#105` (`docs(spec): release semver tag REL-C1 2026-08-08`, rama `automation/spec-2026-08-08`, `MERGEABLE`, no draft) contiene spec + plan de publicación `v1.2.7` y reconciliaciones de planes. **Ninguno de esos archivos está en `origin/main`** (`REL_C1_PLAN_NOT_ON_MAIN`). Tag aún no publicado — REL-C1 permanece **abierto**.

**Fronteras FPS:** intactas (sin Marketing / `LebytekApiClient` / `mkt_*` en `src/`). Verticals `marketing`/`payments`/`invoicing` = `false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`.

**Conteo esta corrida:** **0 hallazgos críticos nuevos**; **0 medios nuevos**. **5 críticos abiertos arrastrados** (CRUD-C4, CRUD-C6, REL-C1, INV-E1, INV-E2). Deuda media M3–M6, M10, D6 + drift tags reservados en planes M3/M4. Suites verdes salvo 7 Integrations (MySQL ausente = entorno). CI tip `main` **success** (run `31257420492`).

---

## Hallazgos críticos

### REL-C1 — Tip `1.2.7` sin tags `v1.2.6` / `v1.2.7` (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | `composer.json` / `config/app.php` / `skeleton/config/app.php` = `1.2.7`; `git tag -l 'v1.2.*'` → sólo hasta `v1.2.3`; `git tag --contains 64a6877` / `60477dc` → vacío |
| Pipeline | Spec/plan en PR `#105` (abierto, no mergeado; no en tip `main`) |
| Impacto | Fixes AuthZ (`#95`) y states (`#100`) **no son alcanzables** por consumidores que instalan por tag semver + `composer.lock`. Product truth rota en el eslabón release. |
| Owner | Framework / release ops |
| Estado | **Abierto** — cortar tag(s) desde tip tras merge del plan `#105` (mínimo publicar el semver a consumir; documentar skip de `1.2.4`/`1.2.5`/`1.2.6` según release notes del spec) |

### CRUD-C4 — Transitions sin CAS / TOCTOU (arrastrado)

| Campo | Valor |
|-------|-------|
| Fuente | `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (#90) |
| Evidencia tip `5bf0863` | `CrudTransitionService::apply` lee estado del `$record` en memoria, autoriza, luego `updateRecord(...)` **sin** predicado `WHERE estado = :from` / versión — TOCTOU intacto |
| Owner | Framework |
| Estado | **Abierto** — fuera de p01/p02 |

### CRUD-C6 — Uploads sin allowlist obligatoria + `public_path` sin normalizar (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia tip | `UploadValidator::assertValid` acepta cuando `allowedExtensions` es null/vacío (test explícito «acepta cuando no hay lista blanca»); `FileUploadService` concatena `PUBLIC_PATH . '/' . trim($cfg->directorio)` sin `realpath`/bloqueo de `..` |
| Owner | Framework |
| Estado | **Abierto** — orden remediación 3 del programa CRUD |

### INV-E1 — Doble timbrado posible tras timeout post-create (arrastrado)

| Campo | Valor |
|-------|-------|
| Fuente | `docs/audits/2026-08-08-auditoria-invoicing-facturapi.md` E1; plan hardening `2026-08-08-invoicing-facturapi-production-hardening.md` **0/50** en tip |
| Archivo | `IssueInvoiceFromSource::handle` — `releaseClaim` si create remoto ambiguo (línea ~73 tip) |
| Mitigación actual | `vertical.invoicing=false`, `FACTURAPI_ENABLED=false` |
| Owner | Framework |
| Estado | **Abierto** — no habilitar Facturapi en prod hasta hardening |

### INV-E2 — Fallo dual markIssued/markNeedsReconcile deja id irrecuperable (arrastrado)

| Campo | Valor |
|-------|-------|
| Fuente | audit invoicing E2; mismo plan hardening 0/50 |
| Evidencia tip | `catch` traga fallo de `markNeedsReconcile` y lanza `InvoiceNeedsReconcile` sin persistir id local |
| Owner | Framework |
| Estado | **Abierto** |

### CRUD-C1 / C2 / C5 / C3 — resueltos en tip (código); release pendiente

| ID | Título | Estado tip `5bf0863` | Release |
|----|--------|----------------------|---------|
| CRUD-C1 | IDOR `scope_handler` | **RESUELTO** `#95` | pendiente tag (REL-C1) |
| CRUD-C2 | Acción sin `permission` | **RESUELTO** `#95` | pendiente tag |
| CRUD-C5 | `CrudReporteDataSource` sin `{resource}.ver` | **RESUELTO** `#95` | pendiente tag |
| CRUD-C3 | Columna states editable + demo toggle | **RESUELTO** `#100` | pendiente tag |

**Nota:** «resuelto en `main`» ≠ «consumible en Portal». Portal/CRM **dependen** de un tag Framework nuevo + bump de `composer.lock`.

### Deuda crítica histórica (estado actualizado)

| ID | Hallazgo | Estado 2026-08-09 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` | **RESUELTO** — #36; tip 0 archivos | Framework |
| C2 / #21 | Stripe subscription | **Framework RESUELTO** (`v1.2.3`+); ops Portal no verificable (M6) | Framework ✅ / Portal ⏳ |
| C3 / #23 | Bootstrap marketing | Portal; issue Framework CLOSED | Portal |

---

## Hallazgos medios

### M1 / M2 / M7 / M8 / M9 / D7 — históricos resueltos (sin regresión)

| ID | Estado |
|----|--------|
| M1 semver trío | **RESUELTO** en tip (`1.2.7` sync); `PlatformVersionSemverTest` parte de Docs PASS — **pero** falta tag (REL-C1) |
| M2 `.env.example` Portal keys | **RESUELTO** — #62; tip keys Facturapi OFF — frontera OK |
| M7 audit PR sin merge | **RESUELTO** — #54 |
| M8 / D5 docs ops legacy | **RESUELTO** |
| M9 dompdf | **RESUELTO** — `composer audit --no-dev` limpio (dompdf v3.1.6) |
| D7 CI Actions | **RESUELTO** — tip run `31257420492` **success** @ `5bf0863` |

Hygiene: `composer validate --no-check-publish` exit **2** en esta corrida (warnings + «lock file is not up to date»); CI usa `--no-check-lock`. No reabre M1.

### M3 — CRUD/Calendario sin `RbacMiddleware` router (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Rutas `/crud/{resource}` y `/calendario/{key}` sólo bajo grupo `AuthMiddleware` (harness + skeleton); plan `2026-08-06-audit-crud-rbac-router.md` **0/40** checkboxes |
| Drift | Plan aún cita tag `1.2.5`; tip ya está en `1.2.7` sin ese feature — retarget semver al cortar release (≥`1.2.8` tras REL-C1) |
| Owner | Framework |
| Estado | **Abierto** |

### M4 — API `/api/*` sesión / sin health público (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | `routes/api.php`: grupo `/api` + `AuthMiddleware`; sólo `GET /api/ping`; sin `GET /api/health` público. Plan `2026-08-05-audit-api-health-public.md` **0/38**. |
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
| Evidencia | `gh` → 404 / GraphQL fail sobre `Lebytek_Portal` (reconfirmado 2026-08-09) |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** |

### M10 — Hueco auditorías 2026-08-03..05 (arrastrado / proceso)

| Campo | Valor |
|-------|-------|
| Evidencia | Siguen ausentes `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`; cadena 06–09 en curso |
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

**Medios nuevos esta corrida:** ninguno.

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-08-auditoria-tecnica-diaria.md` (PR `#104` @ `5bf0863`) + audit crítica CRUD `#90` + audit invoicing `#101`.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO; ops Portal N/V |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal |
| CRUD-C1 / C2 / C5 AuthZ | #90 | **RESUELTO en tip** `#95` — release bloqueado por REL-C1 |
| CRUD-C3 states form | #90 | **RESUELTO en tip** `#100` — release bloqueado por REL-C1 |
| CRUD-C4 CAS/TOCTOU | #90 | **Abierto** (sin cambio) |
| CRUD-C6 uploads | #90 | **Abierto** (sin cambio) |
| REL-C1 tags `v1.2.6`/`v1.2.7` | 2026-08-08 | **Abierto** — pipeline `#105` spec/plan pendiente merge+ejecución |
| INV-E1 / INV-E2 | #101 | **Abierto** (hardening 0/50) |
| M1–M2 / M7–M9 / D7 | previos | RESUELTOS |
| M3 CRUD RBAC router | 2026-07-27 | **Abierto** — plan 0/40; tag target obsoleto |
| M4 API health | 2026-07-27 | **Abierto** — plan 0/38; tag target obsoleto |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M10 hueco audits 03–05 | 2026-08-06 | **Abierto** (proceso) |
| D6 skeleton.lebytek.com | inventario | **Abierto** |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `5bf0863` | Auth, RBAC, CRUD Engine, Invoicing vertical, Payments genérico, install, skeleton, CI |
| Release semver | Tag publicado `v1.2.3` @ `041e402`; tip declara `1.2.7` **sin tag** | Cortar `v1.2.7` (plan `#105`) — REL-C1 |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, wiring Facturapi routes/RBAC, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` |
| CRM | `Lebytek_CRM` (doc) | Consume Framework vía lock |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API / lifecycle |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Dependencias cruzadas:**

- AuthZ/states: **Framework tip** ya parcheado → **falta tag** ≥ declarado → bump `composer.lock` en Portal/CRM. Sin tag, Portal **no** recibe el fix aunque `main` lo tenga. Fix Portal **depende** de tag Framework nuevo.
- Invoicing: código plataforma Framework; habilitación + rutas RBAC + `InvoiceableSource` = consumidor. **No** activar en Portal hasta tag post-hardening.
- `mkt_leads` afterListRows: Portal (plan 0/5) depende Framework ≥ `v1.2.2` (ya en `v1.2.3`); confirmación lock bloqueada por M6.
- M3/M4: Framework; retarget tags a `1.2.8`+ tras REL-C1.
- Stripe QA: Portal/VPS.

---

## Cambios recientes en `main` (desde auditoría 2026-08-08 @ `89a1a89`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `5bf0863` / #104 | Auditoría diaria 2026-08-08 | Ancla audit anterior mergeada; **único** avance de tip |

PRs abiertos Framework: **1** (`#105` REL-C1 spec/plan, no draft, `MERGEABLE`). Issues abiertos Framework: **0**.

Delta código plataforma `89a1a89..5bf0863`: **ninguno** (sólo `docs/audits/2026-08-08-auditoria-tecnica-diaria.md`).

---

## Fronteras del paquete

- `src/` — sin `LebytekApiClient`, sin `App\Domain\Marketing`, sin `mkt_leads` (`rg` vacío).
- `config/vertical.php` / skeleton — `marketing=false`, `payments=false`, `invoicing=false`; `STRIPE_ENABLED=false` (root), `FACTURAPI_ENABLED=false`.
- Payments genérico: `src/Domain/Payments/` intacto (`PaymentGatewayInterface`, `SupportsSubscriptions`, VOs, event log).
- Invoicing: capas Domain/Application/Infrastructure + SQL módulo — plataforma; consumidor aporta source/RBAC/UI.
- Bootstrap SQL módulos vía `PackagePaths` (`database/schema/modules/*.sql` incluye `invoicing.sql`); ausencia de espejo bajo `skeleton/database/schema/modules/` = modelo path-repo.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Root `.env.example`: keys Facturapi presentes pero OFF — no keys Marketing Portal.

**Conclusión:** no se coló negocio Portal. Hallazgos dominantes = release sin tag + CRUD residual (C4/C6) + invoicing pre-prod. Sin hallazgos nuevos de frontera.

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| AuthZ/states en `main` sin tag Composer | **Alta** | **REL-C1 abierto**; plan `#105` pendiente |
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
| `composer validate` lock warning (exit 2) | Baja | Hygiene; CI `--no-check-lock` |

---

## Archivos involucrados

Delta `89a1a89..5bf0863`:

- `docs/audits/2026-08-08-auditoria-tecnica-diaria.md` (merge `#104`)

Re-inspeccionados (sin cambio vs audit 08-08):

- `composer.json`, `config/app.php`, `skeleton/config/app.php` (semver `1.2.7`)
- `routes/api.php`, `routes/web.php`, `skeleton/routes/{api,web}.php`
- `src/Application/Services/{UploadValidator,FileUploadService,CrudTransitionService}.php`
- `src/Application/Invoicing/IssueInvoiceFromSource.php`
- `src/Domain/Payments/*`, `config/vertical.php`, `.env.example`
- `database/schema/modules/*`, planes M3/M4/INV hardening
- Artefacto nuevo: `docs/audits/2026-08-09-auditoria-tecnica-diaria.md` (este archivo)

Fuera de tip (PR `#105`, no en `main`): `docs/superpowers/specs/2026-08-08-audit-release-semver-tag-design.md`, `docs/superpowers/plans/2026-08-08-audit-release-semver-tag.md`.

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
| GitHub Actions tip | **success** @ `5bf0863` (run `31257420492`) |
| Issues / PRs abiertos Framework | **0** / **1** (`#105`) |

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

$ php tests/run.php Archivos
20 passed, 0 failed
exit code: 0

$ php tests/run.php Integrations
46 passed, 7 failed
exit code: 1

$ php /tmp/composer.phar audit --no-dev
No security vulnerability advisories found.
exit code: 0

$ php /tmp/composer.phar validate --no-check-publish
./composer.json is valid, but with a few warnings
# Lock file errors — The lock file is not up to date…
exit code: 2
```

Contadores: suite completa **701 passed / 7 failed** (idéntico a 2026-08-08; sin delta de código). Suites Kernel/Payments/SkeletonPurity/Install/Docs/Crud/Invoicing/Security/Reporte/Archivos **verdes**. Ningún comando descubrió cero tests.

### Análisis de fallos

**0 fails de código** en tip para suites no-Integrations.

**7 fails de entorno (Integrations / MySQL ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `Connection refused`

**Clasificación:** bloqueador de entorno local. CI `platform-integration-gates` cubre el hueco (tip green @ `31257420492`).

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Prioridad release inmediata:** mergear/ejecutar plan REL-C1 (`#105`) y publicar tag Composer desde tip (`v1.2.7` recomendado) — cierra REL-C1 y desbloquea consumo Portal/CRM de AuthZ/states. Actualizar locks consumidores.
3. **No habilitar** `vertical.invoicing` / `FACTURAPI_ENABLED` en ningún deploy hasta ejecutar plan hardening (**0/50**) — INV-E1/E2.
4. **Siguiente lote CRUD:** C6 uploads (allowlist obligatoria + path normalize), luego C4 CAS — según programa `#90`.
5. **AUTOMATION-07 / humano:** retarget planes M4/M3 a semver ≥`1.2.8` (reservas `1.2.4`/`1.2.5` ya consumidas/saltadas); implementar health público y router RBAC.
6. **Portal/ops:** conceder lectura `gh` a `Lebytek_Portal` (M6); tras tag, bump lock; plan mkt_leads; QA Stripe. D6 skeleton host sigue pendiente.
7. **Automation:** MySQL local o confiar en CI integration; no omitir paso 00 (M10).

**Veredicto:** **sin hallazgos nuevos**. Fronteras FPS sanas; tip estable vs ayer (sólo merge audit `#104`); AuthZ C1/C2/C5 y states C3 **cerrados en tip**; **release sigue roto** (REL-C1 — pipeline `#105` avanzó a spec/plan pero tag no publicado); **2 críticos CRUD residuales** (C4/C6); **2 críticos invoicing** pre-prod (E1/E2, vertical OFF); **0 medios nuevos**; deuda M3–M6/M10/D6 arrastrada; Integrations locales bloqueadas por MySQL (CI las cubre); Portal SHA no inspeccionable.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
