# Cierre de parciales de la auditoría — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar los tres ítems "Parcial" de la auditoría que ya tienen código a medio camino: pie de totales en listado plano (#4), aislamiento por usuario / row-level scope (#3), y dashboard filtrado por permiso (#2).

**Architecture:** Todo el cambio vive en las capas Application/Presentation/Infrastructure existentes del CRUD Engine y del Dashboard. Se reutilizan piezas huérfanas (`CrudListScopeInterface`, `CrudListContext`) y se conecta el patrón `tienePermiso()` ya documentado. La regla de oro es **cero regresión**: un recurso sin la nueva config (`list.scope`) y un dashboard de admin con todos los permisos se comportan exactamente como hoy.

**Tech Stack:** PHP 8.1+, MVC + Onion (5 capas), microtest propio (`php tests/run.php`, sin DB — solo unit puro), Bootstrap 5 en vistas.

---

## Contexto que el implementador DEBE conocer

- **Harness de tests:** `php tests/run.php` ejecuta cualquier archivo `*Test.php` bajo `tests/`. El bootstrap (`tests/lib/bootstrap.php`) **solo** define rutas y carga el autoloader: **no hay conexión a base de datos**. Por eso todos los tests son unitarios puros. No escribas tests que toquen la DB; el `GenericCrudRepository` es `final` (no se puede subclasear para un spy).
- **Helpers de aserción disponibles** (`tests/lib/microtest.php`): `test(string, callable)`, `assert_true(bool, msg)`, `assert_same(expected, actual, msg)`, `assert_null(actual, msg)`, `assert_throws(class, callable, msg)`. No hay otros; no inventes `assert_equals`.
- **Estilo de test:** archivo plano, sin clase, una llamada `test('...', function () { ... });` por caso. Ver `tests/Crud/Action/CrudActionResolverTest.php` como referencia.
- **Ejecutar un subconjunto:** `php tests/run.php Scope` filtra por substring de ruta.
- **Convención `$can`:** el motor pasa una closure `fn(string $slug): bool` para chequear permisos (ver `CrudActionResolver`). Reutilizamos ese patrón para el scope.
- **Trabajo directo sobre `main`** (sin PR; el VPS auto-pull). Commits frecuentes.
- **Slugs de permisos reales** (de `database/seeds_legacy/baseline-2026-06/010_auth_permisos.sql` y `routes/web.php`): Usuarios → `usuarios.gestionar`; Roles → `roles.gestionar`; Ajustes → `administracion.ver`. **No inventes slugs**: un slug inexistente ocultaría todo.

---

## File Structure

| Capa | Archivo | Acción | Ítem |
|------|---------|--------|------|
| Application | `app/Application/Services/CrudTableBuilder.php` | editar (extraer `formatScalar`, pie plano) | #4 |
| Presentation | `app/Presentation/Views/admin/crud/index.php` | editar (`<tfoot>` plano) | #4 |
| Domain | `app/Domain/Entities/CrudResourceDefinition.php` | editar (accesores `listScope`/`listScopeHandler`) | #3 |
| Application | `app/Application/Crud/Scopes/OwnerListScope.php` | nuevo | #3 |
| Application | `app/Application/Services/CrudScopeResolver.php` | nuevo | #3 |
| Application | `app/Application/Services/CrudConfigValidator.php` | editar (validar `scope`/`scope_handler`) | #3 |
| Application | `app/Application/Services/CrudDataService.php` | editar (firma `list` + cableado scope) | #3 |
| Application | `app/Application/Services/CrudResourceService.php` | editar (bloqueo show/edit/update/delete + propagar userId) | #3 |
| Presentation | `app/Presentation/Controllers/Admin/CrudController.php` | editar (propagar userId a index/show/edit) | #3 |
| Kernel/Config | `config/container.php` | editar (inyectar `CrudScopeResolver`) | #3 |
| Infrastructure | `app/Infrastructure/Dashboard/DefaultPlatformDashboardProvider.php` | editar (filtrar por permiso) | #2 |
| Tests | `tests/Crud/Table/`, `tests/Crud/Scope/`, `tests/Dashboard/` | nuevo | #2/#3/#4 |
| Docs | `docs/modules/crud/modulo-crud-engine.md` | documentar `list.scope` + pie plano | #3/#4 |

**Decisión de no-tocar:** los `config/cruds/demo_*.json` **no se modifican**. `demo_productos.json` ya declara `list.summaries` con `group_by: ""`, así que el pie plano (#4) se valida e2e ahí sin cambiar config. Ningún demo declara `scope`, así que #3 no los afecta.

---

## Ítem #4 — Pie de totales en listado plano

### Task 1: `CrudTableBuilder` — extraer `formatScalar` y construir pie plano

**Files:**
- Modify: `app/Application/Services/CrudTableBuilder.php`
- Test: `tests/Crud/Table/CrudTableBuilderSummaryTest.php`

**Contexto:** En modo agrupado, `$columns` contiene la columna de grupo + las columnas-alias de summary (`crud_sum_X`), y `formatRow($summaryRow, $columns)` formatea por esos alias. En modo **plano**, `$columns` son las columnas literales del listado (`id`, `codigo`, `precio_venta`, …) y **no** coinciden con las claves de `$summaryRow` (que vienen como `crud_sum_precio_venta`, `crud_cnt_X` desde `selectGlobalAggregates`). El cambio mapea cada summary a su columna literal.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Crud/Table/CrudTableBuilderSummaryTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudTableBuilder;
use App\Domain\Entities\CrudResourceDefinition;
use App\Kernel\Helpers\Paginator;

function tb_flat_def(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => '',
            'columns' => [
                ['name' => 'id', 'label' => 'ID'],
                ['name' => 'nombre', 'label' => 'Nombre'],
                ['name' => 'precio_venta', 'label' => 'Precio', 'format' => 'money'],
                ['name' => 'stock_actual', 'label' => 'Stock'],
            ],
            'summaries' => [
                ['column' => 'precio_venta', 'type' => 'sum', 'format' => 'money', 'label' => 'Suma precio'],
                ['column' => 'stock_actual', 'type' => 'sum', 'label' => 'Suma stock'],
            ],
        ],
    ]);
}

function tb_paginator(): Paginator
{
    return new Paginator(total: 0, perPage: 15, currentPage: 1, baseUrl: '/admin/crud/demo');
}

test('CrudTableBuilder: pie plano coloca cada summary en su columna y deja el resto vacío', function (): void {
    $builder = new CrudTableBuilder();
    $summaryRow = ['crud_sum_precio_venta' => 1234.5, 'crud_sum_stock_actual' => 42];

    $vm = $builder->build(
        definition: tb_flat_def(),
        rows: [],
        paginator: tb_paginator(),
        total: 0,
        permissions: [],
        query: [],
        groupBy: '',
        summaryRow: $summaryRow
    );

    assert_true(isset($vm['summaryRow']['_formatted']), 'falta _formatted en summaryRow');
    $cells = $vm['summaryRow']['_formatted'];
    assert_same('$1,234.50', $cells['precio_venta'] ?? null, 'sum precio formateado money');
    assert_same(42, $cells['stock_actual'] ?? null, 'sum stock sin formato');
    assert_true(!array_key_exists('id', $cells), 'columnas sin summary no aparecen');
    assert_true(!array_key_exists('nombre', $cells), 'columnas sin summary no aparecen');
});

test('CrudTableBuilder: plano sin summaries devuelve pie vacío', function (): void {
    $builder = new CrudTableBuilder();
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => ['group_by' => '', 'columns' => [['name' => 'id', 'label' => 'ID']]],
    ]);
    $vm = $builder->build(
        definition: $def, rows: [], paginator: tb_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    assert_same([], $vm['summaryRow'], 'pie vacío sin summaries');
});

test('CrudTableBuilder: modo agrupado conserva el pie por alias (sin regresión)', function (): void {
    $builder = new CrudTableBuilder();
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => 'nombre',
            'columns' => [['name' => 'nombre', 'label' => 'Nombre'], ['name' => 'precio_venta', 'label' => 'Precio']],
            'summaries' => [['column' => 'precio_venta', 'type' => 'sum', 'label' => 'Total']],
        ],
    ]);
    $vm = $builder->build(
        definition: $def, rows: [], paginator: tb_paginator(), total: 0,
        permissions: [], query: [], groupBy: 'nombre',
        summaryRow: ['crud_sum_precio_venta' => 99]
    );
    assert_same(99, $vm['summaryRow']['_formatted']['crud_sum_precio_venta'] ?? null, 'pie agrupado por alias intacto');
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php tests/run.php CrudTableBuilderSummary`
Expected: FAIL — el primer test falla porque hoy `summaryRow` formateado solo se llena cuando `$grouped` es true (queda `[]` en plano).

- [ ] **Step 3: Implementar el cambio mínimo**

En `app/Application/Services/CrudTableBuilder.php`, reemplazar el bloque actual de formateo del summary (líneas ~83-86):

```php
        $formattedSummary = [];
        if ($grouped && $summaryRow !== []) {
            $formattedSummary = $this->formatRow($summaryRow, $columns);
        }
```

por:

```php
        $formattedSummary = [];
        if ($summaryRow !== []) {
            if ($grouped) {
                $formattedSummary = $this->formatRow($summaryRow, $columns);
            } else {
                $cells = [];
                foreach (is_array($summaries) ? $summaries : [] as $summary) {
                    $type = (string) ($summary['type'] ?? '');
                    $col  = (string) ($summary['column'] ?? '');
                    if ($col === '') {
                        continue;
                    }
                    if ($type === 'sum') {
                        $alias = 'crud_sum_' . $col;
                    } elseif ($type === 'count') {
                        $alias = 'crud_cnt_' . $col;
                    } else {
                        continue;
                    }
                    if (!array_key_exists($alias, $summaryRow)) {
                        continue;
                    }
                    $cells[$col] = $this->formatScalar($summaryRow[$alias], (string) ($summary['format'] ?? ''));
                }
                if ($cells !== []) {
                    $formattedSummary = ['_formatted' => $cells];
                }
            }
        }
```

Luego, extraer el helper `formatScalar` y reutilizarlo en `formatRow`. Añadir este método privado al final de la clase (antes del `}` de cierre):

```php
    private function formatScalar(mixed $value, string $format): mixed
    {
        if ($format === 'date' && !empty($value)) {
            $timestamp = strtotime((string) $value);
            return $timestamp ? date('d/m/Y', $timestamp) : $value;
        }

        if ($format === 'datetime' && !empty($value)) {
            $timestamp = strtotime((string) $value);
            return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
        }

        if ($format === 'money' && $value !== null && $value !== '') {
            return '$' . number_format((float) $value, 2, '.', ',');
        }

        return $value;
    }
```

Y refactorizar `formatRow` para usarlo. Reemplazar las tres ramas de formato dentro de `formatRow` (líneas ~119-134) por:

```php
            if (in_array($format, ['date', 'datetime', 'money'], true)) {
                $row['_formatted'][$name] = $this->formatScalar($value, $format);
                if ($format === 'money' && ($value === null || $value === '')) {
                    // money vacío: dejar el valor crudo como hacía la rama original
                    $row['_formatted'][$name] = $value;
                }
                if (in_array($format, ['date', 'datetime'], true) && empty($value)) {
                    $row['_formatted'][$name] = $value;
                }
                if ($this->formatScalar($value, $format) !== $value || !empty($value)) {
                    continue;
                }
            }
```

> **Nota de implementación:** el refactor de `formatRow` debe preservar el comportamiento exacto actual (badge incluido). Si la equivalencia te resulta arriesgada, **opción más simple y segura**: deja `formatRow` intacto (no lo toques) y solo añade `formatScalar` como helper nuevo usado únicamente por el pie plano. La DRY del pie justifica el helper; reescribir `formatRow` es opcional. Prefiere esta opción si dudas.

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php tests/run.php CrudTableBuilderSummary`
Expected: PASS (3 tests).

- [ ] **Step 5: Correr toda la suite (no regresión)**

Run: `php tests/run.php`
Expected: todo en verde.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudTableBuilder.php tests/Crud/Table/CrudTableBuilderSummaryTest.php
git commit -m "feat(crud): pie de totales en listado plano (CrudTableBuilder)"
```

---

### Task 2: Vista `index.php` — renderizar `<tfoot>` plano

**Files:**
- Modify: `app/Presentation/Views/admin/crud/index.php`

No hay test de vista en este harness (las vistas se validan con el smoke manual de la Task 11). Cambio acotado y alineado con `<thead>`.

- [ ] **Step 1: Añadir la rama de pie plano**

En `app/Presentation/Views/admin/crud/index.php`, localizar el bloque del `<tfoot>` agrupado (líneas ~162-177):

```php
                    <?php if ($grouped && !empty($summaryRow) && count($columns ?? []) > 1): ?>
                        <tfoot class="table-group-divider">
                            ... (bloque agrupado actual, NO tocar) ...
                        </tfoot>
                    <?php endif; ?>
```

Inmediatamente **después** del `<?php endif; ?>` de ese bloque, agregar:

```php
                    <?php if (!$grouped && !empty($summaryRow['_formatted'])): ?>
                        <tfoot class="table-group-divider">
                            <tr class="table-light fw-semibold">
                                <?php $sumCells = $summaryRow['_formatted']; $labelPlaced = false; ?>
                                <?php if (!empty($selectable)): ?>
                                    <td class="px-3">Totales</td>
                                    <?php $labelPlaced = true; ?>
                                <?php endif; ?>
                                <?php foreach (($columns ?? []) as $column): ?>
                                    <?php
                                        $cname = (string) ($column['name'] ?? '');
                                        $val = $sumCells[$cname] ?? '';
                                    ?>
                                    <?php if (!$labelPlaced && ($val === '' || $val === null)): ?>
                                        <td class="px-3 text-muted">Totales</td>
                                        <?php $labelPlaced = true; ?>
                                    <?php else: ?>
                                        <td class="px-3"><?= ViewHelper::e((string) $val) ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <td class="px-3"></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
```

> El `<td class="px-3"></td>` final corresponde a la columna "Acciones" (en modo plano `!$grouped` siempre existe esa columna, ver `<thead>` líneas 116-118). La celda de etiqueta "Totales" cae en la primera columna sin summary; si todas las columnas tuvieran summary no se muestra texto (los números hablan).

- [ ] **Step 2: Verificación manual rápida**

(Se ejecuta junto al smoke de la Task 11.) Levantar `php -S localhost:8000 -t public`, entrar a `/admin/crud/demo_productos`. El pie debe mostrar "Totales" en la columna ID y las sumas alineadas bajo Precio y Stock. Si hay filtro que dispara `aggregationSkipped`, el pie no aparece (porque `summaryRow` llega `[]`).

- [ ] **Step 3: Commit**

```bash
git add app/Presentation/Views/admin/crud/index.php
git commit -m "feat(crud): tfoot de totales en vista de listado plano"
```

---

## Ítem #3 — Aislamiento por usuario (row-level scope)

### Task 3: `CrudResourceDefinition` — accesores de scope

**Files:**
- Modify: `app/Domain/Entities/CrudResourceDefinition.php`
- Test: `tests/Crud/Scope/CrudResourceDefinitionScopeTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Crud/Scope/CrudResourceDefinitionScopeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;

test('CrudResourceDefinition: list.scope owner se expone como array', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id', 'permission_prefix' => 'clientes'],
        'list' => ['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']],
    ]);
    $scope = $def->listScope();
    assert_true(is_array($scope), 'scope debe ser array');
    assert_same('owner', $scope['type']);
    assert_same('created_by', $scope['column']);
    assert_same('{prefix}.ver_todos', $scope['bypass_permission']);
    assert_null($def->listScopeHandler());
});

test('CrudResourceDefinition: list.scope_handler se expone como string', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id', 'permission_prefix' => 'clientes'],
        'list' => ['scope_handler' => 'clientes_owner'],
    ]);
    assert_same('clientes_owner', $def->listScopeHandler());
    assert_null($def->listScope());
});

test('CrudResourceDefinition: sin scope ambos accesores son null', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'd', 'table' => 'dom_d', 'primary_key' => 'id', 'permission_prefix' => 'd'],
        'list' => ['columns' => [['name' => 'id', 'label' => 'ID']]],
    ]);
    assert_null($def->listScope());
    assert_null($def->listScopeHandler());
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php tests/run.php CrudResourceDefinitionScope`
Expected: FAIL — `Call to undefined method ...->listScope()`.

- [ ] **Step 3: Implementar**

En `app/Domain/Entities/CrudResourceDefinition.php`:

1. Añadir dos parámetros `readonly` al **final** del constructor (tras `array $detailTabs`):

```php
        private readonly array $detailTabs,
        private readonly ?array $listScope,
        private readonly ?string $listScopeHandler
```

2. En `fromArray`, antes del `return new self(`, calcular:

```php
        $listScope = is_array($list['scope'] ?? null) ? $list['scope'] : null;
        $listScopeHandler = (isset($list['scope_handler']) && is_string($list['scope_handler']) && $list['scope_handler'] !== '')
            ? $list['scope_handler']
            : null;
```

3. En la llamada `new self(...)`, añadir los dos argumentos nombrados (el orden no importa porque son named args):

```php
            detailTabs: $detailTabs,
            listScope: $listScope,
            listScopeHandler: $listScopeHandler
```

4. Añadir los accesores (junto a los demás getters):

```php
    /** @return array<string, mixed>|null */
    public function listScope(): ?array { return $this->listScope; }

    public function listScopeHandler(): ?string { return $this->listScopeHandler; }
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php tests/run.php CrudResourceDefinitionScope`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/CrudResourceDefinition.php tests/Crud/Scope/CrudResourceDefinitionScopeTest.php
git commit -m "feat(crud): accesores list.scope / list.scope_handler en CrudResourceDefinition"
```

---

### Task 4: `OwnerListScope`

**Files:**
- Create: `app/Application/Crud/Scopes/OwnerListScope.php`
- Test: `tests/Crud/Scope/OwnerListScopeTest.php`

**Diseño:** El scope se construye con la columna de propiedad, un booleano `hasBypass` ya resuelto, y el `userId`. `apply()` solo añade una condición al contexto. Sin bypass y con `userId` → `column = userId`. Con bypass → no añade nada (ve todo). `userId` null sin bypass → `column = -1` (id imposible, no-fuga).

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Crud/Scope/OwnerListScopeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudListContext;
use App\Application\Crud\Scopes\OwnerListScope;

function scope_ctx(?int $userId): CrudListContext
{
    return new CrudListContext('clientes', 'dom_clientes', 'id', $userId, '127.0.0.1', []);
}

test('OwnerListScope: sin bypass añade created_by = userId', function (): void {
    $ctx = scope_ctx(7);
    (new OwnerListScope('created_by', false, 7))->apply($ctx);
    assert_same([['column' => 'created_by', 'op' => '=', 'value' => 7]], $ctx->conditions());
});

test('OwnerListScope: con bypass no añade condición (ve todo)', function (): void {
    $ctx = scope_ctx(7);
    (new OwnerListScope('created_by', true, 7))->apply($ctx);
    assert_same([], $ctx->conditions());
});

test('OwnerListScope: userId null sin bypass aplica no-fuga (created_by = -1)', function (): void {
    $ctx = scope_ctx(null);
    (new OwnerListScope('created_by', false, null))->apply($ctx);
    assert_same([['column' => 'created_by', 'op' => '=', 'value' => -1]], $ctx->conditions());
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php tests/run.php OwnerListScope`
Expected: FAIL — clase `OwnerListScope` no existe.

- [ ] **Step 3: Implementar**

Crear `app/Application/Crud/Scopes/OwnerListScope.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Scopes;

use App\Application\Crud\Context\CrudListContext;
use App\Domain\Interfaces\CrudListScopeInterface;

/**
 * Scope built-in de propiedad por usuario. Restringe el listado a las filas
 * cuya columna de autor coincide con el usuario actual, salvo que tenga el
 * permiso de bypass (ve todo). userId null sin bypass => no devuelve filas.
 */
final class OwnerListScope implements CrudListScopeInterface
{
    public function __construct(
        private readonly string $column,
        private readonly bool $hasBypass,
        private readonly ?int $userId
    ) {}

    public function apply(CrudListContext $ctx): void
    {
        if ($this->hasBypass) {
            return;
        }

        if ($this->userId === null) {
            // Política de no-fuga: id imposible para vaciar el listado.
            $ctx->addCondition($this->column, '=', -1);
            return;
        }

        $ctx->addCondition($this->column, '=', $this->userId);
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php tests/run.php OwnerListScope`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Crud/Scopes/OwnerListScope.php tests/Crud/Scope/OwnerListScopeTest.php
git commit -m "feat(crud): OwnerListScope built-in (row-level por autor)"
```

---

### Task 5: `CrudScopeResolver`

**Files:**
- Create: `app/Application/Services/CrudScopeResolver.php`
- Test: `tests/Crud/Scope/CrudScopeResolverTest.php`

**Diseño:** Punto único de resolución. `resolve()` elige built-in / handler / null. `ownerMeta()` expone `{column, bypass}` (bypass ya con `{prefix}` expandido) para que el listado **y** el bloqueo server-side compartan una sola fuente de verdad. `conditionsToSql()` (estático, puro) traduce las condiciones del contexto a `$where`/`$params`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Crud/Scope/CrudScopeResolverTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudListContext;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudScopeResolver;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\CrudListScopeInterface;

if (!class_exists('FixtureCustomScope')) {
    class FixtureCustomScope implements CrudListScopeInterface
    {
        public function apply(CrudListContext $ctx): void
        {
            $ctx->addCondition('created_by', '=', 99);
        }
    }
}

function scope_def(array $list): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id', 'permission_prefix' => 'clientes'],
        'list' => $list,
    ]);
}

test('CrudScopeResolver: scope owner sin bypass produce OwnerListScope que filtra por userId', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']]);
    $deny = static fn(string $slug): bool => false;

    $scope = $resolver->resolve($def, 7, $deny);
    assert_true($scope instanceof CrudListScopeInterface, 'devuelve un scope');

    $ctx = new CrudListContext('clientes', 'dom_clientes', 'id', 7, '', []);
    $scope->apply($ctx);
    assert_same([['column' => 'created_by', 'op' => '=', 'value' => 7]], $ctx->conditions());
});

test('CrudScopeResolver: ownerMeta expande {prefix} en bypass', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']]);
    $meta = $resolver->ownerMeta($def);
    assert_same('created_by', $meta['column']);
    assert_same('clientes.ver_todos', $meta['bypass']);
});

test('CrudScopeResolver: usuario con bypass obtiene un scope que NO filtra', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']]);
    $allow = static fn(string $slug): bool => $slug === 'clientes.ver_todos';

    $scope = $resolver->resolve($def, 7, $allow);
    $ctx = new CrudListContext('clientes', 'dom_clientes', 'id', 7, '', []);
    $scope->apply($ctx);
    assert_same([], $ctx->conditions(), 'con bypass no hay filtro');
});

test('CrudScopeResolver: scope_handler resuelve la clase registrada', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry(['clientes_owner' => FixtureCustomScope::class]));
    $def = scope_def(['scope_handler' => 'clientes_owner']);
    $scope = $resolver->resolve($def, 7, static fn(string $s): bool => false);
    assert_true($scope instanceof FixtureCustomScope, 'resuelve el handler custom');
});

test('CrudScopeResolver: sin scope devuelve null', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['columns' => [['name' => 'id', 'label' => 'ID']]]);
    assert_null($resolver->resolve($def, 7, static fn(string $s): bool => false));
    assert_null($resolver->ownerMeta($def));
});

test('CrudScopeResolver: conditionsToSql traduce = a backtick + placeholder', function (): void {
    [$where, $params] = CrudScopeResolver::conditionsToSql([
        ['column' => 'created_by', 'op' => '=', 'value' => 7],
    ]);
    assert_same(['`created_by` = ?'], $where);
    assert_same([7], $params);
});

test('CrudScopeResolver: conditionsToSql expande IN con array', function (): void {
    [$where, $params] = CrudScopeResolver::conditionsToSql([
        ['column' => 'created_by', 'op' => 'IN', 'value' => [1, 2, 3]],
    ]);
    assert_same(['`created_by` IN (?, ?, ?)'], $where);
    assert_same([1, 2, 3], $params);
});

test('CrudScopeResolver: conditionsToSql con IN vacío fuerza conjunto vacío', function (): void {
    [$where, $params] = CrudScopeResolver::conditionsToSql([
        ['column' => 'created_by', 'op' => 'IN', 'value' => []],
    ]);
    assert_same(['1 = 0'], $where);
    assert_same([], $params);
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php tests/run.php CrudScopeResolver`
Expected: FAIL — clase `CrudScopeResolver` no existe.

- [ ] **Step 3: Implementar**

Crear `app/Application/Services/CrudScopeResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Crud\Scopes\OwnerListScope;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\CrudListScopeInterface;

/**
 * Resuelve el scope de listado de un recurso (built-in owner o handler custom)
 * y traduce las condiciones acumuladas a SQL. Fuente única de verdad para el
 * filtrado del listado y el bloqueo server-side (show/edit/update/delete).
 */
final class CrudScopeResolver
{
    public function __construct(
        private readonly ?CrudHandlerRegistry $handlerRegistry = null
    ) {}

    /**
     * @param callable(string): bool $can
     */
    public function resolve(CrudResourceDefinition $definition, ?int $userId, callable $can): ?CrudListScopeInterface
    {
        $handlerKey = $definition->listScopeHandler();
        if ($handlerKey !== null && $handlerKey !== '' && $this->handlerRegistry !== null) {
            $scope = $this->handlerRegistry->resolve($handlerKey, CrudListScopeInterface::class);
            return $scope instanceof CrudListScopeInterface ? $scope : null;
        }

        $meta = $this->ownerMeta($definition);
        if ($meta === null) {
            return null;
        }

        $hasBypass = $meta['bypass'] !== null && $can($meta['bypass']);
        return new OwnerListScope($meta['column'], $hasBypass, $userId);
    }

    /**
     * Metadata de propiedad para el bloqueo server-side. bypass ya con {prefix}
     * expandido. Devuelve null si el recurso no declara scope owner.
     *
     * @return array{column: string, bypass: ?string}|null
     */
    public function ownerMeta(CrudResourceDefinition $definition): ?array
    {
        $scope = $definition->listScope();
        if (!is_array($scope) || (string) ($scope['type'] ?? '') !== 'owner') {
            return null;
        }
        $column = (string) ($scope['column'] ?? '');
        if ($column === '') {
            return null;
        }
        $bypassRaw = isset($scope['bypass_permission']) && is_string($scope['bypass_permission']) && $scope['bypass_permission'] !== ''
            ? $scope['bypass_permission']
            : null;
        $bypass = $bypassRaw !== null
            ? str_replace('{prefix}', $definition->permissionPrefix(), $bypassRaw)
            : null;

        return ['column' => $column, 'bypass' => $bypass];
    }

    /**
     * Traduce condiciones estructuradas a partes WHERE + params posicionales.
     *
     * @param list<array{column: string, op: string, value: mixed}> $conditions
     * @return array{0: list<string>, 1: list<mixed>}
     */
    public static function conditionsToSql(array $conditions): array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $cond) {
            $column = '`' . str_replace('`', '', (string) ($cond['column'] ?? '')) . '`';
            $op = (string) ($cond['op'] ?? '=');
            $value = $cond['value'] ?? null;

            if ($op === 'IN' && is_array($value)) {
                if ($value === []) {
                    $where[] = '1 = 0';
                    continue;
                }
                $where[] = $column . ' IN (' . implode(', ', array_fill(0, count($value), '?')) . ')';
                foreach ($value as $v) {
                    $params[] = $v;
                }
                continue;
            }

            $where[] = $column . ' ' . $op . ' ?';
            $params[] = $value;
        }

        return [$where, $params];
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php tests/run.php CrudScopeResolver`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudScopeResolver.php tests/Crud/Scope/CrudScopeResolverTest.php
git commit -m "feat(crud): CrudScopeResolver (resolución y traducción SQL de scope)"
```

---

### Task 6: `CrudConfigValidator` — validar `scope` / `scope_handler`

**Files:**
- Modify: `app/Application/Services/CrudConfigValidator.php`
- Test: `tests/Crud/Scope/CrudConfigValidatorScopeTest.php`

**Diseño:** Método estático puro `scopeShapeErrors($config)` (mutual-exclusión, `type=owner`, `column` requerido, tipos), llamado desde `validate()`. La existencia de la columna y el registro del handler se chequean dentro de `validate()` (necesitan DB/registry), igual que el patrón de `form.validators`. Los tests apuntan al método puro.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Crud/Scope/CrudConfigValidatorScopeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('CrudConfigValidator: scope + scope_handler juntos es error', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => [
            'scope' => ['type' => 'owner', 'column' => 'created_by'],
            'scope_handler' => 'clientes_owner',
        ],
    ]);
    assert_true(in_array('list.scope y list.scope_handler son mutuamente excluyentes.', $errors, true), 'falta error de exclusión');
});

test('CrudConfigValidator: scope.type inválido es error', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => ['scope' => ['type' => 'tenant', 'column' => 'created_by']],
    ]);
    assert_true(in_array("list.scope.type debe ser 'owner'.", $errors, true), 'falta error de type');
});

test('CrudConfigValidator: scope sin column es error', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => ['scope' => ['type' => 'owner']],
    ]);
    assert_true(in_array('list.scope.column es obligatorio.', $errors, true), 'falta error de column');
});

test('CrudConfigValidator: scope owner válido no genera errores de forma', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => ['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']],
    ]);
    assert_same([], $errors);
});

test('CrudConfigValidator: sin list.scope ni handler no genera errores', function (): void {
    assert_same([], CrudConfigValidator::scopeShapeErrors([]));
    assert_same([], CrudConfigValidator::scopeShapeErrors(['list' => ['columns' => []]]));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php tests/run.php CrudConfigValidatorScope`
Expected: FAIL — método `scopeShapeErrors` no existe.

- [ ] **Step 3: Implementar el método estático**

En `app/Application/Services/CrudConfigValidator.php`, añadir este método estático (junto a los demás `*Errors` estáticos):

```php
    /**
     * Valida la forma del bloque list.scope / list.scope_handler. Pura, sin DB.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function scopeShapeErrors(array $config): array
    {
        $list = $config['list'] ?? null;
        if (!is_array($list)) {
            return [];
        }

        $hasScope = array_key_exists('scope', $list);
        $hasHandler = array_key_exists('scope_handler', $list);
        $errors = [];

        if ($hasScope && $hasHandler) {
            $errors[] = 'list.scope y list.scope_handler son mutuamente excluyentes.';
        }

        if ($hasScope) {
            $scope = $list['scope'];
            if (!is_array($scope)) {
                $errors[] = 'list.scope debe ser un objeto.';
            } else {
                if ((string) ($scope['type'] ?? '') !== 'owner') {
                    $errors[] = "list.scope.type debe ser 'owner'.";
                }
                if ((string) ($scope['column'] ?? '') === '') {
                    $errors[] = 'list.scope.column es obligatorio.';
                }
                if (array_key_exists('bypass_permission', $scope) && !is_string($scope['bypass_permission'])) {
                    $errors[] = 'list.scope.bypass_permission debe ser string.';
                }
            }
        }

        if ($hasHandler && (!is_string($list['scope_handler']) || $list['scope_handler'] === '')) {
            $errors[] = 'list.scope_handler debe ser una clave string no vacía.';
        }

        return $errors;
    }
```

- [ ] **Step 4: Cablear en `validate()` (forma + existencia + registro)**

En `validate()`, justo después de la línea `$this->validateListAggregationConfig($config, $errors);`, añadir:

```php
        foreach (self::scopeShapeErrors($config) as $scopeError) {
            $errors[] = $scopeError;
        }

        $listScope = is_array($config['list']['scope'] ?? null) ? $config['list']['scope'] : null;
        if ($listScope !== null) {
            $scopeColumn = (string) ($listScope['column'] ?? '');
            if ($scopeColumn !== '' && $table !== '' && !isset($columnLookup[$scopeColumn])) {
                $errors[] = "list.scope.column ({$scopeColumn}) no existe en {$table}.";
            }
        }

        $scopeHandler = $config['list']['scope_handler'] ?? null;
        if (is_string($scopeHandler) && $scopeHandler !== '') {
            if (!$this->handlerRegistry->hasKey($scopeHandler)) {
                $errors[] = "list.scope_handler '{$scopeHandler}' no está registrado en config/crud_handlers.php.";
            } else {
                $class = $this->handlerRegistry->classForKey($scopeHandler);
                if ($class === null || !class_exists($class)) {
                    $errors[] = "La clase del scope '{$scopeHandler}' no existe o no es autoload-eable.";
                } elseif (!in_array(\App\Domain\Interfaces\CrudListScopeInterface::class, class_implements($class) ?: [], true)) {
                    $errors[] = "El scope '{$scopeHandler}' ({$class}) debe implementar CrudListScopeInterface.";
                }
            }
        }
```

- [ ] **Step 5: Correr y verificar que pasa**

Run: `php tests/run.php CrudConfigValidatorScope`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudConfigValidator.php tests/Crud/Scope/CrudConfigValidatorScopeTest.php
git commit -m "feat(crud): validación de list.scope / list.scope_handler"
```

---

### Task 7: Cablear el scope en `CrudDataService::list()` + container

**Files:**
- Modify: `app/Application/Services/CrudDataService.php`
- Modify: `config/container.php`

**Nota de cobertura:** la lógica de scope ya está cubierta por unit tests (Tasks 4-5). Este paso es glue mínimo: construir el contexto, resolver, aplicar y traducir antes del conteo. Se valida end-to-end en el smoke manual (Task 11). No hay test DB porque el harness no tiene base de datos y `GenericCrudRepository` es `final`.

- [ ] **Step 1: Añadir la dependencia y cambiar la firma**

En `app/Application/Services/CrudDataService.php`:

1. Añadir el import:

```php
use App\Application\Crud\Context\CrudListContext;
```

2. Añadir `?CrudScopeResolver $scopeResolver = null` al final del constructor:

```php
        private readonly ?CrudHandlerRegistry $handlerRegistry = null,
        private readonly ?CrudScopeResolver $scopeResolver = null
    ) {}
```

3. Cambiar la firma de `list()`:

```php
    public function list(CrudResourceDefinition $definition, array $query, ?int $userId = null, ?callable $can = null): array
```

- [ ] **Step 2: Inyectar el filtro de scope antes del conteo**

En `list()`, localizar (líneas ~101-103):

```php
        }

        $candidateCount = $this->repository->countFiltered($definition->table(), $where, $params);
```

Insertar, **entre** el cierre del `foreach` de filtros y la línea de `countFiltered`:

```php
        }

        if ($this->scopeResolver !== null) {
            $canCheck = $can ?? static fn(string $slug): bool => false;
            $scope = $this->scopeResolver->resolve($definition, $userId, $canCheck);
            if ($scope !== null) {
                $scopeCtx = new CrudListContext(
                    $definition->key(),
                    $definition->table(),
                    $definition->primaryKey(),
                    $userId,
                    '',
                    $query
                );
                $scope->apply($scopeCtx);
                [$scopeWhere, $scopeParams] = CrudScopeResolver::conditionsToSql($scopeCtx->conditions());
                foreach ($scopeWhere as $part) {
                    $where[] = $part;
                }
                foreach ($scopeParams as $param) {
                    $params[] = $param;
                }
            }
        }

        $candidateCount = $this->repository->countFiltered($definition->table(), $where, $params);
```

> Al inyectarse **antes** de `countFiltered`, el scope se respeta en conteo, paginación, agregados y pie de totales (todos derivan de `$where`/`$params`).

- [ ] **Step 3: Actualizar el binding del container**

En `config/container.php`, primero añadir el import junto a los demás `use App\Application\Services\Crud*;` (tras la línea `use App\Application\Services\CrudRelationService;`):

```php
use App\Application\Services\CrudScopeResolver;
```

Registrar el singleton (tras el bloque de `CrudHandlerRegistry`, ~línea 99):

```php
    $container->singleton(CrudScopeResolver::class, fn(Container $c) => new CrudScopeResolver(
        $c->get(CrudHandlerRegistry::class)
    ));
```

Y añadir el argumento al binding de `CrudDataService` (tras `$c->get(CrudHandlerRegistry::class)` en su `new CrudDataService(...)`, ~línea 120):

```php
    $container->singleton(CrudDataService::class, fn(Container $c) => new CrudDataService(
        $c->get(GenericCrudRepository::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudHookRunner::class),
        $c->get(CrudFieldValidationService::class),
        $c->get(CrudDbConstraintValidator::class),
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudScopeResolver::class)
    ));
```

- [ ] **Step 4: Verificar que el lint/autoload no rompió nada**

Run: `php -l app/Application/Services/CrudDataService.php`
Expected: `No syntax errors detected`.

Run: `php tests/run.php`
Expected: toda la suite en verde (las firmas con default `= null` no rompen tests existentes que llaman `list($def, $query)`).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CrudDataService.php config/container.php
git commit -m "feat(crud): cablear row-level scope en CrudDataService::list()"
```

---

### Task 8: Bloqueo server-side + propagación de userId

**Files:**
- Modify: `app/Application/Services/CrudResourceService.php`
- Modify: `app/Presentation/Controllers/Admin/CrudController.php`
- Modify: `config/container.php`

**Diseño:** El bloqueo reutiliza `CrudScopeResolver::ownerMeta()`. Si el recurso declara scope owner, el usuario no tiene bypass, y `registro[column] !== userId` → se trata como "no encontrado" (misma `ValidationException` que ya usa "el registro no existe"). En show/edit eso ya produce **404** (el controller mapea `ValidationException` → `Response::notFound()`); en update/delete deniega la escritura por el mismo camino existente. `buildIndexData`, `buildShowData` y `buildEditData` pasan a recibir `?int $userId`.

- [ ] **Step 1: Inyectar `CrudScopeResolver` en `CrudResourceService`**

En `app/Application/Services/CrudResourceService.php`, añadir el parámetro al constructor (al final):

```php
        private readonly CrudDetailBuilder $detailBuilder,
        private readonly CrudScopeResolver $scopeResolver
    ) {}
```

Añadir el import:

```php
use App\Application\Services\CrudScopeResolver;
```

> `CrudScopeResolver` está en el mismo namespace `App\Application\Services`, así que el `use` es opcional; si PHP marca import redundante, omítelo.

Y en `config/container.php`, añadir el argumento al binding de `CrudResourceService` (tras `$c->get(CrudDetailBuilder::class)`):

```php
    $container->singleton(CrudResourceService::class, fn(Container $c) => new CrudResourceService(
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudFormBuilder::class),
        $c->get(CrudTableBuilder::class),
        $c->get(RbacService::class),
        $c->get(CrudActionResolver::class),
        $c->get(CrudActionService::class),
        $c->get(CrudDetailBuilder::class),
        $c->get(CrudScopeResolver::class)
    ));
```

- [ ] **Step 2: Añadir el helper de bloqueo y cablearlo**

En `CrudResourceService`, añadir un método privado:

```php
    private function assertOwnership(\App\Domain\Entities\CrudResourceDefinition $definition, array $row, ?int $userId): void
    {
        $meta = $this->scopeResolver->ownerMeta($definition);
        if ($meta === null) {
            return;
        }
        if ($meta['bypass'] !== null && $this->rbacService->puede($meta['bypass'])) {
            return;
        }
        $owner = $row[$meta['column']] ?? null;
        if ($userId === null || (string) $owner !== (string) $userId) {
            // Tratar como inexistente para no revelar registros ajenos (404 en show/edit).
            throw new ValidationException('El registro solicitado no existe.');
        }
    }
```

- [ ] **Step 3: Cambiar firmas y aplicar el bloqueo**

3a. `buildIndexData` — recibir userId y pasarlo a `list()`:

```php
    public function buildIndexData(string $resource, array $query, ?int $userId = null): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('ver'));

        $can = fn(string $slug): bool => $this->rbacService->puede($slug);
        $result = $this->dataService->list($definition, $query, $userId, $can);
```

(El resto del método queda igual; nota que `$can` se define aquí y se reutiliza más abajo donde ya existía `$can = fn(...)` — **elimina la redefinición duplicada** de `$can` que había en el cuerpo para no declararla dos veces.)

3b. `buildShowData` — recibir userId y bloquear tras cargar la fila:

```php
    public function buildShowData(string $resource, int $id, ?int $userId = null): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('ver'));

        $row = $this->dataService->find($definition, $id);
        if ($row === null || (int) ($row['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }
        $this->assertOwnership($definition, $row, $userId);
```

3c. `buildEditData` — recibir userId y bloquear:

```php
    public function buildEditData(string $resource, int $id, ?int $userId = null): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('editar'));

        $row = $this->dataService->find($definition, $id);
        if ($row === null || (int) ($row['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }
        $this->assertOwnership($definition, $row, $userId);
```

3d. `update` — bloquear con el `$existing` ya cargado (justo antes de `$this->dataService->update(...)`):

```php
        if ($existing === null || (int) ($existing['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        $this->assertOwnership($definition, $existing, $userId);

        $this->dataService->update($definition, $id, $input, $files, $userId, $ip);
```

3e. `delete` — igual, antes de `$this->dataService->delete(...)`:

```php
        if ($existing === null || (int) ($existing['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        $this->assertOwnership($definition, $existing, $userId);

        $this->dataService->delete($definition, $id, $userId, $ip);
```

- [ ] **Step 4: Propagar userId desde el controller**

En `app/Presentation/Controllers/Admin/CrudController.php`:

4a. `index()` — pasar userId:

```php
            $resource = (string) $request->param('resource');
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $data = $this->crudResourceService->buildIndexData($resource, $request->all(), $userId > 0 ? $userId : null);
```

4b. `show()`:

```php
            $resource = (string) $request->param('resource');
            $id = (int) $request->param('id');
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $data = $this->crudResourceService->buildShowData($resource, $id, $userId > 0 ? $userId : null);
```

4c. `edit()`:

```php
            $resource = (string) $request->param('resource');
            $id = (int) $request->param('id');
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $data = $this->crudResourceService->buildEditData($resource, $id, $userId > 0 ? $userId : null);
```

(`update()` y `delete()` ya computan `$userId` y lo pasan; no cambian.)

- [ ] **Step 5: Lint + suite completa**

Run: `php -l app/Application/Services/CrudResourceService.php`
Run: `php -l app/Presentation/Controllers/Admin/CrudController.php`
Expected: `No syntax errors detected` en ambos.

Run: `php tests/run.php`
Expected: toda la suite en verde.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudResourceService.php app/Presentation/Controllers/Admin/CrudController.php config/container.php
git commit -m "feat(crud): bloqueo server-side de propiedad (show/edit/update/delete)"
```

---

## Ítem #2 — Dashboard por perfil (corto plazo)

### Task 9: `DefaultPlatformDashboardProvider` filtra por permiso

**Files:**
- Modify: `app/Infrastructure/Dashboard/DefaultPlatformDashboardProvider.php`
- Test: `tests/Dashboard/DefaultPlatformDashboardProviderTest.php`

**Slugs reales:** Usuarios → `usuarios.gestionar`; Roles → `roles.gestionar`; Ajustes → `administracion.ver`. El KPI "Extensión" no tiene permiso (es informativo) → siempre visible.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Dashboard/DefaultPlatformDashboardProviderTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Dashboard\DashboardBuildContext;
use App\Infrastructure\Dashboard\DefaultPlatformDashboardProvider;

function dash_labels(array $items): array
{
    return array_values(array_map(static fn(array $i): string => (string) ($i['label'] ?? ''), $items));
}

test('Dashboard: con permiso de usuarios el KPI y quick "Usuarios" aparecen', function (): void {
    $ctx = new DashboardBuildContext(1, ['usuarios.gestionar'], []);
    $c = (new DefaultPlatformDashboardProvider())->contribute($ctx);

    assert_true(in_array('Usuarios', dash_labels($c->kpis), true), 'KPI Usuarios presente');
    assert_true(in_array('Usuarios', dash_labels($c->quickAccess), true), 'Quick Usuarios presente');
    assert_true(!in_array('Roles', dash_labels($c->kpis), true), 'KPI Roles ausente sin permiso');
});

test('Dashboard: sin ningún permiso la contribución es válida y no rompe', function (): void {
    $ctx = new DashboardBuildContext(1, [], []);
    $c = (new DefaultPlatformDashboardProvider())->contribute($ctx);

    assert_true(!in_array('Usuarios', dash_labels($c->kpis), true), 'sin permiso no hay KPI Usuarios');
    assert_true(!in_array('Roles', dash_labels($c->kpis), true), 'sin permiso no hay KPI Roles');
    assert_true(!in_array('Ajustes', dash_labels($c->kpis), true), 'sin permiso no hay KPI Ajustes');
    // "Extensión" (informativo, sin permiso) sigue presente => contribución válida
    assert_true(in_array('Extensión', dash_labels($c->kpis), true), 'KPI informativo presente');
    assert_same([], $c->quickAccess, 'sin permisos no hay accesos rápidos');
});

test('Dashboard: con todos los permisos se ve lo mismo que hoy (sin regresión)', function (): void {
    $ctx = new DashboardBuildContext(1, ['usuarios.gestionar', 'roles.gestionar', 'administracion.ver'], []);
    $c = (new DefaultPlatformDashboardProvider())->contribute($ctx);

    assert_same(['Usuarios', 'Roles', 'Ajustes', 'Extensión'], dash_labels($c->kpis));
    assert_same(['Usuarios', 'Roles', 'Ajustes'], dash_labels($c->quickAccess));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php tests/run.php DefaultPlatformDashboardProvider`
Expected: FAIL — hoy los KPIs/quick se devuelven sin filtrar (los tests de "sin permiso" fallan).

- [ ] **Step 3: Implementar el filtrado**

Reemplazar el cuerpo de `contribute()` en `app/Infrastructure/Dashboard/DefaultPlatformDashboardProvider.php` (desde `$kpis = [` hasta el cierre antes de `return new DashboardContribution(`) por construcción condicional:

```php
        $kpis = [];
        if ($context->tienePermiso('usuarios.gestionar')) {
            $kpis[] = [
                'label'       => 'Usuarios',
                'value'       => '—',
                'icon'        => 'bi-people-fill',
                'color'       => 'primary',
                'url'         => '/admin/administracion/usuarios',
                'description' => 'Gestión de cuentas',
            ];
        }
        if ($context->tienePermiso('roles.gestionar')) {
            $kpis[] = [
                'label'       => 'Roles',
                'value'       => '—',
                'icon'        => 'bi-shield-lock',
                'color'       => 'secondary',
                'url'         => '/admin/administracion/roles',
                'description' => 'RBAC',
            ];
        }
        if ($context->tienePermiso('administracion.ver')) {
            $kpis[] = [
                'label'       => 'Ajustes',
                'value'       => '—',
                'icon'        => 'bi-sliders',
                'color'       => 'info',
                'url'         => '/admin/ajustes',
                'description' => 'Layout y tema',
            ];
        }
        // KPI informativo (sin permiso): siempre presente para una contribución válida.
        $kpis[] = [
            'label'       => 'Extensión',
            'value'       => '',
            'icon'        => 'bi-journal-text',
            'color'       => 'success',
            'url'         => '#',
            'description' => 'Registrar proveedores en config/dashboard.php',
        ];

        $quick = [];
        if ($context->tienePermiso('usuarios.gestionar')) {
            $quick[] = ['url' => '/admin/administracion/usuarios', 'icon' => 'bi-people', 'label' => 'Usuarios'];
        }
        if ($context->tienePermiso('roles.gestionar')) {
            $quick[] = ['url' => '/admin/administracion/roles', 'icon' => 'bi-key', 'label' => 'Roles'];
        }
        if ($context->tienePermiso('administracion.ver')) {
            $quick[] = ['url' => '/admin/ajustes', 'icon' => 'bi-gear', 'label' => 'Ajustes'];
        }
```

(El bloque `$activity` y el `return new DashboardContribution(...)` quedan igual.)

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php tests/run.php DefaultPlatformDashboardProvider`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Dashboard/DefaultPlatformDashboardProvider.php tests/Dashboard/DefaultPlatformDashboardProviderTest.php
git commit -m "feat(dashboard): filtrar KPIs y accesos rápidos por permiso"
```

---

## Documentación y cierre

### Task 10: Documentar `list.scope` y el pie plano

**Files:**
- Modify: `docs/modules/crud/modulo-crud-engine.md`

- [ ] **Step 1: Añadir sección de scope y de pie de totales**

Agregar al doc una subsección "Aislamiento por usuario (`list.scope`)" con los dos modos y la regla de bypass:

````markdown
### Aislamiento por usuario — `list.scope`

Restringe el listado (y bloquea show/edit/update/delete) a las filas del usuario actual.

**Built-in (sin clase PHP):**

```json
"list": {
  "scope": {
    "type": "owner",
    "column": "created_by",
    "bypass_permission": "{prefix}.ver_todos"
  }
}
```

- `type` debe ser `"owner"` (único built-in).
- `column` es la columna de autor (normalmente `created_by`, que el motor ya rellena al crear).
- `bypass_permission` (opcional): quien tenga ese permiso ve/edita todo. `{prefix}` se expande al `permission_prefix` del recurso.
- Acceso por URL directa a un registro ajeno → **404** (no se revela su existencia).
- `userId` nulo en panel autenticado → política de no-fuga: el listado queda vacío y el bloqueo deniega.

**Custom (escape hatch):**

```json
"list": { "scope_handler": "clientes_owner" }
```

donde `clientes_owner` se registra en `config/crud_handlers.php` apuntando a una clase que implementa `CrudListScopeInterface`.

`scope` y `scope_handler` son **mutuamente excluyentes**. Un recurso sin ninguno se comporta como hoy (sin scope).

### Pie de totales en listado plano

Si el recurso declara `list.summaries` y **no** usa `group_by`, el listado muestra un `<tfoot>` con los totales alineados bajo cada columna. Si la agregación se omite por volumen (`aggregationSkipped`), el pie no se muestra.
````

- [ ] **Step 2: Commit**

```bash
git add docs/modules/crud/modulo-crud-engine.md
git commit -m "docs(crud): documentar list.scope y pie de totales plano"
```

---

### Task 11: Verificación final + smoke manual

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite completa en verde**

Run: `php tests/run.php`
Expected: `N passed, 0 failed` (incluye los nuevos tests de Tasks 1, 3, 4, 5, 6, 9).

- [ ] **Step 2: Lint de todos los archivos tocados**

Run (uno por archivo modificado/creado):
```
php -l app/Application/Services/CrudTableBuilder.php
php -l app/Application/Services/CrudScopeResolver.php
php -l app/Application/Crud/Scopes/OwnerListScope.php
php -l app/Application/Services/CrudDataService.php
php -l app/Application/Services/CrudResourceService.php
php -l app/Application/Services/CrudConfigValidator.php
php -l app/Domain/Entities/CrudResourceDefinition.php
php -l app/Presentation/Controllers/Admin/CrudController.php
php -l app/Infrastructure/Dashboard/DefaultPlatformDashboardProvider.php
```
Expected: `No syntax errors detected` en todos.

- [ ] **Step 3: Smoke manual del pie plano (#4)**

Levantar `php -S localhost:8000 -t public`, login como admin, ir a `/admin/crud/demo_productos`. Verificar que aparece el `<tfoot>` "Totales" con las sumas alineadas bajo Precio y Stock. Aplicar un filtro de estado y confirmar que el pie sigue cuadrando (o desaparece si `aggregationSkipped`).

- [ ] **Step 4: Smoke manual del dashboard (#2)**

Como admin (todos los permisos) el dashboard se ve igual que antes (Usuarios/Roles/Ajustes/Extensión). Opcional: crear un rol sin `usuarios.gestionar`/`roles.gestionar`/`administracion.ver`, loguear con ese usuario y confirmar que solo queda el KPI "Extensión" y sin accesos rápidos, sin errores.

- [ ] **Step 5: (Opcional) Smoke del scope (#3)**

El scope no está activo en ningún `demo_*` (intencional, sin regresión). Para validarlo end-to-end, declarar temporalmente `"scope": {"type":"owner","column":"created_by"}` en `config/cruds/demo_productos.json`, crear registros con dos usuarios distintos, confirmar que cada uno solo ve los suyos y que abrir por URL `/admin/crud/demo_productos/{id_ajeno}` devuelve 404. **Revertir el cambio del JSON al terminar** (los demos deben quedar intactos).

- [ ] **Step 6: Confirmar árbol limpio**

Run: `git status`
Expected: sin cambios sin commitear (salvo el JSON de smoke ya revertido).

---

## Self-Review (cobertura del spec)

- **#4 Pie plano:** Task 1 (builder + test), Task 2 (vista). Casos: valores en columna correcta, resto vacío, sin summaries → vacío, agrupado intacto, `aggregationSkipped` → `summaryRow` llega `[]` ⇒ sin pie. ✔
- **#3 Scope:** Task 3 (accesores), Task 4 (`OwnerListScope` incl. bypass y no-fuga null), Task 5 (resolver built-in/handler/null + `{prefix}` + `conditionsToSql`), Task 6 (validación type/column/exclusión), Task 7 (cableado en `list()` antes del conteo), Task 8 (bloqueo show/edit/update/delete → 404 vía `ValidationException` + propagación de userId). ✔
- **#2 Dashboard:** Task 9 con slugs reales (`usuarios.gestionar`, `roles.gestionar`, `administracion.ver`), contribución válida sin permisos, sin regresión con todos. ✔
- **No regresión:** demos sin `scope` intactos; `list()` con defaults `= null` no rompe llamadas existentes; `formatRow` preservado; dashboard de admin idéntico. ✔
- **Consistencia de tipos:** `OwnerListScope(string $column, bool $hasBypass, ?int $userId)`, `CrudScopeResolver::resolve(def, ?int, callable): ?CrudListScopeInterface`, `ownerMeta(def): ?array{column,bypass}`, `conditionsToSql(array): array{0:list,1:list}` — usados igual en Tasks 5, 7, 8. ✔

**Decisión registrada:** el spec pide 404 para show/edit/update/delete ante registros ajenos. Show/edit ya devuelven 404 vía `ValidationException`→`Response::notFound()`. Update/delete deniegan la escritura por el mismo camino "no existe" (sin mutación, sin fuga); no se introduce un tipo de excepción nuevo para no alterar el flujo existente de esos POST.
