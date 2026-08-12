# Design: Deuda post-v1.2.11 — harness M11, RBAC M5 y cadena consumidor

**Fecha:** 2026-08-12  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel A)

**Auditoría fuente:** `docs/audits/2026-08-12-auditoria-tecnica-diaria.md` (PR #120 `docs(audit): auditoría técnica diaria 2026-08-12`, head `f24e83e07f92bcc7a6f97f0da4bc9c75a7fdb88e`)  
**Hallazgo principal:** **Cierre CRUD-C4** en tip `cf9e67e` / tag `v1.2.11` (#118) — **0 hallazgos nuevos** (críticos o medios). Deuda abierta arrastrada: **M11** (harness sesión), **M5** (`permisos.gestionar`), **M6** (Portal gh 404), **M10** (huecos audits), **D6** (`skeleton.lebytek.com`). **Riesgo dominante:** consumidores sin bump de `composer.lock` a ≥ `v1.2.11`.

**Contexto de cierre reciente (no reimplementar):** CRUD-C4 + G13 + G1 + G14 (#118 / `v1.2.11`); REL-C1 tags `v1.2.7`…`v1.2.11`; CRUD-C6 (`v1.2.8`+); INV-E1/E2; M3/M4 (`v1.2.10`). Specs/planes C4 ya shippeados — **no duplicar** (`2026-08-11-crud-p04-cas-bulk-equality-design.md`, plan `2026-08-07-crud-p04-cas-bulk-equality.md`). PRs residuales `#116`/`#117` obsoletos respecto a C4 — cerrar en AUTOMATION-03.

**Specs/planes relacionados (no duplicar):**

- CAS C4 (**resuelto** `#118` / `v1.2.11`): `docs/superpowers/specs/2026-08-11-crud-p04-cas-bulk-equality-design.md`
- Release semver REL-C1 (**resuelto**): `docs/superpowers/specs/2026-08-08-audit-release-semver-tag-design.md`
- RBAC router M3 (**resuelto** `#114` / `v1.2.10`): `docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md` · plan checkboxes **sin marcar**
- API health M4 (**resuelto** `#114` / `v1.2.10`): `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md` · plan checkboxes **sin marcar**
- Alineación permisos histórica: `docs/archive/audits/correccion_alineacion_modulos_v0.1.md` (workaround `administracion.ver` documentado)
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec (design) |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `cf9e67ef52237ac98136bb3335031ee058da893f` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde 2026-08-02) |
| Rama generada | `automation/spec-2026-08-12` |
| Timestamp UTC | trigger cron `2026-08-12T12:10:00Z` / corrida agente `2026-08-12T12:10:00Z` |
| Nivel de fuente | **A** — PR #120 abierto, título `docs(audit): auditoría técnica diaria 2026-08-12`, `baseRefName=main`, `mergeable=MERGEABLE`. Diff único: `docs/audits/2026-08-12-auditoria-tecnica-diaria.md`. `git merge-base --is-ancestor origin/main f24e83e` → exit 0; ningún commit de `origin/main..refs/tags/archive/backoffice-api-integration` es ancestro del head audit. |
| PR auditoría fuente | #120 — https://github.com/Parzival2103/Lebytek_Framework/pull/120 |
| headRefOid fuente | `f24e83e07f92bcc7a6f97f0da4bc9c75a7fdb88e` (rama audit; **no heredada**) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | **4** — #120 audit, #119 docs evaluación, #117 spec C4 duplicado, #116 spec C4+M11 residual |
| CI tip `main` | **success** @ `cf9e67e` (run `31550181578`) |
| Semver tip | `1.2.11` (trío sincronizado); tag `v1.2.11` publicado; tree tip ≡ tag |

---

## Problema

La auditoría del 2026-08-12 confirma el **cierre del último crítico de código** (CRUD-C4 → `v1.2.11`) y **ausencia de hallazgos nuevos**. Permanece deuda **media/proceso/entorno** que bloquea confianza en release y DX local:

### M11 — Contaminación de sesión en suite monolítica (arrastrado, abierto)

| Comprobación | Resultado en tip `cf9e67e` |
|--------------|---------------------------|
| `tests/run.php` | Carga todos los `*Test.php` en **un proceso**, orden alfabético; **sin** reset de `$_SESSION` entre archivos |
| `LoginUseCaseTest` L91–102 | Login exitoso vía `AuthService` deja `auth_user` en sesión PHP |
| `tests/Kernel/ApiHealthPublicDispatchTest.php` L30–36, L55–67 | Esperan 302 / no-200 en `/api/ping` sin sesión — **fallan** tras suite Auth |
| CI | Jobs aislados (`php tests/run.php Kernel`) → **success**; fallo es DX local, no regresión M4 en producción |

**Impacto:** `php tests/run.php` reporta **802/9** (2 fails M11 + 7 MySQL env); desarrolladores ven falso negativo en Auth/ping aunque M4 esté correcto.

### M5 — Slug `permisos.gestionar` ausente en seeds (arrastrado, abierto)

| Comprobación | Resultado en tip `cf9e67e` |
|--------------|---------------------------|
| `routes/web.php` / `skeleton/routes/web.php` L62–66 | Comentario explícito: workaround `administracion.ver` para rutas `/administracion/permisos/*` |
| `database/seeds_legacy/010_auth_permisos.sql` | Slugs base sin `permisos.gestionar` |
| `rg permisos.gestionar database/` | **0** coincidencias en schema/seeds activos |

**Impacto:** Cualquier rol con `administracion.ver` (ajustes) puede gestionar catálogo RBAC — permiso más amplio de lo deseado. Documentado como deuda desde 2026-05-02 (`correccion_alineacion_modulos_v0.1.md` §4).

### P-LOCK — Consumidores sin bump a ≥ `v1.2.11` (riesgo dominante post-C4)

| Comprobación | Resultado |
|--------------|-----------|
| Framework tip | `1.2.11` tagueado; CAS + AuthZ + states + C6 + M3/M4 disponibles vía Composer |
| Portal `composer.lock` | **No verificado** (M6) — última evidencia documentada `v1.1.0` @ `a79d3ad` |
| Consecuencia | Portal/CRM desplegados sin bump siguen **sin** CAS/C4 aunque el paquete ya lo tenga |

### Hygiene documental (baja, arrastrada)

| Ítem | Evidencia |
|------|-----------|
| Planes M3/M4 | `2026-08-06-audit-crud-rbac-router.md` y `2026-08-05-audit-api-health-public.md` — checkboxes mayoritariamente `[ ]` pese a ship `#114` / `v1.2.10` |
| `docs/release/v1.2.8.md` | **Ausente** (existen `v1.2.7`, `v1.2.9`, `v1.2.10`, `v1.2.11`) |
| PRs `#116`/`#117` | Specs C4+M11 / C4 duplicado — C4 ya mergeado `#118`; obsoletos para implementación |

### Fuera de alcance de implementación producto (registrar, no auto-fix)

| ID | Tema | Owner |
|----|------|-------|
| M6 | Token automation sin lectura Portal | Ops / credenciales |
| M10 | Huecos audits 2026-08-03…05 + 2026-08-10 | Ops / automation |
| D6 | `skeleton.lebytek.com` pendiente | Ops |

**Clasificación:** M11 = medio harness/DX. M5 = medio RBAC/plataforma. P-LOCK = alto consumo/release. Hygiene = baja documentación.

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

| Dimensión | Detalle |
|-----------|---------|
| Contexto | Post-cierre C4 (`v1.2.11`); tip sin críticos de código abiertos; CI verde; deuda dominante pasa de «implementar CAS» a «consumir tag + higiene DX/RBAC» |
| Propósito | (1) Harness monolítico honesto; (2) slug RBAC dedicado para catálogo permisos; (3) cadena consumidor documentada hacia `v1.2.11`; (4) alinear docs/planes con estado real |
| Restriciones | Sin negocio Portal en `src/`; no editar `vendor/`; no deploy/SSH/producción en corrida desatendida; legacy tag solo evidencia histórica; no reabrir C4 salvo regresión |
| Criterios de éxito | `php tests/run.php` sin fails M11 (modulo MySQL); slug `permisos.gestionar` en seeds + rutas; checklist consumidor verificable; planes M3/M4 reflejan ship; tests TDD descubren ≥1 test y fallan por motivo documentado pre-fix |

### Enfoques evaluados — M11 harness

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **`microtest.php`:** helper `microtest_reset_session()` tras cada test — vacía `$_SESSION`, reinicia flag interno `Session` si hace falta (`Session::destroy()` o reset controlado sin destruir handler en tests que no iniciaron sesión) | Protege todos los tests futuros; diff mínimo | **Recomendado** |
| **B** | TearDown solo en `ApiHealthPublicDispatchTest` y `LoginUseCaseTest` | Parche local | **Rechazado** — próximo test Auth recontamina; no escala |
| **C** | Subproceso por archivo en `run.php` | Aislamiento total | **Rechazado** — lento; rompe fixtures compartidos en memoria |

### Enfoques evaluados — M5 RBAC permisos

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **Slug dedicado:** INSERT `permisos.gestionar` en seed base + migración idempotente + asignación a rol admin + `$rbacPermisos = [new RbacMiddleware('permisos.gestionar')]` en harness/skeleton + menú `core_menu_items` si aplica | Separación clara «ver administración» vs «editar catálogo»; alinea con auditorías mayo 2026 | **Recomendado** |
| **B** | Documentar workaround `administracion.ver` como permanente | Cero diff | **Rechazado** — auditoría 2026-08-12 pide seed o documentación explícita de permanencia; producto ya pidió slug en `correccion_auth_rbac_v0.1.md` |
| **C** | Slug `permisos.gestionar` solo en migración, sin seed base | Instalaciones nuevas incompletas | **Rechazado** — skeleton/install deben ser self-contained |

### Enfoques evaluados — cadena consumidor P-LOCK

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **Checklist ops + smoke CRUD transition** en Portal post-`composer update` a `v1.2.11`; Framework publica nota en `docs/release/v1.2.11.md` § consumidores | No requiere acceso gh en automation; operador ejecuta | **Recomendado** |
| **B** | Automation con token Portal para abrir PR bump lock | Automático | **Bloqueado** — M6 (404); marcar **no verificado** |
| **C** | Consumidores siguen en lock antiguo indefinidamente | Sin coste ops | **Rechazado** — dejan CAS/C6/M3/M4 sin aplicar en prod |

### Decisión integrada

Lote **Framework-first** en un plan `docs/superpowers/plans/2026-08-12-audit-post-v1211-debt.md` (AUTOMATION-04):

1. **M11** en harness (sin contrato HTTP nuevo; opcional semver **`1.2.12`** si se empaqueta junto M5).
2. **M5** con migración + seeds + rutas espejo skeleton.
3. **Hygiene** docs M3/M4 + `docs/release/v1.2.8.md` stub mínimo (retroactivo).
4. **P-LOCK** como sección ops Portal — fuera de implementación Framework; operador manual.

---

## Comportamiento esperado

### Framework — M11 harness

- Tras **cada** invocación de `test()` en `microtest.php`, el estado de sesión PHP queda limpio (`auth_user` ausente).
- `php tests/run.php` (sin MySQL) produce **0 fails** en `ApiHealthPublicDispatchTest` por sesión residual.
- Suites aisladas (`php tests/run.php Kernel`, `php tests/run.php Auth`) mantienen comportamiento actual.
- Tests que **requieren** sesión autenticada deben llamar setup explícito al inicio del test (no depender de contaminación cruzada).

### Framework — M5 RBAC

- Seed/migración inserta permiso `{ slug: 'permisos.gestionar', nombre: 'Gestionar permisos', modulo: 'administracion' }` (nombre exacto alineado con convenciones existentes).
- Rol administrador recibe el slug en pivot `auth_roles_permisos` (migración idempotente `INSERT IGNORE`).
- Rutas `/admin/administracion/permisos/*` usan `RbacMiddleware('permisos.gestionar')` en `routes/web.php` y `skeleton/routes/web.php`.
- Comentario workaround eliminado o sustituido por referencia al slug canónico.
- Usuario con solo `administracion.ver` **no** accede a catálogo permisos; usuario con `permisos.gestionar` sí.

### Portal — P-LOCK consumidor (**no verificado**)

- Tras tag Framework ≥ `v1.2.11`, operador en `Lebytek_Portal` @ `main`: `composer require lebytek/framework:^1.2.11` (o bump lock equivalente), merge, deploy **staging** primero.
- Smoke: transición CRUD concurrente en recurso `dom_*` devuelve conflicto accionable bajo carrera (validación CAS).
- Producción: mismo bump **solo** tras smoke staging — **fuera de corrida desatendida**.

### Docs hygiene

- Planes M3/M4: marcar tareas completadas según `#114` / `v1.2.10`; añadir nota «shipped» con PR y tag.
- Crear `docs/release/v1.2.8.md` mínimo (fecha, PR `#111`, tema uploads C6) para paridad cadena release.

---

## Alcance

| ID | Requisito | Owner | Repo / rama base |
|----|-----------|-------|------------------|
| F1 | Reset sesión post-test en `tests/lib/microtest.php` | Framework | `Lebytek_Framework` / `main` |
| F2 | Test gate M11: assert monolítico vs aislado (Docs o Kernel) | Framework | `Lebytek_Framework` / `main` |
| F3 | Seed + migración `permisos.gestionar` | Framework | `Lebytek_Framework` / `main` |
| F4 | Rutas permisos → slug dedicado (harness + skeleton) | Framework | `Lebytek_Framework` / `main` |
| F5 | Test gate M5: slug presente en seeds/SQL activo | Framework | `Lebytek_Framework` / `main` |
| F6 | Actualizar `docs/core/auth_rbac_seguridad_v0.1.md` § permisos catálogo | Framework | `Lebytek_Framework` / `main` |
| F7 | Marcar checkboxes planes M3/M4; stub `docs/release/v1.2.8.md` | Framework | `Lebytek_Framework` / `main` |
| F8 | Semver patch **`1.2.12`** + tag `v1.2.12` si F3–F4 shippean (M11 solo harness puede ir en mismo patch) | Framework | `Lebytek_Framework` / `main` |
| P1 | Bump `composer.lock` a ≥ `v1.2.11` | Portal | `Lebytek_Portal` / `main` — **no verificado** |
| P2 | Smoke transición CAS en staging Portal | Portal/Ops | **Fuera de automation** |
| O1 | Conceder lectura gh a token automation (M6) | Ops | N/A |
| O2 | Deploy `skeleton.lebytek.com` (D6) | Ops | N/A |

---

## No-alcance

- Reimplementar o re-especificar CRUD-C4 / CAS (cerrado `#118` / `v1.2.11`).
- Código Marketing, leads, membresías, `LebytekApiClient` — **Portal** (`Lebytek_Portal`).
- Habilitar verticals `marketing`/`payments`/`invoicing` o `FACTURAPI_ENABLED` / `STRIPE_ENABLED` en prod.
- Merge `feature/backoffice-api-integration` → `main`.
- Deploy producción Portal/CRM, SSH VPS, `.env` con secretos.
- Cierre de PRs `#116`/`#117`/`#120` — AUTOMATION-03.
- Auto-fix M10 (huecos audits) ni M6 (credenciales) en esta corrida.
- Renombrar slugs existentes distintos de añadir `permisos.gestionar`.

---

## Ownership map

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `cf9e67e` | M11 harness, M5 RBAC seed/rutas, docs hygiene, tag `v1.2.12` |
| Release semver | Tags `v1.2.7`…`v1.2.11` (actual); siguiente **`v1.2.12`** post M5+M11 | Mantener tip↔tag |
| App desplegable | `Lebytek_Portal` / `main` | Bump lock ≥ `v1.2.11`, smoke CAS, SQL `dom_*` — **no verificado** |
| CRM / tenants | Consumidores desde skeleton | Mismo bump lock vía Composer |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Contratos públicos ausentes (no asumir desde legacy):**

- No existe API HTTP pública para «reset harness» — solo cambio en `tests/lib/`.
- No existe endpoint Portal documentado para verificar lock semver — operador usa `composer.lock` + `vendor/lebytek/framework/composer.json`.
- Slug `permisos.gestionar` **no existe** hoy en BD base — no asumir en tests de integración Portal hasta migración Framework consumida.

**Frontera semver/release:**

| Capacidad | Primera versión tag | Consumidor debe |
|-----------|---------------------|---------------|
| CAS transitions C4 | `v1.2.11` | lock ≥ `1.2.11` |
| Uploads C6 | `v1.2.8`+ | lock ≥ `1.2.8` |
| M3 CRUD RBAC router | `v1.2.10` | lock ≥ `1.2.10` |
| M4 `/api/health` | `v1.2.10` | lock ≥ `1.2.10` |
| M5 `permisos.gestionar` | **`v1.2.12`** (propuesto) | lock ≥ `1.2.12` + migración SQL |

---

## Dependencias y compatibilidad

### Framework (base nueva — install desde skeleton)

- Install/migrate aplica seed con `permisos.gestionar`; rutas skeleton ya referencian slug dedicado.
- Harness tests verdes en monolito sin MySQL.

### Framework (base existente — tenant ya desplegado)

- Migración idempotente añade permiso y pivot admin; no rompe slugs existentes.
- Rutas actualizadas requieren asignar `permisos.gestionar` a roles que hoy gestionan permisos vía `administracion.ver` — script ops o re-asignación manual en UI roles.

### Portal existente (**no verificado**)

- Depende de bump lock secuencial: idealmente directo a **`1.2.12`** tras publicar tag (absorbe C4 + M5).
- Sin bump: comportamiento pre-CAS persiste — **riesgo TOCTOU** en transiciones CRUD Portal.
- Wiring Facturapi/Marketing independiente de este spec.

### Compatibilidad semver

- M11: cambio solo tests — compatible MINOR/PATCH; puede bundlearse en `1.2.12`.
- M5: PATCH aditivo (nuevo slug + middleware más estricto en una ruta) — roles sin nuevo permiso pierden acceso a catálogo hasta re-asignación (comportamiento deseado).

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Portal sin bump ≥ `v1.2.11` | Alta | P1 checklist ops; nota release; M6 resolver acceso gh |
| M5 rompe acceso permisos para roles legacy | Media | Migración asigna slug a rol admin; documentar re-asignación |
| M11 enmascara tests que dependían de sesión cruzada | Baja | Revisar tests Auth; setup explícito |
| Portal SHA desconocido (M6) | Media | Marcar P1/P2 **no verificados** |
| Plan duplicado C4 (#116/#117) confunde implementadores | Baja | Cerrar en AUTOMATION-03 |
| `composer validate` lock warning (exit 2) | Baja | Fuera de alcance; CI usa `--no-check-lock` |

---

## Rollback

| Cambio | Rollback |
|--------|----------|
| M11 harness | Revert commit `microtest.php` — sin impacto producción |
| M5 slug + rutas | Revert + migración down opcional DELETE slug; restaurar `administracion.ver` en rutas permisos |
| Tag `v1.2.12` | Consumidores pinnean `1.2.11`; no yank salvo emergencia |
| Portal bump | `composer.lock` revert + redeploy versión anterior |
| Producción | **Solo operador manual** — fuera de automation |

---

## Criterios de aceptación

### M11 harness

- [ ] **AC-M11-1:** Test TDD `tests/Docs/MonolithicHarnessSessionIsolationTest.php` (o equivalente) **falla** pre-fix al detectar que `microtest.php` no resetea sesión (assert sobre helper ausente o comportamiento simulado).
- [ ] **AC-M11-2:** Tras fix, `php tests/run.php Kernel/ApiHealthPublicDispatch` → **PASS** en monolito y aislado.
- [ ] **AC-M11-3:** `php tests/run.php` (sin MySQL) — **0 fails** en tests ping/health por sesión residual (802+ pass, 7 fails MySQL env aceptables si servidor ausente).
- [ ] **AC-M11-4:** `php tests/run.php Auth` sigue **PASS** (52/0 verificado en audit).

### M5 RBAC

- [ ] **AC-M5-1:** Test gate **falla** pre-fix — p. ej. `tests/Docs/PermisosGestionarSlugTest.php` assert `permisos.gestionar` en SQL seed/migración activa.
- [ ] **AC-M5-2:** `rg permisos.gestionar database/` ≥ 1 en seeds o migraciones no-legacy.
- [ ] **AC-M5-3:** `routes/web.php` y `skeleton/routes/web.php` usan `permisos.gestionar` en rutas permisos (no solo comentario workaround).
- [ ] **AC-M5-4:** Usuario harness con solo `administracion.ver` recibe 403 en GET permisos; con `permisos.gestionar` → 200.

### Release y docs

- [ ] **AC-R1:** Trío semver `1.2.12` + tag `v1.2.12` publicado tras merge M5 (M11 puede incluirse).
- [ ] **AC-D1:** Planes M3/M4 tienen checkboxes alineados con ship `#114`.
- [ ] **AC-D2:** `docs/release/v1.2.8.md` existe con referencia PR `#111` / C6.

### Portal (**no verificados** — requieren M6 resuelto)

- [ ] **AC-P1:** Portal `composer.lock` referencia `lebytek/framework` ≥ `1.2.11`.
- [ ] **AC-P2:** Smoke staging — transición concurrente devuelve conflicto CAS accionable.

### Carry-forward (registrar, no bloquean cierre spec)

- [ ] **CF-M6:** Token automation lee Portal main SHA.
- [ ] **CF-M10:** Audits 03–05 + 10 backfilled o aceptados.
- [ ] **CF-D6:** `skeleton.lebytek.com` live según `docs/ENVIRONMENTS.md`.

---

## Migración segura

### Base nueva (skeleton / install limpio)

1. `composer create-project` o skeleton deploy.
2. `scripts/install` aplica schema + seeds incluyendo `permisos.gestionar`.
3. Admin recibe permiso vía seed rol.
4. Verificar rutas permisos con slug dedicado.

### Base Framework existente (harness / tenant)

1. Merge Framework `main` con tag `v1.2.12`.
2. Ejecutar migraciones SQL idempotentes (permiso + pivot admin).
3. Re-asignar `permisos.gestionar` a roles no-admin que gestionaban catálogo.
4. Correr `php tests/run.php` monolítico — confirmar M11 verde.

### Base Portal existente (**no verificado**)

1. Bump lock a `^1.2.11` mínimo (ideal `1.2.12` tras M5).
2. Staging: smoke CRUD + permisos admin.
3. Producción: solo tras sign-off operador — **prohibido en corrida desatendida**.

---

## Tests TDD (pre-implementación)

| Test | Suite | Fallo esperado pre-fix | Motivo |
|------|-------|------------------------|--------|
| `MonolithicHarnessSessionIsolationTest` | Docs | Helper reset ausente o monolito deja `auth_user` | M11 |
| `ApiHealthPublicDispatchTest` (existente) | Kernel | 302→200 en monolito | M11 contaminación |
| `PermisosGestionarSlugTest` | Docs | slug no encontrado en SQL activo | M5 |
| `PlatformVersionSemverTest` (existente) | Docs | N/A hasta bump 1.2.12 | Release |

Ningún test debe ser trivial ni assert constante true.

---

## Operaciones por entorno

| Operación | Implementación | Staging | Producción |
|-----------|----------------|---------|------------|
| Fix M11 microtest | Automation / dev | N/A | N/A |
| Seed/migración M5 | Automation / dev | Aplicar en DB staging tenant | Operador tras QA |
| Tag `v1.2.12` | Maintainer Framework | — | — |
| Bump Portal lock | — | Operador manual | Operador tras staging |
| Deploy skeleton.lebytek.com | — | Ops | Ops |
| Conceder gh Portal token | — | Ops | Ops |

**Producción explícitamente excluida** de corrida cron desatendida.

---

*Design-only. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
