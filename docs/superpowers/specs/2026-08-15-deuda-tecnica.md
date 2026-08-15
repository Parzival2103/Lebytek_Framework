# Inventario de deuda técnica — 2026-08-15

**Modo:** degradado — sin spec del día (`docs/superpowers/specs/2026-08-15-audit-*-design.md` ausente; rama `automation/spec-2026-08-15` creada desde `origin/main` porque no existía rama del día).

**Repositorio:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Rama:** `automation/spec-2026-08-15`  
**Auditoría fuente más reciente:** `docs/audits/2026-08-14-auditoria-tecnica-diaria.md` (mergeada en `main` @ `990776e` — PR #124; 0 hallazgos nuevos; delta docs-only respecto a tip código `v1.2.11`). **No existe** auditoría `2026-08-15` en `main` al inspeccionar.  
**Inventario anterior:** `docs/superpowers/specs/2026-08-14-audit-post-v1211-debt-design.md` § Deuda técnica (pase deuda 2026-08-14 @ `990776e`, rama `automation/spec-2026-08-14`, commit `cef1b79`).  
**Spec relacionado pendiente de merge:** mismo archivo 2026-08-14 (PRs #121/#123) — remediación M11/M5/P-LOCK; no sustituye este inventario degradado.

**Hallazgo principal:** Tip código ≡ **`v1.2.11`** @ `990776e`; **0 delta código** desde pase deuda 2026-08-14. Deuda dominante sin cambio: **M11** (harness sesión), **M5** (RBAC permisos), **P-LOCK** (consumidores sin bump lock), más backlog ops/docs **M6**, **M10**, **D6**, **H1–H4**, estructural **D11–D12**.

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | deuda técnica (inventario degradado) |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `990776e63a68919e4ae0576abb80bf9cf07725eb` |
| Rama generada | `automation/spec-2026-08-15` (creada; no existía rama del día; rama `automation/spec-*` más reciente con ancestry limpia: `automation/spec-2026-08-14`) |
| Modo | **degradado** — sin `2026-08-15-audit-*-design.md` |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD |
| Auditoría fuente | PR #124 — `docs/audits/2026-08-14-auditoria-tecnica-diaria.md` (última disponible en `main`) |
| Spec del día | **Ausente** — cadena spec activa: `2026-08-14-audit-post-v1211-debt-design.md` (#121/#123) |
| Pase deuda (AUTOMATION-02) | `2026-08-15T13:01:56Z` — modo **degradado** — `origin/main` @ `990776e63a68919e4ae0576abb80bf9cf07725eb` |

---

## Reconciliación heredada

Verificación contra `origin/main` @ `990776e` (idéntico al pase 2026-08-14; sin commits nuevos en `main` desde entonces).

### Cerrados (permanecen resueltos; no re-listar como abiertos)

| ID | Tema | Resolución |
|----|------|------------|
| **CRUD-C4** | CAS/TOCTOU transitions | PR #118 / tag `v1.2.11` |
| **REL-C1** | Tags semver | `v1.2.7`…`v1.2.11`; trío sync (`composer.json` L6, `config/app.php` L7 → `1.2.11`) |
| **M3** | CRUD RBAC router | PR #114 / `v1.2.10` |
| **M4** | `/api/health` público | PR #114 / `v1.2.10` |
| **INV-E1/E2** | Invoicing vertical OFF | `config/vertical.php` L20–23 |
| **M1/M2/M7/M8/M9/D7** | Semver/env/CI/docs | Sin regresión |
| **D10-ops** | Legacy branch en runbooks | `scripts/` sin refs `backoffice-api-integration`; `docs/composer-setup.md` L128 solo tag archive histórico |

**Cierres desde pase deuda 2026-08-14:** **0** — `origin/main` sin delta respecto a `990776e` inspeccionado ayer.

### Parcialmente avanzado (spec branch, no en `main`)

| ID | Tema | Estado en `main` | Nota |
|----|------|------------------|------|
| **H2** | Planes M3/M4 checkboxes obsoletos | **Abierto** en `main` | En ramas `automation/spec-2026-08-13`/`2026-08-14`: planes archivados/marcados; **pendiente merge** a `main` |

---

## Deuda técnica

### Alcance principal (abiertos, verificados @ `990776e`)

| ID | Alias | Hallazgo | Evidencia | Impacto | Capa | Owner | Acción |
|----|-------|----------|-----------|---------|------|-------|--------|
| **D1** | M11 | Contaminación sesión monolito | `tests/run.php` L29–31 — carga secuencial sin teardown; `tests/lib/microtest.php` L7–17 — `test()` sin reset; `tests/Auth/LoginUseCaseTest.php` L91–98 — login deja sesión; `tests/Kernel/ApiHealthPublicDispatchTest.php` L30–36, L55–67 — fallan en monolito; `MonolithicHarnessSessionIsolationTest` **ausente** | `php tests/run.php` monolito → falsos negativos Auth/ping; CI aislada verde | `tests/` harness | Framework | Reset post-test en `microtest.php` + gate TDD (spec 2026-08-14 F1–F2) |
| **D2** | M5 | Slug `permisos.gestionar` ausente | `routes/web.php` L62–66 + `skeleton/routes/web.php` L62–66 — workaround `administracion.ver`; `database/schema/schema.sql` L299 y `database/seeds_legacy/010_auth_permisos.sql` L2–3 sin slug; `config/modules/core.php` L20–23 sin slug; `rg permisos.gestionar database/` → **0**; `PermisosGestionarSlugTest` **ausente** | Rol con `administracion.ver` gestiona catálogo RBAC | `Domain` RBAC / `routes/` | Framework | Seed + migración + rutas + tag `v1.2.12` propuesto (spec 2026-08-14 F3–F6) |
| **D3** | P-LOCK | Consumidores sin bump ≥ `v1.2.11` | Framework tip `1.2.11` tagueado; Portal lock **no verificado** (D4) — última evidencia doc `v1.1.0` @ `a79d3ad` | Portal/CRM sin CAS/C6/M3/M4 en prod | Consumidor Composer | `Lebytek_Portal` | Bump lock manual + smoke staging |

### Backlog ops y hygiene (abiertos, verificados)

| ID | Alias | Hallazgo | Evidencia | Impacto | Capa | Owner | Acción |
|----|-------|----------|-----------|---------|------|-------|--------|
| **D4** | M6 | Portal SHA / lock no inspeccionable | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (reconfirmado 2026-08-15) | Automation no verifica bump consumidor | Ops / credenciales | Ops | Conceder lectura gh al token |
| **D5** | M10 | Huecos auditorías diarias | `docs/audits/` — ausentes `2026-08-03`, `2026-08-04`, `2026-08-05`, `2026-08-10`; presentes 01–02, 06–09, 11–14; **sin** `2026-08-15` al escribir | Cadena diseño con huecos; spec del día ausente hoy | Proceso automation | Ops | Backfill o aceptación documentada; AUTOMATION-00 emitir audit 2026-08-15 |
| **D6** | D6 | `skeleton.lebytek.com` pendiente | `docs/ENVIRONMENTS.md` L7–8, L13, L34 — «pendiente de implementar» | LAB package no desplegado | Ops | Framework/Ops | Plan `2026-07-26-skeleton-package-staging-design.md` |
| **D7** | H1 | Release notes `v1.2.8` ausentes | `docs/release/` — `v1.2.7.md`, `v1.2.9.md`, `v1.2.10.md`, `v1.2.11.md`; tag git `v1.2.8` existe; **sin** `v1.2.8.md` | Paridad cadena release C6 uploads | `docs/` | Framework | Stub retroactivo PR #111 |
| **D8** | H2 | Planes M3/M4 checkboxes obsoletos | `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` — **41** `[ ]`; `2026-08-05-audit-api-health-public.md` — **39** `[ ]` pese a ship #114 / `v1.2.10` | Implementador puede reabrir trabajo cerrado | `docs/` | Framework | Archivar/marcar shipped (parcial en spec branches) |
| **D9** | H3 | PRs spec C4 duplicados/obsoletos | PRs abiertos `#116` (C4+M11), `#117` (C4 duplicado) — C4 mergeado #118; `#121`/`#123` duplican M11/M5 | Ruido proceso | GitHub | AUTOMATION-03 | Cerrar/consolidar PRs |
| **D10** | H4 | `composer validate` lock drift | `.github/workflows/platform-tests.yml` L22–25 — `composer validate --no-check-lock` | Hygiene semver/lock; no bloquea CI | CI | Framework | PR hygiene opcional post-`1.2.12` |

### Deuda estructural carry-forward (abierta, verificada)

| ID | Hallazgo | Evidencia | Impacto | Capa | Owner | Acción |
|----|----------|-----------|---------|------|-------|--------|
| **D11** | Domain depende de Application (6 interfaces) | `src/Domain/Interfaces/MailerInterface.php` L7; `CrudValidatorInterface.php` L7; `CrudTransitionGuardInterface.php` L7; `CrudListScopeInterface.php` L7; `CrudHookHandlerInterface.php` L7; `CrudActionHandlerInterface.php` L7 — importan DTOs/contextos Application | Violación capa onion; acoplamiento CRUD | `Domain` | Framework | Refactor futuro (spec dedicado) |
| **D12** | Suites Auth/Calendar ausentes en CI | `.github/workflows/platform-tests.yml` L48–139 — jobs Kernel, Docs, SkeletonPurity, Crud, Payments, Install, Integrations; **sin** `php tests/run.php Auth` ni Calendar | M11 no detectado en CI; regresiones Auth locales posibles | CI / `tests/` | Framework | Spec CI dedicado (fuera F1–F8) |

### Verificado sin deuda nueva (registro @ `990776e`)

| Comprobación | Resultado |
|--------------|-----------|
| Migraciones ↔ manifiesto | 3 archivos `database/migrations/*.sql` ↔ `config/modules/core.php` L15–17, `crud-engine.php` L14–16, `pdf-kit.php` L16; gate `tests/Install/SchemaBootstrapTest.php` L75–84 |
| Capas `src/` TODO/FIXME | Grep vacío — **0** coincidencias |
| Legacy operativo | `scripts/` sin refs `backoffice-api-integration`; `docs/integration/` limpio |
| Payments bootstrap | `config/vertical.php` L20–23 — `marketing`/`payments`/`invoicing` = `false`; `tests/Payments/PaymentsConfigTest.php` |
| Bootstrap schema | Sin migraciones huérfanas ni drift manifiesto |
| CI gates descubren tests | `.github/workflows/platform-tests.yml` presente; suites aisladas no triviales |
| `.env.example` root vs skeleton | Drift intencional (harness vs tenant template); root remite vars Portal a `Lebytek_Portal` |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ `v1.2.11` | D4 — gh 404 Portal | Operador: `composer require lebytek/framework:^1.2.11` |
| **P2** | Smoke CAS staging Portal | D4 | Operador manual post-P1 |
| **P3** | Portal semver en `/admin/sistema/estado` | D4 + D6 | Verificación manual staging/prod |
| **Portal issues abiertos** | Conteo issues Portal | D4 — gh 404 | Ops conceder acceso |
| **H5** | `composer validate --strict` en tip | No ejecutado en corrida (inventario estático) | Maintainer en release train |

**Resumen:** **12 ítems abiertos verificados** (D1–D12); **5 no verificados** (P1–P3, Portal issues, H5); **0 cierres** heredados desde pase 2026-08-14.

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Portal sin bump ≥ `v1.2.11` (P-LOCK) | Alta | Checklist ops; resolver M6 para verificación automation |
| M5 rompe acceso permisos para roles legacy | Media | Migración asigna slug a rol admin; release notes `v1.2.12` |
| M11 enmascara regresiones Auth/ping en DX local | Media | Fix harness + gate TDD antes de tag `1.2.12` |
| Portal SHA desconocido (M6) | Media | Marcar P1/P2/P3 no verificados |
| PRs `#116`/`#117`/`#121`/`#123` confunden prioridad | Baja | Cerrar/consolidar en AUTOMATION-03 |
| Gates TDD M11/M5 ausentes pre-implementación | Media | Tests documentados en spec 2026-08-14 aún no existen en tip |
| Consumidor lock &lt; `1.2.11` mantiene TOCTOU CRUD | Alta | P-LOCK; no asumir prod al día solo por tag Framework |
| Spec + audit del día ausentes (2026-08-15) | Baja | Este inventario degradado; AUTOMATION-00/01 deben retomar cadence |
| Domain→Application acoplamiento (D11) | Baja | Spec refactor dedicado; no bloquea M11/M5 |

---

## Criterios de aceptación

- [x] **AC-D1:** Inventario lista abiertos verificados (D1–D12) con evidencia ruta/línea en `main` @ `990776e`.
- [x] **AC-D2:** Reconciliación heredada — 0 cierres desde pase 2026-08-14; resueltos históricos no re-listados como abiertos.
- [x] **AC-D3:** Ítems no verificables (P1–P3, Portal issues, H5) declarados explícitamente.
- [x] **AC-D4:** Verificado sin deuda nueva — migraciones/manifiesto, capas, legacy ops, Payments OFF, bootstrap schema, CI gates.
- [ ] **AC-D5:** Spec del día presente — **no cumplido** (modo degradado); cadena spec 2026-08-14 pendiente merge (#121/#123).

---

## No-alcance

- Implementación producto en `src/`, `tests/`, `database/`, `skeleton/`, `routes/`, `config/` — pertenece a AUTOMATION-04/plan 2026-08-14.
- Negocio Portal (Marketing, leads, membresías, `LebytekApiClient`) — repo `Lebytek_Portal`.
- Merge/deploy/SSH/producción en corrida cron desatendida.
- Merge `feature/backoffice-api-integration` → `main`.
- Escritura bajo `docs/audits/` — pertenece a AUTOMATION-00.
- Apertura/cierre de PRs — pertenece a AUTOMATION-03.
- Auto-fix de M6 (token gh Portal), D6 (`skeleton.lebytek.com`) ni P-LOCK consumidor — solo registro y acción ops documentada.

---

*Inventario degradado. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
