# CRUD Transitions CAS + Bulk Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar **CRUD-C4** (CAS en transiciones), **G13** (race soft-delete en update), **G1/G14** (paridad bulk vs fila + fail-closed en condiciones) y **M11** (reset de sesión en harness) como release PATCH **`1.2.11`**.

**Architecture:** Enfoque A del spec: nuevo `GenericCrudRepository::updateRecordWhere` retorna filas afectadas; `CrudTransitionService::apply` persiste con `WHERE pk = ? AND {state_column} = :from`; `CrudDataService::update` añade `deleted = 0` al predicado; `CrudActionService::runBulk` revalida `isVisibleFor`/`isEnabledFor` como `run()`; `CrudActionDefinition::equalityMatches` fail-closed si la columna no está en el row; `microtest.php` limpia `$_SESSION` tras cada test. Sin columna `version` ni API HTTP nueva.

**Tech Stack:** PHP `>=8.2`, harness `php tests/run.php` + `tests/lib/microtest.php`, capas `Lebytek\Framework\{Application,Domain,Infrastructure}`, MySQL vía `GenericCrudRepository` (tests integración con SKIP si DB ausente).

**Source spec:** `docs/superpowers/specs/2026-08-11-audit-crud-transition-cas-design.md` · **Modo:** normal  
**Source audit PR:** #115 — https://github.com/Parzival2103/Lebytek_Framework/pull/115 (mergeado en tip `23e1dd2`)  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`23e1dd219d5b2383ac6cbb02ca6681ad01638932`); rama `feature/crud-p04-cas-bulk-equality` (creable desde `main` — verificado `git ls-remote origin refs/heads/main`)

**Programa CRUD:** punto **4/12** · alias esperado `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` — **este archivo es el plan diario canónico 2026-08-11**

## Global Constraints

- Solo plataforma Framework (`src/`, `tests/`, `docs/`, semver trío); sin negocio Portal / `dom_*` en este repo.
- No editar `vendor/`; no desactivar RBAC, CSRF, soft-delete ni tests existentes.
- Mensajes `ValidationException` en español, accionables (U1/U3 del spec).
- Prerrequisito semver: tip actual **`1.2.10`**, tag **`v1.2.10`** publicado (PR #114).
- Rama `feature/crud-p04-cas-bulk-equality` no existe aún — crear desde `origin/main`.
- Legacy `archive/backoffice-api-integration` solo histórico; prohibido merge → `main`.

## Requisitos → tareas (matriz)

| ID | Requisito | Owner | Tarea | Criterio |
|----|-----------|-------|-------|----------|
| C4 | CAS transición `WHERE state = :from` | Framework | Task 2 | `php tests/run.php Crud/State/CrudTransitionServiceCas` |
| C4+ | Repo `updateRecordWhere` retorna 0 sin mutar | Framework | Task 1 | `php tests/run.php Crud/State/GenericCrudRepositoryUpdateWhere` |
| G13 | Update mutante con `deleted = 0` en WHERE | Framework | Task 3 | `php tests/run.php Crud/State/CrudDataServiceUpdateCas` |
| G14 | `equalityMatches` fail-closed columna ausente | Framework | Task 4 | `php tests/run.php Crud/Action/CrudActionDefinitionEquality` |
| G1 | `runBulk` revalida visible/enabled | Framework | Task 5 | `php tests/run.php Crud/Action/CrudActionServiceBulkConditions` |
| M11 | Reset `$_SESSION` post-test en harness | Framework | Task 6 | `php tests/run.php` monolítico + `Kernel/ApiHealthPublicDispatch` |
| Doc | § concurrencia + semver `1.2.11` | Framework | Task 7 | `php tests/run.php Docs/CrudModuleConcurrency PlatformVersionSemver` |

**Fuera de alcance:** G12 aggregation breaker (punto 5); columna `version` global; JSON CRUD Portal; deploy VPS; bump lock Portal (M6 no verificado); merge legacy feature.

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `src/Infrastructure/Repositories/GenericCrudRepository.php` | `updateRecordWhere(...): int` — UPDATE con predicados extra |
| `src/Application/Services/CrudTransitionService.php` | CAS en `apply()` vía `updateRecordWhere` |
| `src/Application/Services/CrudDataService.php` | `update()` usa predicado `deleted = 0` |
| `src/Domain/Entities/Crud/CrudActionDefinition.php` | `equalityMatches` fail-closed |
| `src/Application/Services/CrudActionService.php` | `runBulk` paridad con `run()` |
| `tests/lib/microtest.php` | Reset sesión tras cada `test()` |
| `docs/modules/crud/modulo-crud-engine.md` | § transiciones/concurrencia + bulk |
| `composer.json`, `config/app.php`, `skeleton/config/app.php` | bump `1.2.11` |
| `docs/release/v1.2.11.md` | Notas release C4/G1/G13/G14/M11 |

---

### Task 1: `GenericCrudRepository::updateRecordWhere`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** None  
**Files:**
- Modify: `src/Infrastructure/Repositories/GenericCrudRepository.php` (después de `updateRecord`, ~L245)
- Create: `tests/Crud/State/GenericCrudRepositoryUpdateWhereTest.php`
- Create: `tests/Crud/State/fixtures/cas_repository_helpers.php` (helper SKIP DB, DDL temp table, `cas_bitacora_null()` no-op)

**Interfaces:**
- Consumes: `BaseRepository::execute()` (retorna `rowCount()`)
- Produces: `updateRecordWhere(string $table, string $primaryKey, int $id, array $payload, array $whereEquals): int`

**Firma y SQL:**

```php
public function updateRecordWhere(
    string $table,
    string $primaryKey,
    int $id,
    array $payload,
    array $whereEquals
): int {
    // SET cols from $payload (mismo patrón quoteIdentifier que updateRecord)
    // WHERE pk = ? AND col1 = ? AND col2 = ? ... desde $whereEquals
    // return execute(...);  // 0 si ninguna fila coincide
}
```

- [ ] **Step 1: Escribir el test que falla** — `tests/Crud/State/GenericCrudRepositoryUpdateWhereTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/fixtures/cas_repository_helpers.php';

test('updateRecordWhere retorna 1 y muta cuando predicado coincide', function (): void {
    cas_repo_skip_if_no_db();
    $repo = cas_repo_new();
    $table = cas_repo_temp_table($repo, 'dom_cas_t1');
    cas_repo_insert($repo, $table, ['id' => 1, 'status' => 'pendiente', 'deleted' => 0]);

    $affected = $repo->updateRecordWhere($table, 'id', 1, ['status' => 'autorizado'], ['status' => 'pendiente', 'deleted' => 0]);
    assert_same(1, $affected);
    assert_same('autorizado', cas_repo_fetch_status($repo, $table, 1));
});

test('updateRecordWhere retorna 0 sin mutar cuando predicado no coincide (C4 base)', function (): void {
    cas_repo_skip_if_no_db();
    $repo = cas_repo_new();
    $table = cas_repo_temp_table($repo, 'dom_cas_t2');
    cas_repo_insert($repo, $table, ['id' => 1, 'status' => 'autorizado', 'deleted' => 0]);

    $affected = $repo->updateRecordWhere($table, 'id', 1, ['status' => 'cerrado'], ['status' => 'pendiente', 'deleted' => 0]);
    assert_same(0, $affected);
    assert_same('autorizado', cas_repo_fetch_status($repo, $table, 1));
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Crud/State/GenericCrudRepositoryUpdateWhere` / Expected: FAIL — `Call to undefined method ... updateRecordWhere` o método ausente.

- [ ] **Step 3: Implementar el cambio mínimo** — añadir `updateRecordWhere` en `GenericCrudRepository.php`; reutilizar `quoteIdentifier`; rechazar claves vacías en `$whereEquals` con `\InvalidArgumentException` si alguna key no matchea `IDENTIFIER_PATTERN`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Crud/State/GenericCrudRepositoryUpdateWhere` / Expected: PASS (2 tests) o SKIP documentado si DB no disponible (helper escribe `SKIP` a STDOUT y no incrementa fail).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/State/CrudTransitionService` / Expected: PASS — tests existentes sin regresión.

- [ ] **Step 6: Commit** — `git add src/Infrastructure/Repositories/GenericCrudRepository.php tests/Crud/State/GenericCrudRepositoryUpdateWhereTest.php tests/Crud/State/fixtures/cas_repository_helpers.php` · mensaje: `feat(crud): add updateRecordWhere for CAS predicates (C4 base)`

---

### Task 2: CAS en `CrudTransitionService::apply` (C4)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Task 1  
**Files:**
- Modify: `src/Application/Services/CrudTransitionService.php` (`apply`, ~L104–108)
- Create: `tests/Crud/State/CrudTransitionServiceCasTest.php`

**Interfaces:**
- Consumes: `GenericCrudRepository::updateRecordWhere`, `CrudStateMachine::column()`
- Produces: `ValidationException` con mensaje `El registro cambió de estado; recarga la página.` cuando `$affected === 0`; **no** escribe bitácora ni `afterTransition` en conflicto

**Pseudodiff `apply()`:**

```php
$affected = $this->repository->updateRecordWhere(
    $definition->table(),
    $definition->primaryKey(),
    $id,
    [$column => $to, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $userId],
    [$column => $from]
);
if ($affected === 0) {
    throw new ValidationException('El registro cambió de estado; recarga la página.');
}
// bitácora + afterTransition solo tras affected > 0
```

- [ ] **Step 1: Escribir el test que falla** — `tests/Crud/State/CrudTransitionServiceCasTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Application\Services\CrudTransitionService;
use Lebytek\Framework\Domain\Entities\Crud\CrudActionDefinition;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Infrastructure\Repositories\GenericCrudRepository;

require_once __DIR__ . '/fixtures/cas_repository_helpers.php';
require_once dirname(__DIR__, 2) . '/fixtures/transition_guards.php';

test('apply rechaza transición cuando estado en DB difiere del record (C4 CAS)', function (): void {
    cas_repo_skip_if_no_db();
    $repo = cas_repo_new();
    $table = cas_repo_temp_table($repo, 'dom_cas_tr');
    cas_repo_insert($repo, $table, ['id' => 5, 'status' => 'autorizado', 'deleted' => 0]);

    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'evt', 'table' => $table, 'primary_key' => 'id', 'permission_prefix' => 'evt'],
        'states' => [
            'column' => 'status',
            'values' => ['pendiente' => [], 'autorizado' => []],
            'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
        ],
    ]);
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]), $repo, cas_bitacora_null(), null);

    assert_throws(ValidationException::class, function () use ($svc, $def, $action): void {
        $svc->apply($def, $action, ['id' => 5, 'status' => 'pendiente'], 1, '127.0.0.1');
    });
    assert_same('autorizado', cas_repo_fetch_status($repo, $table, 5), 'DB no debe mutar en conflicto CAS');
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Crud/State/CrudTransitionServiceCas` / Expected: FAIL — transición pisa estado o no lanza (hoy `updateRecord` sin predicado).

- [ ] **Step 3: Implementar el cambio mínimo** — reemplazar `updateRecord` por `updateRecordWhere` con predicado `[$column => $from]`; mover bitácora/hooks **después** del check `$affected`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Crud/State/CrudTransitionServiceCas Crud/State/CrudTransitionService` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/Action/CrudActionPermission` / Expected: PASS.

- [ ] **Step 6: Commit** — mensaje: `fix(crud): CAS state predicate on transition apply (C4)`

---

### Task 3: Update con `deleted = 0` en WHERE (G13)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Task 1  
**Files:**
- Modify: `src/Application/Services/CrudDataService.php` (`update`, ~L486)
- Create: `tests/Crud/State/CrudDataServiceUpdateCasTest.php`

**Interfaces:**
- Consumes: `updateRecordWhere`, `$existing` de `findById`
- Produces: `ValidationException` `El registro ya no está disponible.` cuando `$affected === 0` tras soft-delete concurrente

- [ ] **Step 1: Escribir el test que falla** — simular fila soft-deleted: insert con `deleted=1`, intentar `update()` con record stale `deleted=0` en memoria vía reflexión parcial o integración:

```php
test('update rechaza fila soft-deleted concurrente (G13)', function (): void {
    cas_repo_skip_if_no_db();
    // insert id=8 deleted=1; buildPayload+update con existing deleted=0 simulado
    // expect ValidationException 'El registro ya no está disponible.'
    // assert columna mutada no cambió
});
```

Usar `ReflectionClass` + `newInstanceWithoutConstructor` (patrón `CrudDataServiceStateColumnWriteTest.php`) cableando `GenericCrudRepository` real.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Crud/State/CrudDataServiceUpdateCas` / Expected: FAIL — update muta fila `deleted=1`.

- [ ] **Step 3: Implementar el cambio mínimo** — en `update()`, reemplazar:

```php
$affected = $this->repository->updateRecordWhere(
    $definition->table(),
    $definition->primaryKey(),
    $id,
    $payload,
    ['deleted' => 0]
);
if ($affected === 0) {
    throw new ValidationException('El registro ya no está disponible.');
}
```

No invocar bitácora/hooks si `$affected === 0`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Crud/State/CrudDataServiceUpdateCas` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/State/CrudDataServiceStateColumnWrite Crud/Upload/CrudUploadLedger` / Expected: PASS.

- [ ] **Step 6: Commit** — mensaje: `fix(crud): guard update with deleted=0 predicate (G13)`

---

### Task 4: `equalityMatches` fail-closed (G14)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** None (parallel-safe con Task 1)  
**Files:**
- Modify: `src/Domain/Entities/Crud/CrudActionDefinition.php` (`equalityMatches`, ~L115)
- Modify: `tests/Crud/Action/CrudActionDefinitionTest.php`
- Create: `tests/Crud/Action/CrudActionDefinitionEqualityTest.php` (alias opcional si tests van en archivo existente)

**Interfaces:**
- Consumes: `$conditions` no vacío, `$row` sin clave
- Produces: `false` cuando `!array_key_exists($column, $row)` y `$conditions !== []`

- [ ] **Step 1: Escribir el test que falla**:

```php
test('equalityMatches fail-closed cuando columna ausente en row (G14)', function (): void {
    $ok = CrudActionDefinition::equalityMatches(['enabled' => false], []);
    assert_false($ok, 'enabled_when:false con row vacío debe ser false, no fail-open');
    $ok2 = CrudActionDefinition::equalityMatches(['status' => 'activo'], ['nombre' => 'x']);
    assert_false($ok2);
});

test('equalityMatches true cuando condiciones vacías (sin regresión)', function (): void {
    assert_true(CrudActionDefinition::equalityMatches([], []));
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Crud/Action/CrudActionDefinitionEquality` / Expected: FAIL — primer test: `(string) null === (string) false` evalúa true.

- [ ] **Step 3: Implementar el cambio mínimo** — al inicio del `foreach`:

```php
if ($conditions !== [] && !array_key_exists((string) $column, $row)) {
    return false;
}
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Crud/Action/CrudActionDefinition` / Expected: PASS (incl. test existente L58 `isVisibleFor([])` sigue false para acción con condición).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/Action/CrudActionResolver` / Expected: PASS.

- [ ] **Step 6: Commit** — mensaje: `fix(crud): equalityMatches fail-closed on missing columns (G14)`

---

### Task 5: Paridad `runBulk` con `run()` (G1)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Task 4  
**Files:**
- Modify: `src/Application/Services/CrudActionService.php` (`runBulk`, ~L186–198)
- Create: `tests/Crud/Action/CrudActionServiceBulkConditionsTest.php`
- Reuse: `tests/fixtures/action_handlers.php`

**Interfaces:**
- Consumes: `CrudActionDefinition::isVisibleFor`, `isEnabledFor`
- Produces: bulk item falla con mismo mensaje que `run()` — `La acción no está disponible para este registro.`

**Pseudodiff en loop `runBulk`**, después de `assertActionOwnership`:

```php
if (!$action->isVisibleFor($record) || !$action->isEnabledFor($record)) {
    throw new ValidationException('La acción no está disponible para este registro.');
}
```

- [ ] **Step 1: Escribir el test que falla** — wire mínimo `CrudActionService` con `CrudConfigLoader` fake (array en test), `CrudDataService` stub vía closure class, `RbacService` allow-all, `RecordingActionHandler`:

```php
test('runBulk no ejecuta handler cuando enabled_when no cumple (G1)', function (): void {
    RecordingActionHandler::$last = null;
    // bulk action enabled_when: { bloqueado: 0 }, record { id:1, bloqueado:1 }
    $result = $svc->runBulk('demo', 'sync', [1], [], 1, '127.0.0.1');
    assert_same(0, $result['ok']);
    assert_same(1, $result['fail']);
    assert_null(RecordingActionHandler::$last);
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Crud/Action/CrudActionServiceBulkConditions` / Expected: FAIL — handler ejecuta pese a `enabled_when`.

- [ ] **Step 3: Implementar el cambio mínimo** — insertar re-check visible/enabled antes de `dispatch`; transiciones bulk siguen vía `transitionService->apply` (heredan CAS Task 2).

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Crud/Action/CrudActionServiceBulkConditions` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Security/CrudActionOwnership` / Expected: PASS.

- [ ] **Step 6: Commit** — mensaje: `fix(crud): runBulk revalidates visible_when and enabled_when (G1)`

---

### Task 6: Reset sesión harness (M11)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** None (parallel-safe; merge antes de Task 7 gate)  
**Files:**
- Modify: `tests/lib/microtest.php` (`test()` function, ~L7–17)
- Modify: `tests/Kernel/ApiHealthPublicDispatchTest.php` (opcional comentario — comportamiento cubierto por microtest)

**Interfaces:**
- Consumes: `$_SESSION`, `Lebytek\Framework\Kernel\Security\Session`
- Produces: `$_SESSION = []` tras cada test; reinicio flag Session vía reflexión si `$started === true`

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Kernel/HarnessSessionIsolationTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Security\Session;

test('microtest aísla sesión: auth_user no persiste entre archivos', function (): void {
    Session::start();
    Session::set('auth_user', ['id' => 1]);
    assert_true(Session::has('auth_user'));
});
// Nota: este test pasa solo; el gate es la suite monolítica orden alfabético:
// Auth* antes de ApiHealthPublicDispatchTest — ver Step 2.
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Kernel/ApiHealthPublicDispatch` **después** de simular contaminación:  
  `php -r '$_SESSION=["auth_user"=>["id"=>1]]; require "tests/Kernel/ApiHealthPublicDispatchTest.php";'`  
  / Expected pre-fix: FAIL en test «Router dispatch does not return 200 JSON ok for /api/ping» (got 200).  
  Run suite: `php tests/run.php Kernel` con Auth tests incluidos / Expected pre-fix: al menos 1 FAIL en ping test.

- [ ] **Step 3: Implementar el cambio mínimo** — en `microtest.php` función `test()`, bloque `finally`:

```php
function test(string $name, callable $fn): void
{
    try {
        $fn();
        // ...
    } catch (\Throwable $e) {
        // ...
    } finally {
        $_SESSION = [];
        $ref = new \ReflectionClass(\Lebytek\Framework\Kernel\Security\Session::class);
        $prop = $ref->getProperty('started');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }
}
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Kernel/ApiHealthPublicDispatch Kernel/HarnessSessionIsolation` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel Auth` / Expected: PASS — contadores alineados con ejecución aislada por filtro.

- [ ] **Step 6: Commit** — mensaje: `fix(tests): reset session after each microtest (M11)`

---

### Task 7: Docs, semver `1.2.11`, release notes y gate

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p04-cas-bulk-equality`  
**Depends on:** Tasks 1–6  
**Files:**
- Modify: `docs/modules/crud/modulo-crud-engine.md` (§ Fase 2 transiciones + § acciones bulk — concurrencia CAS)
- Create: `docs/release/v1.2.11.md`
- Create: `tests/Docs/CrudModuleConcurrencyTest.php`
- Modify: `composer.json`, `config/app.php`, `skeleton/config/app.php` → `1.2.11`

**Interfaces:**
- Produces: frases obligatorias: `El registro cambió de estado; recarga la página.`, `updateRecordWhere`, `runBulk` revalida condiciones, reset sesión harness

- [ ] **Step 1: Escribir el test que falla** — `tests/Docs/CrudModuleConcurrencyTest.php` assert `str_contains` en doc paths.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/CrudModuleConcurrency` / Expected: FAIL — frases ausentes.

- [ ] **Step 3: Implementar docs + bump** — añadir subsección «Concurrencia y CAS» bajo Fase 2; nota bulk G1; crear `docs/release/v1.2.11.md` listando C4, G13, G1, G14, M11.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/CrudModuleConcurrency Docs/PlatformVersionSemver` / Expected: PASS @ `1.2.11`.

- [ ] **Step 5: Regresión relevante (gate final)** — Run:

```bash
php tests/run.php Crud
php tests/run.php Kernel
php tests/run.php Security/CrudActionOwnership
php tests/run.php SkeletonPurity
```

Expected: 0 failed (SKIP MySQL-only aceptable solo en tests marcados explícitamente).

- [ ] **Step 6: Commit** — mensaje: `docs(crud): CAS concurrency runbook and bump 1.2.11 (C4/G1/G13/G14/M11)`

**Requiere operador humano:** sí — publicar tag Git `v1.2.11` desde tip merge CI-verde; bump `composer.lock` Portal (M6 no verificado).

---

## Criterios finales de aceptación

- [ ] Transición concurrente: segunda petición lanza `ValidationException` U1; DB no inconsistente.
- [ ] Update sobre fila `deleted=1`: 0 filas afectadas, mensaje U3.
- [ ] Bulk respeta `enabled_when`/`visible_when` igual que `run()` (U4).
- [ ] `equalityMatches` con columna ausente → false (U5/G14).
- [ ] `php tests/run.php` monolítico: 0 fails en Kernel/Auth ping por sesión (M11/U11).
- [ ] Semver trío @ `1.2.11`; tag `v1.2.11` publicado post-merge (ops).
- [ ] Sin cambios Portal en este repo.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Más errores «recarga» bajo concurrencia real | Mensaje U1; comportamiento deseado |
| Bulk más lento | MAX_BULK_IDS=500 acota |
| Tests Auth dependían de sesión cruzada | Setup explícito por test |
| Portal sin bump | Documentar dependencia tag |

**Rollback:** revert PR implementación; consumidores mantienen lock `1.2.10`.

## Evidencia que debe recopilar el ejecutor

- Salida PASS de gates Task 7.
- PR URL + SHA merge.
- Tag `v1.2.11` URL (ops).
- Checkbox programa punto 4 en `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md` (commit docs separado permitido).

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-11 |
| Framework `origin/main` referencia | `23e1dd219d5b2383ac6cbb02ca6681ad01638932` |
| Tareas completadas / totales | 0 / 7 |
| Siguiente tarea ejecutable | Task 1 (`updateRecordWhere`) |
| Prerrequisitos | PHP ≥8.2, `composer install`, MySQL harness para tests integración CAS |
| Bloqueos | Ninguno en Framework; tag/Portal ops humano post-merge |
| Estado | Pendiente de implementación |
