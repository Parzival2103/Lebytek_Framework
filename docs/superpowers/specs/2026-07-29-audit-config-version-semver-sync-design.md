# Design: Sincronización semver de versión de plataforma y higiene harness post-v1.2.1

**Fecha:** 2026-07-29  
**Repo:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación  
**Auditoría fuente:** `docs/audits/2026-07-29-auditoria-tecnica-diaria.md` (PR #48, head `2eaa50a`)  
**Rama spec:** `automation/spec-2026-07-29`  
**Rama deuda:** `automation/audit-spec-2026-07-29`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `0ec722bc38258b2e479d30cafd59940aa44d558e` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → HTTP 404; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| Rama generada | `automation/spec-2026-07-29` |
| Timestamp UTC | `2026-07-29T13:35:00Z` |
| Nivel de fuente | **A** — PR abierto #48 `docs(audit): auditoría técnica diaria 2026-07-29`, base `main`, `mergeable: MERGEABLE` (draft), headRefOid `2eaa50ad546deaa94e9f59a56ef8e4fffb7ff4b8`, diff único: `docs/audits/2026-07-29-auditoria-tecnica-diaria.md` |
| PR auditoría fuente | #48 |
| headRefOid fuente | `2eaa50ad546deaa94e9f59a56ef8e4fffb7ff4b8` |
| Pase deuda | `deuda` @ `2026-07-29T14:02:28Z` — modo **normal** — SHA `origin/main` `0ec722bc38258b2e479d30cafd59940aa44d558e` — rama `automation/audit-spec-2026-07-29` |

---

## Problema

La auditoría del 2026-07-29 confirma que `main` @ `0ec722b` está **estable** tras siete commits funcionales desde la auditoría consolidada del 2026-07-27: eliminación de scripts VPS destructivos (PR #36), contrato Stripe subscription + fixes de instalador en tag **`v1.2.1`** (PR #42), `SqlFileRunner` consciente de literales (PR #40), y documentación de entornos (PRs #43–#46). `SkeletonPurityTest` y suites Payments/Install verdes en entorno con dependencias.

Sin embargo, la **superficie de versión visible al operador** sigue desincronizada del release semver publicado:

| ID | Hallazgo | Evidencia verificada | Impacto |
|----|----------|----------------------|---------|
| **M1** (nuevo) | `config/app.php` fija `'version' => '1.0.0'` | Root `config/app.php:7`; tags publicados `v1.2.0`, `v1.2.1` @ `fba3e03`; `composer.json` **sin** campo `version` | Página `/admin/sistema/estado` y wizard de instalación muestran v1.0.0; soporte confunde release real |
| **M2** (arrastrado) | Root `.env.example` conserva vars Portal/Marketing | `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*` en L54–102; `skeleton/.env.example` limpio | Mantenedores del harness copian vars obsoletas post-FPS |
| **M6** (nuevo, entorno) | Portal SHA no inspeccionable desde agente cloud | `gh` → 404 en `Lebytek_Portal` | Cadena automation no puede verificar `composer.lock` ni QA Stripe en Portal |

**Deuda crítica arrastrada (sin cambio de estado):**

| ID | Estado 2026-07-29 | Owner |
|----|-------------------|-------|
| C1 deploy destructivo | **Resuelto** PR #36 | Framework |
| C2 Stripe subscription (#21) | Framework **resuelto** v1.2.1; gate `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` vigente hasta QA Portal | Framework ✅ / Portal ⏳ |
| C3 bootstrap marketing (#23) | Re-scopeado a Portal `Lebytek_Portal#4` | Portal |

**Gap de tests:** no existe test que compare `config/app.php` `version` con el tag semver más reciente ni con un campo canónico en `composer.json`. `FrameworkRootNotPortalTest` valida frontera FPS pero **no** el drift M2 en root `.env.example`.

---

## Deuda técnica

Inventario verificado contra `origin/main` @ `0ec722b` (2026-07-29). **Ningún ítem se auto-fixea en este pase** — queda como requisito del spec/PR/plan posterior.

### Reconciliación heredada (corrida anterior → estado en `main`)

| ID heredado | Tema | Estado 2026-07-29 | Evidencia |
|-------------|------|-------------------|-----------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **Resuelto** | PR #36; `tests/Docs/DeployScriptsRemovedTest.php` PASS; `scripts/vps-deploy-*.sh` ausentes |
| D-SqlRunner (2026-07-27) | Partición SQL en seeds con `;` en strings | **Resuelto** | PR #40 — `src/Infrastructure/Install/SqlFileRunner.php` |
| C2 / #21 Stripe subscription | Contrato Framework C1–C6 | **Resuelto Framework** | Tag `v1.2.1` @ `fba3e03` (PR #42); gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` vigente |
| C3 / #23 bootstrap marketing | Columnas lifecycle/churn en bootstrap | **Re-scopeado** | Portal `Lebytek_Portal#4` — **no verificado** esta corrida |
| M2 (2026-07-27) | Root `.env.example` vars Portal | **Abierto** | Sin cambio vs spec archivado 2026-07-27 |
| Q4 deprecated banner (2026-07-27) | `vps-deploy-lebytek-com.sh` | **Obsoleto** | Script eliminado PR #36; deuda migra a **docs drift** (D7–D10) |

### Inventario abierto (priorizado)

| ID | Hallazgo | Evidencia (`main` @ `0ec722b`) | Impacto | Capa / owner | Acción requerida |
|----|----------|--------------------------------|---------|--------------|------------------|
| **D1** | Drift semver plataforma (M1) | `config/app.php:7`, `skeleton/config/app.php:7` → `'1.0.0'`; `composer.json` sin `"version"`; tags `v1.2.0`, `v1.2.1` @ `fba3e03` | UI `/admin/sistema/estado` y wizard muestran versión obsoleta | Harness / `config/` — Framework | Fase 1: `"version"` en `composer.json` + sync configs + `PlatformVersionSemverTest` |
| **D2** | Root `.env.example` conserva vars Portal (M2) | `.env.example` L54–102: `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`; `skeleton/.env.example` limpio | Mantenedores harness copian vars post-FPS | Harness — Framework | Fase 2 (spec 2026-07-27): purga + extensión `FrameworkRootNotPortalTest` |
| **D3** | Portal SHA no inspeccionable (M6) | `gh repo view Parzival2103/Lebytek_Portal` → HTTP 404 | Automation no verifica `composer.lock` ni QA Stripe | Ops / credenciales gh | Fase 3 ops: scope lectura Portal al token automation |
| **D4** | Test gate semver ausente | `tests/Docs/PlatformVersionSemverTest.php` **no existe**; grep `PlatformVersionSemver` → 0 | Drift semver no detectado en CI local | `tests/Docs/` — Framework | Crear test TDD (Fase 1); debe **fallar** pre-fix |
| **D5** | `FrameworkRootNotPortalTest` no cubre `.env.example` | `tests/Kernel/FrameworkRootNotPortalTest.php` — valida dirs Marketing y `vertical.php`; **no** assert prefijos env | Reintroducción silenciosa de vars Portal | `tests/Kernel/` — Framework | Fase 2: assert ausencia `MKT_`/`LEBYTEK_API_`/`WAAPI_PORTAL_` en root |
| **D6** | CRUD/Calendario sin `RbacMiddleware` en router (M3) | `routes/web.php` L114–125 — rutas `/admin/crud/*` y `/admin/calendario/*` solo `AuthMiddleware` | Defensa en profundidad delegada a `CrudResourceService`; superficie amplia tras auth | `Presentation` / `routes/` — Framework | Backlog: documentar patrón o añadir RBAC router-level |
| **D7** | API health no pública (M4) | `routes/api.php` L14–16 — grupo `/api` con `AuthMiddleware`; `/api/ping` requiere sesión | Load balancers/cron no pueden health-check sin cookie | `Presentation` / `routes/` — Framework | Backlog Fase 3 (spec 2026-07-27): `GET /api/health` público |
| **D8** | Slug `permisos.gestionar` ausente (M5) | `routes/web.php` L61–65 — comentario + workaround `administracion.ver`; `database/seeds/010_auth_permisos.sql` sin slug | Permisos catálogo RBAC acoplados a `administracion.ver` | `Domain` RBAC — Framework | Backlog: seed + rutas + menú si producto aprueba |
| **D9** | Sin pipeline CI GitHub Actions | `.github/workflows/` **ausente** en árbol `main` | Tests dependen de ejecución manual/`tests/run.php` local | Ops / repo — Framework | Evaluar workflow mínimo post-implementación spec |
| **D10** | `docs/composer-setup.md` pin legacy branch | L121–128: `"lebytek/framework": "dev-feature/backoffice-api-integration"` | Consumidores nuevos instalan monolito pre-FPS | `docs/` — Framework | Actualizar a semver tag / Portal Composer post-cutover |
| **D11** | `docs/integration/VPS_CHECKLIST.md` obsoleto | L13 referencia `vps-deploy-lebytek-com.sh` (eliminado PR #36); L89 `Branch: feature/backoffice-api-integration (until merge)` | Ops cree feature como target final | `docs/integration/` — Framework | Marcar interino/deferred; apuntar a Portal Composer + `ENVIRONMENTS.md` |
| **D12** | Runbooks integration apuntan a feature | `docs/integration/lebytek-implementation-real.md` L3; `role-delegation-lebytek-api.md` L195 — branch `feature/backoffice-api-integration` | Guías operativas desalineadas de FPS | `docs/integration/` — Framework | Reescribir deploy target → `Lebytek_Portal` + Composer |
| **D13** | `despliegue-y-versionado.md` sin paso sync semver | `docs/core/despliegue-y-versionado.md` — no menciona sync `composer.json` + configs en release | Release sin checklist de tres archivos | `docs/core/` — Framework | Fase 1: añadir paso en § Versionado |
| **D14** | Stripe subscription QA Portal pendiente (#21) | Framework v1.2.1 publicado; `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en harness/skeleton | Habilitar checkout sin QA rompe prod | Portal `app/Application/Marketing/` — **no verificado** | Portal PR #16 QA + bump lock ≥ v1.2.1 antes de gate ON |
| **D15** | Bootstrap marketing incompleto (#23) | Re-scopeado Portal `Lebytek_Portal#4` — columnas lifecycle/churn | Fresh install Portal puede fallar en marketing | Portal `database/` — **no verificado** | Portal issue #4 — fuera Framework |
| **D16** | `seguridad_secretos_deploy.md` simplifica auto-pull | L6: «El VPS hace auto-pull de `main`» — no distingue Portal Composer vs harness | Operador asume modelo monolito | `docs/core/` — Framework | Aclarar: Portal despliega repo consumidor, no package source |

**Conteo:** 14 ítems **abiertos** verificados (D1–D13, D16 en Framework; D14–D15 Portal no verificados). 4 ítems heredados **cerrados** (C1, D-SqlRunner, C2 Framework side, Q4 obsoleto).

**No verificado esta corrida:** SHA Portal, estado prod `lebytek.com`/`waapi`, crontab VPS, `composer.lock` Portal, issues Portal #4/#16/#21 en GitHub.

---

## Comportamiento esperado

### Fase 1 — Fuente canónica de versión de plataforma (M1, prioridad inmediata)

1. **`composer.json`** del paquete declara `"version": "1.2.1"` (semver sin prefijo `v`), alineado con el tag Git publicado más reciente en `main`.
2. **`config/app.php`** (harness root) y **`skeleton/config/app.php`** declaran la **misma** versión de plataforma que `composer.json`.
3. **`DeploymentStatus`** y la vista `/admin/sistema/estado` muestran la versión correcta (p. ej. `v1.2.1`) sin cambio de contrato público — siguen leyendo `Config::get('app.version')`.
4. **Procedimiento de release documentado:** al crear tag `vX.Y.Z`, el mantenedor actualiza `composer.json` + ambos `config/app.php` en el **mismo commit** que precede al tag (o en el commit del tag). No se automatiza en esta fase desatendida.
5. **Test gate `PlatformVersionSemverTest`** (nuevo, `tests/Docs/`):
   - Lee `composer.json` → campo `version`.
   - Lee `config/app.php` y `skeleton/config/app.php` → clave `version`.
   - Aserciona igualdad entre los tres.
   - **Antes de implementar:** el test existe, descubre al menos un archivo, y **falla** porque `composer.json` no tiene `version` y `config/app.php` dice `1.0.0` mientras tags llegan a `v1.2.1`.

### Fase 2 — Purga harness `.env.example` (M2, carry-forward)

Spec detallado ya redactado: `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md`.

Comportamiento resumido (sin duplicar el spec archivado):

1. Eliminar bloques Portal/Marketing del root `.env.example`.
2. Extender `FrameworkRootNotPortalTest` (o test hermano) para fallar si root contiene prefijos `MKT_`, `LEBYTEK_API_`, `WAAPI_PORTAL_`.
3. Comentario de referencia apuntando a `Lebytek_Portal/.env.example` — **no verificado** en esta corrida.

### Fase 3 — Verificación cross-repo automation (M6, ops — fuera de producto)

1. Token `gh` del agente cloud recibe acceso **lectura** a `Parzival2103/Lebytek_Portal`.
2. Auditorías futuras registran SHA Portal + versión `lebytek/framework` en `composer.lock`.
3. **Esta corrida desatendida no configura tokens ni secrets.**

### Estado interino VPS (sin cambio en esta corrida)

- `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` hasta QA humano Portal PR #16 (referenciado en cierre #21) — **Portal no verificado**.
- `lebytek.com` / `waapi.lebytek.com` — estado prod **no re-auditado** (sin acceso gh).
- `skeleton.lebytek.com` — pendiente según `docs/superpowers/plans/2026-07-26-skeleton-package-staging.md`.

---

## Enfoques considerados

### Enfoque A — `composer.json` como fuente canónica + test gate (recomendado)

Añadir `"version"` a `composer.json` (estándar Composer para paquetes), sincronizar manualmente `config/app.php` en release, test que falla en drift.

| Pros | Contras |
|------|---------|
| Alineado con semver Composer y tags Git | Bump manual en tres archivos hasta automatizar |
| Test determinista sin depender de red ni git en CI | Requiere disciplina en checklist de release |
| Consumidores (Portal) ya leen versión vía `vendor/composer/installed.json` | No resuelve versión de **app** Portal (scope distinto) |

### Enfoque B — Script `scripts/bump-version.php` que escribe los tres archivos

Un comando recibe `X.Y.Z` y actualiza composer + configs atómicamente.

| Pros | Contras |
|------|---------|
| Elimina error humano en release | Más código de tooling; fuera de alcance mínimo M1 |
| Integrable en CI de tag | Requiere mantener script |

### Enfoque C — Leer versión en runtime desde `InstalledVersions::getVersion('lebytek/framework')`

`DeploymentStatus` obtiene versión del paquete Composer en lugar de config.

| Pros | Contras |
|------|---------|
| Single source en vendor lock | En monorepo harness (`path` repo) puede devolver `dev-main`; wizard pre-install no tiene vendor |
| | Rompe contrato documentado en `docs/core/despliegue-y-versionado.md` (T1 → `config/app.php`) |

**Recomendación:** Enfoque A para Fase 1 (diff mínimo, test gate). Enfoque B como mejora opcional en release posterior. Enfoque C rechazado por incompatibilidad con harness package-source y wizard pre-vendor.

---

## Alcance

### Framework (`Lebytek_Framework` / `main`)

| Ítem | Fase |
|------|------|
| Añadir `"version"` a `composer.json` | 1 |
| Actualizar `config/app.php` `version` → `1.2.1` | 1 |
| Actualizar `skeleton/config/app.php` `version` → `1.2.1` | 1 |
| Crear `tests/Docs/PlatformVersionSemverTest.php` | 1 |
| Documentar paso en checklist de release (`docs/core/despliegue-y-versionado.md` § Versionado) | 1 |
| Purga root `.env.example` + extensión `FrameworkRootNotPortalTest` | 2 (spec 2026-07-27) |
| Actualizar docs integration/composer post-FPS (D10–D12, D16) | 2b (backlog docs) |

### Portal (`Lebytek_Portal` / `main`) — **no verificado**

| Ítem | Notas |
|------|-------|
| Confirmar `composer.lock` con `lebytek/framework` ≥ v1.2.1 | Requiere acceso gh — **no verificado** |
| QA PR #16 Stripe subscription | Gate ops hasta cierre humano |
| `config/app.php` Portal | Versión de **app negocio**, independiente de plataforma (T3/T4) — fuera de alcance M1 |

### Ops / Automation

| Ítem | Entorno |
|------|---------|
| Scope lectura gh → Portal | Configuración automation — **fuera de corrida desatendida** |
| Preinstalar `php-cli`, `composer`, `php-mysql` en cloud agent | CI/imagen — mejora entorno, no producto |

---

## No-alcance

- Implementación de código en esta corrida (AUTOMATION-02 spec-only).
- Cambios en `src/Domain/Payments/` ni contrato Stripe (ya en v1.2.1).
- Merge o deploy Portal; habilitar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` en producción.
- Despliegue `skeleton.lebytek.com` (plan existente, tareas VPS humanas).
- Automatización completa de tagging semver (script bump — Fase B futura).
- Editar `vendor/` en consumidores.
- Merge `feature/backoffice-api-integration` → `main`.
- Cierre del PR #48 de auditoría (AUTOMATION-03).
- Auto-fix de deuda D6–D8 (RBAC router, `/api/health`, `permisos.gestionar`) — backlog separado, no bloquea Fase 1 semver.
- Auto-fix Portal D14–D15 (#21 QA, #23 bootstrap) — requisitos documentados; owner Portal.
- Purga docs integration D10–D12 en la misma PR que Fase 1 semver — puede ser PR docs dedicado post-implementación.
- Configuración token `gh` Portal (D3) — ops humano.

---

## Ownership map

| Requisito | Repositorio propietario | Rama base | Capa / ruta |
|-----------|-------------------------|-----------|-------------|
| Campo `version` en paquete Composer | `Lebytek_Framework` | `main` | `composer.json` |
| Versión plataforma harness | `Lebytek_Framework` | `main` | `config/app.php` |
| Versión plataforma plantilla skeleton | `Lebytek_Framework` | `main` | `skeleton/config/app.php` |
| Test gate semver | `Lebytek_Framework` | `main` | `tests/Docs/PlatformVersionSemverTest.php` |
| Purga `.env.example` root | `Lebytek_Framework` | `main` | `.env.example`, `tests/Kernel/FrameworkRootNotPortalTest.php` |
| Consumo semver Framework | `Lebytek_Portal` | `main` | `composer.lock` → `vendor/lebytek/framework` — **no verificado** |
| QA Stripe subscription | `Lebytek_Portal` | `main` | `app/Application/Marketing/` — **no verificado** |
| Token gh Portal | Cursor automation / GitHub org settings | — | Ops |
| Tag release Framework | `Lebytek_Framework` | `main` | Git tag `v*` — ya existe `v1.2.1` |

---

## Dependencias y compatibilidad

### Semver / release boundary

- Tag publicado **`v1.2.1`** @ `fba3e03` incluye contrato Stripe subscription + fixes instalador (PR #42).
- Portal debe consumir **`lebytek/framework` ^1.2** (mínimo `1.2.1`) antes de habilitar checkout subscription.
- Fase 1 de este spec es **PATCH-level documental/config** dentro del mismo minor line — no requiere tag nuevo obligatorio si se mergea antes del próximo release; si se mergea después de `v1.2.2`, alinear número al tag vigente.
- **Contrato público ausente:** no existe API HTTP de versión de plataforma; la superficie es config + UI admin estado + wizard install metadata. No asumir endpoint `/api/version`.

### Compatibilidad consumidores

| Consumidor | Impacto Fase 1 | Migración |
|------------|----------------|-----------|
| Harness local (monorepo) | Solo display en estado/wizard | Ninguna — bump config |
| `skeleton/` plantilla | Mismo valor en `skeleton/config/app.php` | Regenerar espejo `Lebytek_Skeleton` en próximo split |
| Portal con lock v1.1.0 | **Sin impacto** — versión en `vendor` no cambia hasta `composer update` | Bump lock separado (Portal scope) |
| Portal con lock ≥ v1.2.1 | Display en Portal app sigue siendo versión **app** propia | Independiente de M1 |

### Dependencias de specs/planes existentes

- M2 → `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md`
- skeleton.lebytek.com → `docs/superpowers/specs/2026-07-26-skeleton-package-staging-design.md` + plan activo
- Stripe boundary → `docs/archive/superpowers/specs/2026-07-27-stripe-subscription-boundary-design.md`

---

## Riesgos

| Riesgo | Severidad | Mitigación | Deuda |
|--------|-----------|------------|-------|
| Drift reintroducido en próximo release sin bump config | Media | Test gate D4 + checklist D13 | D1, D4, D13 |
| Confundir versión app Portal con versión framework | Baja | Documentar dos números independientes (ya en `despliegue-y-versionado.md`) | D1 |
| Habilitar Stripe sin QA Portal | **Alta** | Mantener `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` — **no verificado en prod esta corrida** | D14 |
| Portal lock desactualizado (v1.1.0 documentado) | Media | Verificación manual operador — gh bloqueado (D3) | D3, D14 |
| Fase 2 purga `.env.example` rompe test harness que dependa de `MKT_*` | Baja | Grep tests antes de merge; vars solo en Portal | D2, D5 |
| Docs operativas apuntan a feature branch / scripts eliminados | Media | Operador sigue runbooks obsoletos (D10–D12) | D10–D12 |
| CRUD accesible tras auth sin RBAC router | Baja | `CrudResourceService` verifica permisos por recurso | D6 |
| Health check API requiere sesión | Baja | Cron/load balancer no puede usar `/api/ping` | D7 |
| Sin CI automatizado | Media | Regresiones semver/env pasan desapercibidas hasta `tests/run.php` manual | D9 |
| Legacy `archive/backoffice-api-integration` | Histórico | Solo evidencia migración — tag @ `4789f95`, no base de implementación | — |
| Fresh install Portal marketing incompleto | **Alta** (Portal) | Issue Portal #4 — **no verificado** | D15 |

---

## Rollback

| Fase | Rollback |
|------|----------|
| Fase 1 | Revert commit que cambia `composer.json` + `config/app.php`; tags Git no se reescriben |
| Fase 2 | Restaurar vars en `.env.example` desde git; revert test extension |
| Portal bump (fuera de alcance) | `composer require lebytek/framework:1.1.0` + redeploy — solo si regresión |

Operaciones de **producción** (VPS, flags Stripe, deploy Portal) quedan **fuera** de rollback automatizado en esta corrida.

---

## Criterios de aceptación

### Fase 1 — Semver sync (Framework) — cierra D1, D4, D13

- [ ] `composer.json` contiene `"version": "1.2.1"` (o versión del tag vigente al merge).
- [ ] `config/app.php` y `skeleton/config/app.php` tienen el mismo valor `version`.
- [ ] `php tests/run.php PlatformVersionSemver` pasa (test nuevo verde).
- [ ] Antes del fix, el test **falla** detectando drift `1.0.0` vs tags `v1.2.1`.
- [ ] `docs/core/despliegue-y-versionado.md` menciona sincronizar tres archivos en release.
- [ ] `php tests/run.php SkeletonPurity` sigue verde.
- [ ] Sin cambios en `src/` salvo que un futuro plan decida runtime read (rechazado aquí).

### Fase 2 — Env purge (Framework, spec 2026-07-27) — cierra D2, D5

- [ ] Root `.env.example` sin `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`.
- [ ] `FrameworkRootNotPortalTest` (o hermano) falla si se reintroducen prefijos.
- [ ] `skeleton/.env.example` sin regresión.

### Fase 2b — Docs drift post-FPS (Framework, backlog) — cierra D10–D12, D16

- [ ] `docs/composer-setup.md` §6 elimina pin `dev-feature/backoffice-api-integration`; referencia semver Composer.
- [ ] `docs/integration/VPS_CHECKLIST.md` — sección lebytek.com apunta a `Lebytek_Portal` + tag/sha; sin referencia a `vps-deploy-*.sh`.
- [ ] `docs/integration/lebytek-implementation-real.md` y `role-delegation-lebytek-api.md` — deploy target Portal Composer, no feature branch.
- [ ] `docs/core/seguridad_secretos_deploy.md` distingue deploy Portal vs package source.

### Verificación cross-repo (Ops — manual) — cierra D3, D14

- [ ] Token automation lee Portal `main` SHA — **pendiente, no verificado**.
- [ ] Operador confirma Portal lock ≥ v1.2.1 antes de gate Stripe — **pendiente**.
- [ ] QA humano Portal PR #16 (Stripe subscription) completado antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true`.

### Deuda explícitamente fuera de criterios de este spec

- [ ] D6 RBAC router CRUD/Calendario — backlog separado.
- [ ] D7 `/api/health` público — backlog Fase 3 spec 2026-07-27.
- [ ] D8 slug `permisos.gestionar` — backlog producto.
- [ ] D9 workflow GitHub Actions — evaluación ops independiente.
- [ ] D15 bootstrap marketing Portal #4 — owner Portal.

### Explícitamente fuera de criterios de esta corrida

- Deploy staging/producción.
- Cierre PR #48.
- Apertura PR de implementación (AUTOMATION-03).

---

## Tests (diseño TDD)

### `PlatformVersionSemverTest` (nuevo)

```php
// Pseudocódigo — implementación en AUTOMATION-03+
test('platform version matches composer.json and skeleton config', function (): void {
    $composer = json_decode(file_get_contents('composer.json'), true);
    $rootConfig = require 'config/app.php';
    $skelConfig = require 'skeleton/config/app.php';

    assert_true(isset($composer['version']), 'composer.json must declare version');
    assert_same($composer['version'], $rootConfig['version']);
    assert_same($composer['version'], $skelConfig['version']);
});
```

**Estado previsto pre-implementación:** FAIL — `composer.json` sin `version`; configs en `1.0.0`.

### Regresión existente

- `DeployScriptsRemovedTest` — scripts VPS ausentes (C1 resuelto).
- `AutomationPreflightRefTest` — preflight legacy-ref.
- `SkeletonPurityTest` — frontera FPS intacta.

---

## Operaciones por entorno

| Operación | Implementación | Staging | Producción |
|-----------|----------------|---------|------------|
| Bump version config | Sí (PR Framework) | N/A harness | N/A — Portal usa vendor lock |
| Merge spec/plan | Automation | — | — |
| `composer update lebytek/framework` Portal | No (Portal PR) | Futuro staging Portal | Manual post-QA — **fuera corrida** |
| Habilitar Stripe subscription | No | Sandbox futuro | **Prohibido** hasta QA — gate OFF |
| Configurar gh token Portal | No (ops) | — | — |
| Deploy skeleton.lebytek.com | No | Plan humano | No tocar prod Portal |

---

## Issues abiertos (contexto de riesgo — no auto-fix)

| Repo | Issue / PR | Relación | Deuda |
|------|------------|----------|-------|
| Framework | *(ninguno abierto verificado vía gh)* | — | — |
| Portal | #21 Stripe | Cerrado Framework side; QA Portal pendiente — **Portal no verificado** | D14 |
| Portal | #23 bootstrap | Re-scopeado Portal #4 — **no verificado** | D15 |
| Portal | PR #16 | Referenciado para QA subscription — **no verificado** | D14 |
| Portal | #4 bootstrap marketing | Columnas lifecycle/churn en SQL negocio — **no verificado** | D15 |

---

## Referencias

- Auditoría: `docs/audits/2026-07-29-auditoria-tecnica-diaria.md`
- Guía versionado: `docs/core/despliegue-y-versionado.md` § "Dos números independientes"
- Spec M2 archivado: `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md`
- Entornos: `docs/ENVIRONMENTS.md`
- Legacy histórico: tag `archive/backoffice-api-integration` @ `4789f95` — **no** base de trabajo
