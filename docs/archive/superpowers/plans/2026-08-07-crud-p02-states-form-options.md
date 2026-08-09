# CRUD Engine P02 — States form lock + options + demo toggle (C3+G15+G6) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Impedir que la columna de la máquina de estados se escriba por formulario o por el handler demo `toggle`, y revalidar en servidor las opciones de campos `select` (cierra C3 + G15 + G6).

**Architecture:** Gate de config en `CrudConfigValidator::statesBlockErrors` (colisión `states.column` ∈ `form.fields`); strip defensivo en `CrudDataService::buildPayload`; allowlist automática de claves `options` en `CrudFieldValidationService::validateValue` para `type=select`; eliminar el showcase `DemoProductoToggleStatusHandler` / `demo_producto_toggle` y dejar solo `type=transition` (`activar`/`desactivar`) como vía canónica.

**Tech Stack:** PHP 8.1+ (`composer.json`), harness `tests/run.php` + `tests/lib/microtest.php`, capas `Lebytek\Framework\{Application,Domain}`, configs JSON `config/cruds/` + espejo `skeleton/config/cruds/`, registry `config/crud_handlers.php` + espejo skeleton.

**Programa:** Remediación CRUD Engine · **Punto:** 2/12 · **IDs:** C3, G15, G6

**Source audit:** `docs/audits/2026-08-07-auditoria-critica-crud-engine.md`  
**Estructura programa:** `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md`  
**Modo:** normal

**Source audit PR:** #90 — https://github.com/Parzival2103/Lebytek_Framework/pull/90  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`b57bd6068b2cf8031b263f3ae73fdef1c913b570`); rama de trabajo de implementación `feature/crud-p02-states-form-options` (creable desde `main` — verificado `git ls-remote origin refs/heads/main`)

## Baseline asumida (puntos 1..N-1)

| Punto | Plan | Estado verificado | Evidencia (SHA / PR / archivos) |
|------:|------|-------------------|----------------------------------|
| 1 | `2026-08-07-crud-p01-authz-multi-canal.md` | completo en main | PR [#95](https://github.com/Parzival2103/Lebytek_Framework/pull/95) merge `64a6877`; tip `b57bd60`: `CrudScopeResolver::recordMatchesConditions`, `CrudActionService::resolveExecutablePermission`, `CrudReporteDataSource::assertCanViewResource`, tests AuthZ, semver `1.2.6` |

**Implicaciones para este plan:**

- No tocar AuthZ de scope/actions/Reportes (C1/C2/C5). Cualquier acción `handler`/`transition` que quede debe seguir declarando `permission` (C2).
- Semver tip = `1.2.6` → este punto publica **PATCH `1.2.7`** (trío `composer.json` / `config/app.php` / `skeleton/config/app.php`).
- CAS (punto 4) asume que este punto ya impide escritura de `states.column` por form/handler demo; no implementar CAS aquí.
- DI de handlers / interfaz en validator de actions (G5/M14, punto 8) fuera de alcance: se **elimina** el toggle en lugar de reescribirlo con DI.

## Global Constraints

- Solo IDs **C3, G15, G6** como entregables de producto.
- No editar `vendor/`; no negocio Portal en este repo.
- No debilitar CSRF, soft-delete, whitelist de handlers, owner/custom scope, ni `permission` obligatoria de p01.
- Espejo obligatorio: todo cambio en `config/cruds/*.json` o `config/crud_handlers.php` se replica byte-a-byte en `skeleton/config/...`.
- No meter `states.column` en `PROTECTED_COLUMNS` (ese set es lifecycle/auditoría); la regla es colisión explícita contra `states.column`.
- Mutaciones de estado solo vía `CrudActionService::run` → `type=transition` → `CrudTransitionService::apply` (no reintroducir writes de status en handlers).
- Semver: **PATCH `1.2.7`**. Si el tip ya tiene `≥1.2.7`, bump al siguiente PATCH libre y anótelo.
- Mensaje de colisión C3 (exacto): `states.column ('{column}') no puede aparecer en form.fields; use actions type=transition.`
- Mensaje G6 default (exacto, reutiliza existente): `Valor no permitido.` vía `msg($rules, 'in', ...)`.

## Requisitos → tareas (matriz)

| ID auditoría | Requisito | Owner | Tarea | Verificación |
|--------------|-----------|-------|-------|--------------|
| C3 | Validator rechaza `states.column` en `form.fields` | Framework | Task 1 | `php tests/run.php Crud/State/CrudConfigValidatorStates` |
| C3 | Demos sin campo form = columna de estados; create usa DEFAULT SQL | Framework | Task 2 | `php tests/run.php Crud/State/CrudDemoStatesFormLock` |
| C3 | `buildPayload` no persiste la columna de SM aunque esté en form (defensa) | Framework | Task 3 | `php tests/run.php Crud/State/CrudDataServiceStateColumnWrite` |
| G15 | Eliminar toggle demo (JSON row+bulk, handler, registry×2) | Framework | Task 4 | `php tests/run.php Crud/Action/DemoProductoToggleRemoved` |
| G6 | `select` con `options` → allowlist servidor (y `validation.in` explícito sigue ganando) | Framework | Task 5 | `php tests/run.php Crud/Validation/CrudFieldSelectOptions` |
| C3+G15+G6 | Docs estados + semver + regresión p01 | Framework | Task 6 | `php tests/run.php Crud/State Crud/Validation Crud/Action Security Crud/Scope Reporte PlatformVersionSemver Kernel/SkeletonPurity` |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `src/Application/Services/CrudConfigValidator.php` | Colisión `states.column` ↔ `form.fields` en `statesBlockErrors` |
| `src/Application/Services/CrudDataService.php` | Strip defensivo de `stateMachine()->column()` en `buildPayload` |
| `src/Application/Services/CrudFieldValidationService.php` | Allowlist auto desde `options()` para `type=select` |
| `config/cruds/demo_productos.json` | Quitar form `status`; quitar actions toggle row+bulk |
| `config/cruds/demo_pedidos.json` | Quitar form `status` |
| `config/cruds/demo_citas.json` | Quitar form `estado` |
| `skeleton/config/cruds/demo_{productos,pedidos,citas}.json` | Espejo idéntico |
| `config/crud_handlers.php` + `skeleton/config/crud_handlers.php` | Quitar `demo_producto_toggle` |
| `src/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php` | **Borrar** |
| `docs/modules/crud/modulo-crud-engine.md` | Nota: columna de estados no va en form; create vía DEFAULT/hook; sin toggle |
| `composer.json`, `config/app.php`, `skeleton/config/app.php` | bump `1.2.7` |
| `tests/Crud/State/CrudConfigValidatorStatesTest.php` | Casos colisión C3 |
| `tests/Crud/State/CrudDemoStatesFormLockTest.php` | Demos JSON sin form state column / sin toggle |
| `tests/Crud/State/CrudDataServiceStateColumnWriteTest.php` | Strip en buildPayload |
| `tests/Crud/Action/DemoProductoToggleRemovedTest.php` | Registry + clase ausentes |
| `tests/Crud/Validation/CrudFieldSelectOptionsTest.php` | G6 options → `in` |

**Interfaces producidas:**

- `CrudConfigValidator::statesBlockErrors(array $config): array` — misma firma; nuevo error de colisión con mensaje exacto arriba.
- `CrudDataService::buildPayload(...)` — misma firma privada; omite `$definition->stateMachine()?->column()` del payload escribible.
- `CrudFieldValidationService::validateValue(CrudFieldDefinition $field, mixed $normalized): array` — misma firma; si `type==='select'` y `options()` no vacío y no hay `rules['in']`, usa `array_keys(options)` como allowlist.

**Interfaces consumidas (sin cambiar firma pública):**

- `CrudResourceDefinition::stateMachine(): ?CrudStateMachine`
- `CrudStateMachine::column(): string`
- `CrudFieldDefinition::{type,options,validation,name,required}()`
- `CrudTransitionService::apply` / acciones `type=transition` (sin cambios)
- `AccesoException` / `ValidationException` (sin cambios de mensaje RBAC p01)

---

### Task 1: Tests + gate C3 en `statesBlockErrors`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p02-states-form-options`  
**Depends on:** None (baseline p01 en main)  
**Files:**
- Modify: `tests/Crud/State/CrudConfigValidatorStatesTest.php`
- Modify: `src/Application/Services/CrudConfigValidator.php` (`statesBlockErrors`)
- Test: `tests/Crud/State/CrudConfigValidatorStatesTest.php`
**Interfaces:**
- Consumes: `CrudConfigValidator::statesBlockErrors`
- Produces: rechazo de colisión `states.column` ∈ `form.fields[*].name`

- [x] **Step 1: Escribir el test que falla** — añadir al final de `tests/Crud/State/CrudConfigValidatorStatesTest.php`:

```php
test('statesBlockErrors: states.column en form.fields es error (C3)', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors([
        'states' => [
            'column' => 'status',
            'values' => [
                'activo' => ['label' => 'Activo'],
                'inactivo' => ['label' => 'Inactivo'],
            ],
            'transitions' => ['activo' => ['inactivo'], 'inactivo' => ['activo']],
        ],
        'form' => [
            'fields' => [
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text'],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['activo' => 'Activo']],
            ],
        ],
    ]);
    assert_true(
        in_array("states.column ('status') no puede aparecer en form.fields; use actions type=transition.", $errors, true),
        'debe reportar colisión states.column ↔ form.fields'
    );
});

test('statesBlockErrors: sin form field = states.column pasa (C3)', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors([
        'states' => [
            'column' => 'status',
            'values' => [
                'activo' => ['label' => 'Activo'],
                'inactivo' => ['label' => 'Inactivo'],
            ],
            'transitions' => ['activo' => ['inactivo'], 'inactivo' => ['activo']],
        ],
        'form' => [
            'fields' => [
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text'],
            ],
        ],
        'actions' => ['row' => [
            ['name' => 'desactivar', 'type' => 'transition', 'to' => 'inactivo'],
        ]],
    ]);
    assert_same([], $errors);
});

test('statesBlockErrors: colisión usa el nombre real de column (estado)', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors([
        'states' => [
            'column' => 'estado',
            'values' => ['pendiente' => [], 'confirmada' => []],
            'transitions' => ['pendiente' => ['confirmada'], 'confirmada' => []],
        ],
        'form' => ['fields' => [
            ['name' => 'estado', 'type' => 'select'],
        ]],
    ]);
    assert_true(
        in_array("states.column ('estado') no puede aparecer en form.fields; use actions type=transition.", $errors, true),
        'mensaje debe interpolar estado'
    );
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Crud/State/CrudConfigValidatorStates`  
Expected: FAIL — al menos el test C3 de colisión (hoy `statesBlockErrors` no inspecciona `form.fields`).

- [x] **Step 3: Implementar el cambio mínimo** — en `CrudConfigValidator::statesBlockErrors`, **después** de validar `values`/`transitions`/`actions.row` transition `to` y **antes** del `return $errors`, añadir:

```php
        $column = (string) ($states['column'] ?? '');
        if ($column !== '') {
            $formFields = is_array($config['form'] ?? null) ? ($config['form']['fields'] ?? null) : null;
            if (is_array($formFields)) {
                foreach ($formFields as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $fieldName = (string) ($field['name'] ?? '');
                    if ($fieldName !== '' && $fieldName === $column) {
                        $errors[] = "states.column ('{$column}') no puede aparecer en form.fields; use actions type=transition.";
                        break;
                    }
                }
            }
        }
```

No modificar `PROTECTED_COLUMNS`. No tocar `actionsBlockErrors` (p01).

- [x] **Step 4: Verificación enfocada**

Run: `php tests/run.php Crud/State/CrudConfigValidatorStates`  
Expected: PASS

- [x] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/State/CrudConfigValidatorStates Crud/Action/CrudConfigValidatorActions`  
Expected: PASS (C2 permission sigue intacto)

- [x] **Step 6: Commit**

```bash
git add tests/Crud/State/CrudConfigValidatorStatesTest.php src/Application/Services/CrudConfigValidator.php
git commit -m "fix(crud): reject states.column in form.fields (C3)"
```

---

### Task 2: Demos JSON — quitar columna de estados del form

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p02-states-form-options`  
**Depends on:** Task 1  
**Files:**
- Modify: `config/cruds/demo_productos.json` (quitar objeto form field `status`; **no** quitar list/detail/filter `status`)
- Modify: `config/cruds/demo_pedidos.json` (quitar form field `status`)
- Modify: `config/cruds/demo_citas.json` (quitar form field `estado`)
- Modify: `skeleton/config/cruds/demo_productos.json` (espejo idéntico)
- Modify: `skeleton/config/cruds/demo_pedidos.json` (espejo idéntico)
- Modify: `skeleton/config/cruds/demo_citas.json` (espejo idéntico)
- Create: `tests/Crud/State/CrudDemoStatesFormLockTest.php`
- Test: `tests/Crud/State/CrudDemoStatesFormLockTest.php`
**Interfaces:**
- Consumes: `CrudConfigValidator::statesBlockErrors` sobre JSON decodificado de demos
- Produces: demos cargables sin colisión C3; create sigue válido vía DEFAULT SQL (`activo` / `pendiente`)

- [x] **Step 1: Escribir el test que falla** — crear `tests/Crud/State/CrudDemoStatesFormLockTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

/**
 * @return array{0: string, 1: array<string, mixed>}
 */
function crud_p02_load_demo(string $relative): array
{
    $path = dirname(__DIR__, 3) . '/' . $relative;
    assert_true(is_file($path), "falta {$relative}");
    $data = json_decode((string) file_get_contents($path), true);
    assert_true(is_array($data), "JSON inválido: {$relative}");

    return [$path, $data];
}

function crud_p02_form_names(array $config): array
{
    $names = [];
    foreach (($config['form']['fields'] ?? []) as $field) {
        if (is_array($field) && isset($field['name'])) {
            $names[] = (string) $field['name'];
        }
    }

    return $names;
}

test('demo_productos: form no incluye states.column status (C3)', function (): void {
    foreach (['config/cruds/demo_productos.json', 'skeleton/config/cruds/demo_productos.json'] as $rel) {
        [, $cfg] = crud_p02_load_demo($rel);
        assert_same('status', (string) ($cfg['states']['column'] ?? ''));
        assert_true(!in_array('status', crud_p02_form_names($cfg), true), "{$rel} aún tiene status en form");
        assert_same([], CrudConfigValidator::statesBlockErrors($cfg), "{$rel} statesBlockErrors debe ser []");
    }
});

test('demo_pedidos: form no incluye states.column status (C3)', function (): void {
    foreach (['config/cruds/demo_pedidos.json', 'skeleton/config/cruds/demo_pedidos.json'] as $rel) {
        [, $cfg] = crud_p02_load_demo($rel);
        assert_same('status', (string) ($cfg['states']['column'] ?? ''));
        assert_true(!in_array('status', crud_p02_form_names($cfg), true), "{$rel} aún tiene status en form");
        assert_same([], CrudConfigValidator::statesBlockErrors($cfg));
    }
});

test('demo_citas: form no incluye states.column estado (C3)', function (): void {
    foreach (['config/cruds/demo_citas.json', 'skeleton/config/cruds/demo_citas.json'] as $rel) {
        [, $cfg] = crud_p02_load_demo($rel);
        assert_same('estado', (string) ($cfg['states']['column'] ?? ''));
        assert_true(!in_array('estado', crud_p02_form_names($cfg), true), "{$rel} aún tiene estado en form");
        assert_same([], CrudConfigValidator::statesBlockErrors($cfg));
    }
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Crud/State/CrudDemoStatesFormLock`  
Expected: FAIL — demos actuales aún declaran el campo form de estado (y tras Task 1, `statesBlockErrors` no sería `[]` sobre esos JSON).

- [x] **Step 3: Implementar el cambio mínimo**

En **cada** uno de los 6 JSON (harness + skeleton):

1. `demo_productos.json`: eliminar el objeto completo del form field con `"name": "status"` (hoy ~líneas 191–202). Conservar `list.columns`/`filters`/`detail.tabs` que referencian `status`. Conservar transitions `activar`/`desactivar` (Task 4 quita solo `toggle`).
2. `demo_pedidos.json`: eliminar el form field `"name": "status"` (select con options pendiente/pagado/cancelado). Conservar list badges y transitions `pagar`/`cancelar`.
3. `demo_citas.json`: eliminar el form field `"name": "estado"`. Conservar list/detail y transitions `confirmar`/`cancelar`.

Tras editar, forzar espejo:

```bash
cmp config/cruds/demo_productos.json skeleton/config/cruds/demo_productos.json
cmp config/cruds/demo_pedidos.json skeleton/config/cruds/demo_pedidos.json
cmp config/cruds/demo_citas.json skeleton/config/cruds/demo_citas.json
```

Expected: exit 0 en los tres.

No tocar schema SQL: `dom_demo_productos.status` DEFAULT `'activo'`, `dom_demo_pedidos.status` DEFAULT `'pendiente'`, `dom_demo_citas.estado` DEFAULT `'pendiente'` ya cubren el create sin campo form.

- [x] **Step 4: Verificación enfocada**

Run: `php tests/run.php Crud/State/CrudDemoStatesFormLock Crud/State/CrudConfigValidatorStates`  
Expected: PASS

- [x] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/State`  
Expected: PASS

- [x] **Step 6: Commit**

```bash
git add config/cruds/demo_productos.json config/cruds/demo_pedidos.json config/cruds/demo_citas.json \
  skeleton/config/cruds/demo_productos.json skeleton/config/cruds/demo_pedidos.json skeleton/config/cruds/demo_citas.json \
  tests/Crud/State/CrudDemoStatesFormLockTest.php
git commit -m "fix(crud): remove state column from demo forms (C3)"
```

---

### Task 3: Defensa en profundidad — strip de columna SM en `buildPayload`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p02-states-form-options`  
**Depends on:** Task 1  
**Files:**
- Create: `tests/Crud/State/CrudDataServiceStateColumnWriteTest.php`
- Modify: `src/Application/Services/CrudDataService.php` (`buildPayload`)
- Test: `tests/Crud/State/CrudDataServiceStateColumnWriteTest.php`
**Interfaces:**
- Consumes: `CrudResourceDefinition::stateMachine()`, `CrudStateMachine::column()`, reflexión sobre `buildPayload` privado (mismo patrón que `tests/Crud/Upload/CrudUploadLedgerTest.php`)
- Produces: payload sin clave de columna de estados aunque el field exista en la definition de prueba

- [x] **Step 1: Escribir el test que falla** — crear `tests/Crud/State/CrudDataServiceStateColumnWriteTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudDataService;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

function crud_p02_invoke_build_payload(CrudDataService $service, CrudResourceDefinition $def, array $input): array
{
    $m = new ReflectionMethod(CrudDataService::class, 'buildPayload');
    $m->setAccessible(true);

    return $m->invoke($service, $def, $input, [], true, null, 1, '127.0.0.1');
}

function crud_p02_def_with_status_in_form(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo_productos',
            'title' => 'Demo',
            'table' => 'dom_demo_productos',
            'primary_key' => 'id',
            'permission_prefix' => 'demo_productos',
        ],
        'states' => [
            'column' => 'status',
            'values' => [
                'activo' => ['label' => 'Activo'],
                'inactivo' => ['label' => 'Inactivo'],
            ],
            'transitions' => ['activo' => ['inactivo'], 'inactivo' => ['activo']],
        ],
        'form' => [
            'fields' => [
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                [
                    'name' => 'status',
                    'label' => 'Estado',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
                ],
            ],
        ],
    ]);
}

test('buildPayload omite states.column aunque venga en form+input (C3 defensa)', function (): void {
    // CrudDataService es final; patrón idéntico a tests/Crud/Upload/CrudUploadLedgerTest.php.
    // buildPayload tolera dbConstraintValidator/handlerRegistry null; solo necesita fieldValidation.
    $ref = new ReflectionClass(CrudDataService::class);
    $service = $ref->newInstanceWithoutConstructor();
    $prop = $ref->getProperty('fieldValidation');
    $prop->setAccessible(true);
    $prop->setValue($service, new \Lebytek\Framework\Application\Services\CrudFieldValidationService());

    $payload = crud_p02_invoke_build_payload(
        $service,
        crud_p02_def_with_status_in_form(),
        ['nombre' => 'X', 'status' => 'inactivo']
    );
    assert_true(!array_key_exists('status', $payload), 'status no debe persistirse vía form');
    assert_same('X', $payload['nombre'] ?? null);
});
```

**Nota de wiring:** `validatePayload` ignora fields ausentes en `$normalizedByField` (así el skip de `status` no dispara `required`). `dbConstraintValidator` y `handlerRegistry` son nullable y quedan null con `newInstanceWithoutConstructor`. El strip debe calcular `$stateColumn` **una vez** antes del primer `foreach ($definition->formFields() as $field)` y hacer `continue` al inicio del loop tras resolver `$name`. Si la reflexión sobre `buildPayload` completo resultara frágil en el entorno del ejecutor, fallback permitido: extraer `private static function isStateMachineColumn(CrudResourceDefinition $definition, string $name): bool` y testearlo en unidad + llamarlo desde `buildPayload` (sin refactor amplio).

- [x] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Crud/State/CrudDataServiceStateColumnWrite`  
Expected: FAIL — hoy `status` entra al payload.

- [x] **Step 3: Implementar el cambio mínimo** — en `CrudDataService::buildPayload`, justo antes del `foreach ($definition->formFields() as $field)`:

```php
        $stateColumn = $definition->stateMachine()?->column() ?? '';
```

Y dentro del foreach, inmediatamente después de resolver `$name` y **antes** del check `PROTECTED_COLUMNS` (o justo después):

```php
            if ($stateColumn !== '' && $name === $stateColumn) {
                continue;
            }
```

- [x] **Step 4: Verificación enfocada**

Run: `php tests/run.php Crud/State/CrudDataServiceStateColumnWrite`  
Expected: PASS

- [x] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/State Crud/Upload/CrudUploadLedger`  
Expected: PASS (patrón reflexión upload intacto)

- [x] **Step 6: Commit**

```bash
git add src/Application/Services/CrudDataService.php tests/Crud/State/CrudDataServiceStateColumnWriteTest.php
git commit -m "fix(crud): strip state machine column from form payload (C3)"
```

---

### Task 4: G15 — eliminar demo toggle (handler + registry + JSON)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p02-states-form-options`  
**Depends on:** Task 2  
**Files:**
- Delete: `src/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php`
- Modify: `config/crud_handlers.php` — quitar entrada `'demo_producto_toggle' => ...`
- Modify: `skeleton/config/crud_handlers.php` — espejo
- Modify: `config/cruds/demo_productos.json` — quitar action row `toggle` y bulk `toggle`
- Modify: `skeleton/config/cruds/demo_productos.json` — espejo
- Create: `tests/Crud/Action/DemoProductoToggleRemovedTest.php`
- Test: `tests/Crud/Action/DemoProductoToggleRemovedTest.php`
**Interfaces:**
- Consumes: paths de registry/JSON/clase
- Produces: superficie demo sin bypass de SM; transitions `activar`/`desactivar` permanecen

- [x] **Step 1: Escribir el test que falla** — crear `tests/Crud/Action/DemoProductoToggleRemovedTest.php`:

```php
<?php

declare(strict_types=1);

test('DemoProductoToggleStatusHandler class is removed (G15)', function (): void {
    $path = dirname(__DIR__, 3) . '/src/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php';
    assert_true(!is_file($path), 'archivo handler toggle debe estar borrado');
    assert_true(
        !class_exists(\Lebytek\Framework\Application\Crud\Handlers\DemoProductoToggleStatusHandler::class, true),
        'autoload no debe resolver DemoProductoToggleStatusHandler'
    );
});

test('crud_handlers registry no registra demo_producto_toggle (G15)', function (): void {
    foreach (['config/crud_handlers.php', 'skeleton/config/crud_handlers.php'] as $rel) {
        $map = require dirname(__DIR__, 3) . '/' . $rel;
        assert_true(is_array($map), "{$rel} debe devolver array");
        assert_true(!array_key_exists('demo_producto_toggle', $map), "{$rel} aún mapea demo_producto_toggle");
    }
});

test('demo_productos JSON no declara action toggle (G15)', function (): void {
    foreach (['config/cruds/demo_productos.json', 'skeleton/config/cruds/demo_productos.json'] as $rel) {
        $cfg = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/' . $rel), true);
        assert_true(is_array($cfg));
        $rowNames = array_map(
            static fn ($a) => is_array($a) ? (string) ($a['name'] ?? '') : '',
            $cfg['actions']['row'] ?? []
        );
        $bulkNames = array_map(
            static fn ($a) => is_array($a) ? (string) ($a['name'] ?? '') : '',
            $cfg['actions']['bulk'] ?? []
        );
        assert_true(!in_array('toggle', $rowNames, true), "{$rel} row aún tiene toggle");
        assert_true(!in_array('toggle', $bulkNames, true), "{$rel} bulk aún tiene toggle");
        assert_true(in_array('desactivar', $rowNames, true), 'debe conservar transition desactivar');
        assert_true(in_array('activar', $rowNames, true), 'debe conservar transition activar');
    }
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Crud/Action/DemoProductoToggleRemoved`  
Expected: FAIL — clase/archivo/registry/JSON aún presentes.

- [x] **Step 3: Implementar el cambio mínimo**

1. Borrar `src/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php`.
2. En `config/crud_handlers.php` y `skeleton/config/crud_handlers.php`, eliminar la línea:
   `'demo_producto_toggle' => \Lebytek\Framework\Application\Crud\Handlers\DemoProductoToggleStatusHandler::class,`
3. En ambos `demo_productos.json`, eliminar:
   - el objeto row `{ "name": "toggle", "type": "handler", "handler": "demo_producto_toggle", ... }`
   - el objeto bulk homónimo
4. Conservar `desactivar` / `activar` (transitions) y `demo_producto_state_guard`.
5. `cmp` harness vs skeleton para JSON y `crud_handlers.php`.

No reescribir el toggle para llamar a `CrudTransitionService` (sin DI = G5; bulk transitions no existen en `runBulk`).

- [x] **Step 4: Verificación enfocada**

Run: `php tests/run.php Crud/Action/DemoProductoToggleRemoved Crud/State/CrudDemoStatesFormLock`  
Expected: PASS

- [x] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/Action Crud/State/CrudTransitionService`  
Expected: PASS — transitions y C2 permission intactos; ningún test debe referenciar `demo_producto_toggle` (si alguno lo hace, actualizarlo a `activar`/`desactivar`).

- [x] **Step 6: Commit**

```bash
git add -u src/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php \
  config/crud_handlers.php skeleton/config/crud_handlers.php \
  config/cruds/demo_productos.json skeleton/config/cruds/demo_productos.json \
  tests/Crud/Action/DemoProductoToggleRemovedTest.php
git commit -m "fix(crud): remove demo status toggle bypassing state machine (G15)"
```

---

### Task 5: G6 — revalidar opciones `select` en servidor

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p02-states-form-options`  
**Depends on:** None (puede paralelizarse tras Task 1; no depende de demos)  
**Files:**
- Create: `tests/Crud/Validation/CrudFieldSelectOptionsTest.php`
- Modify: `src/Application/Services/CrudFieldValidationService.php` (`validateValue`)
- Test: `tests/Crud/Validation/CrudFieldSelectOptionsTest.php`
**Interfaces:**
- Consumes: `CrudFieldDefinition::fromArray`, `CrudFieldValidationService::validateValue`
- Produces: deny de valores fuera de `options` / `validation.in` para `type=select`

- [x] **Step 1: Escribir el test que falla** — crear `tests/Crud/Validation/CrudFieldSelectOptionsTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudFieldValidationService;
use Lebytek\Framework\Domain\Entities\CrudFieldDefinition;

function crud_p02_select_field(array $data): CrudFieldDefinition
{
    return CrudFieldDefinition::fromArray($data);
}

test('select con options rechaza valor fuera de allowlist (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'status',
        'label' => 'Estado',
        'type' => 'select',
        'required' => true,
        'options' => [
            'activo' => 'Activo',
            'inactivo' => 'Inactivo',
        ],
    ]);
    $errors = $svc->validateValue($field, 'hacked');
    assert_same(['Valor no permitido.'], $errors);
});

test('select con options acepta clave válida (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'status',
        'label' => 'Estado',
        'type' => 'select',
        'options' => [
            'activo' => 'Activo',
            'inactivo' => 'Inactivo',
        ],
    ]);
    assert_same([], $svc->validateValue($field, 'activo'));
});

test('validation.in explícito tiene prioridad sobre options (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'status',
        'label' => 'Estado',
        'type' => 'select',
        'options' => [
            'activo' => 'Activo',
            'inactivo' => 'Inactivo',
        ],
        'validation' => [
            'in' => ['activo'],
            'messages' => ['in' => 'Solo activo'],
        ],
    ]);
    assert_same(['Solo activo'], $svc->validateValue($field, 'inactivo'));
    assert_same([], $svc->validateValue($field, 'activo'));
});

test('select sin options no inventa allowlist (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'color',
        'label' => 'Color',
        'type' => 'select',
    ]);
    assert_same([], $svc->validateValue($field, 'cualquier-cosa'));
});

test('relation no usa options del field como in automático (G6 scope)', function (): void {
    // Las options de relation se resuelven en FormBuilder vía CrudRelationService;
    // el field JSON no las trae. exists sigue siendo la regla de integridad (punto 7 soft-delete).
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'categoria_id',
        'label' => 'Categoría',
        'type' => 'relation',
        'relation' => 'categoria',
        'options' => ['1' => 'A', '2' => 'B'], // si alguien las pone, no las tratamos como select auto-in
    ]);
    assert_same([], $svc->validateValue($field, '999'));
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo**

Run: `php tests/run.php Crud/Validation/CrudFieldSelectOptions`  
Expected: FAIL — primer test (hoy no hay allowlist desde `options`).

- [x] **Step 3: Implementar el cambio mínimo** — en `CrudFieldValidationService::validateValue`, **reemplazar** el bloque actual de `rules['in']` (líneas ~236–240) por:

```php
        $allowed = null;
        if (isset($rules['in']) && is_array($rules['in'])) {
            $allowed = array_map('strval', $rules['in']);
        } elseif ($field->type() === 'select') {
            $options = $field->options();
            if ($options !== []) {
                $allowed = array_map('strval', array_keys($options));
            }
        }
        if ($allowed !== null && !in_array((string) $normalized, $allowed, true)) {
            $errors[] = $this->msg($rules, 'in', 'Valor no permitido.');
        }
```

No inyectar `CrudRelationService` aquí (punto 7 / exists cubre FK; auto-in solo `select`).

- [x] **Step 4: Verificación enfocada**

Run: `php tests/run.php Crud/Validation/CrudFieldSelectOptions`  
Expected: PASS

- [x] **Step 5: Regresión relevante**

Run: `php tests/run.php Crud/Validation`  
Expected: PASS (`CrudFieldMessages` y constraints intactos)

- [x] **Step 6: Commit**

```bash
git add src/Application/Services/CrudFieldValidationService.php tests/Crud/Validation/CrudFieldSelectOptionsTest.php
git commit -m "fix(crud): enforce select options allowlist on server (G6)"
```

---

### Task 6: Docs estados + semver `1.2.7` + regresión cruzada (p01 + p02)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p02-states-form-options`  
**Depends on:** Task 1–5  
**Files:**
- Modify: `docs/modules/crud/modulo-crud-engine.md` (sección Fase 2 — Estados)
- Modify: `composer.json`, `config/app.php`, `skeleton/config/app.php`
- Test: suites listadas abajo
**Interfaces:**
- Consumes: trío semver @ `1.2.6`
- Produces: trío @ `1.2.7`; docs alineadas a C3/G15

- [x] **Step 1: Escribir el test que falla** — N/A para semver hasta editar. Primero confirmar baseline:

```bash
rg -n "version" composer.json config/app.php skeleton/config/app.php | head -20
```

Expected: `1.2.6` en los tres (o anotar tip real).

- [x] **Step 2: Ejecutar regresión funcional antes del bump**

Run: `php tests/run.php Crud/State Crud/Validation/CrudFieldSelectOptions Crud/Action/DemoProductoToggleRemoved Security/CrudActionOwnership Crud/Action/CrudActionPermission Reporte/CrudReporteDataSourceAuthz`  
Expected: PASS

- [x] **Step 3: Implementar docs + bump**

En `docs/modules/crud/modulo-crud-engine.md`, sección **Fase 2 — Estados / transiciones**, actualizar el bullet de `column` y el párrafo Demo:

Sustituir/ampliar el bullet `- **column**: ...` para incluir (texto exacto a integrar):

```markdown
- **`column`**: columna del registro que guarda el estado. Obligatoria, debe existir en la tabla y no puede ser una columna protegida (`id`, `created_*`, `updated_*`, `deleted*`). **No puede aparecer en `form.fields`**: el motor rechaza esa colisión en `CrudConfigValidator` y la omite del payload de create/update. El estado inicial en create debe venir del `DEFAULT` SQL de la columna, de un hook `beforeCreate`, o de una acción `type: transition` posterior — nunca de un `<select>` del formulario. Cambios de estado en runtime **solo** vía acciones `type: transition` (`CrudTransitionService`); no uses un `type: handler` que haga `updateRecord` de esa columna.
```

En el párrafo **Demo** de la misma sección, asegurar que menciona `desactivar`/`activar` y **no** un toggle handler. Si el texto aún implica edición de estado en form, corregirlo.

Bump semver a la **misma** versión en:

- `composer.json` → `"version": "1.2.7"`
- `config/app.php` → `'version' => '1.2.7'`
- `skeleton/config/app.php` → `'version' => '1.2.7'`

Si el tip ya es `≥1.2.7`, usar el siguiente PATCH libre en los tres.

- [x] **Step 4: Verificación enfocada**

Run: `php tests/run.php PlatformVersionSemver`  
Expected: PASS @ `1.2.7`

- [x] **Step 5: Regresión relevante (gate final del punto)**

Run: `php tests/run.php Security Crud/Scope Crud/Action Crud/State Crud/Validation Reporte PlatformVersionSemver Kernel/SkeletonPurity`  
Expected: PASS — 0 failed.

Smoke espejo:

```bash
cmp config/cruds/demo_productos.json skeleton/config/cruds/demo_productos.json
cmp config/cruds/demo_pedidos.json skeleton/config/cruds/demo_pedidos.json
cmp config/cruds/demo_citas.json skeleton/config/cruds/demo_citas.json
cmp config/crud_handlers.php skeleton/config/crud_handlers.php
test ! -f src/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php
rg -n 'demo_producto_toggle' config skeleton || true
```

Expected: `cmp` exit 0; archivo handler ausente; `rg` sin matches de `demo_producto_toggle`.

- [x] **Step 6: Commit**

```bash
git add docs/modules/crud/modulo-crud-engine.md composer.json config/app.php skeleton/config/app.php
git commit -m "chore(release): bump platform version to 1.2.7 for CRUD states p02"
```

Tag `v1.2.7` **solo post-merge** en `main` (operador / release); no taggear desde la rama feature.

---

## Criterios de aceptación (punto 2)

- [x] **C3:** `statesBlockErrors` rechaza configs con `states.column` igual a algún `form.fields[].name` (mensaje exacto).
- [x] **C3:** Demos `demo_productos` / `demo_pedidos` / `demo_citas` (harness + skeleton) no exponen la columna de estados en el form; list/detail/transitions siguen.
- [x] **C3:** `buildPayload` no escribe la columna de la SM aunque un field mal configurado la declare.
- [x] **G15:** No existe `DemoProductoToggleStatusHandler` ni clave `demo_producto_toggle`; JSON sin action `toggle`; transitions `activar`/`desactivar` vivas.
- [x] **G6:** `type=select` con `options` no vacías rechaza valores fuera de las claves; `validation.in` explícito manda; `relation` no usa auto-in de options del field.
- [x] Tests nuevos del plan en verde.
- [x] No regresión p01: `Security/CrudActionOwnership`, `Crud/Action/CrudActionPermission`, `Reporte/CrudReporteDataSourceAuthz` PASS.
- [x] Semver trío @ `1.2.7` + `PlatformVersionSemver` PASS.
- [x] `SkeletonPurity` PASS; espejos `cmp` OK.
- [x] Diff **sin** CAS (C4/G13), uploads (C6), router RBAC (G4), bulk equality (G1/G14), Portal business.

## Fuera de alcance

- IDs de otros puntos: C1/C2/C5 (ya cerrados), C6, C4/G13/G1/G14, G12, G4/G10/G16, G7–G9, M*, B*, G2/G3, G5/M14 (DI/interfaces action).
- Bulk `type=transition` en `runBulk` (no existe hoy; no inventar en p02).
- Auto-allowlist de `type=relation` vía `CrudRelationService::optionsFor` (queda `exists`; soft-delete de exists = punto 7).
- Rewire del toggle a `CrudTransitionService` (se elimina).
- Portal bump `composer.lock`, WhatsApi, legacy `feature/backoffice-api-integration`.
- Plan histórico `2026-08-06-audit-crud-rbac-router.md` (punto 6).

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Consumidores con `states.column` en form | Validator rompe cold-load a propósito; docs + mensaje explícito; Portal debe quitar el field antes del bump |
| Create sin DEFAULT SQL en tabla custom | Documentado: DEFAULT, hook `beforeCreate`, o transition post-create; demos del paquete ya tienen DEFAULT |
| Test reflexión `buildPayload` frágil | Fallback a helper `isFormWritableColumn` estático (Step 3 Task 3) |
| Alguien reintroduce toggle en JSON sin clase | Test `DemoProductoToggleRemoved` + ausencia de registry key |
| Select sin `options` (UI libre) | Sin allowlist automática (test explícito); no forzar options obligatorias en p02 |
| Semver choca si otro PATCH mergea antes | Task 6 relee tip y elige siguiente PATCH libre ≥1.2.7 |
| PHP CLI ausente en agente | Ejecutor requiere entorno con PHP ≥8.1 |

**Rollback:** revertir PR del punto 2; punto 1 (AuthZ) permanece. No restaurar el toggle en un hotfix parcial: o se revierte el lote completo C3+G15+G6 o se mantiene cerrado.

## Evidencia que debe recopilar el ejecutor

- Salida de:
  - `php tests/run.php Crud/State/CrudConfigValidatorStates`
  - `php tests/run.php Crud/State/CrudDemoStatesFormLock`
  - `php tests/run.php Crud/State/CrudDataServiceStateColumnWrite`
  - `php tests/run.php Crud/Action/DemoProductoToggleRemoved`
  - `php tests/run.php Crud/Validation/CrudFieldSelectOptions`
  - `php tests/run.php Security Crud/Scope Crud/Action Crud/State Crud/Validation Reporte PlatformVersionSemver Kernel/SkeletonPurity`
  - `cmp` de los cuatro pares harness↔skeleton listados
- Contraste breve por ID:
  - **C3:** antes form podía POST `status`/`estado`; después validator + demos + strip.
  - **G15:** antes `demo_producto_toggle` hacía `updateRecord(status)`; después archivo/registry/JSON ausentes; solo transitions.
  - **G6:** antes `options` solo UI; después `validateValue` deny `hacked` en select.
- PR URL Framework + SHA final de la rama de implementación.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| **Reconciliación UTC** | 2026-08-09 |
| **`origin/main` verificado** | `487ccd8132e7c42eabd2a0e3b335b075ccc123e1` |
| **Completadas / totales** | 6 / 6 |
| **Implementación** | PR [#100](https://github.com/Parzival2103/Lebytek_Framework/pull/100) merge @ `60477dcf` — `fix(crud): P02 states form lock + select options + remove demo toggle (C3/G15/G6)` |
| **Evidencia por tarea** | Task 1: `CrudConfigValidator::statesBlockErrors` L617 + `tests/Crud/State/CrudConfigValidatorStatesTest.php` · Task 2: demos JSON sin form state column + `CrudDemoStatesFormLockTest.php` · Task 3: `CrudDataService::buildPayload` L545–554 + `CrudDataServiceStateColumnWriteTest.php` · Task 4: handler borrado + `DemoProductoToggleRemovedTest.php` · Task 5: `CrudFieldValidationService` L239–247 + `CrudFieldSelectOptionsTest.php` · Task 6: semver trío `1.2.7` + docs `modulo-crud-engine.md` |
| **Siguiente tarea ejecutable** | N/A — plan completo |
| **Bloqueos** | Tag Composer `v1.2.7` (REL-C1) aún no publicado en GitHub — semver en tip pero consumidores en `v1.2.3` |
| **Estado** | **Completo** — archivado 2026-08-09 |
