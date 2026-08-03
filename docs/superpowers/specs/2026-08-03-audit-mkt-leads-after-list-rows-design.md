# Design: Enriquecimiento listado `mkt_leads` vía hook `afterListRows` (Portal)

**Fecha:** 2026-08-03  
**Repo spec:** `Parzival2103/Lebytek_Framework` (artefacto de diseño; implementación Portal en `Lebytek_Portal`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel C)

**Auditoría fuente:** `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (mergeada en `main` vía PR #67 @ `d372ad8`)  
**Estado post-audit verificado en tip `main`:** M1 semver y M9 dompdf **resueltos** (#74 + bump `v1.2.3` @ `041e402`); hook `afterListRows` publicado (#66, tag `v1.2.2`+). El hallazgo accionable restante es **consumo Portal** (P1).

**Specs/planes relacionados:**

- Framework release integrity (implementado): `docs/superpowers/specs/2026-08-02-audit-v122-release-integrity-design.md` · plan `docs/superpowers/plans/2026-08-02-audit-v122-release-integrity.md` (Tasks 1–4 completadas #74)
- Plan Portal hermano: `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md`
- Contratos integración: `docs/integration/waapi-api-contract.md`, `docs/integration/role-delegation-lebytek-api.md`
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `041e402d404bf4c398d0866776b03614db0be8d4` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-03` |
| Timestamp UTC | trigger cron `2026-08-03T12:10:00Z` / corrida agente `2026-08-03T12:15:00Z` |
| Nivel de fuente | **C** — no hubo auditoría del día 2026-08-03; reporte más reciente en `origin/main`: `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (fecha real del reporte: 2026-08-02, merge PR #67). Nivel A: `gh pr list --search "docs(audit):" --state open --base main` → vacío. Nivel B: rama `origin/automation/audit-2026-08-02` existe pero `origin/main` no es ancestro de su head (reporte ya mergeado por camino distinto) → rechazada. |
| PR auditoría fuente | #67 — mergeado 2026-08-02; head histórico `a8331573ec94d65621dd77512ec7ccaf522af035` |
| headRefOid fuente | `a8331573ec94d65621dd77512ec7ccaf522af035` (rama audit; no heredada) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head de auditoría |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |

---

## Problema

La auditoría del 2026-08-02 identificó que Framework publicó la capacidad CRUD `afterListRows` (#66, tag `v1.2.2`) para permitir que consumidores enriquezcan filas de listado **después** de la query SQL y **antes** del formateo de tabla. El caso de uso documentado en el commit es el listado admin de `mkt_leads` en Portal con estado WhatsApi/tenant en vivo.

**Estado verificado en tip `main` @ `041e402` (post-audit):**

| ID | Hallazgo audit 2026-08-02 | Estado hoy |
|----|--------------------------|------------|
| **M1** | Regresión semver post-`v1.2.2` | **RESUELTO** — tres fuentes `1.2.3`; tag `v1.2.3` @ `041e402` |
| **M9** | dompdf v3.1.5 advisories | **RESUELTO** — lock `v3.1.6`; `DompdfSecurityVersionTest` presente |
| **F-hook** | Hook `afterListRows` genérico | **PUBLICADO** — `CrudListRowsContext`, `CrudResourceService::buildIndexData`, tests Crud 171/171 |
| **P1** | Portal no consume el hook | **ABIERTO / no verificable** — M6 impide inspeccionar `Lebytek_Portal`; última evidencia: framework `v1.1.0` |

**Consecuencia:** el admin de leads en Portal sigue mostrando solo columnas SQL persistidas (`api_lifecycle_status`, etc.) sin estado en vivo de instancia/tenant ni columnas virtuales enriquecidas. La capacidad Framework existe pero no tiene consumidor desplegado.

**Contexto positivo (no reabrir):** M2 `.env.example` resuelto (#62); fronteras FPS intactas (hook en `src/` sin `mkt_*` ni `LebytekApiClient`); C2 Stripe Framework resuelto; WhatsApi @ `f3f3ec7` accesible vía gh.

**Deuda carry-forward registrada (fuera de alcance inmediato):** M3 (CRUD RBAC router), M4 (API sesión), M5 (`permisos.gestionar` seeds), M6 (gh Portal 404), D6 (`skeleton.lebytek.com`), D7 (CI GitHub Actions).

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

- **Propósito:** diseñar la implementación Portal que consume `afterListRows` para enriquecer `/admin/crud/mkt_leads` con estado WhatsApi y actividad tenant, usando el contrato HTTP documentado contra `api.lebytek.com`.
- **Restricciones:** negocio Marketing vive en `Lebytek_Portal`; no editar `vendor/lebytek/framework`; Portal requiere `lebytek/framework` ≥ `v1.2.2` (recomendado `v1.2.3`); staging Portal inexistente (`docs/ENVIRONMENTS.md`); legacy `archive/backoffice-api-integration` solo evidencia histórica.
- **Éxito:** `composer.lock` Portal referencia framework ≥ `v1.2.2`; handler registrado; JSON CRUD declara hook + columnas `"virtual": true`; listado admin muestra `wa_estado` y `tenant_actividad` con degradación si API falla; test gate Portal rojo→verde.

### Enfoques evaluados — enriquecimiento Portal

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — Handler Application + batch API por página** | `MktLeadsListEnrichmentHandler` llama `LebytekApiClient` con batch de `api_tenant_public_id` de la página actual (≤15 filas) | Estado en vivo; alinea intención #66 y docs integración; timeout acotado por página | Latencia listado; depende API; requiere cliente HTTP Portal |
| **B — Solo columnas BD persistidas** | Handler lee `api_lifecycle_status` / columnas SQL sin HTTP | Rápido, sin dependencia runtime | No refleja actividad en vivo; desalinea propósito del hook |
| **C — Híbrido con cache TTL (Redis/file)** | Batch API + cache 60s por `publicId` | Balance latencia/frescura | Infra cache no verificada en Portal; complejidad ops |

**Recomendación:** **A con degradación graceful** — enriquecer vía batch `GET /tenants/{publicId}` (o endpoint batch futuro) cuando `api_tenant_public_id` presente; timeout total **2s por página**; fallback a valor BD (`api_lifecycle_status`) o «—». Rechazar B como solución final. C queda optimización futura si latencia lo exige en producción.

### Enfoques evaluados — estrategia de bump Framework en Portal

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A1 — PR único bump + handler** | Un PR: `composer update lebytek/framework` + handler + JSON + tests | Un merge, smoke único | PR más grande |
| **A2 — Dos PRs: bump lock primero** | PR1 solo lock; PR2 handler+JSON | Aislamiento de riesgo Composer | Dos merges; hook inerte entre PRs (aceptable) |

**Recomendación:** **A1** — bump y handler en un solo PR `feature/mkt-leads-after-list-rows`; el hook es inerte hasta registrar handler + JSON.

---

## Comportamiento esperado

### Portal — consumo `afterListRows` para `mkt_leads`

1. Tras `composer update lebytek/framework` a ≥ `v1.2.2` (recomendado `v1.2.3`), el listado `/admin/crud/mkt_leads` invoca el hook registrado.
2. Handler Portal (`App\Application\Marketing\Crud\MktLeadsListEnrichmentHandler` o equivalente) implementa `afterListRows(CrudListRowsContext $ctx)`:
   - Recibe filas post-query con columnas SQL existentes (`api_tenant_public_id`, `api_lifecycle_status`, etc.).
   - Agrupa `api_tenant_public_id` únicos de la página.
   - Para cada ID, consulta API WhatsApi (`GET /tenants/{publicId}` con token plataforma) — estado instancia / `isActive`.
   - Escribe columnas virtuales en cada fila: `wa_estado`, `tenant_actividad`.
   - Si API falla, timeout (>2s total) o ID ausente: fallback «—» o valor BD persistido.
3. `config/cruds/mkt_leads.json` declara:
   - `"hooks": { "handler": "mkt_leads_enrich" }` (clave whitelist, no FQCN).
   - Columnas listado con `"virtual": true` para campos enriquecidos.
4. Registro en `config/crud_handlers.php` → `'mkt_leads_enrich' => MktLeadsListEnrichmentHandler::class`.
5. Sin cambios en `vendor/lebytek/framework`.

### Contrato público Framework (ya publicado — no reimplementar)

| Artefacto | Namespace / ruta | Notas |
|-----------|-------------------|-------|
| `CrudListRowsContext` | `Lebytek\Framework\Application\Crud\Context\` | `rows()`, `setRows()`, `query()` |
| `AbstractCrudHookHandler::afterListRows` | `Lebytek\Framework\Application\Crud\Handlers\` | No-op default; Portal sobrescribe |
| Invocación | `CrudResourceService::buildIndexData` | Hook **después** de `list()` y **antes** de `CrudTableBuilder::build` |
| Registro handler | `config/crud_handlers.php` → `CrudHandlerRegistry` | Clave string en JSON CRUD |
| Columnas virtuales | `list.columns[].virtual: true` | Soportado desde #66 |

**Contratos ausentes en Framework (correcto — no asumir):**

- No existe `LebytekApiClient` en el paquete — Portal debe implementar cliente HTTP contra `https://api.lebytek.com/api/v1` según `docs/integration/waapi-api-contract.md`.
- No existe endpoint batch tenants en contrato documentado — diseño asume N llamadas paralelas acotadas por página (≤15) con timeout global; batch API sería mejora futura en `WhatsApiLebytek`.

### Framework — sin cambios requeridos

Prerrequisito satisfecho en tip `main` @ `041e402`. No se requiere nuevo release Framework para este diseño.

---

## Alcance

### Portal — `Parzival2103/Lebytek_Portal`, base `main` (**no verificado**)

| ID | Entregable | Archivos esperados (convención Portal) |
|----|------------|----------------------------------------|
| P1 | Bump `composer.lock` a `lebytek/framework` ≥ `v1.2.2` | `composer.json`, `composer.lock` |
| P2 | Handler `afterListRows` | `app/Application/Marketing/Crud/MktLeadsListEnrichmentHandler.php` |
| P3 | Registro handler | `config/crud_handlers.php` → clave `mkt_leads_enrich` |
| P4 | CRUD JSON hook + columnas virtuales | `config/cruds/mkt_leads.json` |
| P5 | Test gate Portal | `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php` (o equivalente) |

### Framework — `Parzival2103/Lebytek_Framework`, base `main`

| ID | Entregable | Estado |
|----|------------|--------|
| F-hook | Hook `afterListRows` | **Ya publicado** — no acción |
| F-semver | Release ≥ `v1.2.2` | **Satisfecho** — tip `v1.2.3` @ `041e402` |

### Operaciones — implementación y staging (incluidas)

- Portal: rama `feature/mkt-leads-after-list-rows` → PR → merge `main`.
- Verificación local Portal: `php tests/run.php Marketing` o suite CRUD equivalente (**estructura no verificada** — M6).
- Smoke admin listado leads en entorno dev/staging Portal cuando exista.

### Operaciones — producción (fuera de esta corrida desatendida)

- Deploy `lebytek.com` / bump `composer.lock` en VPS producción.
- Configuración `.env` producción (`LEBYTEK_API_TOKEN`).
- SSH, smoke VPS, habilitar Stripe subscription checkout.
- Conceder token gh lectura Portal a automation (M6).

---

## No-alcance

- Implementación código en esta corrida (spec-only).
- Cambios en `src/`, `database/`, `routes/`, `skeleton/` Framework.
- Reabrir M1, M2, M7, M8, M9 — resueltos en tip `main`.
- M3–M5 (RBAC router, API token, permisos seed) — backlog Framework separado.
- D6 skeleton.lebytek.com, D7 CI Actions — planes existentes.
- Merge o referencia operativa a `feature/backoffice-api-integration`.
- QA Stripe subscription checkout Portal (C2 ops).
- Nuevo endpoint batch en WhatsApi (mejora futura, no bloqueante).

---

## Ownership map

| Requisito | Repositorio | Rama base | Release semver |
|-----------|-------------|-----------|----------------|
| P1–P5 hook mkt_leads | `Lebytek_Portal` | `main` | Consume Framework ≥ `v1.2.2`; recomendado `v1.2.3` |
| F-hook (prerrequisito) | `Lebytek_Framework` | `main` | Tag `v1.2.3` @ `041e402` — **ya publicado** |
| Cliente HTTP leads↔API | `Lebytek_Portal` | `main` | `LebytekApiClient` o equivalente en `App\Infrastructure` |
| API tenant status | `WhatsApiLebytek` | `main` | Sin cambio requerido @ `f3f3ec7` |
| Credenciales gh Portal | Ops / automation | — | Fuera de diseño producto (M6) |

---

## Dependencias y compatibilidad

### Framework → Portal

| Versión Framework | Capacidad | Consumidor |
|-------------------|-----------|------------|
| `< v1.2.2` | Sin `afterListRows` | Portal **no debe** registrar hook (validación fallará o no-op) |
| `v1.2.2` | Hook + columnas virtuales | Mínimo P1–P5 |
| `v1.2.3` | M1+M9 fix + hook | **Recomendado** — compatible con constraint `^1.2` |

**Frontera semver/release:** Portal debe actualizar `composer.lock` a tag ≥ `v1.2.2` **antes** de merge P2–P5. El tag `v1.2.3` incluye fixes de hygiene no bloqueantes para el hook pero recomendados por seguridad dompdf.

### Portal → WhatsApi

- Autenticación: `Authorization: Bearer {LEBYTEK_API_TOKEN}` (token plataforma).
- Endpoint consumido: `GET /tenants/{publicId}` — permiso `tenants.ver`.
- Respuesta esperada: `isActive`, metadata tenant; mapear a `tenant_actividad` / `wa_estado` según convención UI Portal (**labels exactos no verificados en repo Portal**).

### Migración segura

1. **Framework ya publicado:** no hay dependencia de orden con nuevo release Framework.
2. **Portal base existente:**
   - Crear rama desde `main` Portal.
   - `composer update lebytek/framework` — verificar que `CrudConfigValidator` acepta clave handler.
   - Merge handler + JSON + registro en un PR.
   - Columnas `dom_mkt_leads` con `api_tenant_public_id` ya documentadas en `docs/integration/role-delegation-lebytek-api.md` (**estado BD producción no verificado**).
3. **Portal base nueva (skeleton/tenant):** instalar framework ≥ `v1.2.2`; patrón handler solo aplica si módulo Marketing habilitado.
4. **Rollback Portal:** quitar `"hooks"` de `mkt_leads.json` y entrada en `crud_handlers.php`; listado vuelve a columnas SQL-only sin revertir bump lock.

---

## Tests (TDD — deben fallar antes del fix)

### Portal (**diseño — rutas no verificadas en repo**)

| Test | Archivo propuesto | Pre-fix esperado | Post-fix |
|------|-------------------|------------------|----------|
| Framework version gate | script o test existente | **FAIL** — vendor < `v1.2.2` (evidencia doc: v1.1.0) | **PASS** — lock ≥ `v1.2.2` |
| Handler registrado | `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php` | **FAIL** — clave `mkt_leads_enrich` ausente | **PASS** |
| JSON hook declarado | idem | **FAIL** — `mkt_leads.json` sin `hooks.handler` | **PASS** |
| Enriquecimiento filas | idem | **FAIL** — filas sin `wa_estado` tras hook (mock API) | **PASS** — columna virtual poblada |
| Timeout degradación | idem | N/A pre-fix | **PASS** — API mock timeout → fallback «—» |

Usar mock de cliente API en test unitario; no llamar `api.lebytek.com` real en CI.

### Framework (verificación cruzada — ya verde en tip)

| Test | Archivo | Estado @ `041e402` |
|------|---------|-------------------|
| Hook mutates rows | `tests/Crud/Table/CrudListRowsHookTest.php` | **PASS** (171/171 Crud suite) |
| Semver sync | `tests/Docs/PlatformVersionSemverTest.php` | **PASS** (1.2.3) |
| Dompdf seguro | `tests/Docs/DompdfSecurityVersionTest.php` | **PASS** (≥3.1.6) |

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Portal prod sigue en framework `v1.1.0` | Alta | P1 explícito; ops confirman SHA + lock (M6) |
| Latencia listado leads (N HTTP por página) | Media | Paralelizar requests; timeout 2s; fallback BD |
| API WhatsApi caída en listado admin | Media | Degradación graceful — «—» o valor cache BD |
| Credenciales automation sin acceso Portal | Media | Marcar P1–P5 **no verificados**; operador valida rutas |
| Staging Portal inexistente | Media | Smoke en dev local; prod fuera de corrida desatendida |
| Endpoint batch tenants ausente | Baja | N ≤ 15 por página; optimización futura WhatsApi |
| Regresión bump framework en Portal | Media | Correr suite Portal completa post-bump |

---

## Rollback

| Ámbito | Procedimiento |
|--------|---------------|
| Portal handler | Revert PR: quitar hooks JSON + registro handler; listado SQL-only |
| Portal bump lock | Mantener bump si no hay breaking change; hook es opt-in vía JSON |
| Framework | No aplica — sin cambios en esta implementación |

---

## Criterios de aceptación

### Portal (P1–P5)

- [ ] `composer.lock` referencia `lebytek/framework` ≥ `v1.2.2` (preferible `v1.2.3`).
- [ ] Handler `MktLeadsListEnrichmentHandler` registrado como `mkt_leads_enrich`.
- [ ] `config/cruds/mkt_leads.json` declara hook y columnas `wa_estado`, `tenant_actividad` con `"virtual": true`.
- [ ] Test gate `MktLeadsListEnrichmentTest` pasa con mock API.
- [ ] Listado admin muestra columnas enriquecidas; timeout API → fallback sin error 500.
- [ ] Sin edits en `vendor/lebytek/framework`.

### Framework (prerrequisito — ya cumplido)

- [x] Tag ≥ `v1.2.2` publicado con hook `afterListRows`.
- [x] Semver sync y dompdf seguro en tip `main` @ `041e402`.

### Verificación cruzada documentada

- [x] Framework hook verificado en `src/Application/Services/CrudResourceService.php` y tests Crud.
- [ ] Portal SHA y lock — **no verificado** (M6).
- [ ] Issues Portal abiertos — **no verificado** (M6).
- [x] WhatsApi `main` @ `f3f3ec7` accesible vía gh.

---

*Spec-only. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
