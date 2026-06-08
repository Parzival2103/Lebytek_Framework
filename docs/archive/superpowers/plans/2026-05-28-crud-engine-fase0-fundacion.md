# CRUD Engine — Fase 0 (Fundación) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the typed-context foundation for the CRUD Engine Fase 2 — context family, segregated interfaces, a type-checked handler registry, a context-passing hook runner that fixes the `beforeStore` mutation bug, and config-validator scaffolding — with zero visible feature change and full backward compatibility.

**Architecture:** Replace the loose `array` payload passed to hooks with a small family of immutable/mutable context objects (capa Application). The hook runner now passes a context *object* by handle, so a handler's mutations to `data()` are read back by `CrudDataService` before persistence (this fixes the real bug where `beforeStore` could not change what got inserted). Hook method vocabulary moves to `beforeCreate/afterCreate/...` while the runner still dispatches legacy `beforeStore/afterStore` when a handler explicitly defines them. Four segregated interfaces (action / transition guard / validator / list scope) are added for later phases. All new metadata blocks remain optional; the config validator only gains shape scaffolding.

**Tech Stack:** PHP 8.1+ (runtime here is 8.5), custom PSR-4 autoloader (no Composer autoload map), MySQL via PDO, MVC + Onion layering. **Tests run as plain-PHP scripts** via a tiny in-repo harness (`php tests/run.php`) — PHPUnit is referenced in `composer.json`/`phpunit.xml.dist` but is **not actually installed** (its entry script is missing), and the repo's own `phpunit.xml.dist` says to use `php tests/smoke.php` meanwhile. This plan therefore uses a zero-dependency harness consistent with the project's no-Composer philosophy.

---

## Architectural note (read before starting)

The spec (section 4) places the context classes in `app/Application/Crud/Context/` and the handler interfaces in `app/Domain/Interfaces/`. Because the interfaces type-hint the contexts (e.g. `CrudActionHandlerInterface::handle(CrudActionContext $ctx)`), **Domain will import Application** for these CRUD-engine ports. This bends the strict onion rule in `CLAUDE.md` ("Domain has zero external dependencies"). This is per the approved spec and is intentional (the contexts are CRUD-engine transport objects, not domain entities). If the framework owner prefers strict layering, the alternative is to move `Crud/Context/` under `app/Domain/`. **Do not silently restructure** — follow the spec's placement; flag it in the Task 15 doc note so the decision is visible.

---

## File Structure

**New files (created by this plan):**

| File | Responsibility |
|---|---|
| `tests/lib/bootstrap.php` | Defines `ROOT_PATH/APP_PATH/PUBLIC_PATH/STORAGE_PATH`, registers the Kernel autoloader for `App\` in tests |
| `tests/lib/microtest.php` | Zero-dependency test harness: `test()`, `assert_same/true/null/throws`, summary + exit code |
| `tests/run.php` | Recursively discovers `*Test.php` under `tests/`, runs them, prints summary |
| `tests/fixtures/hook_handlers.php` | Test-only handler classes used by the runner test |
| `app/Application/Crud/Context/CrudContext.php` | Immutable base: `resourceKey, table, primaryKey, userId, ip` |
| `app/Application/Crud/Context/CrudWriteContext.php` | create/update/delete: `input` (ro), `record` (ro), `recordId`, mutable `data()` + read-back API |
| `app/Application/Crud/Context/CrudActionContext.php` | row action: `recordId, record, action, input` |
| `app/Application/Crud/Context/CrudTransitionContext.php` | `record, statusColumn, from, to, input` |
| `app/Application/Crud/Context/CrudValidationContext.php` | `input, normalized, record, isEdit` + `addError()` collector |
| `app/Application/Crud/Context/CrudListContext.php` | `query` + `addCondition()` with op whitelist |
| `app/Application/Crud/Context/CrudFormContext.php` | `isEdit, record` + `setFieldOptions/setFieldValue` overrides |
| `app/Domain/Interfaces/CrudActionHandlerInterface.php` | `handle(CrudActionContext): void` |
| `app/Domain/Interfaces/CrudTransitionGuardInterface.php` | `authorize(CrudTransitionContext): void` (throw = block) |
| `app/Domain/Interfaces/CrudValidatorInterface.php` | `validate(CrudValidationContext): void` |
| `app/Domain/Interfaces/CrudListScopeInterface.php` | `apply(CrudListContext): void` |
| `tests/Crud/Context/*Test.php`, `tests/Crud/*Test.php` | One test file per unit |

**Modified files:**

| File | Change |
|---|---|
| `app/Domain/Interfaces/CrudHookHandlerInterface.php` | Method signatures `array` → `CrudWriteContext` |
| `app/Application/Crud/Handlers/AbstractCrudHookHandler.php` | No-op bodies with typed contexts + optional extended events |
| `app/Application/Services/CrudHandlerRegistry.php` | `resolve(?string $key, string $expectedInterface = ...): ?object` with interface type-check |
| `app/Application/Services/CrudHookRunner.php` | Accept a context *object*; canonical + legacy-alias dispatch |
| `app/Application/Services/CrudDataService.php` | Build `CrudWriteContext`, read back `data()` after `beforeCreate/beforeUpdate`, new event names |
| `app/Application/Services/CrudConfigValidator.php` | Add pure static `newBlockShapeErrors()` scaffolding, wire into `validate()` |
| `config/crud_handlers.php` | Doc comment: registry now also holds action/validator/guard/scope handlers |
| `docs/modules/crud/modulo-crud-engine.md` | Short Fase 0 note + layering trade-off |

**Unchanged (reused):** `CrudConfigLoader`, `GenericCrudRepository`, `CrudFieldValidationService`, `CrudFormBuilder`, `CrudTableBuilder`, `CrudResourceService`, `CrudController`, `config/container.php` (no binding signatures change in Fase 0).

---

## Conventions for every task

- Run the suite with: `php tests/run.php` (optionally `php tests/run.php <substring>` to filter by path).
- Expected suite output ends with a line like `N passed, 0 failed` and exit code 0.
- All PHP files start with `<?php` then `declare(strict_types=1);`.
- Commit after each task with the exact message shown.

---

### Task 1: Test harness (plain-PHP, no PHPUnit)

**Files:**
- Create: `tests/lib/bootstrap.php`
- Create: `tests/lib/microtest.php`
- Create: `tests/run.php`
- Create: `tests/HarnessSelfTest.php`

- [ ] **Step 1: Write the harness bootstrap**

Create `tests/lib/bootstrap.php`:

```php
<?php

declare(strict_types=1);

// tests/lib/bootstrap.php -> repo root is two levels up.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

require_once APP_PATH . '/Kernel/Autoloader.php';
```

- [ ] **Step 2: Write the micro test harness**

Create `tests/lib/microtest.php`:

```php
<?php

declare(strict_types=1);

$GLOBALS['__mt'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

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
    }
}

function assert_true(bool $cond, string $msg = 'expected true'): void
{
    if (!$cond) {
        throw new \RuntimeException($msg);
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $prefix = $msg !== '' ? $msg . ': ' : '';
        throw new \RuntimeException($prefix . 'expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}

function assert_null(mixed $actual, string $msg = 'expected null'): void
{
    if ($actual !== null) {
        throw new \RuntimeException($msg . ' got ' . var_export($actual, true));
    }
}

function assert_throws(string $exceptionClass, callable $fn, string $msg = ''): void
{
    $prefix = $msg !== '' ? $msg . ': ' : '';
    try {
        $fn();
    } catch (\Throwable $e) {
        if (!($e instanceof $exceptionClass)) {
            throw new \RuntimeException($prefix . "expected {$exceptionClass} got " . get_class($e) . ' (' . $e->getMessage() . ')');
        }
        return;
    }
    throw new \RuntimeException($prefix . "expected {$exceptionClass} to be thrown, nothing thrown");
}

function microtest_summary(): void
{
    $mt = $GLOBALS['__mt'];
    fwrite(STDOUT, "\n{$mt['pass']} passed, {$mt['fail']} failed\n");
    exit($mt['fail'] > 0 ? 1 : 0);
}
```

- [ ] **Step 3: Write the runner**

Create `tests/run.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/microtest.php';

$filter = $argv[1] ?? null;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($iterator as $entry) {
    if (!$entry->isFile()) {
        continue;
    }
    $path = $entry->getPathname();
    if (!str_ends_with($path, 'Test.php')) {
        continue;
    }
    if ($filter !== null && !str_contains(str_replace('\\', '/', $path), $filter)) {
        continue;
    }
    $files[] = $path;
}

sort($files);
foreach ($files as $file) {
    require $file;
}

microtest_summary();
```

- [ ] **Step 4: Write a self-test to prove the harness runs**

Create `tests/HarnessSelfTest.php`:

```php
<?php

declare(strict_types=1);

test('harness: assert_same works', function (): void {
    assert_same(2, 1 + 1);
});

test('harness: assert_throws catches the expected type', function (): void {
    assert_throws(\InvalidArgumentException::class, function (): void {
        throw new \InvalidArgumentException('boom');
    });
});
```

- [ ] **Step 5: Run the suite**

Run: `php tests/run.php`
Expected: two `PASS` lines and final line `2 passed, 0 failed`, exit code 0.

- [ ] **Step 6: Commit**

```bash
git add tests/lib/bootstrap.php tests/lib/microtest.php tests/run.php tests/HarnessSelfTest.php
git commit -m "test: add zero-dependency PHP test harness for CRUD engine"
```

---

### Task 2: `CrudContext` base

**Files:**
- Create: `app/Application/Crud/Context/CrudContext.php`
- Test: `tests/Crud/Context/CrudContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Context/CrudContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;

test('CrudContext: exposes identity getters', function (): void {
    $ctx = new CrudContext('eventos', 'dom_eventos', 'id', 7, '127.0.0.1');
    assert_same('eventos', $ctx->resourceKey());
    assert_same('dom_eventos', $ctx->table());
    assert_same('id', $ctx->primaryKey());
    assert_same(7, $ctx->userId());
    assert_same('127.0.0.1', $ctx->ip());
});

test('CrudContext: userId may be null (anonymous/system)', function (): void {
    $ctx = new CrudContext('eventos', 'dom_eventos', 'id', null, '');
    assert_null($ctx->userId());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudContextTest`
Expected: FAIL — `Class "App\Application\Crud\Context\CrudContext" not found`.

- [ ] **Step 3: Implement the base context**

Create `app/Application/Crud/Context/CrudContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Base inmutable de los contextos del CRUD Engine.
 * Transporta la identidad común del recurso y del actor.
 */
class CrudContext
{
    public function __construct(
        protected readonly string $resourceKey,
        protected readonly string $table,
        protected readonly string $primaryKey,
        protected readonly ?int $userId,
        protected readonly string $ip
    ) {}

    public function resourceKey(): string
    {
        return $this->resourceKey;
    }

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function ip(): string
    {
        return $this->ip;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudContextTest`
Expected: `2 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Context/CrudContext.php tests/Crud/Context/CrudContextTest.php
git commit -m "feat(crud): add CrudContext base for typed hook contexts"
```

---

### Task 3: `CrudWriteContext` (create/update/delete + read-back API)

**Files:**
- Create: `app/Application/Crud/Context/CrudWriteContext.php`
- Test: `tests/Crud/Context/CrudWriteContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Context/CrudWriteContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudWriteContext;

function make_write_ctx(array $data = ['nombre' => 'Ana'], bool $isCreate = true): CrudWriteContext
{
    return new CrudWriteContext(
        'clientes',
        'dom_clientes',
        'id',
        42,
        '127.0.0.1',
        ['nombre' => 'Ana'],   // input
        null,                  // record
        null,                  // recordId
        $data,                 // data
        $isCreate              // isCreate
    );
}

test('CrudWriteContext: is a CrudContext and exposes write state', function (): void {
    $ctx = make_write_ctx();
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same(['nombre' => 'Ana'], $ctx->input());
    assert_null($ctx->record());
    assert_null($ctx->recordId());
    assert_true($ctx->isCreate());
    assert_same(['nombre' => 'Ana'], $ctx->data());
});

test('CrudWriteContext: setData replaces the whole payload', function (): void {
    $ctx = make_write_ctx();
    $ctx->setData(['nombre' => 'Ana', 'slug' => 'ana']);
    assert_same(['nombre' => 'Ana', 'slug' => 'ana'], $ctx->data());
});

test('CrudWriteContext: mergeData patches keys', function (): void {
    $ctx = make_write_ctx();
    $ctx->mergeData(['slug' => 'ana', 'nombre' => 'Ana M.']);
    assert_same(['nombre' => 'Ana M.', 'slug' => 'ana'], $ctx->data());
});

test('CrudWriteContext: set writes a single key', function (): void {
    $ctx = make_write_ctx();
    $ctx->set('slug', 'ana');
    assert_same('ana', $ctx->data()['slug']);
});

test('CrudWriteContext: setRecordId is used after insert', function (): void {
    $ctx = make_write_ctx();
    $ctx->setRecordId(99);
    assert_same(99, $ctx->recordId());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudWriteContextTest`
Expected: FAIL — `Class "App\Application\Crud\Context\CrudWriteContext" not found`.

- [ ] **Step 3: Implement the context**

Create `app/Application/Crud/Context/CrudWriteContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto de escritura (create/update/delete).
 * `input` y `record` son de solo lectura; `data` es mutable y el motor lo
 * relee tras beforeCreate/beforeUpdate para persistir las mutaciones del handler.
 */
final class CrudWriteContext extends CrudContext
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $input  Entrada cruda del usuario (solo lectura).
     * @param array<string, mixed>|null $record  Fila existente (update/delete) o null (create).
     * @param array<string, mixed> $data  Payload a persistir (mutable).
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly array $input,
        private readonly ?array $record,
        private ?int $recordId,
        array $data,
        private readonly bool $isCreate
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
        $this->data = $data;
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function recordId(): ?int
    {
        return $this->recordId;
    }

    public function setRecordId(?int $recordId): void
    {
        $this->recordId = $recordId;
    }

    public function isCreate(): bool
    {
        return $this->isCreate;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /** @param array<string, mixed> $patch */
    public function mergeData(array $patch): void
    {
        $this->data = array_merge($this->data, $patch);
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudWriteContextTest`
Expected: `5 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Context/CrudWriteContext.php tests/Crud/Context/CrudWriteContextTest.php
git commit -m "feat(crud): add CrudWriteContext with mutable data read-back API"
```

---

### Task 4: `CrudActionContext`

**Files:**
- Create: `app/Application/Crud/Context/CrudActionContext.php`
- Test: `tests/Crud/Context/CrudActionContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Context/CrudActionContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Context\CrudContext;

test('CrudActionContext: exposes action target', function (): void {
    $record = ['id' => 5, 'status' => 'pendiente'];
    $ctx = new CrudActionContext(
        'eventos',
        'dom_eventos',
        'id',
        3,
        '10.0.0.1',
        5,
        $record,
        'autorizar',
        ['nota' => 'ok']
    );
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same(5, $ctx->recordId());
    assert_same($record, $ctx->record());
    assert_same('autorizar', $ctx->action());
    assert_same(['nota' => 'ok'], $ctx->input());
});

test('CrudActionContext: record may be null', function (): void {
    $ctx = new CrudActionContext('eventos', 'dom_eventos', 'id', 3, '', 5, null, 'x', []);
    assert_null($ctx->record());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudActionContextTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the context**

Create `app/Application/Crud/Context/CrudActionContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto de una acción de fila (handler/transition/link tipo handler).
 */
final class CrudActionContext extends CrudContext
{
    /**
     * @param array<string, mixed>|null $record
     * @param array<string, mixed> $input
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly int $recordId,
        private readonly ?array $record,
        private readonly string $action,
        private readonly array $input
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    public function recordId(): int
    {
        return $this->recordId;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function action(): string
    {
        return $this->action;
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudActionContextTest`
Expected: `2 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Context/CrudActionContext.php tests/Crud/Context/CrudActionContextTest.php
git commit -m "feat(crud): add CrudActionContext"
```

---

### Task 5: `CrudTransitionContext`

**Files:**
- Create: `app/Application/Crud/Context/CrudTransitionContext.php`
- Test: `tests/Crud/Context/CrudTransitionContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Context/CrudTransitionContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudTransitionContext;

test('CrudTransitionContext: exposes from/to and status column', function (): void {
    $record = ['id' => 1, 'status' => 'pendiente'];
    $ctx = new CrudTransitionContext(
        'eventos',
        'dom_eventos',
        'id',
        9,
        '::1',
        $record,
        'status',
        'pendiente',
        'autorizado',
        ['motivo' => 'aprobado']
    );
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same($record, $ctx->record());
    assert_same('status', $ctx->statusColumn());
    assert_same('pendiente', $ctx->from());
    assert_same('autorizado', $ctx->to());
    assert_same(['motivo' => 'aprobado'], $ctx->input());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudTransitionContextTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the context**

Create `app/Application/Crud/Context/CrudTransitionContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto de una transición de estado.
 * Un guard puede lanzar para bloquear la transición.
 */
final class CrudTransitionContext extends CrudContext
{
    /**
     * @param array<string, mixed>|null $record
     * @param array<string, mixed> $input
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly ?array $record,
        private readonly string $statusColumn,
        private readonly string $from,
        private readonly string $to,
        private readonly array $input
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function statusColumn(): string
    {
        return $this->statusColumn;
    }

    public function from(): string
    {
        return $this->from;
    }

    public function to(): string
    {
        return $this->to;
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudTransitionContextTest`
Expected: `1 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Context/CrudTransitionContext.php tests/Crud/Context/CrudTransitionContextTest.php
git commit -m "feat(crud): add CrudTransitionContext"
```

---

### Task 6: `CrudValidationContext`

**Files:**
- Create: `app/Application/Crud/Context/CrudValidationContext.php`
- Test: `tests/Crud/Context/CrudValidationContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Context/CrudValidationContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudValidationContext;

function make_validation_ctx(bool $isEdit = false): CrudValidationContext
{
    return new CrudValidationContext(
        'eventos',
        'dom_eventos',
        'id',
        1,
        '',
        ['monto' => '100'],          // input
        ['monto' => 100],            // normalized
        $isEdit ? ['id' => 5] : null, // record
        $isEdit
    );
}

test('CrudValidationContext: starts with no errors', function (): void {
    $ctx = make_validation_ctx();
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_true(!$ctx->hasErrors(), 'should start clean');
    assert_same([], $ctx->errors());
    assert_same(['monto' => '100'], $ctx->input());
    assert_same(['monto' => 100], $ctx->normalized());
    assert_null($ctx->record());
    assert_true(!$ctx->isEdit());
});

test('CrudValidationContext: addError accumulates per field', function (): void {
    $ctx = make_validation_ctx();
    $ctx->addError('monto', 'Muy bajo');
    $ctx->addError('monto', 'Debe ser positivo');
    $ctx->addError('fecha', 'Requerida');
    assert_true($ctx->hasErrors());
    assert_same(
        ['monto' => ['Muy bajo', 'Debe ser positivo'], 'fecha' => ['Requerida']],
        $ctx->errors()
    );
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudValidationContextTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the context**

Create `app/Application/Crud/Context/CrudValidationContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto para validadores de formulario externos (CrudValidatorInterface).
 * Los errores se acumulan y luego el motor los convierte en ValidationException.
 */
final class CrudValidationContext extends CrudContext
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $normalized
     * @param array<string, mixed>|null $record
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly array $input,
        private readonly array $normalized,
        private readonly ?array $record,
        private readonly bool $isEdit
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    /** @return array<string, mixed> */
    public function normalized(): array
    {
        return $this->normalized;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function isEdit(): bool
    {
        return $this->isEdit;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudValidationContextTest`
Expected: `2 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Context/CrudValidationContext.php tests/Crud/Context/CrudValidationContextTest.php
git commit -m "feat(crud): add CrudValidationContext with error collector"
```

---

### Task 7: `CrudListContext` (op whitelist)

**Files:**
- Create: `app/Application/Crud/Context/CrudListContext.php`
- Test: `tests/Crud/Context/CrudListContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Context/CrudListContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudListContext;

function make_list_ctx(): CrudListContext
{
    return new CrudListContext('eventos', 'dom_eventos', 'id', 1, '', ['buscar' => 'x']);
}

test('CrudListContext: exposes query and starts with no conditions', function (): void {
    $ctx = make_list_ctx();
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same(['buscar' => 'x'], $ctx->query());
    assert_same([], $ctx->conditions());
});

test('CrudListContext: addCondition records normalized op', function (): void {
    $ctx = make_list_ctx();
    $ctx->addCondition('status', 'like', '%a%');
    $ctx->addCondition('monto', '>=', 100);
    assert_same(
        [
            ['column' => 'status', 'op' => 'LIKE', 'value' => '%a%'],
            ['column' => 'monto', 'op' => '>=', 'value' => 100],
        ],
        $ctx->conditions()
    );
});

test('CrudListContext: addCondition rejects ops outside the whitelist', function (): void {
    $ctx = make_list_ctx();
    assert_throws(\InvalidArgumentException::class, function () use ($ctx): void {
        $ctx->addCondition('status', 'OR 1=1', 'x');
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudListContextTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the context**

Create `app/Application/Crud/Context/CrudListContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto para scopes de listado (CrudListScopeInterface).
 * Las condiciones se acumulan estructuradas; el motor arma SQL con
 * quoteIdentifier + params. Solo se aceptan operadores en whitelist.
 */
final class CrudListContext extends CrudContext
{
    private const ALLOWED_OPS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN'];

    /** @var list<array{column: string, op: string, value: mixed}> */
    private array $conditions = [];

    /** @param array<string, mixed> $query */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly array $query
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    public function addCondition(string $column, string $op, mixed $value): void
    {
        $normalizedOp = strtoupper(trim($op));
        if (!in_array($normalizedOp, self::ALLOWED_OPS, true)) {
            throw new \InvalidArgumentException("Operador de condición no permitido: {$op}");
        }
        $this->conditions[] = ['column' => $column, 'op' => $normalizedOp, 'value' => $value];
    }

    /** @return list<array{column: string, op: string, value: mixed}> */
    public function conditions(): array
    {
        return $this->conditions;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudListContextTest`
Expected: `3 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Context/CrudListContext.php tests/Crud/Context/CrudListContextTest.php
git commit -m "feat(crud): add CrudListContext with op whitelist"
```

---

### Task 8: `CrudFormContext`

**Files:**
- Create: `app/Application/Crud/Context/CrudFormContext.php`
- Test: `tests/Crud/Context/CrudFormContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/Context/CrudFormContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudFormContext;

test('CrudFormContext: exposes edit flag and record', function (): void {
    $ctx = new CrudFormContext('eventos', 'dom_eventos', 'id', 1, '', true, ['id' => 5]);
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_true($ctx->isEdit());
    assert_same(['id' => 5], $ctx->record());
    assert_same([], $ctx->fieldOptions());
    assert_same([], $ctx->fieldValues());
});

test('CrudFormContext: collects option and value overrides', function (): void {
    $ctx = new CrudFormContext('eventos', 'dom_eventos', 'id', 1, '', false, null);
    $ctx->setFieldOptions('categoria_id', [['value' => 1, 'label' => 'A']]);
    $ctx->setFieldValue('codigo', 'EV-001');
    assert_same(['categoria_id' => [['value' => 1, 'label' => 'A']]], $ctx->fieldOptions());
    assert_same(['codigo' => 'EV-001'], $ctx->fieldValues());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudFormContextTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the context**

Create `app/Application/Crud/Context/CrudFormContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto para el hook beforeRenderForm: permite a un handler sembrar
 * opciones de campos y valores por defecto antes de renderizar el formulario.
 */
final class CrudFormContext extends CrudContext
{
    /** @var array<string, mixed> */
    private array $fieldOptions = [];

    /** @var array<string, mixed> */
    private array $fieldValues = [];

    /** @param array<string, mixed>|null $record */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly bool $isEdit,
        private readonly ?array $record
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    public function isEdit(): bool
    {
        return $this->isEdit;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    /** @param array<int, mixed> $options */
    public function setFieldOptions(string $field, array $options): void
    {
        $this->fieldOptions[$field] = $options;
    }

    public function setFieldValue(string $field, mixed $value): void
    {
        $this->fieldValues[$field] = $value;
    }

    /** @return array<string, mixed> */
    public function fieldOptions(): array
    {
        return $this->fieldOptions;
    }

    /** @return array<string, mixed> */
    public function fieldValues(): array
    {
        return $this->fieldValues;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudFormContextTest`
Expected: `2 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Context/CrudFormContext.php tests/Crud/Context/CrudFormContextTest.php
git commit -m "feat(crud): add CrudFormContext"
```

---

### Task 9: Segregated handler interfaces (Domain)

**Files:**
- Create: `app/Domain/Interfaces/CrudActionHandlerInterface.php`
- Create: `app/Domain/Interfaces/CrudTransitionGuardInterface.php`
- Create: `app/Domain/Interfaces/CrudValidatorInterface.php`
- Create: `app/Domain/Interfaces/CrudListScopeInterface.php`
- Test: `tests/Crud/SegregatedInterfacesTest.php`

> Layering note: these Domain interfaces import Application context classes per the approved spec (see "Architectural note" at top).

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/SegregatedInterfacesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Context\CrudListContext;
use App\Application\Crud\Context\CrudTransitionContext;
use App\Application\Crud\Context\CrudValidationContext;
use App\Domain\Interfaces\CrudActionHandlerInterface;
use App\Domain\Interfaces\CrudListScopeInterface;
use App\Domain\Interfaces\CrudTransitionGuardInterface;
use App\Domain\Interfaces\CrudValidatorInterface;

test('segregated interfaces: a class can implement all four', function (): void {
    $impl = new class implements
        CrudActionHandlerInterface,
        CrudTransitionGuardInterface,
        CrudValidatorInterface,
        CrudListScopeInterface {
        public bool $called = false;
        public function handle(CrudActionContext $ctx): void { $this->called = true; }
        public function authorize(CrudTransitionContext $ctx): void {}
        public function validate(CrudValidationContext $ctx): void {}
        public function apply(CrudListContext $ctx): void {}
    };

    assert_true($impl instanceof CrudActionHandlerInterface);
    assert_true($impl instanceof CrudTransitionGuardInterface);
    assert_true($impl instanceof CrudValidatorInterface);
    assert_true($impl instanceof CrudListScopeInterface);

    $impl->handle(new CrudActionContext('r', 't', 'id', 1, '', 1, null, 'a', []));
    assert_true($impl->called, 'handle ran');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php SegregatedInterfacesTest`
Expected: FAIL — `Interface "App\Domain\Interfaces\CrudActionHandlerInterface" not found`.

- [ ] **Step 3: Implement the four interfaces**

Create `app/Domain/Interfaces/CrudActionHandlerInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudActionContext;

interface CrudActionHandlerInterface
{
    public function handle(CrudActionContext $ctx): void;
}
```

Create `app/Domain/Interfaces/CrudTransitionGuardInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudTransitionContext;

interface CrudTransitionGuardInterface
{
    /** Lanzar una excepción bloquea la transición. */
    public function authorize(CrudTransitionContext $ctx): void;
}
```

Create `app/Domain/Interfaces/CrudValidatorInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudValidationContext;

interface CrudValidatorInterface
{
    /** Agrega errores vía $ctx->addError(); no lanza por errores de validación. */
    public function validate(CrudValidationContext $ctx): void;
}
```

Create `app/Domain/Interfaces/CrudListScopeInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudListContext;

interface CrudListScopeInterface
{
    public function apply(CrudListContext $ctx): void;
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php SegregatedInterfacesTest`
Expected: `1 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Interfaces/CrudActionHandlerInterface.php app/Domain/Interfaces/CrudTransitionGuardInterface.php app/Domain/Interfaces/CrudValidatorInterface.php app/Domain/Interfaces/CrudListScopeInterface.php tests/Crud/SegregatedInterfacesTest.php
git commit -m "feat(crud): add segregated handler interfaces (action/guard/validator/scope)"
```

---

### Task 10: Retype `CrudHookHandlerInterface` + `AbstractCrudHookHandler`

**Files:**
- Modify: `app/Domain/Interfaces/CrudHookHandlerInterface.php` (full rewrite)
- Modify: `app/Application/Crud/Handlers/AbstractCrudHookHandler.php` (full rewrite)
- Test: `tests/Crud/AbstractCrudHookHandlerTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/AbstractCrudHookHandlerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudWriteContext;
use App\Application\Crud\Handlers\AbstractCrudHookHandler;
use App\Domain\Interfaces\CrudHookHandlerInterface;

test('AbstractCrudHookHandler: is a CrudHookHandlerInterface with no-op typed hooks', function (): void {
    $handler = new class extends AbstractCrudHookHandler {};
    assert_true($handler instanceof CrudHookHandlerInterface);

    $ctx = new CrudWriteContext('r', 't', 'id', 1, '', [], null, null, ['a' => 1], true);
    // No-ops must not throw and must not mutate data.
    $handler->beforeCreate($ctx);
    $handler->afterCreate($ctx);
    $handler->beforeUpdate($ctx);
    $handler->afterUpdate($ctx);
    $handler->beforeDelete($ctx);
    $handler->afterDelete($ctx);
    assert_same(['a' => 1], $ctx->data());
});

test('AbstractCrudHookHandler: subclass can override beforeCreate to mutate data', function (): void {
    $handler = new class extends AbstractCrudHookHandler {
        public function beforeCreate(CrudWriteContext $ctx): void
        {
            $ctx->set('slug', 'generated');
        }
    };
    $ctx = new CrudWriteContext('r', 't', 'id', 1, '', [], null, null, ['nombre' => 'X'], true);
    $handler->beforeCreate($ctx);
    assert_same('generated', $ctx->data()['slug']);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php AbstractCrudHookHandlerTest`
Expected: FAIL — `TypeError`/`Fatal error`: the current `AbstractCrudHookHandler::beforeCreate` does not exist (only `beforeStore(array $payload)` exists), so calling `$handler->beforeCreate($ctx)` errors.

- [ ] **Step 3: Rewrite the interface**

Replace the entire contents of `app/Domain/Interfaces/CrudHookHandlerInterface.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudWriteContext;

/**
 * Contrato para hooks de escritura del CRUD Engine.
 * Las implementaciones extienden AbstractCrudHookHandler y sobrescriben solo
 * lo necesario. Vocabulario canónico: Create/Update/Delete.
 *
 * Eventos extendidos opcionales (NO en la interfaz; el runner los invoca por
 * method_exists si el handler los define):
 *   beforeTransition/afterTransition(CrudTransitionContext)
 *   beforeRenderForm(CrudFormContext)
 *   beforeListQuery(CrudListContext)
 *   afterUpload(CrudWriteContext)
 */
interface CrudHookHandlerInterface
{
    public function beforeCreate(CrudWriteContext $ctx): void;

    public function afterCreate(CrudWriteContext $ctx): void;

    public function beforeUpdate(CrudWriteContext $ctx): void;

    public function afterUpdate(CrudWriteContext $ctx): void;

    public function beforeDelete(CrudWriteContext $ctx): void;

    public function afterDelete(CrudWriteContext $ctx): void;
}
```

- [ ] **Step 4: Rewrite the abstract handler**

Replace the entire contents of `app/Application/Crud/Handlers/AbstractCrudHookHandler.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudFormContext;
use App\Application\Crud\Context\CrudListContext;
use App\Application\Crud\Context\CrudTransitionContext;
use App\Application\Crud\Context\CrudWriteContext;
use App\Domain\Interfaces\CrudHookHandlerInterface;

/**
 * Base no-op para handlers de hooks. Provee también los eventos extendidos
 * opcionales como no-op para que las subclases puedan sobrescribirlos.
 */
abstract class AbstractCrudHookHandler implements CrudHookHandlerInterface
{
    public function beforeCreate(CrudWriteContext $ctx): void {}

    public function afterCreate(CrudWriteContext $ctx): void {}

    public function beforeUpdate(CrudWriteContext $ctx): void {}

    public function afterUpdate(CrudWriteContext $ctx): void {}

    public function beforeDelete(CrudWriteContext $ctx): void {}

    public function afterDelete(CrudWriteContext $ctx): void {}

    // --- Eventos extendidos opcionales (no forman parte de la interfaz) ---

    public function beforeTransition(CrudTransitionContext $ctx): void {}

    public function afterTransition(CrudTransitionContext $ctx): void {}

    public function beforeRenderForm(CrudFormContext $ctx): void {}

    public function beforeListQuery(CrudListContext $ctx): void {}

    public function afterUpload(CrudWriteContext $ctx): void {}
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `php tests/run.php AbstractCrudHookHandlerTest`
Expected: `2 passed, 0 failed`.

> Note: `CrudConfigValidator` and `CrudHandlerRegistry` still reference `CrudHookHandlerInterface` by name only (instanceof / is_subclass_of) — those continue to compile. `CrudHookRunner` and `CrudDataService` still use the OLD array call style at this point; they are updated in Tasks 12 and 14. The suite stays green because nothing executes the runner yet in tests.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Interfaces/CrudHookHandlerInterface.php app/Application/Crud/Handlers/AbstractCrudHookHandler.php tests/Crud/AbstractCrudHookHandlerTest.php
git commit -m "refactor(crud): retype hook handler interface to typed contexts"
```

---

### Task 11: `CrudHandlerRegistry::resolve(key, expectedInterface)`

**Files:**
- Modify: `app/Application/Services/CrudHandlerRegistry.php:33-46`
- Create: `tests/fixtures/hook_handlers.php`
- Test: `tests/Crud/CrudHandlerRegistryTest.php`

- [ ] **Step 1: Add test fixtures**

Create `tests/fixtures/hook_handlers.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Context\CrudWriteContext;
use App\Application\Crud\Handlers\AbstractCrudHookHandler;
use App\Domain\Interfaces\CrudActionHandlerInterface;

if (!class_exists('Tests\\Fixtures\\MutatingHookHandler')) {
    /** Hook handler que muta data en beforeCreate (prueba del read-back). */
    class MutatingHookHandler extends AbstractCrudHookHandler
    {
        public function beforeCreate(CrudWriteContext $ctx): void
        {
            $ctx->set('slug', 'from-hook');
        }
    }

    /** Hook handler cuyo beforeUpdate lanza (prueba de abort). */
    class ThrowingHookHandler extends AbstractCrudHookHandler
    {
        public function beforeUpdate(CrudWriteContext $ctx): void
        {
            throw new \RuntimeException('abort from hook');
        }
    }

    /** Handler legacy: implementa la interfaz nueva (vía abstract) pero además
     *  define el método legacy beforeStore para probar el dispatch de alias. */
    class LegacyAliasHookHandler extends AbstractCrudHookHandler
    {
        public function beforeStore(CrudWriteContext $ctx): void
        {
            $ctx->set('legacy', 'yes');
        }
    }

    /** Solo implementa la interfaz de acción, NO la de hooks. */
    class ActionOnlyHandler implements CrudActionHandlerInterface
    {
        public function handle(CrudActionContext $ctx): void {}
    }
}
```

> The classes are global (no namespace) but reference real `App\` types. They are guarded with `class_exists` so re-`require` across the suite never redeclares.

- [ ] **Step 2: Write the failing test**

Create `tests/Crud/CrudHandlerRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudHandlerRegistry;
use App\Domain\Interfaces\CrudActionHandlerInterface;
use App\Domain\Interfaces\CrudHookHandlerInterface;

require_once dirname(__DIR__) . '/fixtures/hook_handlers.php';

test('CrudHandlerRegistry::resolve returns instance when interface matches', function (): void {
    $registry = new CrudHandlerRegistry(['mutator' => MutatingHookHandler::class]);
    $handler = $registry->resolve('mutator', CrudHookHandlerInterface::class);
    assert_true($handler instanceof CrudHookHandlerInterface);
});

test('CrudHandlerRegistry::resolve defaults to the hook interface', function (): void {
    $registry = new CrudHandlerRegistry(['mutator' => MutatingHookHandler::class]);
    $handler = $registry->resolve('mutator');
    assert_true($handler instanceof CrudHookHandlerInterface);
});

test('CrudHandlerRegistry::resolve returns null for unknown key', function (): void {
    $registry = new CrudHandlerRegistry([]);
    assert_null($registry->resolve('nope'));
    assert_null($registry->resolve(null));
});

test('CrudHandlerRegistry::resolve rejects a class that does not implement the expected interface', function (): void {
    $registry = new CrudHandlerRegistry(['act' => ActionOnlyHandler::class]);
    assert_throws(\RuntimeException::class, function () use ($registry): void {
        $registry->resolve('act', CrudHookHandlerInterface::class);
    });
});

test('CrudHandlerRegistry::resolve accepts the same class for a different expected interface', function (): void {
    $registry = new CrudHandlerRegistry(['act' => ActionOnlyHandler::class]);
    $handler = $registry->resolve('act', CrudActionHandlerInterface::class);
    assert_true($handler instanceof CrudActionHandlerInterface);
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `php tests/run.php CrudHandlerRegistryTest`
Expected: FAIL — the current `resolve(?string $key)` ignores `$expectedInterface` (too few args is fine in PHP only if defaulted; here calling `resolve('act', CrudActionHandlerInterface::class)` errors with `Too many arguments` OR the interface check is missing). Either way the new assertions fail.

- [ ] **Step 4: Implement the new resolve**

In `app/Application/Services/CrudHandlerRegistry.php`, replace the existing `resolve` method (lines 33-46) with:

```php
    /**
     * Resuelve la clave a una instancia y valida que implemente la interfaz
     * esperada. Devuelve null si la clave no está registrada.
     *
     * @param class-string $expectedInterface
     */
    public function resolve(?string $key, string $expectedInterface = CrudHookHandlerInterface::class): ?object
    {
        $class = $this->classForKey($key);
        if ($class === null) {
            return null;
        }

        $instance = new $class();
        if (!$instance instanceof $expectedInterface) {
            throw new \RuntimeException("El handler '{$key}' ({$class}) no implementa {$expectedInterface}.");
        }

        return $instance;
    }
```

(The `use App\Domain\Interfaces\CrudHookHandlerInterface;` import at the top of the file is already present and stays.)

- [ ] **Step 5: Run to verify it passes**

Run: `php tests/run.php CrudHandlerRegistryTest`
Expected: `5 passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudHandlerRegistry.php tests/fixtures/hook_handlers.php tests/Crud/CrudHandlerRegistryTest.php
git commit -m "feat(crud): type-checked CrudHandlerRegistry::resolve(key, interface)"
```

---

### Task 12: `CrudHookRunner` — context object + canonical/legacy dispatch + read-back

**Files:**
- Modify: `app/Application/Services/CrudHookRunner.php` (rewrite `run` signature/body)
- Test: `tests/Crud/CrudHookRunnerTest.php`

**Design decision (documented):** dispatch the canonical method when present; additionally dispatch the legacy alias (`beforeCreate→beforeStore`, `afterCreate→afterStore`) **only if the handler explicitly defines it**. Because `AbstractCrudHookHandler` no longer declares `beforeStore/afterStore`, abstract-based handlers never double-fire. The context is an object passed by handle, so a handler's mutations are visible to the caller after `run()` returns (this is the read-back mechanism that `CrudDataService` relies on in Task 14).

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/CrudHookRunnerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudWriteContext;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudHookRunner;
use App\Domain\Entities\CrudResourceDefinition;

require_once dirname(__DIR__) . '/fixtures/hook_handlers.php';

function runner_definition(?string $handlerKey): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id'],
        'hooks' => $handlerKey !== null ? ['handler' => $handlerKey] : [],
    ]);
}

function runner_write_ctx(array $data): CrudWriteContext
{
    return new CrudWriteContext('clientes', 'dom_clientes', 'id', 1, '', [], null, null, $data, true);
}

test('CrudHookRunner: handler mutation is visible after run (read-back mechanism)', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry(['mutator' => MutatingHookHandler::class]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition('mutator'), 'beforeCreate', $ctx);
    assert_same('from-hook', $ctx->data()['slug']);
});

test('CrudHookRunner: no handler configured is a safe no-op', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry([]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition(null), 'beforeCreate', $ctx);
    assert_same(['nombre' => 'Ana'], $ctx->data());
});

test('CrudHookRunner: unknown handler key is a safe no-op', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry([]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition('ghost'), 'beforeCreate', $ctx);
    assert_same(['nombre' => 'Ana'], $ctx->data());
});

test('CrudHookRunner: an exception in a hook aborts (rethrows)', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry(['boom' => ThrowingHookHandler::class]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    assert_throws(\RuntimeException::class, function () use ($runner, $ctx): void {
        $runner->run(runner_definition('boom'), 'beforeUpdate', $ctx);
    });
});

test('CrudHookRunner: legacy beforeStore alias fires on beforeCreate when defined', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry(['legacy' => LegacyAliasHookHandler::class]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition('legacy'), 'beforeCreate', $ctx);
    assert_same('yes', $ctx->data()['legacy']);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudHookRunnerTest`
Expected: FAIL — current `run(..., array $payload = [])` type-errors when passed a `CrudWriteContext` object (`array` expected), and there is no read-back.

- [ ] **Step 3: Rewrite the runner**

Replace the entire contents of `app/Application/Services/CrudHookRunner.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\CrudHookHandlerInterface;
use App\Kernel\Logging\AppLogger;

final class CrudHookRunner
{
    /**
     * Mapeo de eventos canónicos a sus alias legacy. El alias solo se dispara
     * si el handler lo define explícitamente (las clases que extienden
     * AbstractCrudHookHandler no lo definen, así que no hay doble ejecución).
     */
    private const LEGACY_ALIASES = [
        'beforeCreate' => 'beforeStore',
        'afterCreate'  => 'afterStore',
    ];

    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry
    ) {}

    /**
     * Ejecuta el hook pasando el contexto por objeto (referencia de handle).
     * Las mutaciones del handler sobre el contexto son visibles al volver.
     */
    public function run(CrudResourceDefinition $definition, string $hookMethod, object $context): void
    {
        $handlerKey = $definition->hookHandler();
        if ($handlerKey === null || $handlerKey === '') {
            return;
        }

        try {
            $handler = $this->handlerRegistry->resolve($handlerKey, CrudHookHandlerInterface::class);
        } catch (\Throwable $e) {
            AppLogger::error('CRUD hook: error al resolver handler', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($handler === null) {
            AppLogger::warning('CRUD hook: handler no registrado en whitelist', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
            ]);
            return;
        }

        $methods = [$hookMethod];
        if (isset(self::LEGACY_ALIASES[$hookMethod])) {
            $methods[] = self::LEGACY_ALIASES[$hookMethod];
        }

        $invoked = false;
        foreach (array_unique($methods) as $method) {
            if (!method_exists($handler, $method)) {
                continue;
            }
            $invoked = true;
            try {
                $handler->{$method}($context);
            } catch (\Throwable $e) {
                AppLogger::error('CRUD hook: excepción en ejecución', [
                    'resource' => $definition->key(),
                    'handlerKey' => $handlerKey,
                    'hook' => $method,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        if (!$invoked) {
            AppLogger::warning('CRUD hook: método no implementado en handler', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
            ]);
        }
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudHookRunnerTest`
Expected: `5 passed, 0 failed`.

- [ ] **Step 5: Run the full suite (nothing else should break)**

Run: `php tests/run.php`
Expected: all tests pass, final `N passed, 0 failed`.

> `CrudDataService` still passes arrays to `run()` at this point. PHP will accept arrays for the `object $context` param? No — it will not. **Do not run the app between Task 12 and Task 14.** The unit suite does not exercise `CrudDataService`, so it stays green; the live create/update/delete paths are fixed in Task 14 (next). This ordering is intentional and the two tasks should land together if deploying.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudHookRunner.php tests/Crud/CrudHookRunnerTest.php
git commit -m "refactor(crud): hook runner passes typed context, fixes mutation read-back"
```

---

### Task 13: `CrudConfigValidator` — new-block shape scaffolding

**Files:**
- Modify: `app/Application/Services/CrudConfigValidator.php` (add static method + wire into `validate`)
- Test: `tests/Crud/CrudConfigValidatorShapeTest.php`

> The full validator (`validate()`) needs a DB connection (final `GenericCrudRepository`), so it is not unit-testable here. We add a **pure static** `newBlockShapeErrors(array $config): array` that only checks top-level shapes of the new optional blocks, and call it from `validate()`. The static method is fully unit-testable with no DB. Deep validation of each block lands in later phases.

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/CrudConfigValidatorShapeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('newBlockShapeErrors: empty config has no new-block errors', function (): void {
    assert_same([], CrudConfigValidator::newBlockShapeErrors([]));
});

test('newBlockShapeErrors: well-formed optional blocks pass', function (): void {
    $config = [
        'actions' => ['row' => [], 'bulk' => []],
        'states' => ['column' => 'status', 'values' => [], 'transitions' => []],
        'relations' => [],
        'detail' => ['tabs' => []],
        'form' => ['validators' => ['anticipo_minimo']],
    ];
    assert_same([], CrudConfigValidator::newBlockShapeErrors($config));
});

test('newBlockShapeErrors: wrong types are reported', function (): void {
    $errors = CrudConfigValidator::newBlockShapeErrors([
        'actions' => 'nope',
        'states' => 'nope',
        'relations' => 5,
        'detail' => 'nope',
        'form' => ['validators' => 'nope'],
    ]);
    assert_same(5, count($errors));
});

test('newBlockShapeErrors: states requires a column when present', function (): void {
    $errors = CrudConfigValidator::newBlockShapeErrors([
        'states' => ['values' => [], 'transitions' => []],
    ]);
    assert_same(['states.column es obligatorio cuando se define el bloque states.'], $errors);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php CrudConfigValidatorShapeTest`
Expected: FAIL — `Call to undefined method App\Application\Services\CrudConfigValidator::newBlockShapeErrors()`.

- [ ] **Step 3: Add the static method and wire it in**

In `app/Application/Services/CrudConfigValidator.php`, add this method to the class (e.g. immediately before the closing `}` of the class):

```php
    /**
     * Scaffolding Fase 0: valida solo la forma de nivel superior de los bloques
     * opcionales nuevos. La validación profunda se agrega en fases posteriores.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function newBlockShapeErrors(array $config): array
    {
        $errors = [];

        if (array_key_exists('actions', $config) && !is_array($config['actions'])) {
            $errors[] = 'actions debe ser un objeto.';
        }

        if (array_key_exists('states', $config)) {
            if (!is_array($config['states'])) {
                $errors[] = 'states debe ser un objeto.';
            } elseif (($config['states']['column'] ?? '') === '') {
                $errors[] = 'states.column es obligatorio cuando se define el bloque states.';
            }
        }

        if (array_key_exists('relations', $config) && !is_array($config['relations'])) {
            $errors[] = 'relations debe ser un objeto.';
        }

        if (array_key_exists('detail', $config) && !is_array($config['detail'])) {
            $errors[] = 'detail debe ser un objeto.';
        }

        $validators = $config['form']['validators'] ?? null;
        if ($validators !== null && !is_array($validators)) {
            $errors[] = 'form.validators debe ser un arreglo.';
        }

        return $errors;
    }
```

Then wire it into `validate()`. Find this block (currently around lines 122-128):

```php
        $this->validateHooks($config, $errors);
        $this->validateListAggregations($config, $columnLookup, $errors);
        $this->validateListAggregationConfig($config, $errors);

        if (!empty($errors)) {
```

and change it to:

```php
        $this->validateHooks($config, $errors);
        $this->validateListAggregations($config, $columnLookup, $errors);
        $this->validateListAggregationConfig($config, $errors);

        foreach (self::newBlockShapeErrors($config) as $shapeError) {
            $errors[] = $shapeError;
        }

        if (!empty($errors)) {
```

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/run.php CrudConfigValidatorShapeTest`
Expected: `4 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudConfigValidator.php tests/Crud/CrudConfigValidatorShapeTest.php
git commit -m "feat(crud): config validator scaffolding for actions/states/relations/detail/validators"
```

---

### Task 14: `CrudDataService` — build `CrudWriteContext`, read back, new event names

**Files:**
- Modify: `app/Application/Services/CrudDataService.php` (imports + `store`, `update`, `delete`)

> This is the wiring that makes the mutation fix real in production. The read-back **mechanism** is already proven by Task 12's runner test. `CrudDataService` itself is not unit-testable here (it requires the final `GenericCrudRepository` + a live DB), so this task ends with a code-review checkpoint and a manual create/update smoke (Step 5). Do not introduce a repository interface — that is a deliberate no-goal for Fase 0.

- [ ] **Step 1: Add the import**

In `app/Application/Services/CrudDataService.php`, add this `use` line alongside the existing imports (after `use App\Domain\Entities\CrudResourceDefinition;`):

```php
use App\Application\Crud\Context\CrudWriteContext;
```

- [ ] **Step 2: Rewrite `store()`**

Replace the entire `store()` method (currently lines 245-272) with:

```php
    public function store(CrudResourceDefinition $definition, array $input, array $files, ?int $userId, string $ip): int
    {
        $payload = $this->buildPayload($definition, $input, $files, true, null);

        $systemColumns = [
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
            'updated_at' => null,
            'updated_by' => null,
            'deleted' => 0,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
        $payload = array_merge($payload, $systemColumns);

        $ctx = new CrudWriteContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $input,
            null,
            null,
            $payload,
            true
        );
        $this->hookRunner->run($definition, 'beforeCreate', $ctx);

        // Read-back: el handler pudo mutar data(); re-aplicamos columnas de
        // sistema para que no puedan ser sobrescritas por un handler.
        $payload = array_merge($ctx->data(), $systemColumns);

        $id = $this->repository->insertRecord($definition->table(), $payload);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.create',
            $definition->table(),
            $id,
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        $ctx->setData($payload);
        $ctx->setRecordId($id);
        $this->hookRunner->run($definition, 'afterCreate', $ctx);

        return $id;
    }
```

- [ ] **Step 3: Rewrite `update()`**

Replace the entire `update()` method (currently lines 274-294) with:

```php
    public function update(CrudResourceDefinition $definition, int $id, array $input, array $files, ?int $userId, string $ip): void
    {
        $existing = $this->repository->findById($definition->table(), $definition->primaryKey(), $id);
        $payload = $this->buildPayload($definition, $input, $files, false, is_array($existing) ? $existing : null);

        $systemColumns = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ];
        $payload = array_merge($payload, $systemColumns);

        $ctx = new CrudWriteContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $input,
            is_array($existing) ? $existing : null,
            $id,
            $payload,
            false
        );
        $this->hookRunner->run($definition, 'beforeUpdate', $ctx);

        // Read-back con columnas de sistema preservadas.
        $payload = array_merge($ctx->data(), $systemColumns);

        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, $payload);
        $this->bitacoraRepository->registrar(
            $userId,
            'crud.update',
            $definition->table(),
            $id,
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        $ctx->setData($payload);
        $this->hookRunner->run($definition, 'afterUpdate', $ctx);
    }
```

- [ ] **Step 4: Rewrite `delete()`**

Replace the entire `delete()` method (currently lines 296-319) with:

```php
    public function delete(CrudResourceDefinition $definition, int $id, ?int $userId, string $ip): void
    {
        $existing = $this->repository->findById($definition->table(), $definition->primaryKey(), $id);

        $payload = [
            'deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ];

        $ctx = new CrudWriteContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            [],
            is_array($existing) ? $existing : null,
            $id,
            $payload,
            false
        );
        $this->hookRunner->run($definition, 'beforeDelete', $ctx);

        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, $payload);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.delete',
            $definition->table(),
            $id,
            'Borrado lógico',
            $ip
        );

        $this->hookRunner->run($definition, 'afterDelete', $ctx);
    }
```

- [ ] **Step 5: Run the full suite, then manual smoke**

Run: `php tests/run.php`
Expected: all tests pass, `N passed, 0 failed`.

Then verify the live write path compiles and runs (the array→object change must not error at runtime). Start the dev server and exercise an existing CRUD that has **no** hook handler (so the no-handler no-op path runs):

Run: `php -S localhost:8000 -t public`
Then in a browser, log in and create + edit + delete one record in an existing CRUD resource (e.g. the demo/clientes resource under `/admin/crud/...`).
Expected: create, edit, and delete all succeed with their flash messages; no `TypeError` in `storage/logs/app-*.log`. Confirm the `log_bitacora` rows for `crud.create/update/delete` are still written.

> Regression for the mutation fix specifically (optional, needs a handler): register a temporary handler in `config/crud_handlers.php` whose `beforeCreate` calls `$ctx->set('<some_column>', '<value>')` on a `dom_` table that has that column, create a record, and confirm the column persists with the injected value. Remove the temporary handler afterward.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudDataService.php
git commit -m "fix(crud): persist hook data mutations via CrudWriteContext read-back"
```

---

### Task 15: Docs + handler registry comment

**Files:**
- Modify: `config/crud_handlers.php` (comment only)
- Modify: `docs/modules/crud/modulo-crud-engine.md` (append a Fase 0 section)

- [ ] **Step 1: Update the handler registry doc comment**

In `config/crud_handlers.php`, replace the closing comment block and example (lines 16-20) with:

```php
| El registro ahora admite, además de hooks de escritura, los handlers de:
|   - acciones de fila/bulk      (CrudActionHandlerInterface)
|   - guards de transición       (CrudTransitionGuardInterface)
|   - validadores de formulario  (CrudValidatorInterface)
|   - scopes de listado          (CrudListScopeInterface)
| Una misma clave puede mapear a una clase que implemente varias interfaces.
|--------------------------------------------------------------------------
*/

return [
    // Ejemplo (descomenta y crea la clase al implementar lógica real):
    // 'clientes'        => \App\Application\Crud\Handlers\ClientesHandler::class,
    // 'anticipo_minimo' => \App\Application\Crud\Handlers\AnticipoMinimoValidator::class,
];
```

- [ ] **Step 2: Append a Fase 0 note to the module doc**

Append the following section to the end of `docs/modules/crud/modulo-crud-engine.md`:

```markdown

## Fase 0 — Fundación (contextos tipados)

Los hooks ya no reciben un `array` suelto sino objetos de contexto en
`app/Application/Crud/Context/`:

- `CrudContext` (base: resourceKey, table, primaryKey, userId, ip)
- `CrudWriteContext` (create/update/delete) — expone `input()`, `record()`,
  `recordId()`, `isCreate()` y un `data()` **mutable** (`setData/mergeData/set`).
  El motor relee `data()` tras `beforeCreate`/`beforeUpdate`, por lo que un
  handler puede inyectar o transformar columnas antes de persistir. Las
  columnas de sistema (`created_at/by`, `updated_at/by`, `deleted*`) se
  re-aplican después del read-back y no pueden ser sobrescritas por un handler.
- `CrudActionContext`, `CrudTransitionContext`, `CrudValidationContext`,
  `CrudListContext`, `CrudFormContext` — usados por las fases siguientes.

Vocabulario canónico de hooks: `beforeCreate/afterCreate`,
`beforeUpdate/afterUpdate`, `beforeDelete/afterDelete`. El runner también
dispara los alias legacy `beforeStore/afterStore` **solo si el handler los
define explícitamente** (las clases que extienden `AbstractCrudHookHandler` no
los definen, así que no hay doble ejecución).

Interfaces segregadas nuevas (en `app/Domain/Interfaces/`):
`CrudActionHandlerInterface`, `CrudTransitionGuardInterface`,
`CrudValidatorInterface`, `CrudListScopeInterface`. Se resuelven con
`CrudHandlerRegistry::resolve($key, $expectedInterface)`, que valida la
interfaz esperada.

> **Nota de capas:** por decisión del spec (Fase 2), los contextos viven en la
> capa Application y las interfaces de handler en Domain importan esos
> contextos. Esto relaja la regla de onion ("Domain sin dependencias externas")
> de forma deliberada para los puertos del CRUD Engine. Si se prefiere capas
> estrictas, mover `Crud/Context/` a `app/Domain/`.

Pruebas: `php tests/run.php` (arnés plano sin PHPUnit; PHPUnit aún no está
instalado en este repo).
```

- [ ] **Step 3: Run the full suite one last time**

Run: `php tests/run.php`
Expected: all tests pass, `N passed, 0 failed`, exit 0.

- [ ] **Step 4: Commit**

```bash
git add config/crud_handlers.php docs/modules/crud/modulo-crud-engine.md
git commit -m "docs(crud): document Fase 0 typed contexts and handler registry"
```

---

## Self-Review

**1. Spec coverage (Fase 0 items, section 7):**
- Familia `CrudContext` → Tasks 2–8 (all 7 contexts). ✓
- Interfaces segregadas → Task 9 (4 interfaces). ✓
- `CrudHandlerRegistry::resolve(key, interface)` → Task 11. ✓
- `CrudHookRunner` con contexto tipado + read-back + eventos extendidos/legacy → Task 12 (read-back proven) + Task 10 (extended events as overridable no-ops). ✓
- `AbstractCrudHookHandler` con contextos + nuevos eventos opcionales → Task 10. ✓
- Scaffolding del validador de config → Task 13. ✓
- Arregla el bug de mutación (`beforeStore` no podía mutar) → mechanism Task 12, production wiring Task 14. ✓
- 100% compatible / refactor cubierto por tests → harness Task 1, regression note + manual smoke Task 14. ✓
- Retype `CrudHookHandlerInterface` (array→contextos) → Task 10. ✓

**2. Placeholder scan:** No "TBD"/"add error handling"/"similar to". Every code step shows full file contents or exact replacement blocks. The one non-automatable item (live DB write path) is explicitly called out as a manual smoke with concrete commands, not hidden behind a vague step.

**3. Type/name consistency:** Context constructor argument order is identical across the runner/data-service/test usages (`resourceKey, table, primaryKey, userId, ip, ...specific`). `CrudWriteContext` order `(input, record, recordId, data, isCreate)` matches Tasks 3, 10, 11 fixtures, 12 tests, and 14 wiring. `resolve(?string $key, string $expectedInterface = CrudHookHandlerInterface::class): ?object` is consistent between Task 11 and its single caller in Task 12. Hook event names (`beforeCreate/afterCreate/beforeUpdate/afterUpdate/beforeDelete/afterDelete`) match between the interface (Task 10), the runner aliases (Task 12), and the data service calls (Task 14).

**Known limitation (honest):** `CrudDataService` and `CrudConfigValidator::validate()` are coupled to the final `GenericCrudRepository` + live DB and are not unit-tested here. The read-back mechanism and the config shape scaffolding are tested at their pure seams (runner+context, static method); the data-service wiring is verified by code review + a manual smoke. Decoupling the repo behind an interface is intentionally deferred (no-goal for Fase 0).

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-28-crud-engine-fase0-fundacion.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
