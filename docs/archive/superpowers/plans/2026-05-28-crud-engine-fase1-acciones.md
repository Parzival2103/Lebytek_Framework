# CRUD Engine — Fase 1 (Acciones custom + Bulk) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add declarative custom row actions and bulk actions to the CRUD Engine — driven by an optional `actions` metadata block — with a generic execution endpoint that dispatches to whitelisted `CrudActionHandlerInterface` handlers, server-side `visible_when`/RBAC re-checks, audit logging, and a vanilla-JS bulk selection bar. Zero hardcoded module logic in the core; 100% backward compatible (configs without `actions` behave exactly as today).

**Architecture:** A pure value object (`CrudActionDefinition`) parses each action and evaluates `visible_when`/`enabled_when` (equality only — no expression language). `CrudResourceDefinition` parses the optional `actions` block into row/bulk action lists, falling back to today's `list.actions` strings when absent. A pure `CrudActionResolver` turns definitions + a row + a permission-checker into view-model action lists and resolves an action for execution. `CrudActionService` is thin glue: load definition → resolve → RBAC → load record → **re-check `visible_when` server-side** → dispatch by `type` (`handler` executes via the Fase 0 registry/`CrudActionContext`; `link` is navigation-only and rejected server-side; `builtin`/`transition` are out of scope for Fase 1) → audit. Two new POST routes (`/accion/{action}`, `/accion-masiva/{action}`) reuse the existing controller/CSRF/flash patterns. The index view renders per-row resolved actions and a bulk bar; correctness-critical logic lives in unit-tested pure code, wiring is verified by a manual smoke (same split proven in the Fase 0 plan).

**Tech Stack:** PHP 8.1+ (runtime 8.5), custom PSR-4 autoloader, MySQL via PDO, MVC + Onion, Bootstrap 5, vanilla JS. **Tests run via the Fase 0 plain-PHP harness:** `php tests/run.php` (no PHPUnit). JSON metadata in `config/cruds/{resource}.json`.

---

## Prerequisites (already in place from Fase 0)

These exist and are committed (verified): `app/Application/Crud/Context/CrudActionContext.php` (ctor: `resourceKey, table, primaryKey, userId, ip, recordId:int, record:?array, action:string, input:array`), `app/Domain/Interfaces/CrudActionHandlerInterface.php` (`handle(CrudActionContext): void`), `app/Application/Services/CrudHandlerRegistry.php` (`resolve(?string $key, string $expectedInterface = ...): ?object`), the test harness (`tests/run.php`, `tests/lib/*`), and `CrudConfigValidator::newBlockShapeErrors()`.

**Verified collaborator APIs (do not re-invent):**
- `CrudConfigLoader::load(string $resource): CrudResourceDefinition`
- `CrudDataService::find(CrudResourceDefinition $def, int $id): ?array`
- `RbacService::verificar(string $slug): void` (throws `App\Domain\Exceptions\AccesoException`), `RbacService::puede(string $slug): bool`
- `BitacoraRepositoryInterface::registrar(?int $userId, string $accion, string $tabla, int $registroId, string $detalle, string $ip): void`
- `ValidationException(string $message, array $errors = [])`, `getErrors()`, `getMessage()`
- `CrudResourceDefinition`: `key(): string`, `title(): string`, `table(): string`, `primaryKey(): string`, `permissionPrefix(): string`, `permissionFor(string $action): string`, `listActions(): array`, `listColumns(): array`, `fromArray(array): self`
- Controller helpers (in `AdminBaseController`/`CrudController`): `verifyCsrf($request)`, `currentUser()`, `redirectWithFlash($url,$type,$msg)`, `redirect($url)`, `view($tpl,$data)`, `Response::forbidden()`, `Response::notFound()`; `Request`: `param($k)`, `all()`, `ip()`; `Session::flash`, `Session::getFlash`.

---

## File Structure

**New files:**

| File | Responsibility |
|---|---|
| `app/Domain/Entities/Crud/CrudActionDefinition.php` | Immutable VO for one action: type, label, icon, method, route, confirm, handler, permission; `isVisibleFor`/`isEnabledFor` (equality eval), `resolvePermission`, type predicates |
| `app/Application/Services/CrudActionResolver.php` | Pure: build per-row visible+permitted action view-models, permitted bulk actions, and resolve an action name for execution |
| `app/Application/Services/CrudActionService.php` | Glue: orchestrate row + bulk action execution (RBAC, record load, server-side visible re-check, dispatch, audit) |
| `app/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php` | Example `CrudActionHandlerInterface` for the e2e checkpoint |
| `app/Presentation/Views/admin/crud/partials/actions_row.php` | Renders `$row['_actions']` (builtin + custom + link) |
| `app/Presentation/Views/admin/crud/partials/actions_bulk.php` | Bulk action bar + hidden form |
| `public/assets/js/crud-engine.js` | Bulk selection (select-all, row checkboxes), generic POST-action confirm + submit |
| `tests/Crud/Action/CrudActionDefinitionTest.php` | Unit tests (Task 1) |
| `tests/Crud/Action/CrudResourceDefinitionActionsTest.php` | Unit tests (Task 2) |
| `tests/Crud/Action/CrudConfigValidatorActionsTest.php` | Unit tests (Task 3) |
| `tests/Crud/Action/CrudActionResolverTest.php` | Unit tests (Task 4) |
| `tests/fixtures/action_handlers.php` | Test-only action handlers (Tasks 4–5) |

**Modified files:**

| File | Change |
|---|---|
| `app/Domain/Entities/CrudResourceDefinition.php` | Parse optional `actions` block → `rowActions()`, `bulkActions()`, `hasActionsBlock()`; back-compat builtin fallback |
| `app/Application/Services/CrudConfigValidator.php` | Deep-validate the `actions` block shape |
| `app/Application/Services/CrudResourceService.php` | New deps `CrudActionResolver` + `CrudActionService`; augment `buildIndexData` with `_actions`/`bulkActions`/`selectable`; add `runAction()` + `runBulkAction()` |
| `app/Presentation/Controllers/Admin/CrudController.php` | New `action()` + `bulkAction()` methods |
| `routes/web.php` | 2 new POST routes (after line 81) |
| `config/container.php` | Add `CrudActionResolver` + `CrudActionService` singletons; add 2 args to `CrudResourceService` binding |
| `app/Presentation/Views/admin/crud/index.php` | Use `actions_row` partial + bulk bar + checkbox column + script include |
| `config/cruds/demo_productos.json` | Add an `actions` block for the e2e checkpoint |
| `config/crud_handlers.php` | Register the demo action handler |
| `docs/modules/crud/modulo-crud-engine.md` | Append a Fase 1 section |

**Unchanged (reused):** `CrudConfigLoader`, `CrudDataService`, `CrudTableBuilder`, `GenericCrudRepository`, `CrudHookRunner`, `RbacService`, `CsrfMiddleware`, `BitacoraRepository`, all Fase 0 contexts/interfaces.

---

## Conventions for every task

- Run the suite with `php tests/run.php` (filter: `php tests/run.php <substring>`). Expected tail: `N passed, 0 failed`, exit 0.
- All PHP files start with `<?php` then `declare(strict_types=1);`.
- Commit after each task with the exact message shown (append the Co-Authored-By trailer used in this repo).
- The harness in this environment occasionally corrupts/duplicates tool output — verify test results with a file capture if a run looks wrong: `php tests/run.php >/tmp/s.txt 2>&1; grep -c FAIL /tmp/s.txt; tail -1 /tmp/s.txt`.

---

### Task 1: `CrudActionDefinition` value object

**Files:**
- Create: `app/Domain/Entities/Crud/CrudActionDefinition.php`
- Test: `tests/Crud/Action/CrudActionDefinitionTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Action/CrudActionDefinitionTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\Crud\CrudActionDefinition;

test('CrudActionDefinition: parses a handler action', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'regenerar', 'type' => 'handler', 'handler' => 'evt_regen',
        'label' => 'Regenerar', 'icon' => 'bi-arrow-repeat', 'method' => 'POST',
        'permission' => 'eventos.doc.regenerar', 'confirm' => '¿Regenerar?',
    ]);
    assert_same('regenerar', $a->name());
    assert_same('handler', $a->type());
    assert_same('evt_regen', $a->handler());
    assert_same('Regenerar', $a->label());
    assert_same('bi-arrow-repeat', $a->icon());
    assert_same('POST', $a->method());
    assert_same('¿Regenerar?', $a->confirm());
    assert_true($a->isHandler());
    assert_true(!$a->isLink());
    assert_true(!$a->isBuiltin());
});

test('CrudActionDefinition: link defaults method to GET, keeps route', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'contrato', 'type' => 'link', 'route' => '/admin/eventos/{id}/contrato',
    ]);
    assert_true($a->isLink());
    assert_same('GET', $a->method());
    assert_same('/admin/eventos/{id}/contrato', $a->route());
});

test('CrudActionDefinition: handler defaults method to POST and label to name', function (): void {
    $a = CrudActionDefinition::fromArray(['name' => 'sync', 'type' => 'handler', 'handler' => 'h']);
    assert_same('POST', $a->method());
    assert_same('sync', $a->label());
});

test('CrudActionDefinition: resolvePermission expands a suffix against the prefix', function (): void {
    $suffix = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'h', 'permission' => 'editar']);
    assert_same('eventos.editar', $suffix->resolvePermission('eventos'));

    $full = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'h', 'permission' => 'otro.modulo.ver']);
    assert_same('otro.modulo.ver', $full->resolvePermission('eventos'));

    $none = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'link', 'route' => '/r']);
    assert_null($none->resolvePermission('eventos'));
});

test('CrudActionDefinition: isVisibleFor with scalar equality', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'autorizar', 'type' => 'handler', 'handler' => 'h',
        'visible_when' => ['status' => 'pendiente'],
    ]);
    assert_true($a->isVisibleFor(['status' => 'pendiente']));
    assert_true(!$a->isVisibleFor(['status' => 'autorizado']));
    assert_true(!$a->isVisibleFor([]));
});

test('CrudActionDefinition: isVisibleFor with list membership and no condition', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'x', 'type' => 'handler', 'handler' => 'h',
        'visible_when' => ['status' => ['pendiente', 'revision']],
    ]);
    assert_true($a->isVisibleFor(['status' => 'revision']));
    assert_true(!$a->isVisibleFor(['status' => 'cerrado']));

    $always = CrudActionDefinition::fromArray(['name' => 'y', 'type' => 'handler', 'handler' => 'h']);
    assert_true($always->isVisibleFor([]));
});

test('CrudActionDefinition: isEnabledFor defaults true and honors enabled_when', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'x', 'type' => 'handler', 'handler' => 'h',
        'enabled_when' => ['bloqueado' => 0],
    ]);
    assert_true($a->isEnabledFor(['bloqueado' => 0]));
    assert_true(!$a->isEnabledFor(['bloqueado' => 1]));

    $b = CrudActionDefinition::fromArray(['name' => 'y', 'type' => 'handler', 'handler' => 'h']);
    assert_true($b->isEnabledFor([]));
});

test('CrudActionDefinition: builtin predicate', function (): void {
    $a = CrudActionDefinition::fromArray(['name' => 'edit', 'type' => 'builtin']);
    assert_true($a->isBuiltin());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudActionDefinitionTest`
Expected: FAIL — `Class "App\Domain\Entities\Crud\CrudActionDefinition" not found`.

- [ ] **Step 3: Implement the VO**

Create `app/Domain/Entities/Crud/CrudActionDefinition.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities\Crud;

/**
 * Definición inmutable de una acción de fila o masiva del CRUD Engine.
 * `visible_when`/`enabled_when` son mapas de igualdad simple (escalar o lista);
 * no hay lenguaje de expresiones ni eval. Se evalúan al render y se RE-VALIDAN
 * en el servidor antes de ejecutar.
 */
final class CrudActionDefinition
{
    private const TYPES = ['builtin', 'handler', 'link', 'transition'];

    /**
     * @param array<string, mixed> $visibleWhen
     * @param array<string, mixed> $enabledWhen
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly string $label,
        private readonly string $icon,
        private readonly string $method,
        private readonly ?string $route,
        private readonly ?string $confirm,
        private readonly ?string $handler,
        private readonly ?string $permission,
        private readonly ?string $to,
        private readonly array $visibleWhen,
        private readonly array $enabledWhen
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $name = (string) ($config['name'] ?? '');
        $type = (string) ($config['type'] ?? 'builtin');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'builtin';
        }

        $defaultMethod = $type === 'link' ? 'GET' : 'POST';
        $method = strtoupper((string) ($config['method'] ?? $defaultMethod));
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = $defaultMethod;
        }

        return new self(
            name: $name,
            type: $type,
            label: (string) ($config['label'] ?? $name),
            icon: (string) ($config['icon'] ?? ''),
            method: $method,
            route: isset($config['route']) && $config['route'] !== '' ? (string) $config['route'] : null,
            confirm: isset($config['confirm']) && $config['confirm'] !== '' ? (string) $config['confirm'] : null,
            handler: isset($config['handler']) && $config['handler'] !== '' ? (string) $config['handler'] : null,
            permission: isset($config['permission']) && $config['permission'] !== '' ? (string) $config['permission'] : null,
            to: isset($config['to']) && $config['to'] !== '' ? (string) $config['to'] : null,
            visibleWhen: is_array($config['visible_when'] ?? null) ? $config['visible_when'] : [],
            enabledWhen: is_array($config['enabled_when'] ?? null) ? $config['enabled_when'] : []
        );
    }

    public function name(): string { return $this->name; }
    public function type(): string { return $this->type; }
    public function label(): string { return $this->label; }
    public function icon(): string { return $this->icon; }
    public function method(): string { return $this->method; }
    public function route(): ?string { return $this->route; }
    public function confirm(): ?string { return $this->confirm; }
    public function handler(): ?string { return $this->handler; }
    public function to(): ?string { return $this->to; }

    public function isBuiltin(): bool { return $this->type === 'builtin'; }
    public function isHandler(): bool { return $this->type === 'handler'; }
    public function isLink(): bool { return $this->type === 'link'; }
    public function isTransition(): bool { return $this->type === 'transition'; }

    /** Slug completo si contiene punto; si no, se expande contra el prefijo. null si no hay permiso. */
    public function resolvePermission(string $prefix): ?string
    {
        if ($this->permission === null) {
            return null;
        }
        return str_contains($this->permission, '.') ? $this->permission : $prefix . '.' . $this->permission;
    }

    /** @param array<string, mixed> $row */
    public function isVisibleFor(array $row): bool
    {
        return self::equalityMatches($this->visibleWhen, $row);
    }

    /** @param array<string, mixed> $row */
    public function isEnabledFor(array $row): bool
    {
        return self::equalityMatches($this->enabledWhen, $row);
    }

    /**
     * Cada par columna→valor debe coincidir. El valor puede ser escalar (igualdad
     * laxa por string) o lista (pertenencia). Mapa vacío => true.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $row
     */
    public static function equalityMatches(array $conditions, array $row): bool
    {
        foreach ($conditions as $column => $expected) {
            $actual = $row[$column] ?? null;
            if (is_array($expected)) {
                $ok = false;
                foreach ($expected as $candidate) {
                    if ((string) $actual === (string) $candidate) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    return false;
                }
            } elseif ((string) $actual !== (string) $expected) {
                return false;
            }
        }
        return true;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudActionDefinitionTest`
Expected: `8 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/Crud/CrudActionDefinition.php tests/Crud/Action/CrudActionDefinitionTest.php
git commit -m "feat(crud): add CrudActionDefinition value object for declarative actions"
```

---

### Task 2: Parse the `actions` block in `CrudResourceDefinition`

**Files:**
- Modify: `app/Domain/Entities/CrudResourceDefinition.php`
- Test: `tests/Crud/Action/CrudResourceDefinitionActionsTest.php`

> Back-compat: when there is no `actions` block, `rowActions()` is built from the existing `list.actions` strings (`show`/`edit`/`delete`) as `builtin` actions, so the view renders exactly as today.

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Action/CrudResourceDefinitionActionsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Entities\Crud\CrudActionDefinition;

test('CrudResourceDefinition: no actions block falls back to list.actions builtins', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'p', 'table' => 'dom_p', 'primary_key' => 'id'],
        'list' => ['actions' => ['show', 'edit', 'delete']],
    ]);
    assert_true(!$def->hasActionsBlock());
    $names = array_map(static fn(CrudActionDefinition $a): string => $a->name(), $def->rowActions());
    assert_same(['show', 'edit', 'delete'], $names);
    foreach ($def->rowActions() as $a) {
        assert_true($a->isBuiltin(), 'fallback actions must be builtin');
    }
    assert_same([], $def->bulkActions());
});

test('CrudResourceDefinition: parses row and bulk actions', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'p', 'table' => 'dom_p', 'primary_key' => 'id'],
        'actions' => [
            'row' => [
                ['name' => 'edit', 'type' => 'builtin'],
                ['name' => 'toggle', 'type' => 'handler', 'handler' => 'p_toggle', 'permission' => 'editar'],
            ],
            'bulk' => [
                ['name' => 'activar', 'type' => 'handler', 'handler' => 'p_bulk', 'permission' => 'editar'],
            ],
        ],
    ]);
    assert_true($def->hasActionsBlock());
    assert_same(2, count($def->rowActions()));
    assert_same('toggle', $def->rowActions()[1]->name());
    assert_same(1, count($def->bulkActions()));
    assert_same('activar', $def->bulkActions()[0]->name());
});

test('CrudResourceDefinition: empty actions block yields no row actions', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'p', 'table' => 'dom_p', 'primary_key' => 'id'],
        'actions' => [],
    ]);
    assert_true($def->hasActionsBlock());
    assert_same([], $def->rowActions());
    assert_same([], $def->bulkActions());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudResourceDefinitionActionsTest`
Expected: FAIL — `Call to undefined method ...::hasActionsBlock()`.

- [ ] **Step 3: Add the import, constructor params, parsing, and getters**

In `app/Domain/Entities/CrudResourceDefinition.php`, add the import after `namespace App\Domain\Entities;`:

```php
use App\Domain\Entities\Crud\CrudActionDefinition;
```

Add three constructor parameters at the end of the promoted-property list (after `private readonly bool $listTableCompact`). Change:

```php
        private readonly bool $listTableCompact
    ) {}
```

to:

```php
        private readonly bool $listTableCompact,
        private readonly bool $hasActionsBlock,
        private readonly array $rowActions,
        private readonly array $bulkActions
    ) {}
```

In `fromArray`, immediately before the `return new self(` line, insert the parsing:

```php
        $hasActionsBlock = array_key_exists('actions', $config) && is_array($config['actions']);
        $rowActions = [];
        $bulkActions = [];
        if ($hasActionsBlock) {
            foreach (($config['actions']['row'] ?? []) as $raw) {
                if (is_array($raw) && ($raw['name'] ?? '') !== '') {
                    $rowActions[] = CrudActionDefinition::fromArray($raw);
                }
            }
            foreach (($config['actions']['bulk'] ?? []) as $raw) {
                if (is_array($raw) && ($raw['name'] ?? '') !== '') {
                    $bulkActions[] = CrudActionDefinition::fromArray($raw);
                }
            }
        } else {
            $listActions = is_array($list['actions'] ?? null) ? $list['actions'] : ['show', 'edit', 'delete'];
            foreach ($listActions as $builtin) {
                if (is_string($builtin) && $builtin !== '') {
                    $rowActions[] = CrudActionDefinition::fromArray(['name' => $builtin, 'type' => 'builtin']);
                }
            }
        }
```

Add the three new arguments at the end of the `return new self(...)` call (after `listTableCompact: $listTableCompact`). Change:

```php
            listTableCompact: $listTableCompact
        );
```

to:

```php
            listTableCompact: $listTableCompact,
            hasActionsBlock: $hasActionsBlock,
            rowActions: $rowActions,
            bulkActions: $bulkActions
        );
```

Add the getters just before the final closing `}` of the class (after `listTableCompact()`):

```php
    public function hasActionsBlock(): bool
    {
        return $this->hasActionsBlock;
    }

    /** @return list<CrudActionDefinition> */
    public function rowActions(): array
    {
        return $this->rowActions;
    }

    /** @return list<CrudActionDefinition> */
    public function bulkActions(): array
    {
        return $this->bulkActions;
    }
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudResourceDefinitionActionsTest`
Expected: `3 passed, 0 failed`. Then run `php tests/run.php` — the full suite must stay green (the Fase 0 `CrudHookRunnerTest` builds `CrudResourceDefinition::fromArray` and must still work).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/CrudResourceDefinition.php tests/Crud/Action/CrudResourceDefinitionActionsTest.php
git commit -m "feat(crud): parse optional actions block into CrudResourceDefinition"
```

---

### Task 3: Validate the `actions` block in `CrudConfigValidator`

**Files:**
- Modify: `app/Application/Services/CrudConfigValidator.php`
- Test: `tests/Crud/Action/CrudConfigValidatorActionsTest.php`

> Pure static, no DB — mirrors the Fase 0 `newBlockShapeErrors`. Validates: `actions` is an object; `row`/`bulk` (if present) are arrays; each action has a non-empty `name` and a `type` in `{builtin,handler,link,transition}`; `handler` type requires a `handler` key; `link` requires a `route`; `builtin` name in `{show,edit,delete}`; `method` (if present) in `{GET,POST}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Action/CrudConfigValidatorActionsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('actionsBlockErrors: no actions block is fine', function (): void {
    assert_same([], CrudConfigValidator::actionsBlockErrors([]));
});

test('actionsBlockErrors: well-formed actions pass', function (): void {
    $config = ['actions' => [
        'row' => [
            ['name' => 'edit', 'type' => 'builtin'],
            ['name' => 'toggle', 'type' => 'handler', 'handler' => 'p_toggle'],
            ['name' => 'pdf', 'type' => 'link', 'route' => '/admin/x/{id}/pdf'],
        ],
        'bulk' => [
            ['name' => 'activar', 'type' => 'handler', 'handler' => 'p_bulk'],
        ],
    ]];
    assert_same([], CrudConfigValidator::actionsBlockErrors($config));
});

test('actionsBlockErrors: reports structural problems', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['type' => 'handler', 'handler' => 'h'],            // missing name
            ['name' => 'bad', 'type' => 'nope'],                // bad type
            ['name' => 'h2', 'type' => 'handler'],              // handler missing handler key
            ['name' => 'l2', 'type' => 'link'],                 // link missing route
            ['name' => 'b2', 'type' => 'builtin'],              // builtin not in show/edit/delete
            ['name' => 'm2', 'type' => 'handler', 'handler' => 'h', 'method' => 'PUT'], // bad method
        ],
    ]]);
    assert_same(6, count($errors));
});

test('actionsBlockErrors: actions/row/bulk must be arrays', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => 'nope']);
    assert_same(['actions debe ser un objeto.'], $errors);

    $errors2 = CrudConfigValidator::actionsBlockErrors(['actions' => ['row' => 'x', 'bulk' => 5]]);
    assert_same(2, count($errors2));
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudConfigValidatorActionsTest`
Expected: FAIL — `Call to undefined method ...::actionsBlockErrors()`.

- [ ] **Step 3: Add the static method and wire it in**

In `app/Application/Services/CrudConfigValidator.php`, add this method immediately before `newBlockShapeErrors` (or before `validateTableSecurity`):

```php
    /**
     * Valida la forma del bloque `actions` (Fase 1). Pura, sin DB.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function actionsBlockErrors(array $config): array
    {
        if (!array_key_exists('actions', $config)) {
            return [];
        }
        $actions = $config['actions'];
        if (!is_array($actions)) {
            return ['actions debe ser un objeto.'];
        }

        $errors = [];
        foreach (['row', 'bulk'] as $group) {
            if (!array_key_exists($group, $actions)) {
                continue;
            }
            if (!is_array($actions[$group])) {
                $errors[] = "actions.{$group} debe ser un arreglo.";
                continue;
            }
            foreach ($actions[$group] as $i => $action) {
                if (!is_array($action)) {
                    $errors[] = "actions.{$group}[{$i}] debe ser un objeto.";
                    continue;
                }
                $name = (string) ($action['name'] ?? '');
                $type = (string) ($action['type'] ?? '');
                if ($name === '') {
                    $errors[] = "actions.{$group}[{$i}].name es obligatorio.";
                }
                if (!in_array($type, ['builtin', 'handler', 'link', 'transition'], true)) {
                    $errors[] = "actions.{$group}[{$i}].type inválido ('{$type}').";
                    continue;
                }
                if ($type === 'handler' && ($action['handler'] ?? '') === '') {
                    $errors[] = "actions.{$group}[{$i}] (handler) requiere 'handler'.";
                }
                if ($type === 'link' && ($action['route'] ?? '') === '') {
                    $errors[] = "actions.{$group}[{$i}] (link) requiere 'route'.";
                }
                if ($type === 'builtin' && !in_array($name, ['show', 'edit', 'delete'], true)) {
                    $errors[] = "actions.{$group}[{$i}] builtin debe ser show/edit/delete.";
                }
                if (array_key_exists('method', $action)
                    && !in_array(strtoupper((string) $action['method']), ['GET', 'POST'], true)) {
                    $errors[] = "actions.{$group}[{$i}].method debe ser GET o POST.";
                }
            }
        }
        return $errors;
    }
```

Then wire it into `validate()`. Find the Fase 0 wiring block:

```php
        foreach (self::newBlockShapeErrors($config) as $shapeError) {
            $errors[] = $shapeError;
        }
```

and add, immediately after it:

```php
        foreach (self::actionsBlockErrors($config) as $actionError) {
            $errors[] = $actionError;
        }
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudConfigValidatorActionsTest`
Expected: `4 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudConfigValidator.php tests/Crud/Action/CrudConfigValidatorActionsTest.php
git commit -m "feat(crud): validate the actions config block shape"
```

---

### Task 4: `CrudActionResolver` (pure)

**Files:**
- Create: `app/Application/Services/CrudActionResolver.php`
- Test: `tests/Crud/Action/CrudActionResolverTest.php`

> Pure decision logic: builds per-row view-models (filtered by `visible_when` + a permission-checker callable), permitted bulk actions, and resolves a row action by name for execution (throws `ValidationException` if unknown/not a row action). The execution endpoint URL is built here so the view stays dumb.

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Action/CrudActionResolverTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudActionResolver;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;

function resolver_def(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'eventos', 'table' => 'dom_eventos', 'primary_key' => 'id', 'permission_prefix' => 'eventos'],
        'actions' => [
            'row' => [
                ['name' => 'show', 'type' => 'builtin'],
                ['name' => 'autorizar', 'type' => 'handler', 'handler' => 'evt_auth',
                 'label' => 'Autorizar', 'icon' => 'bi-check2', 'permission' => 'autorizar',
                 'visible_when' => ['status' => 'pendiente'], 'confirm' => '¿Autorizar?'],
                ['name' => 'contrato', 'type' => 'link', 'route' => '/admin/eventos/{id}/contrato',
                 'permission' => 'contrato.ver'],
            ],
            'bulk' => [
                ['name' => 'activar', 'type' => 'handler', 'handler' => 'evt_bulk', 'permission' => 'editar'],
            ],
        ],
    ]);
}

test('CrudActionResolver: visibleRowActions filters by visible_when and permission', function (): void {
    $resolver = new CrudActionResolver();
    $allow = static fn(string $slug): bool => true;

    $vm = $resolver->visibleRowActions(resolver_def(), ['id' => 7, 'status' => 'pendiente'], $allow);
    $names = array_map(static fn(array $a): string => $a['name'], $vm);
    assert_same(['show', 'autorizar', 'contrato'], $names);

    // autorizar hidden when status != pendiente
    $vm2 = $resolver->visibleRowActions(resolver_def(), ['id' => 7, 'status' => 'autorizado'], $allow);
    $names2 = array_map(static fn(array $a): string => $a['name'], $vm2);
    assert_same(['show', 'contrato'], $names2);
});

test('CrudActionResolver: permission denial hides the action', function (): void {
    $resolver = new CrudActionResolver();
    $deny = static fn(string $slug): bool => $slug !== 'eventos.autorizar';
    $vm = $resolver->visibleRowActions(resolver_def(), ['id' => 7, 'status' => 'pendiente'], $deny);
    $names = array_map(static fn(array $a): string => $a['name'], $vm);
    assert_same(['show', 'contrato'], $names);
});

test('CrudActionResolver: handler view-model carries endpoint, link carries substituted route', function (): void {
    $resolver = new CrudActionResolver();
    $allow = static fn(string $slug): bool => true;
    $vm = $resolver->visibleRowActions(resolver_def(), ['id' => 42, 'status' => 'pendiente'], $allow);

    $auth = $vm[1];
    assert_same('autorizar', $auth['name']);
    assert_same('handler', $auth['type']);
    assert_same('/admin/crud/eventos/42/accion/autorizar', $auth['endpoint']);
    assert_same('¿Autorizar?', $auth['confirm']);

    $link = $vm[2];
    assert_same('link', $link['type']);
    assert_same('/admin/eventos/42/contrato', $link['href']);
});

test('CrudActionResolver: permittedBulkActions respects permissions', function (): void {
    $resolver = new CrudActionResolver();
    $allow = static fn(string $slug): bool => true;
    $bulk = $resolver->permittedBulkActions(resolver_def(), $allow);
    assert_same(1, count($bulk));
    assert_same('activar', $bulk[0]['name']);
    assert_same('/admin/crud/eventos/accion-masiva/activar', $bulk[0]['endpoint']);

    $deny = static fn(string $slug): bool => false;
    assert_same([], $resolver->permittedBulkActions(resolver_def(), $deny));
});

test('CrudActionResolver: resolveExecutable returns the row action or throws', function (): void {
    $resolver = new CrudActionResolver();
    $a = $resolver->resolveExecutable(resolver_def(), 'autorizar');
    assert_same('autorizar', $a->name());

    assert_throws(ValidationException::class, function () use ($resolver): void {
        $resolver->resolveExecutable(resolver_def(), 'fantasma');
    });
});

test('CrudActionResolver: resolveBulkExecutable returns the bulk action or throws', function (): void {
    $resolver = new CrudActionResolver();
    $a = $resolver->resolveBulkExecutable(resolver_def(), 'activar');
    assert_same('activar', $a->name());

    assert_throws(ValidationException::class, function () use ($resolver): void {
        $resolver->resolveBulkExecutable(resolver_def(), 'nope');
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudActionResolverTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the resolver**

Create `app/Application/Services/CrudActionResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Exceptions\ValidationException;

/**
 * Lógica pura de resolución de acciones: produce view-models para render y
 * resuelve acciones por nombre para ejecución. No toca DB ni RBAC directamente;
 * recibe un callable `can(slug): bool` para filtrar por permiso.
 */
final class CrudActionResolver
{
    /**
     * @param array<string, mixed> $row
     * @param callable(string): bool $can
     * @return list<array<string, mixed>>
     */
    public function visibleRowActions(CrudResourceDefinition $definition, array $row, callable $can): array
    {
        $id = (int) ($row[$definition->primaryKey()] ?? 0);
        $prefix = $definition->permissionPrefix();
        $out = [];

        foreach ($definition->rowActions() as $action) {
            if (!$action->isVisibleFor($row)) {
                continue;
            }
            $permission = $action->resolvePermission($prefix);
            if ($permission !== null && !$can($permission)) {
                continue;
            }
            $out[] = $this->rowViewModel($definition, $action, $id, $row, $can);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @param callable(string): bool $can
     * @return array<string, mixed>
     */
    private function rowViewModel(CrudResourceDefinition $definition, CrudActionDefinition $action, int $id, array $row, callable $can): array
    {
        $vm = [
            'name' => $action->name(),
            'type' => $action->type(),
            'label' => $action->label(),
            'icon' => $action->icon(),
            'method' => $action->method(),
            'confirm' => $action->confirm(),
            'enabled' => $action->isEnabledFor($row),
        ];

        if ($action->isBuiltin()) {
            $vm['href'] = $this->builtinHref($definition->key(), $action->name(), $id);
        } elseif ($action->isLink()) {
            $vm['href'] = str_replace('{id}', (string) $id, (string) $action->route());
        } else { // handler / transition
            $vm['endpoint'] = '/admin/crud/' . $definition->key() . '/' . $id . '/accion/' . $action->name();
        }

        return $vm;
    }

    private function builtinHref(string $resourceKey, string $name, int $id): string
    {
        $base = '/admin/crud/' . $resourceKey;
        return match ($name) {
            'show'   => $base . '/' . $id,
            'edit'   => $base . '/' . $id . '/editar',
            'delete' => $base . '/' . $id . '/eliminar',
            default  => $base . '/' . $id,
        };
    }

    /**
     * @param callable(string): bool $can
     * @return list<array<string, mixed>>
     */
    public function permittedBulkActions(CrudResourceDefinition $definition, callable $can): array
    {
        $prefix = $definition->permissionPrefix();
        $out = [];
        foreach ($definition->bulkActions() as $action) {
            $permission = $action->resolvePermission($prefix);
            if ($permission !== null && !$can($permission)) {
                continue;
            }
            $out[] = [
                'name' => $action->name(),
                'label' => $action->label(),
                'confirm' => $action->confirm(),
                'endpoint' => '/admin/crud/' . $definition->key() . '/accion-masiva/' . $action->name(),
            ];
        }
        return $out;
    }

    public function resolveExecutable(CrudResourceDefinition $definition, string $actionName): CrudActionDefinition
    {
        foreach ($definition->rowActions() as $action) {
            if ($action->name() === $actionName) {
                return $action;
            }
        }
        throw new ValidationException("La acción '{$actionName}' no existe en este recurso.");
    }

    public function resolveBulkExecutable(CrudResourceDefinition $definition, string $actionName): CrudActionDefinition
    {
        foreach ($definition->bulkActions() as $action) {
            if ($action->name() === $actionName) {
                return $action;
            }
        }
        throw new ValidationException("La acción masiva '{$actionName}' no existe en este recurso.");
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudActionResolverTest`
Expected: `6 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudActionResolver.php tests/Crud/Action/CrudActionResolverTest.php
git commit -m "feat(crud): add pure CrudActionResolver for row/bulk action view-models"
```

---

### Task 5: `CrudActionService` (execution glue)

**Files:**
- Create: `app/Application/Services/CrudActionService.php`
- Create: `tests/fixtures/action_handlers.php`
- Test: `tests/Crud/Action/CrudActionServiceTest.php`

> Like the Fase 0 `CrudDataService`, this service is glue over `CrudConfigLoader`/`CrudDataService`/`RbacService`/`BitacoraRepository` (DB-bound, not unit-testable here). The **dispatch decision** is the testable seam: we extract `dispatch(CrudActionDefinition $action, CrudActionContext $ctx)` as a method that uses only the (constructable) `CrudHandlerRegistry`, and unit-test it. The full `run()`/`runBulk()` orchestration is covered by a manual smoke in Task 9.

- [ ] **Step 1: Add test fixtures**

Create `tests/fixtures/action_handlers.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Domain\Interfaces\CrudActionHandlerInterface;

if (!class_exists('RecordingActionHandler')) {
    /** Registra la última llamada en una propiedad estática para aserciones. */
    class RecordingActionHandler implements CrudActionHandlerInterface
    {
        public static ?CrudActionContext $last = null;
        public function handle(CrudActionContext $ctx): void
        {
            self::$last = $ctx;
        }
    }

    /** Lanza para simular un fallo de acción. */
    class FailingActionHandler implements CrudActionHandlerInterface
    {
        public function handle(CrudActionContext $ctx): void
        {
            throw new \RuntimeException('boom en acción');
        }
    }
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Crud/Action/CrudActionServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Services\CrudActionService;
use App\Application\Services\CrudHandlerRegistry;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Exceptions\ValidationException;

require_once dirname(__DIR__, 1) . '/../fixtures/action_handlers.php';

function action_ctx(): CrudActionContext
{
    return new CrudActionContext('eventos', 'dom_eventos', 'id', 1, '127.0.0.1', 7, ['id' => 7, 'status' => 'pendiente'], 'autorizar', []);
}

test('CrudActionService::dispatch runs a handler action via the registry', function (): void {
    RecordingActionHandler::$last = null;
    $svc = new CrudActionService(
        new CrudHandlerRegistry(['evt_auth' => RecordingActionHandler::class])
    );
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'handler', 'handler' => 'evt_auth']);
    $svc->dispatch($action, action_ctx());
    assert_true(RecordingActionHandler::$last instanceof CrudActionContext, 'handler ran');
    assert_same('autorizar', RecordingActionHandler::$last->action());
});

test('CrudActionService::dispatch rejects link actions (navigation only)', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry([]));
    $action = CrudActionDefinition::fromArray(['name' => 'pdf', 'type' => 'link', 'route' => '/x/{id}']);
    assert_throws(ValidationException::class, function () use ($svc, $action): void {
        $svc->dispatch($action, action_ctx());
    });
});

test('CrudActionService::dispatch rejects builtin and transition in Fase 1', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry([]));
    foreach (['builtin', 'transition'] as $type) {
        $action = CrudActionDefinition::fromArray(['name' => 'x', 'type' => $type, 'handler' => 'h', 'to' => 't', 'route' => '/r']);
        assert_throws(ValidationException::class, function () use ($svc, $action): void {
            $svc->dispatch($action, action_ctx());
        });
    }
});

test('CrudActionService::dispatch rethrows a handler exception', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry(['fail' => FailingActionHandler::class]));
    $action = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'fail']);
    assert_throws(\RuntimeException::class, function () use ($svc, $action): void {
        $svc->dispatch($action, action_ctx());
    });
});

test('CrudActionService::dispatch errors when handler key is missing from the registry', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry([]));
    $action = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'ausente']);
    assert_throws(ValidationException::class, function () use ($svc, $action): void {
        $svc->dispatch($action, action_ctx());
    });
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `php tests/run.php CrudActionServiceTest`
Expected: FAIL — `Class "App\Application\Services\CrudActionService" not found`.

- [ ] **Step 4: Implement the service**

Create `app/Application/Services/CrudActionService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Crud\Context\CrudActionContext;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Domain\Interfaces\CrudActionHandlerInterface;
use App\Kernel\Logging\AppLogger;

/**
 * Ejecuta acciones de fila y masivas del CRUD Engine. La decisión de despacho
 * (por tipo) vive en `dispatch()` y es el punto testeable; `run()`/`runBulk()`
 * orquestan carga de definición, RBAC, carga de registro, re-chequeo de
 * `visible_when` en servidor y auditoría.
 *
 * Tipos soportados en Fase 1: `handler`. `link` es solo navegación (no se
 * ejecuta en servidor). `builtin` usa las rutas existentes. `transition` llega
 * en Fase 2.
 */
final class CrudActionService
{
    private const MAX_BULK_IDS = 500;

    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry,
        private readonly ?CrudConfigLoader $configLoader = null,
        private readonly ?CrudDataService $dataService = null,
        private readonly ?CrudActionResolver $resolver = null,
        private readonly ?RbacService $rbacService = null,
        private readonly ?BitacoraRepositoryInterface $bitacoraRepository = null
    ) {}

    /**
     * Despacha una acción ya resuelta sobre un contexto ya construido.
     * Único punto que toca el registry; sin DB → unit-testable.
     */
    public function dispatch(CrudActionDefinition $action, CrudActionContext $ctx): void
    {
        if (!$action->isHandler()) {
            throw new ValidationException("La acción '{$action->name()}' no es ejecutable en el servidor (tipo {$action->type()}).");
        }

        $handler = $this->handlerRegistry->resolve($action->handler(), CrudActionHandlerInterface::class);
        if ($handler === null) {
            throw new ValidationException("El handler '{$action->handler()}' no está registrado en la whitelist.");
        }

        /** @var CrudActionHandlerInterface $handler */
        $handler->handle($ctx);
    }

    /**
     * Ejecuta una acción de fila completa.
     *
     * @param array<string, mixed> $input
     */
    public function run(string $resource, int $id, string $actionName, array $input, ?int $userId, string $ip): void
    {
        $this->assertWired();
        $definition = $this->configLoader->load($resource);
        $action = $this->resolver->resolveExecutable($definition, $actionName);

        $permission = $action->resolvePermission($definition->permissionPrefix());
        if ($permission !== null) {
            $this->rbacService->verificar($permission);
        }

        $record = $this->dataService->find($definition, $id);
        if (!is_array($record) || (int) ($record['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        // Re-chequeo server-side: nunca confiar en la UI.
        if (!$action->isVisibleFor($record) || !$action->isEnabledFor($record)) {
            throw new ValidationException('La acción no está disponible para este registro.');
        }

        $ctx = new CrudActionContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $id,
            $record,
            $action->name(),
            $input
        );

        $this->dispatch($action, $ctx);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.action:' . $action->name(),
            $definition->table(),
            $id,
            json_encode(['action' => $action->name(), 'input' => $input], JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );
    }

    /**
     * Ejecuta una acción masiva best-effort. Devuelve resumen {ok, fail, errors}.
     *
     * @param list<int> $ids
     * @param array<string, mixed> $input
     * @return array{ok: int, fail: int, errors: list<string>}
     */
    public function runBulk(string $resource, string $actionName, array $ids, array $input, ?int $userId, string $ip): array
    {
        $this->assertWired();
        $definition = $this->configLoader->load($resource);
        $action = $this->resolver->resolveBulkExecutable($definition, $actionName);

        $permission = $action->resolvePermission($definition->permissionPrefix());
        if ($permission !== null) {
            $this->rbacService->verificar($permission);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
        if (count($ids) > self::MAX_BULK_IDS) {
            throw new ValidationException('Demasiados registros seleccionados (máximo ' . self::MAX_BULK_IDS . ').');
        }

        $ok = 0;
        $fail = 0;
        $errors = [];
        foreach ($ids as $id) {
            try {
                $record = $this->dataService->find($definition, $id);
                if (!is_array($record) || (int) ($record['deleted'] ?? 0) === 1) {
                    throw new ValidationException("Registro {$id} no existe.");
                }
                $ctx = new CrudActionContext(
                    $definition->key(),
                    $definition->table(),
                    $definition->primaryKey(),
                    $userId,
                    $ip,
                    $id,
                    $record,
                    $action->name(),
                    $input
                );
                $this->dispatch($action, $ctx);
                $this->bitacoraRepository->registrar(
                    $userId,
                    'crud.action:' . $action->name(),
                    $definition->table(),
                    $id,
                    json_encode(['bulk' => true, 'action' => $action->name()], JSON_UNESCAPED_UNICODE) ?: '',
                    $ip
                );
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $errors[] = "ID {$id}: " . $e->getMessage();
                AppLogger::warning('CRUD bulk action: ítem falló', [
                    'resource' => $resource, 'action' => $actionName, 'id' => $id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return ['ok' => $ok, 'fail' => $fail, 'errors' => $errors];
    }

    private function assertWired(): void
    {
        if ($this->configLoader === null || $this->dataService === null
            || $this->resolver === null || $this->rbacService === null
            || $this->bitacoraRepository === null) {
            throw new \LogicException('CrudActionService no está cableado con todas sus dependencias.');
        }
    }
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `php tests/run.php CrudActionServiceTest`
Expected: `5 passed, 0 failed`. Then `php tests/run.php` — full suite green.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudActionService.php tests/fixtures/action_handlers.php tests/Crud/Action/CrudActionServiceTest.php
git commit -m "feat(crud): add CrudActionService with unit-tested dispatch seam"
```

---

### Task 6: `CrudResourceService` passthrough + index data augmentation

**Files:**
- Modify: `app/Application/Services/CrudResourceService.php`

> Adds the two new deps and three methods. RBAC for run/bulk is enforced inside `CrudActionService` (via the action permission); here we only orchestrate. `buildIndexData` gains `_actions` per row + `bulkActions` + `selectable` for the view.

- [ ] **Step 1: Add the imports and constructor deps**

In `app/Application/Services/CrudResourceService.php`, the constructor currently is:

```php
    public function __construct(
        private readonly CrudConfigLoader $configLoader,
        private readonly CrudDataService $dataService,
        private readonly CrudFormBuilder $formBuilder,
        private readonly CrudTableBuilder $tableBuilder,
        private readonly RbacService $rbacService
    ) {}
```

Replace it with:

```php
    public function __construct(
        private readonly CrudConfigLoader $configLoader,
        private readonly CrudDataService $dataService,
        private readonly CrudFormBuilder $formBuilder,
        private readonly CrudTableBuilder $tableBuilder,
        private readonly RbacService $rbacService,
        private readonly CrudActionResolver $actionResolver,
        private readonly CrudActionService $actionService
    ) {}
```

(No new `use` lines needed — `CrudActionResolver` and `CrudActionService` are in the same `App\Application\Services` namespace.)

- [ ] **Step 2: Augment `buildIndexData`**

In `buildIndexData`, the method currently ends with `return $this->tableBuilder->build(...);`. Change it to capture the result, attach actions, and return. Replace:

```php
        return $this->tableBuilder->build(
            definition: $definition,
            rows: $result['rows'],
            paginator: $result['paginator'],
            total: $result['total'],
            permissions: $permissions,
            query: $query,
            groupBy: (string) ($result['groupBy'] ?? ''),
            summaryRow: is_array($result['summaryRow'] ?? null) ? $result['summaryRow'] : [],
            aggregationSkipped: (bool) ($result['aggregationSkipped'] ?? false),
            aggregationSkipMessage: isset($result['aggregationSkipMessage']) ? (string) $result['aggregationSkipMessage'] : null,
            tableCompact: $definition->listTableCompact()
        );
    }
```

with:

```php
        $data = $this->tableBuilder->build(
            definition: $definition,
            rows: $result['rows'],
            paginator: $result['paginator'],
            total: $result['total'],
            permissions: $permissions,
            query: $query,
            groupBy: (string) ($result['groupBy'] ?? ''),
            summaryRow: is_array($result['summaryRow'] ?? null) ? $result['summaryRow'] : [],
            aggregationSkipped: (bool) ($result['aggregationSkipped'] ?? false),
            aggregationSkipMessage: isset($result['aggregationSkipMessage']) ? (string) $result['aggregationSkipMessage'] : null,
            tableCompact: $definition->listTableCompact()
        );

        $can = fn(string $slug): bool => $this->rbacService->puede($slug);
        if (is_array($data['rows'] ?? null)) {
            foreach ($data['rows'] as &$row) {
                $row['_actions'] = $this->actionResolver->visibleRowActions($definition, $row, $can);
            }
            unset($row);
        }
        $data['bulkActions'] = $this->actionResolver->permittedBulkActions($definition, $can);
        $data['selectable'] = !empty($data['bulkActions']) && empty($data['grouped']);

        return $data;
    }
```

- [ ] **Step 3: Add `runAction` and `runBulkAction` passthroughs**

Add these methods just before `private function resolvePermissions(` :

```php
    /** @param array<string, mixed> $input */
    public function runAction(string $resource, int $id, string $action, array $input, ?int $userId, string $ip): void
    {
        $this->actionService->run($resource, $id, $action, $input, $userId, $ip);
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $input
     * @return array{ok: int, fail: int, errors: list<string>}
     */
    public function runBulkAction(string $resource, string $action, array $ids, array $input, ?int $userId, string $ip): array
    {
        return $this->actionService->runBulk($resource, $action, $ids, $input, $userId, $ip);
    }
```

- [ ] **Step 4: Run the full suite**

Run: `php tests/run.php`
Expected: still `N passed, 0 failed` (no test directly exercises this file; this guards against parse errors). Also run `php -l app/Application/Services/CrudResourceService.php` → `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudResourceService.php
git commit -m "feat(crud): wire action resolver/service into CrudResourceService and index data"
```

---

### Task 7: Container bindings + routes

**Files:**
- Modify: `config/container.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Register the new singletons**

In `config/container.php`, find the `CrudResourceService` singleton (it currently passes 5 args ending with `$c->get(RbacService::class)`). First register the two new services *before* it. Locate:

```php
    $container->singleton(CrudResourceService::class, fn(Container $c) => new CrudResourceService(
```

Insert immediately above that line:

```php
    $container->singleton(CrudActionResolver::class, fn() => new CrudActionResolver());
    $container->singleton(CrudActionService::class, fn(Container $c) => new CrudActionService(
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudActionResolver::class),
        $c->get(RbacService::class),
        $c->get(\App\Domain\Interfaces\BitacoraRepositoryInterface::class)
    ));
```

Then add the two new args to the `CrudResourceService` binding. Its last current argument line is `$c->get(RbacService::class)`. Change that line to add the two new dependencies after it:

```php
        $c->get(RbacService::class),
        $c->get(CrudActionResolver::class),
        $c->get(CrudActionService::class)
```

Add the imports near the other `use App\Application\Services\Crud...;` lines at the top of `config/container.php`:

```php
use App\Application\Services\CrudActionResolver;
use App\Application\Services\CrudActionService;
```

> If `BitacoraRepositoryInterface` is already imported in this file under a short alias, use it; otherwise the fully-qualified name above works as-is. Verify the exact binding key the project uses for the bitácora repo by grepping: `grep -n "Bitacora" config/container.php` — bind against whatever interface/class is already registered there and adjust the `$c->get(...)` accordingly.

- [ ] **Step 2: Add the routes**

In `routes/web.php`, the CRUD routes end at line 81 (`$router->post('/crud/{resource}/{id}/eliminar', ...)`), just before the admin group's closing `});`. Add the two action routes immediately after line 81:

```php
    $router->post('/crud/{resource}/{id}/accion/{action}',   [CrudController::class, 'action'],     [CsrfMiddleware::class]);
    $router->post('/crud/{resource}/accion-masiva/{action}', [CrudController::class, 'bulkAction'], [CsrfMiddleware::class]);
```

> Route precedence: the row-action route has 5 segments under `/admin` (`crud/{resource}/{id}/accion/{action}`) and the bulk route has 4 (`crud/{resource}/accion-masiva/{action}`); neither matches the existing `/crud/{resource}/{id}` (3) or `/crud/{resource}/{id}/eliminar` (4, but the 3rd literal segment is `eliminar`, not `accion-masiva`). They do not collide. Confirm during the Task 9 smoke that POSTing to a custom action does not hit `update`/`delete`.

- [ ] **Step 3: Lint**

Run: `php -l config/container.php` and `php -l routes/web.php`
Expected: `No syntax errors detected` for both. Then `php tests/run.php` — full suite green.

- [ ] **Step 4: Commit**

```bash
git add config/container.php routes/web.php
git commit -m "feat(crud): bind action services and register action endpoints"
```

---

### Task 8: Controller methods `action()` + `bulkAction()`

**Files:**
- Modify: `app/Presentation/Controllers/Admin/CrudController.php`

> Mirror the existing `store`/`delete` patterns exactly (CSRF, currentUser, flash, exception mapping).

- [ ] **Step 1: Add the two methods**

In `app/Presentation/Controllers/Admin/CrudController.php`, add these methods immediately before the final closing `}` of the class (after `delete()`):

```php
    public function action(Request $request): Response
    {
        $resource = (string) $request->param('resource');
        $id = (int) $request->param('id');
        $action = (string) $request->param('action');
        try {
            $this->verifyCsrf($request);
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $this->crudResourceService->runAction($resource, $id, $action, $request->all(), $userId > 0 ? $userId : null, $request->ip());
            return $this->redirectWithFlash('/admin/crud/' . $resource, 'success', 'Acción ejecutada correctamente.');
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/crud/' . $resource, 'error', $e->getMessage());
        }
    }

    public function bulkAction(Request $request): Response
    {
        $resource = (string) $request->param('resource');
        $action = (string) $request->param('action');
        try {
            $this->verifyCsrf($request);
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $idsRaw = $request->all()['ids'] ?? [];
            $ids = is_array($idsRaw) ? array_map('intval', $idsRaw) : [];
            if ($ids === []) {
                return $this->redirectWithFlash('/admin/crud/' . $resource, 'error', 'No se seleccionaron registros.');
            }
            $summary = $this->crudResourceService->runBulkAction($resource, $action, $ids, $request->all(), $userId > 0 ? $userId : null, $request->ip());
            $type = $summary['fail'] > 0 ? 'warning' : 'success';
            $msg = "Acción masiva: {$summary['ok']} correctos, {$summary['fail']} con error.";
            return $this->redirectWithFlash('/admin/crud/' . $resource, $type, $msg);
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/crud/' . $resource, 'error', $e->getMessage());
        }
    }
```

(`Request`, `Response`, `AccesoException`, `ValidationException` are already imported at the top of this file.)

- [ ] **Step 2: Lint**

Run: `php -l app/Presentation/Controllers/Admin/CrudController.php`
Expected: `No syntax errors detected`. Then `php tests/run.php` — full suite green.

- [ ] **Step 3: Commit**

```bash
git add app/Presentation/Controllers/Admin/CrudController.php
git commit -m "feat(crud): add action and bulkAction controller endpoints"
```

---

### Task 9: Views (row actions + bulk bar + JS), example config, docs, e2e smoke

**Files:**
- Create: `app/Presentation/Views/admin/crud/partials/actions_row.php`
- Create: `app/Presentation/Views/admin/crud/partials/actions_bulk.php`
- Create: `public/assets/js/crud-engine.js`
- Create: `app/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php`
- Modify: `app/Presentation/Views/admin/crud/index.php`
- Modify: `config/cruds/demo_productos.json`
- Modify: `config/crud_handlers.php`
- Modify: `docs/modules/crud/modulo-crud-engine.md`

- [ ] **Step 1: Row actions partial**

Create `app/Presentation/Views/admin/crud/partials/actions_row.php`:

```php
<?php
use App\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $rowActions */
/** @var string $resource */
$rowActions = $rowActions ?? [];
?>
<div class="d-flex justify-content-end gap-1 flex-wrap">
    <?php foreach ($rowActions as $a):
        $name = (string) ($a['name'] ?? '');
        $label = (string) ($a['label'] ?? $name);
        $icon = (string) ($a['icon'] ?? '');
        $type = (string) ($a['type'] ?? '');
        $enabled = (bool) ($a['enabled'] ?? true);
        $disabledAttr = $enabled ? '' : ' disabled aria-disabled="true"';
    ?>
        <?php if ($type === 'builtin' || $type === 'link'): ?>
            <a href="<?= ViewHelper::e((string) ($a['href'] ?? '#')) ?>"
               class="btn btn-sm btn-outline-secondary<?= $enabled ? '' : ' disabled' ?>"
               title="<?= ViewHelper::e($label) ?>" aria-label="<?= ViewHelper::e($label) ?>">
                <?php if ($icon !== ''): ?><i class="bi <?= ViewHelper::e($icon) ?>" aria-hidden="true"></i><?php else: ?><?= ViewHelper::e($label) ?><?php endif; ?>
            </a>
        <?php else: /* handler (POST) */ ?>
            <form method="POST" action="<?= ViewHelper::e((string) ($a['endpoint'] ?? '#')) ?>" class="d-inline js-crud-action"
                  <?php if (!empty($a['confirm'])): ?>data-confirm="<?= ViewHelper::e((string) $a['confirm']) ?>"<?php endif; ?>>
                <?= ViewHelper::csrfField() ?>
                <button type="submit" class="btn btn-sm btn-outline-primary"<?= $disabledAttr ?>
                        title="<?= ViewHelper::e($label) ?>" aria-label="<?= ViewHelper::e($label) ?>">
                    <?php if ($icon !== ''): ?><i class="bi <?= ViewHelper::e($icon) ?>" aria-hidden="true"></i><?php else: ?><?= ViewHelper::e($label) ?><?php endif; ?>
                </button>
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
```

- [ ] **Step 2: Bulk bar partial**

Create `app/Presentation/Views/admin/crud/partials/actions_bulk.php`:

```php
<?php
use App\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $bulkActions */
/** @var string $resource */
$bulkActions = $bulkActions ?? [];
if ($bulkActions === []) { return; }
?>
<div class="ct-bulk-bar d-none align-items-center gap-2 p-2 border-bottom bg-light" data-crud-bulk-bar>
    <span class="small text-muted"><span data-crud-bulk-count>0</span> seleccionados</span>
    <?php foreach ($bulkActions as $b):
        $name = (string) ($b['name'] ?? '');
    ?>
        <form method="POST" action="<?= ViewHelper::e((string) ($b['endpoint'] ?? '#')) ?>" class="d-inline js-crud-bulk-form"
              <?php if (!empty($b['confirm'])): ?>data-confirm="<?= ViewHelper::e((string) $b['confirm']) ?>"<?php endif; ?>>
            <?= ViewHelper::csrfField() ?>
            <span data-crud-bulk-ids></span>
            <button type="submit" class="btn btn-sm btn-outline-primary"><?= ViewHelper::e((string) ($b['label'] ?? $name)) ?></button>
        </form>
    <?php endforeach; ?>
</div>
```

- [ ] **Step 3: Bulk/selection JS**

Create `public/assets/js/crud-engine.js`:

```javascript
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Generic confirm for single + bulk POST action forms.
        document.querySelectorAll('form.js-crud-action, form.js-crud-bulk-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var msg = form.getAttribute('data-confirm');
                if (msg && !window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        });

        var bar = document.querySelector('[data-crud-bulk-bar]');
        if (!bar) { return; }

        var selectAll = document.querySelector('[data-crud-select-all]');
        var checkboxes = function () {
            return Array.prototype.slice.call(document.querySelectorAll('[data-crud-row-check]'));
        };
        var countEl = bar.querySelector('[data-crud-bulk-count]');

        function selectedIds() {
            return checkboxes().filter(function (c) { return c.checked; })
                .map(function (c) { return c.value; });
        }

        function refresh() {
            var ids = selectedIds();
            if (countEl) { countEl.textContent = String(ids.length); }
            bar.classList.toggle('d-none', ids.length === 0);
            // Inject hidden ids[] into every bulk form.
            document.querySelectorAll('[data-crud-bulk-ids]').forEach(function (slot) {
                slot.innerHTML = '';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    slot.appendChild(input);
                });
            });
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes().forEach(function (c) { c.checked = selectAll.checked; });
                refresh();
            });
        }
        document.addEventListener('change', function (e) {
            if (e.target && e.target.matches('[data-crud-row-check]')) { refresh(); }
        });
        refresh();
    });
})();
```

- [ ] **Step 4: Rewire `index.php`**

In `app/Presentation/Views/admin/crud/index.php`:

(a) After the opening `<section class="ct-table-card ...">` line and before the `<div class="card-header ...">` search form, add the bulk bar partial:

```php
    <?= ViewHelper::partial('crud/partials/actions_bulk', [
        'bulkActions' => $bulkActions ?? [],
        'resource' => $resource ?? '',
    ]) ?>
```

(b) In the `<thead>`, add a leading checkbox header. Change:

```php
                        <tr>
                            <?php foreach (($columns ?? []) as $column): ?>
```

to:

```php
                        <tr>
                            <?php if (!empty($selectable)): ?>
                                <th class="px-3" style="width:2.5rem">
                                    <input type="checkbox" class="form-check-input" data-crud-select-all aria-label="Seleccionar todo">
                                </th>
                            <?php endif; ?>
                            <?php foreach (($columns ?? []) as $column): ?>
```

(c) In the `<tbody>` row loop, add a leading checkbox cell. Change:

```php
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach (($columns ?? []) as $column): ?>
```

to:

```php
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php if (!empty($selectable)): ?>
                                    <td class="px-3">
                                        <input type="checkbox" class="form-check-input" data-crud-row-check
                                               value="<?= (int) ($row[$primaryKey] ?? 0) ?>" aria-label="Seleccionar registro">
                                    </td>
                                <?php endif; ?>
                                <?php foreach (($columns ?? []) as $column): ?>
```

(d) Replace the entire hardcoded actions cell (the `<?php if (!$grouped): ?> <td class="text-end px-3"> ... </td> <?php endif; ?>` block inside the row loop, currently lines ~138–161) with the partial:

```php
                                <?php if (!$grouped): ?>
                                    <td class="text-end px-3">
                                        <?= ViewHelper::partial('crud/partials/actions_row', [
                                            'rowActions' => $row['_actions'] ?? [],
                                            'resource' => $resource ?? '',
                                        ]) ?>
                                    </td>
                                <?php endif; ?>
```

(e) Update the empty-state and select-all `colspan` to account for the checkbox column. Change:

```php
                        <?= ViewHelper::partial('crud/list_empty', [
                            'colspan' => count($columns ?? []) + ($grouped ? 0 : 1),
```

to:

```php
                        <?= ViewHelper::partial('crud/list_empty', [
                            'colspan' => count($columns ?? []) + ($grouped ? 0 : 1) + (!empty($selectable) ? 1 : 0),
```

(f) Before the closing `</div>` of the outer container (just before the final `<div class="modal ...">` delete modal, or at the end of the file), add the script include:

```php
<script src="<?= ViewHelper::asset('js/crud-engine.js') ?>"></script>
```

> Back-compat: configs without an `actions` block produce `_actions` = builtin show/edit/delete (Task 2 fallback) and `bulkActions = []`, so `selectable` is false and the table renders exactly as before — verified in the smoke.

- [ ] **Step 5: Example handler + config + registry**

Create `app/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudActionContext;
use App\Domain\Interfaces\CrudActionHandlerInterface;
use App\Infrastructure\Repositories\GenericCrudRepository;

/**
 * Acción demo: alterna el estado activo/inactivo de un producto.
 * Ejemplo de escape hatch — la lógica vive aquí, no en el core.
 */
final class DemoProductoToggleStatusHandler implements CrudActionHandlerInterface
{
    public function __construct(
        private readonly GenericCrudRepository $repository
    ) {}

    public function handle(CrudActionContext $ctx): void
    {
        $record = $ctx->record() ?? [];
        $current = (string) ($record['status'] ?? 'activo');
        $next = $current === 'activo' ? 'inactivo' : 'activo';

        $this->repository->updateRecord($ctx->table(), $ctx->primaryKey(), $ctx->recordId(), [
            'status' => $next,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $ctx->userId(),
        ]);
    }
}
```

> Verify `GenericCrudRepository::updateRecord(table, pk, id, array)` exists with this signature (it is used by `CrudDataService` in Fase 0). If the handler needs construction args, register it as a closure in `config/crud_handlers.php` is not possible (the registry instantiates by `new $class()` with no args). **Therefore** the registry must be able to build it — confirm `CrudHandlerRegistry::resolve` does `new $class()`. Since this handler needs a repository, instead make it resolve the repo itself: replace the constructor with a no-arg one and obtain the repository inside `handle()` via the container, OR keep handlers dependency-free. **Chosen approach:** make the handler dependency-free by using a tiny static accessor is undesirable; simplest correct option — give `CrudHandlerRegistry` access to the container so `resolve()` can autowire. If that is out of scope, write the demo handler to perform the update through a `new GenericCrudRepository()` directly (it has a no-arg constructor, per the container binding `fn() => new GenericCrudRepository()`).

Given the container binds `GenericCrudRepository` as `new GenericCrudRepository()` (no args), use the no-arg form. Replace the handler body's constructor usage accordingly:

```php
final class DemoProductoToggleStatusHandler implements CrudActionHandlerInterface
{
    public function handle(CrudActionContext $ctx): void
    {
        $record = $ctx->record() ?? [];
        $next = ((string) ($record['status'] ?? 'activo')) === 'activo' ? 'inactivo' : 'activo';

        (new \App\Infrastructure\Repositories\GenericCrudRepository())->updateRecord(
            $ctx->table(),
            $ctx->primaryKey(),
            $ctx->recordId(),
            ['status' => $next, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $ctx->userId()]
        );
    }
}
```

Register it in `config/crud_handlers.php` — change the `return [...]` to:

```php
return [
    'demo_producto_toggle' => \App\Application\Crud\Handlers\DemoProductoToggleStatusHandler::class,
];
```

Add an `actions` block to `config/cruds/demo_productos.json` (top-level, sibling of `list`/`form`). Insert after the `list` object's closing brace:

```json
  "actions": {
    "row": [
      { "name": "show", "type": "builtin" },
      { "name": "edit", "type": "builtin" },
      { "name": "delete", "type": "builtin" },
      { "name": "toggle", "type": "handler", "handler": "demo_producto_toggle",
        "label": "Alternar estado", "icon": "bi-toggle-on", "method": "POST",
        "permission": "editar", "confirm": "¿Cambiar el estado de este producto?" }
    ],
    "bulk": [
      { "name": "toggle", "type": "handler", "handler": "demo_producto_toggle",
        "label": "Alternar estado", "permission": "editar", "confirm": "¿Alternar el estado de los seleccionados?" }
    ]
  },
```

- [ ] **Step 6: Run the suite, lint, then manual smoke**

Run: `php tests/run.php` → all green. Lint the new PHP: `php -l app/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php`.

Validate the JSON loads (config validator runs on load):
```bash
php -r 'require "app/Kernel/Autoloader.php"; $c=json_decode(file_get_contents("config/cruds/demo_productos.json"),true); var_dump(json_last_error()===JSON_ERROR_NONE);'
```
Expected: `bool(true)`.

Then the live e2e (needs DB + login). Start `php -S localhost:8000 -t public`, log in, go to `/admin/crud/demo_productos`:
- The row "Alternar estado" button appears (with `editar` permission). Click it → confirm dialog → product `status` flips, flash "Acción ejecutada correctamente.", and a `log_bitacora` row `crud.action:toggle` is written.
- Select two rows via checkboxes → bulk bar appears → "Alternar estado" → both flip, flash shows `2 correctos, 0 con error`.
- Open a CRUD **without** an actions block (e.g. `clientes`) → table + builtin show/edit/delete render exactly as before; no checkbox column.
- Confirm no `TypeError`/`Fatal` in `storage/logs/app-*.log`.

- [ ] **Step 7: Append the Fase 1 doc section**

Append to `docs/modules/crud/modulo-crud-engine.md`:

```markdown

## Fase 1 — Acciones declarativas (custom + bulk)

Bloque `actions` opcional en `config/cruds/{resource}.json`:

```json
"actions": {
  "row":  [ { "name": "...", "type": "builtin|handler|link", ... } ],
  "bulk": [ { "name": "...", "type": "handler", "handler": "<clave>", ... } ]
}
```

- **Tipos** (Fase 1): `builtin` (show/edit/delete, usa las rutas existentes), `handler` (ejecuta una clase `CrudActionHandlerInterface` whitelisteada), `link` (solo navegación, no se ejecuta en servidor). `transition` llega en Fase 2.
- **`visible_when` / `enabled_when`**: mapa de igualdad simple (escalar o lista) evaluado en el render **y re-validado en el servidor** antes de ejecutar.
- **`permission`**: slug completo (`modulo.accion`) o sufijo expandido contra `permission_prefix`.
- **Endpoints**: `POST /admin/crud/{resource}/{id}/accion/{action}` y `POST /admin/crud/{resource}/accion-masiva/{action}` (ambos con CSRF). Las acciones masivas son best-effort por ítem con resumen en flash y tope de 500 ids.
- **Auditoría**: cada ejecución registra `crud.action:{name}` en `log_bitacora`.
- **Compat**: sin bloque `actions`, se usan los `list.actions` (show/edit/delete) actuales y no hay barra bulk.

Componentes: `CrudActionDefinition` (VO), `CrudActionResolver` (puro, view-models + resolución), `CrudActionService` (orquestación: RBAC, carga, re-chequeo, dispatch, auditoría). La lógica de negocio vive en handlers externos (`app/Application/Crud/Handlers/`), nunca en el core.
```

- [ ] **Step 8: Commit**

```bash
git add app/Presentation/Views/admin/crud/partials/actions_row.php app/Presentation/Views/admin/crud/partials/actions_bulk.php public/assets/js/crud-engine.js app/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php app/Presentation/Views/admin/crud/index.php config/cruds/demo_productos.json config/crud_handlers.php docs/modules/crud/modulo-crud-engine.md
git commit -m "feat(crud): render custom row/bulk actions in index, demo handler, docs"
```

---

## Self-Review

**1. Spec coverage (Fase 1, spec §7 + §5 actions):**
- `CrudActionDefinition` → Task 1. ✓
- `CrudActionService` (fila + masiva) → Task 5 (`run`/`runBulk`/`dispatch`). ✓
- 2 endpoints → Task 7 (routes) + Task 8 (controller). ✓
- `CrudActionHandlerInterface` execution → Task 5 `dispatch` (Fase 0 interface reused). ✓
- Render en index con RBAC + `visible_when` → Task 4 (resolver) + Task 6 (`buildIndexData`) + Task 9 (partials). ✓
- Barra bulk (checkboxes, JS vanilla) → Task 9 (`actions_bulk.php` + `crud-engine.js`). ✓
- Auditoría → Task 5 (`crud.action:{name}` bitácora). ✓
- Tipos `handler`/`link`/`builtin` → Tasks 1/4/5; `transition` explicitly deferred to Fase 2 (rejected in `dispatch`). ✓
- Config validation of `actions` → Task 3. ✓
- `visible_when` server-side re-check (spec §3.4, risk "burlado desde la UI") → Task 5 `run()`. ✓
- Bulk id cap / best-effort (spec §3.4, risk "fallos parciales/coste") → Task 5 `runBulk()` (MAX_BULK_IDS, per-item try/catch). ✓
- Route precedence risk (spec §8) → Task 7 note + Task 9 smoke. ✓

**2. Placeholder scan:** No "TBD"/"add error handling"/"similar to". Every code step shows full file contents or exact, anchored replacement blocks. Two spots flag a *verification* (exact `BitacoraRepositoryInterface` binding key in container; `GenericCrudRepository::updateRecord` signature) with a concrete grep and a concrete fallback — these are real-codebase confirmations, not hidden work, and the fallback code is provided.

**3. Type/name consistency:** `CrudActionDefinition` getters (`name/type/label/icon/method/route/confirm/handler/to`, `isVisibleFor/isEnabledFor/resolvePermission`, predicates) are identical across Tasks 1, 4, 5. Resolver method names (`visibleRowActions/permittedBulkActions/resolveExecutable/resolveBulkExecutable`) match between Task 4 and Tasks 5/6. `CrudActionService` ctor (registry required first; loader/dataService/resolver/rbac/bitácora optional) matches the container binding in Task 7 and the unit tests in Task 5. View-model keys (`name/type/label/icon/method/confirm/enabled/href/endpoint`) match between resolver (Task 4) and partials (Task 9). Endpoint shapes (`/accion/{action}`, `/accion-masiva/{action}`) match resolver, routes, and controller.

**Known limitation (honest):** `CrudActionService::run/runBulk` and the controller/view/JS wiring are DB/HTTP-bound and not unit-tested here; correctness-critical logic (`CrudActionDefinition`, `CrudActionResolver`, `CrudActionService::dispatch`, config validation) is unit-tested at its pure seams, and the end-to-end path is verified by the Task 9 manual smoke — the same split used and proven in the Fase 0 plan.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-28-crud-engine-fase1-acciones.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks.

**2. Inline Execution** — execute tasks in this session using executing-plans, batch with checkpoints.

Which approach?
