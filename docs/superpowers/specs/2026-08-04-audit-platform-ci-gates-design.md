# Design: Gates de CI GitHub Actions para el harness de plataforma (D7)

**Fecha:** 2026-08-04  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel C)

**Auditoría fuente:** `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (fecha real del reporte: 2026-08-02, mergeado en `main` vía PR #67 @ `d372ad8`)  
**Estado post-audit verificado en tip `main` @ `c78e672`:** M1 semver y M9 dompdf **resueltos** (#74 + tag `v1.2.3` @ `041e402`); hook `afterListRows` publicado (#66); spec Portal `mkt_leads` en `docs/superpowers/specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md`. El hallazgo accionable restante prioritario de plataforma es **D7** (ausencia total de CI).

**Specs/planes relacionados:**

- Release integrity (implementado): `docs/superpowers/specs/2026-08-02-audit-v122-release-integrity-design.md` · plan archivado `docs/superpowers/plans/2026-08-02-audit-v122-release-integrity.md`
- Portal afterListRows (diseño): `docs/superpowers/specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md` · plan `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md`
- Skeleton staging (D6, separado): `docs/superpowers/specs/2026-07-26-skeleton-package-staging-design.md`
- Inventario deuda: `docs/audits/2026-07-28-deuda-tecnica-inventario.md` § D7
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `c78e672b73b8259a6cab6a7126aaf45354dded09` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-04` |
| Timestamp UTC | trigger cron `2026-08-04T12:10:00Z` / corrida agente `2026-08-04T12:15:00Z` / pase ux `2026-08-04T12:30:00Z` (modo **sin superficie UI**) / pase deuda `2026-08-04T13:03:00Z` (modo **normal**) |
| Pase deuda | `2026-08-04T13:03:00Z` UTC; SHA `origin/main` `c78e672b73b8259a6cab6a7126aaf45354dded09`; modo **normal** |
| Nivel de fuente | **C** — no hubo auditoría del día 2026-08-04; reporte más reciente en `origin/main`: `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (fecha real 2026-08-02, merge PR #67). Nivel A: `gh pr list --search "docs(audit):" --state open --base main` → vacío. Nivel B: rama `origin/automation/audit-2026-08-02` @ `a8331573ec94d65621dd77512ec7ccaf522af035` existe pero `git merge-base --is-ancestor origin/main a833157` → exit 1 (reporte ya mergeado por camino distinto) → rechazada. |
| PR auditoría fuente | #67 — mergeado 2026-08-02; head histórico `a8331573ec94d65621dd77512ec7ccaf522af035` |
| headRefOid fuente | `a8331573ec94d65621dd77512ec7ccaf522af035` (rama audit; no heredada) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |

---

## Problema

La auditoría del 2026-08-02 y el inventario D1–D11 registran **D7**: el repositorio Framework no tiene directorio `.github/` ni workflows de GitHub Actions. Los gates del harness (`php tests/run.php` y suites filtradas) sólo se ejecutan en entornos locales o en agentes cloud ad-hoc — sin garantía en PR ni en push a `main`.

**Evidencia verificada en tip `main` @ `c78e672`:**

| Comprobación | Resultado |
|--------------|-----------|
| `ls .github/workflows` | **No existe** — ni siquiera `.github/` |
| `composer.json` `require.php` | `>=8.1` |
| Versión plataforma sincronizada | `1.2.3` en `composer.json`, `config/app.php`, `skeleton/config/app.php` |
| `dompdf/dompdf` en lock | `v3.1.6` (M9 resuelto) |
| Archivos `*Test.php` bajo `tests/` | **159** (descubiertos por convención `tests/run.php`) |
| Tests Docs semver/dompdf | `tests/Docs/PlatformVersionSemverTest.php`, `tests/Docs/DompdfSecurityVersionTest.php` presentes |
| Tests Integrations con MySQL | `IntegrationAccountRepositoryTest.php` y similares usan `Connection::getInstance()` — requieren daemon MySQL + schema `int_*` |
| PHP CLI en agente cloud (esta corrida) | **Ausente** — no se pudo re-ejecutar suite; clasificado como bloqueador de entorno, no como regresión de código |
| Portal CI | **No verificable** (M6) — diseño Portal fuera de alcance |

**Consecuencia:** merges a `main` pueden introducir regresiones semver, advisories de dependencias o roturas en suites Kernel/Crud/Docs sin bloqueo automático. Los agentes de automation (AUTOMATION-00–08) dependen de instalar PHP/MySQL ad-hoc en cada corrida — frágil y no reproducible. La auditoría 2026-08-02 clasificó 7 fails Integrations como entorno (MySQL ausente) y 2 fails Docs como código (M1, ya resuelto); sin CI, esa distinción no es enforceable en PR.

**Deuda carry-forward registrada (fuera de alcance inmediato de este spec):** M3 (CRUD RBAC router), M4 (API sesión), M5 (`permisos.gestionar` seeds), M6 (gh Portal 404), D6 (`skeleton.lebytek.com` despliegue).

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

- **Propósito:** diseñar CI GitHub Actions en el repo Framework que ejecute gates reproducibles del harness de plataforma en cada PR y push a `main`, incluyendo servicio MySQL para Integrations.
- **Restricciones:** package source no desplegable; no CI de negocio Portal en este repo; no secrets de producción en workflows; no desactivar RBAC ni firmas; legacy `archive/backoffice-api-integration` solo evidencia histórica; operaciones VPS/producción fuera de alcance de automation desatendida.
- **Éxito:** workflow mergeable verde en tip `main`; PR futuro con regresión semver falla en CI; test gate `CiWorkflowPresentTest` pasa tras implementación; documentación mínima en `docs/core/despliegue-y-versionado.md` § CI; semver release del paquete **no requerido** (solo infra repo).

### Enfoques evaluados

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — Workflow único con job matrix PHP + MySQL service** | Un archivo `.github/workflows/platform-tests.yml`: matrix `php: ['8.1', '8.3']`, servicio `mysql:8.0`, steps `composer install` + `php tests/run.php` | Reproduce entorno audit; cubre Integrations; una sola fuente de verdad | Tiempo de CI ~3–5 min; requiere seed/migrate SQL en job |
| **B — Dos jobs: fast (sin DB) + full (con DB)** | Job `fast-gates` ejecuta Kernel, Docs, SkeletonPurity, Crud, Payments, Install; job `full` añade Integrations con MySQL | Feedback rápido en PR; fallos semver en <2 min | Dos jobs a mantener; duplicación de checkout/composer |
| **C — Solo job fast sin MySQL** | CI corre suites que no requieren PDO MySQL; Integrations quedan manuales | Implementación mínima | Deja 7+ tests Integrations sin gate; repite el gap de la auditoría 2026-08-02 |

**Recomendación:** **B** — job `platform-fast-gates` (obligatorio, bloquea merge) + job `platform-integration-gates` (obligatorio en PR a `main`, puede usar `continue-on-error: false`). El job fast alinea con lo que automation ya ejecuta cuando PHP está disponible; el job full cierra el hueco MySQL documentado en auditorías. Rechazar C: Integrations persisten en deuda silenciosa. A puro es aceptable si el maintainer prefiere simplicidad sobre latencia — B es preferible para DX en PRs frecuentes de docs/automation.

### Esbozo del diseño

```text
PR / push main
    │
    ├─► job platform-fast-gates (ubuntu-latest, PHP 8.3)
    │       composer install --no-interaction
    │       php tests/run.php Kernel
    │       php tests/run.php Docs
    │       php tests/run.php SkeletonPurity
    │       php tests/run.php Crud
    │       php tests/run.php Payments
    │       php tests/run.php Install
    │       composer validate --strict
    │       composer audit --no-dev (advisories = fail)
    │
    └─► job platform-integration-gates (needs: fast OR parallel)
            services: mysql:8.0 (health-check)
            env: DB_* alineado con .env.example harness
            php scripts/migrate.php (o equivalente documentado)
            php tests/run.php Integrations
```

---

## Comportamiento esperado

### Framework — workflow CI

1. En **pull_request** hacia `main` y en **push** a `main`, GitHub Actions dispara el workflow.
2. Job **fast** instala extensiones PHP: `mbstring`, `xml`, `curl`, `zip`, `sqlite3`, `pdo_mysql`.
3. `composer install --no-interaction --prefer-dist` sin dev extras innecesarios (lock commiteado).
4. Suites fast ejecutan con exit code 0 en tip `main` limpio.
5. Job **integration** levanta MySQL 8, aplica schema/migraciones plataforma (`database/schema/` + scripts existentes), ejecuta `php tests/run.php Integrations`.
6. `composer audit` falla el job si hay advisories en dependencias directas/transitivas (dompdf, etc.).
7. El workflow **no** despliega a VPS, **no** publica tags semver automáticamente, **no** toca `Lebytek_Portal`.

### Test gate TDD (debe existir antes de considerar implementación completa)

1. Nuevo test `tests/Docs/CiWorkflowPresentTest.php`:
   - Assert: existe `.github/workflows/platform-tests.yml`.
   - Assert: el YAML referencia `php tests/run.php` y al menos una suite fast (`Kernel` o `Docs`).
   - Assert: el YAML declara servicio `mysql` **o** step explícito de base de datos para Integrations.
2. **Estado pre-implementación:** test **rojo** (archivo workflow ausente) — motivo previsto documentado.
3. **Estado post-implementación:** test **verde**.

### Contratos públicos ausentes (no asumir APIs legacy)

- No existe contrato CI publicado en el paquete Composer — el workflow es **infra del repo fuente**, no exportable vía `vendor/lebytek/framework`.
- Portal **no hereda** este workflow; consumidores pueden copiar el patrón pero requieren spec/plan propio en `Lebytek_Portal` (no verificable hoy).
- Referencia histórica: rama legacy `archive/backoffice-api-integration` tenía `.github/workflows/tests.yml` en monolito — **solo evidencia histórica**; no usar como plantilla sin revisión FPS (incluía suites Marketing eliminadas).

---

## Alcance

| ID | Requisito | Owner | Repo / rama base |
|----|-----------|-------|------------------|
| F1 | Crear `.github/workflows/platform-tests.yml` con jobs fast + integration | Framework | `Lebytek_Framework` / `main` |
| F2 | Job fast: Kernel, Docs, SkeletonPurity, Crud, Payments, Install + `composer validate` + `composer audit` | Framework | idem |
| F3 | Job integration: servicio MySQL 8, migrate/seed mínimo plataforma, `php tests/run.php Integrations` | Framework | idem |
| F4 | Test gate `CiWorkflowPresentTest` en suite Docs | Framework | idem |
| F5 | Documentar CI en `docs/core/despliegue-y-versionado.md` (sección nueva § CI / gates PR) | Framework | idem |
| F6 | Branch protection recomendación: required check `platform-fast-gates` (documentar para operador; **no** configurar en esta corrida automation) | Ops | GitHub repo settings — **manual** |

### Semver / release Framework

- Cambios F1–F5 son **infra repo** — no modifican API del paquete `lebytek/framework`.
- **No** requiere tag semver nuevo ni bump `composer.json` `version`.
- Portal que consuma el paquete **no** se ve afectado por el workflow; su bump a `v1.2.3` sigue siendo independiente (spec 2026-08-03).

---

## No-alcance

- CI/CD de `Lebytek_Portal`, `WhatsApiLebytek` o despliegue VPS (`lebytek.com`, `waapi.lebytek.com`, `skeleton.lebytek.com`).
- Configuración de branch protection rules en GitHub (operador humano).
- Implementación M3 (RBAC middleware en rutas CRUD/calendario), M4 (token API), M5 (`permisos.gestionar`).
- Publicación automática de tags semver o Packagist/VCS release en push a `main`.
- Preinstalación PHP/MySQL en agentes cloud Cursor (mejora separada de entorno automation).
- Merge o referencia a `feature/backoffice-api-integration` como base.

---

## Ownership map

| Componente | Repositorio | Capa / ruta | Notas |
|------------|-------------|-------------|-------|
| Workflow CI | `Lebytek_Framework` | `.github/workflows/platform-tests.yml` | Infra repo; no va en `src/` |
| Test gate Docs | `Lebytek_Framework` | `tests/Docs/CiWorkflowPresentTest.php` | Descubre workflow |
| Scripts migrate harness | `Lebytek_Framework` | `scripts/` existentes | Reusar; no duplicar |
| Suite harness | `Lebytek_Framework` | `tests/run.php`, `tests/**` | Sin cambios de comportamiento |
| Doc operativa CI | `Lebytek_Framework` | `docs/core/despliegue-y-versionado.md` | § CI |
| Portal CI (futuro) | `Lebytek_Portal` | `.github/workflows/` | **No verificado** — spec futuro Portal |
| Branch protection | GitHub settings | — | Operador manual post-merge workflow |

---

## Dependencias y compatibilidad

| Dependencia | Versión / estado | Impacto |
|-------------|------------------|---------|
| PHP | 8.1–8.3 (matrix recomendada 8.1 + 8.3) | Alineado con `composer.json` y VPS documentados |
| Composer | 2.x | Lock commiteado; `composer validate --strict` |
| MySQL | 8.0 service container | Schema plataforma `int_*`, `auth_*`, etc. |
| GitHub Actions | `ubuntu-latest` | Runners estándar GitHub |
| Extensiones PHP | pdo_mysql, mbstring, xml, curl, zip | Requeridas por harness |
| `lebytek/framework` consumidores | Sin cambio de contrato | CI no altera API paquete |

**Migración segura:**

- **Base nueva (repo clone):** workflow corre tras `composer install`; no requiere `.env` local si el job exporta `DB_*` desde secrets/vars de Actions o valores inline de test (no producción).
- **Base existente (`main` tip):** añadir workflow es aditivo; primer PR verde confirma que tip `main` pasa gates. No migración de datos.

**Portal existente:** ninguna acción requerida para CI Framework; verificar independientemente cuando M6 se resuelva.

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| CI rojo por MySQL mal configurado en job | Media | Health-check servicio; documentar env vars; replicar `.env.example` |
| Tiempo de CI largo frustra PRs docs-only | Baja | Job fast <3 min; integration paralelo o `needs` opcional en iteración 2 |
| `composer audit` falla por advisory transitivo futuro | Media | Pin/update deps en PR dedicado; no silenciar audit |
| Falsos verdes si workflow no corre en fork PRs | Baja | Documentar permisos `pull_request` vs `pull_request_target` — usar `pull_request` estándar |
| Operador no habilita branch protection | Media | F5 documenta check requerido; fuera de automation |
| Confundir CI Framework con CI Portal | Media | Ownership map explícito; M6 impide verificar Portal |
| Merge a `main` sin CI mientras D7 abierto | **Alta** | Este spec + plan `2026-08-04-audit-platform-ci-gates.md`; regresiones semver/dompdf/Integrations no bloqueadas en PR |
| RBAC CRUD/calendario solo en servicio (M3) | Baja | Backlog CF6; 403 inconsistente hasta middleware router |
| Health LB usa `/api/ping` con sesión (M4) | Media | Documentar en § CI smoke; backlog CF7 `GET /api/health` |
| `permisos.gestionar` ausente (M5) | Baja | Workaround `administracion.ver` L61–65; backlog CF8 |
| LAB `skeleton.lebytek.com` sin deploy (D6) | Media | Plan `2026-07-26-skeleton-package-staging.md`; no bloquea CI Framework |
| Portal lock/handler P1 no inspeccionable (M6) | Media | Ops credenciales gh; spec 2026-08-03 fuera de alcance CI |

---

## Rollback

1. Revertir PR que añade `.github/workflows/platform-tests.yml` y test gate — vuelve estado pre-D7 (gates solo locales).
2. Deshabilitar workflow en GitHub UI sin borrar archivo — rollback operativo inmediato.
3. Quitar required check en branch protection si se configuró — operador manual.
4. **No** rollback de tags semver — este spec no publica tags.

---

## Compatibilidad, UX y responsive

### Modo del pase: sin superficie UI

Este spec trata exclusivamente de **infraestructura CI GitHub Actions** (workflow YAML, test gate Docs,
documentación operativa § CI). No introduce ni modifica pantallas, rutas HTTP de producto, assets CSS ni
flujos de login/dashboard. **Sin superficie UI en este spec.**

Los requisitos K/U/R siguientes documentan (a) compatibilidad verificada para el alcance CI/harness y
(b) **carry-forward UX** — ítems concretos que el próximo spec con superficie UI debe cubrir, derivados
de deuda abierta real (M3–M6, D6; M1/M2/M9 resueltos; D7 cubierto por este spec).

### Compatibilidad (verificado vs carry-forward)

| Área | Este spec (F1–F6) | Evidencia / carry-forward |
|------|-------------------|---------------------------|
| PHP soportado | **Alcance CI** | `composer.json` exige `>=8.1`; matrix recomendada **8.1 + 8.3** en workflow (Enfoque B). VPS documentado PHP **8.4.22** CLI/pool (`2026-07-26-skeleton-package-staging-design.md`) — compatible; CI no debe fijar sólo 8.4 hasta runner esté disponible en matriz. |
| Instalación vía `vendor/` | Sin cambio contrato paquete | CI corre `composer install` sobre lock commiteado; consumidores siguen instalando `lebytek/framework` semver en `vendor/` — workflow **no** se exporta vía paquete. |
| Health sin cookie de sesión | Carry-forward **M4** | `routes/api.php` L14–16 — grupo `/api` + `AuthMiddleware`; `/api/ping` requiere sesión. Smoke post-merge CI: **no** usar `/api/ping` como health LB; backlog `GET /api/health` público (CF7). |
| `.env.example` sin vars Portal | **Resuelto (M2)** — sin alcance | Root `.env.example` L53–55 remite vars `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` a Portal; job CI debe usar `DB_*` inline de test (no secretos prod). AC8 prohíbe Stripe live / DB prod en workflow. |
| Navegadores objetivo | N/A en este spec | Carry-forward: Chrome, Firefox, Safari, Edge últimas 2 versiones + iOS Safari ≥ 15 para admin; sin IE11. Baseline `docs/core/ui_ux.md`. |

### UX — flujos operativos CI y documentación (alcance de este spec)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U1** | § CI en `docs/core/despliegue-y-versionado.md`: comandos locales equivalentes al job fast (`composer install && php tests/run.php Kernel Docs …`) — operador reproduce fallo sin adivinar suite | F5 |
| **U2** | `CiWorkflowPresentTest`: mensaje de fallo cita ruta `.github/workflows/platform-tests.yml`, suites esperadas y acción («añadir workflow según spec D7») | F4 |
| **U3** | Status check GitHub: nombre job legible (`platform-fast-gates`, `platform-integration-gates`) — no IDs opacos; PR muestra qué suite falló | F1 |
| **U4** | Fallo MySQL en job integration: log Actions incluye hint («verificar health-check servicio mysql:8.0; replicar `DB_*` de `.env.example` harness») — no sólo PDO exception | F3 |
| **U5** | `composer audit` falla con advisory: mensaje indica paquete afectado y acción («composer update <pkg> en PR dedicado») — no silenciar | F2 |
| **U6** | Doc branch protection (F6): copy accionable para operador («Settings → Branches → required check: platform-fast-gates») — fuera automation, documentado | F6 |

### Carry-forward UX — próximo spec con superficie UI

Ítems derivados de deuda abierta verificada; **CF1–CF2 (semver harness + env purge), CF5 parcial (`mkt_leads`) y D7 (CI) quedan cubiertos** en specs previos o este spec — no se arrastran.

| # | Ítem | Origen | Requisito concreto |
|---|------|--------|-------------------|
| CF3 | Login responsive 320–768px | `ui_ux.md` | `.ct-login-page`, `.ct-login-card` sin overflow horizontal; tap targets ≥44px; sin scroll lateral en 320px. |
| CF4 | Dashboard admin responsive 320–768px | layouts side/top/bottom | Nav colapsable; KPI grid legible; topbar sin solapamiento de acciones. |
| CF5′ | Tablas CRUD restantes | módulo CRUD, D6 | `table-responsive` + `list.columns[].priority` en recursos distintos de `mkt_leads`; toolbar móvil. |
| CF6 | RBAC router CRUD/calendario | M3 | Errores 403 accionables (slug requerido vs permiso denegado). |
| CF7 | Health endpoint público | M4 | `GET /api/health` 200 sin cookie; body `{ "status": "ok" }`; checklist VPS. |
| CF8 | Permisos admin catálogo | M5 | Slug `permisos.gestionar` en seeds; UI permisos sin workaround `administracion.ver`. |
| CF9 | Estados vacío / error / carga (global) | `ui_ux.md` §8 | Unificar empty states en CRUDs sin hook; spinners list; validación con hint de corrección. |
| CF10 | Copy errores accionables (transversal) | transversal | Auth, wizard install, CRUD save: qué falló + qué hacer. |

### Responsive

**N/A en este spec** (sin superficie UI). Los ítems **CF3–CF5′** del carry-forward son el backlog
responsive verificado para el próximo spec con pantallas.

---

## Criterios de aceptación

- [ ] **AC1:** Existe `.github/workflows/platform-tests.yml` con al menos dos jobs (fast + integration) o un job único documentado que cubre ambos.
- [ ] **AC2:** Push de prueba a rama feature ejecuta workflow y reporta status en GitHub (verde en tip `main` limpio).
- [ ] **AC3:** `php tests/run.php Docs` incluye `CiWorkflowPresentTest` **verde** post-implementación.
- [ ] **AC4:** Regresión deliberada en `config/app.php` version (≠ `composer.json`) hace fallar job Docs en CI.
- [ ] **AC5:** Job integration ejecuta ≥1 test de `tests/Integrations/` contra MySQL real (no skip silencioso).
- [ ] **AC6:** `composer audit` en CI falla si se pinna dompdf `<3.1.6` (validación manual una vez).
- [ ] **AC7:** Documentación § CI en `despliegue-y-versionado.md` describe comandos locales equivalentes.
- [ ] **AC8:** Ningún secret de producción (Stripe live, DB prod, SSH keys VPS) en el workflow.
- [ ] **AC9:** `git diff origin/main...HEAD` del PR de implementación no incluye cambios en `src/`, `app/`, `database/` schema de negocio Portal, ni `skeleton/` salvo doc cross-ref si aplica.

### Compatibilidad, UX y responsive

- [ ] **AC-UX1:** Sección **Compatibilidad, UX y responsive** declara modo **sin superficie UI** con requisitos K/U/R verificables para F1–F6 (CI/docs).
- [ ] **AC-UX2:** Requisitos U1–U6 (reproducibilidad local, mensajes test gate, nombres jobs, hints MySQL/audit, doc branch protection) incluidos como criterios del spec.
- [ ] **AC-UX3:** Carry-forward CF3–CF4, CF5′, CF6–CF10 documentado; CF1–CF2, CF5 parcial (`mkt_leads`) y D7 no arrastrados (resueltos o cubiertos por este spec).

### Deuda técnica (inventario)

- [ ] **AC-D1:** Sección **Deuda técnica** lista ítems abiertos verificados (D7, M3–M5, D6) con evidencia ruta/línea en `main` @ `c78e672`.
- [ ] **AC-D2:** M1, M9, D1–D2, D4–D5, D13, M2, M7, M8, D10–D12, D16, D22, D17–D21 reconciliados como **resueltos**; no re-listados como abiertos.
- [ ] **AC-D3:** P1, M6/D3, D14, D15 marcados **no verificados** Portal; acción concreta documentada.
- [ ] **AC-D4:** Verificado sin deuda nueva — migraciones post-baseline 3 SQL ↔ 3 entradas manifiesto (`config/modules/{core,crud-engine,pdf-kit}.php` L15–16); `src/` sin `TODO`/`FIXME`; referencias operativas vivas a `feature/backoffice-api-integration` ausentes en `scripts/`, `docs/composer-setup.md`, `docs/integration/`; `docs/core/despliegue-y-versionado.md` sin § CI (deuda D7/F5, no drift doc↔código pre-implementación).

---

## Operaciones por entorno

| Operación | Implementación (dev) | Staging | Producción |
|-----------|---------------------|---------|------------|
| Añadir workflow YAML | PR a `main` | N/A | N/A |
| Ejecutar gates localmente | `composer install && php tests/run.php` | N/A | N/A |
| Habilitar required check | — | — | **Operador GitHub** — manual, post-merge |
| Deploy VPS / skeleton | — | Plan D6 separado | **Fuera de alcance** automation |

---

## Deuda técnica

Fuente: auditoría `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (PR #67 @ `d372ad8`); reconciliación con inventario spec `2026-08-03` (pase deuda @ `041e402`) y tip `origin/main` @ `c78e672`.

### Reconciliación heredada

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| **M1** | Sync semver tres archivos | **Resuelto** | PR #74 + tag `v1.2.3` — `composer.json` L6, `config/app.php` L7, `skeleton/config/app.php` L7 → `1.2.3`; `PlatformVersionSemverTest` presente |
| **M9** | dompdf advisories &lt;3.1.6 | **Resuelto** | PR #74 — `composer.lock` fija `dompdf/dompdf` **v3.1.6**; `DompdfSecurityVersionTest` presente |
| **D1** | Drift semver plataforma | **Resuelto** | #62 + #74 — ver M1 |
| **D2** | Root `.env.example` vars Portal | **Resuelto** | PR #62 — keys activas `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` = **0**; comentario L53–55 |
| **D4** | Test gate semver ausente | **Resuelto** | PR #62 — `tests/Docs/PlatformVersionSemverTest.php` presente |
| **D5** | `FrameworkRootNotPortalTest` env | **Resuelto** | PR #62 — assert prefijos en test |
| **D13** | Checklist release semver | **Resuelto** | PR #62 — `docs/core/despliegue-y-versionado.md` + `ReleaseChecklistDocTest` |
| **M2** | `.env.example` Marketing | **Resuelto** | PR #62 — sin regresión |
| **M7** | Audit PR sin merge | **Resuelto** | #54/#55/#60/#67 |
| **M8 / D5 docs** | Docs ops legacy | **Resuelto** | #56/#57 + `OpsDocsFpsAlignmentTest` |
| **D10–D12, D16, D22** | Runbooks/composer-setup/VPS | **Resuelto** | #56/#57 — `grep feature/backoffice-api-integration` en ops docs → 0 |
| **D17–D21** | Cadena audit M7 | **Resuelto** | PRs #51, #54, #55 |

**Cierres desde corrida anterior (2026-08-03):** **0** — intervalo `041e402..c78e672` en `main` sólo añadió docs automation/spec 2026-08-03 (#75, #76); sin cambios de código que cierren M3–M6 ni D6/D7.

### Alcance principal de este spec (D7 — abierto, verificado)

| ID | Hallazgo | Evidencia (`main` @ `c78e672`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **D7** | Sin pipeline GitHub Actions | `git ls-tree origin/main .github` → vacío; `ls .github/workflows` → **No existe**; `tests/Docs/CiWorkflowPresentTest.php` **ausente**; `docs/core/despliegue-y-versionado.md` sin § CI | Regresiones semver/dompdf/Integrations no bloqueadas en PR; gates solo local/agente ad-hoc | Ops / repo | Framework/Ops | F1–F6 + plan `2026-08-04-audit-platform-ci-gates.md` (rama spec; no mergeado a `main`) |

### Backlog Framework verificado (fuera alcance F1–F6)

| ID | Hallazgo | Evidencia (`main` @ `c78e672`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M3** | CRUD/Calendario sin `RbacMiddleware` router | `routes/web.php` L114–125 — `/crud/{resource}`, `/calendario/{key}` solo heredan `AuthMiddleware` del grupo admin | RBAC delegado a servicio; 403 inconsistente | `Presentation` / `routes/` | Framework | Backlog: middleware router-level o documentar patrón (CF6) |
| **M4** | API `/api/*` autenticada por sesión | `routes/api.php` L11 (comentario token futuro), L14–16 (`AuthMiddleware` grupo), L23 (`/api/ping`) | LB/cron no health-check sin cookie | `Presentation` / `routes/` | Framework | Backlog Fase 3: `GET /api/health` público (CF7) |
| **M5** | Slug `permisos.gestionar` ausente | `routes/web.php` L61–65 — workaround `administracion.ver`; `grep permisos.gestionar database/` → **0** | Catálogo RBAC acoplado | `Domain` RBAC | Framework | Backlog producto: seed + rutas (CF8) |
| **D6** | Plan `skeleton.lebytek.com` sin implementar | `docs/ENVIRONMENTS.md` L6, L13, L31 — «skeleton.lebytek.com pendiente»; plan `2026-07-26-skeleton-package-staging.md` Tasks 2–10 sin deploy | LAB package puro no desplegado | Ops / Framework | Framework/Ops | Ejecutar plan humano Tasks 6–8 |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal no consume hook `afterListRows` | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL fail; última evidencia `composer.lock` Portal con `lebytek/framework` **v1.1.0** @ `a79d3ad` | Spec 2026-08-03 + plan Portal; confirmar lock ≥ `v1.2.2` cuando M6 se resuelva |
| **M6 / D3** | Portal SHA / `composer.lock` | `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 | Ops: conceder lectura Portal al token automation |
| **D14** | Stripe subscription QA Portal (#21) | Repo Portal inaccesible; Framework contrato base resuelto @ `v1.2.3` | Portal: QA checkout antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` |
| **D15** | Bootstrap marketing Portal (#23) | Re-scopeado `Lebytek_Portal#4` — no inspeccionable aquí | Portal issue #4 |

### Verificado sin deuda nueva

- **Migraciones ↔ manifiesto:** 3 archivos en `database/migrations/` ↔ 3 entradas en `config/modules/core.php` L15–16, `crud-engine.php` L14–15, `pdf-kit.php` L16 — sin drift.
- **`src/`:** grep `TODO`/`FIXME` → **0** con impacto.
- **Capas:** sin violaciones nuevas en `src/` (hook `afterListRows` en `Application`; Domain sin deps Presentation/Infrastructure).
- **Legacy operativo:** referencias vivas a `feature/backoffice-api-integration` o `dev-feature/backoffice-api-integration` **ausentes** en `scripts/`, `docs/composer-setup.md`, `docs/integration/`.
- **Payments bootstrap:** `vertical.payments=false` en harness; requisitos Stripe documentados como gate ops Portal (D14), no auto-fix en `src/`.
- **Semver/dompdf:** tres fuentes `1.2.3`; lock dompdf `v3.1.6` — sin regresión M1/M9.

**Conteo:** **5 abiertos verificados** (D7, M3–M5, D6); **4 no verificados** (P1, M6/D3, D14, D15); **0 heredados cerrados** esta corrida.

---

*Report-only design spec. Ningún archivo de código, workflow, config, rutas, migraciones ni tests fue modificado en esta corrida.*
