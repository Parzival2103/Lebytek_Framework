# Post-v1.2.11 Debt — Harness M11 + RBAC M5 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar **M11** (suite monolítica sin contaminación de sesión) y **M5** (slug dedicado `permisos.gestionar` en seeds, migración, rutas, middleware accionable y tests) en el paquete Framework, publicando semver **`1.2.12`**, sin reabrir CRUD-C4 ni negocio Portal.

**Architecture:** Enfoque A del spec 2026-08-14: (1) `microtest.php` invoca `microtest_reset_session()` tras cada `test()`; (2) migración idempotente + `schema.sql` insertan `permisos.gestionar` y el rol administrador lo recibe vía pivot; (3) rutas `/admin/administracion/permisos/*` usan `RbacMiddleware('permisos.gestionar')` con respuestas 403 accionables (HTML + JSON AJAX, patrón M3); (4) hygiene docs archiva planes M3/M4 ya shipeados. P-LOCK consumidor queda documentado para operador manual (M6).

**Tech Stack:** PHP `>=8.2`, harness `php tests/run.php` + `tests/lib/microtest.php`, `Lebytek\Framework\Kernel\Security\Session`, SQL MySQL 8 (`database/schema/schema.sql`, `database/migrations/`), `config/modules/core.php`, rutas `routes/web.php` + espejo skeleton, `RbacMiddleware`.

**Source spec:** `docs/superpowers/specs/2026-08-14-audit-post-v1211-debt-design.md` · **Modo:** normal  
**Source audit PR:** #124 — https://github.com/Parzival2103/Lebytek_Framework/pull/124 (auditoría 2026-08-14, mergeada; 0 hallazgos nuevos; deuda M11/M5/P-LOCK arrastrada)  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`cea890e34d53ad2e400237136bc691a98e030511`); rama `feature/post-v1211-debt-m11-m5` (creable desde `main` — verificado `git ls-remote origin refs/heads/main`)

## Global Constraints

- Solo plataforma Framework (`src/`, `tests/`, `database/`, `routes/`, `skeleton/`, `docs/`); sin Marketing/leads/membresías/`dom_*`.
- No editar `vendor/`; no habilitar verticals `marketing`/`payments`/`invoicing`.
- No merge `feature/backoffice-api-integration` → `main`.
- Migración M5 **debe** registrarse en `config/modules/core.php` → `migraciones` (gate `tests/Platform/SchemaBootstrapTest.php` L75–84).
- Semver patch **`1.2.12`** en trío `composer.json` / `config/app.php` / `skeleton/config/app.php` (Task 6).
- Portal bump lock / smoke CAS = **fuera de alcance** (M6 gh 404); documentar en `docs/release/v1.2.12.md`.

## Requisitos → tareas (matriz)

| ID | Requisito spec | Owner | Tarea | Verificación |
|----|----------------|-------|-------|--------------|
| F1 | Reset sesión post-test en `microtest.php` | Framework | Task 1 | `php tests/run.php Docs/MonolithicHarnessSessionIsolation` |
| F2 | Gate M11 monolito vs aislado | Framework | Task 1 | `php tests/run.php Kernel/ApiHealthPublicDispatch` |
| F3 | Seed + migración `permisos.gestionar` | Framework | Task 2 | `php tests/run.php Docs/PermisosGestionarSlug` |
| F4 | Rutas permisos → slug dedicado + 403 accionable | Framework | Task 3 | `php tests/run.php Docs/PermisosGestionarSlug Kernel/PermisosRbacMiddleware` |
| F5 | Test gate M5 RBAC 403/200 | Framework | Task 4 | `php tests/run.php Kernel/PermisosRbacMiddleware` |
| F6 | Doc § catálogo permisos | Framework | Task 5 | grep `auth_rbac_seguridad_v0.1.md` |
| F7 | Archivar planes M3/M4 + stub `docs/release/v1.2.8.md` | Framework | Task 5 | `test -f docs/release/v1.2.8.md`; planes en `docs/archive/superpowers/plans/` |
| F8 | Semver `1.2.12` + notas consumidor | Framework | Task 6 | `php tests/run.php Docs/PlatformVersionSemver` |
| P1/P2 | Bump Portal lock ≥ `v1.2.11` + smoke CAS | Portal/Ops | **Fuera de alcance** | operador manual post-tag |

**Fuera de alcance:** reimplementar CRUD-C4/CAS (#118 / `v1.2.11`); código Portal; deploy VPS/SSH; tag publicado por operador humano post-merge; cierre PRs `#116`/`#117`/`#121`/`#123` (AUTOMATION-03).

---

### Task 1: Harness M11 — reset de sesión post-test + gate monolítico

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/post-v1211-debt-m11-m5`  
**Depends on:** None  
**Files:**
- Modify: `tests/lib/microtest.php` (`test()` L7–17; añadir helper + invocación)
- Create: `tests/Docs/MonolithicHarnessSessionIsolationTest.php`

**Interfaces:**
- Consumes: `Lebytek\Framework\Kernel\Security\Session` (flag privado `$started` vía `ReflectionClass`)
- Produces: `function microtest_reset_session(): void` — vacía `$_SESSION`, cierra sesión activa si existe, resetea `Session::$started` a `false`

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/MonolithicHarnessSessionIsolationTest.php`:

```php
<?php

declare(strict_types=1);

$microtestPath = dirname(__DIR__, 2) . '/tests/lib/microtest.php';
$src = is_readable($microtestPath) ? (string) file_get_contents($microtestPath) : '';

test('microtest.php define microtest_reset_session()', function () use ($src): void {
    assert_true(
        str_contains($src, 'function microtest_reset_session'),
        'microtest_reset_session ausente — implementar reset post-test (spec M11 F1)'
    );
});

test('microtest.php invoca reset tras cada test()', function () use ($src): void {
    assert_true(
        preg_match('/function test\([^)]+\)[^{]*\{[^}]*microtest_reset_session\s*\(\s*\)/s', $src) === 1
        || preg_match('/finally\s*\{[^}]*microtest_reset_session/s', $src) === 1,
        'test() debe llamar microtest_reset_session() al final (M11)'
    );
});

test('simulación: auth_user no persiste entre invocaciones test() consecutivas', function (): void {
    require_once dirname(__DIR__, 2) . '/tests/lib/microtest.php';
    $_SESSION['auth_user'] = ['id' => 1, 'email' => 'pollution@test.local'];
    microtest_reset_session();
    assert_false(isset($_SESSION['auth_user']), 'auth_user debe ausentarse tras reset (M11)');
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/MonolithicHarnessSessionIsolation` / Expected: FAIL — helper ausente o `test()` no invoca reset; tercer test FAIL si `microtest_reset_session` no existe.

- [ ] **Step 3: Implementar el cambio mínimo** — en `tests/lib/microtest.php`:

```php
function microtest_reset_session(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $ref = new \ReflectionClass(\Lebytek\Framework\Kernel\Security\Session::class);
    $prop = $ref->getProperty('started');
    $prop->setAccessible(true);
    $prop->setValue(null, false);
}

function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__mt']['pass']++;
        fwrite(STDOUT, "  PASS  {$name}\n");
    } catch (\Throwable $e) {
        $GLOBALS['__mt']['fail']++;
        $GLOBALS['__mt']['failures'][] = $name . ' :: ' . $e->getMessage();
        fwrite(STDOUT, "  FAIL  {$name}  --  " . $e->getMessage() . "\n");
    } finally {
        microtest_reset_session();
    }
}
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/MonolithicHarnessSessionIsolation Kernel/ApiHealthPublicDispatch` / Expected: PASS — 3 tests M11 + 4 tests dispatch (sin sesión residual; `/api/ping` 302 en monolito).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Auth Kernel` / Expected: PASS — suites Auth (52+) y Kernel no regresionan; login tests siguen verdes porque cada test configura su propio estado.

- [ ] **Step 6: Commit** — archivos: `tests/lib/microtest.php`, `tests/Docs/MonolithicHarnessSessionIsolationTest.php` — mensaje: `fix(tests): reset PHP session after each microtest (M11)`

---

### Task 2: M5 — migración idempotente + schema base `permisos.gestionar`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/post-v1211-debt-m11-m5`  
**Depends on:** None (paralelo-safe con Task 1)  
**Files:**
- Create: `database/migrations/20260814120000_auth_permiso_gestionar.sql`
- Create: `skeleton/database/migrations/20260814120000_auth_permiso_gestionar.sql` (copia idéntica)
- Modify: `database/schema/schema.sql` (bloque INSERT `auth_permisos` ~L298–304)
- Modify: `database/seeds_legacy/010_auth_permisos.sql` (INSERT list)
- Modify: `config/modules/core.php` (`migraciones` array L15–17; `permisos` array L20–23)
- Create: `tests/Docs/PermisosGestionarSlugTest.php` (stub; ampliar en Tasks 3–5)

**Interfaces:**
- Consumes: tabla `auth_permisos` (`slug` UNIQUE), pivot `auth_roles_permisos`
- Produces: fila `{ slug: 'permisos.gestionar', nombre: 'Gestionar permisos', modulo: 'administracion' }`; admin recibe permiso vía INSERT IGNORE pivot

**SQL migración (idempotente):**

```sql
-- Permiso dedicado catálogo RBAC (M5). Idempotente.
SET NAMES utf8mb4;

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
  ('Gestionar permisos', 'permisos.gestionar', 'administracion', 'Catálogo RBAC: crear/editar/eliminar permisos');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'permisos.gestionar'
WHERE `r`.`slug` = 'administrador';
```

**schema.sql** — añadir fila en INSERT IGNORE L298–304:

```sql
  ('Gestionar permisos', 'permisos.gestionar', 'administracion'),
```

**core.php** — añadir a `migraciones`:

```php
'20260814120000_auth_permiso_gestionar.sql',
```

y a `permisos`:

```php
'permisos.gestionar',
```

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/PermisosGestionarSlugTest.php`:

```php
<?php

declare(strict_types=1);

test('schema.sql declara permisos.gestionar', function (): void {
    $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/schema.sql');
    assert_true(
        str_contains($schema, "'permisos.gestionar'"),
        'permisos.gestionar ausente en database/schema/schema.sql (M5 F3)'
    );
});

test('migración auth_permiso_gestionar registrada en core.php', function (): void {
    $core = require dirname(__DIR__, 2) . '/config/modules/core.php';
    $migs = $core['migraciones'] ?? [];
    assert_true(
        in_array('20260814120000_auth_permiso_gestionar.sql', $migs, true),
        'migración M5 no declarada en config/modules/core.php'
    );
});

test('010_auth_permisos seed legacy incluye permisos.gestionar', function (): void {
    $seed = file_get_contents(dirname(__DIR__, 2) . '/database/seeds_legacy/010_auth_permisos.sql');
    assert_true(str_contains($seed, 'permisos.gestionar'), 'seed legacy sin permisos.gestionar');
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/PermisosGestionarSlug` / Expected: FAIL — slug ausente en schema/seed/manifiesto.

- [ ] **Step 3: Implementar el cambio mínimo** — crear migración harness + skeleton espejo; patch `schema.sql`, `010_auth_permisos.sql`, `core.php` según arriba.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/PermisosGestionarSlug Platform/SchemaBootstrap` / Expected: PASS — slug presente; manifiesto migraciones alineado.

- [ ] **Step 5: Regresión relevante** — Run: `rg "permisos\.gestionar" database/ config/modules/core.php` / Expected: ≥3 coincidencias (schema, migración, manifiesto).

- [ ] **Step 6: Commit** — archivos listados — mensaje: `feat(rbac): add permisos.gestionar seed and idempotent migration (M5)`

---

### Task 3: M5 — rutas + middleware accionable (U1–U3)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/post-v1211-debt-m11-m5`  
**Depends on:** Task 2  
**Files:**
- Modify: `routes/web.php` (~L62–66, `$rbacPermisos`)
- Modify: `skeleton/routes/web.php` (espejo idéntico)
- Modify: `src/Presentation/Middlewares/RbacMiddleware.php` (403 HTML + JSON con slug, patrón `CrudRbacMiddleware`)
- Modify: `tests/Docs/PermisosGestionarSlugTest.php`

**Interfaces:**
- Consumes: `RbacMiddleware('permisos.gestionar')`, `Request::isAjax()`
- Produces: `$rbacPermisos = [new RbacMiddleware('permisos.gestionar')]`; JSON `{ "error": "Acceso denegado.", "permiso": "permisos.gestionar" }`; HTML 403 con slug y copy accionable

- [ ] **Step 1: Escribir el test que falla** — ampliar `tests/Docs/PermisosGestionarSlugTest.php`:

```php
test('routes/web.php usa permisos.gestionar en rutas permisos', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
    assert_true(
        preg_match("/RbacMiddleware\s*\(\s*'permisos\.gestionar'\s*\)/", $routes) === 1,
        'routes/web.php debe usar RbacMiddleware(\'permisos.gestionar\') (M5 F4)'
    );
    assert_false(
        str_contains($routes, 'permisos.gestionar no existe en seeds'),
        'eliminar comentario workaround M5'
    );
});

test('skeleton/routes/web.php espeja permisos.gestionar', function (): void {
    $sk = file_get_contents(dirname(__DIR__, 2) . '/skeleton/routes/web.php');
    assert_true(
        preg_match("/RbacMiddleware\s*\(\s*'permisos\.gestionar'\s*\)/", $sk) === 1,
        'skeleton/routes/web.php debe espejar slug permisos.gestionar'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/PermisosGestionarSlug` / Expected: FAIL — rutas aún usan `administracion.ver`.

- [ ] **Step 3: Implementar el cambio mínimo**
  1. Reemplazar `$rbacPermisos = [new RbacMiddleware('administracion.ver')]` por `'permisos.gestionar'` en harness + skeleton; eliminar comentario workaround.
  2. En `RbacMiddleware.php`, alinear con `CrudRbacMiddleware` (L40–53):

```php
if (!$policy->puede($this->permiso)) {
    if ($request->isAjax()) {
        return Response::json([
            'error'   => 'Acceso denegado.',
            'permiso' => $this->permiso,
        ], 403);
    }
    $message = "No tienes permiso para acceder a esta sección (`{$this->permiso}`). "
        . 'Solicítalo al administrador o revisa tu rol en Usuarios/Roles.';
    Session::flash('error', $message);
    return new Response($message, 403, ['Content-Type' => 'text/html; charset=UTF-8']);
}
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/PermisosGestionarSlug` / Expected: PASS (5 tests).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel/SkeletonPurity Kernel/CrudRbacMiddleware` / Expected: PASS — rutas skeleton alineadas; M3 no regresionado.

- [ ] **Step 6: Commit** — `routes/web.php`, `skeleton/routes/web.php`, `src/Presentation/Middlewares/RbacMiddleware.php`, `tests/Docs/PermisosGestionarSlugTest.php` — mensaje: `fix(rbac): permisos.gestionar routes and actionable 403 (M5 U1-U3)`

---

### Task 4: M5 — gate RBAC 403/200 en middleware permisos

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/post-v1211-debt-m11-m5`  
**Depends on:** Task 3  
**Files:**
- Create: `tests/Kernel/PermisosRbacMiddlewareTest.php`

**Interfaces:**
- Consumes: `RbacMiddleware('permisos.gestionar')`, `$_SESSION['auth_permisos']`, `Request::isAjax()`
- Produces: 403 HTML/JSON con slug `permisos.gestionar` cuando falta permiso; 200 cuando presente

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Kernel/PermisosRbacMiddlewareTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Presentation\Middlewares\RbacMiddleware;

test('RbacMiddleware permisos.gestionar returns 403 HTML with slug without permiso', function (): void {
    $_SESSION['auth_permisos'] = ['administracion.ver'];
    $_SESSION['auth_roles'] = [];

    $middleware = new RbacMiddleware('permisos.gestionar');
    $response = $middleware->handle(
        new Request('GET', '/admin/administracion/permisos'),
        fn (Request $r) => Response::html('<ok/>')
    );

    assert_same(403, $response->getStatusCode());
    assert_true(str_contains($response->getBody(), 'permisos.gestionar'));
});

test('RbacMiddleware permisos.gestionar returns JSON permiso field for AJAX', function (): void {
    $_SESSION['auth_permisos'] = ['administracion.ver'];
    $_SESSION['auth_roles'] = [];

    $request = new Request(
        'GET',
        '/admin/administracion/permisos',
        [],
        [],
        ['X-Requested-With' => 'XMLHttpRequest']
    );

    $middleware = new RbacMiddleware('permisos.gestionar');
    $response = $middleware->handle($request, fn (Request $r) => Response::json(['ok' => true]));

    assert_same(403, $response->getStatusCode());
    $data = json_decode($response->getBody(), true);
    assert_same('permisos.gestionar', $data['permiso'] ?? null);
});

test('RbacMiddleware permisos.gestionar allows access with slug', function (): void {
    $_SESSION['auth_permisos'] = ['permisos.gestionar'];
    $_SESSION['auth_roles'] = [];

    $called = false;
    $middleware = new RbacMiddleware('permisos.gestionar');
    $response = $middleware->handle(
        new Request('GET', '/admin/administracion/permisos'),
        function (Request $r) use (&$called): Response {
            $called = true;
            return Response::html('<ok/>');
        }
    );

    assert_true($called, 'usuario con permisos.gestionar debe pasar middleware');
    assert_same(200, $response->getStatusCode());
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Kernel/PermisosRbacMiddleware` / Expected: FAIL — JSON sin campo `permiso` o HTML sin slug (pre-Task 3).

- [ ] **Step 3: Implementar el cambio mínimo** — N/A si Task 3 aplicado; test documenta contrato AC-M5-4.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Kernel/PermisosRbacMiddleware Docs/PermisosGestionarSlug` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel/CrudRbacMiddleware` / Expected: PASS — M3 no regresionado.

- [ ] **Step 6: Commit** — `tests/Kernel/PermisosRbacMiddlewareTest.php` — mensaje: `test(rbac): gate permisos.gestionar middleware 403/200 (M5)`

---

### Task 5: Docs hygiene — auth RBAC, release notes, archivar planes M3/M4

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/post-v1211-debt-m11-m5`  
**Depends on:** Task 3  
**Files:**
- Modify: `docs/core/auth_rbac_seguridad_v0.1.md` (tabla rutas ~L66–69)
- Create: `docs/release/v1.2.8.md`
- Create: `docs/release/v1.2.12.md`
- Move + reconcile: `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` → `docs/archive/superpowers/plans/` (marcar tareas [x], evidencia PR #114 / `v1.2.10`)
- Move + reconcile: `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` → `docs/archive/superpowers/plans/` (marcar tareas [x], evidencia PR #114 / `v1.2.10`)
- Modify: `tests/Docs/PermisosGestionarSlugTest.php` (assert doc + v1.2.8)

**Interfaces:**
- Produces: doc § indica `permisos.gestionar` canónico; `v1.2.8.md` referencia PR #111 C6; `v1.2.12.md` § consumidores P-LOCK; planes M3/M4 archivados con estado completo

- [ ] **Step 1: Escribir el test que falla** — añadir a `PermisosGestionarSlugTest.php`:

```php
test('auth_rbac_seguridad documenta permisos.gestionar en rutas permisos', function (): void {
    $doc = file_get_contents(dirname(__DIR__, 2) . '/docs/core/auth_rbac_seguridad_v0.1.md');
    assert_true(
        str_contains($doc, 'permisos.gestionar') && str_contains($doc, '/admin/administracion/permisos'),
        'docs/core/auth_rbac_seguridad_v0.1.md debe documentar slug permisos.gestionar (M5 F6)'
    );
});

test('docs/release/v1.2.8.md existe con referencia C6', function (): void {
    $path = dirname(__DIR__, 2) . '/docs/release/v1.2.8.md';
    assert_true(is_readable($path), 'docs/release/v1.2.8.md ausente (F7 H1)');
    $body = file_get_contents($path);
    assert_true(str_contains($body, '1.2.8') && str_contains($body, '#111'), 'v1.2.8.md debe citar PR #111 uploads C6');
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/PermisosGestionarSlug` / Expected: FAIL — doc aún dice `administracion.ver` para permisos; `v1.2.8.md` ausente.

- [ ] **Step 3: Implementar el cambio mínimo**
  1. En `auth_rbac_seguridad_v0.1.md` tabla rutas: cambiar fila `/admin/administracion/permisos` de `administracion.ver` → `permisos.gestionar`; añadir nota «separación ver administración vs gestionar catálogo».
  2. Crear `docs/release/v1.2.8.md` mínimo (fecha merge 2026-08-10, PR #111, tema uploads C6, tag `v1.2.8`).
  3. Crear `docs/release/v1.2.12.md` con § consumidores: bump lock `^1.2.12` para M5; secuencia `1.2.11` CAS → `1.2.12` RBAC permisos; checklist P-LOCK (operador manual).
  4. Reconciliar planes M3/M4: marcar checkboxes completados con evidencia PR #114; actualizar § Estado de ejecución; `git mv` a `docs/archive/superpowers/plans/`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/PermisosGestionarSlug` / Expected: PASS (7 tests).

- [ ] **Step 5: Regresión relevante** — Run: `test ! -f docs/superpowers/plans/2026-08-05-audit-api-health-public.md && test -f docs/archive/superpowers/plans/2026-08-05-audit-api-health-public.md && test ! -f docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md && test -f docs/archive/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` / Expected: exit 0 — planes M3/M4 archivados.

- [ ] **Step 6: Commit** — docs + test + moves — mensaje: `docs(rbac): permisos.gestionar runbook, release notes, archive M3/M4 plans (M5/F7)`

---

### Task 6: Semver patch 1.2.12 + regresión gate

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/post-v1211-debt-m11-m5`  
**Depends on:** Tasks 1–5  
**Files:**
- Modify: `composer.json` (`version`)
- Modify: `config/app.php` (`version`)
- Modify: `skeleton/config/app.php` (`version`)

**Interfaces:**
- Produces: trío semver `1.2.12` sincronizado

- [ ] **Step 1: Escribir el test que falla** — Run: `php tests/run.php Docs/PlatformVersionSemver` / Expected: PASS @ `1.2.11` pre-bump (sanity); tras bump debe seguir PASS @ `1.2.12`.

- [ ] **Step 2: Ejecutar semver pre-bump** — Run: `grep '"version"' composer.json config/app.php skeleton/config/app.php` / Expected: las tres líneas muestran `1.2.11`.

- [ ] **Step 3: Implementar el cambio mínimo** — bump trío a `1.2.12`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/PlatformVersionSemver Docs/PermisosGestionarSlug Docs/MonolithicHarnessSessionIsolation Kernel/ApiHealthPublicDispatch Kernel/PermisosRbacMiddleware` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Auth Kernel` / Expected: PASS. Run monolito (sin MySQL): `php tests/run.php 2>&1 | tail -3` / Expected: **0 failed** en tests Auth/ping/health por sesión (MySQL env fails aceptables si servidor ausente).

- [ ] **Step 6: Commit** — trío semver — mensaje: `chore(release): bump platform version to 1.2.12 (M5+M11)`

**Requiere operador humano:** sí — publicar tag Git `v1.2.12` post-merge; bump `composer.lock` Portal ≥ `1.2.11` (ideal `1.2.12`); smoke CAS staging (P-LOCK P1/P2 bloqueados M6).

---

## Criterios finales de aceptación

- [ ] `microtest_reset_session()` invocado tras cada test; `MonolithicHarnessSessionIsolationTest` PASS.
- [ ] `php tests/run.php Kernel/ApiHealthPublicDispatch` PASS en suite monolítica (sin auth_user residual).
- [ ] `permisos.gestionar` en schema + migración + `core.php` manifiesto.
- [ ] Rutas permisos usan `permisos.gestionar` (harness + skeleton).
- [ ] `PermisosRbacMiddlewareTest`: solo `administracion.ver` → 403 con slug; JSON AJAX incluye `permiso`; con `permisos.gestionar` → 200.
- [ ] Trío semver `1.2.12`; `PlatformVersionSemverTest` PASS.
- [ ] `docs/release/v1.2.8.md` y `docs/release/v1.2.12.md` presentes.
- [ ] Planes M3/M4 archivados con checkboxes alineados a PR #114.
- [ ] Sin cambios negocio Portal en este repo.

## Fuera de alcance

| ID | Tema | Motivo |
|----|------|--------|
| P1/P2 | Portal lock + smoke CAS | M6 gh 404; operador manual |
| O1 | Token gh Portal | Ops |
| O2 | skeleton.lebytek.com D6 | Ops |
| M10 | Huecos audits 03–05, 10 | Ops backfill |
| CRUD-C4 | CAS | Cerrado #118 / v1.2.11 |
| PR #116/#117/#121/#123 | Specs duplicados | AUTOMATION-03 |

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Reset sesión rompe test que depende de contaminación cruzada | Tests deben setup explícito al inicio (spec M11) |
| Rol operador pierde catálogo permisos post-migración | Migración asigna slug a administrador; operador re-asigna roles manualmente |
| Portal sin bump sigue sin CAS | Documentar P-LOCK en v1.2.12.md |
| Cambio global en `RbacMiddleware` afecta otras rutas | Copy accionable mejora UX; contrato JSON alineado con M3 |

**Rollback:** revertir PR único o commits Tasks 1–6; migración idempotente no destructiva.

## Evidencia que debe recopilar el ejecutor

- Salida PASS gates Tasks 4–6.
- PR URL + SHA merge Framework.
- Nota operador: tag `v1.2.12` publicado; Portal lock pendiente M6.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-14 |
| Framework `origin/main` referencia | `cea890e34d53ad2e400237136bc691a98e030511` |
| Tareas completadas / totales | 0 / 6 |
| Siguiente tarea ejecutable | **Task 1** — M11 microtest reset (sin prerrequisitos) |
| Prerrequisitos | PHP ≥8.2, `composer install` |
| Bloqueos | Tag `v1.2.12` requiere operador humano; Portal P-LOCK bloqueado M6 |
| Plan padre reconciliado | `2026-08-09-audit-crud-uploads-hardening.md` archivado (C6 completo PR #111) |
| Estado | Pendiente de implementación |
