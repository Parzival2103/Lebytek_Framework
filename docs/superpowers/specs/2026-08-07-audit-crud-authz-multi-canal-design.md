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
| Timestamp UTC | trigger cron `2026-08-07T12:10:00Z` / corrida agente `2026-08-07T12:10:00Z` |
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

---

## Rollback

1. Revertir PR F1–F8 — restaura comportamiento vulnerable actual (C1/C2/C5 abiertos).
2. Consumidores en `v1.2.6`: `composer require lebytek/framework:1.2.3` (o tag anterior estable).
3. Configs corregidas para C2 pueden revertirse si se downgrade validator — documentar en ops.
4. Sin migración SQL plataforma — rollback Git + redeploy + lock downgrade.
5. No yank automático de tag; publicar patch revert si necesario.

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

*Design-only. Ningún archivo de código, config producto, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
