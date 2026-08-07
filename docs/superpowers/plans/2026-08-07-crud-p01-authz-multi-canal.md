# CRUD Engine P01 — AuthZ multi-canal (C1+C2+C5) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar tres canales AuthZ independientes del CRUD Engine: IDOR con `scope_handler` custom (C1), acciones ejecutables sin `permission` (C2) y exfiltración vía Reportes sin `{resource}.ver` (C5).

**Architecture:** Unificar el bloqueo por-ID en `CrudScopeResolver::assertOwnedBy` evaluando las condiciones del scope resuelto (owner **y** custom) contra el registro; exigir `permission` en validator + fail-closed en `CrudActionService::run`/`runBulk`; gate `{resource}.ver` al inicio de `CrudReporteDataSource::rows`/`findRecord` usando el callable `$can` ya pasado por los use cases (mismo contrato de mensaje que `RbacService::verificar`).

**Tech Stack:** PHP 8.1+ (`composer.json`), harness `tests/run.php` + `tests/lib/microtest.php`, capas `Lebytek\Framework\{Application,Domain}`, configs JSON `config/cruds/` + espejo `skeleton/config/cruds/`.

**Programa:** Remediación CRUD Engine · **Punto:** 1/12 · **IDs:** C1, C2, C5

**Source spec:** `docs/superpowers/specs/2026-08-07-audit-crud-authz-multi-canal-design.md`  ·  **Modo:** normal

**Source audit:** `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (PR #90) · auditoría diaria `docs/audits/2026-08-07-auditoria-tecnica-diaria.md` (PR #96)

**Estructura programa:** `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md`

**Source audit PR:** #96 — https://github.com/Parzival2103/Lebytek_Framework/pull/96 (diaria); hallazgos CRUD #90 — https://github.com/Parzival2103/Lebytek_Framework/pull/90

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`da3ab58bd77be95c4003341454d939aa2584a742`); rama de implementación `cursor/crud-p01-authz-multi-canal-0a6f` (PR #95 OPEN — verificado `git ls-remote origin refs/heads/cursor/crud-p01-authz-multi-canal-0a6f`)

## Baseline asumida (puntos 1..N-1)

| Punto | Plan | Estado verificado | Evidencia (SHA / PR / archivos) |
|------:|------|-------------------|----------------------------------|
| — | (ninguno; este es el punto 1) | N/A | `origin/main` @ `da3ab58`; no existen `docs/superpowers/plans/2026-08-07-crud-p0*.md` previos |

**Implicaciones para este plan:** se implementa sobre el código actual de `main` sin asumir fixes de puntos 2–12. El plan histórico `2026-08-06-audit-crud-rbac-router.md` (G4 / punto 6) **no** se ejecuta aquí y **no** cierra C5.

## Global Constraints

- Solo IDs **C1, C2, C5** como entregables de producto.
- No editar `vendor/`; no negocio Portal en este repo.
- No debilitar CSRF, soft-delete, whitelist de handlers ni owner scope built-in ya cubierta por `tests/Security/CrudActionOwnershipTest.php`.
- Espejo `skeleton/config/cruds/` solo si algún JSON demo queda inválido tras C2 (hoy todos los handlers/transitions demos ya declaran `permission` — verificar; no tocar si siguen válidos).
- Semver: endurecimiento AuthZ + rechazo de configs inseguras previamente cargables → **PATCH `1.2.6`** (reservados: health M4 → `1.2.4`, router M3 → `1.2.5`). Sincronizar trío `composer.json` / `config/app.php` / `skeleton/config/app.php`.
- Mensaje IDOR: conservar exactamente `El registro solicitado no existe.` (no revelar existencia).
- Mensaje RBAC Reportes/acciones: alinear con `RbacService::verificar` → `No tienes permiso para realizar esta acción: {slug}` vía `AccesoException`.

## Requisitos → tareas (matriz)

| ID auditoría | Requisito | Owner | Tarea | Verificación |
|--------------|-----------|-------|-------|--------------|
| C1 | `assertOwnedBy` reaplica scope custom (`scope_handler`) en acceso por ID | Framework | Task 1–2 | `php tests/run.php Security/CrudActionOwnership Crud/Scope/CrudScopeResolver` |
| C2 | `permission` obligatorio en actions `handler`/`transition` (validator + runtime) | Framework | Task 3–4 | `php tests/run.php Crud/Action/CrudConfigValidatorActions Crud/Action/CrudActionPermission` |
| C5 | `CrudReporteDataSource` exige `{resource}.ver` en `rows`/`findRecord` | Framework | Task 5 | `php tests/run.php Reporte/CrudReporteDataSourceAuthz` |
| C1+C2+C5 | Regresión suites + semver | Framework | Task 6 | `php tests/run.php Security Crud/Scope Crud/Action Reporte PlatformVersionSemver` |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `src/Application/Services/CrudScopeResolver.php` | `assertOwnedBy` vía `resolve()` + evaluación de condiciones contra el registro |
| `src/Application/Services/CrudConfigValidator.php` | `actionsBlockErrors`: exigir `permission` no vacío en `handler`/`transition` |
| `src/Application/Services/CrudActionService.php` | Fail-closed: sin permiso resuelto → `AccesoException` antes de mutar |
| `src/Application/Reporte/CrudReporteDataSource.php` | Gate `{prefix}.ver` al inicio de `rows`/`findRecord` |
| `tests/Security/CrudActionOwnershipTest.php` | Extender con casos `scope_handler` (C1) |
| `tests/Crud/Action/CrudConfigValidatorActionsTest.php` | Casos permission obligatoria (C2 validator) |
| `tests/Crud/Action/CrudActionPermissionTest.php` | Gate runtime de permiso ejecutable (C2) |
| `tests/Reporte/CrudReporteDataSourceAuthzTest.php` | Gate `.ver` en datasource (C5) |
| `composer.json`, `config/app.php`, `skeleton/config/app.php` | bump `1.2.6` |
| `docs/modules/crud/modulo-crud-engine.md` | Una nota: custom scope también bloquea show/edit/update/delete/acciones |

**Interfaces producidas:**

- `CrudScopeResolver::assertOwnedBy(CrudResourceDefinition $definition, array $record, ?int $userId, callable $can): void` — misma firma; ahora cubre owner **y** `scope_handler`.
- `CrudScopeResolver::recordMatchesConditions(array $record, array $conditions): bool` — `public static`; operadores de `CrudListContext::ALLOWED_OPS`.
- `CrudActionService` — si `resolvePermission()` es `null` para acción ejecutable (`handler`/`transition`) → `AccesoException` (no silent skip).
- `CrudReporteDataSource::assertCanView(CrudResourceDefinition $definition, ?callable $can): void` — `private`; lanza `AccesoException` si `$can` es null o deniega `{prefix}.ver`.

**Interfaces consumidas (sin cambiar firma pública):**

- `CrudScopeResolver::resolve(CrudResourceDefinition, ?int, callable): ?CrudListScopeInterface`
- `CrudListScopeInterface::apply(CrudListContext): void`
- `CrudActionDefinition::resolvePermission(string $prefix): ?string`
- `CrudResourceDefinition::permissionFor('ver'): string`
- `AccesoException`, `ValidationException`
- `ReporteDataSourceInterface::rows(...)` / `ReporteRecordSourceInterface::findRecord(...)` — firmas intactas

---

### Task 1: Tests C1 — `assertOwnedBy` con `scope_handler` (rojo)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `cursor/crud-p01-authz-multi-canal-0a6f`  
**Depends on:** None  
**Files:**
- Modify: `tests/Security/CrudActionOwnershipTest.php`
- Test: `tests/Security/CrudActionOwnershipTest.php`
**Interfaces:**
- Consumes: `CrudScopeResolver::assertOwnedBy`, `CrudHandlerRegistry`, `CrudListScopeInterface`, `FixtureCustomScope` pattern de `tests/Crud/Scope/CrudScopeResolverTest.php`
- Produces: tests que fallan porque `assertOwnedBy` retorna temprano cuando no hay `list.scope.type === 'owner'`

- [ ] **Step 1: Escribir el test que falla** — al final de `tests/Security/CrudActionOwnershipTest.php`, añadir (tras los tests owner existentes):

```php
use Lebytek\Framework\Application\Crud\Context\CrudListContext;
use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Domain\Interfaces\CrudListScopeInterface;

if (!class_exists('OwnershipFixtureCustomScope')) {
    class OwnershipFixtureCustomScope implements CrudListScopeInterface
    {
        public function apply(CrudListContext $ctx): void
        {
            $ctx->addCondition('created_by', '=', 99);
        }
    }
}

function ownership_def_handler(string $handlerKey): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'eventos',
            'title' => 'Eventos',
            'table' => 'dom_eventos',
            'primary_key' => 'id',
            'permission_prefix' => 'eventos',
        ],
        'list' => ['scope_handler' => $handlerKey],
    ]);
}

test('assertOwnedBy bloquea registro fuera de scope_handler custom (C1 IDOR)', function () use ($deny): void {
    $r = new CrudScopeResolver(new CrudHandlerRegistry([
        'eventos_custom' => OwnershipFixtureCustomScope::class,
    ]));
    assert_throws(ValidationException::class, function () use ($r, $deny): void {
        $r->assertOwnedBy(
            ownership_def_handler('eventos_custom'),
            ['id' => 7, 'created_by' => 42], // 42 ≠ 99 del fixture
            42,
            $deny
        );
    });
});

test('assertOwnedBy permite registro dentro de scope_handler custom', function () use ($deny): void {
    $r = new CrudScopeResolver(new CrudHandlerRegistry([
        'eventos_custom' => OwnershipFixtureCustomScope::class,
    ]));
    $r->assertOwnedBy(
        ownership_def_handler('eventos_custom'),
        ['id' => 7, 'created_by' => 99],
        42,
        $deny
    );
    assert_true(true, 'dentro de scope custom: no lanza');
});

test('assertOwnedBy con scope_handler conserva mensaje no revelador', function () use ($deny): void {
    $r = new CrudScopeResolver(new CrudHandlerRegistry([
        'eventos_custom' => OwnershipFixtureCustomScope::class,
    ]));
    $msg = null;
    try {
        $r->assertOwnedBy(
            ownership_def_handler('eventos_custom'),
            ['id' => 7, 'created_by' => 1],
            42,
            $deny
        );
    } catch (ValidationException $e) {
        $msg = $e->getMessage();
    }
    assert_same('El registro solicitado no existe.', $msg);
});
```

Actualizar el docblock del archivo: reemplazar la frase que dice que el guard solo cubre owner por: «cubre owner built-in y `list.scope_handler` custom (C1)».

- [ ] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Security/CrudActionOwnership`  
Expected: **FAIL** — el test `assertOwnedBy bloquea registro fuera de scope_handler custom (C1 IDOR)` no lanza `ValidationException` (hoy `assertOwnedBy` retorna si `ownerMeta()` es null).

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 2 implementa.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Security/CrudActionOwnership` / Expected: FAIL (TDD rojo confirmado en los 3 tests nuevos; los owner existentes siguen PASS).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/Scope/CrudScopeResolver` / Expected: PASS (suite scope existente intacta).

- [ ] **Step 6: Commit**

```bash
git add tests/Security/CrudActionOwnershipTest.php
git commit -m "test(security): add C1 assertOwnedBy scope_handler IDOR gates (red)"
```

---

### Task 2: Implementar C1 — `assertOwnedBy` unificado (verde)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `cursor/crud-p01-authz-multi-canal-0a6f`  
**Depends on:** Task 1  
**Files:**
- Modify: `src/Application/Services/CrudScopeResolver.php`
- Modify: `docs/modules/crud/modulo-crud-engine.md` (párrafo custom scope)
- Test: `tests/Security/CrudActionOwnershipTest.php`
**Interfaces:**
- Consumes: `resolve()`, `CrudListContext`, condiciones `list<array{column,op,value}>`
- Produces: `assertOwnedBy` fail-closed para cualquier scope resuelto; `recordMatchesConditions(array $record, array $conditions): bool`

- [ ] **Step 1: Escribir el test que falla** — ya escrito en Task 1 (rojo).

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Security/CrudActionOwnership` / Expected: FAIL en tests C1.

- [ ] **Step 3: Implementar el cambio mínimo** — reemplazar el cuerpo de `assertOwnedBy` y añadir helper estático en `CrudScopeResolver.php`:

```php
public function assertOwnedBy(CrudResourceDefinition $definition, array $record, ?int $userId, callable $can): void
{
    $scope = $this->resolve($definition, $userId, $can);
    if ($scope === null) {
        return;
    }

    $ctx = new CrudListContext(
        $definition->key(),
        $definition->table(),
        $definition->primaryKey(),
        $userId,
        '',
        []
    );
    $scope->apply($ctx);

    if (!self::recordMatchesConditions($record, $ctx->conditions())) {
        throw new ValidationException('El registro solicitado no existe.');
    }
}

/**
 * Evalúa condiciones de scope contra un registro ya cargado (acceso por ID).
 * Mapa vacío => true (p. ej. owner con bypass). Columna ausente => false (fail-closed).
 *
 * @param array<string, mixed> $record
 * @param list<array{column: string, op: string, value: mixed}> $conditions
 */
public static function recordMatchesConditions(array $record, array $conditions): bool
{
    foreach ($conditions as $cond) {
        $column = (string) ($cond['column'] ?? '');
        if ($column === '' || !array_key_exists($column, $record)) {
            return false;
        }
        $actual = $record[$column];
        $op = (string) ($cond['op'] ?? '=');
        $expected = $cond['value'] ?? null;

        $ok = match ($op) {
            '='  => (string) $actual === (string) $expected,
            '!=' => (string) $actual !== (string) $expected,
            '<'  => (float) $actual < (float) $expected,
            '>'  => (float) $actual > (float) $expected,
            '<=' => (float) $actual <= (float) $expected,
            '>=' => (float) $actual >= (float) $expected,
            'IN' => is_array($expected) && in_array((string) $actual, array_map('strval', $expected), true),
            'LIKE' => is_string($expected) && self::likeMatch((string) $actual, $expected),
            default => false,
        };
        if (!$ok) {
            return false;
        }
    }
    return true;
}

private static function likeMatch(string $actual, string $pattern): bool
{
    $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')) . '$/u';
    return preg_match($regex, $actual) === 1;
}
```

Añadir al inicio del archivo el `use` de `CrudListContext` si no está:

```php
use Lebytek\Framework\Application\Crud\Context\CrudListContext;
```

Actualizar el docblock de `assertOwnedBy`: eliminar «Si el recurso no declara owner scope, no hace nada»; sustituir por «Si `resolve()` no produce scope, no hace nada. Si produce scope (owner o `scope_handler`), exige que el registro cumpla las condiciones; si no, trata como inexistente».

En `docs/modules/crud/modulo-crud-engine.md`, tras el bloque que documenta `scope_handler`, añadir:

```markdown
El mismo scope (built-in u handler) se revalida en acceso por ID: show, edit, update,
delete y acciones de fila/masivas. Un ID conocido fuera de scope responde como
inexistente (mismo mensaje que owner).
```

- [ ] **Step 4: Verificación enfocada**

Run: `php tests/run.php Security/CrudActionOwnership`  
Expected: **PASS** — todos los tests owner previos + 3 C1 nuevos.

- [ ] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/Scope`  
Expected: PASS — 0 failed.

- [ ] **Step 6: Commit**

```bash
git add src/Application/Services/CrudScopeResolver.php docs/modules/crud/modulo-crud-engine.md
git commit -m "fix(crud): reapply list scope on ID access including scope_handler (C1)"
```

---

### Task 3: C2 — Validator exige `permission` en handler/transition (TDD)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `cursor/crud-p01-authz-multi-canal-0a6f`  
**Depends on:** None (paralelo seguro tras Task 2; no comparte archivos con C1 salvo suites)  
**Files:**
- Modify: `tests/Crud/Action/CrudConfigValidatorActionsTest.php`
- Modify: `src/Application/Services/CrudConfigValidator.php` (`actionsBlockErrors`)
- Test: `tests/Crud/Action/CrudConfigValidatorActionsTest.php`
**Interfaces:**
- Consumes: `CrudConfigValidator::actionsBlockErrors(array $config): array`
- Produces: error string `actions.{group}[{i}] (handler|transition) requiere 'permission'.` cuando falta

- [ ] **Step 1: Escribir el test que falla** — actualizar `tests/Crud/Action/CrudConfigValidatorActionsTest.php`:

1. En el test `well-formed actions pass`, añadir `"permission": "editar"` a cada action `handler` (y a cualquier `transition` si se añade). Los `builtin`/`link` sin permission siguen OK.
2. Añadir tests nuevos:

```php
test('actionsBlockErrors: handler sin permission es error (C2)', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['name' => 'toggle', 'type' => 'handler', 'handler' => 'p_toggle'],
        ],
    ]]);
    assert_true(
        in_array("actions.row[0] (handler) requiere 'permission'.", $errors, true),
        'handler sin permission debe rechazarse'
    );
});

test('actionsBlockErrors: transition sin permission es error (C2)', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['name' => 'pagar', 'type' => 'transition', 'to' => 'pagado'],
        ],
    ]]);
    assert_true(
        in_array("actions.row[0] (transition) requiere 'permission'.", $errors, true),
        'transition sin permission debe rechazarse'
    );
});

test('actionsBlockErrors: link y builtin sin permission siguen válidos', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['name' => 'show', 'type' => 'builtin'],
            ['name' => 'pdf', 'type' => 'link', 'route' => '/admin/x/{id}/pdf'],
        ],
    ]]);
    assert_same([], $errors);
});

test('actionsBlockErrors: handler con permission vacía es error (C2)', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'bulk' => [
            ['name' => 'activar', 'type' => 'handler', 'handler' => 'p_bulk', 'permission' => ''],
        ],
    ]]);
    assert_true(
        in_array("actions.bulk[0] (handler) requiere 'permission'.", $errors, true),
        'permission vacía debe rechazarse'
    );
});
```

También actualizar el test `reports structural problems`: el handler sin key `handler` sigue contando; si algún caso `handler` completo sin permission se añade al arreglo, incrementar el count esperado. El caso actual `['name' => 'h2', 'type' => 'handler']` generará **dos** errores (falta handler + falta permission) — ajustar `assert_same(6, count($errors))` → `assert_same(7, count($errors))` **o** añadir permission al caso h2 y dejar count en 6 si solo se quiere el error de handler key. Preferido: dejar h2 sin permission y sin handler key → count **7**.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Crud/Action/CrudConfigValidatorActions`  
Expected: **FAIL** — tests C2 no encuentran el mensaje de permission (validator aún no lo exige).

- [ ] **Step 3: Implementar el cambio mínimo** — dentro del loop de `actionsBlockErrors` en `CrudConfigValidator.php`, tras las validaciones de `handler`/`link`/`transition`/`builtin`, añadir:

```php
if (in_array($type, ['handler', 'transition'], true)) {
    $perm = $action['permission'] ?? null;
    if (!is_string($perm) || trim($perm) === '') {
        $errors[] = "actions.{$group}[{$i}] ({$type}) requiere 'permission'.";
    }
}
```

- [ ] **Step 4: Verificación enfocada**

Run: `php tests/run.php Crud/Action/CrudConfigValidatorActions`  
Expected: **PASS**.

- [ ] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/Action`  
Expected: PASS — 0 failed. Si algún fixture de action en otros tests del directorio declara handler sin permission, añadir `"permission": "editar"` mínimo.

Verificar demos (no deben romper cold-load):

```bash
# harness + skeleton ya tienen permission en handlers/transitions; confirmar con rg:
rg -n '"type": "handler"|"type": "transition"' config/cruds skeleton/config/cruds -A2
```

Expected: cada bloque handler/transition incluye línea `permission`.

- [ ] **Step 6: Commit**

```bash
git add src/Application/Services/CrudConfigValidator.php tests/Crud/Action/CrudConfigValidatorActionsTest.php
git commit -m "fix(crud): require permission on executable actions in validator (C2)"
```

---

### Task 4: C2 — Runtime fail-closed en `CrudActionService`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `cursor/crud-p01-authz-multi-canal-0a6f`  
**Depends on:** Task 3  
**Files:**
- Create: `tests/Crud/Action/CrudActionPermissionTest.php`
- Modify: `src/Application/Services/CrudActionService.php`
- Test: `tests/Crud/Action/CrudActionPermissionTest.php`
**Interfaces:**
- Consumes: `CrudActionDefinition::resolvePermission`, `RbacService::verificar` (indirecto)
- Produces: `CrudActionService::requireExecutablePermission(CrudActionDefinition $action, string $prefix): string` — `private` o lógica inline; testea vía método de paquete testeable:

Añadir método **público** puro (sin DB) para TDD sin cablear todo el servicio:

```php
/**
 * Resuelve el slug RBAC de una acción ejecutable. Falla cerrado si falta permission
 * en handler/transition (C2). Builtin/link no usan este camino en run()/runBulk().
 */
public static function resolveExecutablePermission(CrudActionDefinition $action, string $prefix): string
```

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Crud/Action/CrudActionPermissionTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudActionService;
use Lebytek\Framework\Domain\Entities\Crud\CrudActionDefinition;
use Lebytek\Framework\Domain\Exceptions\AccesoException;

test('resolveExecutablePermission expande permission relativa al prefix', function (): void {
    $action = CrudActionDefinition::fromArray([
        'name' => 'toggle',
        'type' => 'handler',
        'handler' => 'p_toggle',
        'permission' => 'editar',
    ]);
    assert_same(
        'demo_productos.editar',
        CrudActionService::resolveExecutablePermission($action, 'demo_productos')
    );
});

test('resolveExecutablePermission acepta slug absoluto con punto', function (): void {
    $action = CrudActionDefinition::fromArray([
        'name' => 'wa',
        'type' => 'handler',
        'handler' => 'enviar_whatsapp_demo',
        'permission' => 'integrations.enviar',
    ]);
    assert_same(
        'integrations.enviar',
        CrudActionService::resolveExecutablePermission($action, 'demo_clientes')
    );
});

test('resolveExecutablePermission falla cerrado sin permission (C2)', function (): void {
    $action = CrudActionDefinition::fromArray([
        'name' => 'toggle',
        'type' => 'handler',
        'handler' => 'p_toggle',
    ]);
    assert_throws(AccesoException::class, function () use ($action): void {
        CrudActionService::resolveExecutablePermission($action, 'demo_productos');
    });
});

test('resolveExecutablePermission falla cerrado con permission vacía', function (): void {
    // fromArray trata '' como null
    $action = CrudActionDefinition::fromArray([
        'name' => 'pagar',
        'type' => 'transition',
        'to' => 'pagado',
        'permission' => '',
    ]);
    assert_throws(AccesoException::class, function () use ($action): void {
        CrudActionService::resolveExecutablePermission($action, 'demo_pedidos');
    });
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Crud/Action/CrudActionPermission`  
Expected: **FAIL** — `Call to undefined method` / method missing `resolveExecutablePermission`.

- [ ] **Step 3: Implementar el cambio mínimo**

En `CrudActionService.php`:

1. Añadir `use Lebytek\Framework\Domain\Exceptions\AccesoException;`
2. Añadir método estático:

```php
public static function resolveExecutablePermission(CrudActionDefinition $action, string $prefix): string
{
    $permission = $action->resolvePermission($prefix);
    if ($permission === null || $permission === '') {
        throw new AccesoException(
            "No tienes permiso para realizar esta acción: {$prefix}.(sin-permission)"
        );
    }
    return $permission;
}
```

Usar mensaje estable. Preferido exacto para el caso null:

```php
throw new AccesoException(
    'No tienes permiso para realizar esta acción: ' . $prefix . '.' . $action->name()
);
```

(El slug reportado incluye el nombre de acción cuando falta config — suficiente para soporte; no es un permiso real en BD.)

3. En `run()` y `runBulk()`, reemplazar el bloque:

```php
$permission = $action->resolvePermission($definition->permissionPrefix());
if ($permission !== null) {
    $this->rbacService->verificar($permission);
}
```

por:

```php
$permission = self::resolveExecutablePermission($action, $definition->permissionPrefix());
$this->rbacService->verificar($permission);
```

Nota: `resolveExecutable`/`resolveBulkExecutable` ya limitan a acciones declaradas; `link` no se ejecuta por `dispatch`. Si en el futuro un `builtin` pasara por `run()`, también exigiría permission — hoy los builtins no pasan por `run()` (rutas propias). `transition` sí pasa por `run()` vía action service → queda cubierto.

- [ ] **Step 4: Verificación enfocada**

Run: `php tests/run.php Crud/Action/CrudActionPermission`  
Expected: **PASS**.

- [ ] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/Action Security/CrudActionOwnership`  
Expected: PASS — 0 failed.

- [ ] **Step 6: Commit**

```bash
git add src/Application/Services/CrudActionService.php tests/Crud/Action/CrudActionPermissionTest.php
git commit -m "fix(crud): fail-closed RBAC when action permission missing (C2)"
```

---

### Task 5: C5 — `{resource}.ver` en `CrudReporteDataSource`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `cursor/crud-p01-authz-multi-canal-0a6f`  
**Depends on:** None (archivo distinto; ejecutar tras Task 2 recomendado para baseline limpia)  
**Files:**
- Create: `tests/Reporte/CrudReporteDataSourceAuthzTest.php`
- Modify: `src/Application/Reporte/CrudReporteDataSource.php`
- Test: `tests/Reporte/CrudReporteDataSourceAuthzTest.php`
**Interfaces:**
- Consumes: `CrudResourceDefinition::permissionFor('ver')`, callable `$can`, `AccesoException`
- Produces: gate en `rows()` y `findRecord()` antes de tocar `CrudDataService`

Porque `CrudDataService` es `final` y costoso de construir, el gate se implementa como método de instancia privado invocado al inicio, y los tests cubren un **método estático de paquete** extraído en la misma clase:

```php
public static function assertCanViewResource(CrudResourceDefinition $definition, ?callable $can): void
```

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Reporte/CrudReporteDataSourceAuthzTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Reporte\CrudReporteDataSource;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\AccesoException;

function reporte_authz_def(string $prefix = 'demo_productos'): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo_productos',
            'title' => 'Productos',
            'table' => 'dom_productos',
            'primary_key' => 'id',
            'permission_prefix' => $prefix,
        ],
        'list' => ['columns' => [['name' => 'id', 'label' => 'ID']]],
    ]);
}

test('assertCanViewResource permite cuando can otorga {prefix}.ver (C5)', function (): void {
    $def = reporte_authz_def();
    $can = static fn(string $slug): bool => $slug === 'demo_productos.ver';
    CrudReporteDataSource::assertCanViewResource($def, $can);
    assert_true(true, 'con .ver: no lanza');
});

test('assertCanViewResource deniega cuando can no otorga .ver (C5)', function (): void {
    $def = reporte_authz_def();
    $can = static fn(string $slug): bool => $slug === 'reportes.generar'; // típico vector
    assert_throws(AccesoException::class, function () use ($def, $can): void {
        CrudReporteDataSource::assertCanViewResource($def, $can);
    });
});

test('assertCanViewResource deniega cuando can es null (C5)', function (): void {
    $def = reporte_authz_def();
    assert_throws(AccesoException::class, function () use ($def): void {
        CrudReporteDataSource::assertCanViewResource($def, null);
    });
});

test('assertCanViewResource mensaje incluye el slug .ver', function (): void {
    $def = reporte_authz_def('demo_clientes');
    $msg = null;
    try {
        CrudReporteDataSource::assertCanViewResource($def, static fn(string $s): bool => false);
    } catch (AccesoException $e) {
        $msg = $e->getMessage();
    }
    assert_same('No tienes permiso para realizar esta acción: demo_clientes.ver', $msg);
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Reporte/CrudReporteDataSourceAuthz`  
Expected: **FAIL** — método `assertCanViewResource` inexistente.

- [ ] **Step 3: Implementar el cambio mínimo** — en `CrudReporteDataSource.php`:

```php
use Lebytek\Framework\Domain\Exceptions\AccesoException;

public static function assertCanViewResource(CrudResourceDefinition $definition, ?callable $can): void
{
    $slug = $definition->permissionFor('ver');
    if ($can === null || !$can($slug)) {
        throw new AccesoException("No tienes permiso para realizar esta acción: {$slug}");
    }
}

public function rows(...): array
{
    self::assertCanViewResource($definition, $can);
    return $this->crudDataService->eventsInRange(...);
}

public function findRecord(...): ?array
{
    self::assertCanViewResource($definition, $can);
    // ... resto igual
}
```

No cambiar constructor ni `FrameworkServiceProvider` (se reutiliza `$can` ya inyectado por use cases / controller).

- [ ] **Step 4: Verificación enfocada**

Run: `php tests/run.php Reporte/CrudReporteDataSourceAuthz`  
Expected: **PASS**.

- [ ] **Step 5: Regresión relevante**

Run: `php tests/run.php Reporte`  
Expected: PASS — 0 failed. Los fakes de `BuildReporteDataUseCaseTest` / `GenerarDocumentoUseCaseTest` no pasan por `CrudReporteDataSource` real; no requieren cambio. Si algún test construye el datasource real sin `.ver` en `$can`, actualizar el callable a permitir `{resource}.ver`.

- [ ] **Step 6: Commit**

```bash
git add src/Application/Reporte/CrudReporteDataSource.php tests/Reporte/CrudReporteDataSourceAuthzTest.php
git commit -m "fix(reportes): require resource.ver inside CrudReporteDataSource (C5)"
```

---

### Task 6: Regresión cruzada + semver `1.2.6`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `cursor/crud-p01-authz-multi-canal-0a6f`  
**Depends on:** Task 2, Task 4, Task 5  
**Files:**
- Modify: `composer.json` (`version`)
- Modify: `config/app.php` (clave de versión de plataforma — misma que usan los planes M3/M4)
- Modify: `skeleton/config/app.php`
- Test: `tests/` suites listadas + `PlatformVersionSemver`
**Interfaces:**
- Consumes: trío semver existente @ `1.2.3` en `main` al planificar
- Produces: trío sincronizado @ `1.2.6`

- [ ] **Step 1: Escribir el test que falla** — N/A (gates de Tasks 1–5 ya verdes). Confirmar baseline semver:

Run: `php tests/run.php PlatformVersionSemver`  
Expected: PASS @ `1.2.3` (o la versión actual del tip si otro plan mergeó antes; **no** inventar — leer los tres archivos y alinear al mismo PATCH `1.2.6` solo si aún es `1.2.3`/`1.2.4`/`1.2.5`; si ya hay `≥1.2.6`, bump al siguiente PATCH libre y anótelo en el commit).

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — N/A para semver hasta editar. Primero correr regresión AuthZ:

Run: `php tests/run.php Security Crud/Scope Crud/Action Reporte/CrudReporteDataSourceAuthz`  
Expected: PASS — 0 failed.

- [ ] **Step 3: Implementar el cambio mínimo** — poner **la misma** versión `1.2.6` en:

- `composer.json` → `"version": "1.2.6"`
- `config/app.php` → `'version' => '1.2.6'` (línea del array raíz; hoy `1.2.3`)
- `skeleton/config/app.php` → `'version' => '1.2.6'` (espejo)

- [ ] **Step 4: Verificación enfocada**

Run: `php tests/run.php PlatformVersionSemver`  
Expected: PASS @ `1.2.6`.

- [ ] **Step 5: Regresión relevante**

Run: `php tests/run.php Security Crud/Scope Crud/Action Reporte Kernel/SkeletonPurity`  
Expected: PASS — 0 failed.

Smoke de configs demo (cold validate si existe comando; si no, al menos):

```bash
rg -n '"type": "handler"' config/cruds skeleton/config/cruds -A3 | rg -v permission || true
```

Expected: sin handlers huérfanos sin `permission` en las líneas de contexto.

- [ ] **Step 6: Commit**

```bash
git add composer.json config/app.php skeleton/config/app.php
git commit -m "chore(release): bump platform version to 1.2.6 for CRUD AuthZ p01"
```

Tag `v1.2.6` **solo post-merge** en `main` (operador / release); no taggear desde la rama feature.

---

## Criterios de aceptación (punto 1)

- [ ] **C1:** Con `list.scope_handler`, show/edit/update/delete/acciones por ID deniegan registros fuera de las condiciones del scope (mensaje `El registro solicitado no existe.`).
- [ ] **C1:** Owner built-in + bypass siguen comportándose igual (`CrudActionOwnershipTest` verde).
- [ ] **C2:** `CrudConfigValidator::actionsBlockErrors` rechaza `handler`/`transition` sin `permission` no vacío.
- [ ] **C2:** `CrudActionService::run`/`runBulk` no ejecutan si falta permission (fail-closed `AccesoException`).
- [ ] **C5:** `CrudReporteDataSource::{rows,findRecord}` lanzan `AccesoException` sin `{resource}.ver` aunque exista `reportes.generar`.
- [ ] Tests nuevos del plan en verde: `Security/CrudActionOwnership`, `Crud/Action/CrudConfigValidatorActions`, `Crud/Action/CrudActionPermission`, `Reporte/CrudReporteDataSourceAuthz`.
- [ ] Semver trío @ `1.2.6` + `PlatformVersionSemver` PASS.
- [ ] `SkeletonPurity` PASS; demos JSON siguen cargables.
- [ ] Diff **sin** Marketing/Portal business; **sin** cambios de punto 2–12 (states, uploads, CAS, router RBAC, etc.).
- [ ] UX U1–U9 del spec audit (mensaje IDOR exacto, 403 vs 404 vs flash, slug en `AccesoException`, validator accionable, doc § AuthZ multi-canal) — ver spec § Compatibilidad, UX y responsive.

## Fuera de alcance

- IDs de otros puntos: C3/G15/G6, C6, C4/G13/G1/G14, G12, G4/G10/G16, G7–G9, M*, B*, G2/G3.
- Plan `2026-08-06-audit-crud-rbac-router.md` (punto 6) — no absorber aquí; C5 no se cierra con middleware de ruta CRUD.
- Portal bump `composer.lock`, WhatsApi, legacy `feature/backoffice-api-integration`.
- Refactor onion G2/G3 / puertos Domain (punto 11).
- DI de handlers (G5 / punto 8).
- Cambiar firmas públicas de `ReporteDataSourceInterface` / `ReporteRecordSourceInterface`.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Custom scope con operadores no soportados en match | `default => false` fail-closed; ALLOWED_OPS ya limitan en `addCondition` |
| Columna de scope ausente en SELECT del show | Fail-closed (niega); `find()` actual hace SELECT completo de fila — OK para show/edit; documentar que scopes deben usar columnas persistidas |
| Configs consumidoras sin `permission` en handlers | Validator rompe cold-load a propósito; demos del paquete ya cumplen; Portal debe añadir permission antes del bump |
| `$can === null` en reportes legítimos | Gate deniega (seguro); callers actuales pasan callable desde sesión |
| Semver choca con M3/M4 si mergean antes | Task 6 relee tip y elige PATCH libre ≥1.2.6 |
| PHP CLI ausente en agente | Ejecutor requiere entorno con PHP ≥8.1 (`composer.json`) |

**Rollback:** revertir PR del punto 1; no hay puntos 2–12 que dependan aún. Owner scope built-in vuelve al path `ownerMeta` anterior solo si se revierte el commit C1 completo.

## Evidencia que debe recopilar el ejecutor

- Salida de:
  - `php tests/run.php Security/CrudActionOwnership`
  - `php tests/run.php Crud/Action/CrudConfigValidatorActions Crud/Action/CrudActionPermission`
  - `php tests/run.php Reporte/CrudReporteDataSourceAuthz`
  - `php tests/run.php Security Crud/Scope Crud/Action Reporte PlatformVersionSemver Kernel/SkeletonPurity`
- Contraste breve por ID:
  - **C1:** antes `assertOwnedBy` no-op con `scope_handler`; después deniega `created_by≠scope`.
  - **C2:** antes `permission === null` saltaba `verificar`; después validator + `AccesoException`.
  - **C5:** antes `reportes.generar` bastaba para leer filas; después exige `demo_*.ver`.
- PR URL Framework + SHA final de la rama de implementación.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-07T12:40:00Z (AUTOMATION-04) |
| Plan creado UTC | 2026-08-07T05:08:29Z |
| Framework `origin/main` verificado | `da3ab58bd77be95c4003341454d939aa2584a742` |
| Tareas completadas / totales | **0 / 6** |
| Modo fuente | normal — spec `2026-08-07-audit-crud-authz-multi-canal-design.md` (PR #97) |
| Evidencia @ `da3ab58` | `CrudScopeResolver::assertOwnedBy` owner-only (C1 abierto); `CrudActionService` skip si `permission` null (C2); `CrudReporteDataSource` sin gate `.ver` (C5); ausentes `tests/Reporte/CrudReporteDataSourceAuthzTest.php`, `tests/Crud/Action/CrudActionPermissionTest.php`; semver trío `1.2.3` |
| Implementación draft (no mergeada) | PR #95 — `cursor/crud-p01-authz-multi-canal-0a6f` (OPEN; no marca checkboxes hasta merge en `main`) |
| Siguiente tarea ejecutable | **Task 1** — tests C1 `scope_handler` rojo en `tests/Security/CrudActionOwnershipTest.php` |
| Prerrequisitos | Ninguno (punto 1); PHP ≥8.1 en entorno de ejecución |
| Bloqueos | PR #95 no mergeado; M6 gh Portal 404 (P1–P4 fuera de alcance); PHP CLI puede faltar en agentes cloud |
| Estado | **Pendiente de implementación en main** |
