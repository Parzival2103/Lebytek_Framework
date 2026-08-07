# Design: AuthZ multi-canal del CRUD Engine (C1+C2+C5 / programa C1–C6)

**Fecha:** 2026-08-07  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel A)

**Auditoría fuente:** `docs/audits/2026-08-07-auditoria-tecnica-diaria.md` (PR #96 @ `9ca422b53a8cb803d3f6e4e3a064da73854cc659`; rama `automation/audit-2026-08-07`)  
**Hallazgo principal:** **CRUD-C1…C6** — autorización y mutación asimétricas en el CRUD Engine de plataforma (inventario canónico en `docs/audits/2026-08-07-auditoria-critica-crud-engine.md`, mergeado PR #90). Prioridad inmediata: **C1 + C2 + C5** (punto 1 del programa de remediación).

**Specs/planes relacionados (no duplicar):**

- Router RBAC CRUD/calendario (M3 / G4): `docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md` · plan `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` (**0/5**) — **no sustituye C5** ni cierra C1/C2
- API health público (M4): `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md` · plan **0/5**
- CI gates (D7): **RESUELTO** (#88) — spec `2026-08-04-audit-platform-ci-gates-design.md`
- Portal afterListRows: spec/plan 2026-08-03 · **0/5** · depende tag Framework ≥ `v1.2.2` (publicado `v1.2.3`); AuthZ CRUD es independiente
- Facturapi invoicing: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` — frontera distinta (sin código `src/` aún)
- Estructura programa 12 puntos: `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md`
- Plan ejecutable punto 1 (post-spec): `docs/superpowers/plans/2026-08-07-crud-p01-authz-multi-canal.md` · PR draft #95
- Evidencia histórica: `docs/archive/audits/auditoria_crud_engine_v0.1.md`, tag `archive/backoffice-api-integration` @ `4789f95` — **histórico**, no base de implementación

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `22a2053d7a06115a975dff3ce37167e54c030ab7` |
| SHA Portal inspeccionado | **No verificado** — `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404; `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository». Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-07` |
| Timestamp UTC | trigger cron `2026-08-07T12:10:00Z` / corrida agente `2026-08-07T12:10:00Z` / pase ux `2026-08-07T12:30:00Z` (modo **normal**) / pase deuda `2026-08-07T13:00:17Z` (modo **normal**) |
| Pase deuda | `2026-08-07T13:00:17Z` UTC; SHA `origin/main` `da3ab58bd77be95c4003341454d939aa2584a742`; modo **normal** |
| Nivel de fuente | **A** — PR abierto #96, título `docs(audit): auditoría técnica diaria 2026-08-07`, `baseRefName=main`, `mergeable=MERGEABLE`, `updatedAt=2026-08-07T12:04:11Z`. Verificaciones: `merge-base --is-ancestor origin/main 9ca422b` → exit 0; diff `origin/main...9ca422b` → único archivo `docs/audits/2026-08-07-auditoria-tecnica-diaria.md`; ningún commit legacy ancestro del head. |
| PR auditoría fuente | #96 — https://github.com/Parzival2103/Lebytek_Framework/pull/96 |
| headRefOid fuente | `9ca422b53a8cb803d3f6e4e3a064da73854cc659` (rama audit; no heredada) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | #96 audit 2026-08-07; #95 draft `fix(crud): AuthZ multi-canal C1+C2+C5 (p01)`; #94 docs invoicing plan restructure |

---

## Problema

La auditoría diaria 2026-08-07 eleva **6 críticos abiertos** (CRUD-C1…C6) tras merge de la auditoría crítica CRUD (#90). El patrón dominante es **AuthZ y mutación asimétricas**: el listado aplica filtros (`list.scope` / `list.scope_handler`) que show/edit/update/delete/acciones/reportes **no reaplican** de forma coherente; acciones ejecutables pueden omitir RBAC servidor; Reportes leen filas sin exigir `{resource}.ver`.

**Evidencia verificada en tip `main` @ `22a2053`:**

| ID | Comprobación | Resultado |
|----|--------------|-----------|
| C1 | `CrudScopeResolver::assertOwnedBy` L54–67 | Solo actúa si `list.scope.type === 'owner'`; con `list.scope_handler` custom retorna sin bloqueo |
| C1 | `CrudScopeResolver::resolve` L26–31 | Custom scope sí filtra listado vía handler registrado |
| C2 | `CrudActionService::run` L91–94, `runBulk` L153–156 | `verificar()` solo si `resolvePermission()` ≠ null — acciones sin `permission` ejecutan |
| C2 | `CrudConfigValidator` — bloque actions | No exige `permission` no vacío en actions `handler`/`transition` |
| C5 | `CrudReporteDataSource::rows` / `findRecord` L22–49 | Delega a `CrudDataService` sin gate `{prefix}.ver` al inicio |
| C5 | Tests AuthZ Reportes | **Ausentes** — `tests/Reporte/CrudReporteDataSourceAuthzTest.php` no existe en tip |
| C3–C6 | Código tip | Sin remediación — ver catálogo audit crítica § C3/C4/C6 |
| Semver plataforma | `composer.json`, `config/app.php`, `skeleton/config/app.php` | **`1.2.3`** sincronizado |
| Tag release vigente | `v1.2.3` @ `041e402` | Tip `main` docs/CI/schema-ahead sin bump nuevo |
| CI Actions | `.github/workflows/platform-tests.yml` | **Presente** — D7 resuelto; tip green @ `22a2053` |
| Suites tip (audit #96) | `php tests/run.php` | **591 passed / 7 failed** (Integrations — MySQL ausente en agente); Crud **171/171** verde |
| PHP CLI en agente cloud spec | Ausente al inicio de corrida | No re-ejecutada suite completa aquí; evidencia tomada de audit #96 + inspección estática |

**Consecuencia operativa:** cualquier consumidor (Portal, CRM, skeleton tenant) que habilite CRUD Engine con `scope_handler` custom, acciones sin `permission` declarada, o reportes sobre recursos CRUD queda expuesto a IDOR, ejecución de acciones por autenticados sin slug RBAC, y exfiltración vía capa Reportes — **independientemente** de la futura defensa router-level (M3/G4).

**Deuda carry-forward registrada (fuera de alcance inmediato de este spec):** M3 router RBAC (spec/plan listos 0/5), M4 health API, M5 seeds `permisos.gestionar`, M6 gh Portal 404, M10 hueco audits 03–05, D6 skeleton.lebytek.com, C3/C4/C6 y puntos 2–12 del programa CRUD.

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

- **Contexto:** auditoría crítica CRUD (#90) inventarió 46 hallazgos (6C/16G/20M/4B). La cadena diaria 2026-08-07 confirma tip `main` aún vulnerable y prioriza remediación por lotes (estructura 12 puntos). PR draft #95 implementa punto 1; este spec fija el diseño contractual antes de merge.
- **Propósito:** cerrar tres canales AuthZ **independientes** del router (C1 scope por ID, C2 permission obligatoria en acciones, C5 gate Reportes) como primer lote; delimitar frontera con M3 y con puntos 2–12 sin mezclar entregables.
- **Restriciones:** solo plataforma Framework (`src/`, configs harness, skeleton espejo); sin negocio Portal; legacy `archive/backoffice-api-integration` solo evidencia histórica; operaciones VPS/producción fuera de automation desatendida; mensaje IDOR existente «El registro solicitado no existe.» debe conservarse; fail-closed alineado con `RbacService::verificar` / `AccesoException`.
- **Éxito Framework (punto 1):** `assertOwnedBy` reaplica owner **y** condiciones de `scope_handler`; validator rechaza configs sin `permission` en actions ejecutables; runtime fail-closed en `CrudActionService`; `CrudReporteDataSource` exige `{prefix}.ver`; tests TDD rojo→verde; tag **`v1.2.6`** post-merge (reservas: M4 → `1.2.4`, M3 → `1.2.5`).
- **Éxito consumidor:** bump `composer.lock` a tag ≥ `v1.2.6` para recibir parche AuthZ; sin cambio de contrato JSON salvo configs inválidas que hoy cargan y deben fallar validación (C2).

### Enfoques evaluados

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — Remediación por capa Application (recomendado)** | Unificar bloqueo ID en `CrudScopeResolver`; validator + runtime fail-closed en actions; gate `.ver` en datasource Reportes | Cierra C1/C2/C5 en el punto correcto de la onion; tests unitarios existentes extensibles; independiente de M3 | No cierra C3/C4/C6 ni G4 router; requiere tag semver y bump consumidores |
| **B — Solo middleware router RBAC (M3/G4)** | `CrudRbacMiddleware` en rutas | Defensa temprana 403 | **No cierra C5** (Reportes no pasa por rutas CRUD); **no reaplica scope_handler** en acceso por ID; acciones POST siguen en servicio |
| **C — Documentar riesgo y deshabilitar `scope_handler`/actions sin permission** | Policy ops: prohibir configs inseguras | Cero diff código | No remedia tip; consumidores existentes siguen vulnerables; incumple auditoría |

**Recomendación:** **A** como lote 1 (C1+C2+C5). **Rechazar B** como sustituto de A (complementario en punto 6 del programa). **Rechazar C** como solución final.

### Esbozo del diseño (punto 1 — C1+C2+C5)

```
POST /admin/crud/{resource}/{id}/accion/{action}
  → AuthMiddleware
  → CrudController → CrudActionService::run
       → [C2] resolvePermission() null → AccesoException (fail-closed)
       → load record
       → [C1] CrudScopeResolver::assertOwnedBy
            → resolve(scope_handler|owner) + recordMatchesConditions(record, conditions)
            → fail → ValidationException «El registro solicitado no existe.»
       → dispatch handler

GET Reportes → CrudReporteDataSource::rows|findRecord
  → [C5] assertCanView(definition, $can) → exige {prefix}.ver vía $can / AccesoException
  → CrudDataService (scope list existente)
```

**Componentes tocados (Framework, punto 1):**

| Componente | Capa | Cambio |
|------------|------|--------|
| `CrudScopeResolver` | Application | `assertOwnedBy` vía `resolve()` + `recordMatchesConditions()` estático |
| `CrudConfigValidator` | Application | `permission` obligatorio en actions `handler`/`transition` |
| `CrudActionService` | Application | Fail-closed si permiso resuelto es null en ejecutables |
| `CrudReporteDataSource` | Application | Gate `{prefix}.ver` al inicio de `rows`/`findRecord` |
| Tests Security/Crud/Reporte | Tests | Casos C1/C2/C5 — ver § Tests TDD |

**Programa posterior (fuera de este spec, referencia):** punto 2 C3+G15+G6 (states/form), 3 C6 uploads, 4 C4 CAS/bulk, 6 G4 router RBAC (spec M3), etc. — ver estructura 12 puntos.

---

## Comportamiento esperado

### Punto 1 — C1 (scope_handler / owner)

1. Recurso con `list.scope_handler` custom: listado filtra como hoy.
2. Show/edit/update/delete/acción por ID: `assertOwnedBy` evalúa las **mismas condiciones** que el scope aplicaría al registro (owner built-in **o** condiciones del handler).
3. Registro fuera de scope → `ValidationException` con mensaje **exacto** «El registro solicitado no existe.» (sin revelar existencia).
4. Bypass owner (`bypass_permission`) sigue aplicando antes del chequeo de condiciones.

### Punto 1 — C2 (permission en actions)

1. Toda action `handler` o `transition` en JSON CRUD debe declarar `permission` no vacío — validator rechaza carga si falta.
2. En runtime, `CrudActionService::run` / `runBulk`: si action es ejecutable y `resolvePermission()` es null → `AccesoException` con mensaje alineado a `RbacService::verificar` («No tienes permiso para realizar esta acción: {slug}») **antes** de mutar.
3. Actions tipo `link` / navegación no ejecutables en servidor — sin cambio.

### Punto 1 — C5 (Reportes)

1. `CrudReporteDataSource::rows` y `findRecord` invocan gate privado que exige `$can !== null` y `$can("{prefix}.ver") === true`.
2. Denegación → `AccesoException` (controlador Reportes traduce a 403 coherente).
3. Firmas públicas `ReporteDataSourceInterface` / `ReporteRecordSourceInterface` **sin cambio** — gate interno.

### Tests TDD (pre-implementación — deben fallar por motivo previsto)

| Test | Ubicación | Fallo esperado pre-fix |
|------|-----------|------------------------|
| Custom `scope_handler` — ID fuera de scope bloqueado en `assertOwnedBy` | `tests/Security/CrudActionOwnershipTest.php` | Pasa hoy (bug) — test nuevo debe **fallar** hasta fix C1 |
| Action sin `permission` rechazada en validator | `tests/Crud/Action/CrudConfigValidatorActionsTest.php` | Config inválida aceptada hoy |
| Action sin `permission` rechazada en runtime | `tests/Crud/Action/CrudActionPermissionTest.php` | **Archivo ausente** — crear; rojo hasta fail-closed C2 |
| Reportes sin `.ver` denegado | `tests/Reporte/CrudReporteDataSourceAuthzTest.php` | **Archivo ausente** — crear; rojo hasta gate C5 |
| `recordMatchesConditions` operadores | `tests/Crud/Scope/CrudScopeRecordMatchesConditionsTest.php` | **Archivo ausente** — soporte C1 |

**Nota:** en tip `main`, los tres últimos archivos de test **no existen** — cumple contrato «descubrir al menos un test y fallar por motivo previsto».

---

## Alcance

### Requisitos Framework (`Parzival2103/Lebytek_Framework`, base `main`) — punto 1

| ID | Requisito | Capa / ruta |
|----|-----------|-------------|
| F1 | C1 — `assertOwnedBy` reaplica scope custom vía `resolve()` + condiciones | `src/Application/Services/CrudScopeResolver.php` |
| F2 | C1 — `recordMatchesConditions()` público estático (operadores `CrudListContext::ALLOWED_OPS`) | mismo archivo |
| F3 | C2 — validator exige `permission` en actions ejecutables | `src/Application/Services/CrudConfigValidator.php` |
| F4 | C2 — fail-closed runtime en `run`/`runBulk` | `src/Application/Services/CrudActionService.php` |
| F5 | C5 — gate `{prefix}.ver` en datasource Reportes | `src/Application/Reporte/CrudReporteDataSource.php` |
| F6 | Tests TDD C1/C2/C5 + regresión Security/Crud/Reporte | `tests/Security/`, `tests/Crud/`, `tests/Reporte/` |
| F7 | Doc § AuthZ multi-canal en módulo CRUD | `docs/modules/crud/modulo-crud-engine.md` |
| F8 | Tag semver patch **`v1.2.6`** + trío versión sincronizado | `composer.json`, `config/app.php`, `skeleton/config/app.php` |

### Requisitos Framework — programa C3–C6 (diseño referenciado, implementación posterior)

| ID | Tema | Punto programa | Spec/plan |
|----|------|----------------|-----------|
| C3 | States editables / demo toggle | p02 | Plan futuro `crud-p02-states-form-options` |
| C4 | Transitions CAS / TOCTOU | p04 | Plan futuro `crud-p04-cas-bulk-equality` |
| C6 | Uploads allowlist + `public_path` | p03 | Plan futuro `crud-p03-uploads-hardening` |
| G4 | Router RBAC | p06 / M3 | Spec 2026-08-06 |

### Requisitos Portal (`Parzival2103/Lebytek_Portal`, base `main`) — **no verificados (M6)**

| ID | Requisito | Notas |
|----|-----------|-------|
| P1 | Tras release Framework F1–F8, bump `composer.lock` a tag ≥ `v1.2.6` | Lock actual no inspeccionable |
| P2 | Recursos CRUD Portal con `scope_handler` o actions custom deben auditar JSON post-validator C2 | **No verificado** |
| P3 | Reportes Portal sobre recursos `dom_*` heredan gate C5 tras bump | **No verificado** |
| P4 | QA IDOR/actions/reportes en staging Portal | Manual ops |

### Requisitos Ops / staging

| ID | Requisito | Entorno |
|----|-----------|---------|
| O1 | Smoke: usuario sin `{prefix}.ver` no obtiene filas Reportes CRUD | Staging |
| O2 | Smoke: usuario fuera de scope custom no show/edit por ID conocido | Staging |
| O3 | Ejecutar suite completa con MySQL (Integrations) post-merge | CI `platform-integration-gates` |

### Operaciones producción

**Fuera de esta corrida desatendida.** Tag `v1.2.6`, bump Portal lock, deploy VPS y QA en producción requieren operador con acceso M6.

---

## No-alcance

- C3, C4, C6 y hallazgos G/M/B fuera de C1/C2/C5 — puntos 2–12 del programa.
- M3 `CrudRbacMiddleware` router — spec 2026-08-06 (complementario, no sustituto).
- M4 `/api/health`, M5 seeds, M10 automation hueco, D6 skeleton deploy.
- Portal Marketing, `LebytekApiClient`, SQL `dom_*`, checkout membresía.
- Eliminar checks RBAC existentes en `CrudResourceService` (defensa doble deseada).
- Merge `feature/backoffice-api-integration` → `main`.
- Implementación Facturapi (`docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`).
- Código legacy monolith como contrato vigente.
- Remediación CRUD-C3, C4, C6 y puntos 2–12 del programa (specs/planes futuros).
- Cierre ops D6 (`skeleton.lebytek.com`), M10 (audits 03–05 omitidos), M5 seeds, M4 health — backlog verificado fuera de F1–F8.
- Auto-fix Payments/Stripe en `src/` o harness — requisitos documentados como gates Portal (D14).
- Branch protection GitHub required checks — ops manual post-D7 (#88).

---

## Ownership map

| Entregable | Repositorio | Rama base | Consumidor semver |
|------------|-------------|-----------|-------------------|
| F1–F8 AuthZ multi-canal punto 1 | `Lebytek_Framework` | `main` | Tag **`v1.2.6`** |
| P1–P4 bump + QA Portal | `Lebytek_Portal` | `main` | Post-tag — **no verificado** |
| O1–O3 staging / CI | Ops / GitHub Actions | staging / CI | Manual / automatizado |
| Puntos 2–12 CRUD | `Lebytek_Framework` | `main` | Tags incrementales post-1.2.6 |
| Producción VPS | Ops | prod | **Prohibido** en automation |

**Contratos públicos ausentes (no asumir):**

- No existe hoy garantía de que `assertOwnedBy` cubra `scope_handler` — consumidores no deben asumir paridad listado/show hasta tag ≥ `v1.2.6`.
- No existe gate `{resource}.ver` en `CrudReporteDataSource` — Reportes CRUD no equivalente a permiso `.ver` del recurso hasta tag ≥ `v1.2.6`.
- Actions sin `permission` pueden ejecutarse hoy si pasan CSRF — **no** asumir fail-closed hasta tag ≥ `v1.2.6`.
- Portal CRUD JSON — **no verificado** en repo privado (M6).
- Legacy (`archive/backoffice-api-integration`) mezclaba RBAC en servicio sin scope_handler documentado — **histórico**.

---

## Dependencias y compatibilidad

| Dependencia | Impacto |
|-------------|---------|
| `CrudHandlerRegistry` | C1 resuelve handlers custom — sin cambio de registro |
| `CrudListContext::ALLOWED_OPS` | C1 reutiliza operadores existentes |
| `RbacService` / `AccesoException` | C2/C5 alinean mensajes |
| `ReporteDataSourceInterface` | Firmas intactas; gate interno C5 |
| Tag `v1.2.3` (actual release) | Vulnerable C1/C2/C5 |
| Tags reservados `1.2.4` (M4), `1.2.5` (M3) | Orden release: health/router pueden publicarse antes; AuthZ CRUD → **`1.2.6`** |
| PR draft #95 | Implementación esperada de F1–F8 — revisar contra este spec |
| CI D7 | F6 debe pasar en `platform-fast-gates` + integration job |
| `composer validate` content-hash | Hygiene — `composer update --lock` en release train; CI usa `--no-check-lock` |

**Migración segura:**

- **Skeleton nuevo:** incluye validator C2 desde origen; configs demo ya con `permission` — verificar espejo `skeleton/config/cruds/`.
- **Harness / tenant existente:** configs CRUD con actions sin `permission` **dejarán de cargar** (C2 validator) — corregir JSON antes de bump o en mismo release train.
- **Portal existente:** P1 bump lock; P2 auditar JSON CRUD; sin SQL plataforma.
- **Base nueva vs existente:** mismo parche semver; diferencia es calidad de configs consumidor.

**Semver / release Framework:**

- Endurecimiento AuthZ + rechazo configs inseguras previamente toleradas → **PATCH** acumulado **`v1.2.6`** para lote C1+C2+C5.
- Publicar tag tras merge F1–F8; sincronizar trío versión.
- Portal/CRM consumen vía `composer update lebytek/framework` — **no** branch checkout en producción.

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| IDOR activo hasta tag `1.2.6` | **Alta** | Priorizar merge #95; comunicar bump consumidores |
| Configs Portal con actions sin `permission` rompen carga post-C2 | Media | P2 auditoría JSON; validator mensajes claros |
| Confundir M3 router con cierre C5 | Media | Este spec + audit #90 § prioridad; specs separados |
| Portal no bump lock (M6) | Media | P1 documentado; no asumir parche en prod |
| Regresión owner scope built-in | Media | Extender tests existentes `CrudActionOwnershipTest` |
| Doble gate RBAC (servicio + futuro middleware) | Baja | Comportamiento idempotente |
| Orden tags 1.2.4/1.2.5/1.2.6 | Baja | Coordinar en plans M4/M3/p01 |
| gh Portal 404 | Media | Marcar P1–P4 no verificados |
| Integrations locales sin MySQL | Baja | CI integration job cubre (D7 resuelto) |
| CRUD-C3 states editable / demo toggle | **Alta** | Punto 2 programa (#90); no sustituye C1/C2/C5 |
| CRUD-C4 transitions sin CAS / TOCTOU | **Alta** | Punto 4 programa; `CrudTransitionService::apply` L104–107 UPDATE sin compare-and-swap |
| CRUD-C6 uploads allowlist opcional / `public_path` | **Alta** | Punto 3 programa; `UploadValidator` L67–71 solo valida ext si array no vacío; `FileUploadService` L63 concatena `PUBLIC_PATH` |
| `composer validate` content-hash | Baja | CI usa `--no-check-lock`; hygiene en release train — **H1 no verificado** (Composer CLI ausente en agente deuda) |
| D7 ausencia CI (histórico) | — | **Mitigado** — PR #88; `.github/workflows/platform-tests.yml` + `CiWorkflowPresentTest` @ `da3ab58` |

---

## Rollback

1. Revertir PR F1–F8 — restaura comportamiento vulnerable actual (C1/C2/C5 abiertos).
2. Consumidores en `v1.2.6`: `composer require lebytek/framework:1.2.3` (o tag anterior estable).
3. Configs corregidas para C2 pueden revertirse si se downgrade validator — documentar en ops.
4. Sin migración SQL plataforma — rollback Git + redeploy + lock downgrade.
5. No yank automático de tag; publicar patch revert si necesario.

---

## Compatibilidad, UX y responsive

### Modo del pase: normal

Este spec cierra **AuthZ multi-canal en Application** (C1 scope por ID, C2 permission en actions,
C5 gate Reportes). Superficie UI verificable: respuestas **403** y **404** en show/edit/delete/acciones
CRUD, flash de error en listado/delete/action, pantalla Reportes admin cuando falta `{prefix}.ver`, y
mensajes de validación al cargar JSON CRUD inválido post-C2. No modifica layout login/dashboard ni
estilos globales; **M3 router RBAC** (spec 2026-08-06, plan 0/5) permanece carry-forward complementario.

### Compatibilidad (verificado vs carry-forward)

| Área | Este spec (F1–F8) | Evidencia / carry-forward |
|------|-------------------|---------------------------|
| PHP soportado | Sin cambio runtime | `composer.json` exige `>=8.1`; VPS documentado PHP **8.4.22** CLI/pool (`2026-07-26-skeleton-package-staging-design.md`) — compatible; `CrudScopeResolver`, `CrudActionService` y `CrudReporteDataSource` no requieren extensiones nuevas. |
| Instalación vía `vendor/` | Contrato paquete semver | Consumidores obtienen parche C1/C2/C5 tras bump `lebytek/framework` al tag **`v1.2.6`**; cambios viven en `src/Application/` — **no** parche en `vendor/`. Portal bump lock P1 (**no verificado** M6). |
| Health sin cookie de sesión | Carry-forward **M4** | `routes/api.php` L14–16 — grupo `/api` + `AuthMiddleware`; `/api/ping` requiere sesión. Smoke LB: usar `GET /api/health` cuando M4 mergee (spec 2026-08-05, plan **0/5**). |
| `.env.example` sin vars Portal | **Resuelto (M2)** — sin alcance | Root `.env.example` L55 remite vars `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` a Portal; AuthZ Application no introduce env vars nuevas. |
| Navegadores objetivo | Superficie admin CRUD + Reportes | Baseline `docs/core/ui_ux.md`: admin breakpoint **992px (`lg`)**. Chrome, Firefox, Safari, Edge últimas 2 versiones + iOS Safari ≥ 15; sin IE11. Páginas 403/404 y flash deben ser legibles en **320–768px** sin overflow horizontal. |

### UX — flujos admin CRUD y Reportes (F1–F8)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U1** | C1 IDOR: mensaje **exacto** «El registro solicitado no existe.» cuando registro fuera de `scope_handler`/owner — **sin** revelar existencia del ID | F1, AC-F1 |
| **U2** | C1 show/edit: `ValidationException` scope → `Response::notFound()` (404) — coherente con comportamiento actual `CrudController` L97–99, L119–120 | F1 |
| **U3** | C1 delete/action/bulk: mismo mensaje U1 vía flash redirect — operador entiende «registro no disponible», no error de permiso RBAC | F1, F4 |
| **U4** | C2 runtime: `AccesoException` con slug explícito alineado a `RbacService::verificar` («No tienes permiso para realizar esta acción: {slug}») **antes** de mutar — acción/bulk no ejecutan silenciosamente | F4, AC-F4 |
| **U5** | C2 validator: rechazo de JSON con action `handler`/`transition` sin `permission` incluye recurso, nombre de action y campo faltante — copy accionable para corregir `config/cruds/*.json` | F3, AC-F3 |
| **U6** | C5 Reportes: usuario sin `{prefix}.ver` recibe **403** coherente (HTML o JSON según controlador Reportes) — no filas vacías silenciosas ni exfiltración | F5, AC-F5 |
| **U7** | Distinción UX: **403** = permiso RBAC denegado; **404** = registro inexistente o fuera de scope (C1); **flash error** en index = recurso/config inválido — operador no confunde causas | F1–F5 |
| **U8** | Tests TDD F6: mensajes de fallo citan spec C1/C2/C5, archivo bajo test y acción («implementar fail-closed en `CrudActionService`», «gate `.ver` en `CrudReporteDataSource`») | F6 |
| **U9** | Doc F7 (`modulo-crud-engine.md` § AuthZ multi-canal): tabla scope listado vs show/edit/action vs Reportes — operador sabe qué capa protege cada flujo | F7, AC-F8 |

### UX — instalación y operaciones (sin cambio directo)

| Requisito | Criterio | Estado |
|-----------|----------|--------|
| **U10** | Configs CRUD con actions sin `permission` dejan de cargar post-C2 — doc/wizard indica revisar JSON **antes** de bump a `v1.2.6` (no regresión silenciosa) | P2 carry-forward |
| **U11** | Bump Framework fallido: mensaje CLI indica tag mínimo `v1.2.6` y acción («composer update lebytek/framework») | P1 carry-forward |

### Responsive — smoke en superficies tocadas

Referencia: `docs/core/ui_ux.md` §542 — breakpoint admin **992px (`lg`)**; tablas CRUD exigen `table-responsive` (`ui_ux.md` L222).

| Superficie | Verificación post-merge | Rango |
|------------|-------------------------|-------|
| Página 403 / 404 AuthZ | Mensaje legible; enlace «volver» usable; sin scroll horizontal; tap targets ≥44px | **320–768px** |
| Listado CRUD (usuario **con** permiso) | Sin regresión: `table-responsive` + scroll horizontal en columnas secundarias | **320–768px** |
| Formulario show/edit/action redirect | Flash error U1/U3 legible en móvil; no truncar slug en U4/U5 | **320–768px** |
| Reportes admin (usuario **con** `.ver`) | Tabla reporte con scroll horizontal; 403 C5 legible si permiso revocado | **320–768px** |
| Login / dashboard nav (sin alcance directo) | Carry-forward CF3–CF4 — smoke opcional post-merge | **320–768px** |

### Carry-forward UX — próximo spec con superficie UI más amplia

Ítems derivados de deuda abierta verificada; **C1/C2/C5 Application quedan cubiertos por este spec** en capa servicio — **no** sustituyen **CF6 (M3 router RBAC)**, que sigue en spec 2026-08-06 plan **0/5**. CF1–CF2 (semver harness + env purge) y D7 (CI #88) tampoco se arrastran.

| # | Ítem | Origen | Requisito concreto |
|---|------|--------|-------------------|
| CF3 | Login responsive 320–768px | `ui_ux.md` | `.ct-login-page`, `.ct-login-card` sin overflow horizontal; tap targets ≥44px; sin scroll lateral en 320px. |
| CF4 | Dashboard admin responsive 320–768px | layouts side/top/bottom | Nav colapsable; KPI grid legible; topbar sin solapamiento de acciones. |
| CF5′ | Tablas CRUD restantes | módulo CRUD, D6 | `table-responsive` + `list.columns[].priority` en recursos distintos de `mkt_leads`; toolbar móvil. |
| CF6 | RBAC router CRUD/calendario | M3 | `CrudRbacMiddleware` en rutas; 403 temprano con slug — spec 2026-08-06 (**0/5**); complementario a C1/C2/C5. |
| CF7 | Health endpoint público | M4 | `GET /api/health` 200 sin cookie; body `{ "status": "ok" }` — spec 2026-08-05 (**0/5**). |
| CF8 | Permisos admin catálogo | M5 | Slug `permisos.gestionar` en seeds; UI permisos sin workaround `administracion.ver`. |
| CF9 | Estados vacío / error / carga (global) | `ui_ux.md` §8 | Unificar empty states en CRUDs sin hook; spinners list; validación con hint de corrección — más allá de U7. |
| CF10 | Copy errores accionables (transversal) | transversal | Auth, wizard install, CRUD save: qué falló + qué hacer — extiende U4/U5 fuera de AuthZ Application. |

---

## Criterios de aceptación

### Framework (punto 1 — bloqueante release `v1.2.6`)

- [ ] **AC-F1:** Test custom `scope_handler` — registro fuera de condiciones → `ValidationException` «El registro solicitado no existe.» en `assertOwnedBy`.
- [ ] **AC-F2:** Test owner built-in existente sigue verde (sin regresión).
- [ ] **AC-F3:** Validator rechaza JSON action `handler`/`transition` sin `permission`.
- [ ] **AC-F4:** Runtime `CrudActionService` lanza `AccesoException` si ejecutable sin permiso resuelto.
- [ ] **AC-F5:** `CrudReporteDataSource` deniega `rows`/`findRecord` sin `{prefix}.ver`.
- [ ] **AC-F6:** `php tests/run.php Security Crud/Scope Crud/Action Reporte` — verde (entorno con deps).
- [ ] **AC-F7:** Trío semver `1.2.6` sincronizado; tag `v1.2.6` publicado post-merge.
- [ ] **AC-F8:** Doc módulo CRUD actualizada § AuthZ multi-canal.

### Portal — **no verificables en esta corrida (M6)**

- [ ] **AC-P1:** `composer.lock` referencia `lebytek/framework` ≥ `v1.2.6` — **no verificado**.
- [ ] **AC-P2:** Staging QA C1/C2/C5 sobre recurso `dom_*` — **no verificado**.

### Ops / staging

- [ ] **AC-O1:** CI verde en PR implementación (#95 o sucesor).
- [ ] **AC-O2:** Smoke O1–O2 ejecutado en staging con operador humano.

### Producción

- [ ] **AC-PROD:** Explícitamente **fuera** de automation desatendida.

### Deuda técnica (inventario)

- [ ] **AC-D1:** Sección **Deuda técnica** lista abiertos verificados (CRUD-C1/C2/C5 alcance, CRUD-C3/C4/C6, M3–M5, D6, M10) con evidencia ruta/línea en `main` @ `da3ab58`.
- [ ] **AC-D2:** D7, M1, M2, M7, M8, M9, D1–D5, D13, C1–C3 históricos reconciliados como **resueltos** o **re-scopeados**; no re-listados como abiertos.
- [ ] **AC-D3:** P1–P4, M6/D3, D14, D15, H1 marcados **no verificados**; acción concreta documentada.
- [ ] **AC-D4:** Verificado sin deuda nueva — 3 migraciones ↔ 3 entradas manifiesto (`config/modules/{core,crud-engine,pdf-kit}.php`); `src/` sin `TODO`/`FIXME` impacto; capas OK; referencias operativas vivas a `feature/backoffice-api-integration` ausentes en `scripts/`, `docs/composer-setup.md`, `docs/integration/`; `despliegue-y-versionado.md` § CI presente (D7 cerrado).

### Compatibilidad, UX y responsive

- [ ] **AC-UX1:** Sección **Compatibilidad, UX y responsive** declara modo **normal** con requisitos K/U/R verificables para F1–F8 (AuthZ Application CRUD + Reportes).
- [ ] **AC-UX2:** Requisitos U1–U9 (mensaje IDOR exacto, 404 vs flash, slug en AccesoException, validator accionable, gate Reportes, distinción 403/404/flash, hints test gate, doc multi-canal) incluidos como criterios del spec.
- [ ] **AC-UX3:** Carry-forward CF3–CF4, CF5′, CF6, CF7–CF10 documentado; CF6 **no** cubierto por este spec (M3 router complementario); CF1–CF2 y D7 no arrastrados (resueltos).
- [ ] **AC-UX4:** Smoke responsive en **320–768px** para 403/404/flash AuthZ y tablas CRUD/Reportes accesibles post-implementación (sin regresión `table-responsive`).

---

## Requisitos marcados como no verificados

| ID | Motivo |
|----|--------|
| P1–P4 | M6 — `gh` no resuelve `Lebytek_Portal` (HTTP 404 / GraphQL) |
| Portal lock ≥ `v1.2.3` / ≥ `v1.2.6` | Sin acceso repo consumidor |
| Issues abiertos Portal | M6 |
| Re-ejecución suite completa en agente spec | PHP CLI ausente al inicio de corrida spec |
| `composer validate` content-hash en tip | No re-ejecutado en spec; documentado en audit #96 |

---

## Deuda técnica

Fuente: auditoría `docs/audits/2026-08-07-auditoria-tecnica-diaria.md` (PR #96 @ `9ca422b`, mergeado `da3ab58`); audit crítica `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (#90); reconciliación con inventario spec `2026-08-06-audit-crud-rbac-router-design.md` (pase deuda @ `ddc55ec`) y tip `origin/main` @ `da3ab58` (pase deuda 2026-08-07).

### Reconciliación heredada (cerrados)

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| **D7** | Sin pipeline GitHub Actions | **Resuelto** | PR #88 — `.github/workflows/platform-tests.yml` presente; `tests/Docs/CiWorkflowPresentTest.php` @ `da3ab58`; `docs/core/despliegue-y-versionado.md` L225 § CI; tip Actions green (audit #96 run `31150028471`) |
| **M1** | Sync semver trío | **Resuelto** | #74 + `v1.2.3` @ `041e402`; `composer.json` L6, `config/app.php` L7, `skeleton/config/app.php` L7 → `1.2.3` @ `da3ab58`; `PlatformVersionSemverTest` presente |
| **M2** | `.env.example` Portal vars | **Resuelto** | #62 — root `.env.example` L55 comentario explícito; keys activas `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` = **0** |
| **M9** | dompdf advisories | **Resuelto** | #74 — `composer.lock` fija `dompdf/dompdf` **v3.1.6**; `DompdfSecurityVersionTest` presente |
| **D1–D5, D13** | Semver harness / env / checklist | **Resuelto** | #62 + #74 — ver M1/M2 |
| **M7/M8** | Audit lifecycle / ops docs | **Resuelto** | PRs #54–#67, #56/#57 + `OpsDocsFpsAlignmentTest` |
| **C1 (2026-07-27)** | Scripts `vps-deploy-*` destructivos | **Resuelto** | PR #36; `DeployScriptsRemovedTest` verde |
| **C2 (2026-07-27)** | Stripe subscription (Framework) | **Resuelto** Framework | PR #42 + tags `v1.2.1`…`v1.2.3`; `vertical.payments=false` @ `config/vertical.php` L22 — QA Portal **no verificado** (M6) |
| **C3 (2026-07-27)** | Bootstrap marketing Portal | **Re-scopeado** | `Lebytek_Portal#4` — no inspeccionable aquí |

**Cierres desde corrida anterior (2026-08-06 pase deuda @ `ddc55ec`):** **1** — **D7** (#88 CI platform-tests). Intervalo `ddc55ec..da3ab58` en `main` añadió audit crítica CRUD (#90), plan p01 (#93), audit diaria #96 y docs invoicing — sin código que cierre CRUD-C1…C6 ni M3–M6/D6.

### Alcance principal de este spec (CRUD-C1 + C2 + C5 — abierto, verificado)

| ID | Hallazgo | Evidencia (`main` @ `da3ab58`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **CRUD-C1** | IDOR con `list.scope_handler` custom | `CrudScopeResolver.php` L28–31 `resolve()` usa handler; L54–67 `assertOwnedBy()` solo `ownerMeta()` — retorna sin bloqueo si no hay owner scope | Show/edit/update/delete/acciones por ID fuera del filtro listado | `Application` | Framework | F1–F2 + tests; plan `2026-08-07-crud-p01-authz-multi-canal.md` / PR draft #95 |
| **CRUD-C2** | Action sin `permission` ejecuta sin RBAC servidor | `CrudActionService.php` L91–94, L153–156 — `verificar()` solo si `resolvePermission()` ≠ null; `CrudConfigValidator.php` no exige `permission` en actions `handler`/`transition` | Cualquier autenticado puede ejecutar acciones POST sin slug RBAC | `Application` | Framework | F3–F4 + tests; PR #95 |
| **CRUD-C5** | Reportes sin gate `{prefix}.ver` | `CrudReporteDataSource.php` L22–38 `rows`, L42–49 `findRecord` — delega a `CrudDataService` sin gate `.ver`; `tests/Reporte/CrudReporteDataSourceAuthzTest.php` **ausente** | Exfiltración vía capa Reportes aunque listado CRUD esté protegido | `Application` | Framework | F5 + test gate; PR #95 |

### Backlog Framework verificado (fuera alcance F1–F8)

| ID | Hallazgo | Evidencia (`main` @ `da3ab58`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **CRUD-C3** | Columna states editable por form (+ demo toggle) | Audit #90 § C3 — `demo_pedidos.json` declara `states.column`; validator no prohíbe colisión con `form.fields`; `DemoProductoToggleStatusHandler` bypass transitions | State machine bypass; escritura directa de status | `Application` / config CRUD | Framework | Programa punto 2 (plan p02 futuro) |
| **CRUD-C4** | Transitions sin CAS / TOCTOU | `CrudTransitionService.php` L104–107 — `updateRecord` de columna estado sin compare-and-swap | Carreras concurrentes en transiciones | `Application` | Framework | Programa punto 4 |
| **CRUD-C6** | Uploads allowlist opcional + `public_path` sin normalizar | `UploadValidator.php` L67–71 — deny ext solo si `allowed_extensions` no vacío; `FileUploadService.php` L63 — `PUBLIC_PATH . '/' . $publicRelative` sin jail | Escritura disco / XSS stored (SVG) | `Application` / `Infrastructure` | Framework | Programa punto 3 |
| **M3** | CRUD/Calendario sin `RbacMiddleware` router | `routes/web.php` L114–125 — rutas `/crud/{resource}*`, `/calendario/{key}*` sin middleware RBAC; contraste L127+ (pdf-kit/reportes sí); `skeleton/routes/web.php` espejo; `CrudRbacRouterTest` **ausente** | 403 inconsistente HTML/JSON; defensa router ausente | `Presentation` / `routes/` | Framework | Plan `2026-08-06-audit-crud-rbac-router.md` (**0/5**); tag reservado `1.2.5` — **complementario** a C5 |
| **M4** | API `/api/*` autenticada; sin health público | `routes/api.php` L14–16 grupo `AuthMiddleware`; L23 `/api/ping` dentro del grupo; `rg '/health' routes/` → **0**; `HealthController.php` sin método `health()` | LB/cron no liveness sin cookie | `Presentation` / `routes/` | Framework | Plan `2026-08-05-audit-api-health-public.md` (**0/5**); tag `1.2.4` |
| **M5** | Slug `permisos.gestionar` ausente | `routes/web.php` L61–65 — workaround `administracion.ver`; `rg permisos.gestionar database/` → **0** | Catálogo RBAC acoplado a permiso amplio | `Domain` RBAC | Framework | Spec futuro CF8 |
| **D6** | Plan `skeleton.lebytek.com` sin implementar | `docs/ENVIRONMENTS.md` L7, L13, L34, L43 — «skeleton.lebytek.com pendiente»; plan `2026-07-26-skeleton-package-staging.md` Tasks 2–10 sin deploy | LAB package puro no desplegado | Ops / Framework | Framework/Ops | Ejecutar plan humano Tasks 6–8 |
| **M10** | Hueco auditorías 2026-08-03..05 | `docs/audits/` sin `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`; cadena 06–07 restaurada | Specs diseñados sin ancla audit esos días | Proceso automation | Ops/automation | Monitorizar paso 00; no recupera días omitidos |

### Planes activos — estado ejecución real

| Plan | Tareas | Estado @ `da3ab58` |
|------|--------|-------------------|
| `docs/superpowers/plans/2026-08-07-crud-p01-authz-multi-canal.md` | 0/5+ | Pendiente — PR draft #95 (C1+C2+C5) |
| `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` | 0/5 | Pendiente — M3 router |
| `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` | 0/5 | Pendiente — M4 health |
| `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md` | 0/5 | Pendiente — requiere Portal (M6) |
| `docs/superpowers/plans/2026-08-04-audit-platform-ci-gates.md` | — | **Implementado** (#88) — plan archivado / fuera de tip |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ `v1.2.3` / ≥ `v1.2.6` post-AuthZ | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL fail; última evidencia `composer.lock` Portal con `lebytek/framework` **v1.1.0** @ `a79d3ad` | Bump lock tras tag `v1.2.6`; confirmar cuando M6 resuelva |
| **P2** | Portal CRUD JSON post-validator C2 | Repo Portal inaccesible | Auditar actions sin `permission` antes de bump |
| **P3** | Portal Reportes heredan gate C5 | Repo Portal inaccesible | QA staging O1 post-bump |
| **P4** | QA IDOR/actions/reportes staging Portal | Manual ops | O1–O2 con operador humano |
| **M6 / D3** | Portal SHA / `composer.lock` | `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 | Ops: conceder lectura Portal al token automation |
| **D14** | Stripe subscription QA Portal | Repo Portal inaccesible; Framework `vertical.payments=false` | Portal: QA checkout antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` |
| **D15** | Bootstrap marketing Portal | Re-scopeado `Lebytek_Portal#4` — no inspeccionable aquí | Portal issue #4 |
| **H1** | `composer validate` lock content-hash | Composer CLI **ausente** en agente cloud deuda; audit #96 documenta exit 2 content-hash; CI usa `--no-check-lock` | `composer update --lock` en release train humano; no reabre M1 |

### Verificado sin deuda nueva

- **Migraciones ↔ manifiesto:** 3 archivos en `database/migrations/` ↔ 3 entradas en `config/modules/core.php` L16, `crud-engine.php` L15, `pdf-kit.php` L16 — sin drift.
- **`src/`:** grep `TODO`/`FIXME` → **0** con impacto; sin `LebytekApiClient` ni Marketing.
- **Capas:** hook `afterListRows` en `Application`; Domain sin deps Presentation/Infrastructure.
- **Legacy operativo:** referencias vivas a `feature/backoffice-api-integration` o `dev-feature/backoffice-api-integration` **ausentes** en `scripts/`, `docs/composer-setup.md`, `docs/integration/`.
- **Payments bootstrap:** `vertical.payments=false` en harness; requisitos Stripe documentados como gate ops Portal (D14), no auto-fix en `src/`.
- **Semver/dompdf:** trío `1.2.3`; lock dompdf `v3.1.6` — sin regresión M1/M9.
- **CI (D7):** workflow + test gate presentes; § CI en `despliegue-y-versionado.md` L225 — no drift doc↔código post-#88.
- **Facturapi invoicing:** solo diseño en `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` — sin código `src/` aún; bump PHP ≥8.2 es requisito del spec futuro, no deuda verificada hoy.

**Conteo:** **11 abiertos verificados** (CRUD-C1/C2/C5 alcance + CRUD-C3/C4/C6 + M3/M4/M5/D6/M10 backlog); **7 no verificados** (P1–P4, M6/D3, D14, D15, H1); **1 heredado cerrado** esta corrida (**D7**).

---

*Design-only. Ningún archivo de código, config producto, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
