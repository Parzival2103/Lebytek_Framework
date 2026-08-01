# Design: Desbloqueo de higiene harness post-M8 (M1 semver + M2 env purge)

**Fecha:** 2026-08-01  
**Repo:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel B)

**Auditoría fuente:** `docs/audits/2026-08-01-auditoria-tecnica-diaria.md` (rama `automation/audit-2026-08-01`, head `ff5cd0d`)  
**Specs relacionados (detalle previo, no duplicar):**

- M1 semver: `docs/superpowers/specs/2026-07-29-audit-config-version-semver-sync-design.md`
- M2 env purge: `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md`
- Plan semver (solo M1 hoy): `docs/superpowers/plans/2026-07-29-audit-config-version-semver-sync.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `5b03d9e678a7021ce741420ee5c3d8a1a2f19fdc` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `b6c37739983ded445259c038521861ffb5f253c8` @ `main` (sin delta desde 2026-07-27) |
| Rama generada | `automation/spec-2026-08-01` |
| Timestamp UTC | `2026-08-01T12:30:00Z` |
| Nivel de fuente | **B** — rama `origin/automation/audit-2026-08-01` @ `ff5cd0d840a4db36c0942e4cd1c35be42f7a8ad9`; diff único `docs/audits/2026-08-01-auditoria-tecnica-diaria.md`; ancestry limpia desde `origin/main`; **sin PR de auditoría abierto** (Nivel A: `gh pr list --search "docs(audit):" --base main --state open` → vacío) |
| PR auditoría fuente | N/A (PR pendiente de AUTOMATION-03) |
| headRefOid fuente | `ff5cd0d840a4db36c0942e4cd1c35be42f7a8ad9` |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head de auditoría |
| Pase deuda técnica | `2026-08-01T13:01:00Z` UTC; SHA `origin/main` `5b03d9e678a7021ce741420ee5c3d8a1a2f19fdc`; modo **normal**; **10** abiertos verificados + **3** no verificados (D3, D14–D15); **5** heredados cerrados (D10–D12, D16, D22 vía M8 #56/#57) |

---

## Problema

La auditoría del 2026-08-01 confirma que `origin/main` @ `5b03d9e` avanzó **6 commits docs/automation** desde la auditoría del 2026-07-31, **sin cambios en `src/`**, rutas, SQL ni skeleton funcional. **M8 / D5 (docs ops legacy) quedó RESUELTO** (#56/#57 + `OpsDocsFpsAlignmentTest`). **No hay hallazgos críticos ni medios nuevos de código.**

La deuda accionable inmediata se concentra en **higiene del harness root** — drift que confunde operadores del package source con la app Portal y oculta la versión semver publicada:

| ID | Hallazgo | Evidencia verificada (`main` @ `5b03d9e`) | Impacto |
|----|----------|-------------------------------------------|---------|
| **M1** | Versión plataforma desincronizada del release | `config/app.php:7` y `skeleton/config/app.php:7` → `'version' => '1.0.0'`; `composer.json` **sin** campo `"version"`; tag publicado `v1.2.1` @ `fba3e03` | `/admin/sistema/estado`, wizard install y `scripts/status.php` muestran versión obsoleta |
| **M2** | Root `.env.example` conserva vars Portal/Marketing | **16** keys activas `MKT_*` / `LEBYTEK_API_*` / `WAAPI_PORTAL_*` (L54–102); `skeleton/.env.example` limpio (`SkeletonPurityTest` PASS) | Mantenedores del harness copian configuración post-FPS que vive en Portal |

**Contexto positivo (no reabrir):**

- Fronteras FPS intactas: sin Marketing/`LebytekApiClient` en `src/`; `vertical.marketing=false`, `vertical.payments=false`.
- Release tip `v1.2.1` coherente con contrato Stripe subscription (Framework resuelto; QA Portal **no verificable**).
- Suites críticas verdes en auditoría: Kernel 46/46, Payments 21/21, SkeletonPurity 13/13, Install 50/50, Docs 19/19.

**Gap de tests verificado:**

- `tests/Docs/PlatformVersionSemverTest.php` **no existe** (grep `PlatformVersionSemver` → 0).
- `tests/Kernel/FrameworkRootNotPortalTest.php` valida dirs Marketing y `vertical.php` pero **no** assert prefijos prohibidos en root `.env.example`.

**Bloqueadores de entorno (fuera de alcance de implementación producto, registrados):**

| ID | Tema | Evidencia | Owner |
|----|------|-----------|-------|
| **M6** | Portal SHA no inspeccionable | `gh` → 404 en `Lebytek_Portal` | Ops / credenciales automation |
| **D9** | Sin pipeline GitHub Actions | `.github/workflows/` ausente | Ops / repo Framework |
| **MySQL CI** | 7 tests Integrations fallan sin daemon | `SQLSTATE[HY000] [2002] Connection refused` en auditoría | Entorno agente / CI futuro |

---

## Brainstorm y recomendación de diseño

### Contexto y criterios de éxito

- **Propósito:** cerrar M1+M2 en un único PR de harness, desbloqueando AUTOMATION-06→07 sin tocar `src/` ni negocio Portal.
- **Restricciones:** package source no desplegable; Portal vive en repo aparte; tag `v1.2.1` ya publicado; M8 resuelto — docs ops ya alineados.
- **Éxito:** operador ve `v1.2.1` en UI/CLI; root `.env.example` no contiene vars Portal; tests gate impiden regresión; **sin** nuevo tag semver obligatorio (PATCH de config/docs/tests).

### Enfoques evaluados

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — Semver en `composer.json` + sync manual configs** | Fuente canónica `"version"` en `composer.json`; harness y skeleton copian el valor; test gate compara tres archivos | Predecible en releases; plan 2026-07-29 ya detalla TDD; no depende de `InstalledVersions` (incorrecto en path-repo pre-vendor) | Sync manual en cada release — mitigado con checklist D13 |
| **B — Leer versión en runtime desde `InstalledVersions`** | `DeploymentStatus` consulta paquete instalado | Automático post-`composer update` | Falla en harness monorepo/path-repo; requiere cambio `src/` — rechazado |
| **C — Purga `.env.example` + extensión test FPS existente** | Eliminar bloques Portal; extender `FrameworkRootNotPortalTest` con assert de prefijos | Alinea root con skeleton; reutiliza gate FPS | Requiere comentario de referencia a Portal (SHA Portal **no verificado**) |

**Recomendación:** **A + C en un solo PR** (`feature/harness-hygiene-unblock`). Enfoque B rechazado. M1 y M2 son independientes de Portal runtime; no requieren bump de `composer.lock` en Portal ni nuevo tag Framework.

---

## Comportamiento esperado

### M1 — Sincronización semver plataforma

1. `composer.json` declara `"version": "1.2.1"` (semver sin prefijo `v`), alineado con tag Git `v1.2.1` @ `fba3e03`.
2. `config/app.php` y `skeleton/config/app.php` declaran el **mismo** `'version'`.
3. `DeploymentStatus`, `/admin/sistema/estado` y `scripts/status.php` muestran `v1.2.1` **sin cambios en `src/`** — siguen leyendo `Config::get('app.version')`.
4. Checklist de release en `docs/core/despliegue-y-versionado.md` documenta sync de tres archivos + test gate.

### M2 — Purga root `.env.example`

1. Root `.env.example` elimina bloques Portal/Marketing: `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`.
2. Conserva vars de plataforma (DB, mail, auth, integraciones genéricas, `STRIPE_ENABLED=false`, `INSTALL_TOKEN`).
3. Comentario breve referencia `Lebytek_Portal/.env.example` para vars de negocio — **contenido Portal no verificado** en esta corrida.
4. `skeleton/.env.example` **sin cambios** (ya limpio).

### Tests gate (TDD obligatorio)

| Test | Archivo | Comportamiento pre-fix | Comportamiento post-fix |
|------|---------|------------------------|-------------------------|
| Semver sync | `tests/Docs/PlatformVersionSemverTest.php` (nuevo) | **FAIL** — `composer.json` sin `version` y/o configs en `1.0.0` | **PASS** — tres fuentes iguales a `1.2.1` |
| Env purity | `tests/Kernel/FrameworkRootNotPortalTest.php` (extender) | **FAIL** — root contiene `MKT_`/`LEBYTEK_API_`/`WAAPI_PORTAL_` | **PASS** — prefijos ausentes |
| Checklist doc | `tests/Docs/ReleaseChecklistDocTest.php` (nuevo, hermano) | **FAIL** — `despliegue-y-versionado.md` sin checklist | **PASS** — menciona `composer.json`, `skeleton/config/app.php`, `PlatformVersionSemver` |

Cada test debe **descubrir al menos un archivo** y fallar por el motivo previsto antes del fix de producto.

---

## Alcance

### Framework — harness y docs (`Parzival2103/Lebytek_Framework`, base `main`)

| # | Entregable | Ruta | Owner |
|---|------------|------|-------|
| H1 | Añadir `"version": "1.2.1"` | `composer.json` | Framework |
| H2 | Sync `'version' => '1.2.1'` | `config/app.php`, `skeleton/config/app.php` | Framework |
| H3 | Purga vars Portal/Marketing | `.env.example` | Framework (harness) |
| H4 | Test gate semver | `tests/Docs/PlatformVersionSemverTest.php` | Framework |
| H5 | Extensión test FPS env | `tests/Kernel/FrameworkRootNotPortalTest.php` | Framework |
| H6 | Checklist release semver | `docs/core/despliegue-y-versionado.md` | Framework |
| H7 | Test gate checklist doc | `tests/Docs/ReleaseChecklistDocTest.php` | Framework |

### Staging / implementación (AUTOMATION-06+, **no producción**)

- Rama sugerida: `feature/harness-hygiene-unblock` desde `origin/main`.
- PR sugerido: `fix(harness): sync platform semver 1.2.1 and purge Portal env vars`.
- Verificación local/CI: `php tests/run.php PlatformVersionSemver`, `FrameworkRootNotPortal`, `ReleaseChecklistDoc`, regresión `SkeletonPurity`, `Docs/OpsDocsFpsAlignment`.

### Operaciones de producción — **fuera de esta corrida desatendida**

- Bump `composer.lock` en Portal hacia `v1.2.1`.
- QA Stripe subscription checkout en Portal.
- Deploy `skeleton.lebytek.com` (plan `2026-07-26-skeleton-package-staging.md`).
- Configuración token `gh` con lectura Portal.
- Creación de workflow CI en `.github/workflows/` (D7 — spec futuro).

---

## No-alcance

| Tema | ID | Owner | Motivo |
|------|-----|-------|--------|
| Cambios `src/` runtime semver | — | Framework | Enfoque B rechazado; display via config |
| RBAC router CRUD/calendario | D6 / M3 | Framework | Backlog riesgo bajo |
| API token / health público | D7 / M4 | Framework | Backlog; spec archivado 2026-07-27 Fase 3 |
| Slug `permisos.gestionar` | D8 / M5 | Framework | Backlog producto |
| Portal SHA / `composer.lock` QA | D3 / M6, C2 ops | Portal/Ops | gh 404 — no verificado |
| GitHub Actions CI | D9 | Ops | Diseño separado |
| Publicación repo espejo skeleton | Plan 2026-07-26 | Framework/Ops | Tasks 2–10 pendientes |
| Tag `v1.2.2` obligatorio | — | Maintainer | PATCH config; tag opcional post-merge |
| Referencias históricas legacy | tag `archive/backoffice-api-integration` | Registro | Solo evidencia histórica — no base de implementación |

---

## Ownership map

| Requisito | Repositorio | Rama base | Capa / ruta |
|-----------|-------------|-----------|-------------|
| Semver sync M1 | `Parzival2103/Lebytek_Framework` | `main` | Harness: `composer.json`, `config/app.php`, `skeleton/config/app.php` |
| Env purge M2 | `Parzival2103/Lebytek_Framework` | `main` | Harness: `.env.example` |
| Tests gate | `Parzival2103/Lebytek_Framework` | `main` | `tests/Docs/`, `tests/Kernel/` |
| Checklist release | `Parzival2103/Lebytek_Framework` | `main` | `docs/core/despliegue-y-versionado.md` |
| Vars Portal reales | `Parzival2103/Lebytek_Portal` | `main` | `.env.example` — **no verificado** |
| Consumo semver Portal | `Parzival2103/Lebytek_Portal` | `main` | `composer.lock` → `lebytek/framework` ≥ v1.2.1 — **no verificado** |
| Stripe subscription QA | `Parzival2103/Lebytek_Portal` | `main` | `app/Application/Marketing/` — **no verificado** |

---

## Dependencias y compatibilidad

### Frontera semver / release Framework

| Escenario | Acción |
|-----------|--------|
| Harness root (este PR) | Sync config a `1.2.1`; **no** requiere nuevo tag ni release Composer |
| Portal existente en prod | Sigue consumiendo lock actual hasta bump humano; este PR **no** altera `vendor/` en Portal |
| Portal fresh install futuro | Debe usar tag ≥ `v1.2.1` para subscription checkout; gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT` permanece OFF hasta QA |
| Nuevo tenant desde skeleton | Plantilla `skeleton/config/app.php` mostrará versión correcta tras merge; constraint `"lebytek/framework": "^1.1"` en skeleton **sin cambio** en este alcance |

### Contratos públicos ausentes (no asumir APIs legacy)

| Contrato | Estado en `main` @ `5b03d9e` | Notas |
|----------|--------------------------------|-------|
| Token API plataforma | **Ausente** — `/api/*` usa sesión (`routes/api.php` L14–16) | M4 backlog |
| Slug RBAC `permisos.gestionar` | **Ausente** en seeds (`database/schema/`, seeds) | Workaround `administracion.ver` |
| `GET /api/health` público | **Ausente** — `/api/ping` detrás de auth | No inventar en este PR |
| Vars `LEBYTEK_API_*` en Framework | **Solo en root `.env.example` (drift)** — no en `src/` | Purga M2 |

### Migración segura

| Base | Pasos |
|------|-------|
| **Harness / dev local** | Tras merge: `git pull`; si `.env` se creó desde `.env.example` antiguo, vars Portal copiadas **no afectan** runtime Framework (no hay código que las lea en `src/`); operador puede eliminarlas manualmente |
| **Skeleton plantilla** | Sin migración SQL; solo sync `config/app.php` version |
| **Portal existente** | Sin acción requerida por este PR; bump Framework es PR separado en Portal |
| **Producción VPS** | **Sin operaciones** en corrida desatendida |

### Legacy (solo evidencia histórica)

El tag `archive/backoffice-api-integration` @ `4789f95` documenta el monolito pre-FPS. **No** es base de implementación ni target de deploy. Vars `MKT_*`/`LEBYTEK_API_*` en root `.env.example` son **residuo pre-cutover**, no contrato vigente del paquete.

---

## Riesgos

| Riesgo | Severidad | Deuda | Mitigación |
|--------|-----------|-------|------------|
| Drift semver reintroducido en próximo release | Media | D1, D4, D13 | `PlatformVersionSemverTest` + `ReleaseChecklistDocTest` + checklist H6 |
| Operador confunde versión app Portal vs framework | Baja | D1 | Documentado en `despliegue-y-versionado.md` — dos números independientes |
| Purga `.env.example` rompe flujo dev que dependía de vars Portal en harness | Baja | D2, D5 | Vars no las consume `src/`; referencia a Portal `.env.example`; assert en `FrameworkRootNotPortalTest` |
| Portal prod desactualizado respecto v1.2.1 | Media | D3, D14 | **No verificable** (M6); operador humano debe confirmar lock |
| Stripe habilitado sin QA Portal | Alta (Portal) | D14 | Fuera de alcance — gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` OFF en harness |
| Regresión M8 docs ops | Baja | D22 ✅ | `OpsDocsFpsAlignmentTest` en regresión obligatoria |
| RBAC CRUD/calendario solo en servicio | Baja | D6/M3 | Backlog — documentar patrón o middleware router-level |
| Sin CI automatizado | Media | D9 | Spec futuro workflow mínimo; gates M1/M2 ejecutables vía `tests/run.php` |
| Cloud agent sin PHP/MySQL | Baja (entorno) | — | Gates M1/M2 no requieren MySQL; ejecutor corre tests con PHP ≥ 8.1 |

---

## Rollback

| Componente | Rollback |
|------------|----------|
| H1–H2 semver | Revert commit; tags Git no se reescriben |
| H3 env purge | Revert commit; restaura vars Portal en example (no recomendado) |
| Tests | Revert junto con fix |
| Portal / VPS | **Sin cambios** — rollback no aplica |

---

## Criterios de aceptación

### Framework (verificables en repo)

- [ ] `composer.json` contiene `"version": "1.2.1"`.
- [ ] `config/app.php` y `skeleton/config/app.php` tienen `'version' => '1.2.1'`.
- [ ] Root `.env.example` no contiene keys `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`.
- [ ] `php tests/run.php PlatformVersionSemver` — **FAIL** pre-fix, **PASS** post-fix (≥ 3 tests).
- [ ] `php tests/run.php FrameworkRootNotPortal` — **FAIL** pre-fix por env, **PASS** post-fix.
- [ ] `php tests/run.php ReleaseChecklistDoc` — **FAIL** pre-fix, **PASS** post-fix.
- [ ] `php tests/run.php SkeletonPurity` — sin regresión (13/13).
- [ ] `php tests/run.php Docs/OpsDocsFpsAlignment` — sin regresión (M8).
- [ ] Sin edits en `src/`, `database/`, `routes/`, `skeleton/` funcional (excepto `skeleton/config/app.php` version).
- [ ] `git diff --name-only origin/main...HEAD` del PR de implementación no incluye negocio Portal.

### Portal / ops — **no verificados en esta corrida**

- [ ] SHA Portal `main` y `composer.lock` ≥ `v1.2.1` — requiere acceso `gh` (D3/M6).
- [ ] QA Stripe subscription antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` — Portal (D14).

### Deuda técnica (inventario)

- [ ] **AC-D1:** Sección **Deuda técnica** lista ítems abiertos D1–D2, D4–D9, D13 con evidencia ruta/línea verificada en `main` @ `5b03d9e`.
- [ ] **AC-D2:** D10–D12, D16, D22 reconciliados como **resueltos** (#56/#57); D17–D21 permanecen resueltos (#51, #54); no re-listados como abiertos.
- [ ] **AC-D3:** D3, D14–D15 marcados **no verificados** Portal; acción concreta documentada.
- [ ] **AC-D4:** Verificado sin deuda nueva — migraciones post-baseline registradas (3 SQL ↔ 3 entradas manifiesto en `config/modules/{core,crud-engine,pdf-kit}.php`); `src/` sin `TODO`/`FIXME`; referencias legacy en `docs/superpowers/`, `docs/CUTOVER-PORTAL.md`, `docs/ENVIRONMENTS.md` L128 — registro histórico, no deuda operativa.

---

## Esbozo de implementación (referencia AUTOMATION-04+)

Orden TDD sugerido (detalle en plan 2026-07-29 Tasks 1–3 + extensión M2):

```text
1. PlatformVersionSemverTest (rojo)
2. Extender FrameworkRootNotPortalTest con assert env (rojo)
3. ReleaseChecklistDocTest (rojo)
4. Fix: composer.json + configs version
5. Fix: purga .env.example
6. Fix: checklist despliegue-y-versionado.md
7. Regresión: PlatformVersionSemver, FrameworkRootNotPortal, ReleaseChecklistDoc,
   SkeletonPurity, OpsDocsFpsAlignment
```

Smoke manual (staging/harness local, **no prod**):

```bash
php -S localhost:8000 -t public
# login → /admin/sistema/estado → tarjeta versión v1.2.1
php scripts/status.php | grep 'Plataforma:'
```

---

## Issues abiertos (contexto de riesgo — no autorización para auto-fix)

| Repo | Issues abiertos | Relación |
|------|-----------------|----------|
| `Lebytek_Framework` | **0** (`gh issue list --state open` → vacío) | — |
| `Lebytek_Portal` | **No verificable** (gh 404) | C2 Stripe QA, C3 bootstrap marketing históricos |

---

## Deuda técnica

Fuente: auditoría `docs/audits/2026-08-01-auditoria-tecnica-diaria.md` (rama `automation/audit-2026-08-01` @ `ff5cd0d`); reconciliación con inventario spec `2026-07-31` (pase deuda @ `e19fa25`).

### Reconciliación heredada

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| D10 | `docs/composer-setup.md` pin legacy | **Resuelto** | PR #56/#57 — §6 pinnea `^1.2`; grep `dev-feature/backoffice-api-integration` → 0 |
| D11 | `VPS_CHECKLIST.md` obsoleto | **Resuelto** | PR #56/#57 — apunta a `Lebytek_Portal` @ `main`; `vps-deploy` marcado histórico L13 |
| D12 | Runbooks integration → feature branch | **Resuelto** | PR #56/#57 — `lebytek-implementation-real.md` L5 → Portal `main`; `role-delegation-lebytek-api.md` L195 → Portal `main` |
| D16 | `seguridad_secretos_deploy.md` modelo monolito | **Resuelto** | PR #56/#57 — L6–11 distingue Portal VPS vs package source harness |
| D22 | Gate T1 docs ops ausente | **Resuelto** | PR #56/#57 — `tests/Docs/OpsDocsFpsAlignmentTest.php` presente |
| D17–D21 | Cadena audit M7 | **Resuelto** | PRs #51, #54, #55 (corrida anterior — sin regresión) |
| M8 / D5 docs | Alineación ops post-FPS | **Resuelto** | PR #56/#57 + gate Docs |

**Cierres desde corrida anterior (2026-07-31):** **5** (D10–D12, D16, D22 vía M8).

### Alcance principal de este spec (M1 + M2 — abiertos, verificados)

| ID | Hallazgo | Evidencia (`main` @ `5b03d9e`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **D1** | Drift semver plataforma (M1) | `config/app.php:7`, `skeleton/config/app.php:7` → `'1.0.0'`; `composer.json` sin `"version"`; tag `v1.2.1` @ `fba3e03` | UI estado/wizard muestran v1.0.0 | Harness / `config/` | Framework | H1–H2: sync `"version": "1.2.1"` en tres archivos |
| **D2** | Root `.env.example` vars Portal (M2) | `.env.example` L54–102: **16** keys `MKT_*` / `LEBYTEK_API_*` / `WAAPI_PORTAL_*`; `skeleton/.env.example` limpio (grep → 0) | Mantenedores harness copian config post-FPS | Harness | Framework | H3: purga bloques Portal + comentario ref Portal |
| **D4** | Test gate semver ausente | `tests/Docs/PlatformVersionSemverTest.php` **no existe**; grep `PlatformVersionSemver` → 0 | Drift semver silencioso | `tests/Docs/` | Framework | H4: crear test TDD (FAIL pre-fix) |
| **D5** | `FrameworkRootNotPortalTest` no cubre env | `tests/Kernel/FrameworkRootNotPortalTest.php` L7–27 — dirs Marketing/`vertical.php`; **sin** assert prefijos root `.env.example` | Reintroducción vars Portal | `tests/Kernel/` | Framework | H5: extender assert `MKT_`/`LEBYTEK_API_`/`WAAPI_PORTAL_` |
| **D13** | Checklist release semver ausente | `docs/core/despliegue-y-versionado.md` L176–197 — versionado plataforma/módulo; **sin** paso sync `composer.json` + configs + test gate | Release sin checklist tres archivos | `docs/core/` | Framework | H6 + H7: checklist + `ReleaseChecklistDocTest` |

### Backlog verificado (fuera alcance M1/M2)

| ID | Hallazgo | Evidencia | Impacto | Capa | Owner | Acción |
|----|----------|-----------|---------|------|-------|--------|
| **D6** | CRUD/Calendario sin `RbacMiddleware` router (M3) | `routes/web.php` L114–125 — `/crud/{resource}`, `/calendario/{key}` solo heredan `AuthMiddleware` del grupo | RBAC delegado a servicio; 403 inconsistente | `Presentation` / `routes/` | Framework | Backlog: documentar patrón o middleware router-level |
| **D7** | API `/api/*` por sesión (M4) | `routes/api.php` L11, L14–16 — comentario «token futuro»; grupo con `AuthMiddleware` | LB/cron no health-check sin cookie | `Presentation` / `routes/` | Framework | Backlog Fase 3: `GET /api/health` público |
| **D8** | Slug `permisos.gestionar` ausente (M5) | `routes/web.php` L61–65 — workaround `administracion.ver`; grep `permisos.gestionar` en `database/` → **0** | Catálogo RBAC acoplado | `Domain` RBAC | Framework | Backlog producto: seed + rutas si aprueba |
| **D9** | Sin pipeline GitHub Actions | `.github/workflows/` **ausente** (`ls` → No such file) | Tests solo manual/`tests/run.php` | Ops / repo | Framework/Ops | Spec futuro workflow mínimo |
| **Plan skeleton** | `skeleton.lebytek.com` pendiente | Plan `2026-07-26-skeleton-package-staging.md` Tasks 2–10 sin implementar | Staging package puro no desplegado | Ops / Framework | Framework/Ops | Ejecutar plan humano — complementario M8 |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **D3** | Portal SHA / `composer.lock` (M6) | `gh repo view Parzival2103/Lebytek_Portal` → 404; `gh api …/commits/main` → HTTP 404 | Ops: scope lectura Portal al token automation |
| **D14** | Stripe subscription QA Portal (#21) | Repo Portal inaccesible; Framework v1.2.1 publicado @ `fba3e03` | Portal: QA checkout + lock ≥ v1.2.1 antes de gate ON |
| **D15** | Bootstrap marketing Portal (#23) | Re-scopeado `Lebytek_Portal#4` — no inspeccionable aquí | Portal issue #4 |

### Verificado sin deuda nueva

- Migraciones post-baseline: 3 archivos en `database/migrations/` ↔ 3 entradas en manifiestos `config/modules/{core,crud-engine,pdf-kit}.php`.
- `src/` sin `TODO`/`FIXME` con impacto (grep → 0).
- Capas `Presentation`/`Application`/`Domain`/`Infrastructure` sin violaciones detectadas en `src/` (Domain solo referencia `app/Infrastructure` en comentarios de interfaz — convención documentada, no acoplamiento).
- Referencias operativas vivas a `feature/backoffice-api-integration`: **ausentes** en `scripts/`, `docs/composer-setup.md`, `docs/integration/` (gate `OpsDocsFpsAlignmentTest` PASS).
- Payments: `vertical.payments=false`; sin auto-fix de bootstrap documentado como requisito de este spec.

**Conteo:** **10 abiertos verificados** (D1–D2, D4–D9, D13 + Plan skeleton); **3 no verificados** (D3, D14–D15); **5 heredados cerrados** esta corrida (D10–D12, D16, D22).

---

*Design-only. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en la corrida AUTOMATION-01. Pase deuda técnica enriqueció este spec in-place.*
