# Public API Health Endpoint (M4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Exponer `GET /api/health` como endpoint JSON público de liveness (200 `{ "status": "ok" }` sin sesión), manteniendo `GET /api/ping` autenticado para smoke interno post-login.

**Architecture:** Enfoque A del spec — registrar la ruta **antes** del `$router->group` con `AuthMiddleware` en `routes/api.php` (harness) y `skeleton/routes/api.php` (plantilla). Extender `HealthController` en Presentation con método `health()` de payload mínimo fijo. Tests TDD en suites Docs (contrato de rutas) y Kernel (dispatch/controlador sin MySQL). Tag semver patch `v1.2.4` tras merge.

**Tech Stack:** PHP 8.1+ (`composer.json`), harness `tests/run.php` + `tests/lib/microtest.php`, `Lebytek\Framework\Kernel\Http\Router`, `AuthMiddleware`, `HealthController`, sin extensiones nuevas ni migraciones SQL.

**Source spec:** `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md`  ·  **Modo:** normal

**Source audit PR:** #67 — https://github.com/Parzival2103/Lebytek_Framework/pull/67 (hallazgo M4 carry-forward; mergeado 2026-08-02)

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main`; rama de trabajo `feature/api-health-public-m4` (creable desde `main` — verificado `git ls-remote origin refs/heads/main` @ `42c3a0a`)

## Global Constraints

- **No** editar `app/` harness salvo que un test existente lo exija (este plan no lo requiere).
- **No** mover `/api/ping` fuera del grupo auth — breaking change rechazado en spec.
- **No** incluir versión semver, checks de BD ni secretos en el body de `/api/health`.
- **No** secrets de producción ni cambios VPS/LB en automation — operador manual (P2).
- Portal `Parzival2103/Lebytek_Portal`: bump lock y merge de ruta — **fuera de alcance** (M6/gh 404); documentar en PR Framework.
- Semver: nueva ruta HTTP pública → **PATCH** `1.2.3` → **`1.2.4`**; tag `v1.2.4` post-merge.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| F1 ruta pública harness | Framework | Task 3 | `routes/api.php` antes de group auth |
| F2 `HealthController::health()` | Framework | Task 3 | método + JSON 200 |
| F3 espejo skeleton | Framework | Task 3 | `skeleton/routes/api.php` idéntico |
| F4 `ApiHealthPublicRouteTest` | Framework | Task 1 | TDD rojo→verde |
| F5 `ApiHealthPublicDispatchTest` | Framework | Task 2 | TDD rojo→verde |
| F6 doc § Monitoreo | Framework | Task 4 | `despliegue-y-versionado.md` |
| Semver + tag | Framework | Task 5 | `PlatformVersionSemverTest` + tag `v1.2.4` |
| P1 bump Portal lock | Portal | **Fuera de alcance** | post-tag manual |
| P2 checklist VPS Portal | Portal/Ops | **Fuera de alcance** | operador manual |
| U1–U6 UX operativa | Framework | Task 4 + tests | curl copy-paste, mensajes gate |
| M3/M5/D6/D7 | varios | **Fuera de alcance** | specs/planes separados |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `routes/api.php` | Registrar `GET /api/health` **antes** del group `/api` + auth |
| `skeleton/routes/api.php` | Espejo idéntico para nuevos tenants |
| `src/Presentation/Controllers/Api/HealthController.php` | Añadir `health()` → `{ "status": "ok" }` |
| `tests/Docs/ApiHealthPublicRouteTest.php` | Gate contrato rutas + método |
| `tests/Kernel/ApiHealthPublicDispatchTest.php` | Gate dispatch/controlador sin sesión |
| `docs/core/despliegue-y-versionado.md` | Nueva sección «Monitoreo / health checks» |
| `composer.json` | `"version": "1.2.4"` |
| `config/app.php` | `'version' => '1.2.4'` |
| `skeleton/config/app.php` | `'version' => '1.2.4'` |

**Interfaces producidas:**

- `GET /api/health` → 200 JSON `{ "status": "ok" }` sin cookie
- `GET /api/ping` → sin cambio (302 `/login` sin sesión; 200 JSON con sesión)
- Test filters: `php tests/run.php Docs/ApiHealthPublicRoute`, `php tests/run.php Kernel/ApiHealthPublicDispatch`

**Interfaces consumidas (sin modificar):**

- `Lebytek\Framework\Kernel\Http\Router::get()`, `::group()`, `::dispatch()`
- `Lebytek\Framework\Presentation\Middlewares\AuthMiddleware::handle()`
- `Lebytek\Framework\Kernel\Http\Request`, `Response::json()`

---

### Task 1: Test gate `ApiHealthPublicRouteTest` (TDD — rojo antes de rutas)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/api-health-public-m4`

**Depends on:** None

**Files:**
- Create: `tests/Docs/ApiHealthPublicRouteTest.php`
- Test: `tests/Docs/ApiHealthPublicRouteTest.php`

**Interfaces:**
- Consumes: `routes/api.php` y `skeleton/routes/api.php` sin `/api/health` (estado pre-implementación @ `42c3a0a`)
- Produces: test que falla con mensaje accionable citando spec M4

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/ApiHealthPublicRouteTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('routes/api.php registers GET /api/health before AuthMiddleware group', function () use ($root): void {
    $path = $root . '/routes/api.php';
    $src = (string) file_get_contents($path);

    assert_true(
        str_contains($src, "/api/health"),
        'missing GET /api/health in routes/api.php — register BEFORE $router->group with AuthMiddleware '
        . '(spec M4: docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md)'
    );

    $groupPos = strpos($src, '$router->group');
    $healthPos = strpos($src, '/api/health');
    assert_true(
        $groupPos !== false && $healthPos !== false && $healthPos < $groupPos,
        'GET /api/health must appear BEFORE $router->group([...AuthMiddleware...]) so LB/cron work without session'
    );
});

test('skeleton/routes/api.php mirrors harness public health route', function () use ($root): void {
    $harness = (string) file_get_contents($root . '/routes/api.php');
    $skeleton = (string) file_get_contents($root . '/skeleton/routes/api.php');

    assert_true(str_contains($skeleton, "/api/health"), 'skeleton/routes/api.php must register /api/health');
    assert_true(
        str_contains($skeleton, 'HealthController::class'),
        'skeleton must use HealthController for health endpoint'
    );

    $hGroup = strpos($harness, '$router->group');
    $sGroup = strpos($skeleton, '$router->group');
    $hHealth = strpos($harness, '/api/health');
    $sHealth = strpos($skeleton, '/api/health');
    assert_true($hHealth < $hGroup && $sHealth < $sGroup, 'both files must register /api/health before auth group');
});

test('HealthController declares public health() method', function () use ($root): void {
    $path = $root . '/src/Presentation/Controllers/Api/HealthController.php';
    $src = (string) file_get_contents($path);
    assert_true(
        preg_match('/function\s+health\s*\(/', $src) === 1,
        'HealthController must declare public health() returning JSON liveness payload'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/ApiHealthPublicRoute` / Expected: **FAIL** — `missing GET /api/health in routes/api.php` (test 1); test 3 **FAIL** — `health()` ausente.

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 3 crea ruta y método.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/ApiHealthPublicRoute` / Expected: FAIL (TDD rojo confirmado).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/DeployScriptsRemoved` / Expected: PASS — 3 tests, 0 failed.

- [ ] **Step 6: Commit** — archivos: `tests/Docs/ApiHealthPublicRouteTest.php` / mensaje: `test(docs): add ApiHealthPublicRouteTest gate for public /api/health (red)`

---

### Task 2: Test gate `ApiHealthPublicDispatchTest` (TDD — rojo antes de implementación)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/api-health-public-m4`

**Depends on:** Task 1

**Files:**
- Create: `tests/Kernel/ApiHealthPublicDispatchTest.php`
- Test: `tests/Kernel/ApiHealthPublicDispatchTest.php`

**Interfaces:**
- Consumes: Router + rutas actuales (sin `/api/health` pública)
- Produces: test que demuestra ausencia de liveness pública y preservación de redirect en `/api/ping`

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Kernel/ApiHealthPublicDispatchTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Http\Router;
use Lebytek\Framework\Presentation\Controllers\Api\HealthController;
use Lebytek\Framework\Presentation\Middlewares\AuthMiddleware;

$root = dirname(__DIR__, 2);

test('HealthController::health returns 200 JSON ok without session', function (): void {
    if (!method_exists(HealthController::class, 'health')) {
        throw new \RuntimeException(
            'HealthController::health() missing — add method returning {"status":"ok"} per spec M4'
        );
    }

    $controller = new HealthController();
    $response = $controller->health(new Request('GET', '/api/health'));
    assert_same(200, $response->getStatusCode());

    $data = json_decode($response->getBody(), true);
    assert_true(is_array($data), 'health response must be JSON object');
    assert_same('ok', $data['status'] ?? null);
    assert_true(strlen($response->getBody()) <= 200, 'health payload must be <= 200 bytes (U4)');
});

test('AuthMiddleware blocks unauthenticated /api/ping (not public liveness)', function (): void {
    $middleware = new AuthMiddleware();
    $request = new Request('GET', '/api/ping');
    $response = $middleware->handle($request, fn (Request $r) => (new HealthController())->ping($r));

    assert_same(302, $response->getStatusCode());
    assert_same('/login', $response->getHeaders()['Location'] ?? null);
});

test('Router dispatch serves /api/health without session when route is public', function () use ($root): void {
    $router = new Router();
    require $root . '/routes/api.php';

    ob_start();
    $router->dispatch(new Request('GET', '/api/health'));
    $body = ob_get_clean();

    $data = json_decode($body, true);
    assert_true(
        is_array($data) && ($data['status'] ?? null) === 'ok',
        'GET /api/health absent or behind AuthMiddleware — register before $router->group per spec M4 '
        . '(docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md)'
    );
});

test('Router dispatch does not return 200 JSON ok for /api/ping without session', function () use ($root): void {
    $router = new Router();
    require $root . '/routes/api.php';

    ob_start();
    $router->dispatch(new Request('GET', '/api/ping'));
    $body = ob_get_clean();

    $data = json_decode($body, true);
    assert_true(
        !is_array($data) || ($data['status'] ?? null) !== 'ok',
        'unauthenticated /api/ping must NOT return {"status":"ok"} — use /api/health for LB/cron'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Kernel/ApiHealthPublicDispatch` / Expected: **FAIL** — test 1 (`health()` missing) y test 3 (body no JSON ok / 404).

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 3 implementa.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Kernel/ApiHealthPublicDispatch` / Expected: FAIL (TDD rojo).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel/SkeletonPurity` / Expected: PASS — 13 tests, 0 failed (rutas no afectan purity).

- [ ] **Step 6: Commit** — archivos: `tests/Kernel/ApiHealthPublicDispatchTest.php` / mensaje: `test(kernel): add ApiHealthPublicDispatchTest gate (red)`

---

### Task 3: Implementar ruta pública y `HealthController::health()` (F1–F3)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/api-health-public-m4`

**Depends on:** Task 2

**Files:**
- Modify: `routes/api.php` (insertar ruta pública antes del group)
- Modify: `skeleton/routes/api.php` (espejo idéntico)
- Modify: `src/Presentation/Controllers/Api/HealthController.php` (añadir `health()`)
- Test: `tests/Docs/ApiHealthPublicRouteTest.php`, `tests/Kernel/ApiHealthPublicDispatchTest.php`

**Interfaces:**
- Consumes: tests Task 1–2 rojos
- Produces: `GET /api/health` → 200 `{ "status": "ok" }`; `/api/ping` sin cambio

- [ ] **Step 1: Escribir el test que falla** — tests Task 1–2 ya rojos.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/ApiHealthPublicRoute Kernel/ApiHealthPublicDispatch` / Expected: FAIL.

- [ ] **Step 3: Implementar el cambio mínimo**

En `src/Presentation/Controllers/Api/HealthController.php`, añadir método **después** de `ping()`:

```php
    public function health(Request $request): Response
    {
        return $this->json(['status' => 'ok']);
    }
```

En `routes/api.php`, insertar **después** de los `use` y **antes** del `$router->group`:

```php
// Público — liveness LB/cron (M4). NO usar /api/ping para monitoreo externo.
$router->get('/api/health', [HealthController::class, 'health']);
```

Replicar la misma línea y comentario en `skeleton/routes/api.php` (contenido idéntico al harness para el bloque de registro).

El archivo `routes/api.php` resultante debe conservar el group existente:

```php
$router->group([
    'prefix'      => '/api',
    'middlewares' => [AuthMiddleware::class],
], function ($router) {
    $router->get('/ping', [HealthController::class, 'ping']);
});
```

- [ ] **Step 4: Verificación enfocada** — Run:

```bash
php tests/run.php Docs/ApiHealthPublicRoute
php tests/run.php Kernel/ApiHealthPublicDispatch
```

Expected: **PASS** — 3 + 4 tests, 0 failed.

Smoke local opcional:

```bash
cp .env.example .env
php -S 127.0.0.1:8000 -t public &
sleep 1
curl -sf http://127.0.0.1:8000/api/health
kill %1
```

Expected: `{"status":"ok"}` (pretty-print opcional) y exit 0 de curl.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel` / Expected: 0 failed.

- [ ] **Step 6: Commit** — archivos: `routes/api.php`, `skeleton/routes/api.php`, `src/Presentation/Controllers/Api/HealthController.php` / mensaje: `feat(api): add public GET /api/health liveness endpoint (M4)`

---

### Task 4: Documentación § Monitoreo en `despliegue-y-versionado.md` (F6, U1, U3, U6)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/api-health-public-m4`

**Depends on:** Task 3

**Files:**
- Modify: `docs/core/despliegue-y-versionado.md` (insertar sección tras «Versionado y actualización», antes de «Checklist pre/post deploy por entorno» ~L225)
- Test: `tests/Docs/ApiHealthPublicRouteTest.php`

**Interfaces:**
- Consumes: endpoint Task 3
- Produces: § Monitoreo con tabla `/api/health` vs `/api/ping` y curl copy-paste

- [ ] **Step 1: Escribir el test que falla** — N/A (doc-only; gates Task 1–2 ya verdes).

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — N/A.

- [ ] **Step 3: Implementar el cambio mínimo** — insertar antes de `## Checklist pre/post deploy por entorno`:

````markdown
## Monitoreo / health checks

Load balancers, paneles de hosting y cron externos deben usar el endpoint **público** de liveness. **No** usar `/api/ping` para monitoreo externo: requiere sesión activa y redirige a `/login` (302) sin cookie.

| Endpoint | Autenticación | Uso | Respuesta esperada |
|----------|---------------|-----|-------------------|
| `GET /api/health` | **Ninguna** (público) | Liveness LB / cron / hosting | `200` JSON `{"status":"ok"}` (≤ 200 bytes) |
| `GET /api/ping` | Sesión activa (`AuthMiddleware`) | Smoke interno post-login | `200` JSON `{"status":"ok","timestamp":"…"}` con cookie |

### Verificar liveness (copy-paste)

```bash
curl -sf https://<host>/api/health
```

Expected: body `{"status":"ok"}` (formato puede incluir pretty-print) y exit code 0.

### Verificar ping autenticado (interno)

Tras iniciar sesión en el navegador, desde una sesión con cookie válida:

```bash
curl -sf -b cookies.txt https://<host>/api/ping
```

Sin cookie, un `302` hacia `/login` es **esperado** — no indica caída del sitio; usar `/api/health` para liveness externo.

**WhatsApp API:** `GET /api/v1/health` en `WhatsApiLebytek` es un contrato distinto (Bearer token) — no confundir con el tenant PHP del framework.
````

- [ ] **Step 4: Verificación enfocada** — Run: `grep -c '/api/health' docs/core/despliegue-y-versionado.md` / Expected: salida ≥ `3`.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs` / Expected: PASS incluyendo `ApiHealthPublicRouteTest`.

- [ ] **Step 6: Commit** — archivos: `docs/core/despliegue-y-versionado.md` / mensaje: `docs: add Monitoreo section distinguishing /api/health vs /api/ping`

---

### Task 5: Semver sync, tag `v1.2.4`, PR y evidencia (AC7, AC8)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/api-health-public-m4`

**Depends on:** Task 4

**Files:**
- Modify: `composer.json` (`"version": "1.2.4"`)
- Modify: `config/app.php` (`'version' => '1.2.4'`)
- Modify: `skeleton/config/app.php` (`'version' => '1.2.4'`)
- Test: suite completa relevante

**Interfaces:**
- Consumes: Tasks 1–4
- Produces: tag Git `v1.2.4`; PR hacia `main`

- [ ] **Step 1: Escribir el test que falla** — N/A.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` **antes** del bump / Expected: PASS @ `1.2.3` (baseline).

- [ ] **Step 3: Implementar el cambio mínimo** — actualizar **los tres** archivos al mismo valor `1.2.4`:

```bash
# composer.json → "version": "1.2.4"
# config/app.php → 'version' => '1.2.4',
# skeleton/config/app.php → 'version' => '1.2.4',
```

No modificar `composer.lock` del paquete library (no aplica en package source).

- [ ] **Step 4: Verificación enfocada** — Run:

```bash
php tests/run.php PlatformVersionSemver
php tests/run.php Docs/ApiHealthPublicRoute
php tests/run.php Kernel/ApiHealthPublicDispatch
git diff origin/main...HEAD --name-only
```

Expected: semver 3 tests PASS; health gates PASS; diff contiene **solo** archivos del plan (routes, skeleton routes, HealthController, tests, doc, tres versiones) — **sin** Marketing/Portal en `src/`.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php` / Expected: 0 failed.

Distinción entorno: si PHP CLI ausente en agente cloud, ejecutar en runner local o post-merge CI (plan D7); no marcar verde sin evidencia.

Post-merge (operador o agente con git tag):

```bash
git tag -a v1.2.4 -m "Public GET /api/health liveness endpoint (M4)"
git push origin v1.2.4
```

Push rama y PR:

```bash
git push -u origin feature/api-health-public-m4
gh pr create --base main --title "feat(api): public GET /api/health liveness (M4)" \
  --body "Implementa F1–F6 del spec 2026-08-05.

- GET /api/health público (200 JSON ok)
- /api/ping autenticado preservado
- ApiHealthPublicRouteTest + ApiHealthPublicDispatchTest
- docs/core/despliegue-y-versionado.md § Monitoreo
- semver 1.2.4

Spec: docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md
Audit: #67 (M4)
Plan: docs/superpowers/plans/2026-08-05-audit-api-health-public.md"
```

- [ ] **Step 6: Commit / tag** — commit semver: `chore(release): bump platform version to 1.2.4 for /api/health`; tag post-merge en `main`.

**Requiere operador humano:** sí — reconfigurar LB/cron de `/api/ping` → `/api/health` en VPS (P2); bump `composer.lock` Portal post-tag (P1/M6).

---

## Fuera de alcance

| ID | Tema | Motivo |
|----|------|--------|
| P1/P2 | Portal bump + checklist VPS | Owner Portal; M6 gh 404 |
| Readiness DB/Redis | `GET /api/ready` | Spec futuro; M4 = liveness only |
| Token API plataforma | Auth Bearer `/api/*` | Backlog ampliado M4 |
| CI GitHub Actions | D7 | Plan `2026-08-04-audit-platform-ci-gates.md` |
| M3 RBAC router | CF6 | Spec futuro |
| M5 `permisos.gestionar` | CF8 | Spec futuro |
| D6 skeleton.lebytek.com | Ops | Plan `2026-07-26` |
| WhatsApi health | WhatsApiLebytek | Contrato Bearer distinto |
| Cambio LB producción | Ops | Automation no toca VPS |

## Criterios finales de aceptación

- [ ] `GET /api/health` → 200 `{ "status": "ok" }` sin cookie (AC1).
- [ ] `GET /api/ping` sin sesión no retorna 200 JSON ok (AC2).
- [ ] `php tests/run.php Docs/ApiHealthPublicRoute` PASS (AC3).
- [ ] `php tests/run.php Kernel/ApiHealthPublicDispatch` PASS (AC4).
- [ ] `skeleton/routes/api.php` espeja harness (AC5).
- [ ] § Monitoreo documentado (AC6).
- [ ] Tag `v1.2.4`; tres fuentes semver sincronizadas; `PlatformVersionSemverTest` PASS (AC7).
- [ ] Diff sin lógica Marketing/Portal en `src/` (AC8).

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Confundir `/api/health` con `/api/ping` | Doc § Monitoreo + mensajes test gate |
| Portal no mergea ruta al bump | P2 checklist manual |
| Payload filtra datos sensibles | Body fijo `{status:ok}` only |
| LB sigue en `/api/ping` | Comunicación ops; no auto-cambiar VPS |
| PHP ausente en cloud agent | Verificar en entorno con PHP 8.1+ |

**Rollback:** revertir PR — desaparece `/api/health`; `/api/ping` intacto; reconfigurar LB manualmente.

## Evidencia que debe recopilar el ejecutor

- Salida `php tests/run.php Docs/ApiHealthPublicRoute Kernel/ApiHealthPublicDispatch`.
- Salida `curl -sf http://localhost:8000/api/health` local.
- Número PR Framework y URL tag `v1.2.4`.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-05T12:40:00Z (AUTOMATION-03 — plan creado) |
| Plan creado UTC | 2026-08-05T12:40:00Z |
| Framework `origin/main` referencia | `42c3a0a4d0fafacd24d8632ca6e77c00836da79f` |
| Tareas completadas / totales | **0 / 5** |
| Modo fuente | normal (spec PR #81 @ `automation/spec-2026-08-05`) |
| Siguiente tarea ejecutable | **Task 1** — `ApiHealthPublicRouteTest` (TDD rojo) |
| Prerrequisitos | Ninguno — `HealthController::ping()` existe; rutas auth actuales verificadas @ `42c3a0a` |
| Bloqueos | PHP CLI puede estar ausente en agente cloud — verificar en entorno con PHP 8.1+; Portal P1/P2 requiere operador (M6) |
| Estado | **Pendiente de implementación** |
