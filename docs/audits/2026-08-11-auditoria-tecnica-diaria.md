# Auditoría técnica diaria — 2026-08-11

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `c8221961e29a07e928a4a42a3a9e3ad88863f0a5` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde 2026-08-02) |
| Rama generada | `automation/audit-2026-08-11` |
| Timestamp UTC | trigger cron `2026-08-11T12:01:06Z` / corrida agente `2026-08-11T12:03:47Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0; origin/main resuelve)

$ git rev-parse --verify origin/main
c8221961e29a07e928a4a42a3a9e3ad88863f0a5

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

`origin/main` avanzó con fuerza desde la auditoría diaria 2026-08-09 (`5bf0863` → `c822196`): **7 commits** de tip (audit `#106` + REL-C1 docs `#110` + uploads C6 `#111` + Facturapi Tasks 1–10 `#109`/`#112` + bump `1.2.9` `#113` + M3/M4 patch `1.2.10` `#114`). **No hubo auditoría diaria 2026-08-10** (hueco de proceso; M10 ampliado).

Tip declara semver **`1.2.10`** (trío `composer.json` / `config/app.php` / `skeleton/config/app.php` sincronizado). Tags publicados: **`v1.2.7`…`v1.2.10`**. El árbol de `v1.2.10` @ `1377dda` **coincide** con el árbol del merge tip `c822196` (`git rev-parse` tree SHA idéntico) — consumidores pueden instalar `v1.2.10`.

**Cierres confirmados en tip + tag:** REL-C1, CRUD-C6, INV-E1, INV-E2, M3 (`CrudRbacMiddleware` en CRUD/calendario), M4 (`GET /api/health` público). AuthZ CRUD-C1/C2/C5 y states C3 ya estaban en tip desde `#95`/`#100` y ahora son consumibles vía tags.

**Crítico residual:** CRUD-C4 (CAS/TOCTOU en transitions) sigue abierto. Verticals `marketing`/`payments`/`invoicing` = `false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`.

**Conteo esta corrida:** **0 hallazgos críticos nuevos**; **1 medio nuevo** (M11 — contaminación de sesión en suite monolítica). **1 crítico abierto arrastrado** (CRUD-C4). Suites aisladas verdes; suite completa local 789/9 (7 MySQL env + 2 M11). CI tip `main` **success** (run `31456605262`).

---

## Hallazgos críticos

### CRUD-C4 — Transitions sin CAS / TOCTOU (arrastrado)

| Campo | Valor |
|-------|-------|
| Fuente | `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (#90) |
| Evidencia tip `c822196` | `CrudTransitionService::apply` lee `$from` del `$record` en memoria, autoriza, luego `updateRecord(...)` **sin** predicado `WHERE estado = :from` / versión — TOCTOU intacto |
| Owner | Framework |
| Estado | **Abierto** — único crítico de código residual en tip |

### REL-C1 — Tip sin tags (estado actualizado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia | Tags `v1.2.7`…`v1.2.10` existen; tip declara `1.2.10`; tree de `v1.2.10` ≡ tip merge `#114`; plan/docs `#110` en `main`; Docs gate REL-C1 + CI job «Fetch declared release tag» verdes |
| Owner | Framework / release ops |
| Estado | **RESUELTO** — no reabrir salvo regresión tip≠tag |

### CRUD-C6 — Uploads allowlist + path jail (estado actualizado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia | `#111` / tag `v1.2.8`; `UploadValidator` fail-closed si allowlist null/vacía; denylist global; `FileUploadService::resolvePublicUploadDirectory` con `realpath`, bloqueo `..`, jail bajo `uploads/`; tests Security C6 PASS |
| Owner | Framework |
| Estado | **RESUELTO** en tip + `v1.2.8`+ |

### INV-E1 / INV-E2 — Facturapi hardening (estado actualizado → **RESUELTO** en tip)

| ID | Evidencia tip `c822196` | Estado |
|----|-------------------------|--------|
| INV-E1 | Tras `createInvoked`, fallo remoto → `InvoiceAmbiguousCreate` **sin** `releaseClaim`; claim se conserva | **RESUELTO** (`#109`/`#112`; plan hardening **50/51** checkboxes — el `[ ]` residual es instrucción del prompt, no task) |
| INV-E2 | Tras create OK + fallo `markIssued`: `markNeedsReconcile` + fallback `attachProviderInvoiceId` con `external_id` | **RESUELTO** |

Mitigación residual: vertical `invoicing=false` / `FACTURAPI_ENABLED=false` sigue OFF por defecto — correcto hasta wiring Portal/QA.

### CRUD-C1 / C2 / C5 / C3 — resueltos en tip **y** consumibles por tag

| ID | Estado tip + release |
|----|----------------------|
| CRUD-C1 / C2 / C5 AuthZ | RESUELTO `#95` — disponible desde tags post-`v1.2.7` |
| CRUD-C3 states form | RESUELTO `#100` — idem |

### Deuda crítica histórica

| ID | Hallazgo | Estado 2026-08-11 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` | **RESUELTO** — #36 | Framework |
| C2 / #21 | Stripe subscription | **Framework RESUELTO** (`v1.2.3`+); ops Portal no verificable (M6) | Framework ✅ / Portal ⏳ |
| C3 / #23 | Bootstrap marketing | Portal; issue Framework CLOSED | Portal |

---

## Hallazgos medios

### M11 — Contaminación de sesión en `php tests/run.php` monolítico (**nuevo**)

| Campo | Valor |
|-------|-------|
| Evidencia | Suite completa: `AuthMiddleware blocks unauthenticated /api/ping` → expected 302 got 200; `Router dispatch… /api/ping` falla con sesión residual. `tests/Auth/*` deja `$_SESSION['auth_user']`; orden alfabético carga Auth **antes** de `tests/Kernel/ApiHealthPublicDispatchTest.php` en el mismo proceso. |
| Contra-evidencia | `php tests/run.php Kernel` → 61/0 PASS; CI `platform-fast-gates` ejecuta Kernel como job aislado → **success** @ `c822196` |
| Impacto | Falso negativo local al correr la suite monolítica; **no** es regresión de producción de M4 (`/api/health` público + `/api/ping` tras `AuthMiddleware` en rutas). |
| Owner | Framework / test harness |
| Estado | **Abierto** — aislar sesión por test o resetear `$_SESSION` entre archivos |

### M3 — CRUD/Calendario `CrudRbacMiddleware` (estado actualizado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia | `#114` / tag `v1.2.10`; `routes/web.php` + skeleton: `$crudRbac = [new CrudRbacMiddleware()]` en `/crud/{resource}` y `/calendario/{key}`; clase `src/Presentation/Middlewares/CrudRbacMiddleware.php`; tests Kernel PASS |
| Nota proceso | Plan `2026-08-06-audit-crud-rbac-router.md` sigue **0/41** checkboxes pese a ship — deuda documental, no de código |
| Owner | Framework |
| Estado | **RESUELTO** en tip + tag |

### M4 — `GET /api/health` público (estado actualizado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia | `#114` / `v1.2.10`; `routes/api.php` + skeleton registran `/api/health` **antes** del grupo `AuthMiddleware`; `HealthController::health`; `/api/ping` permanece autenticado |
| Nota proceso | Plan `2026-08-05-audit-api-health-public.md` sigue **0/39** checkboxes — igual que M3 |
| Owner | Framework |
| Estado | **RESUELTO** en tip + tag |

### M1 / M2 / M7 / M8 / M9 / D7 — históricos resueltos (sin regresión)

| ID | Estado |
|----|--------|
| M1 semver trío | **RESUELTO** — tip `1.2.10` sync; Docs `PlatformVersionSemverTest` en suite Docs PASS |
| M2 `.env.example` Portal keys | **RESUELTO** — #62 |
| M7 audit PR sin merge | **RESUELTO** — #54 |
| M8 / D5 docs ops legacy | **RESUELTO** |
| M9 dompdf | **RESUELTO** — `composer audit --no-dev` limpio (dompdf v3.1.6) |
| D7 CI Actions | **RESUELTO** — tip run `31456605262` **success** @ `c822196` |

Hygiene: `composer validate --no-check-publish` exit **2** (warnings + lock-not-up-to-date); CI usa `--no-check-lock`. Falta `docs/release/v1.2.8.md` (existen `v1.2.7`/`v1.2.9`/`v1.2.10`) — baja, no reabre M1.

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Workaround `administracion.ver` en `routes/web.php` / skeleton; comentario explícito; `rg permisos.gestionar database/` → 0 |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh` → 404 / GraphQL fail sobre `Lebytek_Portal` (reconfirmado 2026-08-11) |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** |

### M10 — Huecos de auditorías diarias (arrastrado / ampliado)

| Campo | Valor |
|-------|-------|
| Evidencia | Siguen ausentes `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`; **nuevo hueco** `2026-08-10` (día con merges `#109`–`#111` sin audit diaria). Cadena 06–09 + **11** presente. |
| Owner | Ops / automation |
| Estado | **Abierto** |

### D6 — `skeleton.lebytek.com` pendiente (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | `docs/ENVIRONMENTS.md` — skeleton pendiente; crm.lebytek.com documentado live |
| Owner | Ops |
| Estado | **Abierto** |

### INV (medios históricos #101)

Plan hardening Facturapi en tip **50/51** (tasks de código cerradas vía `#109`/`#112`). Medios S1/S2/S3/S6/S7/E3/M7 del audit `#101` tratados por ese plan; no se re-enumeran como nuevos. Vertical OFF sigue siendo el default seguro hasta QA Portal.

**Medios nuevos esta corrida:** M11 (aislamiento de sesión en suite monolítica).

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-09-auditoria-tecnica-diaria.md` (PR `#106` @ `487ccd8`) + audit crítica CRUD `#90` + audit invoicing `#101`.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO; ops Portal N/V |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal |
| CRUD-C1 / C2 / C5 AuthZ | #90 | **RESUELTO** tip + tags |
| CRUD-C3 states form | #90 | **RESUELTO** tip + tags |
| CRUD-C4 CAS/TOCTOU | #90 | **Abierto** (sin cambio de código) |
| CRUD-C6 uploads | #90 | **RESUELTO** `#111` / `v1.2.8`+ |
| REL-C1 tags release | 2026-08-08 | **RESUELTO** — `v1.2.7`…`v1.2.10` |
| INV-E1 / INV-E2 | #101 | **RESUELTO** `#109`/`#112` |
| M1–M2 / M7–M9 / D7 | previos | RESUELTOS |
| M3 CRUD RBAC router | 2026-07-27 | **RESUELTO** `#114` / `v1.2.10` (plan checkboxes sin marcar) |
| M4 API health | 2026-07-27 | **RESUELTO** `#114` / `v1.2.10` (plan checkboxes sin marcar) |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M10 hueco audits | 2026-08-06 | **Abierto** — ampliado con 2026-08-10 |
| D6 skeleton.lebytek.com | inventario | **Abierto** |
| M11 suite sesión | 2026-08-11 | **Nuevo / abierto** |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `c822196` | Auth, RBAC, CRUD Engine, Invoicing vertical, Payments genérico, install, skeleton, CI |
| Release semver | Tags `v1.2.7`…`v1.2.10`; tip declara `1.2.10` (tree ≡ tag) | Mantener tip↔tag al siguiente patch |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, wiring Facturapi routes/RBAC, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT`, bump `composer.lock` |
| CRM | `Lebytek_CRM` (doc) | Consume Framework vía lock |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API / lifecycle |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Dependencias cruzadas:**

- AuthZ/states/C6/M3/M4/Facturapi hardening: **ya tagueados** (`v1.2.8`…`v1.2.10`). Portal/CRM **dependen** de bump `composer.lock` ≥ `v1.2.10` para recibir el lote. Confirmación del lock Portal bloqueada por M6.
- Invoicing: código plataforma Framework listo para QA; habilitación + `InvoiceableSource` + rutas RBAC = consumidor. No activar en prod sin wiring Portal.
- `mkt_leads` afterListRows: Portal (plan 0/5) depende Framework ≥ `v1.2.2` (ya superado por tags actuales).
- Stripe QA: Portal/VPS.
- CRUD-C4: fix Framework futuro → requerirá **nuevo tag** post-implementación para que Portal lo consuma.

---

## Cambios recientes en `main` (desde auditoría 2026-08-09 @ `5bf0863`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `487ccd8` / #106 | Auditoría diaria 2026-08-09 | Ancla audit anterior |
| `76dc8e8` / #110 | REL-C1 Tasks 1–5 — gate, notes, retarget M3/M4 | Cierra pipeline docs release; tag `v1.2.7` |
| `e9f1607` / #111 | Uploads hardening C6 + bump `1.2.8` | Cierra CRUD-C6; tag `v1.2.8` |
| `154fe17` / #109 | Facturapi mode/key fail-fast (Task 1) | Inicio hardening INV |
| `1425c55` / #112 | Facturapi hardening Tasks 2–10 | Cierra INV-E1/E2 + resto plan |
| `f34ba2a` / #113 | Bump `1.2.9` post-hardening | Tag `v1.2.9` |
| `c822196` / #114 | M4 `/api/health` + M3 `CrudRbacMiddleware` → `1.2.10` | Cierra M3/M4; tag `v1.2.10` (tree ≡ tip) |

PRs abiertos Framework: **0**. Issues abiertos Framework: **0**.

Hueco: **sin** `docs/audits/2026-08-10-auditoria-tecnica-diaria.md` pese a actividad de release ese día UTC.

---

## Fronteras del paquete

- `src/` — sin `LebytekApiClient`, sin `App\Domain\Marketing`, sin `mkt_leads` (`rg` vacío en PHP).
- `config/vertical.php` / skeleton — `marketing=false`, `payments=false`, `invoicing=false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`.
- Payments genérico: `src/Domain/Payments/` intacto (`PaymentGatewayInterface`, `SupportsSubscriptions`, VOs, event log).
- Invoicing: capas Domain/Application/Infrastructure + SQL módulo — plataforma; consumidor aporta source/RBAC/UI.
- Bootstrap SQL módulos vía path-repo (`database/schema/modules/*`); sin espejo obligatorio bajo `skeleton/database/schema/modules/`.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Root `.env.example`: Facturapi OFF — no keys Marketing Portal.

**Conclusión:** no se coló negocio Portal. El delta es plataforma legítima (CRUD uploads, Invoicing hardening, RBAC router, health). Sin hallazgos nuevos de frontera.

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Tip sin tag Composer | Alta | **Mitigado** — REL-C1 cerrado; `v1.2.10` publicado |
| CRUD-C4 TOCTOU transitions | Alta | **Abierto** en tip |
| CRUD-C6 uploads | Alta | **Mitigado** — `v1.2.8`+ |
| Doble timbrado Facturapi | Alta si se habilita | **Mitigado en código** + vertical OFF |
| Portal/CRM sin bump a ≥`v1.2.10` | Alta (consumo) | Depende ops consumidores + M6 |
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — 53 commits no ancestros |
| Stripe sin QA Portal | Alta | Mitigado Framework OFF |
| Suite monolítica local engañosa (M11) | Media (DX/CI local) | Abierto; CI por jobs OK |
| `skeleton.lebytek.com` | Media | D6 |
| Huecos audits 03–05 + 10 | Media (proceso) | M10 |
| Portal prod SHA desconocido | Media | M6 |
| Planes M3/M4 checkboxes en 0 | Baja | Drift documental |
| Falta `docs/release/v1.2.8.md` | Baja | Hygiene |
| `composer validate` lock warning (exit 2) | Baja | CI `--no-check-lock` |

---

## Archivos involucrados

Delta `5bf0863..c822196` (resumen por PR):

- Release/docs: `docs/superpowers/plans|specs/2026-08-08-audit-release-semver-tag*`, `docs/release/v1.2.{7,9,10}.md`, planes M3/M4 retarget
- CRUD C6: `src/Application/Services/{UploadValidator,FileUploadService}.php`, tests Security/Crud, bump `1.2.8`
- Invoicing: `src/Application/Invoicing/IssueInvoiceFromSource.php` + providers/webhook/RBAC/SQL/docs runbook; bumps hacia `1.2.9`
- M3/M4: `src/Presentation/Middlewares/CrudRbacMiddleware.php`, `src/Presentation/Controllers/Api/HealthController.php`, `routes/{web,api}.php`, `skeleton/routes/{web,api}.php`, tests Kernel/Docs; bump `1.2.10`
- Auditoría previa: `docs/audits/2026-08-09-auditoria-tecnica-diaria.md`
- Artefacto nuevo: `docs/audits/2026-08-11-auditoria-tecnica-diaria.md` (este archivo)

Re-inspeccionados sin hallazgo de frontera: `config/vertical.php`, `.env.example`, `src/Domain/Payments/*`, `database/schema/modules/*`, `CrudTransitionService.php` (C4 intacto).

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
| GitHub Actions tip | **success** @ `c822196` (run `31456605262`) |
| Issues / PRs abiertos Framework | **0** / **0** |

### Comandos ejecutados

```console
$ php tests/run.php
789 passed, 9 failed
exit code: 1

$ php tests/run.php Kernel
61 passed, 0 failed
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
50 passed, 0 failed
exit code: 0

$ php tests/run.php Crud
238 passed, 0 failed
exit code: 0

$ php tests/run.php Invoicing
112 passed, 0 failed
exit code: 0

$ php tests/run.php Security
38 passed, 0 failed
exit code: 0

$ php tests/run.php Reporte
56 passed, 0 failed
exit code: 0

$ php tests/run.php Archivos
22 passed, 0 failed
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

Contadores: suite completa **789 passed / 9 failed**. Suites Kernel/Payments/SkeletonPurity/Install/Docs/Crud/Invoicing/Security/Reporte/Archivos **verdes** en aislamiento. Ningún comando descubrió cero tests.

### Análisis de fallos

**7 fails de entorno (Integrations / MySQL ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `Connection refused`

**2 fails de aislamiento (M11) solo en suite monolítica:**

- `AuthMiddleware blocks unauthenticated /api/ping` — expected 302 got 200
- `Router dispatch does not return 200 JSON ok for /api/ping without session`

**Clasificación:** bloqueadores de entorno local (MySQL) + hallazgo medio de harness (sesión). CI tip green cubre Kernel/Docs/Integrations por jobs separados (`platform-fast-gates` + `platform-integration-gates`).

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Portal/CRM ops:** bump `composer.lock` a **`v1.2.10`** (o ≥) para absorber AuthZ, states, C6, Facturapi hardening, M3 y M4. Verificación del SHA Portal sigue bloqueada por M6 — conceder lectura `gh` al token de automation.
3. **Siguiente crítico Framework:** CRUD-C4 CAS/TOCTOU en `CrudTransitionService` — único crítico de código abierto; al cerrarlo, cortar tag nuevo (Portal dependerá de ese tag).
4. **Harness:** resetear sesión entre tests/archivos (M11) para que `php tests/run.php` monolítico no mienta.
5. **Docs hygiene:** marcar checkboxes de planes M3/M4 ya shippeados; añadir `docs/release/v1.2.8.md` si se quiere paridad de notas.
6. **No habilitar** Facturapi en prod hasta wiring + QA Portal (código Framework listo; vertical sigue OFF).
7. **Automation:** no omitir paso 00 (M10 — recuperar narrativa del hueco 08-10 si hace falta); MySQL local o confiar en CI integration.

**Veredicto:** día de **cierre masivo de deuda** (REL-C1, C6, INV-E1/E2, M3, M4) con tip `1.2.10` tagueado y CI verde. **0 críticos nuevos**; **1 medio nuevo** (M11). **1 crítico abierto** (CRUD-C4). Fronteras FPS sanas. Riesgo dominante de release pasa de «tag ausente» a «consumidores sin bump de lock» + C4 residual. Portal SHA no inspeccionable.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
