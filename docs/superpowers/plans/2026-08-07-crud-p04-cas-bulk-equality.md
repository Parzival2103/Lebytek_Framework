# CRUD Engine P04 — CAS transitions + bulk equality Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar **C4 + G13 + G1 + G14**: transitions y writes con predicados CAS (`expected`), reintento ×1 luego conflicto accionable, `runBulk` con parity `visible_when`/`enabled_when`, y `equalityMatches` fail-closed con SELECT de listado que incluye columnas de condiciones.

**Architecture:** Enfoque 1 del spec — `GenericCrudRepository::updateRecord` acepta `array $expected` → `AND col = ?`; `CrudTransitionService::apply` CAS `deleted=0` + `states.column=:from` con retry×1; `CrudDataService` update/delete usan `deleted=0` + misma política de retry; Domain endurece equality; list SELECT une keys de `visible_when`/`enabled_when` (row+bulk).

**Tech Stack:** PHP `>=8.2`, harness `php tests/run.php` + `tests/lib/microtest.php`, capas `Lebytek\Framework\{Domain,Application,Infrastructure}`, Fake repo en tests (subclase sin PDO).

**Programa:** Remediación CRUD Engine · **Punto:** 4/12 · **IDs:** C4, G13, G1, G14  
**Source audit:** `docs/audits/2026-08-07-auditoria-critica-crud-engine.md`  
**Source spec:** `docs/superpowers/specs/2026-08-11-crud-p04-cas-bulk-equality-design.md`  
**Estructura programa:** `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md`  
**Modo:** normal  

**Source audit PR:** #90  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`23e1dd219d5b2383ac6cbb02ca6681ad01638932`); rama de trabajo `feature/crud-p04-cas-bulk-equality` (o `cursor/crud-p04-cas-bulk-impl-c292`) creable desde `main`

## Baseline asumida (puntos 1..3)

| Punto | Plan | Estado verificado | Evidencia |
|------:|------|-------------------|-----------|
| 1 | `2026-08-07-crud-p01-authz-multi-canal.md` | completo en main | PR `#95` |
| 2 | `2026-08-07-crud-p02-states-form-options.md` | completo en main | PR `#100` — `states.column` fuera de form; toggle demo removido |
| 3 | uploads C6 (`2026-08-09-audit-crud-uploads-hardening.md`) | completo en main | PR `#111` · tag `v1.2.8` |
| — | M3/M4 | completo en main | PR `#114` · tip semver `1.2.10` · **no** sustituyen CAS |

**Implicaciones:** CAS sobre columna de estados es significativo (C3 cerrado). `updateRecord` tipado en `GenericCrudRepository` (sin interfaz Domain — no abrir G2). Tests unitarios usan Fake que **no** llama `BaseRepository::__construct()` (evita PDO).

## Global Constraints

- Solo IDs **C4, G13, G1, G14** como entregables de producto.
- No editar `vendor/`; no negocio Portal / `dom_*` / Marketing en este repo.
- No debilitar RBAC, CSRF, soft-delete, C6 uploads, ni checks de servicio CRUD.
- No introducir columna `version` ni migraciones schema transversales.
- Bulk permanece best-effort (`ok`/`fail`/`errors[]`).
- Mensaje de conflicto **exacto** (español): `El registro cambió; recarga e inténtalo de nuevo.`
- Semver: **PATCH `1.2.11`** en trío `composer.json` / `config/app.php` / `skeleton/config/app.php` + `docs/release/v1.2.11.md` + tag `v1.2.11` post-merge.
- Reintento automático: **máximo 1** (sin loops).

## Requisitos → tareas (matriz)

| ID | Requisito | Owner | Tarea | Verificación |
|----|-----------|-------|-------|--------------|
| G14 | `equalityMatches` fail-closed | Framework | Task 1 | `php tests/run.php Crud/Action/CrudActionDefinition` |
| G14 | List SELECT incluye columnas de condiciones | Framework | Task 2 | `php tests/run.php Crud/Action/CrudResourceDefinitionActions` (o suite nueva) |
| C4/G13 | `updateRecord(..., array $expected = [])` | Framework | Task 3 | `php tests/run.php Crud/State/CrudRepositoryUpdateExpected` |
| C4 | Transition CAS + retry×1 + conflicto | Framework | Task 4 | `php tests/run.php Crud/State/CrudTransitionService` |
| G13 | DataService update/delete con `deleted=0` + retry | Framework | Task 5 | `php tests/run.php Crud/State/CrudDataServiceWriteCas` |
| G1 | `runBulk` re-check visible/enabled | Framework | Task 6 | `php tests/run.php Crud/Action/CrudActionServiceBulkGuards` + Docs gate |
| — | Docs + semver 1.2.11 | Framework | Task 7 | `php tests/run.php Docs/CrudModuleCas PlatformVersionSemver` |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `src/Domain/Entities/Crud/CrudActionDefinition.php` | `equalityMatches` fail-closed |
| `src/Domain/Entities/CrudResourceDefinition.php` | `actionConditionColumnNames(): array` |
| `src/Infrastructure/Repositories/GenericCrudRepository.php` | `updateRecord` + `$expected` |
| `src/Application/Services/CrudTransitionService.php` | CAS + retry + conflicto |
| `src/Application/Services/CrudDataService.php` | list SELECT merge; update/delete CAS |
| `src/Application/Services/CrudActionService.php` | `runBulk` parity guards |
| `docs/modules/crud/modulo-crud-engine.md` | Documentar CAS + bulk re-check + equality |
| `docs/release/v1.2.11.md` | Release notes |
| `composer.json`, `config/app.php`, `skeleton/config/app.php` | `1.2.11` |
| `tests/fixtures/fake_crud_repository.php` | Fake `GenericCrudRepository` in-memory |
| `tests/Crud/Action/CrudActionDefinitionTest.php` | Casos G14 |
| `tests/Crud/Action/CrudActionConditionColumnsTest.php` | **Create** — columns helper |
| `tests/Crud/State/CrudRepositoryUpdateExpectedTest.php` | **Create** — expected predicates |
| `tests/Crud/State/CrudTransitionServiceTest.php` | CAS / retry / conflict |
| `tests/Crud/State/CrudDataServiceWriteCasTest.php` | **Create** — G13 writes |
| `tests/Crud/Action/CrudActionServiceBulkGuardsTest.php` | **Create** — G1 source+behavior |
| `tests/Docs/CrudModuleCasTest.php` | **Create** — docs frases |

**Interfaces producidas:**

- `GenericCrudRepository::updateRecord(string $table, string $primaryKey, int $id, array $payload, array $expected = []): int`
- `CrudResourceDefinition::actionConditionColumnNames(): array` — list\<string\>
- Mensaje conflicto: string literal único citado arriba
- `CrudTransitionService::apply(...)` — misma firma; semántica CAS

**Interfaces consumidas:**

- `GenericCrudRepository::findById(string $table, string $primaryKey, int $id): ?array`
- `CrudActionDefinition::isVisibleFor` / `isEnabledFor`
- `ValidationException`
- `CrudStateMachine`, `CrudTransitionContext`, hooks/bitácora existentes

**Constante de mensaje (usar literal idéntico en Transition + DataService):**

```php
'El registro cambió; recarga e inténtalo de nuevo.'
```

---

### Task 1: `equalityMatches` fail-closed (G14)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** None  
**Files:**
- Modify: `src/Domain/Entities/Crud/CrudActionDefinition.php`
- Modify: `tests/Crud/Action/CrudActionDefinitionTest.php`
- Test: `tests/Crud/Action/CrudActionDefinitionTest.php`
**Interfaces:**
- Consumes: `equalityMatches(array $conditions, array $row): bool`
- Produces: fail-closed semantics (spec § Domain)

- [ ] **Step 1: Escribir el test que falla** — añadir al final de `CrudActionDefinitionTest.php`:

```php
test('equalityMatches: null no equivale a string vacío (G14)', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'x', 'type' => 'handler', 'handler' => 'h',
        'enabled_when' => ['nota' => ''],
    ]);
    assert_true(
        !$a->isEnabledFor(['nota' => null]),
        'null no debe satisfacer expected string vacío (fail-closed G14)'
    );
});

test('equalityMatches: false tipado no equivale a string vacío (G14)', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'x', 'type' => 'handler', 'handler' => 'h',
        'enabled_when' => ['flag' => false],
    ]);
    assert_true($a->isEnabledFor(['flag' => false]), 'false === false');
    assert_true(
        !$a->isEnabledFor(['flag' => '']),
        'string vacío no debe satisfacer expected false'
    );
    assert_true(
        !$a->isEnabledFor([]),
        'columna ausente no debe satisfacer enabled_when flag=false (fail-open histórico)'
    );
});

test('equalityMatches: columna ausente nunca matchea escalar no-vacío', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'x', 'type' => 'handler', 'handler' => 'h',
        'visible_when' => ['status' => 'pendiente'],
    ]);
    assert_true(!$a->isVisibleFor([]));
    assert_true(!$a->isVisibleFor(['otra' => 'pendiente']));
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Crud/Action/CrudActionDefinition` / Expected: **FAIL** en al menos el caso `null`≡`''` o `flag:false` con fila vacía (hoy fail-open).

- [ ] **Step 3: Implementar el cambio mínimo** — reemplazar `equalityMatches` por lógica fail-closed:

```php
public static function equalityMatches(array $conditions, array $row): bool
{
    foreach ($conditions as $column => $expected) {
        $column = (string) $column;
        if (!array_key_exists($column, $row)) {
            return false;
        }
        $actual = $row[$column];
        if (is_array($expected)) {
            $ok = false;
            foreach ($expected as $candidate) {
                if (self::scalarEquals($actual, $candidate)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return false;
            }
            continue;
        }
        if (!self::scalarEquals($actual, $expected)) {
            return false;
        }
    }
    return true;
}

/** Igualdad escalar sin coerce null/false → ''. */
private static function scalarEquals(mixed $actual, mixed $expected): bool
{
    if ($actual === null || $expected === null) {
        return $actual === $expected;
    }
    if (is_bool($actual) || is_bool($expected)) {
        return $actual === $expected
            || $actual === (int) $expected
            || (int) $actual === $expected;
    }
    if (is_int($actual) || is_int($expected) || is_float($actual) || is_float($expected)) {
        return (string) $actual === (string) $expected
            && $actual !== null
            && $expected !== null;
    }
    return (string) $actual === (string) $expected;
}
```

Ajuste fino permitido si tests demuestran que `bloqueado => 0` con string `"0"` de PDO debe seguir pasando (usar comparación numérica string-safe **sin** mapear `null`/`false` a `''`).

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Crud/Action/CrudActionDefinition` / Expected: **PASS** (incluye tests previos `isVisibleFor` / `isEnabledFor`).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/Action` / Expected: 0 failed.

- [ ] **Step 6: Commit** — `fix(crud): fail-closed equalityMatches for action conditions (G14)`

---

### Task 2: Columnas de condiciones en list SELECT (G14 soporte)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Task 1  
**Files:**
- Modify: `src/Domain/Entities/CrudResourceDefinition.php`
- Modify: `src/Application/Services/CrudDataService.php` (bloque `$selectColumns` en `list()`)
- Create: `tests/Crud/Action/CrudActionConditionColumnsTest.php`
- Test: `tests/Crud/Action/CrudActionConditionColumnsTest.php`
**Interfaces:**
- Consumes: `rowActions()`, `bulkActions()`, `visibleWhen`/`enabledWhen` via action getters (usar reflexión solo si no hay getters — preferir leer maps ya expuestos; si no hay getters públicos, añadir `visibleWhen(): array` / `enabledWhen(): array` en `CrudActionDefinition` **o** acumular en `fromArray` en el definition)
- Produces: `CrudResourceDefinition::actionConditionColumnNames(): array`

Si `CrudActionDefinition` no expone los mapas, añadir:

```php
/** @return array<string, mixed> */
public function visibleWhen(): array { return $this->visibleWhen; }
/** @return array<string, mixed> */
public function enabledWhen(): array { return $this->enabledWhen; }
```

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Crud/Action/CrudActionConditionColumnsTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

test('actionConditionColumnNames unions visible_when and enabled_when keys', function (): void {
    if (!method_exists(CrudResourceDefinition::class, 'actionConditionColumnNames')) {
        throw new \RuntimeException('CrudResourceDefinition::actionConditionColumnNames missing (G14 list SELECT)');
    }
    $def = CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo', 'title' => 'Demo', 'table' => 'dom_demo',
            'primary_key' => 'id', 'permission_prefix' => 'demo',
        ],
        'list' => ['columns' => [['name' => 'nombre', 'label' => 'Nombre']]],
        'actions' => [
            'row' => [[
                'name' => 'go', 'type' => 'handler', 'handler' => 'h', 'permission' => 'demo.editar',
                'visible_when' => ['status' => 'pendiente'],
                'enabled_when' => ['bloqueado' => 0],
            ]],
            'bulk' => [[
                'name' => 'mass', 'type' => 'handler', 'handler' => 'h', 'permission' => 'demo.editar',
                'visible_when' => ['cola' => 'si'],
            ]],
        ],
    ]);
    $cols = $def->actionConditionColumnNames();
    sort($cols);
    assert_same(['bloqueado', 'cola', 'status'], $cols);
});
```

- [ ] **Step 2: Ejecutar** — `php tests/run.php Crud/Action/CrudActionConditionColumns` / Expected: **FAIL** (método ausente).

- [ ] **Step 3: Implementar**

En `CrudResourceDefinition`:

```php
/** @return list<string> */
public function actionConditionColumnNames(): array
{
    $names = [];
    foreach (array_merge($this->rowActions, $this->bulkActions) as $action) {
        foreach (array_keys($action->visibleWhen()) as $col) {
            $names[] = (string) $col;
        }
        foreach (array_keys($action->enabledWhen()) as $col) {
            $names[] = (string) $col;
        }
    }
    return array_values(array_unique(array_filter($names, static fn(string $c): bool => $c !== '')));
}
```

En `CrudDataService::list()`, tras añadir pk/`deleted`:

```php
foreach ($definition->actionConditionColumnNames() as $condCol) {
    $selectColumns[] = $condCol;
}
$selectColumns = array_values(array_filter(array_unique($selectColumns)));
```

- [ ] **Step 4: Verificación enfocada** — `php tests/run.php Crud/Action/CrudActionConditionColumns` / Expected: PASS.

- [ ] **Step 5: Regresión** — `php tests/run.php Crud/Action/CrudActionDefinition` / Expected: PASS.

- [ ] **Step 6: Commit** — `feat(crud): include action condition columns in list SELECT (G14)`

---

### Task 3: `updateRecord` con predicados `expected` (Infra)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** None (paralelo a Task 1–2 si se desea; merge order: antes de Task 4–5)  
**Files:**
- Modify: `src/Infrastructure/Repositories/GenericCrudRepository.php`
- Create: `tests/fixtures/fake_crud_repository.php`
- Create: `tests/Crud/State/CrudRepositoryUpdateExpectedTest.php`
- Test: `tests/Crud/State/CrudRepositoryUpdateExpectedTest.php`
**Interfaces:**
- Produces: `updateRecord(..., array $expected = []): int` con AND de igualdad

- [ ] **Step 1: Escribir fixture + test que falla**

`tests/fixtures/fake_crud_repository.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Repositories\GenericCrudRepository;

/**
 * In-memory GenericCrudRepository for CAS tests. Skips BaseRepository PDO ctor.
 */
final class FakeCrudRepository extends GenericCrudRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $rowsById = [];
    /** @var list<array{payload: array, expected: array}> */
    public array $updateCalls = [];

    public function __construct()
    {
        // Intentionally skip parent::__construct() — no PDO in unit tests.
    }

    public function findById(string $table, string $primaryKey, int $id): ?array
    {
        return $this->rowsById[$id] ?? null;
    }

    public function updateRecord(string $table, string $primaryKey, int $id, array $payload, array $expected = []): int
    {
        $this->updateCalls[] = ['payload' => $payload, 'expected' => $expected];
        $row = $this->rowsById[$id] ?? null;
        if ($row === null) {
            return 0;
        }
        foreach ($expected as $col => $val) {
            if (!array_key_exists((string) $col, $row) || $row[(string) $col] != $val) {
                return 0;
            }
        }
        $this->rowsById[$id] = array_merge($row, $payload);
        return 1;
    }
}
```

Test:

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/fixtures/fake_crud_repository.php';

test('updateRecord with expected returns 0 when predicate misses', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'autorizado', 'deleted' => 0];
    $n = $repo->updateRecord('dom_x', 'id', 1, ['status' => 'x'], ['status' => 'pendiente', 'deleted' => 0]);
    assert_same(0, $n);
    assert_same('autorizado', $repo->rowsById[1]['status']);
});

test('updateRecord with expected updates when predicate matches', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'pendiente', 'deleted' => 0];
    $n = $repo->updateRecord('dom_x', 'id', 1, ['status' => 'autorizado'], ['status' => 'pendiente', 'deleted' => 0]);
    assert_same(1, $n);
    assert_same('autorizado', $repo->rowsById[1]['status']);
});
```

- [ ] **Step 2: Ejecutar** — `php tests/run.php Crud/State/CrudRepositoryUpdateExpected` / Expected: **FAIL** (firma sin 5º arg → ArgumentCountError, o Fake no alinea con parent hasta implementar parent).

- [ ] **Step 3: Implementar** en `GenericCrudRepository::updateRecord`:

```php
public function updateRecord(string $table, string $primaryKey, int $id, array $payload, array $expected = []): int
{
    $sets = [];
    $params = [];
    foreach ($payload as $column => $value) {
        $sets[] = $this->quoteIdentifier((string) $column) . ' = ?';
        $params[] = $value;
    }
    $where = [$this->quoteIdentifier($primaryKey) . ' = ?'];
    $params[] = $id;
    foreach ($expected as $column => $value) {
        $where[] = $this->quoteIdentifier((string) $column) . ' = ?';
        $params[] = $value;
    }
    $sql = 'UPDATE ' . $this->quoteIdentifier($table)
        . ' SET ' . implode(', ', $sets)
        . ' WHERE ' . implode(' AND ', $where);

    return $this->execute($sql, $params);
}
```

- [ ] **Step 4: Verificación enfocada** — `php tests/run.php Crud/State/CrudRepositoryUpdateExpected` / Expected: PASS.

- [ ] **Step 5: Regresión** — `php tests/run.php Crud/State/CrudTransitionService` / Expected: PASS (authorize-only tests; apply aún sin CAS completo hasta Task 4).

- [ ] **Step 6: Commit** — `feat(crud): GenericCrudRepository updateRecord expected predicates (C4/G13)`

---

### Task 4: `CrudTransitionService` CAS + retry×1 (C4)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Task 3  
**Files:**
- Modify: `src/Application/Services/CrudTransitionService.php`
- Modify: `tests/Crud/State/CrudTransitionServiceTest.php`
- Test: `tests/Crud/State/CrudTransitionServiceTest.php`
**Interfaces:**
- Consumes: `updateRecord(..., expected)`, `findById`
- Produces: conflicto `ValidationException` con mensaje fijo tras retry

- [ ] **Step 1: Escribir tests que fallan** — append a `CrudTransitionServiceTest.php` (require fake fixture):

```php
require_once dirname(__DIR__, 1) . '/../fixtures/fake_crud_repository.php';

use Lebytek\Framework\Domain\Interfaces\BitacoraRepositoryInterface;

final class RecordingBitacora implements BitacoraRepositoryInterface
{
    public int $calls = 0;
    public function registrar(?int $usuarioId, string $accion, string $tabla = '', ?int $registroId = null, string $detalle = '', string $ip = ''): void
    {
        $this->calls++;
    }
}

test('apply CAS succeeds when DB status matches from', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'pendiente', 'deleted' => 0];
    $bit = new RecordingBitacora();
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]), $repo, $bit);
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    assert_same('autorizado', $repo->rowsById[1]['status']);
    assert_same(1, $bit->calls);
    assert_same(['status' => 'pendiente', 'deleted' => 0], $repo->updateCalls[0]['expected']);
});

test('apply CAS conflicts after retry when status already changed', function (): void {
    $repo = new FakeCrudRepository();
    // First CAS sees mismatch; find still shows autorizado; second authorize may block OR second CAS fails.
    $repo->rowsById[1] = ['id' => 1, 'status' => 'autorizado', 'deleted' => 0];
    $bit = new RecordingBitacora();
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]), $repo, $bit);
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    $caught = null;
    try {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    } catch (ValidationException $e) {
        $caught = $e;
    }
    assert_true($caught instanceof ValidationException);
    assert_true(str_contains($caught->getMessage(), 'El registro cambió; recarga e inténtalo de nuevo.'));
    assert_same(0, $bit->calls, 'no bitácora on conflict');
    assert_same('autorizado', $repo->rowsById[1]['status']);
});
```

Ajuste del segundo test: el record en memoria del caller dice `pendiente` pero DB ya `autorizado` → primer CAS expected status=pendiente → 0; re-find → status autorizado; re-authorize `autorizado→autorizado` inválido **o** si machine permite self, segundo CAS con from fresco. Con machine del fixture, `autorizado → autorizado` no está en transitions → `authorize` lanza otro `ValidationException` (mensaje de transición). Spec pide mensaje de **conflicto** unificado cuando CAS falla.

**Contrato de retry a implementar:** si tras re-find la transición desde el nuevo `$from` **no** es autorizable, lanzar **el mismo mensaje de conflicto** (no el mensaje genérico de transición), para no filtrar detalles de carrera. Alternativa aceptable documentada: mapear cualquier fallo post-CAS0 al mensaje conflicto.

- [ ] **Step 2: Ejecutar** — `php tests/run.php Crud/State/CrudTransitionService` / Expected: **FAIL** (sin expected en apply / sin mensaje).

- [ ] **Step 3: Implementar** en `apply()` tras `authorize` + wiring check:

```php
$expected = ['deleted' => 0, $column => $from];
$payload = [
    $column => $to,
    'updated_at' => date('Y-m-d H:i:s'),
    'updated_by' => $userId,
];

if ($this->hookRunner !== null) {
    $this->hookRunner->run($definition, 'beforeTransition', $ctx);
}

$updated = $this->repository->updateRecord(
    $definition->table(),
    $definition->primaryKey(),
    $id,
    $payload,
    $expected
);

if ($updated === 0) {
    $fresh = $this->repository->findById($definition->table(), $definition->primaryKey(), $id);
    if (!is_array($fresh) || (int) ($fresh['deleted'] ?? 0) === 1) {
        throw new ValidationException('El registro cambió; recarga e inténtalo de nuevo.');
    }
    $fromRetry = (string) ($fresh[$column] ?? '');
    $ctxRetry = new CrudTransitionContext(
        $definition->key(),
        $definition->table(),
        $definition->primaryKey(),
        $userId,
        $ip,
        $fresh,
        $column,
        $fromRetry,
        $to,
        []
    );
    try {
        $this->authorize($machine, $action->guard(), $ctxRetry);
    } catch (ValidationException) {
        throw new ValidationException('El registro cambió; recarga e inténtalo de nuevo.');
    }
    $updated = $this->repository->updateRecord(
        $definition->table(),
        $definition->primaryKey(),
        $id,
        [
            $column => $to,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ],
        ['deleted' => 0, $column => $fromRetry]
    );
    if ($updated === 0) {
        throw new ValidationException('El registro cambió; recarga e inténtalo de nuevo.');
    }
    $ctx = $ctxRetry;
}

// bitácora + afterTransition (como hoy)
```

- [ ] **Step 4: Verificación enfocada** — `php tests/run.php Crud/State/CrudTransitionService` / Expected: PASS.

- [ ] **Step 5: Regresión** — `php tests/run.php Crud/State` / Expected: 0 failed.

- [ ] **Step 6: Commit** — `fix(crud): CAS transition apply with one retry (C4)`

---

### Task 5: `CrudDataService` update/delete CAS `deleted=0` (G13)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Task 3  
**Files:**
- Modify: `src/Application/Services/CrudDataService.php` (`update`, `delete`)
- Create: `tests/Crud/State/CrudDataServiceWriteCasTest.php`
- Test: `tests/Crud/State/CrudDataServiceWriteCasTest.php`
**Interfaces:**
- Consumes: `updateRecord(..., ['deleted' => 0])`
- Produces: conflicto mismo mensaje; no muta filas deleted

Helper privado sugerido en `CrudDataService`:

```php
/**
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $expected
 */
private function updateWithCas(CrudResourceDefinition $definition, int $id, array $payload, array $expected): void
{
    $n = $this->repository->updateRecord(
        $definition->table(),
        $definition->primaryKey(),
        $id,
        $payload,
        $expected
    );
    if ($n === 1) {
        return;
    }
    $fresh = $this->repository->findById($definition->table(), $definition->primaryKey(), $id);
    if (!is_array($fresh) || (int) ($fresh['deleted'] ?? 0) === 1) {
        throw new ValidationException('El registro cambió; recarga e inténtalo de nuevo.');
    }
    $n = $this->repository->updateRecord(
        $definition->table(),
        $definition->primaryKey(),
        $id,
        $payload,
        $expected
    );
    if ($n !== 1) {
        throw new ValidationException('El registro cambió; recarga e inténtalo de nuevo.');
    }
}
```

`update()` / `delete()`: reemplazar `$this->repository->updateRecord(...)` por `$this->updateWithCas(..., ['deleted' => 0])` **después** de hooks before* (hooks siguen viendo intent; write condicionado).

- [ ] **Step 1: Test que falla** — `CrudDataServiceWriteCasTest.php` construye `CrudDataService` con `FakeCrudRepository`, bitácora recording, `CrudHookRunner` vacío, `CrudFieldValidationService` real; definition mínima; `delete` sobre fila `deleted=1` no cambia payload / lanza conflicto; `delete` sobre `deleted=0` marca deleted=1.

Usar definition:

```php
CrudResourceDefinition::fromArray([
  'resource' => ['key'=>'x','title'=>'X','table'=>'dom_x','primary_key'=>'id','permission_prefix'=>'x'],
  'form' => ['fields' => []],
]);
```

Si `update()` exige fields/validation pesada, priorizar cubrir **`delete()`** en tests (soft-delete CAS) y dejar `update()` con el mismo helper (grep confirma un solo call site).

- [ ] **Step 2: Ejecutar** — `php tests/run.php Crud/State/CrudDataServiceWriteCas` / Expected: FAIL.

- [ ] **Step 3: Implementar** helper + cablear `update`/`delete`.

- [ ] **Step 4: Verificación enfocada** — PASS.

- [ ] **Step 5: Regresión** — `php tests/run.php Crud/State` / Expected: 0 failed.

- [ ] **Step 6: Commit** — `fix(crud): CAS deleted=0 on CRUD update/delete writes (G13)`

---

### Task 6: `runBulk` re-check visible/enabled (G1)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Task 1  
**Files:**
- Modify: `src/Application/Services/CrudActionService.php` (`runBulk`)
- Create: `tests/Crud/Action/CrudActionServiceBulkGuardsTest.php`
- Test: `tests/Crud/Action/CrudActionServiceBulkGuardsTest.php`
**Interfaces:**
- Consumes: `isVisibleFor` / `isEnabledFor`
- Produces: mismo `ValidationException` texto que `run()`

- [ ] **Step 1: Escribir gate + comportamiento**

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

test('runBulk source re-checks visible_when and enabled_when like run (G1)', function () use ($root): void {
    $src = (string) file_get_contents($root . '/src/Application/Services/CrudActionService.php');
    $bulkPos = strpos($src, 'function runBulk');
    $runPos = strpos($src, 'function run(');
    assert_true($bulkPos !== false && $runPos !== false);
    $bulk = substr($src, $bulkPos, 1200);
    assert_true(
        str_contains($bulk, 'isVisibleFor') && str_contains($bulk, 'isEnabledFor'),
        'runBulk must re-check isVisibleFor/isEnabledFor before dispatch (G1)'
    );
    assert_true(
        str_contains($bulk, 'La acción no está disponible para este registro.'),
        'runBulk must use the same ValidationException message as run()'
    );
});
```

- [ ] **Step 2: Ejecutar** — `php tests/run.php Crud/Action/CrudActionServiceBulkGuards` / Expected: **FAIL**.

- [ ] **Step 3: Implementar** — en `runBulk`, tras ownership y antes de construir/dispatch context:

```php
if (!$action->isVisibleFor($record) || !$action->isEnabledFor($record)) {
    throw new ValidationException('La acción no está disponible para este registro.');
}
```

(idéntico a `run()` L120–122).

- [ ] **Step 4: Verificación enfocada** — PASS.

- [ ] **Step 5: Regresión** — `php tests/run.php Crud/Action` / Expected: 0 failed.

- [ ] **Step 6: Commit** — `fix(crud): re-check visible/enabled in runBulk (G1)`

---

### Task 7: Docs, semver `1.2.11`, release notes

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Tasks 1–6  
**Files:**
- Modify: `docs/modules/crud/modulo-crud-engine.md` (§ Fase 1 actions + § states/transitions)
- Create: `docs/release/v1.2.11.md`
- Create: `tests/Docs/CrudModuleCasTest.php`
- Modify: `composer.json`, `config/app.php`, `skeleton/config/app.php` → `1.2.11`
- Test: `tests/Docs/CrudModuleCasTest.php`, `PlatformVersionSemver`

- [ ] **Step 1: Test docs que falla**

```php
<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
test('modulo-crud-engine documents CAS and bulk re-check', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/modules/crud/modulo-crud-engine.md');
    foreach (['CAS', 'deleted = 0', 'El registro cambió', 'runBulk', 'fail-closed'] as $needle) {
        assert_true(str_contains($src, $needle), "docs must mention {$needle}");
    }
});
```

- [ ] **Step 2: Ejecutar** — `php tests/run.php Docs/CrudModuleCas` / Expected: FAIL.

- [ ] **Step 3: Implementar docs** — en § actions: `run` **y** `runBulk` re-validan; equality fail-closed; list SELECT incluye columnas de condiciones. En § states: persistencia CAS `WHERE pk AND deleted=0 AND {column}=:from`; retry×1; mensaje conflicto.  
  Crear `docs/release/v1.2.11.md` (contenido M3/M4 no; solo p04 CAS/bulk/equality; consumer bump; invoicing OFF).  
  Bump trío a **`1.2.11`**.

- [ ] **Step 4: Verificación enfocada** — `php tests/run.php Docs/CrudModuleCas PlatformVersionSemver` / Expected: PASS @ 1.2.11 (tag gate fallará hasta publicar `v1.2.11` — mismo chicken-egg REL-C1: taggear tip de release tras push).

- [ ] **Step 5: Regresión relevante** — Run:

```bash
php tests/run.php Crud/Action Crud/State Docs/CrudModuleCas Kernel/SkeletonPurity PlatformVersionSemver
```

Expected: 0 failed excepto `ReleaseTagPublishedTest` si el tag aún no existe — entonces publicar:

```bash
git tag -a v1.2.11 -m "Platform release 1.2.11 — CRUD p04 CAS/bulk equality"
git push origin v1.2.11
```

- [ ] **Step 6: Commit** — `docs(crud): CAS/bulk equality runbook and bump 1.2.11 (p04)`  
  Abrir PR hacia `main`. Actualizar fila punto 4 en `2026-08-07-crud-engine-remediacion-estructura.md` → `completo en main` tras merge.

---

## Criterios de aceptación (punto 4)

- [ ] **AC-C4:** Transition con estado DB ≠ `:from` no actualiza; tras retry×1 → mensaje conflicto.
- [ ] **AC-G13:** Update/soft-delete no mutan `deleted=1`.
- [ ] **AC-G1:** `runBulk` contiene re-check `isVisibleFor`/`isEnabledFor` con el mismo mensaje que `run`.
- [ ] **AC-G14:** equality fail-closed; `actionConditionColumnNames` + list SELECT.
- [ ] **AC-UX:** Mensaje conflicto fijo; bulk best-effort.
- [ ] **AC-REL:** Semver `1.2.11` + notas + tag; sin Marketing/Portal en `src/`.
- [ ] Suites del plan en verde; `SkeletonPurity` PASS.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Fake omitió `parent::__construct` y alguien llama método PDO | Fake solo usa overrides; no llamar `query`/`execute` del parent |
| PDO int/string en expected | `=` SQL + tests con int `0`/`1` deleted |
| Fail-closed rompe demos | SELECT añade columnas de condiciones; demos con conditions sobre columnas listadas |
| Tag chicken-egg Docs | Publicar `v1.2.11` en tip de release antes/justo al merge |

**Rollback:** revertir PR; consumidores restauran lock `1.2.10`.

## Evidencia que debe recopilar el ejecutor

- Salida de los comandos `php tests/run.php` listados por task.
- Diff de `updateRecord` mostrando `expected`.
- PR Framework + URL tag `v1.2.11`.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-11 |
| Framework `origin/main` referencia | `23e1dd219d5b2383ac6cbb02ca6681ad01638932` |
| Spec | `docs/superpowers/specs/2026-08-11-crud-p04-cas-bulk-equality-design.md` |
| Tareas completadas / totales | **0 / 7** |
| Siguiente tarea ejecutable | **Task 1** — equalityMatches fail-closed |
| Bloqueos | Ninguno Framework; Portal bump post-tag (ops) |
| Estado | **plan listo** |
