# Auditoría técnica diaria — 2026-08-12

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `cf9e67ef52237ac98136bb3335031ee058da893f` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde 2026-08-02) |
| Rama generada | `automation/audit-2026-08-12` |
| Timestamp UTC | trigger cron `2026-08-12T12:02:20Z` / corrida agente `2026-08-12T12:07:40Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0; origin/main resuelve)

$ git rev-parse --verify origin/main
cf9e67ef52237ac98136bb3335031ee058da893f

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
- `refs/remotes/origin/feature/backoffice-api-integration` también resuelve al mismo SHA; no se usó (primer candidato ya resolvió).
- Commits exclusivos del legacy: **53** (`git rev-list origin/main..refs/tags/archive/backoffice-api-integration`).
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`LEGACY_CHECK=PASS`).

---

## Resumen ejecutivo

`origin/main` avanzó **1 commit** desde la auditoría diaria 2026-08-11 (`23e1dd2` / tip previo `c822196` → `cf9e67e`): **PR `#118`** — CRUD p04 CAS / bulk guards / equality fail-closed + bump semver **`1.2.11`**.

Tip declara **`1.2.11`** (trío `composer.json` / `config/app.php` / `skeleton/config/app.php` sincronizado). Tag **`v1.2.11`** publicado; árbol de `v1.2.11` @ `fe6adec` **≡** árbol del merge tip `cf9e67e` (`f801cab…`) — consumidores pueden instalar `v1.2.11`.

**Cierre confirmado en tip + tag:** CRUD-C4 (CAS/TOCTOU transitions) junto con G13 (soft-delete write guards), G1 (bulk visible/enabled re-check) y G14 (equality fail-closed + columnas de condición en list SELECT). Era el **único crítico de código residual**; queda cerrado.

**Sin hallazgos nuevos** (0 críticos nuevos / 0 medios nuevos). Deuda abierta arrastrada: M5, M6, M10, M11, D6 (+ hygiene documental de planes M3/M4 y `docs/release/v1.2.8.md` ausente). Verticals `marketing`/`payments`/`invoicing` = `false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`.

Suites aisladas verdes; suite completa local **802/9** (7 Integrations MySQL env + 2 M11 session pollution). CI tip `main` **success** (run `31550181578`).

---

## Hallazgos críticos

### Sin hallazgos críticos nuevos

No hay defectos críticos nuevos en tip `cf9e67e`.

### CRUD-C4 — Transitions sin CAS / TOCTOU (estado actualizado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Fuente | `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (#90); remediated `#118` |
| Evidencia tip `cf9e67e` | `CrudTransitionService::apply` construye `$expected = ['deleted' => 0, $column => $from]`, llama `updateRecord(..., $expected)`, y ante `updated === 0` relee, reautoriza y reintenta **una** vez con predicado fresco; conflicto → `ValidationException` accionable. `GenericCrudRepository::updateRecord` acepta predicados esperados. Tests Crud CAS + Docs `CrudModuleCasTest` en tip. |
| Release | Tag `v1.2.11`; notes `docs/release/v1.2.11.md`; tree tip ≡ tag |
| Owner | Framework |
| Estado | **RESUELTO** en tip + tag — no reabrir salvo regresión |

### REL-C1 / CRUD-C6 / INV-E1 / INV-E2 / CRUD-C1–C3 / C1–C3 históricos — sin regresión

| ID | Estado 2026-08-12 |
|----|-------------------|
| REL-C1 tags | **RESUELTO** — ahora `v1.2.7`…`v1.2.11`; tip `1.2.11` tree ≡ tag |
| CRUD-C6 uploads | **RESUELTO** `v1.2.8`+ |
| INV-E1 / INV-E2 | **RESUELTO** `#109`/`#112` |
| CRUD-C1 / C2 / C5 / C3 | **RESUELTO** `#95`/`#100` |
| C1 deploy scripts / C2 Stripe FW / C3 marketing | sin cambio (C3 = Portal) |

**Críticos de código abiertos en Framework tip:** **ninguno**.

---

## Hallazgos medios

### Sin hallazgos medios nuevos

No se abren IDs medios nuevos en esta corrida. Se arrastra la deuda media abierta (abajo).

### M11 — Contaminación de sesión en `php tests/run.php` monolítico (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia tip `cf9e67e` | Suite completa: `AuthMiddleware blocks unauthenticated /api/ping` → expected 302 got 200; `Router dispatch… /api/ping` falla con sesión residual. `php tests/run.php Kernel` → **61/0 PASS**; CI `platform-fast-gates` success @ tip. |
| Impacto | Falso negativo local en suite monolítica; **no** regresión prod de M4. |
| Owner | Framework / test harness |
| Estado | **Abierto** |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Workaround `administracion.ver` en `routes/web.php` / skeleton; comentario explícito; `rg permisos.gestionar database/` → 0 |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh` → 404 / GraphQL fail sobre `Lebytek_Portal` (reconfirmado 2026-08-12) |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** |

### M10 — Huecos de auditorías diarias (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Ausentes `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md` y `2026-08-10`; cadena 06–09 + **11** + **12** presente. Sin hueco nuevo hoy. |
| Owner | Ops / automation |
| Estado | **Abierto** |

### D6 — `skeleton.lebytek.com` pendiente (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | `docs/ENVIRONMENTS.md` — skeleton pendiente; crm.lebytek.com documentado live |
| Owner | Ops |
| Estado | **Abierto** |

### M3 / M4 — resueltos (sin regresión; hygiene de plan)

| ID | Estado |
|----|--------|
| M3 `CrudRbacMiddleware` | **RESUELTO** `#114` / `v1.2.10` — plan checkboxes siguen 0/N |
| M4 `/api/health` | **RESUELTO** `#114` / `v1.2.10` — plan checkboxes siguen 0/N |

### M1 / M2 / M7 / M8 / M9 / D7 — históricos resueltos (sin regresión)

| ID | Estado |
|----|--------|
| M1 semver trío | **RESUELTO** — tip `1.2.11` sync; Docs `PlatformVersionSemverTest` PASS |
| M2 / M7 / M8 / M9 / D7 | **RESUELTO** — sin regresión; `composer audit --no-dev` limpio |

Hygiene: `composer validate --no-check-publish` exit **2** (lock-not-up-to-date); CI usa `--no-check-lock`. Falta `docs/release/v1.2.8.md` — baja. PRs abiertos residuales: `#116` (spec C4/M11 post-audit 08-11; C4 ya shippeado), `#117` (draft spec duplicado absorbido por `#118`), `#119` (docs evaluación externa) — proceso, no defectos de plataforma.

**Medios nuevos esta corrida:** ninguno.

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-11-auditoria-tecnica-diaria.md` (PR `#115` @ `23e1dd2`) + audit crítica CRUD `#90`.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO; ops Portal N/V |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal |
| CRUD-C1 / C2 / C5 AuthZ | #90 | **RESUELTO** tip + tags |
| CRUD-C3 states form | #90 | **RESUELTO** tip + tags |
| CRUD-C4 CAS/TOCTOU | #90 | **RESUELTO** `#118` / `v1.2.11` |
| CRUD-C6 uploads | #90 | **RESUELTO** `#111` / `v1.2.8`+ |
| REL-C1 tags release | 2026-08-08 | **RESUELTO** — `v1.2.7`…`v1.2.11` |
| INV-E1 / INV-E2 | #101 | **RESUELTO** `#109`/`#112` |
| M1–M2 / M7–M9 / D7 | previos | RESUELTOS |
| M3 CRUD RBAC router | 2026-07-27 | **RESUELTO** `#114` / `v1.2.10` (plan checkboxes sin marcar) |
| M4 API health | 2026-07-27 | **RESUELTO** `#114` / `v1.2.10` (plan checkboxes sin marcar) |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M10 hueco audits | 2026-08-06 | **Abierto** — 03–05 + 10 |
| D6 skeleton.lebytek.com | inventario | **Abierto** |
| M11 suite sesión | 2026-08-11 | **Abierto** |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `cf9e67e` | Auth, RBAC, CRUD Engine (incl. CAS), Invoicing vertical, Payments genérico, install, skeleton, CI |
| Release semver | Tags `v1.2.7`…`v1.2.11`; tip declara `1.2.11` (tree ≡ tag) | Mantener tip↔tag al siguiente patch |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, wiring Facturapi routes/RBAC, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT`, bump `composer.lock` |
| CRM | `Lebytek_CRM` (doc) | Consume Framework vía lock |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API / lifecycle |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Dependencias cruzadas:**

- AuthZ/states/C6/M3/M4/Facturapi hardening/CAS (C4): **ya tagueados** hasta `v1.2.11`. Portal/CRM **dependen** de bump `composer.lock` ≥ **`v1.2.11`** para recibir CAS + lote previo. Confirmación del lock Portal bloqueada por M6.
- Invoicing: plataforma lista; habilitación + `InvoiceableSource` + rutas RBAC = consumidor. No activar en prod sin wiring Portal.
- `mkt_leads` afterListRows: Portal (plan 0/5) — Framework ≥ `v1.2.2` ya superado por tags actuales.
- Stripe QA: Portal/VPS.
- M11: fix Framework harness — no requiere tag de consumidores salvo que se quiera sincronizar DX.

---

## Cambios recientes en `main` (desde auditoría 2026-08-11 @ `23e1dd2` / tip previo `c822196`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `23e1dd2` / #115 | Auditoría diaria 2026-08-11 | Ancla audit anterior (ya en main al inicio del día) |
| `cf9e67e` / #118 | CRUD p04 CAS + bulk guards + equality fail-closed → `1.2.11` | **Cierra CRUD-C4** (+ G13/G1/G14); tag `v1.2.11` |

PRs abiertos Framework: **3** (`#116` spec residual, `#117` draft duplicado, `#119` docs evaluación). Issues abiertos Framework: **0**.

---

## Fronteras del paquete

- `src/` — sin `LebytekApiClient`, sin `App\Domain\Marketing`, sin `mkt_leads` (`rg` vacío en PHP de negocio Portal).
- `config/vertical.php` / skeleton — `marketing=false`, `payments=false`, `invoicing=false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`.
- Payments genérico: `src/Domain/Payments/` intacto (`PaymentGatewayInterface`, `SupportsSubscriptions`, VOs, event log). Campo `membresiaId` en `PaymentEvent` = metadata de gateway, no módulo Marketing.
- Invoicing: capas Domain/Application/Infrastructure + SQL módulo — plataforma; consumidor aporta source/RBAC/UI.
- Bootstrap SQL módulos vía path-repo (`database/schema/modules/*`); sin espejo obligatorio bajo `skeleton/database/schema/modules/`.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Root `.env.example`: sin `MKT_*` / `LEBYTEK_API_*` / `PAYMENTS_SUBSCRIPTION_CHECKOUT`.

**Conclusión:** no se coló negocio Portal. El delta `#118` es plataforma CRUD legítima. **Sin hallazgos nuevos de frontera.**

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Tip sin tag Composer | Alta | **Mitigado** — `v1.2.11` publicado; tree ≡ tip |
| CRUD-C4 TOCTOU transitions | Alta | **Mitigado** — `#118` / `v1.2.11` |
| CRUD-C6 uploads | Alta | **Mitigado** — `v1.2.8`+ |
| Doble timbrado Facturapi | Alta si se habilita | **Mitigado en código** + vertical OFF |
| Portal/CRM sin bump a ≥`v1.2.11` | Alta (consumo) | Depende ops consumidores + M6 — **riesgo dominante** |
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — 53 commits no ancestros |
| Stripe sin QA Portal | Alta | Mitigado Framework OFF |
| Suite monolítica local engañosa (M11) | Media (DX) | Abierto; CI por jobs OK |
| `skeleton.lebytek.com` | Media | D6 |
| Huecos audits 03–05 + 10 | Media (proceso) | M10 |
| Portal prod SHA desconocido | Media | M6 |
| Planes M3/M4 checkboxes en 0 | Baja | Drift documental |
| Falta `docs/release/v1.2.8.md` | Baja | Hygiene |
| `composer validate` lock warning (exit 2) | Baja | CI `--no-check-lock` |
| PRs draft residuales `#116`/`#117` | Baja | Cerrar/abandonar tras ship `#118` |

---

## Archivos involucrados

Delta `23e1dd2..cf9e67e` (PR `#118`):

- CRUD CAS/bulk/equality: `src/Application/Services/{CrudTransitionService,CrudActionService,CrudDataService}.php`, `src/Domain/Entities/Crud/{CrudActionDefinition,CrudResourceDefinition}.php`, `src/Infrastructure/Repositories/GenericCrudRepository.php`
- Tests: `tests/Crud/Action/*`, `tests/Crud/Transition/*` (CAS/bulk), Docs `CrudModuleCasTest`
- Spec/plan/runbook: `docs/superpowers/specs/2026-08-11-crud-p04-cas-bulk-equality-design.md`, `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md`, `docs/modules/crud/modulo-crud-engine.md`, `docs/release/v1.2.11.md`
- Semver trío: `composer.json`, `config/app.php`, `skeleton/config/app.php` → `1.2.11`
- Auditoría previa: `docs/audits/2026-08-11-auditoria-tecnica-diaria.md`
- Artefacto nuevo: `docs/audits/2026-08-12-auditoria-tecnica-diaria.md` (este archivo)

Re-inspeccionados sin hallazgo de frontera: `config/vertical.php` / skeleton vertical, `.env.example`, `src/Domain/Payments/*`, `routes/web.php` (M5 workaround), `database/schema/modules/*`.

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Ausente al inicio → instalado ad-hoc `php8.3-cli` (+ xml, mbstring, curl, zip, sqlite, mysql) — **PHP 8.3.6** |
| Composer / vendor | Ausente → `/tmp/composer.phar` 2.10.2 + `composer install`; **sin** `composer.phar` en el árbol del repo |
| `ext-pdo_mysql` | Presente tras install ad-hoc |
| Servidor MySQL | **Ausente** — 7 tests Integrations `Connection refused` |
| Acceso Portal (`gh`) | **404** — bloqueador entorno (M6) |
| GitHub Actions tip | **success** @ `cf9e67e` (run `31550181578`; jobs `platform-fast-gates` + `platform-integration-gates`) |
| Issues / PRs abiertos Framework | **0** / **3** (`#116`, `#117`, `#119`) |

### Comandos ejecutados

```console
$ php tests/run.php
802 passed, 9 failed
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
51 passed, 0 failed
exit code: 0

$ php tests/run.php Crud
251 passed, 0 failed
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

$ php tests/run.php Auth
52 passed, 0 failed
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

Contadores: suite completa **802 passed / 9 failed** (↑ Docs 51, Crud 251 vs 50/238 del 2026-08-11 por tests p04). Suites Kernel/Payments/SkeletonPurity/Install/Docs/Crud/Invoicing/Security/Reporte/Archivos/Auth **verdes** en aislamiento. Ningún comando descubrió cero tests.

### Análisis de fallos

**7 fails de entorno (Integrations / MySQL ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `SQLSTATE[HY000] [2002] Connection refused`

**2 fails de aislamiento (M11) solo en suite monolítica:**

- `AuthMiddleware blocks unauthenticated /api/ping` — expected 302 got 200
- `Router dispatch does not return 200 JSON ok for /api/ping without session`

**Clasificación:** bloqueadores de entorno local (MySQL) + hallazgo medio de harness arrastrado (M11). CI tip green cubre Kernel/Docs/Integrations por jobs separados. **No** son PASS de código ni fallos de regresión prod.

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Portal/CRM ops:** bump `composer.lock` a **`v1.2.11`** (o ≥) para absorber CAS/C4 + AuthZ/states/C6/Facturapi/M3/M4. Verificación del SHA Portal sigue bloqueada por M6 — conceder lectura `gh` al token de automation.
3. **Harness:** resetear sesión entre tests/archivos (M11) para que `php tests/run.php` monolítico no mienta.
4. **Docs/proceso hygiene:** marcar checkboxes de planes M3/M4 ya shippeados; añadir `docs/release/v1.2.8.md` si se quiere paridad; cerrar o abandonar drafts `#116`/`#117` tras ship `#118`.
5. **M5:** seed `permisos.gestionar` o documentar el workaround como permanente.
6. **No habilitar** Facturapi en prod hasta wiring + QA Portal (código Framework listo; vertical sigue OFF).
7. **Automation:** no omitir paso 00 (M10 — huecos 03–05 + 10 siguen); MySQL local o confiar en CI integration.

**Veredicto:** día de **cierre del último crítico de código** (CRUD-C4 → `v1.2.11`). **0 hallazgos nuevos** (críticos o medios). Deuda abierta = medios/proceso/entorno (M5/M6/M10/M11/D6). Fronteras FPS sanas. Riesgo dominante de release: **consumidores sin bump de lock a ≥`v1.2.11`**. Portal SHA no inspeccionable.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
