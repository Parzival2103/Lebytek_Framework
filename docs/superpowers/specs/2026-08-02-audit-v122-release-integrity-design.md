# Design: Integridad release v1.2.2 + consumo Portal del hook `afterListRows`

**Fecha:** 2026-08-02  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel B)

**Auditoría fuente:** `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (rama `automation/audit-2026-08-02`, head `a8331573ec94d65621dd77512ec7ccaf522af035`)  
**Specs/planes relacionados (detalle previo, no duplicar):**

- Semver sync (histórico): `docs/superpowers/specs/2026-07-29-audit-config-version-semver-sync-design.md`
- Harness hygiene (implementado #62, regresión #66): `docs/superpowers/specs/2026-08-01-audit-harness-hygiene-unblock-design.md` · plan `docs/superpowers/plans/2026-08-01-audit-harness-hygiene-unblock.md`
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`
- Integración leads ↔ API: `docs/integration/role-delegation-lebytek-api.md`, `docs/integration/waapi-api-contract.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `09b4f3e71c4abe3fddc2b430d93bb2a074448fe6` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (merge #49 docs subdomain) |
| Rama generada | `automation/spec-2026-08-02` |
| Timestamp UTC | trigger cron `2026-08-02T12:10:00Z` / corrida agente `2026-08-02T12:15:00Z` |
| Nivel de fuente | **B** — rama `origin/automation/audit-2026-08-02` @ `a8331573ec94d65621dd77512ec7ccaf522af035`; diff único `docs/audits/2026-08-02-auditoria-tecnica-diaria.md`; ancestry limpia desde `origin/main`; **sin PR de auditoría abierto** (Nivel A: `gh pr list --search "docs(audit):" --base main --state open` → vacío) |
| PR auditoría fuente | N/A (PR pendiente de AUTOMATION-03) |
| headRefOid fuente | `a8331573ec94d65621dd77512ec7ccaf522af035` |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head de auditoría |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 que M6 |
| Pase UX | `2026-08-02T12:35:00Z` UTC; modo **normal** (semver display Framework + listado CRUD `mkt_leads` Portal) |

---

## Problema

La auditoría del 2026-08-02 confirma que `origin/main` @ `09b4f3e` avanzó **10 commits** desde la auditoría del 2026-08-01 (`5b03d9e`), incluyendo el **primer cambio de plataforma en `src/`** desde varias corridas docs-only: PR #66 publica la capacidad CRUD `afterListRows` y el tag **`v1.2.2`**.

Tres deudas accionables emergen de ese release:

| ID | Hallazgo | Evidencia verificada (`main` @ `09b4f3e`) | Impacto |
|----|----------|-------------------------------------------|---------|
| **M1** | Regresión sync semver post-`v1.2.2` | `composer.json` → `"version": "1.2.2"`; `config/app.php:7` y `skeleton/config/app.php:7` → `'1.2.1'`; tag `v1.2.2` @ `09b4f3e`. `PlatformVersionSemverTest` existe y **fallaría** (auditoría: Docs 21/23, 2 fails semver) | Gate Docs rojo en tip; UI/CLI muestran `v1.2.1` mientras el paquete/tag declaran `1.2.2`; checklist `despliegue-y-versionado.md` incumplido |
| **M9** | `dompdf/dompdf` v3.1.5 con advisories | `composer.lock` fija `v3.1.5`; constraint `^3.1`; auditoría: 6 advisories, fix ≥ `3.1.6` | Superficie PDF-kit/reportes; CVE local-file/DoS según advisory |
| **P1** | Portal no consume el hook `afterListRows` | Framework #66 entrega contrato genérico (`CrudListRowsContext`, `CrudResourceService::buildIndexData`); commit message cita enriquecimiento `mkt_leads` con estado Green API — **lógica Portal no verificable** (M6). Última evidencia Portal: framework `v1.1.0` | Admin leads sin columnas virtuales de estado tenant/actividad en vivo; capacidad publicada sin consumidor |

**Contexto positivo (no reabrir):**

- **M2 RESUELTO** — PR #62 purgó vars Portal del root `.env.example`; `FrameworkRootNotPortalTest` PASS.
- Fronteras FPS intactas: hook `afterListRows` en `src/` es genérico (sin `mkt_*`, sin `LebytekApiClient`); `SkeletonPurityTest` 13/13 PASS; Crud suite 171/171 PASS.
- C2 Stripe Framework resuelto (`v1.2.1`+); QA Portal subscription checkout **no verificable**.

**Deuda carry-forward (registrada, fuera de alcance inmediato):** M3 (CRUD RBAC router), M4 (API sesión), M5 (`permisos.gestionar` seeds), M6 (gh Portal 404), D6 (`skeleton.lebytek.com`), D7 (CI GitHub Actions).

---

## Brainstorm y recomendación de diseño

### Contexto y criterios de éxito

- **Propósito:** restaurar integridad del release `v1.2.2` en Framework (semver + seguridad dompdf) y diseñar el consumo Portal del hook `afterListRows` para enriquecer el listado admin de `mkt_leads` con estado Green API / actividad tenant.
- **Restricciones:** package source no desplegable; negocio Marketing vive en `Lebytek_Portal`; tag `v1.2.2` ya publicado; checklist release de 5 pasos ya documentado en `despliegue-y-versionado.md`; legacy `archive/backoffice-api-integration` solo como evidencia histórica.
- **Éxito Framework:** tres fuentes semver iguales a `1.2.2`; `composer audit` sin advisories dompdf; Docs suite verde; tag patch `v1.2.3` solo si el bump dompdf se publica en el mismo tren.
- **Éxito Portal:** `composer.lock` consume `lebytek/framework` ≥ `v1.2.2`; handler registrado en `config/crud_handlers.php`; CRUD `mkt_leads.json` declara hook + columnas virtuales; listado admin muestra estado enriquecido sin N+1 HTTP descontrolado.

### Enfoques evaluados — Framework (M1 + M9)

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — PR único release-hygiene** | Sync semver a `1.2.2` + `composer update dompdf/dompdf` ≥3.1.6 + refresh lock hash | Un merge, un tag patch opcional; alinea checklist release | Mezcla hygiene semver con bump de seguridad (aceptable: ambos son PATCH) |
| **B — Dos PRs secuenciales** | PR1 semver-only; PR2 dompdf | Aislamiento de blame | Dos releases/tags; M1 deja Docs rojo más tiempo |
| **C — Semver sin tag nuevo** | Sync configs sin tag; dompdf en PR aparte | Menos tags | Tag `v1.2.2` queda inconsistente con lock dompdf; operadores confundidos |

**Recomendación Framework:** **A** — PR `feature/v122-release-integrity` desde `main`. Tag **`v1.2.3`** si se publica bump dompdf; si dompdf se difiere, semver-only puede ser commit directo sobre `main` sin tag (el tag `v1.2.2` ya existe y el contrato Composer no cambia).

### Enfoques evaluados — Portal (P1 / `afterListRows`)

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — Handler Application + batch API** | `MktLeadsListEnrichmentHandler` en `App\Application\Marketing\` llama `LebytekApiClient` con batch de `api_tenant_public_id` por página | Estado en vivo; alinea commit #66 y docs integración | Latencia listado; requiere timeout/fallback; depende API disponible |
| **B — Solo columnas DB persistidas** | Handler lee `api_lifecycle_status` / columnas ya en `dom_mkt_leads` sin HTTP | Rápido, sin dependencia runtime API | No refleja actividad tenant en vivo; desalinea intención del hook |
| **C — Híbrido con cache TTL** | Batch API + cache request-scoped o Redis 60s por tenant | Balance latencia/frescura | Más complejidad; cache infra no verificada en Portal |

**Recomendación Portal:** **A con degradación graceful** — enriquecer filas vía batch API cuando `api_tenant_public_id` está presente; si API falla o timeout (>2s total por página), mostrar valor persistido en BD o «—». Rechazar B como solución final (el hook existe precisamente para datos no materializados en SQL). C queda como optimización futura si latencia lo exige.

---

## Comportamiento esperado

### Framework — M1 semver sync (regresión #66)

1. `composer.json`, `config/app.php` y `skeleton/config/app.php` declaran **`1.2.2`** (mismo valor, sin prefijo `v`).
2. `composer.lock` content-hash actualizado tras cualquier cambio en `composer.json` (incluido bump dompdf).
3. `DeploymentStatus`, `/admin/sistema/estado` y `scripts/status.php` muestran **`v1.2.2`** sin cambios en `src/`.
4. Checklist § release en `docs/core/despliegue-y-versionado.md` cumplido en el commit pre-tag.

### Framework — M9 dompdf

1. `composer update dompdf/dompdf` resuelve ≥ **`3.1.6`** respetando `^3.1`.
2. `composer audit` sin advisories para `dompdf/dompdf`.
3. Suites PdfKit/Reportes (si existen en harness) permanecen verdes; sin cambio funcional en generación PDF.

### Portal — consumo `afterListRows` para `mkt_leads`

1. Tras `composer update lebytek/framework` a ≥ `v1.2.2`, el listado `/admin/crud/mkt_leads` invoca el hook registrado.
2. Handler Portal (`App\Application\Marketing\Crud\MktLeadsListEnrichmentHandler` o equivalente) implementa `afterListRows(CrudListRowsContext $ctx)`:
   - Recibe filas post-query con columnas SQL existentes (`api_tenant_public_id`, `api_lifecycle_status`, etc.).
   - Para filas con `api_tenant_public_id`, consulta API WhatsApi (batch) estado instancia / actividad tenant.
   - Escribe columnas virtuales en cada fila (p. ej. `wa_estado`, `tenant_actividad`) antes del formateo.
3. `config/cruds/mkt_leads.json` declara:
   - `"hooks": { "handler": "mkt_leads_enrich" }` (clave whitelist, no FQCN).
   - Columnas listado con `"virtual": true` para campos enriquecidos.
4. Sin cambios en `vendor/lebytek/framework`; registro en `config/crud_handlers.php` del consumidor.

### Contrato público Framework (ya publicado en v1.2.2 — no reimplementar)

| Artefacto | Namespace / ruta | Notas |
|-----------|------------------|-------|
| `CrudListRowsContext` | `Lebytek\Framework\Application\Crud\Context\` | `rows()`, `setRows()`, `query()`, hereda `resourceKey/table/primaryKey/userId/ip` |
| `AbstractCrudHookHandler::afterListRows` | `Lebytek\Framework\Application\Crud\Handlers\` | No-op default; Portal sobrescribe |
| Invocación | `CrudResourceService::buildIndexData` | Hook corre **después** de `list()` y **antes** de `CrudTableBuilder::build` |
| Registro handler | `config/crud_handlers.php` → `CrudHandlerRegistry` | Clave string en JSON CRUD; FQCN en PHP |
| Columnas virtuales | `list.columns[].virtual: true` | Soportado en #66 para display sin columna SQL |

**Contrato ausente en Framework (correcto — no asumir):** no existe `LebytekApiClient` ni endpoints Marketing en el paquete. Portal debe implementar cliente HTTP contra `WhatsApiLebytek` según `docs/integration/waapi-api-contract.md` (**contenido Portal no verificado en esta corrida**).

---

## Alcance

### Framework — `Parzival2103/Lebytek_Framework`, base `main`

| ID | Entregable | Archivos esperados |
|----|------------|-------------------|
| F1 | Sync semver `1.2.2` en harness + skeleton | `config/app.php`, `skeleton/config/app.php` |
| F2 | Bump dompdf ≥3.1.6 | `composer.lock` (+ posible pin mínimo en `composer.json` si audit lo exige) |
| F3 | Test gate dompdf (nuevo, TDD) | `tests/Docs/DompdfSecurityVersionTest.php` o equivalente en suite Docs |
| F4 | Verificación Docs verde | `PlatformVersionSemverTest` PASS (ya existe; falla hoy) |

**Semver / release boundary:** consumidores que ya instalaron `v1.2.2` reciben F1–F2 vía tag **`v1.2.3`** (PATCH). Portal **debe** estar en ≥ `v1.2.2` antes de implementar P1; el hook no existe en `v1.2.1`.

### Portal — `Parzival2103/Lebytek_Portal`, base `main` (**no verificado**)

| ID | Entregable | Archivos esperados (convención Portal) |
|----|------------|--------------------------------------|
| P1 | Bump `composer.lock` a `lebytek/framework` ≥ `v1.2.2` | `composer.json`, `composer.lock` |
| P2 | Handler `afterListRows` | `app/Application/Marketing/Crud/MktLeadsListEnrichmentHandler.php` (ruta ilustrativa) |
| P3 | Registro handler | `config/crud_handlers.php` → clave `mkt_leads_enrich` |
| P4 | CRUD JSON hook + columnas virtuales | `config/cruds/mkt_leads.json` |
| P5 | Test gate Portal | `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php` (o equivalente) |

### Operaciones — implementación y staging (incluidas)

- Framework: rama `feature/v122-release-integrity` → PR → merge `main` → tag `v1.2.3` si dompdf incluido.
- Portal: rama `feature/mkt-leads-after-list-rows` → PR → merge `main` → deploy staging Portal (cuando exista; hoy **staging Portal inexistente** según `ENVIRONMENTS.md`).
- Verificación local: `php tests/run.php Docs` (Framework); `php tests/run.php Marketing` o suite CRUD Portal ( **no verificable** ).

### Operaciones — producción (fuera de esta corrida desatendida)

- Deploy `lebytek.com` / bump `composer.lock` en VPS producción.
- Habilitar `PAYMENTS_SUBSCRIPTION_CHECKOUT` o Stripe en Portal.
- SSH, `.env` producción, smoke en VPS.
- Cierre PR auditoría (AUTOMATION-03).

---

## No-alcance

- Implementación código en esta corrida (spec-only).
- Reabrir M2, M7, M8, C1 — resueltos.
- M3–M5 (RBAC router, API token, permisos seed) — backlog separado.
- D6 skeleton.lebytek.com, D7 CI Actions — planes existentes sin ejecutar aquí.
- Merge o referencia operativa a `feature/backoffice-api-integration`.
- Cambios en `src/` Framework salvo lo ya publicado en #66.
- QA Stripe subscription checkout Portal (C2 ops).
- Conceder credenciales gh a automation (M6) — registro ops, no diseño producto.

---

## Ownership map

| Requisito | Repositorio | Rama base | Release semver |
|-----------|-------------|-----------|----------------|
| F1–F4 semver + dompdf | `Lebytek_Framework` | `main` | Tag `v1.2.3` PATCH (si dompdf); F1-only puede ser hotfix sin tag |
| P1–P5 hook mkt_leads | `Lebytek_Portal` | `main` | Consume Framework ≥ `v1.2.2`; deploy Portal independiente |
| API tenant status | `WhatsApiLebytek` | `main` | Sin cambio requerido si contrato HTTP vigente @ `f3f3ec7` |
| Gate ops Stripe | Portal VPS | — | Fuera de alcance |

---

## Dependencias y compatibilidad

### Framework → consumidores

| Versión Framework | Capacidad | Consumidor mínimo |
|-------------------|-----------|-------------------|
| `< v1.2.2` | Sin `afterListRows` | Portal no debe registrar hook (fallaría validación o no-op) |
| `v1.2.2` | Hook + columnas virtuales + datetime ISO | Portal P1–P5 |
| `v1.2.3` (propuesto) | M1 fix + dompdf ≥3.1.6 | Recomendado; compatible con P1 (`^1.2` o constraint existente) |

### Portal → Framework

- **Base nueva (skeleton/tenant):** instalar `lebytek/framework` ≥ `v1.2.2` desde Composer VCS; copiar patrón `config/crud_handlers.php` del spec Portal; no aplica enriquecimiento leads sin módulo Marketing.
- **Base Portal existente:** `composer update lebytek/framework` en rama feature; verificar `CrudConfigValidator` acepta handler key; migraciones `dom_mkt_leads` con columnas API ya documentadas en `docs/integration/` (**estado BD producción no verificado**).

### Migración segura

1. **Framework primero:** publicar F1–F4 y tag antes de merge Portal P2–P5 (Portal puede bump lock antes de handler — hook simplemente no se invoca hasta JSON + registro).
2. **Portal:** merge handler + JSON en un solo PR; feature flag no requerido (hook inerte si handler no registrado).
3. **Rollback Framework:** revert commit semver/dompdf; retag no recomendado — preferir forward fix.
4. **Rollback Portal:** quitar `"hooks"` de `mkt_leads.json` y entrada en `crud_handlers.php`; listado vuelve a columnas SQL-only.

---

## Tests (TDD — deben fallar antes del fix)

### Framework

| Test | Archivo | Pre-fix (estado verificado @ `09b4f3e`) | Post-fix |
|------|---------|----------------------------------------|----------|
| Semver harness | `tests/Docs/PlatformVersionSemverTest.php` | **FAIL** — configs `1.2.1` vs composer `1.2.2` | **PASS** — tres fuentes `1.2.2` |
| Semver skeleton | idem test 3 | **FAIL** | **PASS** |
| Dompdf versión segura | `tests/Docs/DompdfSecurityVersionTest.php` (**nuevo**) | **FAIL** — lock `v3.1.5` | **PASS** — lock ≥ `3.1.6` |
| Checklist doc | `tests/Docs/ReleaseChecklistDocTest.php` | PASS (ya existe) | PASS |

Diseño test dompdf (propuesto):

```php
// Assert composer.lock dompdf/dompdf version >= 3.1.6
// Mensaje: "dompdf below 3.1.6 — run composer update dompdf/dompdf (M9)"
```

### Portal (**diseño — rutas no verificadas**)

| Test | Comportamiento pre-fix | Post-fix |
|------|------------------------|----------|
| Handler registrado | **FAIL** — clave `mkt_leads_enrich` ausente en map | **PASS** |
| JSON hook declarado | **FAIL** — `mkt_leads.json` sin `hooks.handler` | **PASS** |
| Enriquecimiento filas | **FAIL** — filas sin `wa_estado` tras hook (mock API) | **PASS** — columna virtual poblada |

Usar mock de `LebytekApiClient` en test unitario; no llamar API real en CI.

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Portal prod sigue en framework `v1.1.0` | Alta | Ops confirman SHA + lock (M6); P1 explícito en plan Portal |
| Latencia listado leads (N+1 API) | Media | Batch API por página; timeout 2s; fallback BD |
| Tag `v1.2.2` ya publicado con metadata UI desfasada | Media | F1 hotfix; comunicar operadores |
| dompdf bump rompe PDF layout | Baja | Smoke PdfKit; PATCH semver |
| API WhatsApi caída en listado admin | Media | Degradación graceful — columnas «—» o valor cache BD |
| Credenciales automation sin acceso Portal | Media | Marcar P1–P5 **no verificados**; operador valida |

---

## Rollback

| Capa | Procedimiento |
|------|---------------|
| Framework semver | Revert commit F1; no retirar tag `v1.2.2` |
| Framework dompdf | `composer require dompdf/dompdf:3.1.5` + revert lock (solo si regressión crítica) |
| Portal handler | Quitar hook JSON + registro; redeploy sin handler |
| Producción | **Fuera de alcance** — operador manual según runbook Portal |

---

## Compatibilidad, UX y responsive

### Modo del pase: normal

Este spec combina **F1 semver** (display plataforma en harness/skeleton), **F2 dompdf** (superficie PDF/reportes, sin layout admin nuevo) y **P1–P5** (listado admin Portal `mkt_leads` enriquecido vía `afterListRows`). Superficie UI verificable: tarjeta versión y tablas en `/admin/sistema/estado`, copy semver en install/`scripts/status.php`, y tabla CRUD leads con columnas virtuales. Health API pública, login responsive y dashboard nav permanecen carry-forward.

### Compatibilidad (verificado vs carry-forward)

| Área | Este spec (F1–F4 / P1–P5) | Evidencia / carry-forward |
|------|---------------------------|---------------------------|
| PHP soportado | Sin cambio runtime Framework | `composer.json` exige `>=8.1`; VPS documentado PHP **8.4.22** (`2026-07-26-skeleton-package-staging-design.md`). F1–F4 y hook `afterListRows` compatibles 8.1+; dompdf bump PATCH no eleva requisito PHP. |
| Instalación vía `vendor/` | **Alcance P1 + F1–F4** | Consumidores instalan `lebytek/framework` ≥ `v1.2.2` en `vendor/lebytek/framework` — **solo lectura**; handler Portal vive en `App\` + `config/crud_handlers.php`. Framework publica tag; Portal bump `composer.lock` antes de P2–P5. Path-repo sólo desarrollo local del package source. |
| Health sin cookie de sesión | Carry-forward **M4/D7** | `routes/api.php` L14–16 — grupo `/api` + `AuthMiddleware`; `/api/ping` L23 requiere sesión. Smoke post-merge: **no** usar `/api/ping` como health LB; backlog `GET /api/health` público (CF7). |
| `.env.example` sin vars Portal | **Resuelto (M2)** — sin alcance | Root `.env.example` L55 comenta no duplicar `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*`; `FrameworkRootNotPortalTest` PASS (#62). P1–P5 usan vars Portal en consumidor — fuera harness. |
| Navegadores objetivo | Superficie admin + CRUD | Baseline `docs/core/ui_ux.md`: admin breakpoint **992px (`lg`)**; install wizard **720px**. Chrome, Firefox, Safari, Edge últimas 2 versiones + iOS Safari ≥ 15; sin IE11. Columnas virtuales leads deben degradar en `<768px` vía `list.columns[].priority`. |

### UX — flujos Framework (F1–F4)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U1** | Tarjeta versión en `/admin/sistema/estado` muestra **`v1.2.2`** (no `v1.2.1`) tras F1 — operador distingue versión plataforma publicada del tag Git | M1 |
| **U2** | Install `paso_resultado.php` y `scripts/status.php` stdout alineados con tarjeta web — mismo semver post-merge | M1 |
| **U3** | `PlatformVersionSemverTest` / `DompdfSecurityVersionTest`: mensaje de fallo cita archivo, valor esperado y acción («sync tres archivos a 1.2.2» / «composer update dompdf/dompdf ≥3.1.6») | M1, M9 |
| **U4** | Checklist release en `despliegue-y-versionado.md`: pasos numerados cumplidos antes de tag `v1.2.3` (si dompdf incluido) | D13 |

### UX — flujos Portal `mkt_leads` (P1–P5, **no verificados**)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U5** | Columnas virtuales `wa_estado` / `tenant_actividad` (o equivalente) con **labels legibles** en español; valor «—» cuando `api_tenant_public_id` ausente — no celda vacía ambigua | P1 |
| **U6** | Timeout API (>2s por página) o fallo HTTP: degradación graceful — mostrar valor persistido en BD (`api_lifecycle_status`) o «—»; **no** bloquear render del listado ni error 500 genérico | P1, P2 |
| **U7** | Copy accionable si API caída repetida: hint operador («revisa WhatsApi / credenciales en `.env` Portal») en tooltip o badge secundario — no sólo «Error» | P1, CF10 parcial |
| **U8** | Handler no registrado o clave JSON inválida: validación `CrudConfigValidator` con mensaje que indique `config/crud_handlers.php` + clave esperada (`mkt_leads_enrich`) | P3, P4 |
| **U9** | Estado carga durante batch `afterListRows`: spinner fila o placeholder «…» en columnas virtuales hasta completar enriquecimiento — evitar flash vacío→valor (CF9 parcial) | P2 |

### Responsive — smoke en superficies tocadas

Referencia: `docs/core/ui_ux.md` §542 — breakpoint admin **992px (`lg`)**; tablas CRUD exigen `table-responsive` (`ui_ux.md` L222).

| Superficie | Verificación post-merge | Rango |
|------------|-------------------------|-------|
| Tarjeta versión (`/admin/sistema/estado`) | `v1.2.2` legible sin truncar; `col-md-4` stack full-width en móvil | **320–768px** |
| Tabla health checks (misma página) | `table-responsive` + `js-dt-responsive` sin regresión por F1 | **320–768px** |
| Listado admin `mkt_leads` (Portal) | `table-responsive` obligatorio; columnas virtuales con `priority` (identificador lead `1–2`, `wa_estado` `2–3`, actividad tenant `4+`); toolbar filtros usable sin solapamiento | **320–768px** |
| PDF/reportes dompdf (F2) | Sin cambio layout admin; smoke generación PDF en viewport escritorio ≥992px si aplica | ≥992px |

### Carry-forward UX — próximo spec con superficie UI más amplia

Ítems derivados de deuda abierta; **CF1–CF2 (semver harness + env purge) resueltos** (#62); **CF5 parcialmente cubierto** para `mkt_leads` en P4 — otros CRUDs pendientes.

| # | Ítem | Origen | Requisito concreto |
|---|------|--------|-------------------|
| CF3 | Login responsive 320–768px | `ui_ux.md` | `.ct-login-page`, `.ct-login-card` sin overflow horizontal; tap targets ≥44px; sin scroll lateral en 320px. |
| CF4 | Dashboard admin responsive 320–768px | layouts side/top/bottom | Nav colapsable; KPI grid legible; topbar sin solapamiento de acciones. |
| CF5′ | Tablas CRUD restantes | módulo CRUD, D6 | `table-responsive` + `list.columns[].priority` en recursos distintos de `mkt_leads`; toolbar móvil. |
| CF6 | RBAC router CRUD/calendario | M3 | Errores 403 accionables (slug requerido vs permiso denegado). |
| CF7 | Health endpoint público | M4/D7 | `GET /api/health` 200 sin cookie; body `{ "status": "ok" }`; checklist VPS. |
| CF8 | Permisos admin catálogo | M5 | Slug `permisos.gestionar` en seeds; UI permisos sin workaround `administracion.ver`. |
| CF9 | Estados vacío / error / carga (global) | `ui_ux.md` §8 | Unificar empty states en CRUDs sin hook; spinners list; validación con hint de corrección — más allá de U9 en leads. |
| CF10 | Copy errores accionables (transversal) | transversal | Auth, wizard install, CRUD save: qué falló + qué hacer; extiende U7 fuera de leads. |

---

## Criterios de aceptación

### Framework

- [ ] `composer.json`, `config/app.php`, `skeleton/config/app.php` → versión **`1.2.2`** idéntica.
- [ ] `php tests/run.php Docs` → **0 failed** (incl. PlatformVersionSemver + DompdfSecurityVersion).
- [ ] `composer audit` → **0 advisories** para `dompdf/dompdf`.
- [ ] `git diff origin/main...HEAD` en PR Framework limitado a F1–F4 (sin `src/` salvo lock).
- [ ] Si tag publicado: `v1.2.3` apunta al commit con F1+F2; checklist 5 pasos cumplido.

### Portal (**verificación pendiente acceso repo**)

- [ ] `composer.lock` referencia `lebytek/framework` ≥ **`v1.2.2`**.
- [ ] Handler registrado y JSON CRUD declara hook.
- [ ] Listado admin `mkt_leads` muestra columnas virtuales enriquecidas con API mock en test.
- [ ] Sin ediciones en `vendor/lebytek/framework`.
- [ ] Frontera FPS respetada: handler en `App\`, cliente API en `App\Infrastructure\Integrations\`.

### Compatibilidad, UX y responsive

- [ ] **AC-UX1:** Sección **Compatibilidad, UX y responsive** declara modo **normal** con requisitos K/U/R verificables para F1–F4 y P1–P5.
- [ ] **AC-UX2:** Requisitos U1–U9 (semver display, tests accionables, enriquecimiento leads, degradación API) incluidos como criterios del spec.
- [ ] **AC-UX3:** Carry-forward CF3–CF4, CF5′, CF6–CF10 documentado; CF1–CF2 no arrastrados (resueltos); CF5 parcial vía P4.
- [ ] **AC-UX4:** Smoke responsive R1–R3 en **320–768px** para estado admin Framework y listado `mkt_leads` Portal post-implementación.

### Cadena automation

- [ ] Spec commit único bajo `docs/superpowers/specs/`.
- [ ] PR spec abierto hacia `main`; PR auditoría fuente mergeado por AUTOMATION-03.

---

## Referencia legacy (solo histórico)

El tag `archive/backoffice-api-integration` @ `4789f95` contenía `config/cruds/mkt_leads.json` y handlers monolíticos pre-FPS. **No** usar como base de implementación. Portal moderno debe implementar P1–P5 sobre `Lebytek_Portal/main` con Framework empaquetado vía Composer.

---

*Spec-only. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
