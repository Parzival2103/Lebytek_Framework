# Auditoría técnica diaria — 2026-08-02

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `09b4f3e71c4abe3fddc2b430d93bb2a074448fe6` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view` / `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 / GraphQL «Could not resolve…» (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (merge #49 docs subdomain; avanzó desde `b6c3773` de la auditoría anterior) |
| Rama generada | `automation/audit-2026-08-02` |
| Timestamp UTC | trigger cron `2026-08-02T12:02:27Z` / corrida agente `2026-08-02T12:04:48Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0)

$ git rev-parse --verify origin/main
09b4f3e71c4abe3fddc2b430d93bb2a074448fe6

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
- `refs/remotes/origin/feature/backoffice-api-integration` también resuelve al mismo SHA; se usa el tag (primer candidato, FQ — evita ambigüedad con rama local obsoleta).
- Commits exclusivos del legacy: **53** (`git rev-list origin/main..refs/tags/archive/backoffice-api-integration`).
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`LEGACY_CHECK=PASS`). Comprobar sólo la punta sería insuficiente.

---

## Resumen ejecutivo

`origin/main` **avanzó** desde la auditoría del 2026-08-01 (`5b03d9e` → `09b4f3e`): **10 commits** en el intervalo (PRs #60–#63, #66 + merges locales). Incluye el **primer cambio de código de plataforma** desde varias corridas docs-only: hook CRUD `afterListRows` (#66) y tag release **`v1.2.2`** @ tip `09b4f3e`.

Novedades positivas:

1. **M2 RESUELTO** — `#62` purgó keys `MKT_*` / `LEBYTEK_API_*` / `WAAPI_PORTAL_*` del root `.env.example`; gate `FrameworkRootNotPortalTest` (env) PASS.
2. **M1 cerrado temporalmente** por `#62` (sync `1.2.1` + `PlatformVersionSemverTest`) — **luego reabierto** por `#66`/`v1.2.2` (ver abajo).
3. Capacidad plataforma legítima: `CrudListRowsContext` / `afterListRows` genérico en `src/` (sin SQL Marketing ni `LebytekApiClient`); Crud suite **171/171 PASS**.
4. Audit 2026-08-01 mergeado (#60); pipeline automation 08 endurecido (#63).

**Hallazgo medio nuevo de código en tip:** regresión de sync semver post-`v1.2.2` (Docs **2 FAIL** reales, no entorno). **Hallazgo medio nuevo de dependencia:** `dompdf/dompdf` v3.1.5 con 6 advisories (fix ≥3.1.6). Fronteras FPS intactas respecto a negocio Portal.

**Hallazgos nuevos:** 0 críticos, **2 medios** (M1 reabierto + M9 dompdf). **Deuda arrastrada abierta:** M3–M6 + D6/D7 + ops Stripe Portal. **M2 resuelto.**

---

## Hallazgos críticos

*Ningún hallazgo crítico nuevo en Framework `main` en esta corrida.*

### Deuda crítica arrastrada (estado actualizado)

| ID | Hallazgo | Estado 2026-08-02 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **RESUELTO** — eliminados PR #36; `DeployScriptsRemovedTest` + Docs verdes | Framework |
| C2 (2026-07-27) / #21 | Stripe subscription C1–C6 | **Framework RESUELTO** — PR #42 + tag `v1.2.1` (contrato base). Tip release ahora `v1.2.2` (CRUD hook). Gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT` es **Portal/VPS**. Mitigación Framework: `STRIPE_ENABLED=false`, `vertical.payments=false`. QA Portal no verificable (M6) | Framework ✅ / Portal ⏳ QA |
| C3 (2026-07-27) / #23 | Bootstrap marketing + migraciones | **Re-scopeado** — Portal; issue Framework #23 CLOSED | Portal |

---

## Hallazgos medios

### M1 — Sync semver tres archivos **REABIERTO** (regresión post-`v1.2.2`) — **nuevo en tip**

| Campo | Valor |
|-------|-------|
| Archivos | `composer.json` (`1.2.2`), `config/app.php` (`1.2.1`), `skeleton/config/app.php` (`1.2.1`); tag `v1.2.2` @ `09b4f3e` |
| Evidencia | `#62` cerró el desfase histórico `1.0.0`→`1.2.1` e introdujo `PlatformVersionSemverTest`. `#66` bumpeó `composer.json` a `1.2.2` y etiquetó `v1.2.2` **sin** actualizar harness/skeleton. Suite Docs: **FAIL** esperado `'1.2.2'` got `'1.2.1'` (×2). `composer validate` advierte lock content-hash desfasado tras el bump de `version` (sólo hash; deps sin cambio). |
| Impacto | Gate Docs rojo en `main` tip; UI/operadores pueden mostrar `1.2.1` mientras el tag/paquete declaran `1.2.2`; checklist en `docs/core/despliegue-y-versionado.md` incumplido en el propio release |
| Owner | Framework |
| Estado | **Abierto / reabierto** — fix trivial de sync + regenerar content-hash del lock; **no** requiere diseño nuevo (spec/plan hygiene ya en `main`: #50/#61/#62) |

### M9 — `dompdf/dompdf` v3.1.5 con advisories de seguridad (nuevo)

| Campo | Valor |
|-------|-------|
| Evidencia | `composer.lock` fija `dompdf/dompdf` **v3.1.5**. `composer audit`: **6** advisories (4 medium / 2 low) — CVE-2026-59943, 59942, 59941, 56722, 55555, 55554; afectados `<3.1.6`. Constraint `composer.json`: `^3.1`. |
| Impacto | Superficie PDF-kit / reportes; riesgo local-file / DoS según advisory. No es bypass auth de plataforma, pero es deuda de release del paquete. |
| Owner | Framework |
| Estado | **Abierto** — bump a `≥3.1.6` + `composer update dompdf/dompdf` + tag menor; verificar tests PdfKit/Reportes |

### M2 — `.env.example` root con variables Portal/Marketing (arrastrado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia resolución | PR [#62](https://github.com/Parzival2103/Lebytek_Framework/pull/62): keys activas `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` = **0**; comentario explícito de no duplicar; `FrameworkRootNotPortalTest` env gate **PASS**; `SkeletonPurity` **13/13 PASS** |
| Estado | **RESUELTO** en `main`. No reabrir salvo regresión del gate Kernel. |

### M3 — CRUD/Calendario sin `RbacMiddleware` a nivel router (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `routes/web.php` L114–125 |
| Evidencia | Rutas `/crud/{resource}` y `/calendario/{key}` sólo heredan `AuthMiddleware` del grupo; RBAC dentro del servicio. Sin cambio en este intervalo. |
| Impacto | Defensa en profundidad inconsistente; 403 desde servicio, no middleware |
| Owner | Framework |
| Estado | **Abierto** — backlog riesgo bajo |

### M4 — API `/api/*` autenticada por sesión (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `routes/api.php` |
| Evidencia | Grupo con `AuthMiddleware` (sesión); sin token API de plataforma |
| Owner | Framework |
| Estado | **Abierto** |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Comentario en `routes/web.php` / skeleton: se usa `administracion.ver` como workaround; `rg permisos.gestionar database/` → 0 |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable desde agente cloud (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh repo view` → GraphQL fail; `gh api …/commits/main` → HTTP 404 |
| Impacto | No se puede verificar SHA producción ni si Portal `composer.lock` ya consume `v1.2.2` (hook `afterListRows` para mkt leads) |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** — bloqueador de entorno, no defecto de código |

### M7 / M8 — históricos resueltos

| ID | Estado |
|----|--------|
| M7 audit PR sin merge | **RESUELTO** — lifecycle #54; audits #55/#60 mergeados |
| M8 / D5 docs ops legacy | **RESUELTO** — #56/#57 + `OpsDocsFpsAlignmentTest`; sin regresión hoy |

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-01-auditoria-tecnica-diaria.md` (PR #60) + inventario `docs/audits/2026-07-28-deuda-tecnica-inventario.md`.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO (`v1.2.1`+); ops Portal pendiente / no verificable |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal; cerrado en Framework |
| M1 version UI / semver sync | 2026-07-29 | **REABIERTO** — regresión tip `v1.2.2` (configs `1.2.1`) |
| M2 `.env.example` Marketing | 2026-07-27 | **RESUELTO** (#62) |
| M3 CRUD RBAC router | 2026-07-27 | **Abierto** |
| M4 API sesión | 2026-07-27 | **Abierto** |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M7 audit PR sin merge | 2026-07-30 | RESUELTO |
| M8 / D5 docs ops legacy | inventario / audit 31 | RESUELTO |
| M9 dompdf advisories | **nuevo 2026-08-02** | **Abierto** |
| D6 skeleton.lebytek.com | inventario / plan 2026-07-26 | **Abierto** — plan reconciliado (#53), sin implementación |
| D7 CI GitHub Actions | inventario | **Abierto** — `.github/workflows/` ausente (ni siquiera `.github/`) |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `09b4f3e` | Auth, RBAC, CRUD (+ `afterListRows`), install, Payments genérico, skeleton |
| Release semver | Tag `v1.2.2` @ `09b4f3e` | Tip vigente (CRUD list enrichment); **metadata UI desfasada** (M1) |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, handler Portal del hook `afterListRows`, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API, lifecycle instancias, OpenAPI en docs.lebytek.com |
| Legacy (histórico) | `archive/backoffice-api-integration` @ `4789f95` | Evidencia migración — **no** base de trabajo |

**Dependencia cruzada:** el enrich de `mkt_leads` (status Green API / actividad tenant) es **lógica Portal** que **depende** del tag Framework ≥ `v1.2.2` (`afterListRows`). Un fix/feature Portal de listado leads admin **requiere** bump de `composer.lock` a ese tag. Esta corrida **no** pudo confirmar el lock de Portal (M6). El desfase M1 (configs `1.2.1`) **no** bloquea el contrato Composer del tag, pero deja Docs rojo hasta el patch de sync.

---

## Cambios recientes en `main` (desde auditoría 2026-08-01 @ `5b03d9e`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `7ad7224` / #60 | Auditoría diaria 2026-08-01 | Ancla audit anterior en `main` |
| `a9e21e8` | Plan closure report 2026-08-01 | Cadena automation |
| `6cb7e96` / #61 | Spec harness hygiene unblock | Diseño M1/M2 |
| `3fd44d4` / #62 | Sync semver `1.2.1` + purga env Portal | **Cierra M2**; cierra M1 histórico; gates Docs/Kernel |
| `2135953` / #63 | AUTOMATION-08 merge permissions + WhatsApp closure | Pipeline |
| `a551b41`+`09b4f3e` / #66 | `afterListRows` + bump/`tag` `v1.2.2` | Capacidad CRUD; **reabre M1** |

PRs abiertos al momento de la corrida: **0**. Issues abiertos Framework: **0**.

Delta `5b03d9e..origin/main`: 26 archivos — docs/automation + **`src/` CRUD** + harness `.env.example`/`config/app.php` + tests Docs/Kernel/Crud.

---

## Fronteras del paquete

Verificación estática + suites:

- `src/` — hook `afterListRows` / `CrudListRowsContext` **genérico** (sin `mkt_*`, sin Marketing domain, sin `LebytekApiClient`). El mensaje de commit menciona el caso de uso Portal; el código del paquete no embebe negocio leads.
- `config/vertical.php` y `skeleton/config/vertical.php` — `marketing=false`, `payments=false`.
- `SkeletonPurityTest` — **13/13 PASS**; Kernel env gate `.env.example` — **PASS** (M2).
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Schema plataforma: `database/schema/modules/` = calendario, crud-engine, integrations, payments, pdf-kit, reportes — sin Marketing/`dom_*` SQL.
- Payments genérico: `src/Domain/Payments/` (`SupportsSubscriptions`, gateways, VOs) + infra Stripe; OFF por defecto.
- Root `.env.example`: sin keys Portal activas; `STRIPE_ENABLED=false`.

**Conclusión:** no se coló módulo de negocio Portal en Framework. `#66` es extensión correcta del contrato CRUD para que Portal enriquezca filas.

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — scripts eliminados; 53 commits no ancestros; runbooks M8 |
| Stripe subscriptions sin QA Portal | Alta | **Mitigado en Framework** — vertical/STRIPE OFF; bump Portal no verificable (M6) |
| Portal sin `v1.2.2` no obtiene `afterListRows` | Media | **Ops Portal** — depende de tag ya publicado; SHA lock no verificable (M6) |
| Docs/semver gate rojo en tip `main` | Media | **Abierto** — M1 reabierto |
| Advisories dompdf &lt;3.1.6 | Media | **Abierto** — M9 |
| Portal prod SHA desconocido | Media | **Bloqueador entorno** — gh 404 |
| `skeleton.lebytek.com` no desplegado | Media | **Abierto** — D6 |
| Staging Portal inexistente | Media | **Abierto** — `ENVIRONMENTS.md` |
| Sin CI Actions | Media | D7 — gates sólo locales/agente |
| Fresh install SQL `;` en strings | Media | **Resuelto** PR #40 |

---

## Archivos involucrados

Delta `5b03d9e..09b4f3e` (relevantes):

- `src/Application/Crud/Context/CrudListRowsContext.php` (nuevo)
- `src/Application/Crud/Handlers/AbstractCrudHookHandler.php`
- `src/Application/Services/Crud{ConfigValidator,DataService,ResourceService,TableBuilder}.php`
- `src/Domain/Interfaces/CrudHookHandlerInterface.php`
- `src/Kernel/Container/FrameworkServiceProvider.php`
- `tests/Crud/Table/CrudListRowsHookTest.php`
- `tests/Docs/PlatformVersionSemverTest.php`, `ReleaseChecklistDocTest.php`
- `tests/Kernel/FrameworkRootNotPortalTest.php`
- `.env.example`, `config/app.php`, `skeleton/config/app.php`, `composer.json`
- `docs/audits/2026-08-01-auditoria-tecnica-diaria.md`, specs/plans hygiene, automation 05/08
- Artefacto nuevo: `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (este archivo)

Re-inspeccionados sin cambio de código en deuda abierta: `routes/web.php`, `routes/api.php`, `src/Domain/Payments/*`, `config/vertical.php`, `database/schema/modules/*`.

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Ausente al inicio → instalado ad-hoc `php8.3-cli` (+ xml, mbstring, curl, zip, sqlite, mysql) |
| Composer / vendor | Ausente → `composer.phar` 2.10.2 + `composer install` ad-hoc; `composer.phar` **eliminado** antes del commit |
| `ext-pdo_mysql` | Presente tras install |
| Servidor MySQL | **Ausente** — 7 tests Integrations fallan con `SQLSTATE[HY000] [2002] Connection refused` |
| Acceso Portal (`gh`) | **404** — bloqueador entorno |
| Issues abiertos Framework | **0** |
| PRs abiertos Framework | **0** |

### Comandos ejecutados

```console
$ php tests/run.php
580 passed, 9 failed
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
21 passed, 2 failed
exit code: 1

$ php tests/run.php Crud
171 passed, 0 failed
exit code: 0
```

Contadores: suite completa **580 passed / 9 failed** (ayer 575/7; + Crud hook tests + Docs/Kernel gates − 2 fails semver). Suites Kernel/Payments/SkeletonPurity/Install/Crud **verdes**. Docs **rojo por M1** (2 fails de código). Ningún comando descubrió cero tests.

### Análisis de fallos

**2 fails de código (Docs / M1):**

- `harness config/app.php version matches composer.json` — expected `1.2.2` got `1.2.1`
- `skeleton config/app.php version matches composer.json` — expected `1.2.2` got `1.2.1`

**7 fails de entorno (Integrations / MySQL ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `Connection refused`

**Clasificación:** 2 = defecto de release hygiene en tip; 7 = bloqueador de entorno (sin daemon MySQL), **no** defecto de código.

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Hotfix inmediato Framework:** sincronizar `config/app.php` + `skeleton/config/app.php` a `1.2.2`, refrescar content-hash de `composer.lock`, verificar `php tests/run.php Docs` verde; tag patch opcional `v1.2.3` sólo si se publica el bump dompdf en el mismo tren.
3. **M9:** `composer update dompdf/dompdf` → ≥3.1.6; correr PdfKit/Reportes; release patch.
4. **Portal:** operador con acceso debe confirmar SHA + `composer.lock` ≥ `v1.2.2` para usar `afterListRows` en leads, y QA Stripe antes de habilitar subscription checkout. El fix Portal de listado **depende** del tag Framework `v1.2.2` (ya publicado) — no del sync UI M1.
5. **Automation:** conceder al token `gh` lectura de `Lebytek_Portal`; preinstalar PHP + Composer + MySQL (o skip Integrations sin DSN).
6. **Backlog:** M3–M5, D6 (`skeleton.lebytek.com`), D7 CI Actions.

**Veredicto:** fronteras FPS sanas; capacidad CRUD `afterListRows` correcta en plataforma; **0 críticos nuevos**; **2 medios** (M1 reabierto en tip `v1.2.2`, M9 dompdf); M2 cerrado; verificación Docs **roja por semver**; Integrations bloqueadas por MySQL; Portal/ops siguen bloqueados por credenciales.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
