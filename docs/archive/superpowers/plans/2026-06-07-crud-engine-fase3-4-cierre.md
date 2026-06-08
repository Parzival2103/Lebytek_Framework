# CRUD Engine — Cierre del módulo (Fase 3 Validaciones + Fase 4 Relaciones/Tabs + Demo Showcase + Instalador) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Terminar el CRUD Engine implementando las fases que faltan del spec (validaciones declarativas/complejas y relaciones + tabs en detalle) y extender los módulos demo + un script instalador para que cada despliegue muestre todo el potencial del motor.

**Architecture:** Onion/MVC ya establecido. Todo bloque de metadata nuevo es **opcional** (compatibilidad total). La lógica de negocio vive en handlers/validators/guards externos whitelisteados (Application/Crud/Handlers); los servicios `Crud*` solo orquestan. Repositorio genérico expone métodos nuevos detrás de **interfaces de Domain segregadas** para mantener unit-tests sin DB. Identificadores y operadores SQL siempre desde whitelist (`quoteIdentifier`).

**Tech Stack:** PHP 8.1, MySQL 8, JS vanilla, Bootstrap 5. Sin frameworks. Tests con el harness propio `tests/lib/microtest.php` (funciones `test`, `assert_true`, `assert_same`, `assert_null`, `assert_throws`), ejecutados con `php tests/run.php [filtro]`.

---

## Contexto: qué ya existe (no reimplementar)

- **Fase 0** (fundación): familia `app/Application/Crud/Context/*` (incluye `CrudValidationContext`, `CrudListContext`, `CrudFormContext`), interfaces segregadas (`CrudValidatorInterface`, `CrudListScopeInterface`, `CrudActionHandlerInterface`, `CrudTransitionGuardInterface`), `CrudHandlerRegistry::resolve(key, interface)`, `CrudHookRunner` con contexto tipado + read-back.
- **Fase 1** (acciones + bulk): `CrudActionDefinition`, `CrudActionService`, `CrudActionResolver`, rutas `accion`/`accion-masiva`, partials `actions_row.php`/`actions_bulk.php`, `public/assets/js/crud-engine.js`.
- **Fase 2** (estados): `CrudStateMachine`, `CrudTransitionService`, acciones `type: transition`, badges en header de `show.php`.
- `CrudConfigValidator` ya valida `actions`/`states` (forma + DB) con métodos estáticos `actionsBlockErrors()`/`statesBlockErrors()`/`newBlockShapeErrors()`.
- `GenericCrudRepository` es `final` y su `quoteIdentifier()` es **privado** (los métodos nuevos viven dentro de la misma clase).
- Demo actual: `config/cruds/demo_clientes.json`, `config/cruds/demo_productos.json` (+ `clientes.json` real). Tablas demo en `database/migrations/20260428132500_crud_engine_demo_resources.sql`.

## Lo que falta (este plan)

- **Fase 3 — Validaciones:** mensajes custom por regla, `unique`/`exists` vía DB, validadores de formulario externos (`form.validators`), validación de config de esos bloques.
- **Fase 4 — Relaciones + tabs:** `belongsTo` (selects), `hasMany` (tabs read-only), `detail.tabs` con `CrudDetailBuilder`, reescritura de `show.php`, validación de config.
- **Demo Showcase:** extender `demo_productos`/`demo_clientes`, nuevos recursos `demo_categorias` y `demo_pedidos` (+ tabla hija `dom_demo_pedido_items` para `hasMany`), handlers/validators/guards demo.
- **Instalador:** `scripts/install.php` idempotente (schema → migrations → seeds) para dejar la app + demo listas en cada despliegue.

## File Structure (qué archivo hace qué)

**Fase 3**
- `app/Application/Services/CrudFieldValidationService.php` — (modificar) mensajes custom por regla.
- `app/Domain/Interfaces/CrudConstraintRepositoryInterface.php` — (crear) contrato `existsForUnique` / `existsForReference`.
- `app/Infrastructure/Repositories/GenericCrudRepository.php` — (modificar) implementa el contrato.
- `app/Application/Services/CrudDbConstraintValidator.php` — (crear) reglas `unique`/`exists`.
- `app/Domain/Entities/CrudResourceDefinition.php` — (modificar) `formValidators()`, y más abajo relaciones/detalle.
- `app/Application/Services/CrudDataService.php` — (modificar) integra DB-constraints + validadores de formulario.
- `app/Application/Services/CrudConfigValidator.php` — (modificar) valida `unique`/`exists`/`form.validators`.

**Fase 4**
- `app/Domain/Entities/Crud/CrudRelationDefinition.php` — (crear) VO belongsTo/hasMany.
- `app/Domain/Entities/Crud/CrudTabDefinition.php` — (crear) VO de pestaña.
- `app/Domain/Interfaces/CrudRelationRepositoryInterface.php` — (crear) `distinctOptions` / `childrenBy`.
- `app/Infrastructure/Repositories/GenericCrudRepository.php` — (modificar) implementa el contrato de relaciones.
- `app/Application/Services/CrudRelationService.php` — (crear) opciones de select + filas hijas.
- `app/Domain/Entities/CrudFieldDefinition.php` — (modificar) `relation()`.
- `app/Application/Services/CrudFormBuilder.php` — (modificar) resuelve campos `type: relation`.
- `app/Domain/Interfaces/BitacoraRepositoryInterface.php` + `app/Infrastructure/Repositories/BitacoraRepository.php` — (modificar) `porRegistro()`.
- `app/Application/Services/CrudDetailBuilder.php` — (crear) view-model de tabs.
- `app/Application/Services/CrudResourceService.php` — (modificar) inyecta detail builder y publica `tabs`.
- `app/Presentation/Views/admin/crud/show.php` — (reescribir) nav-tabs.
- `app/Presentation/Views/admin/crud/partials/tab_fields.php`, `tab_relation.php`, `tab_history.php` — (crear).
- `config/container.php` — (modificar) bindings additivos.

**Demo + instalador**
- `database/migrations/20260607120000_crud_engine_demo_showcase.sql` — (crear).
- `config/cruds/demo_categorias.json`, `config/cruds/demo_pedidos.json` — (crear).
- `config/cruds/demo_productos.json`, `config/cruds/demo_clientes.json` — (modificar).
- `app/Application/Crud/Handlers/DemoPedidoTotalValidator.php`, `DemoPedidoPagarGuard.php`, `DemoClienteContactoValidator.php` — (crear).
- `config/crud_handlers.php` — (modificar) registrar handlers demo.
- `scripts/install.php` — (crear).
- `docs/modules/crud/modulo-crud-engine.md` — (modificar).

---

## Baseline: confirmar arranque verde

- [ ] **Step 1: Ejecutar la suite completa antes de tocar nada**

Run: `php tests/run.php`
Expected: termina con `N passed, 0 failed` (exit 0). Si algo falla, detente y reporta antes de continuar.

---

# PARTE A — Fase 3: Validaciones

### Task 1: Mensajes custom por regla en `CrudFieldValidationService`

**Files:**
- Modify: `app/Application/Services/CrudFieldValidationService.php`
- Test: `tests/Crud/Validation/CrudFieldMessagesTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Crud/Validation/CrudFieldMessagesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudFieldValidationService;
use App\Domain\Entities\CrudFieldDefinition;

function field_with(array $data): CrudFieldDefinition
{
    return CrudFieldDefinition::fromArray($data);
}

test('CrudFieldValidationService: custom message overrides the default for required', function (): void {
    $svc = new CrudFieldValidationService();
    $field = field_with([
        'name' => 'codigo', 'label' => 'Código', 'type' => 'text', 'required' => true,
        'validation' => ['messages' => ['required' => 'El código es obligatorio']],
    ]);
    $errors = $svc->validateValue($field, '');
    assert_same(['El código es obligatorio'], $errors);
});

test('CrudFieldValidationService: default message is used when no override exists', function (): void {
    $svc = new CrudFieldValidationService();
    $field = field_with(['name' => 'codigo', 'label' => 'Código', 'type' => 'text', 'required' => true]);
    $errors = $svc->validateValue($field, '');
    assert_same(['Este campo es obligatorio.'], $errors);
});

test('CrudFieldValidationService: custom message overrides maxlength', function (): void {
    $svc = new CrudFieldValidationService();
    $field = field_with([
        'name' => 'codigo', 'label' => 'Código', 'type' => 'text',
        'validation' => ['maxlength' => 3, 'messages' => ['maxlength' => 'Máximo 3 caracteres']],
    ]);
    $errors = $svc->validateValue($field, 'ABCD');
    assert_same(['Máximo 3 caracteres'], $errors);
});
```

- [ ] **Step 2: Ejecutar el test para verque falla**

Run: `php tests/run.php CrudFieldMessagesTest`
Expected: FAIL en el primer y tercer test (devuelven el mensaje por defecto, no el custom).

- [ ] **Step 3: Implementar mensajes custom**

En `app/Application/Services/CrudFieldValidationService.php`, reemplaza **íntegramente** el método `validateValue()` por esta versión (idéntica a la actual salvo que cada mensaje pasa por `$this->msg(...)`):

```php
    /**
     * @return list<string>
     */
    public function validateValue(CrudFieldDefinition $field, mixed $normalized): array
    {
        $errors = [];
        $rules = $field->validation();
        if (!is_array($rules)) {
            $rules = [];
        }

        $required = (bool) ($rules['required'] ?? $field->required());
        $effectiveType = $this->effectiveValidationType($field);

        if ($field->type() === 'checkbox') {
            if ($normalized !== 0 && $normalized !== 1) {
                $errors[] = $this->msg($rules, 'checkbox', 'Valor de casilla inválido.');

                return $errors;
            }
            if ($required && $normalized !== 1) {
                $errors[] = $this->msg($rules, 'required', 'Debe marcar esta opción.');
            }

            return $errors;
        }

        if ($required && ($normalized === null || $normalized === '')) {
            $errors[] = $this->msg($rules, 'required', 'Este campo es obligatorio.');

            return $errors;
        }

        if ($normalized === null || $normalized === '') {
            return $errors;
        }

        if (isset($rules['minlength']) && is_string($normalized) && mb_strlen($normalized) < (int) $rules['minlength']) {
            $errors[] = $this->msg($rules, 'minlength', 'Longitud mínima no cumplida.');
        }
        if (isset($rules['maxlength']) && is_string($normalized) && mb_strlen($normalized) > (int) $rules['maxlength']) {
            $errors[] = $this->msg($rules, 'maxlength', 'Longitud máxima excedida.');
        }

        if ($effectiveType === 'email' || $field->type() === 'email') {
            if (!is_string($normalized) || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $this->msg($rules, 'email', 'Correo electrónico inválido.');
            }
        }

        if ($effectiveType === 'integer' || $effectiveType === 'int') {
            if (is_float($normalized)) {
                $errors[] = $this->msg($rules, 'integer', 'Debe ser un número entero válido.');
            } else {
                $asString = is_int($normalized) ? (string) $normalized : trim((string) $normalized);
                if (!$this->isStrictIntegerString($asString)) {
                    $errors[] = $this->msg($rules, 'integer', 'Debe ser un número entero válido.');
                } else {
                    $intVal = (int) $asString;
                    if (isset($rules['min']) && $intVal < (int) $rules['min']) {
                        $errors[] = $this->msg($rules, 'min', 'Valor menor al mínimo permitido.');
                    }
                    if (isset($rules['max']) && $intVal > (int) $rules['max']) {
                        $errors[] = $this->msg($rules, 'max', 'Valor mayor al máximo permitido.');
                    }
                }
            }
        }

        if ($effectiveType === 'numeric' || $effectiveType === 'decimal' || $effectiveType === 'money') {
            if (!is_string($normalized) && !is_int($normalized) && !is_float($normalized)) {
                $errors[] = $this->msg($rules, 'numeric', 'Debe ser un valor numérico.');
            } elseif (!$this->isStrictDecimal((string) $normalized)) {
                $errors[] = $this->msg($rules, 'numeric', 'Formato numérico inválido.');
            } else {
                $floatVal = (float) str_replace(',', '.', (string) $normalized);
                if (!is_finite($floatVal)) {
                    $errors[] = $this->msg($rules, 'numeric', 'Valor numérico no permitido.');
                } else {
                    if (isset($rules['min']) && $floatVal < (float) $rules['min']) {
                        $errors[] = $this->msg($rules, 'min', 'Valor menor al mínimo permitido.');
                    }
                    if (isset($rules['max']) && $floatVal > (float) $rules['max']) {
                        $errors[] = $this->msg($rules, 'max', 'Valor mayor al máximo permitido.');
                    }
                }
            }
        }

        if ($effectiveType === 'string' || $effectiveType === 'text') {
            if (!is_string($normalized) && !is_numeric($normalized)) {
                $errors[] = $this->msg($rules, 'string', 'Debe ser texto.');
            }
        }

        if ($effectiveType === 'boolean' || $effectiveType === 'bool') {
            if (!is_int($normalized) || ($normalized !== 0 && $normalized !== 1)) {
                $errors[] = $this->msg($rules, 'boolean', 'Valor booleano inválido.');
            }
        }

        if ($effectiveType === 'date') {
            if (!is_string($normalized) || !$this->isValidDateYmd($normalized)) {
                $errors[] = $this->msg($rules, 'date', 'Fecha inválida. Use el formato AAAA-MM-DD.');
            }
        }

        if ($effectiveType === 'datetime') {
            if (!is_string($normalized) || !$this->isValidDateTime($normalized)) {
                $errors[] = $this->msg($rules, 'datetime', 'Fecha y hora inválidas.');
            }
        }

        if (isset($rules['in']) && is_array($rules['in'])) {
            if (!in_array((string) $normalized, array_map('strval', $rules['in']), true)) {
                $errors[] = $this->msg($rules, 'in', 'Valor no permitido.');
            }
        }

        if (isset($rules['regex']) && is_string($rules['regex'])) {
            $pattern = $rules['regex'];
            if (!$this->isSafeRegexPattern($pattern)) {
                $errors[] = $this->msg($rules, 'regex', 'Regla de formato no disponible.');
            } elseif (is_string($normalized) && preg_match($pattern, $normalized) !== 1) {
                $errors[] = $this->msg($rules, 'regex', 'Formato inválido.');
            }
        }

        return $errors;
    }

    /**
     * Devuelve el mensaje custom de `validation.messages[$key]` si existe y es
     * un string no vacío; en caso contrario el mensaje por defecto.
     *
     * @param array<string, mixed> $rules
     */
    private function msg(array $rules, string $key, string $default): string
    {
        $messages = $rules['messages'] ?? null;
        if (is_array($messages) && isset($messages[$key]) && is_string($messages[$key]) && $messages[$key] !== '') {
            return $messages[$key];
        }

        return $default;
    }
```

- [ ] **Step 4: Ejecutar el test (debe pasar) y verificar regresión**

Run: `php tests/run.php CrudFieldMessagesTest`
Expected: PASS.
Run: `php -l app/Application/Services/CrudFieldValidationService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudFieldValidationService.php tests/Crud/Validation/CrudFieldMessagesTest.php
git commit -m "feat(crud): mensajes de validacion personalizables por regla"
```

---

### Task 2: Contrato `CrudConstraintRepositoryInterface` + implementación en el repositorio

**Files:**
- Create: `app/Domain/Interfaces/CrudConstraintRepositoryInterface.php`
- Modify: `app/Infrastructure/Repositories/GenericCrudRepository.php`

- [ ] **Step 1: Crear la interfaz de Domain**

`app/Domain/Interfaces/CrudConstraintRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

/**
 * Contrato mínimo para validar constraints de DB del CRUD Engine.
 * Implementado por GenericCrudRepository (Infraestructura). Existe como
 * interfaz para mantener CrudDbConstraintValidator unit-testable sin DB.
 */
interface CrudConstraintRepositoryInterface
{
    /**
     * ¿Existe OTRA fila no borrada con ese valor en la columna? (unique).
     * Excluye la fila $exceptId cuando se provee (ignore_self en update).
     */
    public function existsForUnique(string $table, string $column, mixed $value, string $primaryKey, ?int $exceptId): bool;

    /**
     * ¿Existe al menos una fila con ese valor en la columna? (exists / FK).
     */
    public function existsForReference(string $table, string $column, mixed $value): bool;
}
```

- [ ] **Step 2: Implementar en `GenericCrudRepository`**

En `app/Infrastructure/Repositories/GenericCrudRepository.php`:

1. Cambia la declaración de clase para que implemente la interfaz:

```php
use App\Domain\Interfaces\CrudConstraintRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class GenericCrudRepository extends BaseRepository implements CrudConstraintRepositoryInterface
{
```

2. Agrega estos dos métodos **antes** de `private function quoteIdentifier(...)`:

```php
    public function existsForUnique(string $table, string $column, mixed $value, string $primaryKey, ?int $exceptId): bool
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeCol   = $this->quoteIdentifier($column);
        $safePk    = $this->quoteIdentifier($primaryKey);

        $sql = "SELECT COUNT(*) AS total FROM {$safeTable} WHERE {$safeCol} = ? AND `deleted` = 0";
        $params = [$value];
        if ($exceptId !== null) {
            $sql .= " AND {$safePk} <> ?";
            $params[] = $exceptId;
        }

        $row = $this->queryOne($sql, $params);

        return ((int) ($row['total'] ?? 0)) > 0;
    }

    public function existsForReference(string $table, string $column, mixed $value): bool
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeCol   = $this->quoteIdentifier($column);

        $row = $this->queryOne(
            "SELECT COUNT(*) AS total FROM {$safeTable} WHERE {$safeCol} = ? LIMIT 1",
            [$value]
        );

        return ((int) ($row['total'] ?? 0)) > 0;
    }
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l app/Infrastructure/Repositories/GenericCrudRepository.php`
Expected: `No syntax errors detected`.
Run: `php -l app/Domain/Interfaces/CrudConstraintRepositoryInterface.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Interfaces/CrudConstraintRepositoryInterface.php app/Infrastructure/Repositories/GenericCrudRepository.php
git commit -m "feat(crud): metodos de constraint unique/exists en el repositorio generico"
```

---

### Task 3: `CrudDbConstraintValidator` (`unique` / `exists`)

**Files:**
- Create: `app/Application/Services/CrudDbConstraintValidator.php`
- Create: `tests/fixtures/constraint_repos.php`
- Test: `tests/Crud/Validation/CrudDbConstraintValidatorTest.php`

- [ ] **Step 1: Crear el fixture de repositorio en memoria**

`tests/fixtures/constraint_repos.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Interfaces\CrudConstraintRepositoryInterface;

if (!class_exists('FakeConstraintRepository')) {
    /** Repositorio en memoria para probar CrudDbConstraintValidator sin DB. */
    class FakeConstraintRepository implements CrudConstraintRepositoryInterface
    {
        /** @var array<string, bool> clave "table.column.value[.except]" => bool */
        public array $unique = [];
        /** @var array<string, bool> clave "table.column.value" => bool */
        public array $reference = [];

        public function existsForUnique(string $table, string $column, mixed $value, string $primaryKey, ?int $exceptId): bool
        {
            $key = $table . '.' . $column . '.' . (string) $value . '.' . ($exceptId ?? 'null');

            return $this->unique[$key] ?? false;
        }

        public function existsForReference(string $table, string $column, mixed $value): bool
        {
            return $this->reference[$table . '.' . $column . '.' . (string) $value] ?? false;
        }
    }
}
```

- [ ] **Step 2: Escribir el test que falla**

`tests/Crud/Validation/CrudDbConstraintValidatorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudDbConstraintValidator;
use App\Domain\Entities\CrudResourceDefinition;

require_once dirname(__DIR__, 2) . '/fixtures/constraint_repos.php';

function constraint_definition(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo_pedidos', 'title' => 'Pedidos', 'table' => 'dom_demo_pedidos',
            'primary_key' => 'id', 'permission_prefix' => 'demo_pedidos',
        ],
        'form' => ['fields' => [
            ['name' => 'folio', 'label' => 'Folio', 'type' => 'text',
             'validation' => ['unique' => ['ignore_self' => true], 'messages' => ['unique' => 'Folio repetido']]],
            ['name' => 'cliente_id', 'label' => 'Cliente', 'type' => 'relation', 'relation' => 'cliente',
             'validation' => ['exists' => ['table' => 'dom_demo_clientes', 'column' => 'id']]],
        ]],
    ]);
}

test('CrudDbConstraintValidator: unique conflict adds custom message', function (): void {
    $repo = new FakeConstraintRepository();
    $repo->unique['dom_demo_pedidos.folio.P-1.7'] = true; // existe otra fila con ese folio
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'P-1', 'cliente_id' => 3], 7);
    assert_same(['Folio repetido'], $errors['folio'] ?? []);
});

test('CrudDbConstraintValidator: unique passes when no conflict', function (): void {
    $repo = new FakeConstraintRepository();
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'P-1', 'cliente_id' => 3], null);
    assert_same([], $errors);
});

test('CrudDbConstraintValidator: exists failure adds default message', function (): void {
    $repo = new FakeConstraintRepository();
    // reference vacía => cliente_id 3 no existe
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'NUEVO', 'cliente_id' => 3], null);
    assert_same(['El valor seleccionado no es válido.'], $errors['cliente_id'] ?? []);
});

test('CrudDbConstraintValidator: exists passes when reference present', function (): void {
    $repo = new FakeConstraintRepository();
    $repo->reference['dom_demo_clientes.id.3'] = true;
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'NUEVO', 'cliente_id' => 3], null);
    assert_same([], $errors);
});
```

- [ ] **Step 3: Ejecutar el test para verificar que falla**

Run: `php tests/run.php CrudDbConstraintValidatorTest`
Expected: FAIL con "Class 'App\\Application\\Services\\CrudDbConstraintValidator' not found".

- [ ] **Step 4: Implementar el validador**

`app/Application/Services/CrudDbConstraintValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudFieldDefinition;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\CrudConstraintRepositoryInterface;

/**
 * Aplica constraints de DB declaradas en validation: `unique` y `exists`.
 * Acumula errores por campo (no lanza); el motor los une al resto.
 */
final class CrudDbConstraintValidator
{
    public function __construct(private readonly CrudConstraintRepositoryInterface $repository) {}

    /**
     * @param array<string, mixed> $normalizedByField
     * @return array<string, list<string>>
     */
    public function validate(CrudResourceDefinition $definition, array $normalizedByField, ?int $exceptId): array
    {
        $errors = [];

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition) {
                continue;
            }
            $rules = $field->validation();
            if (!is_array($rules)) {
                continue;
            }
            $name = $field->name();
            if (!array_key_exists($name, $normalizedByField)) {
                continue;
            }
            $value = $normalizedByField[$name];
            if ($value === null || $value === '') {
                continue;
            }

            if (isset($rules['unique']) && $rules['unique'] !== false) {
                $ignoreSelf = is_array($rules['unique']) && !empty($rules['unique']['ignore_self']);
                $except = $ignoreSelf ? $exceptId : null;
                if ($this->repository->existsForUnique($definition->table(), $name, $value, $definition->primaryKey(), $except)) {
                    $errors[$name][] = $this->message($rules, 'unique', 'Ya existe un registro con este valor.');
                }
            }

            if (isset($rules['exists']) && is_array($rules['exists'])) {
                $table = (string) ($rules['exists']['table'] ?? '');
                $column = (string) ($rules['exists']['column'] ?? 'id');
                if ($table !== '' && !$this->repository->existsForReference($table, $column, $value)) {
                    $errors[$name][] = $this->message($rules, 'exists', 'El valor seleccionado no es válido.');
                }
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $rules */
    private function message(array $rules, string $key, string $default): string
    {
        $messages = $rules['messages'] ?? null;
        if (is_array($messages) && isset($messages[$key]) && is_string($messages[$key]) && $messages[$key] !== '') {
            return $messages[$key];
        }

        return $default;
    }
}
```

- [ ] **Step 5: Ejecutar el test (debe pasar)**

Run: `php tests/run.php CrudDbConstraintValidatorTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudDbConstraintValidator.php tests/fixtures/constraint_repos.php tests/Crud/Validation/CrudDbConstraintValidatorTest.php
git commit -m "feat(crud): validador de constraints DB unique/exists"
```

---

### Task 4: `CrudResourceDefinition::formValidators()`

**Files:**
- Modify: `app/Domain/Entities/CrudResourceDefinition.php`
- Test: `tests/Crud/Validation/CrudResourceDefinitionValidatorsTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Crud/Validation/CrudResourceDefinitionValidatorsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;

test('CrudResourceDefinition: formValidators is empty by default', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
    ]);
    assert_same([], $def->formValidators());
});

test('CrudResourceDefinition: formValidators parses form.validators list of strings', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
        'form' => ['validators' => ['anticipo_minimo', 'fecha_disponible', '', 123]],
    ]);
    assert_same(['anticipo_minimo', 'fecha_disponible'], $def->formValidators());
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php tests/run.php CrudResourceDefinitionValidatorsTest`
Expected: FAIL con "Call to undefined method ...::formValidators()".

- [ ] **Step 3: Implementar el accesor**

En `app/Domain/Entities/CrudResourceDefinition.php`:

1. Agrega el parámetro al constructor (al final, después de `?CrudStateMachine $stateMachine`):

```php
        private readonly ?CrudStateMachine $stateMachine,
        private readonly array $formValidators
```

2. En `fromArray()`, antes del `return new self(`, construye la lista:

```php
        $formValidators = [];
        foreach (($form['validators'] ?? []) as $validatorKey) {
            if (is_string($validatorKey) && $validatorKey !== '') {
                $formValidators[] = $validatorKey;
            }
        }
```

3. En la llamada `return new self(...)`, agrega como último argumento nombrado:

```php
            stateMachine: $stateMachine,
            formValidators: $formValidators
```

4. Agrega el accesor junto a los demás:

```php
    /** @return list<string> */
    public function formValidators(): array
    {
        return $this->formValidators;
    }
```

- [ ] **Step 4: Ejecutar el test (debe pasar) y regresión**

Run: `php tests/run.php CrudResourceDefinitionValidatorsTest`
Expected: PASS.
Run: `php tests/run.php CrudResourceDefinition`
Expected: PASS (no se rompen los tests de estados/acciones existentes).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/CrudResourceDefinition.php tests/Crud/Validation/CrudResourceDefinitionValidatorsTest.php
git commit -m "feat(crud): CrudResourceDefinition expone form.validators"
```

---

### Task 5: Integrar DB-constraints + validadores de formulario en `CrudDataService`

**Files:**
- Modify: `app/Application/Services/CrudDataService.php`
- Modify: `config/container.php`

- [ ] **Step 1: Inyectar dependencias nuevas (opcionales) en el constructor**

En `app/Application/Services/CrudDataService.php`, añade imports al inicio:

```php
use App\Application\Crud\Context\CrudValidationContext;
use App\Domain\Interfaces\CrudValidatorInterface;
```

Reemplaza el constructor por:

```php
    public function __construct(
        private readonly GenericCrudRepository $repository,
        private readonly BitacoraRepositoryInterface $bitacoraRepository,
        private readonly CrudHookRunner $hookRunner,
        private readonly CrudFieldValidationService $fieldValidation,
        private readonly ?CrudDbConstraintValidator $dbConstraintValidator = null,
        private readonly ?CrudHandlerRegistry $handlerRegistry = null
    ) {}
```

- [ ] **Step 2: Pasar `userId`/`ip` a `buildPayload` y añadir las cadenas de validación**

En `store()`, cambia la línea:

```php
        $payload = $this->buildPayload($definition, $input, $files, true, null);
```
por:
```php
        $payload = $this->buildPayload($definition, $input, $files, true, null, $userId, $ip);
```

En `update()`, cambia:

```php
        $payload = $this->buildPayload($definition, $input, $files, false, is_array($existing) ? $existing : null);
```
por:
```php
        $payload = $this->buildPayload($definition, $input, $files, false, is_array($existing) ? $existing : null, $userId, $ip);
```

Cambia la firma de `buildPayload`:

```php
    private function buildPayload(CrudResourceDefinition $definition, array $input, array $files, bool $isCreate, ?array $existingRow, ?int $userId = null, string $ip = ''): array
```

Dentro de `buildPayload`, **justo después** del bucle que mezcla los errores de `validatePayload` (el `foreach (... as $fieldName => $fieldErrors)` que termina antes del bloque de archivos `file`) e **antes** del bloque `foreach (...)` que valida archivos requeridos, inserta:

```php
        $exceptId = (!$isCreate && is_array($existingRow))
            ? (((int) ($existingRow[$definition->primaryKey()] ?? 0)) ?: null)
            : null;

        if ($this->dbConstraintValidator !== null) {
            foreach ($this->dbConstraintValidator->validate($definition, $normalizedByField, $exceptId) as $fieldName => $fieldErrors) {
                foreach ($fieldErrors as $msg) {
                    $errors[$fieldName][] = $msg;
                }
            }
        }

        if ($this->handlerRegistry !== null && $definition->formValidators() !== []) {
            $record = (!$isCreate && is_array($existingRow)) ? $existingRow : null;
            $ctx = new CrudValidationContext(
                $definition->key(),
                $definition->table(),
                $definition->primaryKey(),
                $userId,
                $ip,
                $input,
                $normalizedByField,
                $record,
                !$isCreate
            );
            foreach ($definition->formValidators() as $validatorKey) {
                $validator = $this->handlerRegistry->resolve($validatorKey, CrudValidatorInterface::class);
                if ($validator instanceof CrudValidatorInterface) {
                    $validator->validate($ctx);
                }
            }
            foreach ($ctx->errors() as $fieldName => $fieldErrors) {
                foreach ($fieldErrors as $msg) {
                    $errors[$fieldName][] = $msg;
                }
            }
        }
```

> Nota: este bloque queda **antes** del primer `$this->fieldValidation->assertNoErrors($errors);`, de modo que los errores de campo + DB + formulario se acumulan y se lanzan juntos en una sola `ValidationException`.

- [ ] **Step 3: Actualizar el binding del container**

En `config/container.php`, añade el import:

```php
use App\Application\Services\CrudDbConstraintValidator;
```

Registra el validador (después del binding de `CrudFieldValidationService`):

```php
    $container->singleton(CrudDbConstraintValidator::class, fn(Container $c) => new CrudDbConstraintValidator(
        $c->get(GenericCrudRepository::class)
    ));
```

Reemplaza el binding de `CrudDataService` por:

```php
    $container->singleton(CrudDataService::class, fn(Container $c) => new CrudDataService(
        $c->get(GenericCrudRepository::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudHookRunner::class),
        $c->get(CrudFieldValidationService::class),
        $c->get(CrudDbConstraintValidator::class),
        $c->get(CrudHandlerRegistry::class)
    ));
```

- [ ] **Step 4: Verificar sintaxis + suite**

Run: `php -l app/Application/Services/CrudDataService.php`
Expected: `No syntax errors detected`.
Run: `php -l config/container.php`
Expected: `No syntax errors detected`.
Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudDataService.php config/container.php
git commit -m "feat(crud): integra constraints DB y validadores de formulario en la escritura"
```

---

### Task 6: `CrudConfigValidator` valida `unique` / `exists` / `form.validators`

**Files:**
- Modify: `app/Application/Services/CrudConfigValidator.php`
- Test: `tests/Crud/Validation/CrudConfigValidatorValidationsTest.php`

- [ ] **Step 1: Escribir el test que falla (parte pura/estática)**

`tests/Crud/Validation/CrudConfigValidatorValidationsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('CrudConfigValidator: exists sin table es error de forma', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [
            ['name' => 'cliente_id', 'validation' => ['exists' => ['column' => 'id']]],
        ]],
    ]);
    assert_true(in_array("form.fields[0].validation.exists.table es obligatorio.", $errors, true), 'falta error de exists.table');
});

test('CrudConfigValidator: exists con table de prefijo bloqueado es error', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [
            ['name' => 'cliente_id', 'validation' => ['exists' => ['table' => 'auth_usuarios', 'column' => 'id']]],
        ]],
    ]);
    assert_true(
        in_array("form.fields[0].validation.exists.table (auth_usuarios) usa un prefijo bloqueado.", $errors, true),
        'falta error de prefijo bloqueado'
    );
});

test('CrudConfigValidator: unique mal formado es error', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [
            ['name' => 'folio', 'validation' => ['unique' => 'si']],
        ]],
    ]);
    assert_true(in_array("form.fields[0].validation.unique debe ser true u objeto {ignore_self:true}.", $errors, true), 'falta error de unique');
});

test('CrudConfigValidator: config sin constraints no genera errores de forma', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [['name' => 'nombre', 'validation' => ['maxlength' => 60]]]],
    ]);
    assert_same([], $errors);
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php tests/run.php CrudConfigValidatorValidationsTest`
Expected: FAIL con "Call to undefined method ...::validationConstraintShapeErrors()".

- [ ] **Step 3: Implementar el método estático de forma + checks de DB/registry**

En `app/Application/Services/CrudConfigValidator.php`:

1. Agrega el método estático (junto a `actionsBlockErrors`/`statesBlockErrors`):

```php
    /**
     * Valida la forma de los constraints `unique`/`exists` por campo. Pura, sin DB.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function validationConstraintShapeErrors(array $config): array
    {
        $errors = [];
        foreach (($config['form']['fields'] ?? []) as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $rules = $field['validation'] ?? null;
            if (!is_array($rules)) {
                continue;
            }

            if (array_key_exists('unique', $rules)) {
                $u = $rules['unique'];
                $okShape = $u === true || (is_array($u) && (!array_key_exists('ignore_self', $u) || is_bool($u['ignore_self'])));
                if (!$okShape) {
                    $errors[] = "form.fields[{$index}].validation.unique debe ser true u objeto {ignore_self:true}.";
                }
            }

            if (array_key_exists('exists', $rules)) {
                $e = $rules['exists'];
                if (!is_array($e)) {
                    $errors[] = "form.fields[{$index}].validation.exists debe ser un objeto.";
                    continue;
                }
                $table = (string) ($e['table'] ?? '');
                if ($table === '') {
                    $errors[] = "form.fields[{$index}].validation.exists.table es obligatorio.";
                    continue;
                }
                foreach (self::BLOCKED_PREFIXES as $prefix) {
                    if (str_starts_with($table, $prefix)) {
                        $errors[] = "form.fields[{$index}].validation.exists.table ({$table}) usa un prefijo bloqueado.";
                        break;
                    }
                }
            }
        }

        return $errors;
    }
```

2. En `validate()`, después del bloque que llama a `statesBlockErrors`, añade los errores de forma:

```php
        foreach (self::validationConstraintShapeErrors($config) as $constraintError) {
            $errors[] = $constraintError;
        }
```

3. También en `validate()`, después de calcular `$columnLookup`, añade la verificación profunda (existencia de tabla/columna destino de `exists` y registro de `form.validators`). Inserta antes del `if (!empty($errors))` final:

```php
        // exists: la tabla/columna destino debe existir realmente.
        foreach (($config['form']['fields'] ?? []) as $index => $field) {
            if (!is_array($field) || !is_array($field['validation']['exists'] ?? null)) {
                continue;
            }
            $exTable = (string) ($field['validation']['exists']['table'] ?? '');
            $exColumn = (string) ($field['validation']['exists']['column'] ?? 'id');
            if ($exTable === '') {
                continue; // ya reportado por validationConstraintShapeErrors
            }
            if (!$this->repository->tableExists($exTable)) {
                $errors[] = "form.fields[{$index}].validation.exists: la tabla {$exTable} no existe.";
                continue;
            }
            $exCols = array_fill_keys($this->repository->getTableColumns($exTable), true);
            if (!isset($exCols[$exColumn])) {
                $errors[] = "form.fields[{$index}].validation.exists: la columna {$exColumn} no existe en {$exTable}.";
            }
        }

        // form.validators: cada clave debe estar registrada e implementar la interfaz.
        foreach ((is_array($config['form']['validators'] ?? null) ? $config['form']['validators'] : []) as $vIndex => $validatorKey) {
            if (!is_string($validatorKey) || $validatorKey === '') {
                $errors[] = "form.validators[{$vIndex}] debe ser una clave string no vacía.";
                continue;
            }
            if (!$this->handlerRegistry->hasKey($validatorKey)) {
                $errors[] = "form.validators '{$validatorKey}' no está registrado en config/crud_handlers.php.";
                continue;
            }
            $class = $this->handlerRegistry->classForKey($validatorKey);
            if ($class === null || !class_exists($class)) {
                $errors[] = "La clase del validador '{$validatorKey}' no existe o no es autoload-eable.";
                continue;
            }
            if (!in_array(\App\Domain\Interfaces\CrudValidatorInterface::class, class_implements($class) ?: [], true)) {
                $errors[] = "El validador '{$validatorKey}' ({$class}) debe implementar CrudValidatorInterface.";
            }
        }
```

- [ ] **Step 4: Ejecutar el test (debe pasar) y suite**

Run: `php tests/run.php CrudConfigValidatorValidationsTest`
Expected: PASS (4 tests).
Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudConfigValidator.php tests/Crud/Validation/CrudConfigValidatorValidationsTest.php
git commit -m "feat(crud): valida configuracion de unique/exists/form.validators"
```

---

# PARTE B — Fase 4: Relaciones + Tabs

### Task 7: `CrudRelationDefinition` (VO belongsTo / hasMany)

**Files:**
- Create: `app/Domain/Entities/Crud/CrudRelationDefinition.php`
- Test: `tests/Crud/Relation/CrudRelationDefinitionTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Crud/Relation/CrudRelationDefinitionTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\Crud\CrudRelationDefinition;

test('CrudRelationDefinition: belongsTo expone columnas value/label y filtro', function (): void {
    $rel = CrudRelationDefinition::fromArray('categoria', [
        'type' => 'belongsTo', 'table' => 'dom_demo_categorias',
        'foreign_key' => 'categoria_id', 'value' => 'id', 'label' => 'nombre',
        'filter' => ['activa' => 1], 'order_by' => 'nombre',
    ]);
    assert_same('categoria', $rel->name());
    assert_true($rel->isBelongsTo());
    assert_true(!$rel->isHasMany());
    assert_same('dom_demo_categorias', $rel->table());
    assert_same('categoria_id', $rel->foreignKey());
    assert_same('id', $rel->valueColumn());
    assert_same('nombre', $rel->labelColumn());
    assert_same(['activa' => 1], $rel->filter());
    assert_same('nombre', $rel->orderBy());
});

test('CrudRelationDefinition: hasMany expone columnas, direccion y limite', function (): void {
    $rel = CrudRelationDefinition::fromArray('items', [
        'type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id',
        'columns' => [['name' => 'cantidad', 'label' => 'Cantidad']],
        'order_by' => 'id', 'direction' => 'asc', 'limit' => 25,
    ]);
    assert_true($rel->isHasMany());
    assert_same('pedido_id', $rel->foreignKey());
    assert_same('ASC', $rel->direction());
    assert_same(25, $rel->limit());
    assert_same([['name' => 'cantidad', 'label' => 'Cantidad']], $rel->columns());
});

test('CrudRelationDefinition: hasMany aplica defaults (DESC, limit 50)', function (): void {
    $rel = CrudRelationDefinition::fromArray('items', [
        'type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id',
    ]);
    assert_same('DESC', $rel->direction());
    assert_same(50, $rel->limit());
    assert_same('id', $rel->orderBy());
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php tests/run.php CrudRelationDefinitionTest`
Expected: FAIL con "Class ...CrudRelationDefinition not found".

- [ ] **Step 3: Implementar el VO**

`app/Domain/Entities/Crud/CrudRelationDefinition.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities\Crud;

/**
 * Definición inmutable de una relación: `belongsTo` (alimenta selects) o
 * `hasMany` (filas hijas read-only para tabs). `manyToMany` está fuera de alcance.
 */
final class CrudRelationDefinition
{
    /**
     * @param array<string, mixed> $filter
     * @param list<array<string, mixed>> $columns
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly string $table,
        private readonly string $foreignKey,
        private readonly string $valueColumn,
        private readonly string $labelColumn,
        private readonly array $filter,
        private readonly string $orderBy,
        private readonly string $direction,
        private readonly int $limit,
        private readonly array $columns
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(string $name, array $config): self
    {
        $type = (string) ($config['type'] ?? '');
        $type = in_array($type, ['belongsTo', 'hasMany'], true) ? $type : 'belongsTo';

        $direction = strtoupper((string) ($config['direction'] ?? 'DESC'));
        $direction = $direction === 'ASC' ? 'ASC' : 'DESC';

        $limit = isset($config['limit']) ? (int) $config['limit'] : 50;
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $columns = [];
        foreach (($config['columns'] ?? []) as $col) {
            if (is_array($col) && ($col['name'] ?? '') !== '') {
                $columns[] = $col;
            }
        }

        return new self(
            name: $name,
            type: $type,
            table: (string) ($config['table'] ?? ''),
            foreignKey: (string) ($config['foreign_key'] ?? ''),
            valueColumn: (string) ($config['value'] ?? 'id'),
            labelColumn: (string) ($config['label'] ?? 'nombre'),
            filter: is_array($config['filter'] ?? null) ? $config['filter'] : [],
            orderBy: (string) ($config['order_by'] ?? 'id'),
            direction: $direction,
            limit: $limit,
            columns: $columns
        );
    }

    public function name(): string { return $this->name; }
    public function type(): string { return $this->type; }
    public function isBelongsTo(): bool { return $this->type === 'belongsTo'; }
    public function isHasMany(): bool { return $this->type === 'hasMany'; }
    public function table(): string { return $this->table; }
    public function foreignKey(): string { return $this->foreignKey; }
    public function valueColumn(): string { return $this->valueColumn; }
    public function labelColumn(): string { return $this->labelColumn; }
    /** @return array<string, mixed> */
    public function filter(): array { return $this->filter; }
    public function orderBy(): string { return $this->orderBy; }
    public function direction(): string { return $this->direction; }
    public function limit(): int { return $this->limit; }
    /** @return list<array<string, mixed>> */
    public function columns(): array { return $this->columns; }
}
```

- [ ] **Step 4: Ejecutar el test (debe pasar)**

Run: `php tests/run.php CrudRelationDefinitionTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/Crud/CrudRelationDefinition.php tests/Crud/Relation/CrudRelationDefinitionTest.php
git commit -m "feat(crud): VO CrudRelationDefinition (belongsTo/hasMany)"
```

---

### Task 8: `CrudTabDefinition` + parseo de `relations`/`detail` en `CrudResourceDefinition`

**Files:**
- Create: `app/Domain/Entities/Crud/CrudTabDefinition.php`
- Modify: `app/Domain/Entities/CrudResourceDefinition.php`
- Test: `tests/Crud/Relation/CrudResourceDefinitionRelationsTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Crud/Relation/CrudResourceDefinitionRelationsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Entities\Crud\CrudRelationDefinition;
use App\Domain\Entities\Crud\CrudTabDefinition;

function relations_definition(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo_pedidos', 'title' => 'Pedidos', 'table' => 'dom_demo_pedidos', 'primary_key' => 'id', 'permission_prefix' => 'demo_pedidos'],
        'relations' => [
            'cliente' => ['type' => 'belongsTo', 'table' => 'dom_demo_clientes', 'foreign_key' => 'cliente_id', 'value' => 'id', 'label' => 'nombre'],
            'items' => ['type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id'],
        ],
        'detail' => ['tabs' => [
            ['key' => 'general', 'label' => 'Datos generales', 'type' => 'fields', 'columns' => ['folio', 'total']],
            ['key' => 'items', 'label' => 'Items', 'type' => 'relation', 'relation' => 'items'],
            ['key' => 'historial', 'label' => 'Historial', 'type' => 'history'],
        ]],
    ]);
}

test('CrudResourceDefinition: relations are parsed into VOs accessible by name', function (): void {
    $def = relations_definition();
    assert_true($def->hasRelations());
    $cliente = $def->relation('cliente');
    assert_true($cliente instanceof CrudRelationDefinition);
    assert_true($cliente->isBelongsTo());
    assert_null($def->relation('inexistente'));
});

test('CrudResourceDefinition: detail tabs are parsed into VOs', function (): void {
    $def = relations_definition();
    assert_true($def->hasDetail());
    $tabs = $def->detailTabs();
    assert_same(3, count($tabs));
    assert_true($tabs[0] instanceof CrudTabDefinition);
    assert_true($tabs[0]->isFields());
    assert_true($tabs[1]->isRelation());
    assert_same('items', $tabs[1]->relation());
    assert_true($tabs[2]->isHistory());
});

test('CrudResourceDefinition: no detail block => empty tabs and hasDetail false', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
    ]);
    assert_true(!$def->hasDetail());
    assert_same([], $def->detailTabs());
    assert_true(!$def->hasRelations());
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php tests/run.php CrudResourceDefinitionRelationsTest`
Expected: FAIL con "Class ...CrudTabDefinition not found".

- [ ] **Step 3: Crear el VO de pestaña**

`app/Domain/Entities/Crud/CrudTabDefinition.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities\Crud;

/**
 * Definición inmutable de una pestaña de detalle. Tipos: `fields`, `relation`
 * (hasMany read-only), `component` (vista whitelisteada), `history` (bitácora).
 */
final class CrudTabDefinition
{
    private const TYPES = ['fields', 'relation', 'component', 'history'];

    /** @param list<string> $columns */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
        private readonly array $columns,
        private readonly string $relation,
        private readonly string $view
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $type = (string) ($config['type'] ?? 'fields');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'fields';
        }

        $columns = [];
        foreach (($config['columns'] ?? []) as $col) {
            if (is_string($col) && $col !== '') {
                $columns[] = $col;
            }
        }

        return new self(
            key: (string) ($config['key'] ?? ''),
            label: (string) ($config['label'] ?? ($config['key'] ?? '')),
            type: $type,
            columns: $columns,
            relation: (string) ($config['relation'] ?? ''),
            view: (string) ($config['view'] ?? '')
        );
    }

    public function key(): string { return $this->key; }
    public function label(): string { return $this->label; }
    public function type(): string { return $this->type; }
    /** @return list<string> */
    public function columns(): array { return $this->columns; }
    public function relation(): string { return $this->relation; }
    public function view(): string { return $this->view; }

    public function isFields(): bool { return $this->type === 'fields'; }
    public function isRelation(): bool { return $this->type === 'relation'; }
    public function isComponent(): bool { return $this->type === 'component'; }
    public function isHistory(): bool { return $this->type === 'history'; }
}
```

- [ ] **Step 4: Parsear `relations` y `detail` en `CrudResourceDefinition`**

En `app/Domain/Entities/CrudResourceDefinition.php`:

1. Añade imports:

```php
use App\Domain\Entities\Crud\CrudRelationDefinition;
use App\Domain\Entities\Crud\CrudTabDefinition;
```

2. Añade parámetros al final del constructor (después de `array $formValidators`):

```php
        private readonly array $formValidators,
        private readonly array $relations,
        private readonly array $detailTabs
```

3. En `fromArray()`, antes del `return new self(`:

```php
        $relations = [];
        foreach ((is_array($config['relations'] ?? null) ? $config['relations'] : []) as $relName => $relConfig) {
            if (is_string($relName) && $relName !== '' && is_array($relConfig)) {
                $relations[$relName] = CrudRelationDefinition::fromArray($relName, $relConfig);
            }
        }

        $detailTabs = [];
        $detailBlock = $config['detail'] ?? null;
        if (is_array($detailBlock) && is_array($detailBlock['tabs'] ?? null)) {
            foreach ($detailBlock['tabs'] as $tabConfig) {
                if (is_array($tabConfig) && ($tabConfig['key'] ?? '') !== '') {
                    $detailTabs[] = CrudTabDefinition::fromArray($tabConfig);
                }
            }
        }
```

4. En la llamada `return new self(...)`, agrega como últimos argumentos:

```php
            formValidators: $formValidators,
            relations: $relations,
            detailTabs: $detailTabs
```

5. Agrega accesores:

```php
    /** @return array<string, CrudRelationDefinition> */
    public function relations(): array
    {
        return $this->relations;
    }

    public function hasRelations(): bool
    {
        return $this->relations !== [];
    }

    public function relation(string $name): ?CrudRelationDefinition
    {
        return $this->relations[$name] ?? null;
    }

    /** @return list<CrudTabDefinition> */
    public function detailTabs(): array
    {
        return $this->detailTabs;
    }

    public function hasDetail(): bool
    {
        return $this->detailTabs !== [];
    }
```

- [ ] **Step 5: Ejecutar el test (debe pasar) y regresión**

Run: `php tests/run.php CrudResourceDefinitionRelationsTest`
Expected: PASS (3 tests).
Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Entities/Crud/CrudTabDefinition.php app/Domain/Entities/CrudResourceDefinition.php tests/Crud/Relation/CrudResourceDefinitionRelationsTest.php
git commit -m "feat(crud): parseo de relations y detail.tabs en CrudResourceDefinition"
```

---

### Task 9: Contrato `CrudRelationRepositoryInterface` + implementación

**Files:**
- Create: `app/Domain/Interfaces/CrudRelationRepositoryInterface.php`
- Modify: `app/Infrastructure/Repositories/GenericCrudRepository.php`

- [ ] **Step 1: Crear la interfaz**

`app/Domain/Interfaces/CrudRelationRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

/**
 * Contrato de lectura para relaciones del CRUD Engine. Implementado por
 * GenericCrudRepository. Toda columna/filtro pasa por whitelist en la impl.
 */
interface CrudRelationRepositoryInterface
{
    /**
     * Opciones value=>label para selects belongsTo. `$filter` es estructurado
     * ({columna: valor}); nunca SQL crudo.
     *
     * @param array<string, mixed> $filter
     * @return array<string, string>
     */
    public function distinctOptions(string $table, string $valueColumn, string $labelColumn, array $filter, string $orderBy): array;

    /**
     * Filas hijas para tabs hasMany (read-only).
     *
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    public function childrenBy(string $table, string $foreignKey, int $parentId, array $columns, string $orderBy, string $direction, int $limit): array;
}
```

- [ ] **Step 2: Implementar en `GenericCrudRepository`**

Cambia la declaración de clase para sumar la interfaz:

```php
use App\Domain\Interfaces\CrudConstraintRepositoryInterface;
use App\Domain\Interfaces\CrudRelationRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class GenericCrudRepository extends BaseRepository implements CrudConstraintRepositoryInterface, CrudRelationRepositoryInterface
{
```

Agrega estos métodos antes de `private function quoteIdentifier(...)`:

```php
    public function distinctOptions(string $table, string $valueColumn, string $labelColumn, array $filter, string $orderBy): array
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeValue = $this->quoteIdentifier($valueColumn);
        $safeLabel = $this->quoteIdentifier($labelColumn);

        $where = ['`deleted` = 0'];
        $params = [];
        foreach ($filter as $col => $val) {
            $where[] = $this->quoteIdentifier((string) $col) . ' = ?';
            $params[] = $val;
        }

        $orderCol = $orderBy !== '' ? $this->quoteIdentifier($orderBy) : $safeLabel;
        $sql = "SELECT {$safeValue} AS opt_value, {$safeLabel} AS opt_label FROM {$safeTable}"
            . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY {$orderCol} ASC LIMIT 1000";

        $rows = $this->query($sql, $params);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) ($row['opt_value'] ?? '')] = (string) ($row['opt_label'] ?? '');
        }

        return $out;
    }

    public function childrenBy(string $table, string $foreignKey, int $parentId, array $columns, string $orderBy, string $direction, int $limit): array
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeFk    = $this->quoteIdentifier($foreignKey);

        $cols = $columns === [] ? '*' : implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $orderCol = $orderBy !== '' ? $this->quoteIdentifier($orderBy) : $safeFk;
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $limit = $limit > 0 && $limit <= 500 ? $limit : 50;

        $sql = "SELECT {$cols} FROM {$safeTable} WHERE {$safeFk} = ? AND `deleted` = 0 ORDER BY {$orderCol} {$dir} LIMIT ?";

        return $this->query($sql, [$parentId, $limit]);
    }
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l app/Infrastructure/Repositories/GenericCrudRepository.php`
Expected: `No syntax errors detected`.
Run: `php -l app/Domain/Interfaces/CrudRelationRepositoryInterface.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Interfaces/CrudRelationRepositoryInterface.php app/Infrastructure/Repositories/GenericCrudRepository.php
git commit -m "feat(crud): metodos distinctOptions/childrenBy para relaciones"
```

---

### Task 10: `CrudRelationService`

**Files:**
- Create: `app/Application/Services/CrudRelationService.php`
- Create: `tests/fixtures/relation_repos.php`
- Test: `tests/Crud/Relation/CrudRelationServiceTest.php`

- [ ] **Step 1: Crear el fixture en memoria**

`tests/fixtures/relation_repos.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Interfaces\CrudRelationRepositoryInterface;

if (!class_exists('FakeRelationRepository')) {
    /** Repositorio de relaciones en memoria para tests sin DB. */
    class FakeRelationRepository implements CrudRelationRepositoryInterface
    {
        /** @var array<string, array<string, string>> */
        public array $options = [];
        /** @var array<string, list<array<string, mixed>>> */
        public array $children = [];
        /** @var list<array<string, mixed>> registro de llamadas a childrenBy */
        public array $childCalls = [];

        public function distinctOptions(string $table, string $valueColumn, string $labelColumn, array $filter, string $orderBy): array
        {
            return $this->options[$table] ?? [];
        }

        public function childrenBy(string $table, string $foreignKey, int $parentId, array $columns, string $orderBy, string $direction, int $limit): array
        {
            $this->childCalls[] = ['table' => $table, 'fk' => $foreignKey, 'parent' => $parentId, 'dir' => $direction, 'limit' => $limit];

            return $this->children[$table] ?? [];
        }
    }
}
```

- [ ] **Step 2: Escribir el test que falla**

`tests/Crud/Relation/CrudRelationServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudRelationService;
use App\Domain\Entities\Crud\CrudRelationDefinition;

require_once dirname(__DIR__, 2) . '/fixtures/relation_repos.php';

test('CrudRelationService: optionsFor returns value=>label for belongsTo', function (): void {
    $repo = new FakeRelationRepository();
    $repo->options['dom_demo_categorias'] = ['1' => 'Bebidas', '2' => 'Snacks'];
    $svc = new CrudRelationService($repo);

    $rel = CrudRelationDefinition::fromArray('categoria', [
        'type' => 'belongsTo', 'table' => 'dom_demo_categorias',
        'foreign_key' => 'categoria_id', 'value' => 'id', 'label' => 'nombre',
    ]);
    assert_same(['1' => 'Bebidas', '2' => 'Snacks'], $svc->optionsFor($rel));
});

test('CrudRelationService: optionsFor returns empty for hasMany (not a select source)', function (): void {
    $repo = new FakeRelationRepository();
    $svc = new CrudRelationService($repo);
    $rel = CrudRelationDefinition::fromArray('items', ['type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id']);
    assert_same([], $svc->optionsFor($rel));
});

test('CrudRelationService: childrenFor passes parent id and returns rows', function (): void {
    $repo = new FakeRelationRepository();
    $repo->children['dom_demo_pedido_items'] = [['id' => 1, 'cantidad' => 3]];
    $svc = new CrudRelationService($repo);

    $rel = CrudRelationDefinition::fromArray('items', [
        'type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id',
        'columns' => [['name' => 'cantidad', 'label' => 'Cantidad']], 'order_by' => 'id', 'direction' => 'asc', 'limit' => 10,
    ]);
    $rows = $svc->childrenFor($rel, 42);
    assert_same([['id' => 1, 'cantidad' => 3]], $rows);
    assert_same(42, $repo->childCalls[0]['parent']);
    assert_same('ASC', $repo->childCalls[0]['dir']);
});

test('CrudRelationService: childrenFor returns empty for belongsTo', function (): void {
    $repo = new FakeRelationRepository();
    $svc = new CrudRelationService($repo);
    $rel = CrudRelationDefinition::fromArray('categoria', ['type' => 'belongsTo', 'table' => 'dom_demo_categorias', 'foreign_key' => 'categoria_id']);
    assert_same([], $svc->childrenFor($rel, 1));
});
```

- [ ] **Step 3: Ejecutar el test para verificar que falla**

Run: `php tests/run.php CrudRelationServiceTest`
Expected: FAIL con "Class ...CrudRelationService not found".

- [ ] **Step 4: Implementar el servicio**

`app/Application/Services/CrudRelationService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\Crud\CrudRelationDefinition;
use App\Domain\Interfaces\CrudRelationRepositoryInterface;

/**
 * Resuelve datos de relaciones: opciones de selects belongsTo y filas hijas
 * hasMany (read-only). No decide reglas de negocio; solo lee del repositorio.
 */
final class CrudRelationService
{
    public function __construct(private readonly CrudRelationRepositoryInterface $repository) {}

    /** @return array<string, string> */
    public function optionsFor(CrudRelationDefinition $relation): array
    {
        if (!$relation->isBelongsTo()) {
            return [];
        }

        return $this->repository->distinctOptions(
            $relation->table(),
            $relation->valueColumn(),
            $relation->labelColumn(),
            $relation->filter(),
            $relation->orderBy()
        );
    }

    /** @return list<array<string, mixed>> */
    public function childrenFor(CrudRelationDefinition $relation, int $parentId): array
    {
        if (!$relation->isHasMany()) {
            return [];
        }

        $columnNames = [];
        foreach ($relation->columns() as $col) {
            $name = (string) ($col['name'] ?? '');
            if ($name !== '') {
                $columnNames[] = $name;
            }
        }

        return $this->repository->childrenBy(
            $relation->table(),
            $relation->foreignKey(),
            $parentId,
            $columnNames,
            $relation->orderBy(),
            $relation->direction(),
            $relation->limit()
        );
    }
}
```

- [ ] **Step 5: Ejecutar el test (debe pasar)**

Run: `php tests/run.php CrudRelationServiceTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudRelationService.php tests/fixtures/relation_repos.php tests/Crud/Relation/CrudRelationServiceTest.php
git commit -m "feat(crud): CrudRelationService (opciones belongsTo + hijos hasMany)"
```

---

### Task 11: Campo `type: relation` en el formulario

**Files:**
- Modify: `app/Domain/Entities/CrudFieldDefinition.php`
- Modify: `app/Application/Services/CrudFormBuilder.php`
- Modify: `config/container.php`
- Test: `tests/Crud/Relation/CrudFieldDefinitionRelationTest.php`

- [ ] **Step 1: Escribir el test que falla (accesor `relation`)**

`tests/Crud/Relation/CrudFieldDefinitionRelationTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\CrudFieldDefinition;

test('CrudFieldDefinition: relation is null by default', function (): void {
    $f = CrudFieldDefinition::fromArray(['name' => 'nombre', 'label' => 'Nombre']);
    assert_null($f->relation());
});

test('CrudFieldDefinition: relation is parsed from config', function (): void {
    $f = CrudFieldDefinition::fromArray(['name' => 'categoria_id', 'label' => 'Categoría', 'type' => 'relation', 'relation' => 'categoria']);
    assert_same('categoria', $f->relation());
    assert_same('relation', $f->type());
});
```

- [ ] **Step 2: Ejecutar para verificar que falla**

Run: `php tests/run.php CrudFieldDefinitionRelationTest`
Expected: FAIL con "Call to undefined method ...::relation()".

- [ ] **Step 3: Añadir `relation` a `CrudFieldDefinition`**

En `app/Domain/Entities/CrudFieldDefinition.php`:

1. Añade el parámetro al constructor (después de `?string $helpText = null`):

```php
        private readonly ?string $helpText = null,
        private readonly ?string $relation = null
```

2. En `fromArray()`, dentro de `new self(...)`, añade tras `helpText:`:

```php
            helpText: is_string($help) && $help !== '' ? $help : null,
            relation: isset($data['relation']) && $data['relation'] !== '' ? (string) $data['relation'] : null
```

3. Añade el accesor:

```php
    public function relation(): ?string { return $this->relation; }
```

- [ ] **Step 4: Resolver opciones de relación en `CrudFormBuilder`**

Reemplaza **íntegramente** `app/Application/Services/CrudFormBuilder.php` por:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudFieldDefinition;
use App\Domain\Entities\CrudResourceDefinition;

final class CrudFormBuilder
{
    public function __construct(private readonly ?CrudRelationService $relationService = null) {}

    public function build(
        CrudResourceDefinition $definition,
        array $values = [],
        array $errors = [],
        string $action = '',
        bool $isEdit = false
    ): array {
        $fields = [];

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition) {
                continue;
            }

            $name = $field->name();
            $readonly = $field->readonly();
            $type = $field->type();
            $options = $field->options();

            // Campo type: relation -> se renderiza como select con opciones de la relación.
            if ($type === 'relation') {
                $type = 'select';
                $relationName = $field->relation();
                $relation = $relationName !== null ? $definition->relation($relationName) : null;
                if ($relation !== null && $this->relationService !== null) {
                    $options = $this->relationService->optionsFor($relation);
                }
            }

            $fields[] = [
                'name' => $name,
                'label' => $field->label(),
                'type' => $type,
                'required' => $field->required(),
                'readonly' => $readonly,
                'readonlyPreservePost' => $readonly && in_array($type, ['select', 'checkbox'], true),
                'hidden' => $field->hidden(),
                'col' => $field->col(),
                'options' => $options,
                'validation' => $field->validation(),
                'help_text' => $field->helpText(),
                'value' => $values[$name] ?? $field->defaultValue(),
                'errors' => $errors[$name] ?? [],
            ];
        }

        return [
            'resource' => $definition->key(),
            'title' => $definition->title(),
            'action' => $action,
            'method' => 'POST',
            'isEdit' => $isEdit,
            'fields' => $fields,
        ];
    }
}
```

> El render de `form.php` ya soporta `type: select` con `options` como `value=>label`, así que no hace falta tocar la vista.

- [ ] **Step 5: Registrar `CrudRelationService` e inyectarlo en `CrudFormBuilder` (container)**

En `config/container.php`, añade imports:

```php
use App\Application\Services\CrudRelationService;
```

Registra el servicio de relaciones (después del binding de `CrudActionResolver`):

```php
    $container->singleton(CrudRelationService::class, fn(Container $c) => new CrudRelationService(
        $c->get(GenericCrudRepository::class)
    ));
```

Reemplaza el binding de `CrudFormBuilder` por:

```php
    $container->singleton(CrudFormBuilder::class, fn(Container $c) => new CrudFormBuilder(
        $c->get(CrudRelationService::class)
    ));
```

- [ ] **Step 6: Ejecutar tests + sintaxis**

Run: `php tests/run.php CrudFieldDefinitionRelationTest`
Expected: PASS.
Run: `php -l app/Application/Services/CrudFormBuilder.php && php -l config/container.php`
Expected: `No syntax errors detected` (x2).
Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Entities/CrudFieldDefinition.php app/Application/Services/CrudFormBuilder.php config/container.php tests/Crud/Relation/CrudFieldDefinitionRelationTest.php
git commit -m "feat(crud): campo type:relation resuelve opciones belongsTo en el formulario"
```

---

### Task 12: `BitacoraRepository::porRegistro` (para la tab de historial)

**Files:**
- Modify: `app/Domain/Interfaces/BitacoraRepositoryInterface.php`
- Modify: `app/Infrastructure/Repositories/BitacoraRepository.php`

- [ ] **Step 1: Ampliar la interfaz**

En `app/Domain/Interfaces/BitacoraRepositoryInterface.php`, añade el método:

```php
    /**
     * Entradas de bitácora de un registro concreto, recientes primero.
     *
     * @return list<array<string, mixed>>
     */
    public function porRegistro(string $tabla, int $registroId, int $limit = 50): array;
```

- [ ] **Step 2: Implementar en el repositorio**

En `app/Infrastructure/Repositories/BitacoraRepository.php`, añade tras `recientes()`:

```php
    public function porRegistro(string $tabla, int $registroId, int $limit = 50): array
    {
        return $this->query(
            "SELECT b.*, u.nombre, u.apellido
             FROM log_bitacora b
             LEFT JOIN auth_usuarios u ON u.id = b.usuario_id
             WHERE b.tabla = ? AND b.registro_id = ?
             ORDER BY b.created_at DESC
             LIMIT ?",
            [$tabla, $registroId, $limit]
        );
    }
```

- [ ] **Step 3: Verificar sintaxis + suite (la interfaz cambió; confirmar que todo carga)**

Run: `php -l app/Infrastructure/Repositories/BitacoraRepository.php && php -l app/Domain/Interfaces/BitacoraRepositoryInterface.php`
Expected: `No syntax errors detected` (x2).
Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Interfaces/BitacoraRepositoryInterface.php app/Infrastructure/Repositories/BitacoraRepository.php
git commit -m "feat(crud): bitacora por registro para la pestana de historial"
```

---

### Task 13: `CrudDetailBuilder` (view-model de tabs)

**Files:**
- Create: `app/Application/Services/CrudDetailBuilder.php`
- Test: `tests/Crud/Relation/CrudDetailBuilderTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Crud/Relation/CrudDetailBuilderTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudDetailBuilder;
use App\Application\Services\CrudRelationService;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\BitacoraRepositoryInterface;

require_once dirname(__DIR__, 2) . '/fixtures/relation_repos.php';

if (!class_exists('FakeBitacoraRepo')) {
    class FakeBitacoraRepo implements BitacoraRepositoryInterface
    {
        public array $entries = [];
        public function registrar(?int $usuarioId, string $accion, string $tabla = '', ?int $registroId = null, string $detalle = '', string $ip = ''): void {}
        public function recientes(int $limit = 50): array { return []; }
        public function porRegistro(string $tabla, int $registroId, int $limit = 50): array { return $this->entries; }
    }
}

function detail_definition(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo_pedidos', 'title' => 'Pedidos', 'table' => 'dom_demo_pedidos', 'primary_key' => 'id', 'permission_prefix' => 'demo_pedidos'],
        'list' => ['columns' => [['name' => 'folio', 'label' => 'Folio'], ['name' => 'total', 'label' => 'Total', 'format' => 'money']]],
        'relations' => ['items' => ['type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id', 'columns' => [['name' => 'cantidad', 'label' => 'Cantidad']]]],
        'detail' => ['tabs' => [
            ['key' => 'general', 'label' => 'General', 'type' => 'fields', 'columns' => ['folio', 'total']],
            ['key' => 'items', 'label' => 'Items', 'type' => 'relation', 'relation' => 'items'],
            ['key' => 'historial', 'label' => 'Historial', 'type' => 'history'],
        ]],
    ]);
}

test('CrudDetailBuilder: sin detail genera una tab general desde list.columns', function (): void {
    $repo = new FakeRelationRepository();
    $bita = new FakeBitacoraRepo();
    $builder = new CrudDetailBuilder(new CrudRelationService($repo), $bita);

    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
        'list' => ['columns' => [['name' => 'nombre', 'label' => 'Nombre']]],
    ]);
    $tabs = $builder->build($def, ['id' => 1, 'nombre' => 'Ana']);
    assert_same(1, count($tabs));
    assert_same('general', $tabs[0]['key']);
    assert_same('fields', $tabs[0]['type']);
});

test('CrudDetailBuilder: construye tabs fields/relation/history con datos', function (): void {
    $repo = new FakeRelationRepository();
    $repo->children['dom_demo_pedido_items'] = [['cantidad' => 2]];
    $bita = new FakeBitacoraRepo();
    $bita->entries = [['accion' => 'crud.create']];
    $builder = new CrudDetailBuilder(new CrudRelationService($repo), $bita);

    $tabs = $builder->build(detail_definition(), ['id' => 7, 'folio' => 'P-7', 'total' => 100]);
    assert_same(3, count($tabs));

    assert_same('fields', $tabs[0]['type']);
    assert_same(2, count($tabs[0]['columns']));   // folio + total

    assert_same('relation', $tabs[1]['type']);
    assert_same([['cantidad' => 2]], $tabs[1]['rows']);
    assert_same(7, $repo->childCalls[0]['parent']);

    assert_same('history', $tabs[2]['type']);
    assert_same([['accion' => 'crud.create']], $tabs[2]['entries']);
});
```

- [ ] **Step 2: Ejecutar para verificar que falla**

Run: `php tests/run.php CrudDetailBuilderTest`
Expected: FAIL con "Class ...CrudDetailBuilder not found".

- [ ] **Step 3: Implementar el builder**

`app/Application/Services/CrudDetailBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\BitacoraRepositoryInterface;

/**
 * Construye el view-model de pestañas para `show.php`. Sin bloque `detail`,
 * genera una sola tab "Datos generales" equivalente a la vista plana previa.
 */
final class CrudDetailBuilder
{
    public function __construct(
        private readonly CrudRelationService $relationService,
        private readonly BitacoraRepositoryInterface $bitacoraRepository
    ) {}

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    public function build(CrudResourceDefinition $definition, array $row): array
    {
        $primaryId = (int) ($row[$definition->primaryKey()] ?? 0);

        if (!$definition->hasDetail()) {
            return [[
                'key' => 'general',
                'label' => 'Datos generales',
                'type' => 'fields',
                'columns' => $definition->listColumns(),
            ]];
        }

        $out = [];
        foreach ($definition->detailTabs() as $tab) {
            if ($tab->isFields()) {
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'fields',
                    'columns' => $this->columnsFor($definition, $tab->columns()),
                ];
            } elseif ($tab->isRelation()) {
                $relation = $definition->relation($tab->relation());
                $rows = ($relation !== null && $relation->isHasMany())
                    ? $this->relationService->childrenFor($relation, $primaryId)
                    : [];
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'relation',
                    'columns' => $relation !== null ? $relation->columns() : [],
                    'rows' => $rows,
                ];
            } elseif ($tab->isHistory()) {
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'history',
                    'entries' => $this->bitacoraRepository->porRegistro($definition->table(), $primaryId, 50),
                ];
            } elseif ($tab->isComponent()) {
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'component',
                    'view' => $tab->view(),
                ];
            }
        }

        return $out;
    }

    /**
     * Mapea nombres de columna a sus configuraciones (label/format/badge) desde
     * list.columns; si no existe, usa un default con el nombre como label.
     *
     * @param list<string> $names
     * @return list<array<string, mixed>>
     */
    private function columnsFor(CrudResourceDefinition $definition, array $names): array
    {
        if ($names === []) {
            return $definition->listColumns();
        }

        $byName = [];
        foreach ($definition->listColumns() as $col) {
            $byName[(string) ($col['name'] ?? '')] = $col;
        }

        $out = [];
        foreach ($names as $name) {
            $out[] = $byName[$name] ?? ['name' => $name, 'label' => $name];
        }

        return $out;
    }
}
```

- [ ] **Step 4: Ejecutar el test (debe pasar)**

Run: `php tests/run.php CrudDetailBuilderTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudDetailBuilder.php tests/Crud/Relation/CrudDetailBuilderTest.php
git commit -m "feat(crud): CrudDetailBuilder genera view-model de pestanas de detalle"
```

---

### Task 14: Reescribir `show.php` con nav-tabs + partials

**Files:**
- Modify: `app/Application/Services/CrudResourceService.php`
- Modify: `config/container.php`
- Modify: `app/Presentation/Views/admin/crud/show.php`
- Create: `app/Presentation/Views/admin/crud/partials/tab_fields.php`
- Create: `app/Presentation/Views/admin/crud/partials/tab_relation.php`
- Create: `app/Presentation/Views/admin/crud/partials/tab_history.php`

- [ ] **Step 1: Inyectar `CrudDetailBuilder` en `CrudResourceService` y publicar `tabs`**

En `app/Application/Services/CrudResourceService.php`:

1. Añade el parámetro al constructor (último):

```php
        private readonly CrudActionService $actionService,
        private readonly CrudDetailBuilder $detailBuilder
    ) {}
```

2. En `buildShowData()`, antes del `return`, calcula tabs y agrégalas al array devuelto:

```php
        $tabs = $this->detailBuilder->build($definition, $row);

        return [
            'resource' => $definition->key(),
            'title' => $definition->title(),
            'row' => $row,
            'columns' => $definition->listColumns(),
            'primaryKey' => $definition->primaryKey(),
            'actions' => $actions,
            'state' => $state,
            'tabs' => $tabs,
        ];
```

- [ ] **Step 2: Inyectar el builder en el container**

En `config/container.php`, añade el import:

```php
use App\Application\Services\CrudDetailBuilder;
```

Registra el binding (después de `CrudRelationService`):

```php
    $container->singleton(CrudDetailBuilder::class, fn(Container $c) => new CrudDetailBuilder(
        $c->get(CrudRelationService::class),
        $c->get(BitacoraRepositoryInterface::class)
    ));
```

Reemplaza el binding de `CrudResourceService` por:

```php
    $container->singleton(CrudResourceService::class, fn(Container $c) => new CrudResourceService(
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudFormBuilder::class),
        $c->get(CrudTableBuilder::class),
        $c->get(RbacService::class),
        $c->get(CrudActionResolver::class),
        $c->get(CrudActionService::class),
        $c->get(CrudDetailBuilder::class)
    ));
```

- [ ] **Step 3: Crear los partials de pestaña**

`app/Presentation/Views/admin/crud/partials/tab_fields.php`:

```php
<?php
use App\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $tabColumns */
/** @var array<string, mixed> $row */
$tabColumns = $tabColumns ?? [];
$row = $row ?? [];
?>
<dl class="row crud-dl mb-0">
    <?php foreach ($tabColumns as $column): ?>
        <?php
            $name = (string) ($column['name'] ?? '');
            $raw = $row[$name] ?? '';
            $format = (string) ($column['format'] ?? '');
            $display = (string) $raw;
            if ($format === 'date' && $raw !== '' && $raw !== null) {
                $ts = strtotime((string) $raw);
                $display = $ts ? date('d/m/Y', $ts) : $display;
            } elseif ($format === 'datetime' && $raw !== '' && $raw !== null) {
                $ts = strtotime((string) $raw);
                $display = $ts ? date('d/m/Y H:i', $ts) : $display;
            } elseif ($format === 'money' && $raw !== '' && $raw !== null) {
                $display = '$' . number_format((float) $raw, 2, '.', ',');
            }
            $badge = null;
            if (!empty($column['badge']) && is_array($column['badge'])) {
                $badge = (string) ($column['badge'][(string) $raw] ?? '');
            }
        ?>
        <dt class="col-12 col-sm-4 col-lg-3"><?= ViewHelper::e((string) ($column['label'] ?? $name)) ?></dt>
        <dd class="col-12 col-sm-8 col-lg-9 mb-3">
            <?php if ($badge !== null && $badge !== ''): ?>
                <span class="badge rounded-pill bg-<?= ViewHelper::e($badge) ?>-subtle text-<?= ViewHelper::e($badge) ?> border border-<?= ViewHelper::e($badge) ?>-subtle">
                    <?= ViewHelper::e($display) ?>
                </span>
            <?php else: ?>
                <span class="d-block py-1 border-bottom border-light-subtle"><?= ViewHelper::e($display) ?></span>
            <?php endif; ?>
        </dd>
    <?php endforeach; ?>
</dl>
```

`app/Presentation/Views/admin/crud/partials/tab_relation.php`:

```php
<?php
use App\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $relColumns */
/** @var array<int, array<string, mixed>> $relRows */
$relColumns = $relColumns ?? [];
$relRows = $relRows ?? [];
?>
<?php if ($relRows === []): ?>
    <p class="text-muted small mb-0">Sin registros relacionados.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
            <tr>
                <?php foreach ($relColumns as $column): ?>
                    <th class="px-3 text-nowrap"><?= ViewHelper::e((string) ($column['label'] ?? ($column['name'] ?? ''))) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($relRows as $rrow): ?>
                <tr>
                    <?php foreach ($relColumns as $column): ?>
                        <?php
                            $name = (string) ($column['name'] ?? '');
                            $raw = $rrow[$name] ?? '';
                            $format = (string) ($column['format'] ?? '');
                            $display = (string) $raw;
                            if ($format === 'date' && $raw !== '' && $raw !== null) {
                                $ts = strtotime((string) $raw);
                                $display = $ts ? date('d/m/Y', $ts) : $display;
                            } elseif ($format === 'datetime' && $raw !== '' && $raw !== null) {
                                $ts = strtotime((string) $raw);
                                $display = $ts ? date('d/m/Y H:i', $ts) : $display;
                            } elseif ($format === 'money' && $raw !== '' && $raw !== null) {
                                $display = '$' . number_format((float) $raw, 2, '.', ',');
                            }
                        ?>
                        <td class="px-3"><?= ViewHelper::e($display) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
```

`app/Presentation/Views/admin/crud/partials/tab_history.php`:

```php
<?php
use App\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $historyEntries */
$historyEntries = $historyEntries ?? [];
?>
<?php if ($historyEntries === []): ?>
    <p class="text-muted small mb-0">Sin movimientos registrados.</p>
<?php else: ?>
<ul class="list-group list-group-flush">
    <?php foreach ($historyEntries as $entry): ?>
        <?php
            $accion = (string) ($entry['accion'] ?? '');
            $detalle = (string) ($entry['detalle'] ?? '');
            $fecha = (string) ($entry['created_at'] ?? '');
            $autor = trim((string) ($entry['nombre'] ?? '') . ' ' . (string) ($entry['apellido'] ?? ''));
            $ts = $fecha !== '' ? strtotime($fecha) : false;
        ?>
        <li class="list-group-item px-0">
            <div class="d-flex justify-content-between gap-2">
                <span class="fw-medium small"><?= ViewHelper::e($accion) ?></span>
                <span class="text-muted small"><?= $ts ? date('d/m/Y H:i', $ts) : ViewHelper::e($fecha) ?></span>
            </div>
            <?php if ($detalle !== ''): ?>
                <div class="text-muted small text-truncate"><?= ViewHelper::e($detalle) ?></div>
            <?php endif; ?>
            <?php if ($autor !== ''): ?>
                <div class="text-muted small">Por: <?= ViewHelper::e($autor) ?></div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
```

- [ ] **Step 4: Reescribir `show.php` con nav-tabs (conservando header de estado + acciones)**

Reemplaza el `<div class="card-body p-3 p-md-4"> ... </div>` que contiene el `<dl class="row crud-dl mb-0">` (líneas del cuerpo) por un bloque de pestañas. El resultado del archivo `app/Presentation/Views/admin/crud/show.php` debe quedar así (header intacto; solo cambia el card-body):

```php
                <div class="card-body p-3 p-md-4">
                    <?php $tabs = is_array($tabs ?? null) ? $tabs : []; ?>
                    <?php if (count($tabs) <= 1): ?>
                        <?php
                            $only = $tabs[0] ?? ['type' => 'fields', 'columns' => ($columns ?? [])];
                            $tabColumns = is_array($only['columns'] ?? null) ? $only['columns'] : ($columns ?? []);
                            require __DIR__ . '/partials/tab_fields.php';
                        ?>
                    <?php else: ?>
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <?php foreach ($tabs as $i => $tab): ?>
                                <?php $tabKey = (string) ($tab['key'] ?? ('tab' . $i)); ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                            id="tab-btn-<?= ViewHelper::e($tabKey) ?>"
                                            data-bs-toggle="tab" data-bs-target="#tab-pane-<?= ViewHelper::e($tabKey) ?>"
                                            type="button" role="tab">
                                        <?= ViewHelper::e((string) ($tab['label'] ?? $tabKey)) ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="tab-content">
                            <?php foreach ($tabs as $i => $tab): ?>
                                <?php $tabKey = (string) ($tab['key'] ?? ('tab' . $i)); $tabType = (string) ($tab['type'] ?? 'fields'); ?>
                                <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
                                     id="tab-pane-<?= ViewHelper::e($tabKey) ?>" role="tabpanel">
                                    <?php if ($tabType === 'fields'): ?>
                                        <?php $tabColumns = is_array($tab['columns'] ?? null) ? $tab['columns'] : []; require __DIR__ . '/partials/tab_fields.php'; ?>
                                    <?php elseif ($tabType === 'relation'): ?>
                                        <?php $relColumns = is_array($tab['columns'] ?? null) ? $tab['columns'] : []; $relRows = is_array($tab['rows'] ?? null) ? $tab['rows'] : []; require __DIR__ . '/partials/tab_relation.php'; ?>
                                    <?php elseif ($tabType === 'history'): ?>
                                        <?php $historyEntries = is_array($tab['entries'] ?? null) ? $tab['entries'] : []; require __DIR__ . '/partials/tab_history.php'; ?>
                                    <?php elseif ($tabType === 'component'): ?>
                                        <?php
                                            $componentView = (string) ($tab['view'] ?? '');
                                            if ($componentView !== '' && !str_contains($componentView, '..')) {
                                                echo ViewHelper::partial($componentView, ['row' => $row ?? []]);
                                            }
                                        ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
```

> El bloque `<dl>` antiguo se elimina; el resto del archivo (header con badge de estado + acciones, modal de borrado y el `<script>` final) queda **igual**.

- [ ] **Step 5: Verificar sintaxis de todas las vistas + suite**

Run: `php -l app/Presentation/Views/admin/crud/show.php`
Expected: `No syntax errors detected`.
Run: `php -l app/Presentation/Views/admin/crud/partials/tab_fields.php && php -l app/Presentation/Views/admin/crud/partials/tab_relation.php && php -l app/Presentation/Views/admin/crud/partials/tab_history.php`
Expected: `No syntax errors detected` (x3).
Run: `php -l app/Application/Services/CrudResourceService.php && php -l config/container.php`
Expected: `No syntax errors detected` (x2).
Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudResourceService.php config/container.php app/Presentation/Views/admin/crud/show.php app/Presentation/Views/admin/crud/partials/tab_fields.php app/Presentation/Views/admin/crud/partials/tab_relation.php app/Presentation/Views/admin/crud/partials/tab_history.php
git commit -m "feat(crud): detalle con pestanas (fields/relation/history/component)"
```

---

### Task 15: `CrudConfigValidator` valida `relations` y `detail`

**Files:**
- Modify: `app/Application/Services/CrudConfigValidator.php`
- Test: `tests/Crud/Relation/CrudConfigValidatorRelationsTest.php`

- [ ] **Step 1: Escribir el test que falla (forma pura)**

`tests/Crud/Relation/CrudConfigValidatorRelationsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('CrudConfigValidator: relation con type inválido es error', function (): void {
    $errors = CrudConfigValidator::relationsBlockErrors([
        'relations' => ['x' => ['type' => 'manyToMany', 'table' => 'dom_x', 'foreign_key' => 'x_id']],
    ]);
    assert_true(in_array("relations.x.type debe ser 'belongsTo' o 'hasMany'.", $errors, true), 'falta error de type');
});

test('CrudConfigValidator: relation con tabla de prefijo bloqueado es error', function (): void {
    $errors = CrudConfigValidator::relationsBlockErrors([
        'relations' => ['x' => ['type' => 'belongsTo', 'table' => 'auth_usuarios', 'foreign_key' => 'x_id', 'value' => 'id', 'label' => 'nombre']],
    ]);
    assert_true(in_array("relations.x.table (auth_usuarios) usa un prefijo bloqueado.", $errors, true), 'falta error de prefijo');
});

test('CrudConfigValidator: detail tab relation debe referenciar una relación existente', function (): void {
    $errors = CrudConfigValidator::detailBlockErrors([
        'relations' => [],
        'detail' => ['tabs' => [['key' => 'items', 'type' => 'relation', 'relation' => 'items']]],
    ]);
    assert_true(in_array("detail.tabs[0] (relation) referencia una relación inexistente: 'items'.", $errors, true), 'falta error de relación');
});

test('CrudConfigValidator: detail tab component con .. (traversal) es error', function (): void {
    $errors = CrudConfigValidator::detailBlockErrors([
        'detail' => ['tabs' => [['key' => 'c', 'type' => 'component', 'view' => '../../etc/passwd']]],
    ]);
    assert_true(in_array("detail.tabs[0] (component) tiene una vista con ruta inválida.", $errors, true), 'falta error de traversal');
});

test('CrudConfigValidator: relations/detail vacíos no generan errores', function (): void {
    assert_same([], CrudConfigValidator::relationsBlockErrors([]));
    assert_same([], CrudConfigValidator::detailBlockErrors([]));
});
```

- [ ] **Step 2: Ejecutar para verificar que falla**

Run: `php tests/run.php CrudConfigValidatorRelationsTest`
Expected: FAIL con "Call to undefined method ...::relationsBlockErrors()".

- [ ] **Step 3: Implementar los métodos estáticos + checks de DB**

En `app/Application/Services/CrudConfigValidator.php`:

1. Añade los dos métodos estáticos (junto a los demás `*BlockErrors`):

```php
    /**
     * Valida la forma del bloque `relations`. Pura, sin DB.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function relationsBlockErrors(array $config): array
    {
        $relations = $config['relations'] ?? null;
        if ($relations === null) {
            return [];
        }
        if (!is_array($relations)) {
            return ['relations debe ser un objeto.'];
        }

        $errors = [];
        foreach ($relations as $name => $rel) {
            if (!is_array($rel)) {
                $errors[] = "relations.{$name} debe ser un objeto.";
                continue;
            }
            $type = (string) ($rel['type'] ?? '');
            if (!in_array($type, ['belongsTo', 'hasMany'], true)) {
                $errors[] = "relations.{$name}.type debe ser 'belongsTo' o 'hasMany'.";
            }
            $table = (string) ($rel['table'] ?? '');
            if ($table === '') {
                $errors[] = "relations.{$name}.table es obligatorio.";
            } else {
                foreach (self::BLOCKED_PREFIXES as $prefix) {
                    if (str_starts_with($table, $prefix)) {
                        $errors[] = "relations.{$name}.table ({$table}) usa un prefijo bloqueado.";
                        break;
                    }
                }
            }
            if (($rel['foreign_key'] ?? '') === '') {
                $errors[] = "relations.{$name}.foreign_key es obligatorio.";
            }
        }

        return $errors;
    }

    /**
     * Valida la forma del bloque `detail`. Pura, sin DB.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function detailBlockErrors(array $config): array
    {
        $detail = $config['detail'] ?? null;
        if ($detail === null) {
            return [];
        }
        if (!is_array($detail)) {
            return ['detail debe ser un objeto.'];
        }
        $tabs = $detail['tabs'] ?? null;
        if ($tabs === null) {
            return [];
        }
        if (!is_array($tabs)) {
            return ['detail.tabs debe ser un arreglo.'];
        }

        $relationNames = array_fill_keys(array_keys(is_array($config['relations'] ?? null) ? $config['relations'] : []), true);

        $errors = [];
        foreach ($tabs as $i => $tab) {
            if (!is_array($tab)) {
                $errors[] = "detail.tabs[{$i}] debe ser un objeto.";
                continue;
            }
            if (($tab['key'] ?? '') === '') {
                $errors[] = "detail.tabs[{$i}].key es obligatorio.";
            }
            $type = (string) ($tab['type'] ?? 'fields');
            if (!in_array($type, ['fields', 'relation', 'component', 'history'], true)) {
                $errors[] = "detail.tabs[{$i}].type inválido ('{$type}').";
                continue;
            }
            if ($type === 'relation') {
                $relName = (string) ($tab['relation'] ?? '');
                if ($relName === '' || !isset($relationNames[$relName])) {
                    $errors[] = "detail.tabs[{$i}] (relation) referencia una relación inexistente: '{$relName}'.";
                }
            }
            if ($type === 'component') {
                $view = (string) ($tab['view'] ?? '');
                if ($view === '' || str_contains($view, '..') || str_starts_with($view, '/')) {
                    $errors[] = "detail.tabs[{$i}] (component) tiene una vista con ruta inválida.";
                }
            }
        }

        return $errors;
    }
```

2. En `validate()`, después de los `foreach (self::validationConstraintShapeErrors(...))`, agrega:

```php
        foreach (self::relationsBlockErrors($config) as $relationError) {
            $errors[] = $relationError;
        }
        foreach (self::detailBlockErrors($config) as $detailError) {
            $errors[] = $detailError;
        }
```

3. En `validate()`, junto a la verificación profunda de `exists`, añade verificación de existencia DB para tablas/columnas de `relations` (insertar antes del `if (!empty($errors))` final):

```php
        // relations: tabla/columnas destino deben existir en DB.
        foreach ((is_array($config['relations'] ?? null) ? $config['relations'] : []) as $relName => $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $relTable = (string) ($rel['table'] ?? '');
            if ($relTable === '') {
                continue; // ya reportado por relationsBlockErrors
            }
            if (!$this->repository->tableExists($relTable)) {
                $errors[] = "relations.{$relName}: la tabla {$relTable} no existe.";
                continue;
            }
            $relCols = array_fill_keys($this->repository->getTableColumns($relTable), true);
            foreach (['value', 'label', 'foreign_key'] as $colKey) {
                $colName = (string) ($rel[$colKey] ?? '');
                if ($colName !== '' && !isset($relCols[$colName])) {
                    $errors[] = "relations.{$relName}.{$colKey} ({$colName}) no existe en {$relTable}.";
                }
            }
        }
```

- [ ] **Step 4: Ejecutar el test (debe pasar) y suite**

Run: `php tests/run.php CrudConfigValidatorRelationsTest`
Expected: PASS (5 tests).
Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudConfigValidator.php tests/Crud/Relation/CrudConfigValidatorRelationsTest.php
git commit -m "feat(crud): valida configuracion de relations y detail.tabs"
```

---

# PARTE C — Demo Showcase + Instalador

> Estas tareas tocan DB/configs y se verifican manualmente con un entorno con DB. La migración es **idempotente** (guards con `information_schema` + `INSERT IGNORE`) para que el instalador se pueda correr en cada despliegue.

### Task 16: Migración de tablas demo "showcase"

**Files:**
- Create: `database/migrations/20260607120000_crud_engine_demo_showcase.sql`

- [ ] **Step 1: Crear la migración**

`database/migrations/20260607120000_crud_engine_demo_showcase.sql`:

```sql
-- CRUD Engine demo showcase (Fase 3 + Fase 4)
-- Añade categorías (belongsTo target), pedidos (estados + relaciones + tabs)
-- e items (hasMany). Idempotente: se puede re-ejecutar en cada despliegue.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Categorías (target de belongsTo desde productos/pedidos no; aquí demo CRUD propio)
CREATE TABLE IF NOT EXISTS `dom_demo_categorias` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(120)    NOT NULL,
  `activa`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_demo_categorias_nombre` (`nombre`),
  KEY `idx_demo_categorias_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Productos: agregar categoria_id (belongsTo dom_demo_categorias) idempotente
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'dom_demo_productos' AND column_name = 'categoria_id');
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE `dom_demo_productos` ADD COLUMN `categoria_id` BIGINT UNSIGNED NULL AFTER `nombre`, ADD KEY `idx_demo_productos_categoria` (`categoria_id`)',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Pedidos (estados + belongsTo cliente + hasMany items)
CREATE TABLE IF NOT EXISTS `dom_demo_pedidos` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folio`       VARCHAR(40)     NOT NULL,
  `cliente_id`  BIGINT UNSIGNED NOT NULL,
  `total`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `status`      VARCHAR(30)     NOT NULL DEFAULT 'pendiente',
  `notas`       VARCHAR(255)    DEFAULT NULL,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_demo_pedidos_folio` (`folio`),
  KEY `idx_demo_pedidos_status` (`status`),
  KEY `idx_demo_pedidos_cliente` (`cliente_id`),
  KEY `idx_demo_pedidos_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Items de pedido (hasMany read-only en tab)
CREATE TABLE IF NOT EXISTS `dom_demo_pedido_items` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_id`        BIGINT UNSIGNED NOT NULL,
  `producto_id`      BIGINT UNSIGNED NOT NULL,
  `descripcion`      VARCHAR(150)    NOT NULL,
  `cantidad`         INT             NOT NULL DEFAULT 1,
  `precio_unitario`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `subtotal`         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `deleted`          TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`       BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`       DATETIME        DEFAULT NULL,
  `updated_by`       BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`       DATETIME        DEFAULT NULL,
  `deleted_by`       BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demo_pedido_items_pedido` (`pedido_id`),
  KEY `idx_demo_pedido_items_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Permisos RBAC para los nuevos recursos demo
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver demo categorias', 'demo_categorias.ver', 'crud_demo', 'Listar/ver categorías demo'),
('Crear demo categorias', 'demo_categorias.crear', 'crud_demo', 'Crear categorías demo'),
('Editar demo categorias', 'demo_categorias.editar', 'crud_demo', 'Editar categorías demo'),
('Eliminar demo categorias', 'demo_categorias.eliminar', 'crud_demo', 'Eliminar (lógico) categorías demo'),
('Ver demo pedidos', 'demo_pedidos.ver', 'crud_demo', 'Listar/ver pedidos demo'),
('Crear demo pedidos', 'demo_pedidos.crear', 'crud_demo', 'Crear pedidos demo'),
('Editar demo pedidos', 'demo_pedidos.editar', 'crud_demo', 'Editar pedidos demo'),
('Eliminar demo pedidos', 'demo_pedidos.eliminar', 'crud_demo', 'Eliminar (lógico) pedidos demo'),
('Pagar demo pedidos', 'demo_pedidos.pagar', 'crud_demo', 'Transición pagar pedido demo'),
('Cancelar demo pedidos', 'demo_pedidos.cancelar', 'crud_demo', 'Transición cancelar pedido demo');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'demo_categorias.ver','demo_categorias.crear','demo_categorias.editar','demo_categorias.eliminar',
  'demo_pedidos.ver','demo_pedidos.crear','demo_pedidos.editar','demo_pedidos.eliminar',
  'demo_pedidos.pagar','demo_pedidos.cancelar'
)
WHERE `r`.`slug` = 'administrador';

-- 6) Menú (bajo el parent existente 'crud-demo')
INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 3, 'crud-demo-categorias', 'Demo Categorías', 'bi-tags', '/admin/crud/demo_categorias', '/admin/crud/demo_categorias', 'demo_categorias.ver', NULL, 1
FROM core_menu_items p WHERE p.slug = 'crud-demo';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 4, 'crud-demo-pedidos', 'Demo Pedidos', 'bi-receipt', '/admin/crud/demo_pedidos', '/admin/crud/demo_pedidos', 'demo_pedidos.ver', NULL, 1
FROM core_menu_items p WHERE p.slug = 'crud-demo';

-- 7) Datos de ejemplo (solo si las tablas están vacías)
INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Bebidas' AS n, 1 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Bebidas');
INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Snacks' AS n, 1 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Snacks');
INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Limpieza' AS n, 0 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Limpieza');

-- Asignar categoría a productos demo existentes que aún no la tengan
UPDATE `dom_demo_productos` p
JOIN `dom_demo_categorias` c ON c.`nombre` = 'Bebidas'
SET p.`categoria_id` = c.`id`
WHERE p.`categoria_id` IS NULL;

-- Pedido de ejemplo + items (solo si no existe el folio)
INSERT INTO `dom_demo_pedidos` (`folio`, `cliente_id`, `total`, `status`, `notas`, `deleted`, `created_at`)
SELECT 'PED-DEMO-001',
       (SELECT MIN(`id`) FROM `dom_demo_clientes` WHERE `deleted` = 0),
       289.40, 'pendiente', 'Pedido de demostración', 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_pedidos` WHERE `folio` = 'PED-DEMO-001')
  AND EXISTS (SELECT 1 FROM `dom_demo_clientes` WHERE `deleted` = 0);

INSERT INTO `dom_demo_pedido_items` (`pedido_id`, `producto_id`, `descripcion`, `cantidad`, `precio_unitario`, `subtotal`, `deleted`, `created_at`)
SELECT ped.`id`, COALESCE((SELECT MIN(`id`) FROM `dom_demo_productos` WHERE `deleted` = 0), 0),
       'Producto Demo A', 1, 199.90, 199.90, 0, NOW()
FROM `dom_demo_pedidos` ped
WHERE ped.`folio` = 'PED-DEMO-001'
  AND NOT EXISTS (SELECT 1 FROM `dom_demo_pedido_items` i WHERE i.`pedido_id` = ped.`id`);

INSERT INTO `dom_demo_pedido_items` (`pedido_id`, `producto_id`, `descripcion`, `cantidad`, `precio_unitario`, `subtotal`, `deleted`, `created_at`)
SELECT ped.`id`, COALESCE((SELECT MIN(`id`) FROM `dom_demo_productos` WHERE `deleted` = 0), 0),
       'Producto Demo B', 1, 89.50, 89.50, 0, NOW()
FROM `dom_demo_pedidos` ped
WHERE ped.`folio` = 'PED-DEMO-001'
  AND (SELECT COUNT(*) FROM `dom_demo_pedido_items` i WHERE i.`pedido_id` = ped.`id`) = 1;

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 2: Commit**

```bash
git add database/migrations/20260607120000_crud_engine_demo_showcase.sql
git commit -m "feat(crud): migracion demo showcase (categorias, pedidos, items)"
```

---

### Task 17: Handlers/validators/guard demo + registro

**Files:**
- Create: `app/Application/Crud/Handlers/DemoPedidoTotalValidator.php`
- Create: `app/Application/Crud/Handlers/DemoPedidoPagarGuard.php`
- Create: `app/Application/Crud/Handlers/DemoClienteContactoValidator.php`
- Modify: `config/crud_handlers.php`

- [ ] **Step 1: Crear el validador de total de pedido**

`app/Application/Crud/Handlers/DemoPedidoTotalValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudValidationContext;
use App\Domain\Interfaces\CrudValidatorInterface;

/**
 * Demo de validador de formulario externo (escape hatch). Exige total > 0.
 */
final class DemoPedidoTotalValidator implements CrudValidatorInterface
{
    public function validate(CrudValidationContext $ctx): void
    {
        $normalized = $ctx->normalized();
        if (!array_key_exists('total', $normalized)) {
            return;
        }
        $total = (float) str_replace(',', '.', (string) $normalized['total']);
        if ($total <= 0) {
            $ctx->addError('total', 'El total del pedido debe ser mayor a cero.');
        }
    }
}
```

- [ ] **Step 2: Crear el guard de transición "pagar"**

`app/Application/Crud/Handlers/DemoPedidoPagarGuard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\CrudTransitionGuardInterface;

/**
 * Demo de guard de transición (escape hatch). No permite pagar un pedido en
 * total cero (regla de negocio que vive fuera del core).
 */
final class DemoPedidoPagarGuard implements CrudTransitionGuardInterface
{
    public function authorize(CrudTransitionContext $ctx): void
    {
        $record = $ctx->record();
        $total = (float) ($record['total'] ?? 0);
        if ($total <= 0) {
            throw new ValidationException('No se puede pagar un pedido con total cero.');
        }
    }
}
```

> Nota: confirma el nombre del accesor de registro en `CrudTransitionContext` (`record()`). El test de Fase 2 (`transition_ctx`) construye el contexto con el registro como 6.º argumento; el guard de Fase 2 (`RecordingTransitionGuard`) usa `from()`/`to()`. Si el accesor del registro tuviera otro nombre, ajusta esta línea acorde (revisa `app/Application/Crud/Context/CrudTransitionContext.php`).

- [ ] **Step 3: Crear el validador de contacto de cliente**

`app/Application/Crud/Handlers/DemoClienteContactoValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudValidationContext;
use App\Domain\Interfaces\CrudValidatorInterface;

/**
 * Demo de validación cross-field: un cliente activo debe tener teléfono.
 */
final class DemoClienteContactoValidator implements CrudValidatorInterface
{
    public function validate(CrudValidationContext $ctx): void
    {
        $normalized = $ctx->normalized();
        $status = (string) ($normalized['status'] ?? '');
        $telefono = trim((string) ($normalized['telefono'] ?? ''));
        if ($status === 'activo' && $telefono === '') {
            $ctx->addError('telefono', 'Un cliente activo debe tener teléfono.');
        }
    }
}
```

- [ ] **Step 4: Verificar el accesor `record()` de `CrudTransitionContext`**

Run: `php -r "echo file_get_contents('app/Application/Crud/Context/CrudTransitionContext.php');"`
Expected: confirmar que existe `public function record(): ?array`. Si el nombre difiere, corrige `DemoPedidoPagarGuard.php`.

- [ ] **Step 5: Registrar las claves en el whitelist**

En `config/crud_handlers.php`, dentro del `return [...]`, añade:

```php
    'demo_pedido_total'        => \App\Application\Crud\Handlers\DemoPedidoTotalValidator::class,
    'demo_pedido_pagar_guard'  => \App\Application\Crud\Handlers\DemoPedidoPagarGuard::class,
    'demo_cliente_contacto'    => \App\Application\Crud\Handlers\DemoClienteContactoValidator::class,
```

- [ ] **Step 6: Verificar sintaxis**

Run: `php -l app/Application/Crud/Handlers/DemoPedidoTotalValidator.php && php -l app/Application/Crud/Handlers/DemoPedidoPagarGuard.php && php -l app/Application/Crud/Handlers/DemoClienteContactoValidator.php && php -l config/crud_handlers.php`
Expected: `No syntax errors detected` (x4).

- [ ] **Step 7: Commit**

```bash
git add app/Application/Crud/Handlers/DemoPedidoTotalValidator.php app/Application/Crud/Handlers/DemoPedidoPagarGuard.php app/Application/Crud/Handlers/DemoClienteContactoValidator.php config/crud_handlers.php
git commit -m "feat(crud): handlers/validators/guard demo para el showcase"
```

---

### Task 18: Configs demo extendidas + nuevos recursos

**Files:**
- Create: `config/cruds/demo_categorias.json`
- Create: `config/cruds/demo_pedidos.json`
- Modify: `config/cruds/demo_productos.json`
- Modify: `config/cruds/demo_clientes.json`

- [ ] **Step 1: Crear `config/cruds/demo_categorias.json`** (unique + mensaje custom + checkbox)

```json
{
  "resource": {
    "key": "demo_categorias",
    "title": "Demo Categorías",
    "table": "dom_demo_categorias",
    "primary_key": "id",
    "permission_prefix": "demo_categorias"
  },
  "security": { "allow_core_table": false, "mode": "restricted" },
  "list": {
    "columns": [
      { "name": "id", "label": "ID", "sortable": true },
      { "name": "nombre", "label": "Nombre", "searchable": true, "sortable": true },
      { "name": "activa", "label": "Activa", "badge": { "1": "success", "0": "secondary" } },
      { "name": "created_at", "label": "Creado", "format": "datetime", "sortable": true }
    ],
    "actions": ["show", "edit", "delete"]
  },
  "form": {
    "fields": [
      {
        "name": "nombre", "label": "Nombre", "type": "text", "required": true, "col": "col-md-8",
        "validation": {
          "maxlength": 120,
          "unique": { "ignore_self": true },
          "messages": { "required": "El nombre es obligatorio", "unique": "Ya existe una categoría con ese nombre" }
        }
      },
      { "name": "activa", "label": "Activa", "type": "checkbox", "col": "col-md-4", "default": 1 }
    ]
  },
  "detail": {
    "tabs": [
      { "key": "general", "label": "Datos generales", "type": "fields", "columns": ["nombre", "activa", "created_at"] },
      { "key": "historial", "label": "Historial", "type": "history" }
    ]
  },
  "uploads": { "enabled": false, "public_path": "uploads/cruds/demo_categorias" },
  "hooks": { "handler": null }
}
```

- [ ] **Step 2: Crear `config/cruds/demo_pedidos.json`** (estados + transiciones con guard + relación belongsTo + hasMany + validators + tabs + bulk + summaries)

```json
{
  "resource": {
    "key": "demo_pedidos",
    "title": "Demo Pedidos",
    "table": "dom_demo_pedidos",
    "primary_key": "id",
    "permission_prefix": "demo_pedidos"
  },
  "security": { "allow_core_table": false, "mode": "restricted" },
  "states": {
    "column": "status",
    "values": {
      "pendiente": { "label": "Pendiente", "badge": "warning" },
      "pagado":    { "label": "Pagado",    "badge": "success" },
      "cancelado": { "label": "Cancelado", "badge": "danger" }
    },
    "transitions": {
      "pendiente": ["pagado", "cancelado"],
      "pagado":    [],
      "cancelado": []
    }
  },
  "list": {
    "aggregation": { "enabled": true, "max_rows": 5000, "require_filter_above": 5000 },
    "columns": [
      { "name": "id", "label": "ID", "sortable": true },
      { "name": "folio", "label": "Folio", "searchable": true, "sortable": true },
      { "name": "total", "label": "Total", "format": "money", "sortable": true },
      { "name": "status", "label": "Estado", "badge": { "pendiente": "warning", "pagado": "success", "cancelado": "danger" } },
      { "name": "created_at", "label": "Creado", "format": "datetime", "sortable": true }
    ],
    "filters": [ { "field": "status", "label": "Estado" } ],
    "summaries": [ { "column": "total", "type": "sum", "format": "money", "label": "Suma total" } ],
    "actions": ["show", "edit", "delete"]
  },
  "actions": {
    "row": [
      { "name": "show", "type": "builtin" },
      { "name": "edit", "type": "builtin" },
      { "name": "delete", "type": "builtin" },
      { "name": "pagar", "type": "transition", "to": "pagado",
        "label": "Pagar", "icon": "bi-cash-coin", "method": "POST",
        "permission": "pagar", "confirm": "¿Marcar este pedido como pagado?",
        "visible_when": { "status": "pendiente" }, "guard": "demo_pedido_pagar_guard" },
      { "name": "cancelar", "type": "transition", "to": "cancelado",
        "label": "Cancelar", "icon": "bi-x-octagon", "method": "POST",
        "permission": "cancelar", "confirm": "¿Cancelar este pedido?",
        "visible_when": { "status": "pendiente" } }
    ],
    "bulk": [
      { "name": "cancelar", "type": "handler", "handler": "demo_pedido_total",
        "label": "Validar totales", "permission": "editar", "confirm": "¿Validar los pedidos seleccionados?" }
    ]
  },
  "relations": {
    "cliente": {
      "type": "belongsTo", "table": "dom_demo_clientes",
      "foreign_key": "cliente_id", "value": "id", "label": "nombre",
      "filter": { "status": "activo" }, "order_by": "nombre"
    },
    "items": {
      "type": "hasMany", "table": "dom_demo_pedido_items", "foreign_key": "pedido_id",
      "columns": [
        { "name": "descripcion", "label": "Descripción" },
        { "name": "cantidad", "label": "Cantidad" },
        { "name": "precio_unitario", "label": "P. Unitario", "format": "money" },
        { "name": "subtotal", "label": "Subtotal", "format": "money" }
      ],
      "order_by": "id", "direction": "ASC", "limit": 50
    }
  },
  "form": {
    "fields": [
      {
        "name": "folio", "label": "Folio", "type": "text", "required": true, "col": "col-md-4",
        "validation": {
          "maxlength": 40,
          "unique": { "ignore_self": true },
          "regex": "/^[A-Z0-9-]+$/",
          "messages": { "unique": "Ese folio ya existe", "regex": "Usa mayúsculas, dígitos y guiones" }
        }
      },
      {
        "name": "cliente_id", "label": "Cliente", "type": "relation", "relation": "cliente", "required": true,
        "col": "col-md-4",
        "validation": { "exists": { "table": "dom_demo_clientes", "column": "id" }, "messages": { "exists": "Selecciona un cliente válido" } }
      },
      {
        "name": "total", "label": "Total", "type": "text", "required": true, "col": "col-md-4",
        "validation": { "type": "decimal", "min": 0 },
        "help_text": "Debe ser mayor a cero (validado por regla de negocio)."
      },
      {
        "name": "status", "label": "Estado", "type": "select", "required": true, "col": "col-md-4",
        "options": { "pendiente": "Pendiente", "pagado": "Pagado", "cancelado": "Cancelado" },
        "default": "pendiente"
      },
      { "name": "notas", "label": "Notas", "type": "textarea", "required": false, "col": "col-12" }
    ],
    "validators": ["demo_pedido_total"]
  },
  "detail": {
    "tabs": [
      { "key": "general", "label": "Datos generales", "type": "fields", "columns": ["folio", "total", "status", "created_at"] },
      { "key": "items", "label": "Items", "type": "relation", "relation": "items" },
      { "key": "historial", "label": "Historial", "type": "history" }
    ]
  },
  "uploads": { "enabled": false, "public_path": "uploads/cruds/demo_pedidos" },
  "hooks": { "handler": null }
}
```

- [ ] **Step 3: Extender `config/cruds/demo_productos.json`**

Aplica estos cambios sobre el archivo existente:

1. En `form.fields`, al campo `codigo` añade `validation` con `unique` + mensaje:

```json
      {
        "name": "codigo",
        "label": "Código",
        "type": "text",
        "required": true,
        "col": "col-md-4",
        "validation": {
          "maxlength": 50,
          "unique": { "ignore_self": true },
          "messages": { "unique": "Ese código de producto ya existe" }
        }
      },
```

2. Agrega un campo de relación `categoria_id` después de `nombre` (antes de `precio_venta`):

```json
      {
        "name": "categoria_id",
        "label": "Categoría",
        "type": "relation",
        "relation": "categoria",
        "required": false,
        "col": "col-md-4",
        "validation": { "exists": { "table": "dom_demo_categorias", "column": "id" }, "messages": { "exists": "Categoría inválida" } }
      },
```

3. Agrega el bloque `relations` (nivel raíz, junto a `states`):

```json
  "relations": {
    "categoria": {
      "type": "belongsTo", "table": "dom_demo_categorias",
      "foreign_key": "categoria_id", "value": "id", "label": "nombre",
      "filter": { "activa": 1 }, "order_by": "nombre"
    }
  },
```

4. Agrega el bloque `detail` (nivel raíz):

```json
  "detail": {
    "tabs": [
      { "key": "general", "label": "Datos generales", "type": "fields", "columns": ["codigo", "nombre", "precio_venta", "stock_actual", "status"] },
      { "key": "historial", "label": "Historial", "type": "history" }
    ]
  },
```

5. En `list.columns`, agrega una columna de categoría (opcional, mejora la demo). Solo si quieres mostrarla: añade `{ "name": "categoria_id", "label": "Cat. ID", "sortable": true }`.

- [ ] **Step 4: Extender `config/cruds/demo_clientes.json`**

1. Al campo `email`, añade `validation` con `unique` + mensajes:

```json
      {
        "name": "email",
        "label": "Correo",
        "type": "text",
        "required": true,
        "col": "col-md-6",
        "validation": {
          "type": "email",
          "unique": { "ignore_self": true },
          "messages": { "email": "Correo inválido", "unique": "Ese correo ya está registrado" }
        }
      },
```

2. Agrega el bloque `form.validators` (dentro de `form`, junto a `fields`):

```json
    "validators": ["demo_cliente_contacto"]
```

3. Agrega el bloque `detail` (nivel raíz):

```json
  "detail": {
    "tabs": [
      { "key": "general", "label": "Datos generales", "type": "fields", "columns": ["nombre", "email", "telefono", "status", "created_at"] },
      { "key": "historial", "label": "Historial", "type": "history" }
    ]
  },
```

- [ ] **Step 5: Validar que todos los JSON son sintácticamente correctos**

Run: `php -r "foreach (glob('config/cruds/*.json') as $f) { json_decode(file_get_contents($f), true); echo $f . ': ' . (json_last_error() === JSON_ERROR_NONE ? 'OK' : json_last_error_msg()) . PHP_EOL; }"`
Expected: cada archivo imprime `: OK`.

- [ ] **Step 6: Commit**

```bash
git add config/cruds/demo_categorias.json config/cruds/demo_pedidos.json config/cruds/demo_productos.json config/cruds/demo_clientes.json
git commit -m "feat(crud): configs demo que ejercitan todo el motor (relaciones, tabs, validaciones)"
```

---

### Task 19: Script instalador idempotente

**Files:**
- Create: `scripts/install.php`

- [ ] **Step 1: Crear el instalador**

`scripts/install.php`:

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| install.php — Instalador idempotente de la aplicación
|--------------------------------------------------------------------------
| Aplica, en orden:
|   1) database/schema/schema.sql      (estructura base)
|   2) database/migrations/*.sql        (cambios incrementales, orden por nombre)
|   3) database/seeds/*.sql             (datos base + demo)
|
| Pensado para correr en cada despliegue. Las migraciones/seeds del proyecto
| usan CREATE TABLE IF NOT EXISTS / INSERT IGNORE / guards information_schema,
| por lo que re-ejecutarlo es seguro.
|
| Uso: php scripts/install.php
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$pdo = Connection::getInstance();

/**
 * Ejecuta un archivo SQL completo (multi-statement) vía PDO::exec.
 */
function runSqlFile(\PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new \RuntimeException("No se pudo leer {$path}");
    }
    $pdo->exec($sql);
}

echo "=== Instalación de la aplicación ===\n\n";

// 1) Schema base
echo "→ Schema base\n";
runSqlFile($pdo, ROOT_PATH . '/database/schema/schema.sql');
echo "   ✓ schema.sql\n";

// 2) Migraciones (orden lexicográfico por nombre de archivo)
$migrations = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
sort($migrations, SORT_STRING);
echo "\n→ Migraciones (" . count($migrations) . ")\n";
foreach ($migrations as $file) {
    runSqlFile($pdo, $file);
    echo '   ✓ ' . basename($file) . "\n";
}

// 3) Seeds (orden lexicográfico)
$seeds = glob(ROOT_PATH . '/database/seeds/*.sql') ?: [];
sort($seeds, SORT_STRING);
echo "\n→ Semillas (" . count($seeds) . ")\n";
foreach ($seeds as $file) {
    runSqlFile($pdo, $file);
    echo '   ✓ ' . basename($file) . "\n";
}

echo "\n=== Instalación completada ===\n";
```

- [ ] **Step 2: Verificar sintaxis**

Run: `php -l scripts/install.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add scripts/install.php
git commit -m "feat(install): instalador idempotente (schema + migraciones + seeds)"
```

---

### Task 20: Documentación + verificación final

**Files:**
- Modify: `docs/modules/crud/modulo-crud-engine.md`

- [ ] **Step 1: Documentar Fase 3 + Fase 4 + demo**

En `docs/modules/crud/modulo-crud-engine.md`, añade al final (después de la sección "Fase 2 — Estados / transiciones") estas secciones:

```markdown
## Fase 3 — Validaciones

- **Mensajes personalizados:** cada campo acepta `validation.messages` con pares `regla → mensaje` (ej. `required`, `unique`, `maxlength`, `regex`). Sobrescriben el texto por defecto.
- **`unique`:** `"unique": true` o `"unique": { "ignore_self": true }` (en update excluye la propia fila). Se evalúa contra filas no borradas (`deleted = 0`).
- **`exists`:** `"exists": { "table": "dom_...", "column": "id" }` valida que el valor exista en la tabla destino (FK lógica). La tabla destino debe usar prefijo permitido (no `auth_/cfg_/core_/log_`) y existir.
- **Validadores de formulario externos:** `form.validators: ["clave", ...]` ejecuta clases que implementan `CrudValidatorInterface` (acumulan errores cross-field vía `$ctx->addError()`). Se registran en `config/crud_handlers.php`.

Todos los errores (campo + DB + formulario) se acumulan y se lanzan en una sola `ValidationException`.

## Fase 4 — Relaciones y detalle con pestañas

- **`belongsTo`:** bloque `relations.<nombre>` con `{ table, foreign_key, value, label, filter, order_by }`. Un campo `type: relation` con `relation: "<nombre>"` se renderiza como `select` poblado desde la tabla relacionada (filtro estructurado `{col: val}`).
- **`hasMany`:** `{ table, foreign_key, columns, order_by, direction, limit }`. Consumido por pestañas de detalle read-only.
- **`detail.tabs`:** tipos `fields` (columnas), `relation` (hasMany), `history` (bitácora del registro) y `component` (vista whitelisteada bajo `Views/`, sin `..`). Sin bloque `detail`, el detalle muestra una sola pestaña "Datos generales".

## Demo: qué muestra cada recurso

- **demo_categorias:** `unique` con mensaje custom, checkbox, tab de historial.
- **demo_productos:** `unique` en código, `belongsTo` a categorías (`categoria_id`), `exists`, estados y transiciones, tabs general + historial.
- **demo_clientes:** `unique` email, validador de formulario (`demo_cliente_contacto`), tabs.
- **demo_pedidos:** estados + transiciones con guard (`demo_pedido_pagar_guard`), `belongsTo` cliente, `hasMany` items en tab, validador de total (`demo_pedido_total`), `unique`/`regex`/`exists`, summaries, bulk.

## Instalación / despliegue

`php scripts/install.php` aplica schema + migraciones + seeds de forma idempotente (incluye las tablas y datos demo). Seguro de re-ejecutar en cada despliegue.
```

- [ ] **Step 2: Verificación final completa**

Run: `php tests/run.php`
Expected: `N passed, 0 failed` (exit 0).

Run (verifica que todo el árbol PHP nuevo/modificado parsea):
```bash
php -l app/Application/Services/CrudDbConstraintValidator.php
php -l app/Application/Services/CrudRelationService.php
php -l app/Application/Services/CrudDetailBuilder.php
php -l app/Domain/Entities/Crud/CrudRelationDefinition.php
php -l app/Domain/Entities/Crud/CrudTabDefinition.php
```
Expected: `No syntax errors detected` en todos.

Run (con DB accesible — entorno de pruebas): aplica el instalador y valida que las configs cargan sin error de validación:
```bash
php scripts/install.php
```
Expected: termina en `=== Instalación completada ===` sin excepciones.

- [ ] **Step 3: Verificación manual (golden path, requiere DB + sesión admin)**

- [ ] `demo_categorias`: crear dos categorías con el mismo nombre → la segunda muestra "Ya existe una categoría con ese nombre".
- [ ] `demo_productos`: el formulario muestra el `select` de Categoría poblado; el detalle muestra pestañas (General + Historial).
- [ ] `demo_clientes`: marcar status=activo sin teléfono → error "Un cliente activo debe tener teléfono.".
- [ ] `demo_pedidos`: crear pedido con total 0 → bloqueado por el validador; el detalle muestra pestaña "Items" (hasMany) e "Historial"; la acción "Pagar" sobre un pedido con total > 0 funciona y registra `crud.transition`.
- [ ] Recursos simples sin `detail` (`clientes`) siguen mostrando una sola pestaña "Datos generales" idéntica a antes.

- [ ] **Step 4: Commit**

```bash
git add docs/modules/crud/modulo-crud-engine.md
git commit -m "docs(crud): documenta Fase 3, Fase 4, demo e instalador"
```

---

## Self-Review (cobertura del spec)

**Cobertura por sección del spec (`2026-05-28-crud-engine-fase2-design.md`):**
- §5 `validation.unique/exists` + `messages` → Tasks 1, 3, 6, 18. ✔
- §5 `form.validators` → Tasks 4, 5, 6, 17, 18. ✔
- §5 `relations` (belongsTo/hasMany) → Tasks 7, 9, 10, 11, 15, 18. ✔
- §5 `detail.tabs` (fields/relation/history/component) → Tasks 8, 13, 14, 15, 18. ✔
- §4 nuevos repos `existsWhere`/`distinctOptions`/`childrenBy` → Tasks 2, 9 (nombrados `existsForUnique`/`existsForReference`/`distinctOptions`/`childrenBy`). ✔
- §3.4 cadena de validación (campo → unique/exists → validadores de formulario) → Task 5. ✔
- §3.4 detalle con tabs (`CrudDetailBuilder`, reescritura `show.php`) → Tasks 13, 14. ✔
- §7 Fase 3 + Fase 4 → Partes A y B. ✔
- §8 riesgos: `show.php` sin `detail` = tab general (Task 13/14), `component` traversal (Tasks 14, 15), `exists`/relación con prefijo bloqueado (Tasks 6, 15). ✔
- §10 checklist de pruebas: unit de `CrudDbConstraintValidator`, mensajes custom, relación belongsTo/hasMany, config de tablas bloqueadas, traversal de component → Tasks 1, 3, 6, 10, 13, 15. ✔
- §11 no-goals (manyToMany, tabla de historial dedicada, migraciones formales) → respetados; el instalador es un runner pragmático, no un framework de migraciones.

**Requisito explícito del usuario:** extender los módulos demo para mostrar todo el potencial + tablas demo para el despliegue + configs adicionales si hace falta → Tasks 16–19 (nuevas tablas, nuevos recursos `demo_categorias`/`demo_pedidos`, extensión de `demo_productos`/`demo_clientes`, instalador). ✔

**Consistencia de tipos verificada:** `CrudConstraintRepositoryInterface::existsForUnique/existsForReference` usados igual en repo (Task 2), validador (Task 3) y fake (Task 3). `CrudRelationRepositoryInterface::distinctOptions/childrenBy` consistentes entre repo (Task 9), servicio (Task 10) y fakes. `CrudResourceDefinition`: nuevos params del constructor (`formValidators`, `relations`, `detailTabs`) se pasan siempre vía `fromArray` (los tests construyen por `fromArray`). `CrudFormBuilder` y `CrudResourceService` cambian su firma de constructor pero solo se instancian desde el container (Tasks 5, 11, 14).

**Riesgo conocido a vigilar en ejecución:** `DemoPedidoPagarGuard` asume `CrudTransitionContext::record()`; Task 17 Step 4 obliga a verificar el nombre real antes de continuar.

---

## Execution Handoff

Plan completo y guardado en `docs/superpowers/plans/2026-06-07-crud-engine-fase3-4-cierre.md`. Dos opciones de ejecución:

**1. Subagent-Driven (recomendada)** — Despacho un subagente fresco por tarea, reviso entre tareas, iteración rápida.

**2. Inline Execution** — Ejecuto las tareas en esta sesión con executing-plans, por lotes con checkpoints de revisión.

¿Cuál prefieres?
