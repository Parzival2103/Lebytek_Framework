# CRUD/Calendario RBAC Router Middleware (M3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar `CrudRbacMiddleware` dinámico en rutas `/admin/crud/{resource}` y `/admin/calendario/{key}` para devolver 403 accionables **antes** del controlador, manteniendo la verificación fina en `CrudResourceService` / calendario.

**Architecture:** Enfoque A del spec — `CrudRoutePermissionResolver` (Application) mapea URI + verbo HTTP → slug `{permission_prefix}.{acción}` leyendo JSON en `config/cruds/` y `config/calendars/`; `CrudRbacMiddleware` (Presentation) replica el contrato de respuesta de `RbacMiddleware` con slug explícito (U1–U3). Registro espejado en `routes/web.php` y `skeleton/routes/web.php`. Sin eliminar checks en servicios (defensa doble, AC4).

**Tech Stack:** PHP 8.1+ (`composer.json`), harness `tests/run.php` + `tests/lib/microtest.php`, `CrudConfigLoader`, `CalendarConfigLoader`, `RbacPolicy`, `Router` con middlewares por ruta, semver patch → **`1.2.5`** (M4 health plan reservado `1.2.4` @ plan 2026-08-05, aún 0/5).

**Source spec:** `docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md`  ·  **Modo:** normal

**Source audit PR:** #84 — https://github.com/Parzival2103/Lebytek_Framework/pull/84 (auditoría 2026-08-06, mergeado; hallazgo M3 / CF6)

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`ddc55ec`); rama de trabajo `feature/crud-rbac-router-m3` (creable desde `main` — verificado `git ls-remote origin refs/heads/main` @ `ddc55ec`)

## Global Constraints

- **No** eliminar `RbacService::verificar()` en `CrudResourceService` ni use cases calendario (regresión AC4).
- **No** usar slug estático grueso tipo `administracion.ver` para todo CRUD (enfoque C rechazado).
- **No** editar `vendor/` ni código Marketing/Portal en `src/`.
- Recurso/key inválido → `ValidationException` pasa al controlador (U4); **no** convertir en 403 RBAC.
- Portal P1–P3, O1–O2 producción → **fuera de alcance** (M6 gh 404).
- Semver: middleware RBAC aditivo + 403 temprano → **PATCH** `1.2.3` → **`1.2.5`**; tag `v1.2.5` post-merge (coordinar con plan M4 si mergea antes y publica `v1.2.4`).

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| F1 `CrudRoutePermissionResolver` | Framework | Task 2 + Task 4 | unit tests + mapeo URI |
| F2 `CrudRbacMiddleware` | Framework | Task 3 + Task 4 | 403 slug HTML/JSON |
| F3 registro rutas harness/skeleton | Framework | Task 4 | `CrudRbacRouterTest` |
| F4 tests TDD | Framework | Task 1–3 | rojo→verde |
| F5 docs + `rbac_route_permissions.php` | Framework | Task 5 | grep + doc §5 |
| F6 semver + tag | Framework | Task 5 | `PlatformVersionSemverTest` |
| P1–P3 Portal merge/bump | Portal | **Fuera de alcance** | post-tag manual M6 |
| O1–O2 QA staging | Ops | **Fuera de alcance** | operador manual |
| U1–U8 UX 403 | Framework | Task 3–5 | asserts mensaje/slug |
| M4 health, D7 CI, M5 permisos | varios | **Fuera de alcance** | planes/specs separados |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `src/Application/Services/CrudRoutePermissionResolver.php` | Mapeo puro request → slug; lanza `ValidationException` si recurso/key inválido |
| `src/Presentation/Middlewares/CrudRbacMiddleware.php` | Resolver + `RbacPolicy`; 403 accionable HTML/JSON |
| `routes/web.php` | `$crudRbac` en rutas CRUD/calendario (dentro group `/admin`) |
| `skeleton/routes/web.php` | Espejo idéntico |
| `tests/Docs/CrudRbacRouterTest.php` | Gate registro middleware en rutas |
| `tests/Kernel/CrudRoutePermissionResolverTest.php` | Gate mapeo slug por URI/verbo |
| `tests/Kernel/CrudRbacMiddlewareTest.php` | Gate 403 con slug antes de controlador |
| `config/rbac_route_permissions.php` | Documentar middleware dinámico CRUD + slugs estáticos faltantes |
| `docs/core/auth_rbac_seguridad_v0.1.md` | §5 actualizado: `CrudRbacMiddleware` vs servicio |
| `composer.json`, `config/app.php`, `skeleton/config/app.php` | semver `1.2.5` |

**Interfaces producidas:**

- `CrudRoutePermissionResolver::resolve(Request $request): string` → slug p. ej. `demo_clientes.ver`
- `CrudRbacMiddleware::handle(Request, callable): Response` → 403 JSON `{error, permiso}` o HTML forbidden + flash con slug
- Test filters: `php tests/run.php Docs/CrudRbacRouter`, `Kernel/CrudRoutePermissionResolver`, `Kernel/CrudRbacMiddleware`

**Interfaces consumidas (sin modificar contrato público):**

- `CrudConfigLoader::load(string $resource): CrudResourceDefinition`
- `CalendarConfigLoader::load(string $key): CalendarDefinition` + `crudDefinition(string $resource)`
- `CrudResourceDefinition::permissionFor(string $action): string`
- `RbacPolicy::puede(string $permiso): bool`
- `ValidationException` (Domain) — propagada al controlador vía `$next($request)`

---

### Task 1: Test gate `CrudRbacRouterTest` (TDD — rojo antes de rutas)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/crud-rbac-router-m3`

**Depends on:** None

**Files:**
- Create: `tests/Docs/CrudRbacRouterTest.php`
- Test: `tests/Docs/CrudRbacRouterTest.php`

**Interfaces:**
- Consumes: `routes/web.php`, `skeleton/routes/web.php` sin `CrudRbacMiddleware` @ `ddc55ec`
- Produces: test que falla citando spec M3 y acción concreta

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/CrudRbacRouterTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('routes/web.php registers CrudRbacMiddleware on CRUD routes', function () use ($root): void {
    $path = $root . '/routes/web.php';
    $src = (string) file_get_contents($path);

    assert_true(
        str_contains($src, 'CrudRbacMiddleware'),
        'missing CrudRbacMiddleware in routes/web.php — register on /crud/{resource} routes '
        . '(spec M3: docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md)'
    );

    assert_true(
        preg_match("#get\\('/crud/\\{resource\\}'[^\\n]*CrudRbacMiddleware#", $src) === 1
            || preg_match("#get\\('/crud/\\{resource\\}'[^\\n]*\\$crudRbac#", $src) === 1,
        'GET /crud/{resource} must include CrudRbacMiddleware (or $crudRbac alias)'
    );
});

test('routes/web.php registers CrudRbacMiddleware on calendario routes', function () use ($root): void {
    $src = (string) file_get_contents($root . '/routes/web.php');

    assert_true(
        preg_match("#get\\('/calendario/\\{key\\}'[^\\n]*(CrudRbacMiddleware|\\$crudRbac)#", $src) === 1,
        'GET /calendario/{key} must include CrudRbacMiddleware — spec M3'
    );
    assert_true(
        preg_match("#get\\('/calendario/\\{key\\}/eventos'[^\\n]*(CrudRbacMiddleware|\\$crudRbac)#", $src) === 1,
        'GET /calendario/{key}/eventos must include CrudRbacMiddleware for AJAX 403 (U7)'
    );
});

test('skeleton/routes/web.php mirrors harness CrudRbacMiddleware registration', function () use ($root): void {
    $harness = (string) file_get_contents($root . '/routes/web.php');
    $skeleton = (string) file_get_contents($root . '/skeleton/routes/web.php');

    assert_true(str_contains($skeleton, 'CrudRbacMiddleware'), 'skeleton must use CrudRbacMiddleware');
    assert_true(
        substr_count($harness, 'CrudRbacMiddleware') === substr_count($skeleton, 'CrudRbacMiddleware'),
        'skeleton must mirror harness CrudRbacMiddleware registration count'
    );
});

test('CrudRbacMiddleware class exists in Presentation layer', function () use ($root): void {
    $path = $root . '/src/Presentation/Middlewares/CrudRbacMiddleware.php';
    assert_true(is_readable($path), 'CrudRbacMiddleware.php must exist at src/Presentation/Middlewares/');
    $src = (string) file_get_contents($path);
    assert_true(
        preg_match('/function\s+handle\s*\(/', $src) === 1,
        'CrudRbacMiddleware must declare handle(Request, callable): Response'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/CrudRbacRouter` / Expected: **FAIL** — `missing CrudRbacMiddleware in routes/web.php` (test 1).

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 4 registra middleware.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/CrudRbacRouter` / Expected: FAIL (TDD rojo confirmado).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/DeployScriptsRemoved` / Expected: PASS — 3 tests, 0 failed.

- [ ] **Step 6: Commit** — archivos: `tests/Docs/CrudRbacRouterTest.php` / mensaje: `test(docs): add CrudRbacRouterTest gate for M3 router RBAC (red)`

---

### Task 2: Test gate `CrudRoutePermissionResolverTest` (TDD — rojo antes de resolver)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/crud-rbac-router-m3`

**Depends on:** Task 1

**Files:**
- Create: `tests/Kernel/CrudRoutePermissionResolverTest.php`
- Test: `tests/Kernel/CrudRoutePermissionResolverTest.php`

**Interfaces:**
- Consumes: `config/cruds/demo_clientes.json` (`permission_prefix`: `demo_clientes`), `config/calendars/demo_citas.json` → recurso `demo_citas`
- Produces: asserts de mapeo `{prefix}.{acción}` por URI

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Kernel/CrudRoutePermissionResolverTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Application\Services\CrudConfigLoader;
use Lebytek\Framework\Application\Services\CrudConfigValidator;
use Lebytek\Framework\Application\Services\CrudRoutePermissionResolver;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\Http\Request;

function crud_resolver(): CrudRoutePermissionResolver
{
    return new CrudRoutePermissionResolver(
        new CrudConfigLoader(new CrudConfigValidator()),
        new CalendarConfigLoader(new CalendarConfigValidator())
    );
}

test('CrudRoutePermissionResolver maps GET index to {prefix}.ver', function (): void {
    if (!class_exists(CrudRoutePermissionResolver::class)) {
        throw new \RuntimeException(
            'CrudRoutePermissionResolver missing — add Application service per spec M3 F1'
        );
    }
    $request = new Request('GET', '/admin/crud/demo_clientes');
    $request->setRouteParams(['resource' => 'demo_clientes']);
    assert_same('demo_clientes.ver', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps GET crear to {prefix}.crear', function (): void {
    $request = new Request('GET', '/admin/crud/demo_clientes/crear');
    $request->setRouteParams(['resource' => 'demo_clientes']);
    assert_same('demo_clientes.crear', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps POST store to {prefix}.crear', function (): void {
    $request = new Request('POST', '/admin/crud/demo_clientes');
    $request->setRouteParams(['resource' => 'demo_clientes']);
    assert_same('demo_clientes.crear', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps POST eliminar to {prefix}.eliminar', function (): void {
    $request = new Request('POST', '/admin/crud/demo_clientes/42/eliminar');
    $request->setRouteParams(['resource' => 'demo_clientes', 'id' => '42']);
    assert_same('demo_clientes.eliminar', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps calendario index to linked CRUD {prefix}.ver', function (): void {
    $request = new Request('GET', '/admin/calendario/demo_citas');
    $request->setRouteParams(['key' => 'demo_citas']);
    assert_same('demo_citas.ver', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps calendario eventos AJAX to {prefix}.ver', function (): void {
    $request = new Request('GET', '/admin/calendario/demo_citas/eventos');
    $request->setRouteParams(['key' => 'demo_citas']);
    assert_same('demo_citas.ver', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver throws ValidationException for unknown CRUD resource', function (): void {
    $request = new Request('GET', '/admin/crud/no_existe_xyz');
    $request->setRouteParams(['resource' => 'no_existe_xyz']);
    assert_throws(ValidationException::class, fn () => crud_resolver()->resolve($request));
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Kernel/CrudRoutePermissionResolver` / Expected: **FAIL** — `CrudRoutePermissionResolver missing` (test 1).

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 4 implementa resolver.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Kernel/CrudRoutePermissionResolver` / Expected: FAIL (TDD rojo).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Calendar/CalendarViewModelBuilder` / Expected: PASS — 3 tests, 0 failed.

- [ ] **Step 6: Commit** — archivos: `tests/Kernel/CrudRoutePermissionResolverTest.php` / mensaje: `test(kernel): add CrudRoutePermissionResolverTest gate (red)`

---

### Task 3: Test gate `CrudRbacMiddlewareTest` (TDD — rojo antes de middleware)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/crud-rbac-router-m3`

**Depends on:** Task 2

**Files:**
- Create: `tests/Kernel/CrudRbacMiddlewareTest.php`
- Test: `tests/Kernel/CrudRbacMiddlewareTest.php`

**Interfaces:**
- Consumes: sesión autenticada sin permiso `demo_clientes.ver`
- Produces: 403 con slug en cuerpo/mensaje (U1, U3, U6)

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Kernel/CrudRbacMiddlewareTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Presentation\Middlewares\CrudRbacMiddleware;

test('CrudRbacMiddleware returns 403 with permiso slug when user lacks access', function (): void {
    if (!class_exists(CrudRbacMiddleware::class)) {
        throw new \RuntimeException(
            'CrudRbacMiddleware missing or not registered — middleware ausente o no registrado (spec M3 U6)'
        );
    }

    $_SESSION['auth_permisos'] = [];
    $_SESSION['auth_roles'] = [];

    $request = new Request('GET', '/admin/crud/demo_clientes');
    $request->setRouteParams(['resource' => 'demo_clientes']);

    $middleware = new CrudRbacMiddleware();
    $response = $middleware->handle($request, fn (Request $r) => Response::json(['ok' => true]));

    assert_same(403, $response->getStatusCode(), 'expected 403 before CrudController without demo_clientes.ver');

    $body = $response->getBody();
    assert_true(
        str_contains($body, 'demo_clientes.ver'),
        '403 response must mention required slug demo_clientes.ver (U1/U6)'
    );
});

test('CrudRbacMiddleware returns JSON permiso field for AJAX requests', function (): void {
    $_SESSION['auth_permisos'] = [];
    $_SESSION['auth_roles'] = [];

    $request = new Request(
        'GET',
        '/admin/calendario/demo_citas/eventos',
        [],
        [],
        ['X-Requested-With' => 'XMLHttpRequest']
    );
    $request->setRouteParams(['key' => 'demo_citas']);

    $middleware = new CrudRbacMiddleware();
    $response = $middleware->handle($request, fn (Request $r) => Response::json(['eventos' => []]));

    assert_same(403, $response->getStatusCode());
    $data = json_decode($response->getBody(), true);
    assert_true(is_array($data), 'AJAX 403 must be JSON (U3/U7)');
    assert_same('Acceso denegado.', $data['error'] ?? null);
    assert_same('demo_citas.ver', $data['permiso'] ?? null);
});

test('CrudRbacMiddleware delegates unknown CRUD resource to next handler (ValidationException path)', function (): void {
    $_SESSION['auth_permisos'] = ['anything.ver'];
    $_SESSION['auth_roles'] = [];

    $request = new Request('GET', '/admin/crud/no_existe_xyz');
    $request->setRouteParams(['resource' => 'no_existe_xyz']);

    $middleware = new CrudRbacMiddleware();
    $called = false;
    $response = $middleware->handle($request, function (Request $r) use (&$called) {
        $called = true;
        return Response::json(['delegated' => true]);
    });

    assert_true($called, 'invalid resource must pass through to controller (U4), not RBAC 403');
    assert_same(200, $response->getStatusCode());
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Kernel/CrudRbacMiddleware` / Expected: **FAIL** — `CrudRbacMiddleware missing` (test 1).

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 4 implementa middleware.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Kernel/CrudRbacMiddleware` / Expected: FAIL (TDD rojo).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel/SkeletonPurity` / Expected: PASS — 13 tests, 0 failed.

- [ ] **Step 6: Commit** — archivos: `tests/Kernel/CrudRbacMiddlewareTest.php` / mensaje: `test(kernel): add CrudRbacMiddlewareTest gate (red)`

---

### Task 4: Implementar resolver, middleware y registro de rutas (F1–F3)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/crud-rbac-router-m3`

**Depends on:** Task 3

**Files:**
- Create: `src/Application/Services/CrudRoutePermissionResolver.php`
- Create: `src/Presentation/Middlewares/CrudRbacMiddleware.php`
- Modify: `routes/web.php` (use + `$crudRbac` en rutas CRUD/calendario)
- Modify: `skeleton/routes/web.php` (espejo)
- Test: Tasks 1–3 suites

**Interfaces:**
- Consumes: tests Task 1–3 rojos
- Produces: `resolve()` + `handle()` + rutas registradas

- [ ] **Step 1: Escribir el test que falla** — tests Task 1–3 ya rojos.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/CrudRbacRouter Kernel/CrudRoutePermissionResolver Kernel/CrudRbacMiddleware` / Expected: FAIL.

- [ ] **Step 3: Implementar el cambio mínimo**

Crear `src/Application/Services/CrudRoutePermissionResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\Http\Request;

final class CrudRoutePermissionResolver
{
    public function __construct(
        private readonly CrudConfigLoader $crudConfigLoader,
        private readonly CalendarConfigLoader $calendarConfigLoader,
    ) {}

    public function resolve(Request $request): string
    {
        $uri = $request->uri();

        if (preg_match('#^/admin/calendario/([^/]+)(?:/eventos)?$#', $uri, $m)) {
            $key = (string) ($request->param('key') ?: $m[1]);
            $def = $this->calendarConfigLoader->load($key);
            $crud = $this->calendarConfigLoader->crudDefinition($def->resource());

            return $crud->permissionPrefix() . '.ver';
        }

        if (preg_match('#^/admin/crud/([^/]+)#', $uri, $m)) {
            $resource = (string) ($request->param('resource') ?: $m[1]);
            $definition = $this->crudConfigLoader->load($resource);
            $action = $this->resolveCrudAction($request, $uri);

            return $definition->permissionFor($action);
        }

        throw new ValidationException('Ruta CRUD/calendario no reconocida para RBAC.');
    }

    private function resolveCrudAction(Request $request, string $uri): string
    {
        if (preg_match('#/eliminar$#', $uri)) {
            return 'eliminar';
        }
        if (preg_match('#/accion-masiva/#', $uri) || preg_match('#/accion/#', $uri)) {
            return 'ver';
        }
        if (preg_match('#/crear$#', $uri)) {
            return 'crear';
        }
        if (preg_match('#/editar$#', $uri)) {
            return 'editar';
        }
        if ($request->isPost()) {
            if (preg_match('#^/admin/crud/[^/]+$#', $uri)) {
                return 'crear';
            }

            return 'editar';
        }
        if (preg_match('#^/admin/crud/[^/]+/[^/]+$#', $uri) && !str_contains($uri, '/crear')) {
            return 'ver';
        }

        return 'ver';
    }
}
```

Crear `src/Presentation/Middlewares/CrudRbacMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Middlewares;

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Application\Services\CrudConfigLoader;
use Lebytek\Framework\Application\Services\CrudConfigValidator;
use Lebytek\Framework\Application\Services\CrudRoutePermissionResolver;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Policies\RbacPolicy;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;

final class CrudRbacMiddleware
{
    private readonly CrudRoutePermissionResolver $resolver;

    public function __construct(?CrudRoutePermissionResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new CrudRoutePermissionResolver(
            new CrudConfigLoader(new CrudConfigValidator()),
            new CalendarConfigLoader(new CalendarConfigValidator())
        );
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            $permiso = $this->resolver->resolve($request);
        } catch (ValidationException) {
            return $next($request);
        }

        $policy = new RbacPolicy(
            Session::get('auth_permisos', []),
            Session::get('auth_roles', [])
        );

        if (!$policy->puede($permiso)) {
            if ($request->isAjax()) {
                return Response::json([
                    'error'   => 'Acceso denegado.',
                    'permiso' => $permiso,
                ], 403);
            }

            Session::flash(
                'error',
                "No tienes permiso para acceder a esta sección (`{$permiso}`). "
                . 'Solicítalo al administrador o revisa tu rol en Usuarios/Roles.'
            );

            return Response::forbidden();
        }

        return $next($request);
    }
}
```

En `routes/web.php` y `skeleton/routes/web.php`:

1. Añadir `use Lebytek\Framework\Presentation\Middlewares\CrudRbacMiddleware;`
2. Dentro del group `/admin`, **antes** del bloque CRUD:

```php
    $crudRbac = [new CrudRbacMiddleware()];
```

3. Añadir `$crudRbac` (o `array_merge($crudRbac, [CsrfMiddleware::class])` en POST) a **cada** ruta:

```php
    $router->get('/crud/{resource}',                  [CrudController::class, 'index'], $crudRbac);
    $router->get('/crud/{resource}/crear',            [CrudController::class, 'create'], $crudRbac);
    $router->post('/crud/{resource}',                 [CrudController::class, 'store'],  array_merge($crudRbac, [CsrfMiddleware::class]));
    $router->get('/crud/{resource}/{id}/editar',      [CrudController::class, 'edit'], $crudRbac);
    $router->post('/crud/{resource}/{id}',            [CrudController::class, 'update'], array_merge($crudRbac, [CsrfMiddleware::class]));
    $router->get('/crud/{resource}/{id}',             [CrudController::class, 'show'], $crudRbac);
    $router->post('/crud/{resource}/{id}/eliminar',   [CrudController::class, 'delete'], array_merge($crudRbac, [CsrfMiddleware::class]));
    $router->post('/crud/{resource}/{id}/accion/{action}',   [CrudController::class, 'action'],     array_merge($crudRbac, [CsrfMiddleware::class]));
    $router->post('/crud/{resource}/accion-masiva/{action}', [CrudController::class, 'bulkAction'], array_merge($crudRbac, [CsrfMiddleware::class]));

    $router->get('/calendario/{key}',         [CalendarioController::class, 'index'], $crudRbac);
    $router->get('/calendario/{key}/eventos', [CalendarioController::class, 'events'], $crudRbac);
```

- [ ] **Step 4: Verificación enfocada** — Run:

```bash
php tests/run.php Docs/CrudRbacRouter
php tests/run.php Kernel/CrudRoutePermissionResolver
php tests/run.php Kernel/CrudRbacMiddleware
```

Expected: **PASS** — 4 + 7 + 3 tests, 0 failed.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel` / Expected: 0 failed.

- [ ] **Step 6: Commit** — archivos: `src/Application/Services/CrudRoutePermissionResolver.php`, `src/Presentation/Middlewares/CrudRbacMiddleware.php`, `routes/web.php`, `skeleton/routes/web.php` / mensaje: `feat(rbac): add CrudRbacMiddleware for CRUD/calendario router defense (M3)`

---

### Task 5: Documentación F5, semver `1.2.5`, tag y PR (F5, F6)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/crud-rbac-router-m3`

**Depends on:** Task 4

**Files:**
- Modify: `config/rbac_route_permissions.php`
- Modify: `docs/core/auth_rbac_seguridad_v0.1.md` (§5 tabla rutas)
- Modify: `composer.json`, `config/app.php`, `skeleton/config/app.php` → `1.2.5`
- Test: suites M3 + `PlatformVersionSemver`

**Interfaces:**
- Consumes: Task 4
- Produces: doc § RBAC CRUD router; tag `v1.2.5`; PR hacia `main`

- [ ] **Step 1: Escribir el test que falla** — N/A (doc/semver).

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` **antes** del bump / Expected: PASS @ `1.2.3`.

- [ ] **Step 3: Implementar el cambio mínimo**

Actualizar `config/rbac_route_permissions.php`:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'dashboard.ver',
        'administracion.ver',
        'usuarios.gestionar',
        'roles.gestionar',
        'sistema.ver',
        'pdf_kit.ver',
        'reportes.ver',
        'reportes.crear',
        'reportes.editar',
        'reportes.eliminar',
        'reportes.generar',
    ],
    'crud_router' => [
        'middleware_class' => 'Lebytek\\Framework\\Presentation\\Middlewares\\CrudRbacMiddleware',
        'routes' => ['/admin/crud/{resource}*', '/admin/calendario/{key}*'],
        'permission_source' => 'config/cruds/{resource}.json → permission_prefix + acción por URI',
        'note' => 'Slugs dinámicos no listados en middleware[] — el informe los cruza vía config/cruds/*.json',
    ],
];
```

En `docs/core/auth_rbac_seguridad_v0.1.md` §5, reemplazar fila CRUD por:

```markdown
| `/admin/crud/{resource}*` | `{permission_prefix}.ver` \| `crear` \| `editar` \| `eliminar` vía **`CrudRbacMiddleware`** (router) **y** `CrudResourceService` (servicio) |
| `/admin/calendario/{key}*` | `{permission_prefix}.ver` del CRUD vinculado vía **`CrudRbacMiddleware`** **y** use cases calendario |
```

Añadir subsección **§5.1 RBAC dinámico CRUD/calendario** con diagrama de flujo Auth → CrudRbacMiddleware → Controller → Service (defensa doble) y referencia a `scripts/rbac_integrity_report.php`.

Bump semver en tres archivos a **`1.2.5`**.

- [ ] **Step 4: Verificación enfocada** — Run:

```bash
php tests/run.php PlatformVersionSemver
php tests/run.php Docs/CrudRbacRouter Kernel/CrudRoutePermissionResolver Kernel/CrudRbacMiddleware
grep -c 'CrudRbacMiddleware' docs/core/auth_rbac_seguridad_v0.1.md
git diff origin/main...HEAD --name-only
```

Expected: semver 3 tests PASS; gates M3 PASS; grep ≥ `2`; diff sólo archivos del plan.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php` / Expected: 0 failed.

Distinción entorno: si PHP CLI ausente en agente cloud (bloqueador verificado 2026-08-06), ejecutar en runner local o CI post-merge; no marcar verde sin evidencia.

Post-merge tag:

```bash
git tag -a v1.2.5 -m "CrudRbacMiddleware for CRUD/calendario router RBAC (M3)"
git push origin v1.2.5
```

Push rama y PR:

```bash
git push -u origin feature/crud-rbac-router-m3
gh pr create --base main --title "feat(rbac): CrudRbacMiddleware for CRUD/calendario routes (M3)" \
  --body "Implementa F1–F6 del spec 2026-08-06.

- CrudRoutePermissionResolver + CrudRbacMiddleware
- Registro harness/skeleton routes/web.php
- Tests CrudRbacRouter / CrudRoutePermissionResolver / CrudRbacMiddleware
- docs/core/auth_rbac_seguridad_v0.1.md §5.1
- semver 1.2.5

Spec: docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md
Audit: #84 (M3)
Plan: docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md"
```

- [ ] **Step 6: Commit / tag** — commit doc/config: `docs(rbac): document CrudRbacMiddleware router layer (M3)`; commit semver: `chore(release): bump platform version to 1.2.5 for CRUD router RBAC`; tag post-merge en `main`.

**Requiere operador humano:** sí — smoke O1 staging con rol restringido; bump Portal lock P1 (M6); QA responsive 403 en 320–768px (AC-UX4).

---

## Fuera de alcance

| ID | Tema | Motivo |
|----|------|--------|
| P1–P3 | Portal bump/merge rutas | Owner Portal; M6 gh 404 |
| O1–O2 | QA staging/prod RBAC | Ops manual |
| M4 | `GET /api/health` | Plan 2026-08-05 (0/5) |
| M5 | `permisos.gestionar` seeds | Spec futuro CF8 |
| D7 | GitHub Actions CI | Plan 2026-08-04 (0/5) |
| Portal afterListRows | mkt_leads | Plan Portal 2026-08-02 |
| D6 | skeleton.lebytek.com | Plan 2026-07-26 |
| Eliminar checks servicio | Regresión seguridad | Prohibido AC4 |
| Legacy monolith | archive/backoffice | Evidencia histórica only |

## Criterios finales de aceptación

- [ ] Rutas CRUD/calendario registran `CrudRbacMiddleware` harness + skeleton (AC1).
- [ ] Usuario sin permiso recibe 403 **antes** del controlador (AC2).
- [ ] Mensaje 403 incluye slug requerido HTML/JSON (AC3, U1–U3).
- [ ] `CrudResourceService` / calendario mantienen `RbacService::verificar()` (AC4).
- [ ] `php tests/run.php Docs/CrudRbacRouter` PASS (AC5).
- [ ] `php tests/run.php Kernel/CrudRbacMiddleware` PASS (AC6).
- [ ] Doc § RBAC + `rbac_route_permissions.php` actualizados (AC7, U8).
- [ ] Tag `v1.2.5`; trío semver sincronizado; `PlatformVersionSemverTest` PASS (AC8).
- [ ] Diff sin Marketing/Portal en `src/`; `SkeletonPurityTest` PASS (AC9).
- [ ] AC-UX1–AC-UX4: modo normal documentado; U1–U8; carry-forward CF3–CF4, CF5′, CF7–CF10; smoke responsive 403.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Usuario ve 403 temprano (antes UI vacía) | O1 doc: comportamiento correcto |
| Doble 403 middleware + servicio | Idempotente; mismo slug |
| Recurso inválido vs RBAC | ValidationException → `$next` (U4) |
| Portal no mergea rutas P2 | Documentado; bump semver |
| Orden release vs M4 | Numeración `1.2.5` asume M4 → `1.2.4` primero |
| PHP ausente en cloud agent | Verificar en entorno PHP ≥8.1 |

**Rollback:** revertir PR — middleware desaparece; RBAC vuelve a sólo servicio (estado @ `ddc55ec`).

## Evidencia que debe recopilar el ejecutor

- Salida `php tests/run.php Docs/CrudRbacRouter Kernel/CrudRoutePermissionResolver Kernel/CrudRbacMiddleware`.
- Captura 403 HTML con slug `demo_clientes.ver` (usuario autenticado sin permiso).
- Número PR Framework y URL tag `v1.2.5`.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-06T12:40:00Z (AUTOMATION-03 — plan creado) |
| Plan creado UTC | 2026-08-06T12:40:00Z |
| Framework `origin/main` referencia | `ddc55ec8fb025acfada9500d711bbbe8843f5997` |
| Tareas completadas / totales | **0 / 5** |
| Modo fuente | normal (spec PR #85 @ `automation/spec-2026-08-06`; audit #84 mergeado) |
| Siguiente tarea ejecutable | **Task 1** — `CrudRbacRouterTest` (TDD rojo) |
| Prerrequisitos | Ninguno — rutas CRUD/calendario existen sin middleware @ `ddc55ec`; `demo_clientes` / `demo_citas` JSON verificados |
| Bloqueos | PHP CLI ausente en agente cloud; Portal P1/P2 (M6); rama `feature/crud-rbac-router-m3` no existe aún (creable desde `main`) |
| Estado | **Pendiente de implementación** |
