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
- Cadena artefactos audit (M7/M10 proceso): `docs/archive/superpowers/specs/2026-07-30-audit-artifact-chain-design.md` · test `tests/Docs/AuditArtifactFreshnessTest.php` (M7)
- Release integrity (M1/M9 resueltos): `docs/archive/superpowers/specs/2026-08-02-audit-v122-release-integrity-design.md` · tag `v1.2.3` @ `041e402`
- Inventario deuda: `docs/audits/2026-07-28-deuda-tecnica-inventario.md` · auditoría carry-forward `docs/audits/2026-08-02-auditoria-tecnica-diaria.md`
- Evidencia histórica alineación: `docs/archive/audits/correccion_alineacion_modulos_v0.1.md`, `docs/archive/audits/auditoria_crud_engine_v0.1.md` § RBAC por recurso en servicio
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
| Timestamp UTC | trigger cron `2026-08-06T12:10:00Z` / corrida agente `2026-08-06T12:10:00Z` / pase ux `2026-08-06T12:30:00Z` (modo **normal**) / pase deuda `2026-08-06T13:02:05Z` (modo **normal**) |
| Pase deuda | `2026-08-06T13:02:05Z` · modo **normal** · `origin/main` inspeccionado @ `ddc55ec8fb025acfada9500d711bbbe8843f5997` |
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
| D7 — sin CI GitHub Actions | Media | **159** tests `*Test.php` sin gate PR; F4 TDD no bloquea merge hasta plan `2026-08-04` (0/5) |
| `composer validate` lock content-hash | Baja | Auditoría 2026-08-06 documenta posible drift post-bump semver — **no verificado** (composer CLI ausente en agente); corregir con `composer update --lock` en release train, no reabre M1 |

---

## Rollback

1. Revertir PR F1–F5 — desaparece middleware; RBAC vuelve a sólo servicio (estado actual).
2. Consumidores en tag con middleware: `composer require lebytek/framework:<tag-anterior>` + revert merge rutas si aplicó P2.
3. Sin migración SQL — rollback es revert Git + redeploy.
4. Tag semver: no yank automático; publicar patch revert si necesario.

---

## Compatibilidad, UX y responsive

### Modo del pase: normal

Este spec introduce **middleware RBAC dinámico** en rutas admin `/crud/{resource}` y `/calendario/{key}`.
Superficie UI verificable: pantallas **403** (HTML flash + JSON AJAX), acceso temprano antes de renderizar
listados/formularios CRUD y calendario, y mensajes de error accionables con slug de permiso. No modifica layout
login/dashboard ni estilos globales; login responsive y nav admin permanecen carry-forward.

### Compatibilidad (verificado vs carry-forward)

| Área | Este spec (F1–F6) | Evidencia / carry-forward |
|------|-------------------|---------------------------|
| PHP soportado | Sin cambio runtime | `composer.json` exige `>=8.1`; VPS documentado PHP **8.4.22** CLI/pool (`2026-07-26-skeleton-package-staging-design.md`) — compatible; middleware y resolver no requieren extensiones nuevas. |
| Instalación vía `vendor/` | Contrato paquete semver | Consumidores obtienen `CrudRbacMiddleware` + resolver tras bump `lebytek/framework` al tag release (≥ patch post-F1–F6); registro en `routes/web.php` espejado desde harness/skeleton — **no** parche en `vendor/`. Portal con `routes/web.php` propio: merge manual P2 (**no verificado** M6). |
| Health sin cookie de sesión | Carry-forward **M4** | `routes/api.php` L14–16 — grupo `/api` + `AuthMiddleware`; `/api/ping` requiere sesión. Smoke LB: **no** usar `/api/ping`; backlog `GET /api/health` público (spec 2026-08-05, plan 0/5). |
| `.env.example` sin vars Portal | **Resuelto (M2)** — sin alcance | Root `.env.example` L55 remite vars `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` a Portal; middleware RBAC no introduce env vars nuevas. |
| Navegadores objetivo | Superficie admin 403 + CRUD | Baseline `docs/core/ui_ux.md`: admin breakpoint **992px (`lg`)**. Chrome, Firefox, Safari, Edge últimas 2 versiones + iOS Safari ≥ 15; sin IE11. Página 403 y flash deben ser legibles en **320–768px** sin overflow horizontal. |

### UX — flujos admin CRUD/calendario (F1–F6)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U1** | 403 HTML incluye **slug requerido** explícito (p. ej. «No tienes permiso… `demo_clientes.ver`») — operador distingue permiso faltante de error genérico | F2, AC3 |
| **U2** | Copy accionable: qué falló (acceso denegado) + qué hacer (solicitar permiso al administrador o revisar rol en **Usuarios/Roles**) — no sólo «Acceso denegado» | F2, CF10 parcial |
| **U3** | Peticiones AJAX (`Request::isAjax()`): JSON 403 `{ "error": "Acceso denegado.", "permiso": "<slug>" }` — coherente con `RbacMiddleware` y extensible sin romper clientes existentes | F2 |
| **U4** | Recurso CRUD/key calendario **inválido** → `ValidationException` + redirect flash (comportamiento actual) — **no** confundir con 403 RBAC; mensaje indica recurso inexistente, no permiso | F1 |
| **U5** | `CrudRbacRouterTest`: mensaje de fallo cita spec M3, archivo `routes/web.php` y acción («registrar `CrudRbacMiddleware` en rutas `/crud/` y `/calendario/`») | F4 |
| **U6** | `CrudRbacMiddlewareTest`: fallo sin implementación indica estado actual («middleware ausente o no registrado») y slug esperado en assert | F4 |
| **U7** | Calendario JSON (`/calendario/{key}/eventos`): 403 con mismo contrato U3 — no HTML en respuesta AJAX | F2, F3 |
| **U8** | Doc § RBAC CRUD (`auth_rbac_seguridad_v0.1.md`) + `config/rbac_route_permissions.php`: operador ve slugs CRUD reflejados en capa router para auditoría `rbac_integrity_report.php` | F5 |

### UX — instalación y operaciones (sin cambio directo)

| Requisito | Criterio | Estado |
|-----------|----------|--------|
| **U9** | Wizard install / smoke post-tag: usuario rol restringido que antes veía listado CRUD vacío ahora recibe 403 temprano — doc O1 indica comportamiento **esperado** (seguridad correcta), no regresión | O1 |
| **U10** | Bump Framework fallido: mensaje CLI indica versión mínima del tag release y acción («composer update lebytek/framework») | P1 carry-forward |

### Responsive — smoke en superficies tocadas

Referencia: `docs/core/ui_ux.md` §542 — breakpoint admin **992px (`lg`)**; tablas CRUD exigen `table-responsive` (`ui_ux.md` L222).

| Superficie | Verificación post-merge | Rango |
|------------|-------------------------|-------|
| Página 403 / flash RBAC | Mensaje y enlace «volver» legibles; sin scroll horizontal; tap targets ≥44px en acciones | **320–768px** |
| Listado CRUD (usuario **con** permiso) | Sin regresión: `table-responsive` + scroll horizontal en columnas secundarias | **320–768px** |
| Calendario admin (usuario **con** permiso) | Vista calendario usable; eventos AJAX degradan con 403 JSON legible en móvil | **320–768px** |
| Login / dashboard nav (sin alcance directo) | Carry-forward CF3–CF4 — smoke opcional post-merge | **320–768px** |

### Carry-forward UX — próximo spec con superficie UI más amplia

Ítems derivados de deuda abierta; **CF6 (RBAC router CRUD/calendario) queda cubierto por este spec** — no arrastrar.
CF1–CF2 (semver harness + env purge), CF5 parcial (`mkt_leads` spec 2026-08-03) y D7 (CI spec 2026-08-04) tampoco se arrastran.

| # | Ítem | Origen | Requisito concreto |
|---|------|--------|-------------------|
| CF3 | Login responsive 320–768px | `ui_ux.md` | `.ct-login-page`, `.ct-login-card` sin overflow horizontal; tap targets ≥44px; sin scroll lateral en 320px. |
| CF4 | Dashboard admin responsive 320–768px | layouts side/top/bottom | Nav colapsable; KPI grid legible; topbar sin solapamiento de acciones. |
| CF5′ | Tablas CRUD restantes | módulo CRUD, D6 | `table-responsive` + `list.columns[].priority` en recursos distintos de `mkt_leads`; toolbar móvil. |
| CF7 | Health endpoint público | M4 | `GET /api/health` 200 sin cookie; body `{ "status": "ok" }`; checklist VPS — spec/plan 2026-08-05 (**0/5**). |
| CF8 | Permisos admin catálogo | M5 | Slug `permisos.gestionar` en seeds; UI permisos sin workaround `administracion.ver`. |
| CF9 | Estados vacío / error / carga (global) | `ui_ux.md` §8 | Unificar empty states en CRUDs sin hook; spinners list; validación con hint de corrección — más allá de U4. |
| CF10 | Copy errores accionables (transversal) | transversal | Auth, wizard install, CRUD save: qué falló + qué hacer — extiende U2 fuera de RBAC 403. |

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

### Compatibilidad, UX y responsive

- [ ] **AC-UX1:** Sección **Compatibilidad, UX y responsive** declara modo **normal** con requisitos K/U/R verificables para F1–F6 (403 accionables, CRUD/calendario admin).
- [ ] **AC-UX2:** Requisitos U1–U8 (slug en 403, copy accionable, JSON AJAX coherente, distinción ValidationException, hints test gate, doc rbac_route_permissions) incluidos como criterios del spec.
- [ ] **AC-UX3:** Carry-forward CF3–CF4, CF5′, CF7–CF10 documentado; CF6 no arrastrado (cubierto por este spec); CF1–CF2, CF5 parcial y D7 no arrastrados (resueltos o cubiertos en specs previos).
- [ ] **AC-UX4:** Smoke responsive en **320–768px** para página 403/flash RBAC y listados CRUD/calendario accesibles post-implementación (sin regresión `table-responsive`).

### Deuda técnica (inventario)

- [ ] **AC-D1:** Sección **Deuda técnica** lista abiertos verificados (M4, M5, D6, D7, M10) con evidencia ruta/línea en `main` @ `ddc55ec`.
- [ ] **AC-D2:** M1, M2, M7, M8, M9, D1–D5, D13 reconciliados como **resueltos**; M3 **abierto → este spec** (F1–F6) hasta merge implementación.
- [ ] **AC-D3:** P1, P2, M6/D3, D14, D15 marcados **no verificados** Portal; acción concreta documentada.
- [ ] **AC-D4:** Verificado sin deuda nueva — migraciones 3 SQL ↔ 3 entradas manifiesto; `src/` sin `TODO`/`FIXME`; referencias operativas vivas a `feature/backoffice-api-integration` ausentes en `scripts/`, `docs/composer-setup.md`, `docs/integration/`; `despliegue-y-versionado.md` sin § Monitoreo/§ CI (gap planificado M4 F6 / D7 F5, no drift pre-implementación).

---

## Operaciones por entorno

| Operación | Implementación (dev) | Staging | Producción |
|-----------|---------------------|---------|------------|
| Merge PR F1–F5 | PR a `main` + tag patch | N/A | N/A |
| Smoke RBAC rol restringido | Manual harness | O1 operador | **Fuera automation** |
| Bump Portal lock | — | Operador post-tag | Operador manual — **fuera automation** |
| Deploy VPS | — | Tras QA staging | Operador manual — **fuera automation** |

---

## Deuda técnica

Fuente: auditoría `docs/audits/2026-08-06-auditoria-tecnica-diaria.md` (PR #84 @ `5d6df2f`, mergeado `ddc55ec`); reconciliación con inventario spec `2026-08-05` (pase deuda @ `42c3a0a`) y tip `origin/main` @ `ddc55ec` (pase deuda 2026-08-06).

### Reconciliación heredada (cerrados)

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| **M1** | Sync semver | **Resuelto** | #74 + `v1.2.3` @ `041e402`; `composer.json` L6, `config/app.php` L7, `skeleton/config/app.php` L7 → `1.2.3` @ `ddc55ec`; `PlatformVersionSemverTest` presente |
| **M2** | `.env.example` Portal vars | **Resuelto** | #62 — root `.env.example` L55 comentario explícito; keys activas `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` = **0**; `skeleton/.env.example` sin vars Portal |
| **M9** | dompdf advisories | **Resuelto** | #74 — `composer.lock` fija `dompdf/dompdf` **v3.1.6**; `DompdfSecurityVersionTest` presente |
| **D1–D5, D13** | Semver harness / env / checklist | **Resuelto** | #62 + #74 — ver M1/M2 |
| **M7/M8** | Audit lifecycle / ops docs | **Resuelto** | PRs #54–#67, #56/#57 + `OpsDocsFpsAlignmentTest` |
| **C1** | Scripts `vps-deploy-*` destructivos | **Resuelto** | PR #36; `DeployScriptsRemovedTest` verde |
| **C2** | Stripe subscription (Framework) | **Resuelto** Framework | PR #42 + tags `v1.2.1`…`v1.2.3`; `vertical.payments=false` @ `config/vertical.php` L22 — QA Portal **no verificado** (M6) |
| **C3** | Bootstrap marketing Portal | **Re-scopeado** | `Lebytek_Portal#4` — no inspeccionable aquí |

**Cierres desde corrida anterior (2026-08-05 pase deuda @ `42c3a0a`):** **0** — intervalo `42c3a0a..ddc55ec` en `main` sólo añadió docs automation/spec/plan/audit (#81–#84); sin cambios de código que cierren M3–M6, D6/D7 ni M10.

### Alcance principal de este spec (M3 — abierto, verificado)

| ID | Hallazgo | Evidencia (`main` @ `ddc55ec`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M3** | CRUD/Calendario sin `RbacMiddleware` router | `routes/web.php` L114–125 — `/crud/{resource}*`, `/calendario/{key}*` sin middleware RBAC; contraste L127–139 (pdf-kit, reportes **sí** usan `RbacMiddleware`); `skeleton/routes/web.php` espejo idéntico; RBAC fino permanece en `CrudResourceService.php` L49, L110, L132…; `CrudController.php` L41–42, L55–56 → `AccesoException` → `Response::forbidden()`; `RbacMiddleware.php` L30–36 → JSON 403 AJAX; `CalendarioController.php` L37–38, L59–60; `rg 'CrudRbac\|crud.*RbacMiddleware' tests/` → **0**; `CrudRbacRouterTest` / `CrudRbacMiddlewareTest` **ausentes** | Defensa en profundidad débil; 403 inconsistentes HTML/JSON vs resto admin; `rbac_integrity_report.php` no refleja slugs CRUD en router | `Presentation` / `routes/` | Framework | F1–F6 + tag patch semver (plan `2026-08-06-audit-crud-rbac-router.md`, **0/5** tareas) |

### Backlog Framework verificado (fuera alcance F1–F6)

| ID | Hallazgo | Evidencia (`main` @ `ddc55ec`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M4** | `/api/*` autenticada por sesión; sin health público | `routes/api.php` L14–16 grupo `AuthMiddleware`; L23 `/api/ping` dentro del grupo; `skeleton/routes/api.php` espejo; `rg '/health' routes/` → **0**; `HealthController.php` L13–16 sólo `ping()` — método `health()` **ausente**; `AuthMiddleware.php` L21–24 redirect `/login` (302) sin JSON 401; `ApiHealthPublicRouteTest` / `ApiHealthPublicDispatchTest` **ausentes** | LB/cron/hosting no verifican liveness sin cookie | `Presentation` / `routes/` | Framework | Plan `2026-08-05-audit-api-health-public.md` (**0/5** tareas) |
| **M5** | Slug `permisos.gestionar` ausente | `routes/web.php` L61–65 — comentario + workaround `administracion.ver`; `rg permisos.gestionar database/` → **0** | Catálogo RBAC acoplado a permiso amplio | `Domain` RBAC | Framework | Spec futuro CF8 |
| **D6** | Plan `skeleton.lebytek.com` sin implementar | `docs/ENVIRONMENTS.md` L6, L13, L31, L63 — «skeleton.lebytek.com pendiente»; plan `2026-07-26-skeleton-package-staging.md` Tasks 2–10 sin deploy | LAB package puro no desplegado | Ops / Framework | Framework/Ops | Ejecutar plan humano Tasks 6–8 |
| **D7** | Sin pipeline GitHub Actions | `git ls-tree origin/main .github` → vacío; `tests/Docs/CiWorkflowPresentTest.php` **ausente**; `docs/core/despliegue-y-versionado.md` sin § CI; **159** archivos `*Test.php` bajo `tests/` sin gate CI en PR | Regresiones semver/dompdf/RBAC no bloqueadas en PR | Ops / repo | Framework/Ops | Plan `2026-08-04-audit-platform-ci-gates.md` (**0/5** tareas) |
| **M10** | Hueco auditorías 2026-08-03..05 | `docs/audits/` sin archivos `2026-08-03`/`08-04`/`08-05`; closures automation confirman paso 00 omitido; audit #84 cierra hueco para 2026-08-06 | Cadena 01–08 diseñó specs sin ancla audit diaria; deuda implementación acumulada | Proceso automation | Ops/automation | `AuditArtifactFreshnessTest` (M7) + corrida 00 diaria |

### Planes activos — estado ejecución real

| Plan | Tareas | Estado @ `ddc55ec` |
|------|--------|-------------------|
| `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` | 0/5 | Pendiente — Task 1 `CrudRbacRouterTest` (este spec) |
| `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` | 0/5 | Pendiente — Task 1 `ApiHealthPublicRouteTest` |
| `docs/superpowers/plans/2026-08-04-audit-platform-ci-gates.md` | 0/5 | Pendiente — Task 1 `CiWorkflowPresentTest` |
| `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md` | 0/5 | Pendiente — requiere clone Portal (M6) |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ `v1.2.3` / handler `afterListRows` | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL fail; última evidencia `composer.lock` Portal con `lebytek/framework` **v1.1.0** @ `a79d3ad` | Plan Portal Task 1 cuando M6 resuelva |
| **P2** | Portal `routes/web.php` merge CRUD RBAC | Clone Portal inaccesible | Operador verifica post-release Framework que registro `CrudRbacMiddleware` no se pierda en merge |
| **P3** | Portal CRUD JSON `permission_prefix` | Repo Portal inaccesible | Asumido por patrón harness; validar en staging O1 |
| **M6 / D3** | Portal SHA / `composer.lock` | `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 | Ops: conceder lectura Portal al token automation |
| **D14** | Stripe subscription QA Portal | Repo Portal inaccesible; Framework `vertical.payments=false` | Portal: QA checkout antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` |
| **D15** | Bootstrap marketing Portal | Re-scopeado `Lebytek_Portal#4` — no inspeccionable aquí | Portal issue #4 |
| **H1** | `composer validate` lock content-hash | Composer CLI **ausente** en agente cloud | Ejecutar en release train humano; auditoría #84 documenta posible drift post-bump semver — no reabre M1 |

### Verificado sin deuda nueva

- **Migraciones ↔ manifiesto:** 3 archivos en `database/migrations/` ↔ 3 entradas en `config/modules/core.php` L16, `crud-engine.php` L15, `pdf-kit.php` L16 — sin drift.
- **`src/`:** grep `TODO`/`FIXME` → **0** con impacto; sin `LebytekApiClient` ni Marketing.
- **Capas:** hook `afterListRows` en `Application`; Domain sin deps Presentation/Infrastructure.
- **Legacy operativo:** referencias vivas a `feature/backoffice-api-integration` o `dev-feature/backoffice-api-integration` **ausentes** en `scripts/`, `docs/composer-setup.md`, `docs/integration/`.
- **Payments bootstrap:** `vertical.payments=false` en harness; requisitos Stripe documentados como gate ops Portal (D14), no auto-fix en `src/`.
- **Semver/dompdf:** tres fuentes `1.2.3`; lock dompdf `v3.1.6` — sin regresión M1/M9.
- **Doc pre-implementación:** `despliegue-y-versionado.md` sin § Monitoreo (M4 F6) ni § CI (D7 F5) — gap planificado, no drift doc↔código.

**Conteo:** **5 abiertos verificados** en backlog (M4, M5, D6, D7, M10) + **M3 pendiente implementación** (alcance este spec); **7 no verificados** (P1, P2, P3, M6/D3, D14, D15, H1); **0 heredados cerrados** esta corrida.

---

*Report-only spec (AUTOMATION-01). Sin cambios en `src/`, `routes/`, `database/`, `skeleton/` ni `tests/` en esta corrida.*
