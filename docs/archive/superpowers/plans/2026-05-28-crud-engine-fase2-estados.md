# CRUD Engine — Fase 2 (Estados / Transiciones) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a metadata-driven state machine to the CRUD Engine so resources can declare states + valid transitions, expose `type: transition` row actions guarded server-side, audit each transition as `crud.transition`, and render state badges plus transition buttons in the detail (show) header — all additive and behind the optional `states` metadata block.

**Architecture:** A pure Domain VO `CrudStateMachine` (parsed from the `states` block) decides validity (`canTransition`, `allowedFrom`). A new `CrudTransitionService` mirrors the Fase 1 action seam: a pure, DB-free `authorize()` method (state-machine check + optional `CrudTransitionGuardInterface` guard from the whitelist) and a DB-bound `apply()` that persists the state column, writes the `crud.transition` bitácora row, and fires the extended `beforeTransition`/`afterTransition` hook events. `CrudActionService::run()` routes `type: transition` actions to the transition service (reusing Fase 1's RBAC + server-side `visible_when` re-check). The detail header reuses the Fase 1 `actions_row.php` partial to render transition buttons and shows the current-state badge from the state machine.

**Tech Stack:** PHP 8.1 (strict types, readonly VOs), the project's zero-dependency test harness (`php tests/run.php`), MySQL via `GenericCrudRepository` (prepared statements + `quoteIdentifier` whitelist), JSON metadata in `config/cruds/*.json`, Bootstrap 5 views, vanilla JS (`crud-engine.js`).

---

## Context for the implementer (read first)

This builds directly on Fase 0 (typed contexts) and Fase 1 (custom actions). Key existing pieces you will reuse — **do not reinvent them**:

- **`app/Application/Crud/Context/CrudTransitionContext.php`** — already exists from Fase 0. Extends `CrudContext`. Constructor signature (in order):
  `(string $resourceKey, string $table, string $primaryKey, ?int $userId, string $ip, ?array $record, string $statusColumn, string $from, string $to, array $input)`.
  Accessors: `record()`, `statusColumn()`, `from()`, `to()`, `input()`, plus base `resourceKey()/table()/primaryKey()/userId()/ip()`.
- **`app/Domain/Interfaces/CrudActionHandlerInterface.php`** — the Fase 1 interface to mirror for the new guard interface.
- **`app/Domain/Entities/Crud/CrudActionDefinition.php`** — already parses `type`, `to`, `handler`, `permission`, `visible_when`/`enabled_when`, `resolvePermission($prefix)`, `isVisibleFor($row)`, `isEnabledFor($row)`, `isTransition()`. You will add a `guard` field here.
- **`app/Application/Services/CrudActionService.php`** — `dispatch()` is the pure handler seam (rejects non-handler types); `run()` orchestrates load → RBAC → load record → server-side `visible_when`/`enabled_when` re-check → dispatch → `crud.action:{name}` bitácora. You will add a transition branch in `run()` **before** `dispatch()`. **Leave `dispatch()` handler-only** — the Fase 1 test `CrudActionServiceTest.php` asserts `dispatch` rejects `transition`, and that stays true because the transition path lives in `run()`, not `dispatch()`.
- **`app/Application/Services/CrudActionResolver.php`** — `visibleRowActions($definition, $row, $can)` already returns view-models; for `handler`/`transition` it emits an `endpoint` (`/admin/crud/{resource}/{id}/accion/{name}`). Transition actions therefore already render through the Fase 1 `actions_row.php` partial's `else` (POST form) branch. You reuse this for the detail header.
- **`app/Presentation/Views/admin/crud/partials/actions_row.php`** — the `else` branch renders a `<form method="POST" class="js-crud-action" data-confirm=...>` with a CSRF field; `public/assets/js/crud-engine.js` wires the generic confirm on `form.js-crud-action`.
- **`app/Infrastructure/Repositories/GenericCrudRepository.php`** — `findById($table,$pk,$id)`, `updateRecord($table,$pk,$id,$payload)` (prepared, `quoteIdentifier`-guarded). No new repo methods are needed for Fase 2.
- **`app/Application/Services/CrudHookRunner.php`** — `run($definition, $hookMethod, $context)` invokes the resource's whitelisted hook handler by `method_exists`, passing the context object. Use this to fire `beforeTransition`/`afterTransition`.

**Test harness:** tests are plain PHP files ending in `Test.php` anywhere under `tests/`, auto-discovered by `tests/run.php`. They run in the global namespace, `require` the autoloader via `tests/lib/bootstrap.php` (loaded by the runner), and use `test()`, `assert_true()`, `assert_same()`, `assert_null()`, `assert_throws()` from `tests/lib/microtest.php`. Fixtures (shared test doubles) live in `tests/fixtures/` and are guarded with `if (!class_exists(...))`. Run a subset with a path-substring filter: `php tests/run.php Crud/State`.

**Out of scope for Fase 2** (do NOT implement here — later phases): bulk transitions (bulk stays handler-only via `dispatch()`), DB constraint validators (`unique`/`exists`), relations/tabs, the full `show.php` nav-tabs rewrite. The detail header gets a **minimal** badge + actions change only.

---

## File Structure

**Create:**
- `app/Domain/Entities/Crud/CrudStateMachine.php` — pure VO: parse `states` block, decide transition validity, expose label/badge per state.
- `app/Domain/Interfaces/CrudTransitionGuardInterface.php` — `authorize(CrudTransitionContext): void` (throw to block).
- `app/Application/Services/CrudTransitionService.php` — `authorize()` (pure seam) + `apply()` (persist + audit + events).
- `app/Application/Crud/Handlers/DemoProductoStateGuard.php` — demo guard (escape-hatch example).
- `tests/Crud/State/CrudStateMachineTest.php`
- `tests/Crud/State/CrudTransitionServiceTest.php`
- `tests/Crud/State/CrudResourceDefinitionStatesTest.php`
- `tests/Crud/State/CrudConfigValidatorStatesTest.php`
- `tests/fixtures/transition_guards.php` — recording + blocking guard doubles.

**Modify:**
- `app/Domain/Entities/Crud/CrudActionDefinition.php` — add `guard` field + accessor.
- `app/Domain/Entities/CrudResourceDefinition.php` — parse `states` → `CrudStateMachine`; add `stateMachine()`/`hasStates()`.
- `app/Application/Services/CrudActionService.php` — add `?CrudTransitionService` dep; route `transition` in `run()`.
- `app/Application/Services/CrudConfigValidator.php` — add `statesBlockErrors()` + states column existence + transition-action `to` rule.
- `app/Application/Services/CrudResourceService.php` — `buildShowData()` returns `state` + `actions`.
- `app/Presentation/Views/admin/crud/show.php` — render state badge + transition/handler actions in header; load `crud-engine.js`.
- `config/container.php` — register `CrudTransitionService`; inject it into `CrudActionService`.
- `config/crud_handlers.php` — register `demo_producto_state_guard`.
- `config/cruds/demo_productos.json` — add `states` block + transition row actions.
- `tests/Crud/Action/CrudActionDefinitionTest.php` — append a `guard` parsing test.
- `docs/modules/crud/modulo-crud-engine.md` — append Fase 2 section.

---

### Task 1: `CrudStateMachine` Domain VO

**Files:**
- Create: `app/Domain/Entities/Crud/CrudStateMachine.php`
- Test: `tests/Crud/State/CrudStateMachineTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/State/CrudStateMachineTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\Crud\CrudStateMachine;

function demo_machine(): CrudStateMachine
{
    return CrudStateMachine::fromArray([
        'column' => 'status',
        'values' => [
            'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
            'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
            'rechazado'  => ['label' => 'Rechazado',  'badge' => 'danger'],
        ],
        'transitions' => [
            'pendiente'  => ['autorizado', 'rechazado'],
            'autorizado' => [],
            'rechazado'  => [],
        ],
    ]);
}

test('CrudStateMachine: column is exposed', function (): void {
    assert_same('status', demo_machine()->column());
});

test('CrudStateMachine: valid transition is allowed', function (): void {
    assert_true(demo_machine()->canTransition('pendiente', 'autorizado'));
    assert_true(demo_machine()->canTransition('pendiente', 'rechazado'));
});

test('CrudStateMachine: invalid transition is rejected', function (): void {
    assert_true(!demo_machine()->canTransition('pendiente', 'pendiente'));
    assert_true(!demo_machine()->canTransition('autorizado', 'pendiente'));
    assert_true(!demo_machine()->canTransition('desconocido', 'autorizado'));
});

test('CrudStateMachine: terminal state has no outgoing transitions', function (): void {
    assert_same([], demo_machine()->allowedFrom('autorizado'));
    assert_same(['autorizado', 'rechazado'], demo_machine()->allowedFrom('pendiente'));
    assert_same([], demo_machine()->allowedFrom('inexistente'));
});

test('CrudStateMachine: label and badge resolve per state, null when unknown', function (): void {
    $m = demo_machine();
    assert_same('Pendiente', $m->label('pendiente'));
    assert_same('warning', $m->badge('pendiente'));
    assert_null($m->label('inexistente'));
    assert_null($m->badge('inexistente'));
    assert_true($m->isKnownState('rechazado'));
    assert_true(!$m->isKnownState('inexistente'));
});

test('CrudStateMachine: fromArray fills defaults for sparse value metadata', function (): void {
    $m = CrudStateMachine::fromArray([
        'column' => 'estado',
        'values' => ['nuevo' => []],
        'transitions' => [],
    ]);
    assert_same('nuevo', $m->label('nuevo'));        // label defaults to the key
    assert_same('secondary', $m->badge('nuevo'));     // badge defaults to secondary
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Crud/State/CrudStateMachineTest`
Expected: FAIL — `Class "App\Domain\Entities\Crud\CrudStateMachine" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Domain/Entities/Crud/CrudStateMachine.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities\Crud;

/**
 * Máquina de estados inmutable parseada del bloque `states` de la metadata.
 * Pura: sin DB, sin efectos. Decide validez de transiciones y expone la
 * presentación (label/badge) de cada estado. Cero `if ($module === 'x')`.
 */
final class CrudStateMachine
{
    /**
     * @param array<string, array{label: string, badge: string}> $values
     * @param array<string, list<string>> $transitions
     */
    public function __construct(
        private readonly string $column,
        private readonly array $values,
        private readonly array $transitions
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $column = (string) ($config['column'] ?? '');

        $values = [];
        $rawValues = is_array($config['values'] ?? null) ? $config['values'] : [];
        foreach ($rawValues as $state => $meta) {
            $meta = is_array($meta) ? $meta : [];
            $key = (string) $state;
            $values[$key] = [
                'label' => (string) ($meta['label'] ?? $key),
                'badge' => (string) ($meta['badge'] ?? 'secondary'),
            ];
        }

        $transitions = [];
        $rawTransitions = is_array($config['transitions'] ?? null) ? $config['transitions'] : [];
        foreach ($rawTransitions as $from => $targets) {
            $list = [];
            if (is_array($targets)) {
                foreach ($targets as $target) {
                    if (is_string($target) && $target !== '') {
                        $list[] = $target;
                    }
                }
            }
            $transitions[(string) $from] = $list;
        }

        return new self($column, $values, $transitions);
    }

    public function column(): string
    {
        return $this->column;
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    /** @return list<string> */
    public function allowedFrom(string $state): array
    {
        return $this->transitions[$state] ?? [];
    }

    public function isKnownState(string $state): bool
    {
        return array_key_exists($state, $this->values);
    }

    public function label(string $state): ?string
    {
        return $this->values[$state]['label'] ?? null;
    }

    public function badge(string $state): ?string
    {
        return $this->values[$state]['badge'] ?? null;
    }

    /** @return array<string, array{label: string, badge: string}> */
    public function values(): array
    {
        return $this->values;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Crud/State/CrudStateMachineTest`
Expected: PASS — 6 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/Crud/CrudStateMachine.php tests/Crud/State/CrudStateMachineTest.php
git commit -m "feat(crud): add CrudStateMachine value object with unit tests"
```

---

### Task 2: `CrudTransitionGuardInterface` + guard test fixtures

**Files:**
- Create: `app/Domain/Interfaces/CrudTransitionGuardInterface.php`
- Create: `tests/fixtures/transition_guards.php`

- [ ] **Step 1: Create the interface**

Create `app/Domain/Interfaces/CrudTransitionGuardInterface.php` (mirrors `CrudActionHandlerInterface`):

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudTransitionContext;

/**
 * Guard de transición de estado. `authorize` lanza para bloquear la transición
 * (p. ej. reglas de negocio, permisos finos). No retorna nada: silencio = OK.
 */
interface CrudTransitionGuardInterface
{
    public function authorize(CrudTransitionContext $ctx): void;
}
```

- [ ] **Step 2: Create the test fixtures**

Create `tests/fixtures/transition_guards.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Interfaces\CrudTransitionGuardInterface;

if (!class_exists('RecordingTransitionGuard')) {
    /** Registra el último contexto recibido para aserciones; nunca bloquea. */
    class RecordingTransitionGuard implements CrudTransitionGuardInterface
    {
        public static ?CrudTransitionContext $last = null;

        public function authorize(CrudTransitionContext $ctx): void
        {
            self::$last = $ctx;
        }
    }

    /** Siempre bloquea lanzando, para verificar que la transición se aborta. */
    class BlockingTransitionGuard implements CrudTransitionGuardInterface
    {
        public function authorize(CrudTransitionContext $ctx): void
        {
            throw new \RuntimeException('transición bloqueada por guard');
        }
    }
}
```

- [ ] **Step 3: Verify the interface autoloads (no test yet — exercised in Task 4)**

Run: `php -l app/Domain/Interfaces/CrudTransitionGuardInterface.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Interfaces/CrudTransitionGuardInterface.php tests/fixtures/transition_guards.php
git commit -m "feat(crud): add CrudTransitionGuardInterface and guard test fixtures"
```

---

### Task 3: `CrudActionDefinition` parses `guard`

**Files:**
- Modify: `app/Domain/Entities/Crud/CrudActionDefinition.php`
- Test: `tests/Crud/Action/CrudActionDefinitionTest.php` (append)

- [ ] **Step 1: Write the failing test**

Append to `tests/Crud/Action/CrudActionDefinitionTest.php` (add these `test(...)` blocks at the end of the file; do not remove existing tests):

```php
test('CrudActionDefinition: parses guard key for transition actions', function (): void {
    $a = \App\Domain\Entities\Crud\CrudActionDefinition::fromArray([
        'name' => 'autorizar',
        'type' => 'transition',
        'to' => 'autorizado',
        'guard' => 'evento_autorizacion',
    ]);
    assert_same('autorizado', $a->to());
    assert_same('evento_autorizacion', $a->guard());
    assert_true($a->isTransition());
});

test('CrudActionDefinition: guard is null when absent or empty', function (): void {
    $a = \App\Domain\Entities\Crud\CrudActionDefinition::fromArray(['name' => 'edit', 'type' => 'builtin']);
    assert_null($a->guard());
    $b = \App\Domain\Entities\Crud\CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'transition', 'to' => 't', 'guard' => '']);
    assert_null($b->guard());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Crud/Action/CrudActionDefinitionTest`
Expected: FAIL — `Call to undefined method ...CrudActionDefinition::guard()`.

- [ ] **Step 3: Modify the VO**

In `app/Domain/Entities/Crud/CrudActionDefinition.php`:

Add the field to the constructor (after `private readonly ?string $to,` and before `private readonly array $visibleWhen,`):

```php
        private readonly ?string $to,
        private readonly ?string $guard,
        private readonly array $visibleWhen,
```

In `fromArray`, add the `guard` named argument to the `new self(...)` call (right after the `to:` line):

```php
            to: isset($config['to']) && $config['to'] !== '' ? (string) $config['to'] : null,
            guard: isset($config['guard']) && $config['guard'] !== '' ? (string) $config['guard'] : null,
            visibleWhen: is_array($config['visible_when'] ?? null) ? $config['visible_when'] : [],
```

Add the accessor next to the other accessors (after `public function to(): ?string { return $this->to; }`):

```php
    public function guard(): ?string { return $this->guard; }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Crud/Action/CrudActionDefinitionTest`
Expected: PASS — all existing + 2 new tests pass.

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php tests/run.php`
Expected: all green (Fase 0 + Fase 1 + Task 1 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Entities/Crud/CrudActionDefinition.php tests/Crud/Action/CrudActionDefinitionTest.php
git commit -m "feat(crud): parse guard key on CrudActionDefinition"
```

---

### Task 4: `CrudTransitionService::authorize` (pure seam)

**Files:**
- Create: `app/Application/Services/CrudTransitionService.php`
- Test: `tests/Crud/State/CrudTransitionServiceTest.php`

This task creates the service with **only** the DB-free `authorize()` method and its constructor. `apply()` is added in Task 6 (after `CrudResourceDefinition::stateMachine()` exists). `authorize()` is the unit-testable seam — it touches the state machine and the handler registry, never the DB.

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/State/CrudTransitionServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudTransitionContext;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudTransitionService;
use App\Domain\Entities\Crud\CrudStateMachine;
use App\Domain\Exceptions\ValidationException;

require_once dirname(__DIR__, 1) . '/../fixtures/transition_guards.php';

function transition_machine(): CrudStateMachine
{
    return CrudStateMachine::fromArray([
        'column' => 'status',
        'values' => [
            'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
            'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
        ],
        'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
    ]);
}

function transition_ctx(string $from, string $to): CrudTransitionContext
{
    return new CrudTransitionContext(
        'eventos', 'dom_eventos', 'id', 9, '127.0.0.1',
        ['id' => 5, 'status' => $from], 'status', $from, $to, []
    );
}

test('CrudTransitionService::authorize allows a valid transition with no guard', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    $svc->authorize(transition_machine(), null, transition_ctx('pendiente', 'autorizado'));
    // No exception => authorized.
    assert_true(true);
});

test('CrudTransitionService::authorize blocks an invalid transition', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    assert_throws(ValidationException::class, function () use ($svc): void {
        $svc->authorize(transition_machine(), null, transition_ctx('autorizado', 'pendiente'));
    });
});

test('CrudTransitionService::authorize runs the guard and passes the context', function (): void {
    RecordingTransitionGuard::$last = null;
    $svc = new CrudTransitionService(new CrudHandlerRegistry(['g' => RecordingTransitionGuard::class]));
    $svc->authorize(transition_machine(), 'g', transition_ctx('pendiente', 'autorizado'));
    assert_true(RecordingTransitionGuard::$last instanceof CrudTransitionContext, 'guard ran');
    assert_same('pendiente', RecordingTransitionGuard::$last->from());
    assert_same('autorizado', RecordingTransitionGuard::$last->to());
});

test('CrudTransitionService::authorize blocks when the guard throws', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry(['g' => BlockingTransitionGuard::class]));
    assert_throws(\RuntimeException::class, function () use ($svc): void {
        $svc->authorize(transition_machine(), 'g', transition_ctx('pendiente', 'autorizado'));
    });
});

test('CrudTransitionService::authorize errors when guard key is missing from the registry', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    assert_throws(ValidationException::class, function () use ($svc): void {
        $svc->authorize(transition_machine(), 'ausente', transition_ctx('pendiente', 'autorizado'));
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Crud/State/CrudTransitionServiceTest`
Expected: FAIL — `Class "App\Application\Services\CrudTransitionService" not found`.

- [ ] **Step 3: Write the implementation (authorize only for now)**

Create `app/Application/Services/CrudTransitionService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Entities\Crud\CrudStateMachine;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Domain\Interfaces\CrudTransitionGuardInterface;
use App\Infrastructure\Repositories\GenericCrudRepository;

/**
 * Aplica transiciones de estado del CRUD Engine.
 *
 * `authorize()` es el seam puro (sin DB): valida la transición contra la
 * máquina de estados y, si se declara, corre el guard whitelisteado. Lanza para
 * bloquear. `apply()` (Task 6) añade la persistencia + bitácora `crud.transition`
 * + eventos `beforeTransition`/`afterTransition`. Las deps de DB son opcionales
 * para que las pruebas unitarias de `authorize()` construyan solo con el registry.
 */
final class CrudTransitionService
{
    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry,
        private readonly ?GenericCrudRepository $repository = null,
        private readonly ?BitacoraRepositoryInterface $bitacoraRepository = null,
        private readonly ?CrudHookRunner $hookRunner = null
    ) {}

    /**
     * Seam puro: valida `from → to` contra la máquina y corre el guard opcional.
     * No toca la DB. Lanza ValidationException (transición inválida / guard
     * ausente) o lo que lance el guard (regla de negocio) para bloquear.
     */
    public function authorize(CrudStateMachine $machine, ?string $guardKey, CrudTransitionContext $ctx): void
    {
        if (!$machine->canTransition($ctx->from(), $ctx->to())) {
            throw new ValidationException("Transición no permitida: '{$ctx->from()}' → '{$ctx->to()}'.");
        }

        if ($guardKey !== null && $guardKey !== '') {
            $guard = $this->handlerRegistry->resolve($guardKey, CrudTransitionGuardInterface::class);
            if ($guard === null) {
                throw new ValidationException("El guard '{$guardKey}' no está registrado en la whitelist.");
            }
            /** @var CrudTransitionGuardInterface $guard */
            $guard->authorize($ctx);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Crud/State/CrudTransitionServiceTest`
Expected: PASS — 5 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudTransitionService.php tests/Crud/State/CrudTransitionServiceTest.php
git commit -m "feat(crud): add CrudTransitionService authorize seam (state-machine + guard)"
```

---

### Task 5: `CrudResourceDefinition` parses `states` → `stateMachine()`

**Files:**
- Modify: `app/Domain/Entities/CrudResourceDefinition.php`
- Test: `tests/Crud/State/CrudResourceDefinitionStatesTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/State/CrudResourceDefinitionStatesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\Crud\CrudStateMachine;
use App\Domain\Entities\CrudResourceDefinition;

function states_config(array $extra = []): array
{
    return array_merge([
        'resource' => [
            'key' => 'eventos', 'title' => 'Eventos', 'table' => 'dom_eventos',
            'primary_key' => 'id', 'permission_prefix' => 'eventos',
        ],
    ], $extra);
}

test('CrudResourceDefinition: no states block => stateMachine is null', function (): void {
    $def = CrudResourceDefinition::fromArray(states_config());
    assert_true(!$def->hasStates());
    assert_null($def->stateMachine());
});

test('CrudResourceDefinition: states block is parsed into a CrudStateMachine', function (): void {
    $def = CrudResourceDefinition::fromArray(states_config([
        'states' => [
            'column' => 'status',
            'values' => [
                'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
                'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
            ],
            'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
        ],
    ]));
    assert_true($def->hasStates());
    $machine = $def->stateMachine();
    assert_true($machine instanceof CrudStateMachine);
    assert_same('status', $machine->column());
    assert_true($machine->canTransition('pendiente', 'autorizado'));
    assert_same('success', $machine->badge('autorizado'));
});

test('CrudResourceDefinition: non-array states block is ignored', function (): void {
    $def = CrudResourceDefinition::fromArray(states_config(['states' => 'nope']));
    assert_true(!$def->hasStates());
    assert_null($def->stateMachine());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Crud/State/CrudResourceDefinitionStatesTest`
Expected: FAIL — `Call to undefined method ...CrudResourceDefinition::hasStates()`.

- [ ] **Step 3: Modify the entity**

In `app/Domain/Entities/CrudResourceDefinition.php`:

Add the import near the existing `use App\Domain\Entities\Crud\CrudActionDefinition;`:

```php
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Entities\Crud\CrudStateMachine;
```

Add the constructor field (after `private readonly array $bulkActions`):

```php
        private readonly array $rowActions,
        private readonly array $bulkActions,
        private readonly ?CrudStateMachine $stateMachine
    ) {}
```

In `fromArray`, build the state machine before the `return new self(`:

```php
        $stateMachine = null;
        if (array_key_exists('states', $config) && is_array($config['states'])) {
            $stateMachine = CrudStateMachine::fromArray($config['states']);
        }
```

Add the named argument at the end of the `new self(...)` call (after `bulkActions: $bulkActions`):

```php
            rowActions: $rowActions,
            bulkActions: $bulkActions,
            stateMachine: $stateMachine
        );
```

Add the accessors (after the `bulkActions()` method):

```php
    public function hasStates(): bool
    {
        return $this->stateMachine !== null;
    }

    public function stateMachine(): ?CrudStateMachine
    {
        return $this->stateMachine;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Crud/State/CrudResourceDefinitionStatesTest`
Expected: PASS — 3 passed, 0 failed.

- [ ] **Step 5: Run the full suite (the constructor changed — confirm Fase 1 still green)**

Run: `php tests/run.php`
Expected: all green. (Named-args construction in `fromArray` means the new trailing param is the only change; all callers go through `fromArray`.)

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Entities/CrudResourceDefinition.php tests/Crud/State/CrudResourceDefinitionStatesTest.php
git commit -m "feat(crud): parse states block into CrudStateMachine on CrudResourceDefinition"
```

---

### Task 6: `CrudTransitionService::apply` + route transitions in `CrudActionService::run`

**Files:**
- Modify: `app/Application/Services/CrudTransitionService.php`
- Modify: `app/Application/Services/CrudActionService.php`
- Test: `tests/Crud/State/CrudTransitionServiceTest.php` (append)

`apply()` validates (via `authorize()`) **before** touching the DB, so the failure paths are unit-testable with a DB-less service (`repository = null`). The happy-path persist + audit is covered by the integration/manual checklist (Task 10), mirroring how Fase 1 left `run()`/`runBulk()` to integration.

- [ ] **Step 1: Write the failing tests (append to `CrudTransitionServiceTest.php`)**

Add these blocks to the end of `tests/Crud/State/CrudTransitionServiceTest.php`:

```php
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Entities\CrudResourceDefinition;

function eventos_definition_with_states(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'eventos', 'title' => 'Eventos', 'table' => 'dom_eventos',
            'primary_key' => 'id', 'permission_prefix' => 'eventos',
        ],
        'states' => [
            'column' => 'status',
            'values' => ['pendiente' => [], 'autorizado' => []],
            'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
        ],
    ]);
}

test('CrudTransitionService::apply throws when the resource has no state machine', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    $def = CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'x', 'title' => 'X', 'table' => 'dom_x',
            'primary_key' => 'id', 'permission_prefix' => 'x',
        ],
    ]);
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    assert_throws(ValidationException::class, function () use ($svc, $def, $action): void {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    });
});

test('CrudTransitionService::apply blocks an invalid transition before persisting', function (): void {
    // repository is null: if apply() tried to persist it would fatal; it must throw first.
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'reabrir', 'type' => 'transition', 'to' => 'pendiente']);
    assert_throws(ValidationException::class, function () use ($svc, $def, $action): void {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'autorizado'], 7, '127.0.0.1');
    });
});

test('CrudTransitionService::apply runs the guard and blocks before persisting', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry(['g' => BlockingTransitionGuard::class]));
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado', 'guard' => 'g']);
    assert_throws(\RuntimeException::class, function () use ($svc, $def, $action): void {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Crud/State/CrudTransitionServiceTest`
Expected: FAIL — `Call to undefined method ...CrudTransitionService::apply()`.

- [ ] **Step 3: Add `apply()` to `CrudTransitionService`**

In `app/Application/Services/CrudTransitionService.php`, add this method after `authorize()`:

```php
    /**
     * Flujo completo de transición: valida (authorize) → evento beforeTransition
     * → persiste la columna de estado (+ updated_at/by) → bitácora crud.transition
     * → evento afterTransition. Valida ANTES de tocar la DB, así que las rutas de
     * fallo no requieren repositorio.
     *
     * @param array<string, mixed> $record fila actual del registro (incluye PK + estado)
     */
    public function apply(
        CrudResourceDefinition $definition,
        CrudActionDefinition $action,
        array $record,
        ?int $userId,
        string $ip
    ): void {
        $machine = $definition->stateMachine();
        if ($machine === null) {
            throw new ValidationException('El recurso no define una máquina de estados.');
        }

        $column = $machine->column();
        $from = (string) ($record[$column] ?? '');
        $to = (string) ($action->to() ?? '');
        $id = (int) ($record[$definition->primaryKey()] ?? 0);

        $ctx = new CrudTransitionContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $record,
            $column,
            $from,
            $to,
            []
        );

        // Lanza para bloquear (transición inválida / guard). Antes de cualquier DB.
        $this->authorize($machine, $action->guard(), $ctx);

        if ($this->repository === null || $this->bitacoraRepository === null) {
            throw new \LogicException('CrudTransitionService no está cableado para persistir.');
        }

        if ($this->hookRunner !== null) {
            $this->hookRunner->run($definition, 'beforeTransition', $ctx);
        }

        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, [
            $column => $to,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ]);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.transition',
            $definition->table(),
            $id,
            json_encode(['from' => $from, 'to' => $to, 'action' => $action->name()], JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        if ($this->hookRunner !== null) {
            $this->hookRunner->run($definition, 'afterTransition', $ctx);
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Crud/State/CrudTransitionServiceTest`
Expected: PASS — 8 passed, 0 failed.

- [ ] **Step 5: Route transition actions in `CrudActionService::run`**

In `app/Application/Services/CrudActionService.php`:

Add the constructor dependency (append as the last parameter, keeping it nullable so the Fase 1 unit tests that build `new CrudActionService(new CrudHandlerRegistry([...]))` still work):

```php
        private readonly ?BitacoraRepositoryInterface $bitacoraRepository = null,
        private readonly ?CrudTransitionService $transitionService = null
    ) {}
```

In `run()`, replace the block from the `$ctx = new CrudActionContext(...)` construction through the `dispatch` + bitácora call with this — the transition branch returns early (the transition service writes its own `crud.transition` audit):

```php
        // Re-chequeo server-side: nunca confiar en la UI.
        if (!$action->isVisibleFor($record) || !$action->isEnabledFor($record)) {
            throw new ValidationException('La acción no está disponible para este registro.');
        }

        if ($action->isTransition()) {
            if ($this->transitionService === null) {
                throw new \LogicException('CrudActionService no tiene CrudTransitionService cableado.');
            }
            $this->transitionService->apply($definition, $action, $record, $userId, $ip);
            return;
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
```

(Leave `dispatch()` and `runBulk()` unchanged — bulk transitions are out of scope and `dispatch()` correctly rejects non-handler types.)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php tests/run.php`
Expected: all green — including the Fase 1 `CrudActionServiceTest` (its `dispatch rejects ... transition` test still holds because the transition path is in `run()`, not `dispatch()`).

- [ ] **Step 7: Lint the two changed services**

Run: `php -l app/Application/Services/CrudTransitionService.php` and `php -l app/Application/Services/CrudActionService.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 8: Commit**

```bash
git add app/Application/Services/CrudTransitionService.php app/Application/Services/CrudActionService.php tests/Crud/State/CrudTransitionServiceTest.php
git commit -m "feat(crud): apply state transitions and route transition actions through CrudActionService"
```

---

### Task 7: `CrudConfigValidator` validates the `states` block

**Files:**
- Modify: `app/Application/Services/CrudConfigValidator.php`
- Test: `tests/Crud/State/CrudConfigValidatorStatesTest.php`

Adds a pure static `statesBlockErrors()` (shape + transition consistency + transition-action `to` consistency) wired into `validate()`, plus a DB-backed check that `states.column` exists and is not protected. The Fase 0 `newBlockShapeErrors()` keeps owning the "states.column es obligatorio" message — `statesBlockErrors()` does **not** re-emit it (avoids duplicates).

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/State/CrudConfigValidatorStatesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('statesBlockErrors: no states block is fine', function (): void {
    assert_same([], CrudConfigValidator::statesBlockErrors([]));
});

test('statesBlockErrors: well-formed states + transitions + transition action pass', function (): void {
    $config = [
        'states' => [
            'column' => 'status',
            'values' => [
                'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
                'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
            ],
            'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
        ],
        'actions' => ['row' => [
            ['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado'],
        ]],
    ];
    assert_same([], CrudConfigValidator::statesBlockErrors($config));
});

test('statesBlockErrors: non-array states reported', function (): void {
    assert_same(['states debe ser un objeto.'], CrudConfigValidator::statesBlockErrors(['states' => 'nope']));
});

test('statesBlockErrors: values must be a non-empty object', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors(['states' => ['column' => 'status', 'values' => []]]);
    assert_same(1, count($errors));
});

test('statesBlockErrors: unknown transition source and target are reported', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors(['states' => [
        'column' => 'status',
        'values' => ['a' => [], 'b' => []],
        'transitions' => [
            'a' => ['b', 'zzz'],   // zzz unknown target
            'ghost' => ['a'],      // ghost unknown source
        ],
    ]]);
    assert_same(2, count($errors));
});

test('statesBlockErrors: transition action pointing to an unknown state is reported', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors([
        'states' => ['column' => 'status', 'values' => ['a' => []], 'transitions' => ['a' => []]],
        'actions' => ['row' => [
            ['name' => 'go', 'type' => 'transition', 'to' => 'nowhere'],
        ]],
    ]);
    assert_same(1, count($errors));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Crud/State/CrudConfigValidatorStatesTest`
Expected: FAIL — `Call to undefined method ...CrudConfigValidator::statesBlockErrors()`.

- [ ] **Step 3: Add `statesBlockErrors()` and wire it into `validate()`**

In `app/Application/Services/CrudConfigValidator.php`:

Add the static method (place it right after the existing `actionsBlockErrors()` method):

```php
    /**
     * Valida la forma del bloque `states` y su consistencia con las acciones de
     * transición (Fase 2). Pura, sin DB. NO re-emite "states.column es
     * obligatorio" — eso lo cubre newBlockShapeErrors().
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function statesBlockErrors(array $config): array
    {
        if (!array_key_exists('states', $config)) {
            return [];
        }
        $states = $config['states'];
        if (!is_array($states)) {
            return ['states debe ser un objeto.'];
        }

        $errors = [];

        $values = $states['values'] ?? null;
        $stateKeys = [];
        if (!is_array($values) || $values === []) {
            $errors[] = 'states.values debe ser un objeto con al menos un estado.';
        } else {
            foreach ($values as $state => $meta) {
                $stateKeys[(string) $state] = true;
                if (!is_array($meta)) {
                    $errors[] = "states.values.{$state} debe ser un objeto.";
                }
            }
        }

        $transitions = $states['transitions'] ?? null;
        if ($transitions !== null) {
            if (!is_array($transitions)) {
                $errors[] = 'states.transitions debe ser un objeto.';
            } else {
                foreach ($transitions as $from => $targets) {
                    if (!isset($stateKeys[(string) $from])) {
                        $errors[] = "states.transitions tiene un estado origen desconocido: '{$from}'.";
                    }
                    if (!is_array($targets)) {
                        $errors[] = "states.transitions.{$from} debe ser un arreglo de estados.";
                        continue;
                    }
                    foreach ($targets as $target) {
                        if (!is_string($target) || !isset($stateKeys[$target])) {
                            $shown = is_string($target) ? $target : gettype($target);
                            $errors[] = "states.transitions.{$from} referencia un estado destino desconocido: '{$shown}'.";
                        }
                    }
                }
            }
        }

        // Acciones type=transition deben apuntar a un estado declarado en values.
        $actions = $config['actions'] ?? null;
        if (is_array($actions) && is_array($actions['row'] ?? null)) {
            foreach ($actions['row'] as $i => $action) {
                if (!is_array($action) || ($action['type'] ?? '') !== 'transition') {
                    continue;
                }
                $to = (string) ($action['to'] ?? '');
                if ($to !== '' && $stateKeys !== [] && !isset($stateKeys[$to])) {
                    $errors[] = "actions.row[{$i}] (transition) apunta a un estado desconocido: '{$to}'.";
                }
            }
        }

        return $errors;
    }
```

In the `actionsBlockErrors()` method, add a transition rule next to the existing `handler`/`link` requirement checks (after the `if ($type === 'link' ...)` block):

```php
                if ($type === 'transition' && ($action['to'] ?? '') === '') {
                    $errors[] = "actions.{$group}[{$i}] (transition) requiere 'to'.";
                }
```

In `validate()`, after the existing `foreach (self::actionsBlockErrors($config) as $actionError)` loop, add the states checks:

```php
        foreach (self::statesBlockErrors($config) as $stateError) {
            $errors[] = $stateError;
        }

        $statesColumn = is_array($config['states'] ?? null) ? (string) ($config['states']['column'] ?? '') : '';
        if ($statesColumn !== '' && $table !== '') {
            if (!isset($columnLookup[$statesColumn])) {
                $errors[] = "states.column ({$statesColumn}) no existe en {$table}.";
            }
            if (in_array($statesColumn, self::PROTECTED_COLUMNS, true)) {
                $errors[] = 'states.column no puede ser una columna protegida.';
            }
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Crud/State/CrudConfigValidatorStatesTest`
Expected: PASS — 6 passed, 0 failed.

- [ ] **Step 5: Run the full suite (confirms the new `transition requires 'to'` rule didn't break Fase 1 validator tests)**

Run: `php tests/run.php`
Expected: all green — `CrudConfigValidatorActionsTest` still passes (its malformed-actions fixtures contain no `transition` entries, so the count assertions are unaffected).

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudConfigValidator.php tests/Crud/State/CrudConfigValidatorStatesTest.php
git commit -m "feat(crud): validate states block and transition-action consistency"
```

---

### Task 8: Container wiring for `CrudTransitionService`

**Files:**
- Modify: `config/container.php`

- [ ] **Step 1: Add the import**

In `config/container.php`, near the other `use App\Application\Services\Crud*;` imports (e.g. after `use App\Application\Services\CrudResourceService;`), add:

```php
use App\Application\Services\CrudTransitionService;
```

- [ ] **Step 2: Register the singleton and inject it into `CrudActionService`**

In `config/container.php`, immediately **before** the `CrudActionService` binding, add:

```php
    $container->singleton(CrudTransitionService::class, fn(Container $c) => new CrudTransitionService(
        $c->get(CrudHandlerRegistry::class),
        $c->get(GenericCrudRepository::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudHookRunner::class)
    ));
```

Then update the `CrudActionService` binding to pass the transition service as the new last argument:

```php
    $container->singleton(CrudActionService::class, fn(Container $c) => new CrudActionService(
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudActionResolver::class),
        $c->get(RbacService::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudTransitionService::class)
    ));
```

- [ ] **Step 3: Lint and smoke-check the container**

Run: `php -l config/container.php`
Expected: `No syntax errors detected`.

Run: `php -r "define('ROOT_PATH', getcwd()); require 'app/Kernel/Autoloader.php'; \App\Kernel\Autoloader::register(); echo class_exists(App\Application\Services\CrudTransitionService::class) ? 'OK' : 'MISSING';"`
Expected: prints `OK`. (If the autoloader API differs, simply `php -l` is sufficient; the wiring is exercised end-to-end in Task 10's manual check.)

- [ ] **Step 4: Run the full suite**

Run: `php tests/run.php`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add config/container.php
git commit -m "feat(crud): bind CrudTransitionService and inject it into CrudActionService"
```

---

### Task 9: Detail header — state badge + transition/handler actions

**Files:**
- Modify: `app/Application/Services/CrudResourceService.php`
- Modify: `app/Presentation/Views/admin/crud/show.php`

`buildShowData()` already runs RBAC + loads the row. We add the resolved row actions (reusing the Fase 1 resolver) and the current-state descriptor. The view renders the badge in the header and the actions through the existing `actions_row.php` partial (filtering out the redundant `show` builtin since we are already on the detail page). The hardcoded "Editar"/"Eliminar" buttons are replaced by the resolved actions, so legacy resources (default `show/edit/delete`) still render Edit + Delete — same modal, same behavior.

- [ ] **Step 1: Extend `buildShowData()`**

In `app/Application/Services/CrudResourceService.php`, replace the `return [...]` block inside `buildShowData()` with:

```php
        $can = fn(string $slug): bool => $this->rbacService->puede($slug);
        $actions = $this->actionResolver->visibleRowActions($definition, $row, $can);

        $state = null;
        $machine = $definition->stateMachine();
        if ($machine !== null) {
            $value = (string) ($row[$machine->column()] ?? '');
            $state = [
                'value' => $value,
                'label' => $machine->label($value) ?? $value,
                'badge' => $machine->badge($value) ?? 'secondary',
            ];
        }

        return [
            'resource' => $definition->key(),
            'title' => $definition->title(),
            'row' => $row,
            'columns' => $definition->listColumns(),
            'primaryKey' => $definition->primaryKey(),
            'actions' => $actions,
            'state' => $state,
        ];
```

- [ ] **Step 2: Render the badge + actions in `show.php`**

In `app/Presentation/Views/admin/crud/show.php`:

Add the JS include just after the existing CSS `<link>` near the top:

```php
<link rel="stylesheet" href="<?= ViewHelper::asset('css/crud-engine.css') ?>">
<script src="<?= ViewHelper::asset('js/crud-engine.js') ?>" defer></script>
```

Replace the header `<div>` (the `card-header ...` block containing the title and the hardcoded Volver/Editar buttons) with:

```php
                <div class="card-header bg-transparent border-bottom p-3 p-md-4 d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-3">
                    <div>
                        <h1 class="ct-page-title h4 mb-1 d-flex align-items-center gap-2">
                            <?= ViewHelper::e((string) ($title ?? 'Detalle')) ?>
                            <?php if (!empty($state) && (string) ($state['value'] ?? '') !== ''): ?>
                                <span class="badge rounded-pill bg-<?= ViewHelper::e((string) $state['badge']) ?>-subtle text-<?= ViewHelper::e((string) $state['badge']) ?> border border-<?= ViewHelper::e((string) $state['badge']) ?>-subtle">
                                    <?= ViewHelper::e((string) $state['label']) ?>
                                </span>
                            <?php endif; ?>
                        </h1>
                        <p class="ct-page-subtitle text-muted small mb-0">Solo lectura. Fechas y montos con formato local.</p>
                    </div>
                    <div class="ct-actions d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto align-items-stretch align-items-sm-center">
                        <a href="/admin/crud/<?= ViewHelper::e((string) ($resource ?? '')) ?>"
                           class="btn btn-sm btn-outline-secondary">Volver al listado</a>
                        <?php
                            $headerActions = array_values(array_filter(($actions ?? []), static function (array $a): bool {
                                return (string) ($a['name'] ?? '') !== 'show';
                            }));
                        ?>
                        <?php require __DIR__ . '/partials/actions_row.php'; ?>
                    </div>
                </div>
```

> Note: `actions_row.php` reads `$rowActions`. Set it for the partial by assigning before the `require`. Replace the `<?php require ... ?>` line above with:
> ```php
>                         <?php $rowActions = $headerActions; require __DIR__ . '/partials/actions_row.php'; ?>
> ```

Remove the hardcoded footer "Eliminar" button block (the `<div class="card-footer ...">` containing the `js-crud-delete` button) — the delete builtin now renders via the actions partial and reuses the same `#crudDeleteModal` already defined below. Keep the `#crudDeleteModal` markup and its `<script>` block intact.

- [ ] **Step 3: Lint the changed PHP**

Run: `php -l app/Application/Services/CrudResourceService.php` and `php -l app/Presentation/Views/admin/crud/show.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Run the full suite**

Run: `php tests/run.php`
Expected: all green (view + buildShowData changes don't affect unit tests; this guards against accidental breakage elsewhere).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudResourceService.php app/Presentation/Views/admin/crud/show.php
git commit -m "feat(crud): render state badge and transition actions in detail header"
```

---

### Task 10: Demo wiring (states + transition actions), guard, docs, end-to-end verification

**Files:**
- Create: `app/Application/Crud/Handlers/DemoProductoStateGuard.php`
- Modify: `config/crud_handlers.php`
- Modify: `config/cruds/demo_productos.json`
- Modify: `docs/modules/crud/modulo-crud-engine.md`

- [ ] **Step 1: Create the demo guard (escape-hatch example)**

Create `app/Application/Crud/Handlers/DemoProductoStateGuard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Interfaces\CrudTransitionGuardInterface;
use App\Domain\Exceptions\ValidationException;

/**
 * Guard demo: ejemplo de escape hatch para una transición de estado.
 * Permite reactivar un producto solo si venía de 'inactivo'. La lógica de
 * negocio vive aquí, nunca en los servicios Crud* del core.
 */
final class DemoProductoStateGuard implements CrudTransitionGuardInterface
{
    public function authorize(CrudTransitionContext $ctx): void
    {
        if ($ctx->from() !== 'inactivo') {
            throw new ValidationException('Solo se puede activar un producto inactivo.');
        }
    }
}
```

- [ ] **Step 2: Register the guard in the whitelist**

In `config/crud_handlers.php`, add the key to the returned array (next to `demo_producto_toggle`):

```php
return [
    'demo_producto_toggle' => \App\Application\Crud\Handlers\DemoProductoToggleStatusHandler::class,
    'demo_producto_state_guard' => \App\Application\Crud\Handlers\DemoProductoStateGuard::class,

    // Ejemplo (descomenta y crea la clase al implementar lógica real):
    // 'clientes'        => \App\Application\Crud\Handlers\ClientesHandler::class,
    // 'anticipo_minimo' => \App\Application\Crud\Handlers\AnticipoMinimoValidator::class,
];
```

- [ ] **Step 3: Add the `states` block and transition actions to the demo config**

In `config/cruds/demo_productos.json`:

Add a `states` block (insert it as a top-level key, e.g. right after the `security` block):

```json
  "states": {
    "column": "status",
    "values": {
      "activo":   { "label": "Activo",   "badge": "success" },
      "inactivo": { "label": "Inactivo", "badge": "secondary" }
    },
    "transitions": {
      "activo":   ["inactivo"],
      "inactivo": ["activo"]
    }
  },
```

Add the two transition actions to `actions.row` (append after the existing `toggle` handler action):

```json
      { "name": "desactivar", "type": "transition", "to": "inactivo",
        "label": "Desactivar", "icon": "bi-pause-circle", "method": "POST",
        "permission": "editar", "confirm": "¿Desactivar este producto?",
        "visible_when": { "status": "activo" } },
      { "name": "activar", "type": "transition", "to": "activo",
        "label": "Activar", "icon": "bi-play-circle", "method": "POST",
        "permission": "editar", "confirm": "¿Activar este producto?",
        "visible_when": { "status": "inactivo" }, "guard": "demo_producto_state_guard" }
```

- [ ] **Step 4: Validate the JSON parses**

Run: `php -r "json_decode(file_get_contents('config/cruds/demo_productos.json'), true, 512, JSON_THROW_ON_ERROR); echo 'JSON OK';"`
Expected: prints `JSON OK`.

- [ ] **Step 5: Confirm the config passes the validator (DB required)**

Start the dev server if needed (`php -S localhost:8000 -t public`) and open `http://localhost:8000/admin/crud/demo_productos` while logged in as an admin. The list must load without a validation error flash (proves `states` + transition actions pass `CrudConfigValidator`). If the DB is unavailable in this environment, note it and rely on the manual check in Step 7.

- [ ] **Step 6: Run the full suite one final time**

Run: `php tests/run.php`
Expected: all green — Fase 0 + Fase 1 + all Fase 2 tests.

- [ ] **Step 7: Manual golden-path verification**

With the dev server running and an admin session:
1. Open a `demo_productos` detail page (`/admin/crud/demo_productos/{id}`) for an **activo** product. Confirm the header shows a green **Activo** badge and a **Desactivar** button (no **Activar**).
2. Click **Desactivar**, confirm the prompt. Expect a success flash and the product now reads **inactivo** in the list/detail.
3. Open the same product's detail (now **inactivo**). Confirm the header shows a grey **Inactivo** badge and an **Activar** button (no **Desactivar**). Click **Activar** → success (the `demo_producto_state_guard` allows it because `from == inactivo`).
4. Verify two `log_bitacora` rows with `accion = 'crud.transition'` were written for this product (query `log_bitacora` ordered by id desc, or check the bitácora view if available). Confirm there is **no** `crud.action:*` row for these transitions.
5. Submitting a transition POST without a CSRF token must be blocked (CSRF middleware) — confirmed by the route group; no action needed beyond noting the middleware is on the `accion` route.

- [ ] **Step 8: Update the module docs**

Append a "## Fase 2 — Estados / transiciones" section to `docs/modules/crud/modulo-crud-engine.md` covering: the optional `states` block (`column`, `values{label,badge}`, `transitions`), `type: transition` row actions (`to`, optional `guard`), the `CrudTransitionGuardInterface` escape hatch (whitelisted in `config/crud_handlers.php`, never FQCN in JSON), the `crud.transition` bitácora entry, server-side re-validation of the transition (state machine + `visible_when`), the state badge + transition buttons in the detail header, and the demo example on `demo_productos`. Mirror the structure/length of the existing Fase 1 section already in that file.

- [ ] **Step 9: Commit**

```bash
git add app/Application/Crud/Handlers/DemoProductoStateGuard.php config/crud_handlers.php config/cruds/demo_productos.json docs/modules/crud/modulo-crud-engine.md
git commit -m "feat(crud): demo states/transitions on demo_productos + Fase 2 docs"
```

---

## Self-Review (completed against the spec)

**Spec coverage (§7 Fase 2 bullet + §3/§4/§5/§6/§9.2/§10):**
- `CrudStateMachine` (`canTransition`, `allowedFrom`) — Task 1. ✓ (§6, §10 unit: valid/invalid/terminal)
- `CrudTransitionService` (validate → guard → update column + updated_at/by → `crud.transition` → events) — Tasks 4 & 6. ✓ (§3.4, §4)
- `type: transition` action wired into the action dispatch flow — Task 6 (`run()` branch). ✓ (§3.4, §4 "transition → delega en CrudTransitionService")
- `CrudTransitionGuardInterface` (`authorize` throws to block) — Task 2; resolved via `CrudHandlerRegistry::resolve(key, interface)` — Task 4. ✓ (§3.2, §6, §10 unit: registry rejects wrong interface — already covered by Fase 0 registry tests; here we assert the missing-key path)
- Bitácora `crud.transition` — Task 6. ✓ (§3.4, §10 integration)
- Badges from `states` — Task 1 (`badge()`), surfaced in Task 9 header. ✓ (§5 `states`, §9.2)
- Render in detail header — Task 9. ✓ (§7 "render en header de detalle")
- `states` metadata block parsed (§5/§9.2) — Task 5; validated (§4 validator) — Task 7. ✓
- Reuses Fase 0 `CrudTransitionContext` — Tasks 2/4/6 (no new context created). ✓
- Reuses Fase 1 patterns: `CrudActionDefinition`/resolver/dispatch, `accion` route, server-side `visible_when` re-check, `php tests/run.php` harness. ✓
- Security: CSRF on the `accion` route (existing, §10), server-side transition re-validation before persist (§3.4/§8 "visible_when burlado"), `states.column` not protected + exists (Task 7, §4). ✓

**Out-of-scope honored (§11 / Fase 3–4):** no `unique`/`exists`, no relations/tabs, no nav-tabs rewrite (header-only change), no bulk transitions, no dedicated transition-history table (uses `log_bitacora`). ✓

**Placeholder scan:** every code step contains complete code; commands have expected output. No TBD/TODO/"add validation". ✓

**Type consistency:** `CrudStateMachine::column()/canTransition()/allowedFrom()/label()/badge()/isKnownState()` used identically across Tasks 1/4/5/6/9. `CrudActionDefinition::guard()`/`to()`/`isTransition()` consistent across Tasks 3/6/7. `CrudTransitionService::authorize(machine, ?guardKey, ctx)` and `apply(definition, action, record, ?userId, ip)` signatures consistent between Tasks 4/6 and the container wiring (Task 8) and the `CrudActionService::run()` call site (Task 6). `CrudTransitionContext` constructor arg order matches the existing Fase 0 class. ✓

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-28-crud-engine-fase2-estados.md`.
