# Design: RBAC en router para rutas CRUD y Calendario (M3 / CF6)

**Fecha:** 2026-08-06  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel A)

**Auditoría fuente:** `docs/audits/2026-08-06-auditoria-tecnica-diaria.md` (PR #84 @ `5d6df2f5d23b28baa4d0166e766fc70fa93ecd45`; rama `automation/audit-2026-08-06`)  
**Hallazgo principal:** **M3** — rutas `/crud/{resource}` y `/calendario/{key}` sólo heredan `AuthMiddleware`; RBAC granular ocurre en `CrudResourceService` / use cases, sin `RbacMiddleware` a nivel router. **Sin spec dedicado previo** (CF6 referenciado como «Spec futuro» en specs 2026-08-01..2026-08-05).

**Specs/planes relacionados (no duplicar):**

- API health público (M4): `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md` · plan `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` (**0/5** tareas)
- CI gates (D7): `docs/superpowers/specs/2026-08-04-audit-platform-ci-gates-design.md` · plan `docs/superpowers/plans/2026-08-04-audit-platform-ci-gates.md` (**0/5** tareas)
- Portal afterListRows: `docs/superpowers/specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md` · plan `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md` (**0/5** tareas)
- Cadena artefactos audit (M7/M10 proceso): `docs/superpowers/specs/2026-07-30-audit-artifact-chain-design.md` · test `tests/Docs/AuditArtifactFreshnessTest.php` (M7)
- Release integrity (M1/M9 resueltos): `docs/superpowers/specs/2026-08-02-audit-v122-release-integrity-design.md` · tag `v1.2.3` @ `041e402`
- Inventario deuda: `docs/audits/2026-07-28-deuda-tecnica-inventario.md` · auditoría carry-forward `docs/audits/2026-08-02-auditoria-tecnica-diaria.md`
- Evidencia histórica alineación: `docs/audits/correccion_alineacion_modulos_v0.1.md`, `docs/audits/auditoria_crud_engine_v0.1.md` § RBAC por recurso en servicio
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `0d26a15206e7c60055a7d5f39b8b362df45c301d` |
| SHA Portal inspeccionado | **No verificado** — `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404; `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository». Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-06` |
| Timestamp UTC | trigger cron `2026-08-06T12:10:00Z` / corrida agente `2026-08-06T12:10:00Z` |
| Nivel de fuente | **A** — PR abierto #84, título `docs(audit): auditoría técnica diaria 2026-08-06`, `baseRefName=main`, `mergeable=MERGEABLE`, `updatedAt=2026-08-06T12:05:24Z`. Verificaciones: `merge-base --is-ancestor origin/main 5d6df2f` → exit 0; diff `origin/main...5d6df2f` → único archivo `docs/audits/2026-08-06-auditoria-tecnica-diaria.md`; ningún commit legacy ancestro del head. |
| PR auditoría fuente | #84 — https://github.com/Parzival2103/Lebytek_Framework/pull/84 |
| headRefOid fuente | `5d6df2f5d23b28baa4d0166e766fc70fa93ecd45` (rama audit; no heredada) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | #84 audit 2026-08-06; #77 docs `crm.lebytek.com` ENVIRONMENTS (fuera de ciclo) |

---

## Problema

La auditoría del 2026-08-06 (M3, carry-forward desde 2026-07-27) documenta que las rutas genéricas del CRUD Engine y del módulo Calendario quedan **fuera** del patrón de defensa en profundidad usado en el resto del admin: sólo exigen sesión (`AuthMiddleware` del grupo `/admin`), mientras que usuarios, roles, reportes, PDF Kit e integrations declaran `RbacMiddleware` con slug explícito en el router.

**Evidencia verificada en tip `main` @ `0d26a15`:**

| Comprobación | Resultado |
|--------------|-----------|
| `routes/web.php` L114–122 | Rutas `/crud/{resource}*` **sin** middleware RBAC en registro |
| `routes/web.php` L124–125 | Rutas `/calendario/{key}*` **sin** middleware RBAC |
| `skeleton/routes/web.php` | Espejo idéntico del harness |
| `routes/web.php` L57–65, L127–139 | Contraste: dashboard, admin RBAC, reportes, pdf-kit **sí** usan `RbacMiddleware` |
| `src/Application/Services/CrudResourceService.php` L49, L110, L132… | RBAC por recurso vía `RbacService::verificar($definition->permissionFor(...))` |
| `src/Presentation/Controllers/Admin/CrudController.php` L41–42, L55–56 | `AccesoException` → `Response::forbidden()` (HTML genérico) |
| `src/Presentation/Middlewares/RbacMiddleware.php` L30–36 | 403 JSON en AJAX; flash + `Response::forbidden()` en HTML |
| `src/Presentation/Controllers/Admin/CalendarioController.php` L37–38, L59–60 | `AccesoException` → forbidden HTML o JSON `{error: forbidden}` |
| Tests router CRUD RBAC | **Ausentes** — `rg 'CrudRbac\|crud.*RbacMiddleware' tests/` → 0 |
| Semver plataforma | `1.2.3` sincronizado (`composer.json`, `config/app.php`, `skeleton/config/app.php`) — M1 resuelto |
| Tag release vigente | `v1.2.3` @ `041e402` |
| `.github/workflows/` | **Ausente** (D7 — plan 0/5) |
| PHP CLI en agente cloud | **Ausente** al inicio de corrida spec — no se re-ejecutó suite completa; clasificado bloqueador entorno |

**Consecuencia operativa:** cualquier usuario autenticado (incluso sin permiso `{prefix}.ver`) alcanza el controlador y servicios antes del rechazo. El rechazo funciona hoy (no hay bypass de autorización), pero:

1. **Defensa en profundidad débil** — superficie de controlador/hooks expuesta innecesariamente.
2. **403 inconsistentes** — mensajes, flash y formato JSON difieren entre middleware estático y `AccesoException` en CRUD/calendario.
3. **Operadores RBAC** — `scripts/rbac_integrity_report.php` y `config/rbac_route_permissions.php` no reflejan permisos CRUD en capa router (sólo menú + JSON CRUD).

**Deuda carry-forward registrada (fuera de alcance inmediato de este spec):** M4 (`/api/health` — spec/plan listos 0/5), M5 (`permisos.gestionar` seeds), M6 (gh Portal 404), M10 (hueco audits 03–05 — proceso; parcialmente cubierto por spec artifact-chain + `AuditArtifactFreshnessTest`), D6 (`skeleton.lebytek.com`), D7 (CI GitHub Actions — spec/plan 0/5).

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

- **Propósito:** alinear CRUD Engine y Calendario con el patrón router-level RBAC del admin, manteniendo la autorización fina por recurso/acción en Application (sin duplicar reglas de negocio en Presentation).
- **Restriciones:** permisos CRUD son **dinámicos** (`{permission_prefix}.ver|crear|editar|eliminar` desde JSON `config/cruds/*.json`); no existe slug único estático como `usuarios.gestionar`; legacy `archive/backoffice-api-integration` sólo evidencia histórica; operaciones VPS/producción fuera de automation desatendida; Portal hereda vía semver tag, no parche en `vendor/`.
- **Éxito Framework:** rutas CRUD/calendario registran middleware RBAC dinámico; 403 accionables (slug requerido en mensaje); checks en servicio **permanecen** (defensa doble); tests TDD rojo→verde; skeleton espejado; doc § RBAC CRUD actualizada.
- **Éxito consumidor:** tras bump `lebytek/framework` ≥ tag release, tenants con `routes/web.php` propio deben mergear registro de middleware en rutas CRUD/calendario si sobrescriben el archivo.

### Enfoques evaluados

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — `CrudRbacMiddleware` dinámico + resolver Application** | Nuevo middleware lee `{resource}`/`{key}`, resuelve slug vía `CrudRoutePermissionResolver` (Application), aplica `RbacPolicy` igual que `RbacMiddleware` | Defensa en profundidad; 403 uniformes; permiso correcto por verbo/ruta | Requiere resolver testeable; acciones custom siguen en servicio |
| **B — Documentar patrón servicio-only** | Mejorar mensajes `AccesoException` y docs; sin middleware router | Diff mínimo | No cierra M3; inconsistencia estructural persiste |
| **C — Gate grueso `administracion.ver` en router** | Un slug estático para todo CRUD/calendario | Implementación trivial | Falso sentido de seguridad; no refleja permisos por recurso; rompe roles granulares |

**Recomendación:** **A** — middleware dinámico + resolver en Application. **Rechazar C** (semántica RBAC incorrecta). **Rechazar B** como solución final (insuficiente para M3/CF6; puede complementar mensajes).

### Esbozo del diseño

```
Request GET /admin/crud/demo_clientes
  → AuthMiddleware (grupo /admin)
  → CrudRbacMiddleware
       → CrudRoutePermissionResolver::resolve($request) → "demo_clientes.ver"
       → RbacPolicy::puede → fail → 403 accionable (HTML o JSON)
  → CrudController::index
       → CrudResourceService::buildIndexData → verificar("demo_clientes.ver")  [permanece]
```

**Mapeo ruta → permiso base (v1):**

| Patrón ruta | Método | Permiso resuelto |
|-------------|--------|------------------|
| `/crud/{resource}` | GET | `{prefix}.ver` |
| `/crud/{resource}/crear` | GET | `{prefix}.crear` |
| `/crud/{resource}` | POST | `{prefix}.crear` |
| `/crud/{resource}/{id}/editar` | GET | `{prefix}.editar` |
| `/crud/{resource}/{id}` | GET (show) | `{prefix}.ver` |
| `/crud/{resource}/{id}` | POST (update) | `{prefix}.editar` |
| `/crud/{resource}/{id}/eliminar` | POST | `{prefix}.eliminar` |
| `/crud/{resource}/{id}/accion/{action}` | POST | `{prefix}.ver` (acción fina en `CrudActionService`) |
| `/crud/{resource}/accion-masiva/{action}` | POST | `{prefix}.ver` (acción fina en servicio) |
| `/calendario/{key}` | GET | `{prefix}.ver` del CRUD fuente del calendario |
| `/calendario/{key}/eventos` | GET | `{prefix}.ver` (respuesta JSON 403 coherente) |

`{prefix}` = `permission_prefix` del JSON CRUD (`config/cruds/{resource}.json`). Calendario: resolver lee definición calendario → recurso CRUD vinculado → mismo prefix.

**Componentes nuevos (Framework):**

| Componente | Capa | Responsabilidad |
|------------|------|-----------------|
| `CrudRoutePermissionResolver` | Application | Mapeo puro request + nombre de ruta → slug; lanza `ValidationException` si recurso/key inválido |
| `CrudRbacMiddleware` | Presentation | Orquesta resolver + `RbacPolicy`; replica contrato respuesta de `RbacMiddleware` |
| `tests/Docs/CrudRbacRouterTest.php` | Tests | Assert rutas registran middleware; mensajes gate |
| `tests/Kernel/CrudRbacMiddlewareTest.php` | Tests | Dispatch sin permiso → 403 con slug en cuerpo/mensaje |

**Registro en rutas (harness + skeleton):**

```php
$crudRbac = [new CrudRbacMiddleware()];

$router->get('/crud/{resource}', ..., $crudRbac);
// ... resto rutas CRUD/calendario con mismo middleware
```

---

## Comportamiento esperado

1. Usuario **sin sesión** → sigue bloqueado por `AuthMiddleware` (redirect login); sin cambio.
2. Usuario autenticado **sin** `{prefix}.ver` → **403 antes del controlador** en list/show/calendario; mensaje incluye slug requerido (p. ej. «No tienes permiso… `demo_clientes.ver`»).
3. Usuario autenticado **sin** permiso de mutación → 403 en create/store/edit/delete con slug `{prefix}.crear|editar|eliminar`.
4. Peticiones AJAX (`Request::isAjax()`) → JSON 403 `{ "error": "Acceso denegado.", "permiso": "<slug>" }` (extensión opcional del contrato actual si no rompe clientes).
5. Acciones custom (`/accion/{action}`) → middleware exige `{prefix}.ver`; permiso fino de acción sigue en `CrudActionService` / `CrudActionResolver`.
6. **Doble verificación:** servicios (`CrudResourceService`, calendario use cases) **mantienen** `RbacService::verificar()` — no eliminar checks existentes.
7. Recurso CRUD inexistente / JSON inválido → comportamiento actual vía `ValidationException` (redirect flash), no 403 RBAC.

### Tests TDD (pre-implementación — deben fallar por motivo previsto)

1. **`CrudRbacRouterTest`** (Docs):
   - Assert: `routes/web.php` y `skeleton/routes/web.php` referencian `CrudRbacMiddleware` en al menos una ruta `/crud/`.
   - Assert: rutas `/calendario/` idem.
   - **Estado pre-implementación:** rojo — middleware ausente.
2. **`CrudRbacMiddlewareTest`** (Kernel):
   - Bootstrap mínimo con sesión mock sin permiso `demo_clientes.ver`.
   - Dispatch GET `/admin/crud/demo_clientes` → status 403 (no 200).
   - Assert mensaje/cuerpo menciona `demo_clientes.ver`.
   - **Estado pre-implementación:** rojo — middleware ausente o no registrado.

---

## Alcance

### Requisitos Framework (`Parzival2103/Lebytek_Framework`, base `main`)

| ID | Requisito | Capa / ruta |
|----|-----------|-------------|
| F1 | `CrudRoutePermissionResolver` — mapeo ruta/verbo → slug | `src/Application/Services/` |
| F2 | `CrudRbacMiddleware` — respuesta 403 alineada con `RbacMiddleware` | `src/Presentation/Middlewares/` |
| F3 | Registrar middleware en rutas CRUD + calendario | `routes/web.php`, `skeleton/routes/web.php` |
| F4 | Tests TDD `CrudRbacRouterTest`, `CrudRbacMiddlewareTest` | `tests/Docs/`, `tests/Kernel/` |
| F5 | Actualizar `config/rbac_route_permissions.php` + doc § RBAC CRUD en `docs/core/auth_rbac_seguridad_v0.1.md` | Docs/config harness |
| F6 | Tag semver patch **`v1.2.5`** post-merge (secuencia: M4 → `v1.2.4`, M3 → `v1.2.5`; si M4 no mergeado, M3 puede ser `v1.2.4` — coordinar en plan) | Release train |

### Requisitos Portal (`Parzival2103/Lebytek_Portal`, base `main`) — **no verificados (M6)**

| ID | Requisito | Notas |
|----|-----------|-------|
| P1 | Tras release Framework con F1–F3, bump `composer.lock` a tag ≥ release | Lock actual no inspeccionable |
| P2 | Si Portal define `routes/web.php` propio, mergear registro `CrudRbacMiddleware` en rutas CRUD/calendario | **No verificado** — clone Portal inaccesible |
| P3 | Recursos CRUD Portal (`dom_*`) ya declaran `permission_prefix` en JSON — sin cambio de contrato | Asumido por patrón Framework; **no verificado** |

### Requisitos Ops / staging

| ID | Requisito | Entorno |
|----|-----------|---------|
| O1 | Smoke manual: usuario rol restringido no accede a CRUD ajeno (403 antes de HTML CRUD) | Staging tenant |
| O2 | Ejecutar `php scripts/rbac_integrity_report.php` post-deploy — slugs CRUD router reflejados | Staging |

### Operaciones producción

**Fuera de esta corrida desatendida.** Bump Portal lock, deploy VPS y QA RBAC en producción requieren operador con acceso M6.

---

## No-alcance

- Slug `permisos.gestionar` en seeds (M5 / CF8) — spec futuro.
- `GET /api/health` público (M4) — spec/plan 2026-08-05 pendiente implementación.
- GitHub Actions CI (D7) — spec/plan 2026-08-04 pendiente implementación.
- Portal `afterListRows` / `mkt_leads` — spec/plan Portal 2026-08-03.
- Despliegue `skeleton.lebytek.com` (D6).
- Eliminar checks RBAC en `CrudResourceService` (regresión de seguridad).
- Token API / auth sin sesión en `/api/*` (M4 scope distinto).
- Automation M10 (recuperar audits 03–05 omitidos) — fuera de producto; ver spec artifact-chain.
- Código legacy `archive/backoffice-api-integration` como base de implementación.

---

## Ownership map

| Entregable | Repositorio | Rama base | Consumidor semver |
|------------|-------------|-----------|-------------------|
| F1–F4 middleware + tests | `Lebytek_Framework` | `main` | Tag patch ≥ `v1.2.5` (o `v1.2.4` si M4 no publicado) |
| F5 docs/config harness | `Lebytek_Framework` | `main` | Incluido en tag |
| P1–P2 merge rutas Portal | `Lebytek_Portal` | `main` | Post-tag Framework — **no verificado** |
| O1–O2 QA RBAC staging | Ops / tenant | staging | Manual |
| Producción VPS | Ops | prod | **Prohibido** en automation |

**Contratos públicos ausentes (no asumir):**

- No existe hoy middleware RBAC dinámico exportado en paquete — consumidores no deben asumir 403 router-level hasta tag release.
- Portal CRUD JSON (`dom_*`) — estructura `permission_prefix` verificada en harness demo; **no verificada** en Portal por M6.
- Legacy monolith (`archive/backoffice-api-integration`) usaba rutas bajo `/admin/crud/*` con RBAC en servicio — **histórico**, no contrato vigente.

---

## Dependencias y compatibilidad

| Dependencia | Impacto |
|-------------|---------|
| `CrudConfigLoader` | Resolver carga JSON para `{prefix}` — sin cambio de contrato |
| `RbacPolicy` / sesión `auth_permisos` | Igual que `RbacMiddleware` estático |
| Tag `v1.2.3` (actual) | Sin middleware CRUD router |
| Tag `v1.2.4` (plan M4 health) | Independiente; orden release a coordinar |
| Plan CI D7 | Tests F4 deben pasar en job `platform-fast-gates` cuando exista |
| `AuditArtifactFreshnessTest` | Sin interacción |

**Migración segura:**

- **Skeleton nuevo:** plantilla incluye F3 desde origen.
- **Harness / tenant existente:** merge aditivo en `routes/web.php`; sin SQL; usuarios sin permiso dejan de ver pantallas CRUD (comportamiento **correcto**, posible sorporte operativa si antes veían UI vacía/error tardío).
- **Portal existente:** P2 merge manual si sobrescribe rutas; bump lock semver.

**Semver / release Framework:**

- Cambio aditivo de middleware + 403 más temprano → **PATCH** semver.
- Publicar tag tras merge F1–F5; sincronizar trío versión (`composer.json`, `config/app.php`, `skeleton/config/app.php`).
- Portal consume capacidad vía `composer update lebytek/framework:^X.Y.Z` — **no** branch checkout en producción.

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Doble 403 (middleware + servicio) | Baja | Comportamiento idempotente; mismo slug |
| Recurso CRUD inválido confundido con 403 RBAC | Media | Resolver distingue `ValidationException` vs acceso |
| Portal no mergea rutas | Media | P1/P2 documentados; QA staging O1 |
| Acciones custom con permiso distinto a `.ver` | Media | Middleware gate `.ver`; servicio autoriza acción |
| Regresión performance (carga JSON por request) | Baja | Cache request-scope en middleware o loader existente |
| Orden release vs M4 (`v1.2.4` vs `v1.2.5`) | Baja | Plan implementation coordina numeración |
| gh Portal 404 impide validar P2 | Media | Marcar no verificado; no asumir merge Portal |

---

## Rollback

1. Revertir PR F1–F5 — desaparece middleware; RBAC vuelve a sólo servicio (estado actual).
2. Consumidores en tag con middleware: `composer require lebytek/framework:<tag-anterior>` + revert merge rutas si aplicó P2.
3. Sin migración SQL — rollback es revert Git + redeploy.
4. Tag semver: no yank automático; publicar patch revert si necesario.

---

## Criterios de aceptación

- [ ] **AC1:** Rutas `/crud/*` y `/calendario/*` en harness y skeleton registran `CrudRbacMiddleware`.
- [ ] **AC2:** Usuario sin `{prefix}.ver` recibe 403 **antes** de ejecutar `CrudController` / `CalendarioController`.
- [ ] **AC3:** Mensaje 403 incluye slug requerido (HTML flash o JSON).
- [ ] **AC4:** `CrudResourceService` y use cases calendario **mantienen** `RbacService::verificar()`.
- [ ] **AC5:** `php tests/run.php Docs/CrudRbacRouter` PASS post-implementación (rojo pre-implementación).
- [ ] **AC6:** `php tests/run.php Kernel/CrudRbacMiddleware` PASS post-implementación (rojo pre-implementación).
- [ ] **AC7:** Doc § RBAC CRUD + `rbac_route_permissions.php` actualizados (F5).
- [ ] **AC8:** Tag semver patch publicado; trío versión sincronizado; `PlatformVersionSemverTest` verde.
- [ ] **AC9:** Diff PR no incluye Marketing/Portal en `src/`; frontera FPS intacta; `SkeletonPurityTest` verde.

---

## Operaciones por entorno

| Operación | Implementación (dev) | Staging | Producción |
|-----------|---------------------|---------|------------|
| Merge PR F1–F5 | PR a `main` + tag patch | N/A | N/A |
| Smoke RBAC rol restringido | Manual harness | O1 operador | **Fuera automation** |
| Bump Portal lock | — | Operador post-tag | Operador manual — **fuera automation** |
| Deploy VPS | — | Tras QA staging | Operador manual — **fuera automation** |

---

## Deuda técnica (reconciliación post-audit 2026-08-06)

| ID | Hallazgo | Estado | Owner | Acción |
|----|----------|--------|-------|--------|
| **M3** | CRUD/calendario sin RBAC router | **Abierto → este spec** | Framework | F1–F6 |
| M4 | API sesión / health público | Abierto — spec/plan 0/5 | Framework | Plan 2026-08-05 |
| M5 | `permisos.gestionar` seeds | Abierto — sin spec | Framework | Spec futuro CF8 |
| M6 | Portal gh 404 | Abierto (entorno) | Ops | O1 credenciales |
| M10 | Hueco audits 03–05 | Abierto (proceso) | Ops/automation | Spec artifact-chain + corrida 00 diaria |
| D6 | skeleton.lebytek.com | Abierto — plan 0/5 | Ops | Plan 2026-07-26 |
| D7 | CI GitHub Actions | Abierto — plan 0/5 | Framework | Plan 2026-08-04 |
| M1, M9 | Semver sync, dompdf | **Resueltos** @ v1.2.3 | Framework | — |
| C2 Framework | Stripe subscriptions | **Resuelto** Framework | Portal QA **no verificado** | — |

**Requisitos marcados no verificados:** P1, P2, P3 (Portal); issues Portal; lock Portal ≥ v1.2.3; QA Stripe Portal.

---

*Report-only spec (AUTOMATION-01). Sin cambios en `src/`, `routes/`, `database/`, `skeleton/` ni `tests/` en esta corrida.*
