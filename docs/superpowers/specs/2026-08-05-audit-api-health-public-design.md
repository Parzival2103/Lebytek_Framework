# Design: Endpoint público `GET /api/health` para liveness (M4)

**Fecha:** 2026-08-05  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel C)

**Auditoría fuente:** `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (fecha real del reporte: 2026-08-02, mergeado en `main` vía PR #67 @ `d372ad8`)  
**Nota corrida:** no hubo auditoría del día 2026-08-05; el reporte más reciente en `origin/main` sigue siendo 2026-08-02. Reconciliación post-audit en tip `main` @ `42c3a0a`: **M1/M9 resueltos** (#74 + tag `v1.2.3`); **D7** con spec/plan activo sin implementación; hallazgo accionable prioritario **sin spec dedicado previo**: **M4** (health LB/cron bloqueado por sesión).

**Specs/planes relacionados (no duplicar):**

- CI gates (D7): `docs/superpowers/specs/2026-08-04-audit-platform-ci-gates-design.md` · plan `docs/superpowers/plans/2026-08-04-audit-platform-ci-gates.md` (0/5 tareas)
- Release integrity (implementado): `docs/archive/superpowers/specs/2026-08-02-audit-v122-release-integrity-design.md`
- Portal afterListRows (diseño): `docs/superpowers/specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md` · plan `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md` (0/5 tareas)
- Skeleton staging (D6, separado): `docs/superpowers/specs/2026-07-26-skeleton-package-staging-design.md`
- Inventario deuda: `docs/audits/2026-07-28-deuda-tecnica-inventario.md` § D3/M4
- Evidencia histórica Fase 3 (archivada): `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md` § `/api/health`
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `42c3a0a4d0fafacd24d8632ca6e77c00836da79f` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-05` |
| Timestamp UTC | trigger cron `2026-08-05T12:10:02Z` / corrida agente `2026-08-05T12:15:00Z` / pase ux `2026-08-05T12:30:01Z` (modo **sin superficie UI**) / pase deuda `2026-08-05T13:02:28Z` (modo **normal**) |
| Pase deuda | `2026-08-05T13:02:28Z` UTC; SHA `origin/main` `42c3a0a4d0fafacd24d8632ca6e77c00836da79f`; modo **normal** |
| Nivel de fuente | **C** — no hubo auditoría del día 2026-08-05; reporte más reciente en `origin/main`: `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (fecha real 2026-08-02). Nivel A: `gh pr list --repo Parzival2103/Lebytek_Framework --state open --base main --search "docs(audit):"` → vacío. Nivel B: no existe `origin/automation/audit-2026-08-05`; rama `origin/automation/audit-2026-08-02` ya mergeada en `main` (reporte presente @ `docs/audits/2026-08-02-auditoria-tecnica-diaria.md`). |
| PR auditoría fuente | #67 — mergeado 2026-08-02; head histórico `a8331573ec94d65621dd77512ec7ccaf522af035` |
| headRefOid fuente | `a8331573ec94d65621dd77512ec7ccaf522af035` (rama audit; no heredada) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |

---

## Problema

La auditoría del 2026-08-02 (M4, carry-forward desde 2026-07-27) documenta que **todas** las rutas bajo el prefijo `/api` exigen sesión activa vía `AuthMiddleware`. El único endpoint de salud existente, `GET /api/ping`, queda dentro del grupo autenticado — por lo que load balancers, paneles de hosting y cron externos **no pueden** verificar liveness sin cookie de sesión.

**Evidencia verificada en tip `main` @ `42c3a0a`:**

| Comprobación | Resultado |
|--------------|-----------|
| `routes/api.php` L14–16 | Grupo `/api` con `'middlewares' => [AuthMiddleware::class]` |
| `routes/api.php` L23 | `GET /api/ping` → `HealthController::ping` **dentro** del grupo auth |
| `skeleton/routes/api.php` | Espejo idéntico del harness (mismo grupo + `/ping`) |
| `src/Presentation/Controllers/Api/HealthController.php` | Método `ping()` retorna JSON `{status, timestamp}` — reutilizable |
| `src/Presentation/Middlewares/AuthMiddleware.php` L21–24 | Sin sesión → `Response::redirect('/login')` (302), no JSON 401 |
| `GET /api/health` público | **Ausente** — `rg '/health' routes/` → 0 matches |
| Tests health/API | **Ausentes** — `rg -l 'api/health\|HealthController' tests/` → 0 |
| Semver plataforma | `1.2.3` sincronizado en `composer.json`, `config/app.php`, `skeleton/config/app.php` (M1 resuelto) |
| `dompdf/dompdf` lock | `v3.1.6` (M9 resuelto) |
| `.github/workflows/` | **Ausente** (D7 — spec/plan CI pendiente implementación) |
| PHP CLI en agente cloud | **Ausente** — no se re-ejecutó suite; clasificado bloqueador entorno |

**Consecuencia operativa:** operadores y automation que usan `curl -sf https://<host>/api/ping` obtienen redirect a login o falso negativo — no un 200 JSON de liveness. El spec CI D7 (2026-08-04) documenta explícitamente **no** usar `/api/ping` como health check externo hasta resolver M4 (CF7). VPS checklists que referencian health de **WhatsApi** (`GET /api/v1/health` con token) no aplican al tenant PHP del framework — son contratos distintos.

**Deuda carry-forward registrada (fuera de alcance inmediato de este spec):** M3 (CRUD RBAC router), M5 (`permisos.gestionar` seeds), M6 (gh Portal 404), D6 (`skeleton.lebytek.com`), D7 (CI GitHub Actions — spec/plan separado).

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

- **Propósito:** exponer un endpoint HTTP **público** de liveness (`GET /api/health`) consumible por LB/cron/hosting sin cookie, distinguiéndolo de `/api/ping` (autenticado, uso interno post-login).
- **Restricciones:** package source no desplegable; no token API de plataforma en esta fase (M4 scope = liveness only); no filtrar secretos ni estado BD en respuesta pública; legacy `archive/backoffice-api-integration` solo evidencia histórica; operaciones VPS/producción fuera de automation desatendida; Portal hereda el contrato vía `composer update`, no parche en `vendor/`.
- **Éxito Framework:** `GET /api/health` → 200 JSON mínimo sin sesión; `/api/ping` sigue protegido; test gate TDD rojo→verde; doc § monitoreo en `despliegue-y-versionado.md`; skeleton template alineado.
- **Éxito consumidor:** tras bump `lebytek/framework` ≥ tag que incluya el cambio, tenants Portal/skeleton pueden usar la misma URL relativa `/api/health` en checklists VPS.

### Enfoques evaluados

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — Ruta pública + método `health()` dedicado** | Registrar `GET /api/health` **fuera** del grupo auth; nuevo método en `HealthController` con body fijo `{ "status": "ok" }` | Contrato claro; ping auth intacto; superficie mínima | Dos endpoints health (documentar diferencia) |
| **B — Mover `/ping` fuera del grupo auth** | Sacar `/api/ping` del middleware auth | Un solo endpoint | Rompe expectativa de ping autenticado; breaking change para clientes que asumen sesión |
| **C — Health con checks profundos (DB/Redis)** | `/api/health` consulta PDO/Redis y expone `checks.*` | Readiness real | Scope M4 es liveness; mezcla ops; riesgo filtrar estado infra en endpoint público |

**Recomendación:** **A** — ruta pública `/api/health` con método `health()` en `HealthController`. Mantener `/api/ping` autenticado para smoke interno post-login. Rechazar C en este spec: readiness profundo puede ser spec futuro (`GET /api/ready` con auth o token). Rechazar B: breaking change innecesario.

### Esbozo del diseño

```text
Request GET /api/health (sin cookie)
    │
    ├─► NO pasa AuthMiddleware (registrada antes del group /api)
    │
    └─► HealthController::health()
            └─► 200 JSON { "status": "ok" }
                (sin timestamp obligatorio — payload ≤ 200 bytes)

Request GET /api/ping (sin cookie)
    │
    └─► AuthMiddleware → 302 /login  (sin cambio)
```

**Registro de rutas propuesto (`routes/api.php` y `skeleton/routes/api.php`):**

```php
// Público — liveness LB/cron (M4). NO usar /api/ping para monitoreo externo.
$router->get('/api/health', [HealthController::class, 'health']);

$router->group([
    'prefix'      => '/api',
    'middlewares' => [AuthMiddleware::class],
], function ($router) {
    $router->get('/ping', [HealthController::class, 'ping']);
});
```

---

## Comportamiento esperado

### Framework — endpoint público

1. `GET /api/health` responde **200** con `Content-Type: application/json`.
2. Body JSON mínimo: `{ "status": "ok" }` (camelCase; sin datos de sesión, versión semver ni checks de BD).
3. **No** requiere cookie, header `Authorization`, ni CSRF.
4. `GET /api/ping` **sin** sesión sigue redirigiendo a `/login` (302) — comportamiento actual preservado.
5. `GET /api/ping` **con** sesión válida sigue retornando `{ "status": "ok", "timestamp": "<ISO8601>" }`.
6. Respuesta pública **no** debe incluir stack traces ni paths de filesystem en producción (`app.debug=false`).

### Test gate TDD (debe existir antes de considerar implementación completa)

1. Nuevo test `tests/Docs/ApiHealthPublicRouteTest.php` (suite Docs — contrato de rutas documentado):
   - Assert: `routes/api.php` contiene registro de `/api/health` **antes** del `$router->group` con `AuthMiddleware`.
   - Assert: `skeleton/routes/api.php` espeja el mismo contrato.
   - Assert: `HealthController` declara método público `health`.
2. Nuevo test `tests/Kernel/ApiHealthPublicDispatchTest.php` (dispatch sin sesión):
   - Bootstraps mínimo: `Router` + carga `routes/api.php` + `Request` GET `/api/health` sin cookie.
   - Assert: status 200; body JSON decode → `status === 'ok'`.
   - Assert: `Request` GET `/api/ping` sin sesión **no** retorna 200 JSON ok (redirect o no-200).
3. **Estado pre-implementación:** ambos tests **rojos** — `/api/health` ausente; motivo previsto documentado.
4. **Estado post-implementación:** ambos tests **verdes**.

### Contratos públicos ausentes (no asumir APIs legacy)

- **No existe** hoy contrato HTTP público de liveness en el paquete — consumidores no deben asumir `/api/health` hasta tag semver que lo incluya.
- Legacy monolito (`archive/backoffice-api-integration` @ `4789f95`) puede haber tenido patrones distintos — **solo evidencia histórica**; no copiar sin revisión FPS.
- WhatsApi `GET /api/v1/health` (token Bearer) es contrato **repo separado** `WhatsApiLebytek` — no confundir con tenant PHP framework.
- Portal Lebytek API client (`LebytekApiClient::getTenant`) vive en **`Lebytek_Portal`** — fuera de alcance.

---

## Alcance

| ID | Requisito | Owner | Repo / rama base |
|----|-----------|-------|------------------|
| F1 | Registrar `GET /api/health` fuera del grupo `AuthMiddleware` en `routes/api.php` | Framework | `Lebytek_Framework` / `main` |
| F2 | Añadir `HealthController::health()` retornando JSON `{ "status": "ok" }` | Framework | idem — `src/Presentation/Controllers/Api/` |
| F3 | Espejar F1 en `skeleton/routes/api.php` | Framework | idem |
| F4 | Test gate `ApiHealthPublicRouteTest` (contrato rutas) | Framework | `tests/Docs/` |
| F5 | Test gate `ApiHealthPublicDispatchTest` (dispatch sin sesión) | Framework | `tests/Kernel/` |
| F6 | Documentar § Monitoreo / health en `docs/core/despliegue-y-versionado.md`: distinguir `/api/health` (público) vs `/api/ping` (auth) | Framework | idem |

### Requisitos Portal (consumidor — fuera de implementación Framework)

| ID | Requisito | Owner | Repo / rama base | Notas |
|----|-----------|-------|------------------|-------|
| P1 | Tras release Framework con F1–F2, bump `composer.lock` a tag semver que incluya `/api/health` | Portal | `Lebytek_Portal` / `main` | **No verificado** (M6) |
| P2 | Actualizar checklist VPS Portal si referencia `/api/ping` como health externo | Portal/Ops | `Lebytek_Portal` docs | Manual post-bump |

### Semver / release Framework

- F1–F6 modifican rutas HTTP y controlador en `src/` — **requieren** tag semver patch (p. ej. **`v1.2.4`**) tras merge a `main`.
- Sincronizar tres fuentes versión + content-hash lock según checklist `despliegue-y-versionado.md` (lección M1).
- Portal que consuma la capacidad debe actualizar `composer.lock` al tag publicado — frontera semver explícita.
- Consumidores en `v1.2.3` **no** tienen `/api/health` hasta bump.

---

## No-alcance

- Token API de plataforma, OAuth, o autenticación Bearer para rutas `/api/*` generales (backlog M4 ampliado — spec futuro).
- Readiness con checks de BD/Redis/colas en endpoint público (spec futuro `GET /api/ready` o similar).
- RBAC router-level en CRUD/calendario (M3 / CF6).
- Slug `permisos.gestionar` en seeds (M5 / CF8).
- CI GitHub Actions (D7 — spec/plan `2026-08-04` separado).
- Deploy VPS, configuración LB en producción, SSH, `.env` prod.
- Cambios en `Lebytek_Portal`, `WhatsApiLebytek` en esta corrida.
- Merge o referencia a `feature/backoffice-api-integration` como base.

---

## Ownership map

| Componente | Repositorio | Capa / ruta | Notas |
|------------|-------------|-------------|-------|
| Ruta pública health | `Lebytek_Framework` | `routes/api.php` | Harness; copiada a consumidores |
| Controlador health | `Lebytek_Framework` | `src/Presentation/Controllers/Api/HealthController.php` | Presentation — sin lógica de negocio |
| Template skeleton | `Lebytek_Framework` | `skeleton/routes/api.php` | Nuevos tenants |
| Test contrato Docs | `Lebytek_Framework` | `tests/Docs/ApiHealthPublicRouteTest.php` | TDD gate |
| Test dispatch Kernel | `Lebytek_Framework` | `tests/Kernel/ApiHealthPublicDispatchTest.php` | Sin MySQL |
| Doc monitoreo | `Lebytek_Framework` | `docs/core/despliegue-y-versionado.md` | § Monitoreo |
| Checklist VPS tenant | `Lebytek_Portal` | docs ops Portal | **No verificado** — post-bump manual |
| Health WhatsApi | `WhatsApiLebytek` | API propia | Contrato distinto con Bearer |

---

## Dependencias y compatibilidad

| Dependencia | Versión / estado | Impacto |
|-------------|------------------|---------|
| PHP | >=8.1 (`composer.json`) | Sin extensiones nuevas |
| `HealthController` existente | `src/` @ `42c3a0a` | Extender, no reemplazar |
| `AuthMiddleware` | Sin cambio de comportamiento | `/api/ping` sigue protegido |
| `lebytek/framework` consumidores | Lock < tag release | Deben bump para obtener `/api/health` |
| MySQL / sesión | No requeridos para liveness | Test dispatch sin BD |

**Migración segura:**

- **Base nueva (skeleton):** incluye ruta pública desde plantilla; smoke `curl -sf localhost/api/health` tras `php -S`.
- **Base harness existente:** merge añade ruta aditiva; sin migración SQL; sin breaking change en `/api/ping`.
- **Portal existente:** tras `composer update lebytek/framework` al tag release, verificar que `routes/api.php` del Portal no sobrescriba `/api/health` (si Portal define `routes/api.php` propio, **merge manual** de la línea pública — **no verificado** en clone Portal por M6).

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Exponer información sensible en `/api/health` | Media | Body mínimo fijo; sin versión/BD en v1 |
| Confundir `/api/health` con `/api/ping` | Media | F6 documenta tabla comparativa; mensajes test gate accionables |
| Portal no mergea ruta al actualizar framework | Media | P2 checklist; documentar en release notes |
| Endpoint público habilita fingerprinting | Baja | Respuesta genérica estándar industria |
| LB sigue apuntando a `/api/ping` | Media | Comunicación ops; no auto-cambiar VPS en automation |
| Regresión semver post-release | Media | Checklist 5 pasos + `PlatformVersionSemverTest` |
| Spec duplica trabajo CI D7 | Baja | D7 referencia `/api/health` para smoke; implementaciones independientes |
| Doc § Monitoreo ausente pre-F6 | Baja | `docs/core/despliegue-y-versionado.md` — `rg 'health\|ping\|Monitoreo' docs/core/despliegue-y-versionado.md` → **0** @ `42c3a0a`; operadores sin copy-paste oficial hasta F6 |
| Redirect 302 vs JSON 401 en `/api/ping` sin sesión | Baja | `AuthMiddleware` L21–24 retorna redirect HTML, no 401 JSON — confunde monitoreo naive; F6 documenta comportamiento esperado |
| Gates health/CI sin test gate previo | Media | `tests/Docs/ApiHealthPublicRouteTest.php` y `CiWorkflowPresentTest.php` **ausentes** — regresión silenciosa hasta F4–F5 / plan D7 |

---

## Rollback

1. Revertir PR F1–F6 — desaparece `/api/health`; `/api/ping` sin cambio.
2. Reconfigurar LB/cron a estado anterior (operador manual — fuera automation).
3. Portal: mantener lock anterior si rollback de bump (operador manual).
4. Tag semver: no yank automático; publicar patch revert si necesario.

---

## Compatibilidad, UX y responsive

### Modo del pase: sin superficie UI

Este spec añade exclusivamente un **endpoint JSON público** (`GET /api/health`) y documentación
operativa § Monitoreo. No introduce ni modifica pantallas login/dashboard/CRUD, assets CSS ni layouts
admin. **Sin superficie UI en este spec.**

Los requisitos K/U/R siguientes documentan (a) compatibilidad verificada para el alcance API/harness y
(b) **carry-forward UX** — ítems concretos que el próximo spec con superficie UI debe cubrir, derivados
de deuda abierta real (M3–M6, D6; M1/M2/M9 resueltos; D7 cubierto por spec 2026-08-04; **CF7 cubierto
por este spec**).

### Compatibilidad (verificado vs carry-forward)

| Área | Este spec (F1–F6) | Evidencia / carry-forward |
|------|-------------------|---------------------------|
| PHP soportado | Sin cambio runtime | `composer.json` exige `>=8.1`; VPS documentado PHP **8.4.22** CLI/pool (`2026-07-26-skeleton-package-staging-design.md`) — compatible; `HealthController::health()` no requiere extensiones nuevas. |
| Instalación vía `vendor/` | Contrato paquete semver | Consumidores obtienen `/api/health` tras bump `lebytek/framework` al tag release (≥ patch post-F1–F6); ruta vive en `routes/api.php` copiado/espejado desde paquete — **no** parche en `vendor/`. Portal con `routes/api.php` propio: merge manual línea pública (P2, **no verificado** M6). |
| Health sin cookie de sesión | **Alcance principal M4** | Hoy: `routes/api.php` L14–16 — grupo `/api` + `AuthMiddleware`; `/api/ping` redirige a login (L21–24 `AuthMiddleware`). Post F1: `GET /api/health` **fuera** del grupo → 200 JSON `{ "status": "ok" }` sin cookie, CSRF ni `Authorization`. Smoke: `curl -sf https://<host>/api/health`. |
| `.env.example` sin vars Portal | **Resuelto (M2)** — sin alcance | Root `.env.example` L55 remite vars `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` a Portal; endpoint health no introduce env vars nuevas. |
| Navegadores objetivo | N/A en este spec | Carry-forward: Chrome, Firefox, Safari, Edge últimas 2 versiones + iOS Safari ≥ 15 para admin; sin IE11. Baseline `docs/core/ui_ux.md`. Respuesta JSON legible en panel hosting móvil (U4). |

### UX — flujos operativos API y documentación (alcance de este spec)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U1** | § Monitoreo en `despliegue-y-versionado.md`: copy-paste `curl -sf https://<host>/api/health` con respuesta esperada `{"status":"ok"}` — operador no debe usar `/api/ping` para LB/cron | F6 |
| **U2** | `ApiHealthPublicRouteTest`: mensaje de fallo cita spec M4, ruta `routes/api.php` y acción («registrar GET /api/health **antes** del `$router->group` con AuthMiddleware») | F4 |
| **U3** | Tabla doc § Monitoreo: `/api/health` = liveness LB/cron/hosting (público); `/api/ping` = smoke autenticado post-login (interno) — no intercambiables | F6 |
| **U4** | Respuesta JSON ≤ 200 bytes, body fijo `{ "status": "ok" }` — legible en panel hosting móvil sin truncar ni requerir parser especial | F2 |
| **U5** | `ApiHealthPublicDispatchTest`: fallo sin implementación indica estado actual («GET /api/health ausente o dentro de grupo auth») y siguiente paso TDD | F5 |
| **U6** | Redirect `/api/ping` sin sesión: operador doc entiende que 302 a `/login` es **esperado**, no falso negativo de liveness — evita tickets de soporte | F6 |

### Carry-forward UX — próximo spec con superficie UI

Ítems derivados de deuda abierta verificada; **CF7 (health público) queda cubierto por este spec** — no
arrastrar. CF1–CF2 (semver harness + env purge), CF5 parcial (`mkt_leads` spec 2026-08-03) y D7 (CI spec
2026-08-04) tampoco se arrastran.

| # | Ítem | Origen | Requisito concreto |
|---|------|--------|-------------------|
| CF3 | Login responsive 320–768px | `ui_ux.md` | `.ct-login-page`, `.ct-login-card` sin overflow horizontal; tap targets ≥44px; sin scroll lateral en 320px. |
| CF4 | Dashboard admin responsive 320–768px | layouts side/top/bottom | Nav colapsable; KPI grid legible; topbar sin solapamiento de acciones. |
| CF5′ | Tablas CRUD restantes | módulo CRUD, D6 | `table-responsive` + `list.columns[].priority` en recursos distintos de `mkt_leads`; toolbar móvil. |
| CF6 | RBAC router CRUD/calendario | M3 | Errores 403 accionables (slug requerido vs permiso denegado). |
| CF8 | Permisos admin catálogo | M5 | Slug `permisos.gestionar` en seeds; UI permisos sin workaround `administracion.ver`. |
| CF9 | Estados vacío / error / carga (global) | `ui_ux.md` §8 | Unificar empty states en CRUDs sin hook; spinners list; validación con hint de corrección. |
| CF10 | Copy errores accionables (transversal) | transversal | Auth, wizard install, CRUD save: qué falló + qué hacer. |

### Responsive

**N/A en este spec** (sin superficie UI). Los ítems **CF3–CF5′** del carry-forward son el backlog
responsive verificado para el próximo spec con pantallas.

---

## Criterios de aceptación

- [ ] **AC1:** `GET /api/health` retorna 200 JSON `{ "status": "ok" }` sin cookie de sesión.
- [ ] **AC2:** `GET /api/ping` sin sesión **no** retorna 200 JSON ok (redirect login preservado).
- [ ] **AC3:** `php tests/run.php Docs/ApiHealthPublicRoute` PASS post-implementación (rojo pre-implementación).
- [ ] **AC4:** `php tests/run.php Kernel/ApiHealthPublicDispatch` PASS post-implementación (rojo pre-implementación).
- [ ] **AC5:** `skeleton/routes/api.php` espeja contrato harness.
- [ ] **AC6:** § Monitoreo en `despliegue-y-versionado.md` distingue `/api/health` vs `/api/ping`.
- [ ] **AC7:** Tag semver patch publicado; tres fuentes versión sincronizadas; `PlatformVersionSemverTest` verde.
- [ ] **AC8:** Diff PR no incluye lógica Marketing/Portal en `src/`; frontera FPS intacta.

### Compatibilidad, UX y responsive

- [ ] **AC-UX1:** Sección **Compatibilidad, UX y responsive** declara modo **sin superficie UI** con requisitos K/U/R verificables para F1–F6 (API/docs).
- [ ] **AC-UX2:** Requisitos U1–U6 (curl copy-paste, mensajes test gate, tabla health vs ping, payload ≤200 bytes, hints TDD, doc redirect esperado) incluidos como criterios del spec.
- [ ] **AC-UX3:** Carry-forward CF3–CF4, CF5′, CF6, CF8–CF10 documentado; CF7 no arrastrado (cubierto por este spec); CF1–CF2, CF5 parcial y D7 no arrastrados (resueltos o cubiertos en specs previos).

### Deuda técnica (inventario)

- [ ] **AC-D1:** Sección **Deuda técnica** lista abiertos verificados (M3, M5, D6, D7) con evidencia ruta/línea en `main` @ `42c3a0a`.
- [ ] **AC-D2:** M1, M2, M7, M8, M9, D1–D5, D13 reconciliados como **resueltos**; M4 **abierto** hasta merge implementación F1–F6.
- [ ] **AC-D3:** P1, P2, M6/D3, D14, D15 marcados **no verificados** Portal; acción concreta documentada.
- [ ] **AC-D4:** Verificado sin deuda nueva — migraciones 3 SQL ↔ 3 entradas manifiesto; `src/` sin `TODO`/`FIXME`; referencias operativas vivas a `feature/backoffice-api-integration` ausentes en `scripts/`, `docs/composer-setup.md`, `docs/integration/`; `despliegue-y-versionado.md` sin § Monitoreo/§ CI (gap planificado F6/D7, no drift pre-implementación).

---

## Operaciones por entorno

| Operación | Implementación (dev) | Staging | Producción |
|-----------|---------------------|---------|------------|
| Merge PR F1–F6 | PR a `main` + tag patch | N/A | N/A |
| Smoke local | `curl -sf http://localhost:8000/api/health` | N/A | N/A |
| Actualizar LB health URL | — | Operador manual | Operador manual — **fuera automation** |
| Bump Portal lock | — | — | Operador manual post-tag — **fuera automation** |
| Configurar `/api/ping` en LB | — | — | **Prohibido** — usar `/api/health` |

---

## Deuda técnica

Fuente: auditoría `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (PR #67 @ `d372ad8`); reconciliación con inventario spec `2026-08-04` (pase deuda @ `c78e672`) y tip `origin/main` @ `42c3a0a` (pase deuda 2026-08-05).

### Reconciliación heredada (cerrados)

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| **M1** | Sync semver | **Resuelto** | #74 + `v1.2.3` @ `041e402`; `composer.json` L6, `config/app.php` L7, `skeleton/config/app.php` L7 → `1.2.3` @ `42c3a0a`; `PlatformVersionSemverTest` presente |
| **M2** | `.env.example` Portal vars | **Resuelto** | #62 — root `.env.example` L55 comentario explícito; keys activas `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` = **0**; `skeleton/.env.example` sin vars Portal |
| **M9** | dompdf advisories | **Resuelto** | #74 — `composer.lock` fija `dompdf/dompdf` **v3.1.6**; `DompdfSecurityVersionTest` presente |
| **D1–D5, D13** | Semver harness / env / checklist | **Resuelto** | #62 + #74 — ver M1/M2 |
| **M7/M8** | Audit lifecycle / ops docs | **Resuelto** | PRs #54–#67, #56/#57 + `OpsDocsFpsAlignmentTest` |

**Cierres desde corrida anterior (2026-08-04 pase deuda):** **0** — intervalo `c78e672..42c3a0a` en `main` sólo añadió docs automation/spec/plan CI (#78–#80); sin cambios de código que cierren M3–M6 ni D6/D7.

### Alcance principal de este spec (M4 — abierto, verificado)

| ID | Hallazgo | Evidencia (`main` @ `42c3a0a`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M4** | `/api/*` autenticada por sesión; sin health público | `routes/api.php` L14–16 grupo `AuthMiddleware`; L23 `/api/ping` dentro del grupo; `skeleton/routes/api.php` espejo idéntico; `rg '/health' routes/` → **0**; `HealthController.php` L13–16 sólo `ping()` — método `health()` **ausente**; `AuthMiddleware.php` L21–24 redirect `/login` (302) sin JSON 401; `tests/` — `ApiHealthPublicRouteTest` / `ApiHealthPublicDispatchTest` **ausentes** | LB/cron/hosting no verifican liveness sin cookie; monitoreo naive obtiene redirect, no 200 JSON | `Presentation` / `routes/` | Framework | F1–F6 + tag patch semver |

### Backlog Framework verificado (fuera alcance F1–F6)

| ID | Hallazgo | Evidencia (`main` @ `42c3a0a`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M3** | CRUD/Calendario sin `RbacMiddleware` router | `routes/web.php` L114–125 — `/crud/{resource}`, `/calendario/{key}` sólo heredan `AuthMiddleware` del grupo admin; RBAC delegado a servicio | 403 inconsistente; defensa en profundidad débil | `Presentation` / `routes/` | Framework | Spec futuro CF6 |
| **M5** | Slug `permisos.gestionar` ausente | `routes/web.php` L61–65 — comentario + workaround `administracion.ver`; `rg permisos.gestionar database/` → **0** | Catálogo RBAC acoplado a permiso amplio | `Domain` RBAC | Framework | Spec futuro CF8 |
| **D6** | Plan `skeleton.lebytek.com` sin implementar | `docs/ENVIRONMENTS.md` L6, L13, L31, L63 — «skeleton.lebytek.com pendiente»; plan `2026-07-26-skeleton-package-staging.md` Tasks 2–10 sin deploy | LAB package puro no desplegado | Ops / Framework | Framework/Ops | Ejecutar plan humano Tasks 6–8 |
| **D7** | Sin pipeline GitHub Actions | `git ls-tree origin/main .github` → vacío; `tests/Docs/CiWorkflowPresentTest.php` **ausente**; `docs/core/despliegue-y-versionado.md` sin § CI; **159** archivos `*Test.php` bajo `tests/` sin gate CI en PR | Regresiones semver/dompdf/Integrations no bloqueadas en PR | Ops / repo | Framework/Ops | Plan `2026-08-04-audit-platform-ci-gates.md` (0/5 tareas) |

### Planes activos — estado ejecución real

| Plan | Tareas | Estado @ `42c3a0a` |
|------|--------|-------------------|
| `docs/superpowers/plans/2026-08-04-audit-platform-ci-gates.md` | 0/5 | Pendiente — Task 1 `CiWorkflowPresentTest` |
| `docs/superpowers/plans/2026-08-02-audit-mkt-leads-after-list-rows.md` | 0/5 | Pendiente — requiere clone Portal (M6) |
| `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` | 0/5 | Pendiente — Task 1 `ApiHealthPublicRouteTest` (este spec) |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ `v1.2.2` / handler `afterListRows` | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL fail; última evidencia `composer.lock` Portal con `lebytek/framework` **v1.1.0** @ `a79d3ad` | Plan Portal Task 1 cuando M6 resuelva |
| **P2** | Portal `routes/api.php` merge health | Clone Portal inaccesible | Operador verifica post-release Framework que línea pública `/api/health` no se pierda en merge |
| **M6 / D3** | Portal SHA / `composer.lock` | `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 | Ops: conceder lectura Portal al token automation |
| **D14** | Stripe subscription QA Portal | Repo Portal inaccesible; Framework `vertical.payments=false` @ `config/vertical.php` L22 | Portal: QA checkout antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` |
| **D15** | Bootstrap marketing Portal | Re-scopeado `Lebytek_Portal#4` — no inspeccionable aquí | Portal issue #4 |

### Verificado sin deuda nueva

- **Migraciones ↔ manifiesto:** 3 archivos en `database/migrations/` ↔ 3 entradas en `config/modules/core.php` L15–16, `crud-engine.php` L14–15, `pdf-kit.php` L16 — sin drift.
- **`src/`:** grep `TODO`/`FIXME` → **0** con impacto; sin `LebytekApiClient` ni Marketing.
- **Capas:** hook `afterListRows` en `Application`; Domain sin deps Presentation/Infrastructure.
- **Legacy operativo:** referencias vivas a `feature/backoffice-api-integration` o `dev-feature/backoffice-api-integration` **ausentes** en `scripts/`, `docs/composer-setup.md`, `docs/integration/`.
- **Payments bootstrap:** `vertical.payments=false` en harness; requisitos Stripe documentados como gate ops Portal (D14), no auto-fix en `src/`.
- **Semver/dompdf:** tres fuentes `1.2.3`; lock dompdf `v3.1.6` — sin regresión M1/M9.
- **Doc pre-implementación:** `despliegue-y-versionado.md` sin § Monitoreo (F6) ni § CI (D7 F5) — gap planificado, no drift doc↔código.

**Conteo:** **4 abiertos verificados** (M3, M5, D6, D7) + **M4 pendiente implementación** (alcance este spec); **5 no verificados** (P1, P2, M6/D3, D14, D15); **0 heredados cerrados** esta corrida.

---

*Report-only design spec. Ningún archivo de código, config, rutas, migraciones ni tests fue modificado en esta corrida.*
