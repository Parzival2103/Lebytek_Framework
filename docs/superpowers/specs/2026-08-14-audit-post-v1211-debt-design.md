# Design: Deuda post-v1.2.11 — harness M11, RBAC M5 y cadena consumidor P-LOCK

**Fecha:** 2026-08-14  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel A)

**Auditoría fuente:** `docs/audits/2026-08-14-auditoria-tecnica-diaria.md` (PR #124 `docs(audit): auditoría técnica diaria 2026-08-14`, head `9af4cae465faff1c28c0f50eda2bbd6fdda61ee0`)  
**Hallazgo principal:** **Día quieto — 0 hallazgos nuevos** (críticos o medios). Tip `cea890e` ≡ tag `v1.2.11` en código de plataforma; único delta desde 2026-08-13 = merge audit `#122` (docs-only). Deuda abierta arrastrada: **M11** (harness sesión), **M5** (`permisos.gestionar`), **M6** (Portal gh 404), **M10** (huecos audits), **D6** (`skeleton.lebytek.com`). **Riesgo dominante:** consumidores sin bump de `composer.lock` a ≥ `v1.2.11` (P-LOCK).

**Contexto de cierre reciente (no reimplementar):** CRUD-C4 + G13 + G1 + G14 (#118 / `v1.2.11`); REL-C1 tags `v1.2.7`…`v1.2.11`; CRUD-C6 (`v1.2.8`+); INV-E1/E2; M3/M4 (`v1.2.10`). Specs/planes C4 ya shippeados — **no duplicar** (`2026-08-11-crud-p04-cas-bulk-equality-design.md`, plan `2026-08-07-crud-p04-cas-bulk-equality.md`).

**Cadena specs/planes previos (no mergeados a `main` al escribir):**

| Artefacto | Rama / PR | Estado |
|-----------|-----------|--------|
| Spec `2026-08-12-audit-post-v1211-debt-design.md` | `automation/spec-2026-08-12` / PR #121 | OPEN — contenido base de este diseño |
| Spec `2026-08-13-deuda-tecnica.md` + plan `2026-08-13-audit-post-v1211-debt.md` | `automation/spec-2026-08-13` / PR #123 | OPEN — plan 6 tareas M11+M5; **0/N ejecutadas en `main`** |
| PRs residuales `#116`/`#117` | specs C4 duplicados | OPEN — obsoletos tras `#118` |

Este spec **consolida y actualiza** la línea `#121`/`#123` con provenance 2026-08-14; AUTOMATION-04 debe preferir **un** plan activo (`2026-08-14-audit-post-v1211-debt` o reconciliar merge de `#123`).

**Specs/planes relacionados (no duplicar implementación):**

- CAS C4 (**resuelto** `#118` / `v1.2.11`): `docs/superpowers/specs/2026-08-11-crud-p04-cas-bulk-equality-design.md`
- Release semver REL-C1 (**resuelto**): `docs/superpowers/specs/2026-08-08-audit-release-semver-tag-design.md`
- RBAC router M3 (**resuelto** `#114` / `v1.2.10`): `docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md` · plan checkboxes **sin marcar** en `main`
- API health M4 (**resuelto** `#114` / `v1.2.10`): `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md` · plan checkboxes **sin marcar** en `main`
- Alineación permisos histórica: `docs/archive/audits/correccion_alineacion_modulos_v0.1.md` (workaround `administracion.ver` documentado)
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec (design) |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `990776e63a68919e4ae0576abb80bf9cf07725eb` (merge audit #124 @ `cea890e` + delta docs-only) |
| SHA Portal inspeccionado | **No verificado** — `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404; `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository». Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde 2026-08-02) |
| Rama generada | `automation/spec-2026-08-14` |
| Timestamp UTC | trigger cron `2026-08-14T12:13:10Z` / corrida agente `2026-08-14T12:13:10Z` / pase ux `2026-08-14T12:38:46Z` (modo **normal**) |
| Nivel de fuente | **A** — PR #124 abierto, título `docs(audit): auditoría técnica diaria 2026-08-14`, `baseRefName=main`, `mergeable=MERGEABLE`. Diff único: `docs/audits/2026-08-14-auditoria-tecnica-diaria.md`. `git merge-base --is-ancestor origin/main 9af4cae` → exit 0; ningún commit de `origin/main..refs/tags/archive/backoffice-api-integration` es ancestro del head audit. |
| PR auditoría fuente | #124 — https://github.com/Parzival2103/Lebytek_Framework/pull/124 |
| headRefOid fuente | `9af4cae465faff1c28c0f50eda2bbd6fdda61ee0` (rama audit; **no heredada**) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** |
| Issues abiertos Portal | **No verificable** — bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | **6** — #124 audit, #123 spec+plan, #121 spec, #119 docs evaluación, #117 spec C4 duplicado, #116 spec C4+M11 residual |
| CI tip `main` | **success** @ `cea890e` (run `31701756139`, PR `#122`) |
| Semver tip | `1.2.11` (trío sincronizado); tag `v1.2.11` publicado; tree código tip ≡ tag (delta tip = audit docs) |
| Plan activo verificado | `docs/superpowers/plans/2026-08-13-audit-post-v1211-debt.md` existe en rama `automation/spec-2026-08-13` (PR #123) — **ausente en `main`**; ejecución **0/N** |
| Pase deuda (AUTOMATION-02) | `2026-08-14T13:10:51Z` — modo **normal** — `origin/main` @ `990776e63a68919e4ae0576abb80bf9cf07725eb` |

---

## Problema

La auditoría del 2026-08-14 confirma **ausencia de hallazgos nuevos** tras merge del reporte 2026-08-13 (PR `#122`). El tip permanece en **`1.2.11`** con **0 críticos de código abiertos**. La deuda **media/proceso/entorno** arrastrada sigue bloqueando confianza en release, DX local y consumo del tag por Portal/CRM:

### M11 — Contaminación de sesión en suite monolítica (arrastrado, abierto)

| Comprobación | Resultado en tip `cea890e` |
|--------------|---------------------------|
| `tests/run.php` L29–31 | Carga todos los `*Test.php` en **un proceso**, orden alfabético; **sin** reset de `$_SESSION` entre archivos |
| `tests/lib/microtest.php` L7–17 | `test()` incrementa pass/fail pero **no** invoca reset de sesión |
| `LoginUseCaseTest.php` L91–98 | Login exitoso vía `LoginUseCase`/`AuthService` deja estado auth en sesión PHP |
| `ApiHealthPublicDispatchTest.php` L30–36, L55–67 | Esperan 302 / no-200 en `/api/ping` sin sesión — **fallan** tras suite Auth en monolito |
| `MonolithicHarnessSessionIsolationTest` | **Ausente** en tip |
| CI | Jobs aislados (`php tests/run.php Kernel`) → **success**; fallo es DX local, no regresión M4 en producción |

**Impacto:** `php tests/run.php` reporta **802/9** (2 fails M11 + 7 MySQL env en corrida audit); desarrolladores ven falso negativo en Auth/ping aunque M4 esté correcto.

### M5 — Slug `permisos.gestionar` ausente en seeds (arrastrado, abierto)

| Comprobación | Resultado en tip `cea890e` |
|--------------|---------------------------|
| `routes/web.php` / `skeleton/routes/web.php` L62–66 | Comentario explícito: workaround `administracion.ver` para rutas `/administracion/permisos/*` |
| `database/seeds_legacy/010_auth_permisos.sql` | Slugs base sin `permisos.gestionar` |
| `database/schema/schema.sql` | Idem — solo `administracion.ver` en módulo administración |
| `rg permisos.gestionar database/` | **0** coincidencias |
| `PermisosGestionarSlugTest` | **Ausente** en tip |

**Impacto:** Cualquier rol con `administracion.ver` (ajustes) puede gestionar catálogo RBAC — permiso más amplio de lo deseado. Documentado como deuda desde 2026-05-02.

### P-LOCK — Consumidores sin bump a ≥ `v1.2.11` (riesgo dominante post-C4)

| Comprobación | Resultado |
|--------------|-----------|
| Framework tip | `1.2.11` tagueado; CAS + AuthZ + states + C6 + M3/M4 disponibles vía Composer |
| Portal `composer.lock` | **No verificado** (M6) — última evidencia documentada `v1.1.0` @ `a79d3ad` |
| Consecuencia | Portal/CRM desplegados sin bump siguen **sin** CAS/C4 aunque el paquete ya lo tenga |

### Hygiene documental (baja, arrastrada)

| Ítem | Evidencia |
|------|-----------|
| Planes M3/M4 en `main` | `2026-08-06-audit-crud-rbac-router.md` y `2026-08-05-audit-api-health-public.md` — checkboxes mayoritariamente `[ ]` pese a ship `#114` / `v1.2.10` (archivado en PR #123, **no** en `main`) |
| `docs/release/v1.2.8.md` | **Ausente** (existen `v1.2.7`, `v1.2.9`, `v1.2.10`, `v1.2.11`) |
| PRs `#116`/`#117` | Specs C4+M11 / C4 duplicado — C4 ya mergeado `#118`; obsoletos para implementación |
| Specs `#121`/`#123` | Duplican diseño M11/M5 — pendientes merge o consolidación |

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
| Contexto | Post-cierre C4 (`v1.2.11`); segundo día consecutivo sin hallazgos nuevos; tip sin críticos de código; CI verde; cadena spec/plan `#121`/`#123` abierta sin merge a `main` |
| Propósito | (1) Harness monolítico honesto; (2) slug RBAC dedicado para catálogo permisos; (3) cadena consumidor documentada hacia `v1.2.11`; (4) alinear docs/planes con estado real |
| Restriciones | Sin negocio Portal en `src/`; no editar `vendor/`; no deploy/SSH/producción en corrida desatendida; legacy tag solo evidencia histórica; no reabrir C4 salvo regresión |
| Criterios de éxito | `php tests/run.php` sin fails M11 (modulo MySQL); slug `permisos.gestionar` en seeds + rutas; checklist consumidor verificable; planes M3/M4 reflejan ship; tests TDD descubren ≥1 test y fallan por motivo documentado pre-fix |

### Enfoques evaluados — M11 harness

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **`microtest_reset_session()`** tras cada `test()` — vacía `$_SESSION`, invoca `Session::destroy()` si sesión activa, resetea `Session::$started` vía reflexión si hace falta | Protege todos los tests futuros; diff mínimo; alinea con plan PR #123 Task 1 | **Recomendado** |
| **B** | TearDown solo en `ApiHealthPublicDispatchTest` y tests Auth | Parche local | **Rechazado** — próximo test Auth recontamina; no escala |
| **C** | Subproceso por archivo en `run.php` | Aislamiento total | **Rechazado** — lento; rompe fixtures compartidos en memoria |

### Enfoques evaluados — M5 RBAC permisos

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **Slug dedicado:** INSERT `permisos.gestionar` en seed base + migración idempotente registrada en `config/modules/core.php` + asignación rol admin + `RbacMiddleware('permisos.gestionar')` en harness/skeleton | Separación clara «ver administración» vs «editar catálogo»; semver **`v1.2.12`** | **Recomendado** |
| **B** | Documentar workaround `administracion.ver` como permanente | Cero diff | **Rechazado** — auditorías 2026-08-12…14 piden seed o permanencia explícita documentada |
| **C** | Slug solo en migración, sin seed base | Instalaciones nuevas incompletas | **Rechazado** — skeleton/install deben ser self-contained |

### Enfoques evaluados — cadena consumidor P-LOCK

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **Checklist ops + smoke CRUD transition** en Portal post-`composer update` a `v1.2.11`; nota en `docs/release/v1.2.11.md` § consumidores (ya presente) + `docs/release/v1.2.12.md` post-M5 | No requiere gh Portal en automation | **Recomendado** |
| **B** | Automation con token Portal para abrir PR bump lock | Automático | **Bloqueado** — M6 (404); marcar **no verificado** |
| **C** | Consumidores siguen en lock antiguo indefinidamente | Sin coste ops | **Rechazado** — dejan CAS/C6/M3/M4 sin aplicar en prod |

### Decisión integrada

Lote **Framework-first** en plan `docs/superpowers/plans/2026-08-14-audit-post-v1211-debt.md` (AUTOMATION-04; puede reconciliar con plan existente en PR #123):

1. **M11** en harness (`tests/lib/microtest.php`).
2. **M5** con migración + seeds + rutas espejo skeleton.
3. **Hygiene** docs M3/M4 archivado/checkboxes + `docs/release/v1.2.8.md` stub mínimo.
4. **Semver `1.2.12`** + tag (maintainer humano post-merge).
5. **P-LOCK** como sección ops Portal — fuera de implementación Framework.

---

## Comportamiento esperado

### Framework — M11 harness

- Tras **cada** invocación de `test()` en `microtest.php`, el estado de sesión PHP queda limpio (`auth_user` ausente).
- `php tests/run.php` (sin MySQL) produce **0 fails** en `ApiHealthPublicDispatchTest` por sesión residual.
- Suites aisladas (`php tests/run.php Kernel`, `php tests/run.php Auth`) mantienen comportamiento actual.
- Tests que **requieren** sesión autenticada deben llamar setup explícito al inicio del test.

### Framework — M5 RBAC

- Seed/migración inserta permiso `{ slug: 'permisos.gestionar', nombre: 'Gestionar permisos', modulo: 'administracion' }`.
- Rol administrador recibe el slug en pivot `auth_roles_permisos` (migración idempotente `INSERT IGNORE`).
- Rutas `/admin/administracion/permisos/*` usan `RbacMiddleware('permisos.gestionar')` en `routes/web.php` y `skeleton/routes/web.php`.
- Comentario workaround eliminado o sustituido por referencia al slug canónico.
- Usuario con solo `administracion.ver` **no** accede a catálogo permisos; usuario con `permisos.gestionar` sí.

### Portal — P-LOCK consumidor (**no verificado**)

- Tras tag Framework ≥ `v1.2.11`, operador en `Lebytek_Portal` @ `main`: `composer require lebytek/framework:^1.2.11` (o bump lock equivalente), merge, deploy **staging** primero.
- Smoke: transición CRUD concurrente en recurso `dom_*` devuelve conflicto accionable bajo carrera (validación CAS).
- Producción: mismo bump **solo** tras smoke staging — **fuera de corrida desatendida**.

### Docs hygiene

- Planes M3/M4: archivar o marcar tareas completadas según `#114` / `v1.2.10`.
- Crear `docs/release/v1.2.8.md` mínimo (fecha, PR `#111`, tema uploads C6).

---

## Alcance

| ID | Requisito | Owner | Repo / rama base |
|----|-----------|-------|------------------|
| F1 | Reset sesión post-test en `tests/lib/microtest.php` | Framework | `Lebytek_Framework` / `main` |
| F2 | Test gate M11: assert monolítico vs aislado | Framework | `Lebytek_Framework` / `main` |
| F3 | Seed + migración `permisos.gestionar` | Framework | `Lebytek_Framework` / `main` |
| F4 | Rutas permisos → slug dedicado (harness + skeleton) | Framework | `Lebytek_Framework` / `main` |
| F5 | Test gate M5: slug presente en seeds/SQL activo + RBAC 403/200 | Framework | `Lebytek_Framework` / `main` |
| F6 | Actualizar `docs/core/auth_rbac_seguridad_v0.1.md` § permisos catálogo | Framework | `Lebytek_Framework` / `main` |
| F7 | Archivar/marcar planes M3/M4; stub `docs/release/v1.2.8.md` | Framework | `Lebytek_Framework` / `main` |
| F8 | Semver patch **`1.2.12`** + notas release consumidor | Framework | `Lebytek_Framework` / `main` |
| P1 | Bump `composer.lock` a ≥ `v1.2.11` (ideal `1.2.12` post-M5) | Portal | `Lebytek_Portal` / `main` — **no verificado** |
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
- Cierre de PRs `#116`/`#117`/`#121`/`#123`/`#124` — AUTOMATION-03.
- Auto-fix M10 (huecos audits) ni M6 (credenciales) en esta corrida.
- Renombrar slugs existentes distintos de añadir `permisos.gestionar`.
- Parchear `composer.lock` del harness ni habilitar verticals como auto-fix.
- Crear migraciones RBAC M5 sin registrarlas en manifiesto `config/modules/core.php`.
- Refactor capas **D11** (Domain→Application en interfaces CRUD/Mailer) — deuda estructural carry-forward, no lote M11/M5.
- Añadir jobs CI Auth/Calendar (**D12**) — fuera de alcance F1–F8 salvo spec CI dedicado.

---

## Ownership map

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `cea890e` | M11 harness, M5 RBAC seed/rutas, docs hygiene, tag `v1.2.12` |
| Release semver | Tags `v1.2.7`…`v1.2.11` (actual); siguiente **`v1.2.12`** post M5+M11 | Mantener tip↔tag |
| App desplegable | `Lebytek_Portal` / `main` | Bump lock ≥ `v1.2.11`, smoke CAS, SQL `dom_*` — **no verificado** |
| CRM / tenants | Consumidores desde skeleton | Mismo bump lock vía Composer |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Contratos públicos ausentes (no asumir desde legacy):**

- No existe API HTTP pública para «reset harness» — solo cambio en `tests/lib/`.
- No existe endpoint Portal documentado para verificar lock semver — operador usa `composer.lock` + `vendor/lebytek/framework/composer.json`.
- Slug `permisos.gestionar` **no existe** hoy en BD base — no asumir en tests Portal hasta migración Framework consumida.

**Frontera semver/release:**

| Capacidad | Primera versión tag | Consumidor debe |
|-----------|---------------------|-----------------|
| CAS transitions C4 | `v1.2.11` | lock ≥ `1.2.11` |
| Uploads C6 | `v1.2.8`+ | lock ≥ `1.2.8` |
| M3 CRUD RBAC router | `v1.2.10` | lock ≥ `1.2.10` |
| M4 `/api/health` | `v1.2.10` | lock ≥ `1.2.10` |
| M5 `permisos.gestionar` | **`v1.2.12`** (propuesto) | lock ≥ `1.2.12` + migración SQL |

---

## Dependencias y compatibilidad

### Framework (base nueva — install desde skeleton)

- Install/migrate aplica seed con `permisos.gestionar`; rutas skeleton referencian slug dedicado.
- Harness tests verdes en monolito sin MySQL.

### Framework (base existente — tenant ya desplegado)

- Migración idempotente añade permiso y pivot admin; no rompe slugs existentes.
- Rutas actualizadas requieren asignar `permisos.gestionar` a roles que hoy gestionan permisos vía `administracion.ver`.

### Portal existente (**no verificado**)

- Depende de bump lock secuencial: idealmente directo a **`1.2.12`** tras publicar tag (absorbe C4 + M5).
- Sin bump: comportamiento pre-CAS persiste — **riesgo TOCTOU** en transiciones CRUD Portal.
- Wiring Facturapi/Marketing independiente de este spec.

### Compatibilidad semver

- M11: cambio solo tests — compatible PATCH; puede bundlearse en `1.2.12`.
- M5: PATCH aditivo (nuevo slug + middleware más estricto en una ruta) — roles sin nuevo permiso pierden acceso a catálogo hasta re-asignación (comportamiento deseado).

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Portal sin bump ≥ `v1.2.11` | Alta | P1 checklist ops; nota release; M6 resolver acceso gh |
| M5 rompe acceso permisos para roles legacy | Media | Migración asigna slug a rol admin; documentar re-asignación |
| M11 enmascara tests que dependían de sesión cruzada | Baja | Revisar tests Auth; setup explícito |
| Portal SHA desconocido (M6) | Media | Marcar P1/P2 **no verificados** |
| Specs duplicados `#121`/`#123`/este spec confunden cadena | Baja | AUTOMATION-03 merge/consolidación |
| Plan `#123` no en `main` — executor sin ancla | Media | AUTOMATION-04 crea/reconcilia plan `2026-08-14-*` |
| `composer validate` lock warning (exit 2) — **D10** | Baja | Fuera de alcance; CI usa `--no-check-lock` |
| Gates TDD M11/M5 ausentes pre-implementación | Media | Tests propuestos deben fallar pre-fix |
| **D11** capas Domain→Application (6 interfaces CRUD/Mailer) | Baja | Refactor futuro; no bloquea M11/M5; no auto-fix en corrida deuda |
| **D12** suites Auth/Calendar ausentes en CI | Baja | Jobs aislados en `platform-tests.yml`; M11 local no detectado en CI |
| Doc RBAC drift (`auth_rbac_seguridad_v0.1.md` L69) | Baja | F6 al implementar M5 |

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

## Compatibilidad, UX y responsive

### Modo del pase: normal

Este spec es **deuda post-v1.2.11** (M11 harness, M5 RBAC permisos, P-LOCK consumidor, hygiene docs). La superficie UI verificable concentra **M5**: rutas `/admin/administracion/permisos/*`, pantallas **403** (HTML flash + JSON AJAX) y catálogo permisos existente (`src/Presentation/Views/admin/permisos/*` con `table-responsive`). **M11** afecta DX desarrollador (`php tests/run.php` monolítico). **P-LOCK** afecta flujos ops (checklist bump lock). No modifica login, dashboard nav ni layouts globales — permanecen carry-forward.

### Compatibilidad (verificado vs carry-forward)

| Área | Este spec (F1–F8, P1) | Evidencia / carry-forward |
|------|------------------------|---------------------------|
| PHP soportado | Sin cambio runtime | `composer.json` exige `>=8.2`; VPS documentado PHP **8.4.22** CLI/pool (`2026-07-26-skeleton-package-staging-design.md`) — compatible. M11/M5 no requieren extensiones nuevas. Consumidores en **8.1** deben subir runtime antes de bump ≥ `1.2.11`. |
| Instalación vía `vendor/` | **Contrato P-LOCK** | Consumidores obtienen M5 + M11 fix tras bump `lebytek/framework` al tag ≥ `1.2.12` (propuesto) o mínimo `1.2.11` para C4 — **no** checkout de rama ni parche en `vendor/`. Migración M5 vía manifiesto `config/modules/core.php`; skeleton espeja rutas. |
| Health sin cookie de sesión | **Resuelto (M4)** — sin alcance | `routes/api.php` L14–15 — `GET /api/health` público (M4 ship `#114` / `v1.2.10`). Smoke LB: **no** usar `/api/ping` (exige sesión). M11 no debe reintroducir contaminación que falsee tests ping en monolito. |
| `.env.example` sin vars Portal | **Resuelto (M2)** — sin alcance | Root `.env.example` L53–55 remite `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` a Portal; M5/M11 no introducen env vars nuevas. |
| Navegadores objetivo | Superficie admin permisos + 403 | Baseline `docs/core/ui_ux.md`: admin breakpoint **992px (`lg`)**. Chrome, Firefox, Safari, Edge últimas 2 versiones + iOS Safari ≥ 15; sin IE11. Flash 403 y tablas permisos legibles en **320–768px**. |

### UX — flujos admin permisos (M5, F3–F5)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U1** | 403 HTML en rutas permisos incluye slug **`permisos.gestionar`** explícito — operador distingue permiso faltante de error genérico (extiende `RbacMiddleware` actual que no expone slug en flash) | F4, AC-M5-4 |
| **U2** | Copy accionable: qué falló (acceso al catálogo permisos denegado) + qué hacer (solicitar `permisos.gestionar` al administrador o revisar rol en **Usuarios/Roles**) — no sólo «No tienes permiso para acceder a esta sección.» | F4, CF10 parcial |
| **U3** | Peticiones AJAX: JSON 403 `{ "error": "Acceso denegado.", "permiso": "permisos.gestionar" }` — coherente con contrato M3 spec 2026-08-06 | F4 |
| **U4** | Usuario con solo `administracion.ver` (ajustes) **no** accede a `/admin/administracion/permisos/*` — comportamiento deseado documentado en doc RBAC § catálogo permisos (F6) para evitar confusión «regresión» | F6, AC-M5-4 |
| **U5** | `PermisosGestionarSlugTest`: mensaje de fallo cita spec M5, archivo SQL/seed y acción («INSERT slug permisos.gestionar + registrar migración en core.php») | F5 |
| **U6** | Empty state catálogo permisos (`index.php` L22–23): hint accionable «Crea permisos para usarlos en roles y menú» + CTA «Crear permiso» visible — sin regresión post-M5 | F4 |

### UX — harness desarrollador (M11, F1–F2)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U7** | `php tests/run.php` monolítico (sin MySQL): 0 fails en `ApiHealthPublicDispatchTest` por sesión residual — desarrollador no interpreta falso negativo M4 | F1, AC-M11-3 |
| **U8** | `MonolithicHarnessSessionIsolationTest`: mensaje de fallo indica helper `microtest_reset_session()` ausente y acción («invocar reset tras cada test() en microtest.php») | F2 |
| **U9** | Doc release `v1.2.12`: nota explícita «M11 = fix DX local monolito; CI aislado no afectado» — operador no confunde con regresión producción | F8 |

### UX — cadena consumidor P-LOCK (P1, ops)

| Requisito | Criterio | Estado |
|-----------|----------|--------|
| **U10** | Checklist ops § `docs/release/v1.2.11.md` consumidores: pasos ordenados (bump lock → migrate → smoke CAS staging → prod) con copy-paste — operador no adivina secuencia | P1 — **no verificado** M6 |
| **U11** | Smoke CAS post-bump: mensaje conflicto transición indica **qué hacer** (refrescar, revisar versión, reintentar) — validación de C4 consumido, no error opaco | P2 — **bloqueado** M6 |
| **U12** | `composer update` fallido por semver: mensaje Composer indica versión mínima (`>=1.2.11` / ideal `1.2.12`) y acción («composer require lebytek/framework:^1.2.12») | P-LOCK |

### Responsive — smoke en superficies tocadas (M5)

Referencia: `docs/core/ui_ux.md` §542 — breakpoint admin **992px (`lg`)**; tablas exigen `table-responsive` (`ui_ux.md` L222).

| Superficie | Verificación post-merge | Rango |
|------------|-------------------------|-------|
| Página 403 / flash RBAC permisos | Mensaje U1–U2 legible; enlace «volver» usable; sin scroll horizontal; tap targets ≥44px | **320–768px** |
| Catálogo permisos (`permisos/index.php`) | `table-responsive` + agrupación por módulo sin overflow; toolbar «Crear permiso» accesible | **320–768px** |
| Formularios crear/editar permiso | Campos apilados legibles; botones Cancelar/Guardar sin solapamiento | **320–768px** |
| Login / dashboard nav (sin alcance directo) | Carry-forward CF3–CF4 — smoke opcional post-merge | **320–768px** |

### Carry-forward UX — próximo spec con superficie UI más amplia

Ítems derivados de deuda abierta verificada; **CF8 (permisos admin catálogo / M5) queda cubierto por este spec** — no arrastrar. **CF7 (health público / M4)**, **CF6 (RBAC router CRUD / M3)** y **CRUD-C4** resueltos — no arrastrar. CF1–CF2 (trío semver + env purge), CF5 parcial (`mkt_leads` spec 2026-08-03) y D7 (CI spec 2026-08-04) tampoco se arrastran.

| # | Ítem | Origen | Requisito concreto |
|---|------|--------|-------------------|
| CF3 | Login responsive 320–768px | `ui_ux.md` | `.ct-login-page`, `.ct-login-card` sin overflow horizontal; tap targets ≥44px; sin scroll lateral en 320px. |
| CF4 | Dashboard admin responsive 320–768px | layouts side/top/bottom | Nav colapsable; KPI grid legible; topbar sin solapamiento de acciones. |
| CF5′ | Tablas CRUD restantes | módulo CRUD, D6 | `table-responsive` + `list.columns[].priority` en recursos distintos de `mkt_leads`; toolbar móvil. |
| CF9 | Estados vacío / error / carga (global) | `ui_ux.md` §8 | Unificar empty states en CRUDs sin hook; spinners list; validación con hint de corrección — más allá de U6 permisos. |
| CF10 | Copy errores accionables (transversal) | transversal | Auth, wizard install, CRUD save: qué falló + qué hacer — extiende U2 fuera de RBAC 403 permisos. |
| CF11 | Pantalla estado sistema post-tag | O2, D6 | `/admin/sistema/estado` muestra versión Framework legible en 320–768px tras deploy skeleton/staging — verificación manual bloqueada por D6/M6. |

---

## Criterios de aceptación

### M11 harness

- [ ] **AC-M11-1:** `microtest.php` define `microtest_reset_session()` e invoca tras cada `test()`.
- [ ] **AC-M11-2:** `MonolithicHarnessSessionIsolationTest` existe y pasa.
- [ ] **AC-M11-3:** `php tests/run.php` (sin MySQL) — 0 fails en `ApiHealthPublicDispatchTest` por sesión residual.
- [ ] **AC-M11-4:** `php tests/run.php Kernel` sigue 61/0 PASS.

### M5 RBAC

- [ ] **AC-M5-1:** `permisos.gestionar` en `database/schema/schema.sql` y migración idempotente registrada en `core.php`.
- [ ] **AC-M5-2:** Rutas permisos usan `RbacMiddleware('permisos.gestionar')` en harness + skeleton.
- [ ] **AC-M5-3:** `PermisosGestionarSlugTest` + gate RBAC 403/200 pasan.
- [ ] **AC-M5-4:** Rol con solo `administracion.ver` recibe 403 en catálogo permisos.

### Release / docs

- [ ] **AC-R1:** Trío semver `1.2.12` sync; `PlatformVersionSemverTest` PASS.
- [ ] **AC-D1:** Planes M3/M4 archivados o checkboxes alineados con `#114` / `v1.2.10`.
- [ ] **AC-D2:** `docs/release/v1.2.8.md` existe (stub retroactivo C6).

### Portal (**no verificados**)

- [ ] **AC-P1:** Portal lock ≥ `v1.2.11` — **bloqueado M6**.
- [ ] **AC-P2:** Smoke CAS staging — **bloqueado M6**.

### Deuda carry-forward

- [ ] **AC-D3:** CRUD-C4, M3, M4, REL-C1 reconciliados como **resueltos**; D1–D12 abiertos verificados con evidencia tip `990776e` (código ≡ `v1.2.11`).
- [ ] **AC-D4:** M6, M10, D6 documentados como ops — sin auto-fix en corrida desatendida.
- [ ] **AC-D5:** Reconciliación pase 2026-08-13 → **0 cierres** en tip código (delta `cea890e`→`990776e` = audit #124 docs-only); H2 parcial en rama `automation/spec-2026-08-13` **no mergeado** a `main`.
- [ ] **AC-D6:** P1/P2/P3 y Portal issues marcados **no verificados** (M6); bootstrap/schema/manifiesto y legacy operativo verificados sin deuda nueva.

### Compatibilidad, UX y responsive

- [ ] **AC-UX1:** Sección **Compatibilidad, UX y responsive** declara modo **normal** con requisitos K/U/R verificables para M5 (403 permisos, catálogo admin), M11 (DX monolito) y P-LOCK (checklist ops).
- [ ] **AC-UX2:** Requisitos U1–U12 (slug en 403, copy accionable, JSON AJAX, distinción administracion.ver vs permisos.gestionar, hints test gate M5/M11, empty state permisos, checklist P-LOCK) incluidos como criterios del spec.
- [ ] **AC-UX3:** Carry-forward CF3–CF4, CF5′, CF9–CF11 documentado; CF8 no arrastrado (cubierto por M5 este spec); CF6, CF7, CRUD-C4 no arrastrados (resueltos); CF1–CF2, CF5 parcial y D7 no arrastrados (resueltos o cubiertos en specs previos).
- [ ] **AC-UX4:** Smoke responsive en **320–768px** para 403/flash permisos y catálogo/formularios permisos post-implementación M5 (sin regresión `table-responsive`).

---

## Deuda técnica

Fuente: auditoría `docs/audits/2026-08-14-auditoria-tecnica-diaria.md` (mergeada en `main` @ `990776e` — PR #124); reconciliación con inventario degradado `docs/superpowers/specs/2026-08-13-deuda-tecnica.md` (pase deuda 2026-08-13 @ `cea890e`) y spec `2026-08-12-audit-post-v1211-debt-design.md` (PR #121, no mergeado).

**Verificación:** tip código ≡ **`v1.2.11`** @ `990776e`; delta `cea890e`→`990776e` = merge audit #124 (docs-only). **0 hallazgos nuevos** de plataforma. **0 cierres** en tip código desde pase deuda 2026-08-13.

### Reconciliación heredada (cerrados — no re-listar como abiertos)

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| **CRUD-C4** | CAS/TOCTOU transitions | **Resuelto** | PR #118 / tag `v1.2.11` |
| **REL-C1** | Tags semver publicados | **Resuelto** | `v1.2.7`…`v1.2.11`; trío `1.2.11` sync |
| **M3** | CRUD RBAC router | **Resuelto** | PR #114 / `v1.2.10` — `CrudRbacMiddleware` `routes/web.php` L115 |
| **M4** | `/api/health` público | **Resuelto** | PR #114 / `v1.2.10` — `routes/api.php` L14–15 |
| **INV-E1/E2** | Invoicing vertical OFF | **Resuelto** | `config/vertical.php` L20–23 |
| **M1/M2/M7/M8/M9/D7** | Semver/env/CI/docs | **Resuelto** | Sin regresión audit 2026-08-14 |
| **D10-ops** | Legacy branch en runbooks | **Resuelto** | `scripts/` sin refs `backoffice-api-integration`; `docs/composer-setup.md` L128 pin semver |

**Cierres desde pase deuda 2026-08-13:** **0** en tip código. **H2** (planes M3/M4 archivados) parcial en rama `automation/spec-2026-08-13` — **pendiente merge** a `main`.

### Alcance principal (abiertos, verificados @ `990776e`)

| ID | Alias | Hallazgo | Evidencia | Impacto | Capa | Owner | Acción |
|----|-------|----------|-----------|---------|------|-------|--------|
| **D1** | M11 | Contaminación sesión monolito | `tests/run.php` L29–31 — carga secuencial sin teardown; `tests/lib/microtest.php` L7–17 — `test()` sin reset; `tests/Auth/LoginUseCaseTest.php` L91–98 — login deja sesión; `tests/Kernel/ApiHealthPublicDispatchTest.php` L30–36, L55–67 — fallan en monolito; `MonolithicHarnessSessionIsolationTest` **ausente** | `php tests/run.php` → 802/9 (2 M11 + 7 MySQL env); CI aislada verde | `tests/` harness | Framework | F1–F2 |
| **D2** | M5 | Slug `permisos.gestionar` ausente | `routes/web.php` L62–66 + `skeleton/routes/web.php` L62–66 — workaround `administracion.ver`; `database/schema/schema.sql` L298–299 y `database/seeds_legacy/010_auth_permisos.sql` L2–3 sin slug; `rg permisos.gestionar database/` → **0**; `PermisosGestionarSlugTest` **ausente**; `docs/core/auth_rbac_seguridad_v0.1.md` L69 documenta slug incorrecto | Rol con `administracion.ver` gestiona catálogo RBAC | `Domain` RBAC / `routes/` | Framework | F3–F6 |
| **D3** | P-LOCK | Consumidores sin bump ≥ `v1.2.11` | Framework tip `1.2.11` tagueado (`composer.json` L6, `config/app.php` L7); Portal lock **no verificado** (D4) — última evidencia doc `v1.1.0` @ `a79d3ad` | Portal/CRM sin CAS/C6/M3/M4 en prod | Consumidor Composer | `Lebytek_Portal` | P1–P2 manual |

### Backlog ops y hygiene (abiertos, verificados)

| ID | Alias | Hallazgo | Evidencia | Impacto | Capa | Owner | Acción |
|----|-------|----------|-----------|---------|------|-------|--------|
| **D4** | M6 | Portal SHA / lock no inspeccionable | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL 404; `gh api …/commits/main` → HTTP 404 (reconfirmado 2026-08-14) | Automation no verifica bump consumidor | Ops / credenciales | Ops | Conceder lectura gh al token |
| **D5** | M10 | Huecos auditorías diarias | `docs/audits/` — ausentes `2026-08-03`, `2026-08-04`, `2026-08-05`, `2026-08-10`; presentes 01–02, 06–09, 11–14 | Cadena diseño sin ancla en esas fechas | Proceso automation | Ops | Backfill o aceptación documentada |
| **D6** | D6 | `skeleton.lebytek.com` pendiente | `docs/ENVIRONMENTS.md` L7, L13, L34 — «pendiente de implementar» | LAB package no desplegado | Ops | Framework/Ops | Plan `2026-07-26-skeleton-package-staging-design.md` |
| **D7** | H1 | Release notes `v1.2.8` ausentes | `docs/release/` — `v1.2.7.md`, `v1.2.9.md`, `v1.2.10.md`, `v1.2.11.md`; tag git `v1.2.8` existe; **sin** `v1.2.8.md` | Paridad cadena release C6 uploads | `docs/` | Framework | F7 stub retroactivo PR #111 |
| **D8** | H2 | Planes M3/M4 checkboxes obsoletos | `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` — **41** `[ ]`; `2026-08-05-audit-api-health-public.md` — **39** `[ ]` pese a ship #114 / `v1.2.10` | Implementador puede reabrir trabajo cerrado | `docs/` | Framework | F7 archivar/marcar |
| **D9** | H3 | PRs spec C4 duplicados/obsoletos | PRs abiertos `#116` (C4+M11), `#117` (C4 duplicado) — C4 mergeado #118; `#121`/`#123` duplican M11/M5 | Ruido proceso | GitHub | AUTOMATION-03 | Cerrar/consolidar PRs |
| **D10** | H4 | `composer validate` lock drift | `.github/workflows/platform-tests.yml` L22–25 — `composer validate --no-check-lock`; audit documenta exit 2 en tip | Hygiene semver/lock; no bloquea CI | CI | Framework | PR hygiene opcional post-`1.2.12` |

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
| Payments bootstrap | `config/vertical.php` L20–23 — `marketing`/`payments`/`invoicing` = `false`; `tests/Payments/PaymentsConfigTest.php` L10–24 |
| Bootstrap schema | Sin migraciones huérfanas ni drift manifiesto |
| CI gates descubren tests | `.github/workflows/platform-tests.yml` presente; suites aisladas no triviales |
| `.env.example` root vs skeleton | Drift intencional (harness vs tenant template); root L53–55 remite vars Portal a `Lebytek_Portal` |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ `v1.2.11` | D4 — gh 404 Portal | Operador: `composer require lebytek/framework:^1.2.11` |
| **P2** | Smoke CAS staging Portal | D4 | Operador manual post-P1 |
| **P3** | Portal semver en `/admin/sistema/estado` | D4 + D6 | Verificación manual staging/prod |
| **Portal issues abiertos** | Conteo issues Portal | D4 — gh 404 | Ops conceder acceso |
| **H5** | `composer validate --strict` en tip | No ejecutado en corrida (inventario estático) | Maintainer en release train |

**Resumen:** **12 ítems abiertos verificados** (D1–D12); **5 no verificados** (P1–P3, Portal issues, H5); **0 cierres** heredados desde pase 2026-08-13.

---

## Migración segura

### Base nueva (skeleton / install limpio)

1. Install aplica schema + seeds incluyendo `permisos.gestionar`.
2. Admin recibe permiso vía seed rol.
3. Verificar rutas permisos con slug dedicado.

### Base Framework existente (harness / tenant)

1. Merge Framework con tag `v1.2.12`.
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
| `MonolithicHarnessSessionIsolationTest` | Docs | Helper reset ausente | M11 — **no existe en tip** |
| `ApiHealthPublicDispatchTest` (existente) | Kernel | 302→200 en monolito | M11 contaminación |
| `PermisosGestionarSlugTest` | Docs | slug no encontrado en SQL activo | M5 — **no existe en tip** |
| `PermisosRbacMiddlewareTest` (propuesto) | Kernel | 403/200 gate incorrecto | M5 |
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
| Merge specs `#121`/`#123` | AUTOMATION-03 | — | — |

**Producción explícitamente excluida** de corrida cron desatendida.

---

*Design-only. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
