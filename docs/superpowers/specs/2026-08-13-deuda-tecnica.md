# Inventario de deuda técnica — 2026-08-13

**Modo:** degradado — sin spec del día (`docs/superpowers/specs/2026-08-13-audit-*-design.md` ausente en rama `automation/spec-2026-08-13`).

**Repositorio:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Rama:** `automation/spec-2026-08-13`  
**Auditoría fuente:** `docs/audits/2026-08-13-auditoria-tecnica-diaria.md` (mergeada en `main` @ `cea890e` — PR #122; 0 hallazgos nuevos; delta docs-only respecto a tip código `v1.2.11`).  
**Inventario anterior:** `docs/superpowers/specs/2026-08-12-audit-post-v1211-debt-design.md` § Deuda técnica (pase deuda 2026-08-12 @ `dc587b9`).  
**Spec relacionado pendiente de merge:** `docs/superpowers/specs/2026-08-12-audit-post-v1211-debt-design.md` (PR #121 / #123) — remediación M11/M5/P-LOCK; no sustituye este inventario degradado.

**Hallazgo principal:** Tip código ≡ **`v1.2.11`** (`cf9e67e`); **0 críticos de código abiertos**. Deuda dominante: **M11** (harness sesión), **M5** (RBAC permisos), **P-LOCK** (consumidores sin bump lock), más backlog ops/docs **M6**, **M10**, **D6**, **H1–H4**.

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | deuda técnica (inventario degradado) |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `cea890e34d53ad2e400237136bc691a98e030511` |
| Rama generada | `automation/spec-2026-08-13` |
| Modo | **degradado** — sin `2026-08-13-audit-*-design.md` |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD |
| Pase deuda (AUTOMATION-02) | `2026-08-13T13:19:17Z` — modo **degradado** — `origin/main` @ `cea890e34d53ad2e400237136bc691a98e030511` |
| Auditoría fuente | PR #122 — `docs/audits/2026-08-13-auditoria-tecnica-diaria.md` |
| Spec del día | **Ausente** — cadena spec activa: `2026-08-12-audit-post-v1211-debt-design.md` (#121) |

---

## Reconciliación heredada

Verificación contra `origin/main` @ `cea890e` (árbol código ≡ `v1.2.11`; único delta desde `dc587b9` = audit doc #122).

### Cerrados (permanecen resueltos; no re-listar como abiertos)

| ID | Tema | Resolución |
|----|------|------------|
| **CRUD-C4** | CAS/TOCTOU transitions | PR #118 / tag `v1.2.11` @ `cf9e67e` |
| **REL-C1** | Tags semver | `v1.2.7`…`v1.2.11`; trío sync (`composer.json` L6 `1.2.11`) |
| **M3** | CRUD RBAC router | PR #114 / `v1.2.10` — `CrudRbacMiddleware` en rutas |
| **M4** | `/api/health` público | PR #114 / `v1.2.10` — `routes/api.php` L15 |
| **D7** | CI GitHub Actions | `.github/workflows/platform-tests.yml` presente |
| **M1** | Sync semver trío | Tip `1.2.11` |
| **M2/M7/M8/M9** | Env Portal / ops / dompdf | Sin regresión audit 2026-08-13 |
| **INV-E1/E2** | Invoicing vertical OFF | `config/vertical.php` L20–23 |

**Cierres desde pase deuda 2026-08-12:** **0** en tip código — avance en `main` = merge audit #122 (docs-only).

### Parcialmente avanzado (spec branch, no en `main`)

| ID | Tema | Estado en `main` | Nota |
|----|------|------------------|------|
| **H2** | Planes M3/M4 checkboxes obsoletos | **Abierto** en `main` | En rama `automation/spec-2026-08-13`: planes archivados y marcados **Completo** (commits reconcile 2026-08-13); **pendiente merge** a `main` |

---

## Deuda técnica

### Alcance principal (abiertos, verificados)

| ID | Hallazgo | Evidencia (`main` @ `cea890e`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M11** | Contaminación sesión suite monolítica | `tests/run.php` L29–31 — `sort($files)` + `require` secuencial; `tests/lib/microtest.php` L7–17 — `test()` sin reset `$_SESSION`; `tests/Auth/LoginUseCaseTest.php` L91–98 — login exitoso deja sesión; `tests/Kernel/ApiHealthPublicDispatchTest.php` L30–36, L55–67 — esperan 302/no-200 en `/api/ping` sin sesión; `MonolithicHarnessSessionIsolationTest` **ausente** | `php tests/run.php` monolito → 802/9 (2 M11 + 7 MySQL env); CI aislada verde | `tests/` harness | Framework | Reset post-test en `microtest.php` + gate TDD (spec #121) |
| **M5** | Slug `permisos.gestionar` ausente | `routes/web.php` L62–66 — comentario workaround + `RbacMiddleware('administracion.ver')`; `skeleton/routes/web.php` L63–66 espejo; `config/modules/core.php` L20–23 — permisos sin `permisos.gestionar`; `rg permisos.gestionar database/` → **0**; `PermisosGestionarSlugTest` **ausente** | Rol con `administracion.ver` gestiona catálogo RBAC | `Domain` RBAC / `routes/` | Framework | Seed + migración + rutas + tag `v1.2.12` propuesto (spec #121) |
| **P-LOCK** | Consumidores sin bump ≥ `v1.2.11` | Framework tip tagueado `v1.2.11`; Portal lock **no verificado** (M6) — última evidencia doc `v1.1.0` @ `a79d3ad` | Portal/CRM sin CAS/C6/M3/M4 en prod | Consumidor Composer | `Lebytek_Portal` | Bump lock manual + smoke staging |

### Backlog verificado (fuera implementación Framework inmediata)

| ID | Hallazgo | Evidencia (`main` @ `cea890e`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M6** | Portal SHA / lock no inspeccionable | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL fail; `gh api …/commits/main` → HTTP 404 (reconfirmado 2026-08-13) | Automation no verifica bump consumidor | Ops / credenciales | Ops | Conceder lectura gh al token |
| **M10** | Huecos auditorías diarias | `docs/audits/` — ausentes `2026-08-03`, `2026-08-04`, `2026-08-05`, `2026-08-10`; presentes 06–09, 11–13 | Cadena diseño sin ancla en esas fechas | Proceso automation | Ops | Backfill o aceptación documentada |
| **D6** | `skeleton.lebytek.com` pendiente | `docs/ENVIRONMENTS.md` L7, L13, L34 — «pendiente de implementar» | LAB package no desplegado | Ops | Framework/Ops | Plan `2026-07-26-skeleton-package-staging-design.md` |
| **H1** | Release notes `v1.2.8` ausentes | `docs/release/` — `v1.2.7.md`, `v1.2.9.md`, `v1.2.10.md`, `v1.2.11.md`; **sin** `v1.2.8.md` | Paridad cadena release C6 uploads | `docs/` | Framework | Stub retroactivo PR #111 |
| **H2** | Planes M3/M4 checkboxes obsoletos | `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` — **41** `[ ]`; `2026-08-05-audit-api-health-public.md` — **39** `[ ]` pese a ship #114 / `v1.2.10` | Implementador puede reabrir trabajo cerrado | `docs/` | Framework | Marcar shipped / archivar (parcial en spec branch) |
| **H3** | PRs spec C4 duplicados/obsoletos | PRs abiertos `#116` (C4+M11), `#117` (C4 duplicado) — C4 mergeado #118 | Ruido proceso | GitHub | AUTOMATION-03 | Cerrar PRs obsoletos |
| **H4** | `composer validate` lock drift | `.github/workflows/platform-tests.yml` L22–25 — `composer validate --no-check-lock`; audit #122 documenta exit 2 | Hygiene semver/lock; no bloquea CI | CI | Framework | PR hygiene opcional post-`1.2.12` |

### Verificado sin deuda nueva (registro)

| Comprobación | Resultado @ `cea890e` |
|--------------|-------------------------|
| Migraciones ↔ manifiesto | 3 archivos `database/migrations/*.sql` ↔ `core.php` L15–17, `crud-engine.php` L14–16, `pdf-kit.php` L16; gate `tests/Install/SchemaBootstrapTest.php` L75–84 |
| Capas `src/` | Sin `TODO`/`FIXME` (grep vacío) |
| Legacy operativo | `scripts/` sin refs `backoffice-api-integration`; `docs/integration/` limpio; `docs/composer-setup.md` L128 solo tag archive histórico |
| Payments bootstrap | `config/vertical.php` L20–23 — `marketing`/`payments`/`invoicing` = `false` |
| Bootstrap schema | Sin migraciones huérfanas ni drift manifiesto detectado |
| CI gates | `.github/workflows/platform-tests.yml` presente; suites aisladas descubren tests (audit #122) |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ `v1.2.11` | M6 — gh 404 Portal | Operador: `composer require lebytek/framework:^1.2.11` |
| **P2** | Smoke CAS staging Portal | M6 | Operador manual post-P1 |
| **P3** | Portal semver en `/admin/sistema/estado` | M6 + D6 | Verificación manual staging/prod |
| **H5** | `composer validate --strict` en tip | No ejecutado en corrida (PHP/composer local no requerido para inventario) | Maintainer en release train |

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Portal sin bump ≥ `v1.2.11` | Alta | P-LOCK checklist ops; resolver M6 para verificación automation |
| M5 rompe acceso permisos para roles legacy | Media | Migración asigna slug a rol admin; release notes `v1.2.12` |
| M11 enmascara regresiones Auth/ping en DX local | Media | Fix harness + gate TDD antes de tag `1.2.12` |
| Portal SHA desconocido (M6) | Media | Marcar P1/P2 no verificados |
| PRs `#116`/`#117` confunden prioridad | Baja | Cerrar en AUTOMATION-03 |
| Gates TDD M11/M5 ausentes pre-implementación | Media | Tests documentados en spec #121 aún no existen en tip |
| Consumidor lock &lt; `1.2.11` mantiene TOCTOU CRUD | Alta | P-LOCK; no asumir prod al día solo por tag Framework |
| Spec del día ausente retrasa cadence diseño | Baja | Este inventario degradado; AUTOMATION-01 debe emitir `2026-08-13-audit-*-design.md` en corrida futura o reutilizar spec 2026-08-12 (#121) |

---

## Criterios de aceptación

- [x] **AC-D1:** Inventario lista abiertos verificados (M11, M5, P-LOCK, M6, M10, D6, H1–H4) con evidencia ruta/línea en `main` @ `cea890e`.
- [x] **AC-D2:** CRUD-C4, M3, M4, D7, M1, REL-C1 reconciliados como **resueltos**; no re-listados como abiertos.
- [x] **AC-D3:** P1, P2, P3, Portal SHA marcados **no verificados** (M6).
- [x] **AC-D4:** Verificado sin deuda nueva en bootstrap/schema, capas `src/`, legacy operativo, Payments OFF.
- [x] **AC-D5:** Modo **degradado** declarado; spec del día ausente documentado.
- [x] **AC-D6:** Reconciliación heredada — **0** cierres en tip código desde pase 2026-08-12; H2 parcial en spec branch anotado.

---

## No-alcance

- Implementación producto (`src/`, `tests/` harness fix, migraciones M5, tags semver).
- Merge `feature/backoffice-api-integration` → `main`.
- Deploy producción, SSH VPS, `.env` con secretos.
- Auto-fix M10 (huecos audits) ni M6 (credenciales gh).
- Escritura bajo `docs/audits/` (AUTOMATION-00).
- Habilitar verticals `marketing`/`payments`/`invoicing` en prod.
- Cierre de PRs — AUTOMATION-03.
- Parchear `vendor/` en consumidores.

---

*Inventario degradado. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
