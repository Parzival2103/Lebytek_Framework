# Auditoría técnica diaria — 2026-08-06

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `0d26a15206e7c60055a7d5f39b8b362df45c301d` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/audit-2026-08-06` |
| Timestamp UTC | trigger cron `2026-08-06T12:02:20Z` / corrida agente `2026-08-06T12:03:46Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0)

$ git rev-parse --verify origin/main
0d26a15206e7c60055a7d5f39b8b362df45c301d

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
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`LEGACY_ANCESTOR_CHECK=PASS`).

---

## Resumen ejecutivo

`origin/main` **avanzó** desde la auditoría del 2026-08-02 (`09b4f3e` → `0d26a15`): **19 commits** en el intervalo. Tip de release: tag **`v1.2.3`** @ `041e402` (código); commits posteriores son docs/automation/specs.

**Cierres de deuda en tip:**

1. **M1 RESUELTO** — `#74` sincronizó `config/app.php` + `skeleton/config/app.php` a `1.2.2`; `041e402` bumpeó el trío a **`1.2.3`**. Suite Docs **24/24 PASS** (`PlatformVersionSemverTest` verde).
2. **M9 RESUELTO** — `#74` subió `dompdf/dompdf` a **v3.1.6**; gate `DompdfSecurityVersionTest`; `composer audit` → **0 advisories**.

**Hueco de cadena:** no existen artefactos `docs/audits/2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`. Las etapas 01–08 de esos días operaron en **Nivel C** sobre el audit #67 (2026-08-02), produciendo specs/planes (CI gates, health público, Portal `afterListRows`) **sin implementación** (planes **0/5**). AUTOMATION-07 bloqueado por PHP ausente en corridas 06 (documentado en closures 08-04/08-05).

**Código de plataforma en el intervalo:** sólo el release hygiene `#74` + bump `1.2.3`. Resto docs. Fronteras FPS intactas. **0 hallazgos críticos nuevos. 1 medio nuevo (M10 hueco audits).** Deuda arrastrada abierta: M3–M6, D6/D7 + planes sin ejecutar.

---

## Hallazgos críticos

*Ningún hallazgo crítico nuevo en Framework `main` en esta corrida.*

### Deuda crítica arrastrada (estado actualizado)

| ID | Hallazgo | Estado 2026-08-06 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **RESUELTO** — eliminados PR #36; `DeployScriptsRemovedTest` verde | Framework |
| C2 (2026-07-27) / #21 | Stripe subscription C1–C6 | **Framework RESUELTO** — PR #42 + tags `v1.2.1`…`v1.2.3`. Gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT` es **Portal/VPS**. Mitigación Framework: `STRIPE_ENABLED=false`, `vertical.payments=false`. QA Portal no verificable (M6) | Framework ✅ / Portal ⏳ QA |
| C3 (2026-07-27) / #23 | Bootstrap marketing + migraciones | **Re-scopeado** — Portal; issue Framework #23 CLOSED | Portal |

---

## Hallazgos medios

### M1 — Sync semver tres archivos (arrastrado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia resolución | `#74` sync `1.2.2`; commit `041e402` bump trío a `1.2.3`; tag `v1.2.3` @ `041e402`. `composer.json` / `config/app.php` / `skeleton/config/app.php` = **`1.2.3`**. Docs suite **24 passed, 0 failed**. |
| Nota hygiene | `composer validate` exit 2: *lock file is not up to date* (content-hash tras bump de `version` en `composer.json`; deps sin cambio; `composer audit` limpio). No reabre M1; corregir con `composer update --lock` en próximo release train. |
| Estado | **RESUELTO** en `main`. No reabrir salvo regresión del gate Docs. |

### M9 — `dompdf/dompdf` advisories (arrastrado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia resolución | `composer.lock` fija **v3.1.6**; `tests/Docs/DompdfSecurityVersionTest.php` exige `≥3.1.6`; `composer audit` → *No security vulnerability advisories found* (exit 0). |
| Estado | **RESUELTO** en `main`. |

### M10 — Hueco de auditorías diarias 2026-08-03..05 (nuevo / proceso)

| Campo | Valor |
|-------|-------|
| Evidencia | `docs/audits/` sin archivos `2026-08-03` / `08-04` / `08-05`. Closures automation (`docs/automation-reports/2026-08-0{3,4,5}-plan-closure.md`) confirman «audit del día no ejecutado / paso 00». Specs del intervalo declaran Nivel C sobre audit #67. |
| Impacto | Cadena 01–08 diseñó specs/planes sin ancla audit del día; AUTOMATION-07 no ejecutó (B1 PHP + readiness BLOCKED). Deuda de implementación acumulada (CI, `/api/health`, Portal leads) sin priorización fresca diaria. |
| Owner | Ops / automation Framework |
| Estado | **Abierto** — este artefacto cierra el hueco para 2026-08-06; no recupera los tres días omitidos |

### M2 — `.env.example` root Portal keys (histórico → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia | Comentario anti-duplicación presente; keys activas `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` = 0; `SkeletonPurity` 13/13; Kernel env gate intacto |
| Estado | **RESUELTO**. Sin regresión. |

### M3 — CRUD/Calendario sin `RbacMiddleware` a nivel router (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `routes/web.php` L114–125 |
| Evidencia | Rutas `/crud/{resource}` y `/calendario/{key}` sólo heredan `AuthMiddleware` del grupo; RBAC dentro del servicio. Sin cambio en este intervalo. |
| Impacto | Defensa en profundidad inconsistente |
| Owner | Framework |
| Estado | **Abierto** — backlog riesgo bajo; **sin spec dedicado** en el intervalo |

### M4 — API `/api/*` autenticada por sesión / sin health público (arrastrado + diseño)

| Campo | Valor |
|-------|-------|
| Archivos | `routes/api.php`, `skeleton/routes/api.php` |
| Evidencia tip | Grupo `/api` con `AuthMiddleware`; `GET /api/ping` dentro del grupo; `rg '/health' routes/` → 0. Spec+plan mergeados: `#81` `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md` + plan `…/plans/2026-08-05-audit-api-health-public.md` (**0/5** tareas). |
| Impacto | LB/cron no pueden hacer liveness sin cookie; redirect 302 a `/login` |
| Owner | Framework |
| Estado | **Abierto** — diseño listo; implementación 07 pendiente (tag esperado `v1.2.4` según plan) |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Comentario en `routes/web.php`: workaround `administracion.ver`; `rg permisos.gestionar database/` → 0 |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable desde agente cloud (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh` → GraphQL fail / HTTP 404 sobre `Lebytek_Portal` |
| Impacto | No se puede verificar si Portal `composer.lock` consume ≥ `v1.2.2`/`v1.2.3`, ni QA Stripe, ni avance del plan Portal `afterListRows` |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** — bloqueador de entorno, no defecto de código |

### M7 / M8 — históricos resueltos

| ID | Estado |
|----|--------|
| M7 audit PR sin merge | **RESUELTO** — lifecycle #54; audit #67 mergeado |
| M8 / D5 docs ops legacy | **RESUELTO** — #56/#57 + `OpsDocsFpsAlignmentTest` |

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (PR #67) + inventario `docs/audits/2026-07-28-deuda-tecnica-inventario.md`. **Nota:** no hubo audits diarios mergeados el 03–05; la cadena heredó esta fuente.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO (`v1.2.3`); ops Portal pendiente / no verificable |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal; cerrado en Framework |
| M1 version UI / semver sync | 2026-07-29 / reopen 08-02 | **RESUELTO** (`v1.2.3` trío sync) |
| M2 `.env.example` Marketing | 2026-07-27 | RESUELTO (#62) |
| M3 CRUD RBAC router | 2026-07-27 | **Abierto** |
| M4 API sesión / health | 2026-07-27 | **Abierto** — spec/plan 08-05 listos, 0/5 impl |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M7 audit PR sin merge | 2026-07-30 | RESUELTO |
| M8 / D5 docs ops legacy | inventario / audit 31 | RESUELTO |
| M9 dompdf advisories | 2026-08-02 | **RESUELTO** (#74, ≥3.1.6) |
| M10 hueco audits 03–05 | **nuevo 2026-08-06** | **Abierto** (proceso) |
| D6 skeleton.lebytek.com | inventario / plan 2026-07-26 | **Abierto** — plan reconciliado (#53), sin implementación |
| D7 CI GitHub Actions | inventario / spec 08-04 | **Abierto** — plan `2026-08-04-audit-platform-ci-gates.md` **0/5**; `.github/` ausente |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `0d26a15` | Auth, RBAC, CRUD (+ `afterListRows`), install, Payments genérico, skeleton |
| Release semver | Tag `v1.2.3` @ `041e402` | Tip vigente (sync + dompdf secure); tip `main` docs-only después del tag |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, handler Portal `afterListRows`, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API, lifecycle instancias, OpenAPI en docs.lebytek.com |
| Legacy (histórico) | `archive/backoffice-api-integration` @ `4789f95` | Evidencia migración — **no** base de trabajo |

**Dependencias cruzadas:**

- Enrich `mkt_leads` admin: lógica **Portal** (plan `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md`, 0/5) **depende** del tag Framework ≥ `v1.2.2` (`afterListRows`). Tip Framework ya publica `v1.2.3`. Confirmación del lock Portal **bloqueada por M6**.
- `/api/health` + CI gates: fixes **Framework**; planes listos; requieren ejecución AUTOMATION-07 / humano. Health prevé tag `v1.2.4`.
- Stripe subscription QA: **Portal/VPS** — no bloqueado por tip Framework actual.

---

## Cambios recientes en `main` (desde auditoría 2026-08-02 @ `09b4f3e`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `d372ad8` / #67 | Auditoría diaria 2026-08-02 | Ancla audit anterior en `main` |
| `62d24b2` / #68 | Spec v122 release integrity | Diseño M1/M9 |
| `04e51cb` / #69 + `4dccfb2` / #70 | Plan readiness/closure 08-02 | Cadena automation |
| `dc2c91f` / #74 | Sync semver `1.2.2` + dompdf ≥3.1.6 | **Cierra M1 histórico reopen + M9** |
| `041e402` | Bump plataforma `1.2.3` + tag | Tip release vigente |
| `ada7ce2` / #75 + `dd93e81` / #76 | Spec/plan Portal `mkt_leads` afterListRows | Diseño Portal (no código FW) |
| Closures/readiness 08-03 (varios) | Cadena sin audit del día | Nivel C |
| `14309c8` / #78 | Spec platform CI gates (D7) | Diseño; **0/5 impl** |
| `#79`/`#80` | Readiness/closure 08-04 | 07 bloqueado (PHP) |
| `c1c9305` / #81 | Spec public API health (M4) | Diseño; **0/5 impl** |
| `#82`/`#83` | Readiness/closure 08-05 | 07 no ejecutó |

PRs abiertos Framework: **#77** `docs: add crm.lebytek.com to ENVIRONMENTS` (docs producto; no bloquea ciclo). Issues abiertos Framework: **0**.

Delta `09b4f3e..origin/main`: 26 archivos — **código:** `composer.json`/`composer.lock`/`config/app.php`/`skeleton/config/app.php` + test Dompdf; resto docs/specs/plans/automation-reports.

---

## Fronteras del paquete

Verificación estática + suites:

- `src/` — sin `LebytekApiClient`, sin dominio Marketing, sin `mkt_*` embebido. Hook `afterListRows` sigue genérico.
- Spec `#75` describe trabajo **Portal** correctamente (ownership cruzado en docs Framework).
- `config/vertical.php` / skeleton — `marketing=false`, `payments=false`.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Schema plataforma: `database/schema/modules/` = calendario, crud-engine, integrations, payments, pdf-kit, reportes — sin Marketing/`dom_*`.
- Payments genérico: `src/Domain/Payments/` (`SupportsSubscriptions`, gateways, VOs) + infra Stripe; OFF por defecto (`STRIPE_ENABLED=false`).
- Root `.env.example`: sin keys Portal activas.

**Conclusión:** no se coló módulo de negocio Portal en Framework. El intervalo es release hygiene + documentación de deuda.

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — 53 commits no ancestros; scripts eliminados |
| Stripe subscriptions sin QA Portal | Alta | **Mitigado en Framework** — vertical/STRIPE OFF; bump Portal no verificable (M6) |
| Portal sin ≥`v1.2.2` no obtiene `afterListRows` | Media | **Ops Portal** — tags publicados; lock no verificable (M6) |
| Docs/semver gate | Baja | **Mitigado** — M1 cerrado @ `1.2.3` |
| Advisories dompdf | Baja | **Mitigado** — M9 cerrado; audit limpio |
| `composer validate` content-hash | Baja | **Abierto hygiene** — regenerar lock hash en próximo bump |
| Sin `GET /api/health` público | Media | **Abierto** — M4; plan listo |
| Sin CI Actions | Media | **Abierto** — D7; plan 0/5 |
| Hueco audits 03–05 | Media (proceso) | **Abierto** — M10 |
| Portal prod SHA desconocido | Media | **Bloqueador entorno** — gh 404 |
| `skeleton.lebytek.com` no desplegado | Media | **Abierto** — D6 |
| PR #77 crm.lebytek.com docs | Baja | Abierto; fuera de ciclo diario |

---

## Archivos involucrados

Delta `09b4f3e..0d26a15` (relevantes):

- `composer.json`, `composer.lock`, `config/app.php`, `skeleton/config/app.php`
- `tests/Docs/DompdfSecurityVersionTest.php` (nuevo)
- `docs/audits/2026-08-02-auditoria-tecnica-diaria.md`
- Specs/planes: `docs/superpowers/specs|plans/2026-08-0{2,3,4,5}-*` (v122, mkt_leads, CI gates, api health)
- `docs/automation-reports/2026-08-0{2,3,4,5}-plan-{readiness,closure}.md`
- Artefacto nuevo: `docs/audits/2026-08-06-auditoria-tecnica-diaria.md` (este archivo)

Re-inspeccionados sin cambio de código en deuda abierta: `routes/web.php`, `routes/api.php`, `src/Domain/Payments/*`, `config/vertical.php`, `database/schema/modules/*`.

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Ausente al inicio → instalado ad-hoc `php8.3-cli` (+ xml, mbstring, curl, zip, sqlite, mysql) — **PHP 8.3.6** |
| Composer / vendor | Ausente → `composer.phar` 2.10.2 + `composer install` ad-hoc; `composer.phar` **eliminado** antes del commit |
| `ext-pdo_mysql` | Presente tras install |
| Servidor MySQL | **Ausente** — 7 tests Integrations fallan con `SQLSTATE[HY000] [2002] Connection refused` |
| Acceso Portal (`gh`) | **404** — bloqueador entorno (M6) |
| Issues abiertos Framework | **0** |
| PRs abiertos Framework | **1** (#77 docs crm.lebytek.com) |

### Comandos ejecutados

```console
$ php tests/run.php
583 passed, 7 failed
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
50 passed, 0 failed
exit code: 0

$ php tests/run.php Docs
24 passed, 0 failed
exit code: 0

$ php tests/run.php Crud
171 passed, 0 failed
exit code: 0

$ php composer.phar audit
No security vulnerability advisories found.
exit code: 0

$ php composer.phar validate --no-check-publish
./composer.json is valid, but with a few warnings
# Lock file errors — The lock file is not up to date…
exit code: 2
```

Contadores: suite completa **583 passed / 7 failed** (vs 580/9 el 08-02: + Docs gates Dompdf/semver verdes, −2 fails de código). Suites Kernel/Payments/SkeletonPurity/Install/Docs/Crud **verdes**. Ningún comando descubrió cero tests.

### Análisis de fallos

**0 fails de código** en tip.

**7 fails de entorno (Integrations / MySQL ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `Connection refused`

**Clasificación:** 7 = bloqueador de entorno (sin daemon MySQL), **no** defecto de código. Docs semver/dompdf **verdes** (M1/M9 cerrados).

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **AUTOMATION-07 prioritario:** ejecutar plan activo `platform-ci-gates` (D7) y/o `api-health-public` (M4 → tag `v1.2.4`). Persistir PHP 8.3+ en imagen Cloud Agent para evitar B1 recurrente.
3. **Hygiene release:** `composer update --lock` en el próximo bump para content-hash; no requiere tag solo por el hash.
4. **Portal:** operador con acceso debe confirmar SHA + `composer.lock` ≥ `v1.2.2` (preferible `v1.2.3`) para `afterListRows`, ejecutar plan Portal mkt_leads, y QA Stripe antes de habilitar subscription checkout. Fix Portal de listado **depende** del tag Framework ya publicado — no de código nuevo Framework.
5. **Automation:** conceder al token `gh` lectura de `Lebytek_Portal` (M6); preinstalar MySQL (o skip Integrations sin DSN); asegurar que paso 00 (audit diario) no vuelva a omitirse (M10).
6. **Backlog:** M3, M5, D6 (`skeleton.lebytek.com`); PR #77 merge/cierre cuando convenga (fuera de ciclo).

**Veredicto:** fronteras FPS sanas; tip release `v1.2.3` con M1/M9 cerrados; Docs/Crud/Kernel/Payments verdes; **0 críticos nuevos**; **1 medio nuevo** (M10 hueco audits 03–05); deuda M3–M6 + D6/D7 + planes 0/5 pendientes de implementación; Integrations bloqueadas por MySQL; Portal/ops bloqueados por credenciales.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
