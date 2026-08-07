# Auditoría técnica diaria — 2026-08-07

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `22a2053d7a06115a975dff3ce37167e54c030ab7` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/audit-2026-08-07` |
| Timestamp UTC | trigger cron `2026-08-07T12:01:09Z` / corrida agente `2026-08-07T12:03:08Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0; origin/main resuelve)

$ git rev-parse --verify origin/main
22a2053d7a06115a975dff3ce37167e54c030ab7

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
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`LEGACY_ANCESTOR_FAIL=0` / PASS).

---

## Resumen ejecutivo

`origin/main` avanzó desde la auditoría diaria 2026-08-06 (`0d26a15` → `22a2053`): **19 commits**. Tip de release sigue en tag **`v1.2.3`** @ `041e402` (código); tip `main` aporta CI de plataforma, higiene MySQL 8.0 en schema/migraciones, docs ENVIRONMENTS, export-ignore Composer, audit crítica CRUD y planes de remediación — **sin** bump semver nuevo.

**Cierres / avances materiales:**

1. **D7 RESUELTO** — PR `#88` añadió `.github/workflows/platform-tests.yml` (`platform-fast-gates` + `platform-integration-gates` con MySQL 8.0). Gate `CiWorkflowPresentTest` en suite Docs. Tip `main` @ `22a2053` tiene Actions **success** (run `31150028471`). Primer push post-merge falló por shallow clone sin tag legacy; corregido con step `git fetch … tag archive/backoffice-api-integration`.
2. **Audit crítica CRUD Engine** mergeada (`#90`) — inventaría **6 críticos / 16 graves / 20 medios / 4 bajos** en el módulo. Código tip **aún vulnerable**; remediación p01 AuthZ (C1+C2+C5) tiene plan `#93` y PR draft `#95` (no mergeado; prevé bump `1.2.6`).
3. **M3** ahora tiene spec+plan (`2026-08-06-audit-crud-rbac-router`, 0/5) — sigue abierto; prioridad remediación CRUD lo ubica tras AuthZ/uploads (orden 6 / G4).

**Fronteras FPS:** intactas. Facturapi es diseño/scaffold de plataforma (docs `#91`/`#94`), sin SQL negocio Portal ni `LebytekApiClient` en `src/`.

**Conteo esta corrida:** **6 hallazgos críticos abiertos** (CRUD-C1…C6, nuevos en la cadena diaria vía `#90`); **0 medios nuevos**; deuda media arrastrada M3–M6, M10, D6. D7 cierra. Suites verdes salvo 7 Integrations (MySQL ausente = entorno).

---

## Hallazgos críticos

### CRUD-C1…C6 — AuthZ / mutación asimétrica del CRUD Engine (nuevo en cadena diaria)

Fuente canónica: `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (PR `#90`). Owner: **Framework**. Ninguno remediado en tip `main` @ `22a2053`.

| ID | Título | Estado tip | Remediación |
|----|--------|------------|-------------|
| CRUD-C1 | IDOR con `list.scope_handler` custom (`assertOwnedBy` solo `owner`) | **Abierto** | Plan p01 `#93`; PR draft `#95` |
| CRUD-C2 | Acción sin `permission` ejecuta sin RBAC servidor | **Abierto** | Plan p01 / PR `#95` |
| CRUD-C3 | Columna de states editable por formulario (+ demo toggle) | **Abierto** | Programa remediación orden 2 (sin plan ejecutable p02 en tip) |
| CRUD-C4 | Transitions sin CAS / TOCTOU | **Abierto** | Orden 4 |
| CRUD-C5 | `CrudReporteDataSource` no exige `{resource}.ver` | **Abierto** | Plan p01 / PR `#95` |
| CRUD-C6 | Uploads sin allowlist obligatoria + `public_path` sin normalizar | **Abierto** | Orden 3 |

**Impacto:** IDOR, ejecución de acciones por cualquier autenticado, exfil vía Reportes/PDF, escritura en disco, bypass de state machine. **Blast radius:** cualquier consumidor (Portal, CRM, skeleton) que habilite CRUD Engine.

**Nota semver:** plan p01 reserva tags `1.2.4` (health M4), `1.2.5` (router M3), `1.2.6` (AuthZ C1+C2+C5). Merge de `#95` implica tag Framework nuevo; consumidores deben bump `composer.lock` para obtener el fix.

### Deuda crítica histórica (estado actualizado)

| ID | Hallazgo | Estado 2026-08-07 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **RESUELTO** — PR #36; `DeployScriptsRemovedTest` verde | Framework |
| C2 (2026-07-27) / #21 | Stripe subscription C1–C6 | **Framework RESUELTO** — PR #42 + tags `v1.2.1`…`v1.2.3`. Gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT` es **Portal/VPS**. Mitigación Framework: `STRIPE_ENABLED=false`, `vertical.payments=false`. QA Portal no verificable (M6) | Framework ✅ / Portal ⏳ QA |
| C3 (2026-07-27) / #23 | Bootstrap marketing + migraciones | **Re-scopeado** — Portal; issue Framework #23 CLOSED | Portal |

---

## Hallazgos medios

### M1 / M2 / M7 / M8 / M9 — históricos resueltos (sin regresión)

| ID | Estado |
|----|--------|
| M1 semver trío | **RESUELTO** — `1.2.3` sync; `PlatformVersionSemverTest` PASS |
| M2 `.env.example` Portal keys | **RESUELTO** — #62 |
| M7 audit PR sin merge | **RESUELTO** — lifecycle #54 |
| M8 / D5 docs ops legacy | **RESUELTO** — #56/#57 |
| M9 dompdf advisories | **RESUELTO** — ≥3.1.6; `composer audit` limpio |

Hygiene arrastrada (no reabre M1): `composer validate` exit 2 — *lock file is not up to date* (content-hash). CI usa `composer validate --no-check-lock` a propósito hasta hygiene PR.

### M3 — CRUD/Calendario sin `RbacMiddleware` a nivel router (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivos | `routes/web.php`, `skeleton/routes/web.php` |
| Evidencia | Rutas `/crud/{resource}` y `/calendario/{key}` sólo heredan `AuthMiddleware`; RBAC dentro del servicio. Spec+plan mergeados 2026-08-06 (`#85` / plan `2026-08-06-audit-crud-rbac-router.md`, **0/5**). Coincide con CRUD-G4; prioridad programa = orden 6 (después de AuthZ C1/C2/C5 y uploads C6). |
| Owner | Framework |
| Estado | **Abierto** — diseño listo; impl pendiente; tag reservado `1.2.5` |

### M4 — API `/api/*` autenticada por sesión / sin health público (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivos | `routes/api.php`, `skeleton/routes/api.php` |
| Evidencia | Grupo `/api` con `AuthMiddleware`; `GET /api/ping` dentro del grupo; `rg '/health' routes/` → 0. Spec+plan 08-05 **0/5**. |
| Owner | Framework |
| Estado | **Abierto** — tag esperado `v1.2.4` |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Comentario workaround `administracion.ver` en `routes/web.php` / skeleton; `rg permisos.gestionar database/` → 0. Sólo cambió la ruta del doc de referencia (archive). |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable desde agente cloud (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh` → GraphQL fail / HTTP 404 sobre `Lebytek_Portal` |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** — bloqueador de entorno |

### M10 — Hueco de auditorías diarias 2026-08-03..05 (arrastrado / proceso)

| Campo | Valor |
|-------|-------|
| Evidencia | `docs/audits/` sigue sin `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`. Cadena 06 y 07 restaurada. |
| Owner | Ops / automation Framework |
| Estado | **Abierto** — no recupera los tres días omitidos; monitorizar que paso 00 no vuelva a fallar |

### D7 — Sin CI GitHub Actions (arrastrado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia resolución | PR `#88` mergeado; `.github/workflows/platform-tests.yml` presente; Docs `CiWorkflowPresentTest` PASS (suite Docs **30/30**); tip Actions **success** @ `22a2053`. |
| Residual ops | Checklist del plan archivado aún marca ACs manuales (branch protection required checks, throwaway AC4/AC6) incompletos — no reabre «sin CI». |
| Estado | **RESUELTO** en `main`. |

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-06-auditoria-tecnica-diaria.md` (PR `#84`) + audit crítica CRUD `#90`.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO (`v1.2.3`); ops Portal pendiente / no verificable |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal; cerrado en Framework |
| CRUD-C1…C6 AuthZ/mutación | **#90 2026-08-07** | **Abierto** — p01 draft `#95` para C1+C2+C5 |
| M1 version UI / semver sync | 2026-07-29 | RESUELTO (`v1.2.3`) |
| M2 `.env.example` Marketing | 2026-07-27 | RESUELTO (#62) |
| M3 CRUD RBAC router | 2026-07-27 | **Abierto** — spec/plan 08-06, 0/5 |
| M4 API sesión / health | 2026-07-27 | **Abierto** — plan 08-05, 0/5 |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M7 audit PR sin merge | 2026-07-30 | RESUELTO |
| M8 / D5 docs ops legacy | inventario / audit 31 | RESUELTO |
| M9 dompdf advisories | 2026-08-02 | RESUELTO |
| M10 hueco audits 03–05 | 2026-08-06 | **Abierto** (proceso) |
| D6 skeleton.lebytek.com | inventario / plan 2026-07-26 | **Abierto** — `docs/ENVIRONMENTS.md` sigue «pendiente de implementar»; crm.lebytek.com documentado operativo (`#89`) |
| D7 CI GitHub Actions | inventario / spec 08-04 | **RESUELTO** (#88) |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `22a2053` | Auth, RBAC, CRUD Engine (+ remediación AuthZ), install, Payments genérico, skeleton, CI gates |
| Release semver | Tag `v1.2.3` @ `041e402` | Tip release publicado; tip `main` docs/CI/schema-ahead; next code tags reservados `1.2.4`–`1.2.6` |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, handler Portal `afterListRows`, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` |
| CRM | `Lebytek_CRM` / `main` (doc) | Producto CRM en `crm.lebytek.com`; consume Framework vía lock |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API, lifecycle instancias, OpenAPI en docs.lebytek.com |
| Legacy (histórico) | `archive/backoffice-api-integration` @ `4789f95` | Evidencia migración — **no** base de trabajo |

**Dependencias cruzadas:**

- Fixes CRUD-C1/C2/C5: **Framework** (PR `#95` draft) → tag ≥ `v1.2.6` → bump `composer.lock` en Portal/CRM. Sin tag nuevo, consumidores no reciben el parche AuthZ.
- Enrich `mkt_leads` admin: lógica **Portal** (plan `2026-08-02-audit-mkt-leads-after-list-rows.md`, 0/5) **depende** del tag Framework ≥ `v1.2.2` (ya publicado `v1.2.3`). Confirmación lock Portal **bloqueada por M6**.
- `/api/health` (M4) + router RBAC (M3): fixes **Framework**; planes listos; tags `1.2.4` / `1.2.5`.
- Stripe subscription QA: **Portal/VPS** — no bloqueado por tip Framework actual.
- Facturapi invoicing: diseño plataforma en Framework (`#91`); implementación futura en `src/` — **no** es negocio Marketing Portal.

---

## Cambios recientes en `main` (desde auditoría 2026-08-06 @ `0d26a15`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `ddc55ec` / #84 | Auditoría diaria 2026-08-06 | Ancla audit anterior |
| Spec M3 + readiness/closure 08-06 | `#85`/`#86`/`#87` | Diseño M3; 07 no implementó |
| `#88` platform-ci-gates | CI Actions + MySQL 8.0 compat schema/migración | **Cierra D7**; Install +2 tests |
| `#89` ENVIRONMENTS crm live | Docs mapa hostnames | D6 sigue abierto (skeleton) |
| `#90` audit crítica CRUD | 6C/16G/20M/4B | **Eleva críticos diarios** |
| `#91` Facturapi design | Scaffold docs plataforma | Frontera OK (sin código negocio) |
| `#92` export-ignore + archive docs | Package hygiene + `ComposerExportIgnoreTest` | Docs suite ↑ |
| `#93` plan CRUD p01 AuthZ | Plan C1+C2+C5 | Input para 07 / PR `#95` |

PRs abiertos Framework: **#95** draft `fix(crud): AuthZ multi-canal C1+C2+C5 (p01)`; **#94** docs invoicing plan restructure. Issues abiertos Framework: **0**. PR histórico `#77` crm ENVIRONMENTS: **CLOSED** sin merge (sustituido por `#89` mergeado).

Delta código relevante `0d26a15..22a2053`: `.github/workflows/platform-tests.yml`, `database/migrations/…auth_registro_recuperacion.sql` (+ espejo skeleton), `database/schema/schema.sql` (`cfg_configuraciones.valor` DEFAULT NULL), comentario rutas web, tests Docs/Install.

---

## Fronteras del paquete

- `src/` — sin `LebytekApiClient`, sin dominio Marketing, sin `mkt_*` embebido. Hook `afterListRows` genérico.
- `config/vertical.php` / skeleton — `marketing=false`, `payments=false`; `STRIPE_ENABLED=false`.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Schema módulos: calendario, crud-engine, integrations, payments, pdf-kit, reportes — sin Marketing/`dom_*`.
- Migración auth + schema alineados harness↔skeleton (`diff -q` OK; `routes/web.php` paridad OK).
- Payments genérico: `src/Domain/Payments/` intacto; OFF por defecto.
- Root `.env.example`: sin keys Portal activas.
- Facturapi: sólo specs/planes bajo `docs/superpowers/` — no implementación en `src/` aún.

**Conclusión:** no se coló módulo de negocio Portal. El hallazgo dominante del día es deuda de seguridad **dentro** del CRUD Engine de plataforma (ya inventariada en `#90`).

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| CRUD AuthZ IDOR / actions / Reportes (C1/C2/C5) | **Alta** | **Abierto** en tip; fix en draft `#95` → requiere tag ≥`1.2.6` + bump consumidores |
| CRUD states/uploads/TOCTOU (C3/C4/C6) | Alta | **Abierto** — sin PR de código |
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — 53 commits no ancestros |
| Stripe subscriptions sin QA Portal | Alta | **Mitigado en Framework** — vertical/STRIPE OFF |
| Portal sin ≥`v1.2.2` no obtiene `afterListRows` | Media | **Ops Portal** — tags publicados; lock no verificable (M6) |
| Sin `GET /api/health` público | Media | **Abierto** — M4 |
| Sin CI Actions | Media | **Mitigado** — D7 cerrado; tip green |
| `skeleton.lebytek.com` no desplegado | Media | **Abierto** — D6 |
| Hueco audits 03–05 | Media (proceso) | **Abierto** — M10 |
| Portal prod SHA desconocido | Media | **Bloqueador entorno** — gh 404 |
| `composer validate` content-hash / `--no-check-lock` en CI | Baja | Hygiene release |
| Docs/semver / dompdf | Baja | **Mitigado** |

---

## Archivos involucrados

Delta `0d26a15..22a2053` (relevantes):

- `.github/workflows/platform-tests.yml` (nuevo)
- `database/migrations/20260612120000_auth_registro_recuperacion.sql` + espejo skeleton
- `database/schema/schema.sql`
- `routes/web.php`, `skeleton/routes/web.php` (comentario archive path)
- `tests/Docs/CiWorkflowPresentTest.php`, `tests/Docs/ComposerExportIgnoreTest.php`
- `tests/Install/SchemaBootstrapTest.php` (ampliado)
- `.gitattributes` export-ignore
- `docs/ENVIRONMENTS.md`, `docs/core/despliegue-y-versionado.md`
- `docs/audits/2026-08-06-auditoria-tecnica-diaria.md`, `docs/audits/2026-08-07-auditoria-critica-crud-engine.md`
- Specs/planes: `2026-08-06-audit-crud-rbac-router*`, `2026-08-07-crud-*`, `2026-08-07-invoicing-facturapi*`
- Artefacto nuevo: `docs/audits/2026-08-07-auditoria-tecnica-diaria.md` (este archivo)

Re-inspeccionados sin remediación en tip: `routes/api.php`, `src/Application/Services/CrudScopeResolver.php`, `src/Application/Services/CrudActionService.php`, `src/Application/Reporte/CrudReporteDataSource.php`, `src/Domain/Payments/*`, `config/vertical.php`.

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
| GitHub Actions tip | **success** @ `22a2053` (run `31150028471`) |
| Issues abiertos Framework | **0** |
| PRs abiertos Framework | **2** (#94 docs invoicing; #95 draft AuthZ p01) |

### Comandos ejecutados

```console
$ php tests/run.php
591 passed, 7 failed
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
30 passed, 0 failed
exit code: 0

$ php tests/run.php Crud
171 passed, 0 failed
exit code: 0

$ php composer.phar audit --no-dev
No security vulnerability advisories found.
exit code: 0

$ php composer.phar validate --no-check-publish
./composer.json is valid, but with a few warnings
# Lock file errors — The lock file is not up to date…
exit code: 2
```

Contadores: suite completa **591 passed / 7 failed** (vs 583/7 el 08-06: + Docs CI/export-ignore gates, + Install schema MySQL 8). Suites Kernel/Payments/SkeletonPurity/Install/Docs/Crud **verdes**. Ningún comando descubrió cero tests.

### Análisis de fallos

**0 fails de código** en tip para suites no-Integrations.

**7 fails de entorno (Integrations / MySQL ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `Connection refused`

**Clasificación:** 7 = bloqueador de entorno (sin daemon MySQL local). En CI, el job `platform-integration-gates` cubre ese hueco (tip green).

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Prioridad producto Framework:** revisar y mergear `#95` (CRUD-C1+C2+C5) → tag **`v1.2.6`** (respetar reservas `1.2.4`/`1.2.5` si M4/M3 van antes o documentar skip). Portal/CRM **dependen** de ese tag para el parche AuthZ.
3. **Siguiente lote CRUD** tras AuthZ: C3+G15+G6 (states), luego C6 (uploads), luego C4/bulk — según prioridad confirmada en audit `#90`. No sustituir C5 con sólo M3 router RBAC.
4. **AUTOMATION-07 / humano:** planes listos M4 health (`1.2.4`) y M3 router (`1.2.5`) siguen 0/5.
5. **Hygiene:** `composer update --lock` en próximo bump; no requiere tag solo por content-hash.
6. **Portal/ops:** conceder lectura `gh` a `Lebytek_Portal` (M6); confirmar lock ≥ `v1.2.3`; plan mkt_leads afterListRows; QA Stripe. D6 `skeleton.lebytek.com` sigue pendiente.
7. **Automation:** MySQL local o confiar en CI integration job; no omitir paso 00 (M10).

**Veredicto:** fronteras FPS sanas; D7 cerrado con CI verde en tip; tip release publicado `v1.2.3`; **6 críticos abiertos** (CRUD Engine, audit `#90`) aún sin código en `main`; **0 medios nuevos**; deuda M3–M6/M10/D6 arrastrada; Integrations locales bloqueadas por MySQL (CI las cubre); Portal SHA no inspeccionable.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
