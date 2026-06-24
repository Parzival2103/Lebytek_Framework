# Reportes — Iteración 3 (catálogo demo + documentos de registro) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ampliar el módulo `reportes` para que sirva de biblioteca de referencia: 3 fuentes reportables nuevas (pedidos, productos, clientes), 5 reportes demo de colección adicionales, y el **modo registro** (documentos PDF `ticket_compra`, `presupuesto`, `contrato`) disparados desde el CRUD Engine.

**Architecture:** El módulo ya funciona para colección sobre una sola fuente (`citas`). Esta iteración (a) añade fuentes JSON declarativas que se validan contra los recursos CRUD existentes, (b) implementa la lectura de un solo registro con scope reutilizando `CrudDataService`/`CrudScopeResolver` (sin reimplementar permisos ni SQL bespoke), y (c) añade 3 plantillas PDF de registro al whitelist `config/pdf_templates.php`. El disparo se hace con acciones de fila `type:"link"` ya soportadas por el CRUD Engine — **cero código nuevo en el engine**.

**Tech Stack:** PHP 8.1, arquitectura Onion de 5 capas, contenedor DI propio (`config/container.php`), router propio (`routes/web.php`), pdf-kit (dompdf) con whitelist clave→clase, arnés de pruebas plano (`php tests/run.php`, helpers `test()`/`assert_same()`/`assert_true()`/`assert_throws()`).

---

## Antecedentes verificados (estado actual del código)

Ya leído y confirmado durante la planificación:

- **Fuente actual:** solo `config/reportes/citas.json` (modo `coleccion`), recurso `demo_citas`.
- **Plantillas PDF:** whitelist en `config/pdf_templates.php` → `demo_reporte`, `tabla_estadistica`. Sin plantillas de registro.
- **Read path de colección:** `BuildReporteDataUseCase::build()` siempre llama `dataSource->rows(definition, periodField, from, to, ...)`. `CrudReporteDataSource::rows()` delega en `CrudDataService::eventsInRange()`, que aplica `BETWEEN ? AND ?` sobre `dateColumn`. **`GenericCrudRepository::quoteIdentifier('')` lanza excepción** ⇒ *toda fuente de colección necesita un `period.field` válido presente en `columnNames()` del recurso.*
- **columnNames() por recurso** = `primary_key` + nombres de `list.columns` + nombres de `form.fields` (ver `CrudResourceDefinition::columnNames()`).
  - `demo_pedidos`: `id, folio, total, status, created_at, cliente_id, notas` (tiene `created_at` en list).
  - `demo_clientes`: `id, nombre, email, telefono, status, created_at` (tiene `created_at` en list; **owner-scope** `{type:owner, column:created_by}`).
  - `demo_productos`: `id, codigo, nombre, precio_venta, stock_actual, status, categoria_id` (**NO** tiene columna de fecha en list/form ⇒ ver Task 8: se añade `created_at` a `list.columns` para habilitar `period.field`).
- **Columnas físicas de tablas demo** (de la migración showcase):
  - `dom_demo_pedidos`: `folio, cliente_id, total DECIMAL, status, notas, created_at`.
  - `dom_demo_pedido_items`: `pedido_id, producto_id, descripcion, cantidad INT, precio_unitario DECIMAL, subtotal DECIMAL`.
  - `dom_demo_productos`: `codigo, nombre, categoria_id, precio_venta DECIMAL, stock_actual INT, status`.
  - `dom_demo_clientes`: `nombre, email, telefono, status, created_by, created_at`.
- **Relaciones (`CrudResourceDefinition::relation($name)`):** `demo_pedidos` tiene `cliente` (belongsTo `dom_demo_clientes`, fk `cliente_id`, label `nombre`) e `items` (hasMany `dom_demo_pedido_items`, fk `pedido_id`, columnas descripcion/cantidad/precio_unitario/subtotal). `CrudRelationService::optionsFor()` (belongsTo) y `childrenFor()` (hasMany) leen datos sin SQL nuevo.
- **Scope de un solo registro:** hoy `CrudDataService::find()` (→ `repository->findById()`) **NO** aplica scope. Hay que añadir una lectura de un solo registro que reutilice el mecanismo de scope existente (`applyScopeConditions()` privado + `CrudScopeResolver`).
- **Ruta/controlador:** rutas en `routes/web.php` dentro del grupo admin (líneas ~126–132). Controlador `ReportesController` (constructor con 6 deps). `documento()` no existe.
- **Request:** `Request::query($key, $default)` para query string; `Request::param($key)` para parámetros de ruta.
- **Arnés de pruebas:** `php tests/run.php [filtro]`. Tests en `tests/**/*Test.php`, sin BD (inyectan fakes). Helpers globales: `test()`, `assert_same($exp,$act)`, `assert_true($cond,$msg)`, `assert_null()`, `assert_throws($class,$fn)`.

### Decisión de diseño registrada (desviaciones conscientes del spec)

1. **Agrupar productos por `categoria_id`, no por nombre de categoría.** El read path (`eventsInRange`) devuelve filas planas sin joins; resolver el nombre de la categoría requeriría SQL/join nuevo, prohibido por el principio "reportes nunca arma SQL". El reporte `demo_productos_inventario_categoria` agrupa por la columna FK `categoria_id`. La resolución a nombre amigable queda fuera de alcance (YAGNI).
2. **`demo_productos` gana una columna `created_at` en `list.columns`** (Task 8) para tener un `period.field` válido, igual que el resto de demos. Es el cambio mínimo (1 columna) frente a modificar el core read path para soportar fuentes sin periodo.
3. **Nueva interfaz `ReporteRecordSourceInterface`** (en vez de ampliar `ReporteDataSourceInterface`) para el modo registro: así el `FakeReporteDataSource` de los tests de colección existentes sigue satisfaciendo su interfaz sin cambios. `CrudReporteDataSource` implementa ambas.

---

## Estructura de archivos

**Crear:**
- `config/reportes/pedidos.json` — fuente reportable (coleccion + registro).
- `config/reportes/productos.json` — fuente reportable (coleccion).
- `config/reportes/clientes.json` — fuente reportable (coleccion + registro).
- `app/Domain/Reporte/ReporteRecordSourceInterface.php` — frontera de lectura de un registro con relaciones.
- `app/Application/Pdf/Templates/TicketCompraTemplate.php` — plantilla registro (pedidos).
- `app/Application/Pdf/Templates/PresupuestoTemplate.php` — plantilla registro (pedidos).
- `app/Application/Pdf/Templates/ContratoTemplate.php` — plantilla registro (clientes).
- `app/Application/Reporte/GenerarDocumentoUseCase.php` — `buildPayload()` (puro/testeable) + `generar()` (render).
- `tests/Reporte/ReporteFuenteRelationsTest.php`
- `tests/Reporte/ReporteConfigCatalogoTest.php`
- `tests/Reporte/GenerarDocumentoUseCaseTest.php`
- `tests/Pdf/DocumentoTemplatesTest.php`

**Modificar:**
- `app/Domain/Reporte/ReporteFuente.php` — parsear/exponer `expose.relations`.
- `app/Application/Reporte/ReporteConfigValidator.php` — validar `expose.relations` (param opcional) y `templates.registro`.
- `app/Application/Reporte/ReporteConfigLoader.php` — pasar nombres de relación del recurso al validador.
- `app/Application/Reporte/CrudReporteDataSource.php` — implementar `findRecord()` (nueva interfaz).
- `app/Application/Services/CrudDataService.php` — `findInScope()` (lectura de 1 registro con scope).
- `app/Infrastructure/Repositories/GenericCrudRepository.php` — `findByIdScoped()`.
- `config/pdf_templates.php` — +3 claves (`ticket_compra`, `presupuesto`, `contrato`).
- `app/Presentation/Controllers/Admin/ReportesController.php` — `documento()` + dep `GenerarDocumentoUseCase`.
- `routes/web.php` — `GET /admin/reportes/documento`.
- `config/container.php` — binding de `ReporteRecordSourceInterface`, `GenerarDocumentoUseCase`, ampliar `CrudReporteDataSource`, ampliar constructor del controlador.
- `config/cruds/demo_pedidos.json` — acciones `link` "Ticket PDF" y "Presupuesto PDF".
- `config/cruds/demo_clientes.json` — bloque `actions.row` con acción `link` "Contrato PDF".
- `config/cruds/demo_productos.json` — añadir `created_at` a `list.columns`.
- `database/schema/modules/reportes.sql` — +5 `INSERT IGNORE` en `rep_reportes`.

---

## Task 1: `ReporteFuente` expone relaciones declaradas

**Files:**
- Modify: `app/Domain/Reporte/ReporteFuente.php`
- Test: `tests/Reporte/ReporteFuenteRelationsTest.php` (create)

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Reporte/ReporteFuenteRelationsTest.php`:

```php
<?php
declare(strict_types=1);

use App\Domain\Reporte\ReporteFuente;

test('ReporteFuente expone relationNames desde expose.relations', function (): void {
    $fuente = ReporteFuente::fromArray('pedidos', [
        'fuente'  => ['key' => 'pedidos', 'title' => 'Pedidos', 'resource' => 'demo_pedidos'],
        'modos'   => ['coleccion', 'registro'],
        'expose'  => [
            'columns'   => [['name' => 'folio', 'label' => 'Folio', 'type' => 'text']],
            'relations' => ['cliente', 'items'],
            'max_rows'  => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => ['ticket_compra']],
    ]);

    assert_same(['cliente', 'items'], $fuente->relationNames());
});

test('ReporteFuente sin relaciones devuelve lista vacía', function (): void {
    $fuente = ReporteFuente::fromArray('clientes', [
        'fuente'  => ['key' => 'clientes', 'title' => 'Clientes', 'resource' => 'demo_clientes'],
        'expose'  => ['columns' => [['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text']], 'max_rows' => 5000],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => ['contrato']],
    ]);

    assert_same([], $fuente->relationNames());
});
```

- [ ] **Step 2: Ejecutar el test y verificar que falla**

Run: `php tests/run.php ReporteFuenteRelations`
Expected: FAIL con `Call to undefined method App\Domain\Reporte\ReporteFuente::relationNames()`

- [ ] **Step 3: Implementar `relationNames()` en `ReporteFuente`**

En `app/Domain/Reporte/ReporteFuente.php`, añadir un campo `relations` al constructor y al `fromArray`, y el accesor. Cambios concretos:

En el constructor (lista de `private readonly`), añadir tras `private readonly int $maxRows,`:

```php
        private readonly array $relations,
```

En `fromArray`, justo antes del `$period = ...`, añadir:

```php
        $relations = array_values(array_map('strval', is_array($expose['relations'] ?? null) ? $expose['relations'] : []));
```

En la llamada `return new self(...)`, añadir `$relations` como argumento inmediatamente después de `(int) ($expose['max_rows'] ?? 5000),` y antes del array de `templates`:

```php
            (int) ($expose['max_rows'] ?? 5000),
            $relations,
            [
                'coleccion' => array_values(array_map('strval', is_array($templates['coleccion'] ?? null) ? $templates['coleccion'] : [])),
                'registro'  => array_values(array_map('strval', is_array($templates['registro'] ?? null) ? $templates['registro'] : [])),
            ],
```

Añadir el accesor junto a `maxRows()`:

```php
    /** @return list<string> nombres de relaciones CRUD a cargar en modo registro */
    public function relationNames(): array { return $this->relations; }
```

- [ ] **Step 4: Ejecutar el test y verificar que pasa**

Run: `php tests/run.php ReporteFuenteRelations`
Expected: PASS (2 passed)

- [ ] **Step 5: Verificar que no hay regresión en la suite**

Run: `php tests/run.php Reporte`
Expected: todos los tests de `tests/Reporte/` en verde.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Reporte/ReporteFuente.php tests/Reporte/ReporteFuenteRelationsTest.php
git commit -m "feat(reportes): ReporteFuente expone relaciones declaradas para modo registro"
```

---

## Task 2: El validador acepta `expose.relations` y valida contra el recurso

**Files:**
- Modify: `app/Application/Reporte/ReporteConfigValidator.php`
- Modify: `app/Application/Reporte/ReporteConfigLoader.php`
- Test: `tests/Reporte/ReporteConfigValidatorTest.php` (añadir casos al archivo existente)

- [ ] **Step 1: Añadir tests que fallan**

Al final de `tests/Reporte/ReporteConfigValidatorTest.php`, añadir:

```php
test('acepta expose.relations que existen en el recurso', function (): void {
    $config = [
        'fuente'  => ['key' => 'pedidos', 'resource' => 'demo_pedidos'],
        'expose'  => [
            'columns'   => [['name' => 'folio', 'type' => 'text']],
            'relations' => ['cliente', 'items'],
            'max_rows'  => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => ['ticket_compra']],
    ];
    // No lanza: 'cliente' e 'items' están en la lista de relaciones disponibles.
    (new \App\Application\Reporte\ReporteConfigValidator())
        ->validate($config, ['id', 'folio'], ['cliente', 'items']);
    assert_true(true);
});

test('rechaza expose.relations inexistentes en el recurso', function (): void {
    $config = [
        'fuente'  => ['key' => 'pedidos', 'resource' => 'demo_pedidos'],
        'expose'  => [
            'columns'   => [['name' => 'folio', 'type' => 'text']],
            'relations' => ['fantasma'],
            'max_rows'  => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => []],
    ];
    assert_throws(\App\Domain\Exceptions\ValidationException::class, fn() =>
        (new \App\Application\Reporte\ReporteConfigValidator())
            ->validate($config, ['id', 'folio'], ['cliente', 'items']));
});
```

Asegúrate de que el archivo tiene el `use` o usa FQCN como arriba.

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php tests/run.php ReporteConfigValidator`
Expected: FAIL — el método `validate()` actual sólo acepta 2 argumentos (`$config`, `$availableColumns`); la llamada con 3 argumentos provoca que `relations` se ignore y el caso "rechaza" no lance.

- [ ] **Step 3: Implementar la validación de relaciones**

En `app/Application/Reporte/ReporteConfigValidator.php`, cambiar la firma de `validate()` para aceptar un tercer parámetro opcional y validar `expose.relations`. Reemplazar la línea de la firma:

```php
    public function validate(array $config, array $availableColumns): void
```

por:

```php
    /**
     * @param array<string,mixed> $config
     * @param list<string> $availableColumns columnas conocidas del recurso CRUD
     * @param list<string> $availableRelations relaciones declaradas del recurso CRUD
     */
    public function validate(array $config, array $availableColumns, array $availableRelations = []): void
```

Justo antes del bloque final `if ($errors !== []) {`, añadir la validación de relaciones:

```php
        $knownRelations = array_fill_keys($availableRelations, true);
        foreach (is_array($expose['relations'] ?? null) ? $expose['relations'] : [] as $i => $rel) {
            $rel = (string) $rel;
            if ($rel === '' || !isset($knownRelations[$rel])) {
                $errors[] = "expose.relations[{$i}] ('{$rel}') no es una relación declarada del recurso.";
            }
        }
```

- [ ] **Step 4: Conectar el loader para pasar las relaciones del recurso**

En `app/Application/Reporte/ReporteConfigLoader.php`, dentro de `load()`, localizar:

```php
        $resource = (string) ($config['fuente']['resource'] ?? '');
        $columns = $this->crudDefinition($resource)->columnNames();

        $this->validator->validate($config, $columns);
```

y reemplazarlo por:

```php
        $resource = (string) ($config['fuente']['resource'] ?? '');
        $definition = $this->crudDefinition($resource);
        $columns = $definition->columnNames();
        $relations = array_keys($definition->relations());

        $this->validator->validate($config, $columns, $relations);
```

- [ ] **Step 5: Ejecutar y verificar que pasa**

Run: `php tests/run.php ReporteConfigValidator`
Expected: PASS (incluye los 2 nuevos casos)

Run: `php tests/run.php ReporteConfigLoader`
Expected: PASS (sin regresión; `citas` no declara relaciones y sigue válido)

- [ ] **Step 6: Commit**

```bash
git add app/Application/Reporte/ReporteConfigValidator.php app/Application/Reporte/ReporteConfigLoader.php tests/Reporte/ReporteConfigValidatorTest.php
git commit -m "feat(reportes): validar expose.relations contra relaciones del recurso CRUD"
```

---

## Task 3: Las 3 fuentes reportables nuevas (config JSON)

**Files:**
- Create: `config/reportes/pedidos.json`
- Create: `config/reportes/productos.json`
- Create: `config/reportes/clientes.json`
- Modify: `config/cruds/demo_productos.json` (sólo añadir `created_at` a `list.columns`; ver Step 2)
- Test: `tests/Reporte/ReporteConfigCatalogoTest.php` (create)

> Nota: `productos.json` depende del cambio en `demo_productos.json` (period.field=`created_at`). Se hace en este task para que la fuente cargue.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Reporte/ReporteConfigCatalogoTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Reporte\ReporteConfigValidator;

function cat_loader(): ReporteConfigLoader
{
    return new ReporteConfigLoader(new ReporteConfigValidator());
}

test('la fuente pedidos carga y declara modo registro', function (): void {
    $f = cat_loader()->load('pedidos');
    assert_same('demo_pedidos', $f->resource());
    assert_true(in_array('ticket_compra', $f->templatesFor('registro'), true));
    assert_true(in_array('presupuesto', $f->templatesFor('registro'), true));
    assert_same(['cliente', 'items'], $f->relationNames());
    assert_true($f->hasPeriod(), 'pedidos debe tener period.field');
});

test('la fuente productos carga y agrupa por categoria_id y status', function (): void {
    $f = cat_loader()->load('productos');
    assert_same('demo_productos', $f->resource());
    assert_true(in_array('categoria_id', $f->groupBy(), true));
    assert_true(in_array('status', $f->groupBy(), true));
    assert_true($f->allowsTreatment('precio_venta', 'sum'), 'precio_venta debe permitir sum');
    assert_true($f->allowsTreatment('stock_actual', 'sum'), 'stock_actual debe permitir sum');
});

test('la fuente clientes carga y declara contrato en registro', function (): void {
    $f = cat_loader()->load('clientes');
    assert_same('demo_clientes', $f->resource());
    assert_true(in_array('contrato', $f->templatesFor('registro'), true));
    assert_same([], $f->relationNames());
});

test('listFuentes incluye las cuatro fuentes', function (): void {
    $keys = array_keys(cat_loader()->listFuentes());
    sort($keys);
    assert_same(['citas', 'clientes', 'pedidos', 'productos'], $keys);
});
```

- [ ] **Step 2: Añadir `created_at` a `demo_productos` para habilitar el periodo**

En `config/cruds/demo_productos.json`, dentro de `list.columns`, añadir tras la columna `status` (último objeto del array, respeta la coma):

```json
      {
        "name": "created_at",
        "label": "Creado",
        "format": "datetime",
        "sortable": true
      }
```

(El array `list.columns` debe quedar con `created_at` como último elemento; añade la coma al objeto `status` anterior.)

- [ ] **Step 3: Crear `config/reportes/pedidos.json`**

```json
{
  "fuente": { "key": "pedidos", "title": "Pedidos", "resource": "demo_pedidos", "icon": "bi-receipt" },
  "modos": ["coleccion", "registro"],
  "expose": {
    "columns": [
      { "name": "folio",      "label": "Folio",   "type": "text" },
      { "name": "cliente_id", "label": "Cliente", "type": "number" },
      { "name": "total",      "label": "Total",   "type": "money",  "treatments": ["sum", "avg", "min", "max"] },
      { "name": "status",     "label": "Estado",  "type": "text",   "treatments": ["count"] }
    ],
    "relations": ["cliente", "items"],
    "group_by": ["status", "cliente_id"],
    "order_by": ["status"],
    "filters": [ { "field": "status", "label": "Estado" } ],
    "period": { "field": "created_at", "label": "Creado", "presets": ["hoy", "semana", "mes", "anio", "ayer", "mes_pasado", "anio_pasado", "todo"] },
    "max_rows": 5000
  },
  "templates": { "coleccion": ["tabla_estadistica"], "registro": ["ticket_compra", "presupuesto"] }
}
```

- [ ] **Step 4: Crear `config/reportes/productos.json`**

```json
{
  "fuente": { "key": "productos", "title": "Productos", "resource": "demo_productos", "icon": "bi-box-seam" },
  "modos": ["coleccion"],
  "expose": {
    "columns": [
      { "name": "codigo",       "label": "Código",    "type": "text" },
      { "name": "nombre",       "label": "Nombre",    "type": "text" },
      { "name": "categoria_id", "label": "Categoría", "type": "number" },
      { "name": "precio_venta", "label": "Precio",    "type": "money",  "treatments": ["sum", "avg", "min", "max"] },
      { "name": "stock_actual", "label": "Stock",     "type": "number", "treatments": ["sum", "avg", "min", "max"] },
      { "name": "status",       "label": "Estado",    "type": "text",   "treatments": ["count"] }
    ],
    "group_by": ["categoria_id", "status"],
    "order_by": ["status"],
    "filters": [ { "field": "status", "label": "Estado" } ],
    "period": { "field": "created_at", "label": "Creado", "presets": ["hoy", "semana", "mes", "anio", "ayer", "mes_pasado", "anio_pasado", "todo"] },
    "max_rows": 5000
  },
  "templates": { "coleccion": ["tabla_estadistica"], "registro": [] }
}
```

- [ ] **Step 5: Crear `config/reportes/clientes.json`**

```json
{
  "fuente": { "key": "clientes", "title": "Clientes", "resource": "demo_clientes", "icon": "bi-people" },
  "modos": ["coleccion", "registro"],
  "expose": {
    "columns": [
      { "name": "nombre",   "label": "Nombre",   "type": "text" },
      { "name": "email",    "label": "Correo",   "type": "text" },
      { "name": "telefono", "label": "Teléfono", "type": "text" },
      { "name": "status",   "label": "Estado",   "type": "text", "treatments": ["count"] }
    ],
    "relations": [],
    "group_by": ["status"],
    "order_by": ["status"],
    "filters": [ { "field": "status", "label": "Estado" } ],
    "period": { "field": "created_at", "label": "Creado", "presets": ["hoy", "semana", "mes", "anio", "ayer", "mes_pasado", "anio_pasado", "todo"] },
    "max_rows": 5000
  },
  "templates": { "coleccion": ["tabla_estadistica"], "registro": ["contrato"] }
}
```

- [ ] **Step 6: Ejecutar el test y verificar que pasa**

Run: `php tests/run.php ReporteConfigCatalogo`
Expected: PASS (4 passed). Si alguna fuente no carga, el loader lanza `ValidationException` con el detalle del campo inválido — corregir el JSON correspondiente.

- [ ] **Step 7: Verificar que la suite completa sigue verde**

Run: `php tests/run.php`
Expected: 0 failed.

- [ ] **Step 8: Commit**

```bash
git add config/reportes/pedidos.json config/reportes/productos.json config/reportes/clientes.json config/cruds/demo_productos.json tests/Reporte/ReporteConfigCatalogoTest.php
git commit -m "feat(reportes): catalogo de fuentes demo (pedidos, productos, clientes)"
```

---

## Task 4: Las 3 plantillas PDF de registro

**Files:**
- Create: `app/Application/Pdf/Templates/TicketCompraTemplate.php`
- Create: `app/Application/Pdf/Templates/PresupuestoTemplate.php`
- Create: `app/Application/Pdf/Templates/ContratoTemplate.php`
- Modify: `config/pdf_templates.php`
- Test: `tests/Pdf/DocumentoTemplatesTest.php` (create)

**Contrato de payload de registro** (lo arma `GenerarDocumentoUseCase`, Task 6):
```
[
  'orientation' => 'portrait',
  'title'       => string,
  'marca'       => array (logo, empresa, ...),
  'record'      => array<string,mixed>,            // el registro con scope
  'relations'   => [
      'cliente' => ?string,                         // label (nombre) del belongsTo, o null
      'items'   => list<array<string,mixed>>,       // filas hasMany
  ],
]
```
Cada plantilla usa sólo lo que necesita. `compose()` debe tolerar claves ausentes.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Pdf/DocumentoTemplatesTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Pdf\Templates\ContratoTemplate;
use App\Application\Pdf\Templates\PresupuestoTemplate;
use App\Application\Pdf\Templates\TicketCompraTemplate;

/** @return list<string> tipos de bloque en orden */
function doc_types(\App\Domain\Pdf\PdfDocument $doc): array
{
    return array_map(static fn($b) => $b->type(), $doc->blocks());
}

function doc_pedido_payload(): array
{
    return [
        'orientation' => 'portrait',
        'title'       => 'Ticket',
        'marca'       => ['logo' => '', 'empresa' => 'ACME'],
        'record'      => ['folio' => 'PED-1', 'total' => '289.40', 'status' => 'pagado', 'cliente_id' => 7],
        'relations'   => [
            'cliente' => 'Juan Pérez',
            'items'   => [
                ['descripcion' => 'A', 'cantidad' => 1, 'precio_unitario' => '199.90', 'subtotal' => '199.90'],
                ['descripcion' => 'B', 'cantidad' => 1, 'precio_unitario' => '89.50', 'subtotal' => '89.50'],
            ],
        ],
    ];
}

test('TicketCompraTemplate soporta registro y no coleccion', function (): void {
    $t = new TicketCompraTemplate();
    assert_true($t->supports('registro'));
    assert_true(!$t->supports('coleccion'));
});

test('TicketCompraTemplate compone tabla de items, totales y footer', function (): void {
    $doc = (new TicketCompraTemplate())->compose(doc_pedido_payload());
    $types = doc_types($doc);
    assert_true(in_array('header', $types, true));
    assert_true(in_array('table', $types, true), 'debe incluir la tabla de items');
    assert_true(in_array('totals', $types, true), 'debe incluir el total');
    assert_true(in_array('footer', $types, true));
});

test('PresupuestoTemplate incluye datos de cliente, tabla, totales y firma', function (): void {
    $doc = (new PresupuestoTemplate())->compose(doc_pedido_payload());
    $types = doc_types($doc);
    assert_true(in_array('text', $types, true), 'bloque de datos de cliente');
    assert_true(in_array('table', $types, true));
    assert_true(in_array('totals', $types, true));
    assert_true(in_array('signature', $types, true));
});

test('ContratoTemplate compone texto largo y firma', function (): void {
    $t = new ContratoTemplate();
    assert_true($t->supports('registro'));
    $doc = $t->compose([
        'title'  => 'Contrato',
        'marca'  => ['empresa' => 'ACME'],
        'record' => ['nombre' => 'Juan Pérez', 'email' => 'j@x.com', 'telefono' => '555', 'status' => 'activo'],
        'relations' => [],
    ]);
    $types = doc_types($doc);
    assert_true(in_array('text', $types, true));
    assert_true(in_array('signature', $types, true));
});
```

- [ ] **Step 2: Ejecutar el test y verificar que falla**

Run: `php tests/run.php DocumentoTemplates`
Expected: FAIL con `Class "App\Application\Pdf\Templates\TicketCompraTemplate" not found`

- [ ] **Step 3: Crear `TicketCompraTemplate`**

`app/Application/Pdf/Templates/TicketCompraTemplate.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Pdf\Templates;

use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfLogo;
use App\Domain\Pdf\PdfPageSetup;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfTemplateInterface;
use App\Domain\Pdf\PdfTotalsBlock;

/**
 * Documento de registro (modo registro) para un pedido: ticket de compra compacto.
 * Ejercita logo + marca, header, tabla de items (hasMany) y total. No genera HTML;
 * sólo compone componentes del pdf-kit.
 */
final class TicketCompraTemplate implements PdfTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $doc = PdfDocument::make(new PdfPageSetup('A4', 'portrait'));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $logo = (string) ($marca['logo'] ?? '');
        if ($logo !== '') {
            $doc->add(new PdfLogo($logo, 40));
        }

        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $relations = is_array($payload['relations'] ?? null) ? $payload['relations'] : [];
        $cliente = (string) ($relations['cliente'] ?? '');
        $folio = (string) ($record['folio'] ?? '');

        $doc->add(new PdfHeader('Ticket de compra · ' . $folio, trim((string) ($marca['empresa'] ?? '') . ' · ' . $cliente, ' ·')));
        $doc->add(new PdfSpacer(8));

        $items = is_array($relations['items'] ?? null) ? $relations['items'] : [];
        $doc->add(new PdfDataTable(
            [
                ['name' => 'descripcion', 'label' => 'Descripción'],
                ['name' => 'cantidad', 'label' => 'Cantidad'],
                ['name' => 'precio_unitario', 'label' => 'P. Unitario', 'format' => 'money'],
                ['name' => 'subtotal', 'label' => 'Subtotal', 'format' => 'money'],
            ],
            array_values($items)
        ));

        $doc->add(new PdfSpacer(8));
        $doc->add(new PdfTotalsBlock([
            ['label' => 'Total', 'value' => (string) ($record['total'] ?? '0'), 'format' => 'money'],
        ]));

        $doc->add(new PdfFooter('Generado por Lebytek · ' . date('Y-m-d H:i')));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'registro';
    }
}
```

- [ ] **Step 4: Crear `PresupuestoTemplate`**

`app/Application/Pdf/Templates/PresupuestoTemplate.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Pdf\Templates;

use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfPageSetup;
use App\Domain\Pdf\PdfSignatureBlock;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfTemplateInterface;
use App\Domain\Pdf\PdfText;
use App\Domain\Pdf\PdfTotalsBlock;

/**
 * Documento de registro para un pedido: presupuesto con datos de cliente (belongsTo),
 * tabla de conceptos (hasMany), subtotal/impuestos/total y bloque de firma.
 */
final class PresupuestoTemplate implements PdfTemplateInterface
{
    private const IVA = 0.16;

    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $doc = PdfDocument::make(new PdfPageSetup('A4', 'portrait'));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $relations = is_array($payload['relations'] ?? null) ? $payload['relations'] : [];
        $cliente = (string) ($relations['cliente'] ?? 'Cliente');

        $doc->add(new PdfHeader('Presupuesto · ' . (string) ($record['folio'] ?? ''), (string) ($marca['empresa'] ?? '')));
        $doc->add(new PdfSpacer(6));
        $doc->add(new PdfText('Cliente: ' . $cliente, 'bold'));
        $doc->add(new PdfSpacer(6));

        $items = is_array($relations['items'] ?? null) ? $relations['items'] : [];
        $doc->add(new PdfDataTable(
            [
                ['name' => 'descripcion', 'label' => 'Concepto'],
                ['name' => 'cantidad', 'label' => 'Cantidad'],
                ['name' => 'precio_unitario', 'label' => 'P. Unitario', 'format' => 'money'],
                ['name' => 'subtotal', 'label' => 'Importe', 'format' => 'money'],
            ],
            array_values($items)
        ));

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['subtotal'] ?? 0);
        }
        $impuestos = round($subtotal * self::IVA, 2);
        $total = round($subtotal + $impuestos, 2);

        $doc->add(new PdfSpacer(8));
        $doc->add(new PdfTotalsBlock([
            ['label' => 'Subtotal', 'value' => number_format($subtotal, 2, '.', ''), 'format' => 'money'],
            ['label' => 'IVA (16%)', 'value' => number_format($impuestos, 2, '.', ''), 'format' => 'money'],
            ['label' => 'Total', 'value' => number_format($total, 2, '.', ''), 'format' => 'money'],
        ]));

        $doc->add(new PdfSpacer(16));
        $doc->add(new PdfSignatureBlock(['Cliente', 'Proveedor']));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'registro';
    }
}
```

- [ ] **Step 5: Crear `ContratoTemplate`**

`app/Application/Pdf/Templates/ContratoTemplate.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Pdf\Templates;

use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfPageSetup;
use App\Domain\Pdf\PdfSignatureBlock;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfTemplateInterface;
use App\Domain\Pdf\PdfText;

/**
 * Documento de registro para un cliente: contrato con texto largo y datos del cliente
 * incrustados, más bloque de firma. Ejercita PdfText (texto largo) + PdfSignatureBlock.
 */
final class ContratoTemplate implements PdfTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $doc = PdfDocument::make(new PdfPageSetup('A4', 'portrait'));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $empresa = (string) ($marca['empresa'] ?? 'La Empresa');
        $nombre = (string) ($record['nombre'] ?? '');
        $email = (string) ($record['email'] ?? '');
        $telefono = (string) ($record['telefono'] ?? '');

        $doc->add(new PdfHeader('Contrato de servicio', $empresa));
        $doc->add(new PdfSpacer(10));

        $cuerpo = sprintf(
            'Por el presente documento, %s (en adelante "El Proveedor") y %s, con correo %s y teléfono %s '
            . '(en adelante "El Cliente"), acuerdan los términos y condiciones del servicio contratado. '
            . 'El Cliente declara que los datos proporcionados son correctos y autoriza su tratamiento conforme '
            . 'a la política de privacidad vigente. Este contrato entra en vigor en la fecha de su firma.',
            $empresa,
            $nombre !== '' ? $nombre : 'El Cliente',
            $email !== '' ? $email : 's/d',
            $telefono !== '' ? $telefono : 's/d'
        );

        $doc->add(new PdfText($cuerpo, 'normal'));
        $doc->add(new PdfSpacer(8));
        $doc->add(new PdfText('Estado actual del cliente: ' . (string) ($record['status'] ?? 's/d'), 'muted'));
        $doc->add(new PdfSpacer(24));
        $doc->add(new PdfSignatureBlock(['El Cliente', 'El Proveedor']));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'registro';
    }
}
```

- [ ] **Step 6: Registrar las 3 claves en el whitelist**

En `config/pdf_templates.php`, añadir los `use` y las entradas. Reemplazar el archivo completo por:

```php
<?php

declare(strict_types=1);

use App\Application\Pdf\Templates\ContratoTemplate;
use App\Application\Pdf\Templates\DemoReporteTemplate;
use App\Application\Pdf\Templates\PresupuestoTemplate;
use App\Application\Pdf\Templates\TablaEstadisticaTemplate;
use App\Application\Pdf\Templates\TicketCompraTemplate;

// Whitelist de plantillas PDF: clave estable => clase PdfTemplateInterface.
// NUNCA se acepta un FQCN proveniente de datos de usuario; solo estas claves.
// Reportes y otros módulos añaden sus plantillas aquí.
return [
    'demo_reporte'      => DemoReporteTemplate::class,
    'tabla_estadistica' => TablaEstadisticaTemplate::class,
    'ticket_compra'     => TicketCompraTemplate::class,
    'presupuesto'       => PresupuestoTemplate::class,
    'contrato'          => ContratoTemplate::class,
];
```

- [ ] **Step 7: Ejecutar el test y verificar que pasa**

Run: `php tests/run.php DocumentoTemplates`
Expected: PASS (5 passed)

- [ ] **Step 8: Commit**

```bash
git add app/Application/Pdf/Templates/TicketCompraTemplate.php app/Application/Pdf/Templates/PresupuestoTemplate.php app/Application/Pdf/Templates/ContratoTemplate.php config/pdf_templates.php tests/Pdf/DocumentoTemplatesTest.php
git commit -m "feat(reportes): plantillas PDF de registro (ticket, presupuesto, contrato)"
```

---

## Task 5: Lectura de un registro con scope (`findInScope` + `findByIdScoped` + `findRecord`)

**Files:**
- Create: `app/Domain/Reporte/ReporteRecordSourceInterface.php`
- Modify: `app/Infrastructure/Repositories/GenericCrudRepository.php`
- Modify: `app/Application/Services/CrudDataService.php`
- Modify: `app/Application/Reporte/CrudReporteDataSource.php`

> Estos métodos tocan BD real (no hay doble en este nivel; igual que `CrudReporteDataSource::rows()`, que no tiene test unitario). Se verifican por carga de clases + en el smoke manual de Task 9. La lógica testeable (orquestación con scope) se cubre en Task 6 con un fake de `ReporteRecordSourceInterface`.

- [ ] **Step 1: Crear la interfaz de lectura de registro**

`app/Domain/Reporte/ReporteRecordSourceInterface.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Reporte;

use App\Domain\Entities\CrudResourceDefinition;

/**
 * Frontera de lectura de UN registro con sus relaciones declaradas, respetando el
 * mismo row-level scope que el listado CRUD. Devuelve null si el usuario no lo vería.
 */
interface ReporteRecordSourceInterface
{
    /**
     * @param list<string> $relationNames relaciones CRUD a cargar (belongsTo|hasMany)
     * @return array{record: array<string,mixed>, relations: array<string,mixed>}|null
     */
    public function findRecord(
        CrudResourceDefinition $definition,
        int $id,
        ?int $userId,
        ?callable $can,
        array $relationNames
    ): ?array;
}
```

- [ ] **Step 2: Añadir `findByIdScoped` al repositorio**

En `app/Infrastructure/Repositories/GenericCrudRepository.php`, añadir tras el método `findById()`:

```php
    /**
     * Un registro por condiciones WHERE ya seguras (deleted + pk + scope). Mismo estilo
     * que la familia select*: las condiciones traen placeholders y sus params.
     *
     * @param list<string> $whereSqlParts
     * @param list<mixed>  $params
     * @return array<string,mixed>|null
     */
    public function findByIdScoped(string $table, array $whereSqlParts, array $params): ?array
    {
        $safeTable = $this->quoteIdentifier($table);
        $whereSql = empty($whereSqlParts) ? '' : ' WHERE ' . implode(' AND ', $whereSqlParts);
        $row = $this->queryOne("SELECT * FROM {$safeTable}{$whereSql} LIMIT 1", $params);
        return is_array($row) ? $row : null;
    }
```

- [ ] **Step 3: Añadir `findInScope` a `CrudDataService`**

En `app/Application/Services/CrudDataService.php`, añadir tras el método `find()` (que devuelve sin scope):

```php
    /**
     * Un registro por id respetando el mismo row-level scope que el listado. Devuelve
     * null si el scope lo excluye (el usuario no lo vería en el CRUD). Reutiliza
     * applyScopeConditions(); no reimplementa permisos.
     *
     * @return array<string,mixed>|null
     */
    public function findInScope(
        CrudResourceDefinition $definition,
        int $id,
        ?int $userId = null,
        ?callable $can = null
    ): ?array {
        $where = ['deleted = 0', '`' . $definition->primaryKey() . '` = ?'];
        $params = [$id];
        $this->applyScopeConditions($definition, [], $userId, $can, $where, $params);

        return $this->repository->findByIdScoped($definition->table(), $where, $params);
    }
```

- [ ] **Step 4: Implementar `findRecord` en `CrudReporteDataSource`**

Reemplazar el contenido de `app/Application/Reporte/CrudReporteDataSource.php` por:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Application\Services\CrudDataService;
use App\Application\Services\CrudRelationService;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Reporte\ReporteDataSourceInterface;
use App\Domain\Reporte\ReporteRecordSourceInterface;

/**
 * Adaptador de lectura de datos para reportes sobre CrudDataService, que respeta el
 * mismo row-level scope y filtros de igualdad del listado CRUD. Sirve tanto colección
 * (rows) como registro (findRecord + relaciones). No arma SQL bespoke ni reimplementa
 * permisos.
 */
final class CrudReporteDataSource implements ReporteDataSourceInterface, ReporteRecordSourceInterface
{
    public function __construct(
        private readonly CrudDataService $crudDataService,
        private readonly CrudRelationService $crudRelationService,
    ) {}

    public function rows(
        CrudResourceDefinition $definition,
        string $dateColumn,
        string $from,
        string $to,
        ?int $userId,
        ?callable $can,
        array $filters
    ): array {
        return $this->crudDataService->eventsInRange(
            $definition,
            $dateColumn,
            $from,
            $to,
            $userId,
            $can,
            $filters
        );
    }

    public function findRecord(
        CrudResourceDefinition $definition,
        int $id,
        ?int $userId,
        ?callable $can,
        array $relationNames
    ): ?array {
        $record = $this->crudDataService->findInScope($definition, $id, $userId, $can);
        if ($record === null) {
            return null;
        }

        $relations = [];
        foreach ($relationNames as $name) {
            $name = (string) $name;
            $relation = $definition->relation($name);
            if ($relation === null) {
                continue;
            }
            if ($relation->isBelongsTo()) {
                $options = $this->crudRelationService->optionsFor($relation);
                $fkValue = (string) ($record[$relation->foreignKey()] ?? '');
                $relations[$name] = $options[$fkValue] ?? null;
            } elseif ($relation->isHasMany()) {
                $relations[$name] = $this->crudRelationService->childrenFor($relation, $id);
            }
        }

        return ['record' => $record, 'relations' => $relations];
    }
}
```

- [ ] **Step 5: Verificar que todo carga (sin errores de sintaxis/tipos)**

Run: `php -l app/Application/Reporte/CrudReporteDataSource.php`
Expected: `No syntax errors detected`

Run: `php tests/run.php`
Expected: 0 failed (las pruebas existentes que construyen `CrudReporteDataSource` directamente no existen; los tests de colección usan `FakeReporteDataSource`, no afectado).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Reporte/ReporteRecordSourceInterface.php app/Infrastructure/Repositories/GenericCrudRepository.php app/Application/Services/CrudDataService.php app/Application/Reporte/CrudReporteDataSource.php
git commit -m "feat(reportes): lectura de un registro con scope y relaciones (findRecord)"
```

---

## Task 6: `GenerarDocumentoUseCase` (buildPayload + generar)

**Files:**
- Create: `app/Application/Reporte/GenerarDocumentoUseCase.php`
- Test: `tests/Reporte/GenerarDocumentoUseCaseTest.php` (create)

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Reporte/GenerarDocumentoUseCaseTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Pdf\PdfTemplateRegistry;
use App\Application\Reporte\GenerarDocumentoUseCase;
use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Reporte\ReporteConfigValidator;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteRecordSourceInterface;

final class FakeReporteRecordSource implements ReporteRecordSourceInterface
{
    /** @param array{record:array,relations:array}|null $result */
    public function __construct(private readonly ?array $result, public array $lastCall = []) {}

    public function findRecord(CrudResourceDefinition $definition, int $id, ?int $userId, ?callable $can, array $relationNames): ?array
    {
        $this->lastCall = ['id' => $id, 'userId' => $userId, 'relationNames' => $relationNames];
        return $this->result;
    }
}

function gd_useCase(ReporteRecordSourceInterface $source): GenerarDocumentoUseCase
{
    return new GenerarDocumentoUseCase(
        new ReporteConfigLoader(new ReporteConfigValidator()),
        $source,
        new PdfTemplateRegistry(require ROOT_PATH . '/config/pdf_templates.php')
    );
}

test('buildPayload arma el payload con record y relaciones declaradas', function (): void {
    $source = new FakeReporteRecordSource([
        'record'    => ['folio' => 'PED-1', 'total' => '289.40', 'cliente_id' => 7],
        'relations' => ['cliente' => 'Juan Pérez', 'items' => [['descripcion' => 'A']]],
    ]);
    $payload = gd_useCase($source)->buildPayload('pedidos', 5, 'ticket_compra', 3, fn(string $s): bool => true);

    assert_same('PED-1', $payload['record']['folio']);
    assert_same('Juan Pérez', $payload['relations']['cliente']);
    assert_same(['cliente', 'items'], $source->lastCall['relationNames']);
});

test('buildPayload devuelve null cuando el registro está fuera de scope', function (): void {
    $payload = gd_useCase(new FakeReporteRecordSource(null))
        ->buildPayload('pedidos', 99, 'ticket_compra', 3, fn(string $s): bool => true);
    assert_null($payload);
});

test('buildPayload rechaza una plantilla no declarada por la fuente para registro', function (): void {
    $source = new FakeReporteRecordSource(['record' => [], 'relations' => []]);
    assert_throws(ValidationException::class, fn() =>
        gd_useCase($source)->buildPayload('clientes', 1, 'ticket_compra', 3, fn(string $s): bool => true));
});

test('buildPayload acepta la fuente clientes con la plantilla contrato', function (): void {
    // contrato está declarada en clientes.templates.registro y supports("registro")===true.
    // (La guarda supports() para plantillas de sólo-colección se cubre en los tests de
    // plantilla: tabla_estadistica->supports("registro") es false.)
    $source = new FakeReporteRecordSource(['record' => ['nombre' => 'X'], 'relations' => []]);
    $payload = gd_useCase($source)->buildPayload('clientes', 1, 'contrato', 3, fn(string $s): bool => true);
    assert_same('X', $payload['record']['nombre']);
});
```

- [ ] **Step 2: Ejecutar el test y verificar que falla**

Run: `php tests/run.php GenerarDocumentoUseCase`
Expected: FAIL con `Class "App\Application\Reporte\GenerarDocumentoUseCase" not found`

- [ ] **Step 3: Implementar `GenerarDocumentoUseCase`**

`app/Application/Reporte/GenerarDocumentoUseCase.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Application\Pdf\PdfRenderingService;
use App\Application\Pdf\PdfTemplateRegistry;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteRecordSourceInterface;
use App\Kernel\Config\Config;

/**
 * Modo registro: valida fuente+plantilla, lee el registro con scope (vía la fuente de
 * datos), arma el payload y, en generar(), lo renderiza a PDF. buildPayload() es puro y
 * testeable; generar() es la capa fina que añade la marca y renderiza.
 *
 * PdfRenderingService es opcional para permitir tests de buildPayload sin dompdf.
 */
final class GenerarDocumentoUseCase
{
    public function __construct(
        private readonly ReporteConfigLoader $loader,
        private readonly ReporteRecordSourceInterface $source,
        private readonly PdfTemplateRegistry $registry,
        private readonly ?PdfRenderingService $pdf = null,
    ) {}

    /**
     * @return array<string,mixed>|null payload listo para la plantilla, o null si el
     *         registro no es visible para el usuario (→ 404).
     * @throws ValidationException si la fuente o la plantilla son inválidas.
     */
    public function buildPayload(string $fuenteKey, int $id, string $templateKey, ?int $userId, callable $can): ?array
    {
        $fuente = $this->loader->load($fuenteKey);

        if (!in_array($templateKey, $fuente->templatesFor('registro'), true)) {
            throw new ValidationException("La plantilla '{$templateKey}' no está disponible para registro en esta fuente.");
        }
        if (!$this->registry->has($templateKey)) {
            throw new ValidationException("La plantilla PDF '{$templateKey}' no existe.");
        }
        if (!$this->registry->resolve($templateKey)->supports('registro')) {
            throw new ValidationException("La plantilla '{$templateKey}' no soporta el modo registro.");
        }

        $definition = $this->loader->crudDefinition($fuente->resource());
        $found = $this->source->findRecord($definition, $id, $userId, $can, $fuente->relationNames());
        if ($found === null) {
            return null;
        }

        return [
            'orientation' => 'portrait',
            'title'       => $fuente->title(),
            'record'      => is_array($found['record'] ?? null) ? $found['record'] : [],
            'relations'   => is_array($found['relations'] ?? null) ? $found['relations'] : [],
        ];
    }

    /**
     * @return string|null bytes del PDF, o null si el registro no es visible (→ 404).
     * @throws ValidationException si la fuente o la plantilla son inválidas.
     */
    public function generar(string $fuenteKey, int $id, string $templateKey, ?int $userId, callable $can): ?string
    {
        $payload = $this->buildPayload($fuenteKey, $id, $templateKey, $userId, $can);
        if ($payload === null) {
            return null;
        }
        if ($this->pdf === null) {
            throw new \LogicException('PdfRenderingService es obligatorio para generar().');
        }

        $payload['marca'] = $this->marca();
        return $this->pdf->renderTemplate($templateKey, $payload);
    }

    /** @return array<string,mixed> */
    private function marca(): array
    {
        $marca = Config::get('pdf.marca', []);
        return is_array($marca) ? $marca : [];
    }
}
```

- [ ] **Step 4: Ejecutar el test y verificar que pasa**

Run: `php tests/run.php GenerarDocumentoUseCase`
Expected: PASS (4 passed)

- [ ] **Step 5: Verificar la suite completa**

Run: `php tests/run.php`
Expected: 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Reporte/GenerarDocumentoUseCase.php tests/Reporte/GenerarDocumentoUseCaseTest.php
git commit -m "feat(reportes): GenerarDocumentoUseCase para PDF en modo registro"
```

---

## Task 7: Controlador, ruta y wiring DI

**Files:**
- Modify: `app/Presentation/Controllers/Admin/ReportesController.php`
- Modify: `routes/web.php`
- Modify: `config/container.php`

- [ ] **Step 1: Añadir el método `documento()` y la dependencia al controlador**

En `app/Presentation/Controllers/Admin/ReportesController.php`:

1. Añadir el `use`:

```php
use App\Application\Reporte\GenerarDocumentoUseCase;
```

2. En el constructor, añadir el parámetro tras `private readonly GenerarReporteUseCase $generar,`:

```php
        private readonly GenerarDocumentoUseCase $documentos,
```

3. Añadir el método público tras `generar()`:

```php
    public function documento(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $fuente = trim((string) $request->query('fuente', ''));
        $id = (int) $request->query('id', 0);
        $template = trim((string) $request->query('template', ''));
        if ($fuente === '' || $id <= 0 || $template === '') {
            return Response::notFound();
        }

        try {
            $bytes = $this->documentos->generar($fuente, $id, $template, $this->userId(), $this->canChecker());
        } catch (ValidationException) {
            // Fuente/plantilla inválida o no declarada por la fuente → 404 (no se genera).
            return Response::notFound();
        } catch (\Throwable $e) {
            // dompdf u otro fallo de render: nunca se descarga un PDF corrupto.
            \App\Kernel\Logging\AppLogger::error('Reporte documento: fallo de generación', [
                'fuente' => $fuente, 'id' => $id, 'template' => $template, 'error' => $e->getMessage(),
            ]);
            return Response::notFound();
        }

        if ($bytes === null) {
            // Registro fuera de scope o inexistente → 404 (no revela existencia).
            return Response::notFound();
        }

        return Response::download($bytes, 'documento-' . $fuente . '-' . $id . '.pdf', 'application/pdf');
    }
```

- [ ] **Step 2: Registrar la ruta**

En `routes/web.php`, dentro del grupo admin, añadir tras la línea de `'/reportes/crear'` (línea ~127):

```php
    $router->get('/reportes/documento',       [ReportesController::class, 'documento'], [new RbacMiddleware('reportes.generar')]);
```

(Va antes de las rutas `'/reportes/{id}/...'`; `/reportes/documento` es una ruta literal que no colisiona con `{id}`.)

- [ ] **Step 3: Ampliar los bindings del contenedor**

En `config/container.php`:

1. Ampliar el binding de `CrudReporteDataSource` (líneas ~278–280) para inyectar `CrudRelationService` y registrar también la nueva interfaz. Reemplazar:

```php
    $container->singleton(\App\Domain\Reporte\ReporteDataSourceInterface::class, fn(Container $c) => new \App\Application\Reporte\CrudReporteDataSource(
        $c->get(CrudDataService::class)
    ));
```

por:

```php
    $container->singleton(\App\Application\Reporte\CrudReporteDataSource::class, fn(Container $c) => new \App\Application\Reporte\CrudReporteDataSource(
        $c->get(CrudDataService::class),
        $c->get(CrudRelationService::class)
    ));
    $container->singleton(\App\Domain\Reporte\ReporteDataSourceInterface::class, fn(Container $c) => $c->get(\App\Application\Reporte\CrudReporteDataSource::class));
    $container->singleton(\App\Domain\Reporte\ReporteRecordSourceInterface::class, fn(Container $c) => $c->get(\App\Application\Reporte\CrudReporteDataSource::class));
```

2. Añadir el binding de `GenerarDocumentoUseCase` tras el de `GenerarReporteUseCase` (línea ~290):

```php
    $container->singleton(\App\Application\Reporte\GenerarDocumentoUseCase::class, fn(Container $c) => new \App\Application\Reporte\GenerarDocumentoUseCase(
        $c->get(\App\Application\Reporte\ReporteConfigLoader::class),
        $c->get(\App\Domain\Reporte\ReporteRecordSourceInterface::class),
        $c->get(\App\Application\Pdf\PdfTemplateRegistry::class),
        $c->get(\App\Application\Pdf\PdfRenderingService::class)
    ));
```

3. Ampliar el binding del controlador (líneas ~477–486) añadiendo el nuevo argumento al final de la lista de `new ReportesController(...)`:

```php
            $c->get(\App\Application\Reporte\GenerarDocumentoUseCase::class)
```

(Va tras `$c->get(\App\Application\Reporte\GenerarReporteUseCase::class)`; añade la coma a la línea anterior.)

- [ ] **Step 4: Verificar sintaxis y arranque del contenedor**

Run: `php -l app/Presentation/Controllers/Admin/ReportesController.php`
Expected: `No syntax errors detected`

Run: `php -r "define('ROOT_PATH', getcwd()); require 'app/Kernel/Autoloader.php'; \App\Kernel\Autoloader::register(); echo 'ok';"`
Expected: `ok` (si el autoloader expone `register()`; si la firma difiere, omitir este chequeo y confiar en el smoke de Task 9).

- [ ] **Step 5: Verificar la suite**

Run: `php tests/run.php`
Expected: 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Controllers/Admin/ReportesController.php routes/web.php config/container.php
git commit -m "feat(reportes): ruta y controlador documento() para modo registro"
```

---

## Task 8: Acciones `link` de documentos en los CRUD demo

**Files:**
- Modify: `config/cruds/demo_pedidos.json`
- Modify: `config/cruds/demo_clientes.json`

- [ ] **Step 1: Añadir "Ticket PDF" y "Presupuesto PDF" a `demo_pedidos`**

En `config/cruds/demo_pedidos.json`, dentro de `actions.row`, añadir tras la acción `cancelar` (último objeto del array; respeta la coma):

```json
      { "name": "ticket", "type": "link", "label": "Ticket PDF", "icon": "bi-receipt",
        "route": "/admin/reportes/documento?fuente=pedidos&id={id}&template=ticket_compra",
        "permission": "reportes.generar" },
      { "name": "presupuesto", "type": "link", "label": "Presupuesto PDF", "icon": "bi-file-earmark-text",
        "route": "/admin/reportes/documento?fuente=pedidos&id={id}&template=presupuesto",
        "permission": "reportes.generar" }
```

- [ ] **Step 2: Añadir un bloque `actions.row` con "Contrato PDF" a `demo_clientes`**

`demo_clientes.json` hoy no tiene bloque `actions`. Añadir uno a nivel raíz (p. ej. tras el bloque `"form"` y antes de `"detail"`, respetando comas):

```json
  "actions": {
    "row": [
      { "name": "show", "type": "builtin" },
      { "name": "edit", "type": "builtin" },
      { "name": "delete", "type": "builtin" },
      { "name": "contrato", "type": "link", "label": "Contrato PDF", "icon": "bi-file-earmark-text",
        "route": "/admin/reportes/documento?fuente=clientes&id={id}&template=contrato",
        "permission": "reportes.generar" }
    ],
    "bulk": []
  },
```

- [ ] **Step 3: Verificar que el JSON es válido**

Run: `php -r "json_decode(file_get_contents('config/cruds/demo_pedidos.json'), true); echo json_last_error_msg();"`
Expected: `No error`

Run: `php -r "json_decode(file_get_contents('config/cruds/demo_clientes.json'), true); echo json_last_error_msg();"`
Expected: `No error`

- [ ] **Step 4: Commit**

```bash
git add config/cruds/demo_pedidos.json config/cruds/demo_clientes.json
git commit -m "feat(reportes): acciones link de documentos en pedidos y clientes demo"
```

---

## Task 9: Seeds de los 5 reportes demo de colección

**Files:**
- Modify: `database/schema/modules/reportes.sql`

> Las claves son estables y `compartido=1, deleted=0`. `INSERT IGNORE` + `UNIQUE KEY uq_rep_reportes_clave` hacen el seeding idempotente.

- [ ] **Step 1: Añadir los 5 INSERT IGNORE**

En `database/schema/modules/reportes.sql`, antes de la línea final `SET FOREIGN_KEY_CHECKS = 1;`, añadir:

```sql
-- Reportes demo adicionales (iteración 3): variedad de tratamientos por módulo.
INSERT IGNORE INTO `rep_reportes`
(`clave`, `nombre`, `fuente_key`, `modo`, `columnas`, `tratamientos`, `filtros`, `periodo`, `opciones`, `template_key`, `compartido`, `deleted`, `created_at`)
VALUES
(
  'demo_pedidos_ventas_cliente',
  'Ventas por cliente',
  'pedidos',
  'coleccion',
  '[{"name":"cliente_id","label":"Cliente","type":"number"},{"name":"total","label":"Total","type":"money"}]',
  '{"group_by":["cliente_id"],"aggregations":[{"op":"count","column":""},{"op":"sum","column":"total"}],"order":{"by":"cliente_id","dir":"asc"}}',
  '{}',
  '{"preset":"mes"}',
  '{"titulo":"Ventas por cliente","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_pedidos_por_estado',
  'Pedidos por estado',
  'pedidos',
  'coleccion',
  '[{"name":"status","label":"Estado","type":"text"}]',
  '{"group_by":["status"],"aggregations":[{"op":"count","column":""}],"order":{"by":"status","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Pedidos por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_productos_inventario_categoria',
  'Inventario por categoría',
  'productos',
  'coleccion',
  '[{"name":"categoria_id","label":"Categoría","type":"number"},{"name":"stock_actual","label":"Stock","type":"number"},{"name":"precio_venta","label":"Precio","type":"money"}]',
  '{"group_by":["categoria_id"],"aggregations":[{"op":"sum","column":"stock_actual"},{"op":"sum","column":"precio_venta"}],"order":{"by":"categoria_id","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Inventario por categoría","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_productos_por_estado',
  'Productos por estado',
  'productos',
  'coleccion',
  '[{"name":"status","label":"Estado","type":"text"}]',
  '{"group_by":["status"],"aggregations":[{"op":"count","column":""}],"order":{"by":"status","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Productos por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_clientes_por_estado',
  'Clientes por estado',
  'clientes',
  'coleccion',
  '[{"name":"status","label":"Estado","type":"text"}]',
  '{"group_by":["status"],"aggregations":[{"op":"count","column":""}],"order":{"by":"status","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Clientes por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
);
```

- [ ] **Step 2: Verificar idempotencia (re-ejecución segura)**

Run: `php scripts/install.php` (o el comando de bootstrap del proyecto que aplica `database/schema/modules/*.sql`).
Expected: corre sin error; re-ejecutar una segunda vez **no** duplica filas (las 5 claves ya existen ⇒ `INSERT IGNORE` las omite).

Verificación opcional vía consola del proyecto/DB:
`SELECT clave, COUNT(*) FROM rep_reportes GROUP BY clave HAVING COUNT(*) > 1;`
Expected: 0 filas (ninguna clave duplicada).

- [ ] **Step 3: Commit**

```bash
git add database/schema/modules/reportes.sql
git commit -m "feat(reportes): seeds idempotentes de 5 reportes demo de coleccion"
```

---

## Task 10: Verificación end-to-end y smoke manual

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite completa verde**

Run: `php tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 2: Levantar el servidor de desarrollo**

Run: `php -S localhost:8000 -t public`
(Asegurar `.env` y BD con los seeds aplicados.)

- [ ] **Step 3: Colección — los reportes demo se listan y generan**

En el navegador, autenticado como `administrador`:
1. Ir a `/admin/reportes`. Esperado: aparecen `Citas por estado` (existente) + los 5 nuevos (`Ventas por cliente`, `Pedidos por estado`, `Inventario por categoría`, `Productos por estado`, `Clientes por estado`).
2. Generar cada uno (botón Generar). Esperado: se descarga un PDF `reporte-{id}.pdf` válido (empieza con `%PDF`), sin error de orientación ni de columnas.

- [ ] **Step 4: Registro — documentos desde el CRUD**

1. Ir a `/admin/crud/demo_pedidos`. Esperado: cada fila muestra "Ticket PDF" y "Presupuesto PDF".
2. Click en "Ticket PDF" de un pedido. Esperado: descarga `documento-pedidos-{id}.pdf` con logo/marca, tabla de items y total.
3. Click en "Presupuesto PDF". Esperado: PDF con datos de cliente, conceptos, subtotal/IVA/total y firma.
4. Ir a `/admin/crud/demo_clientes`. Click en "Contrato PDF" de un cliente. Esperado: PDF con texto largo (nombre/email/teléfono incrustados) y firma.

- [ ] **Step 5: Seguridad del modo registro (scope + 404)**

1. URL manipulada con un `id` inexistente: `/admin/reportes/documento?fuente=pedidos&id=999999&template=ticket_compra`. Esperado: **404** (no genera PDF).
2. `template` no declarado por la fuente: `/admin/reportes/documento?fuente=clientes&id=1&template=ticket_compra`. Esperado: **404**.
3. Owner-scope: con un usuario **no admin** cuyo scope (owner `created_by`) no incluye un cliente concreto, intentar `/admin/reportes/documento?fuente=clientes&id={id_de_otro}&template=contrato`. Esperado: **404** (el PDF nunca revela un registro fuera del scope del usuario).
4. Sin permiso `reportes.generar`: la ruta responde 403/redirección de RBAC (la maneja `RbacMiddleware`).

- [ ] **Step 6: Commit final (si hubo ajustes durante el smoke)**

```bash
git add -A
git commit -m "test(reportes): verificacion e2e iteracion 3 (catalogo demo + registro)"
```

---

## Notas de cierre

- **Sin cambios en el CRUD Engine ni en el wizard de colección.** El disparo de documentos usa acciones `type:"link"` ya soportadas (`CrudActionResolver` sustituye `{id}`). El permiso con punto (`reportes.generar`) se usa como slug completo.
- **`demo_categorias` no se vuelve fuente propia** (sólo destino de relación vía `categoria_id`).
- **Fuera de alcance (YAGNI):** rango de fechas personalizado, charts embebidos, export Excel/CSV, reportes programados/por correo, editor WYSIWYG, y la resolución de `categoria_id`→nombre en el reporte de inventario.
- **Principios respetados:** toda lectura pasa por `CrudDataService`/`CrudScopeResolver` (scope reutilizado, no reimplementado); la config del programador es la fuente de verdad y se re-valida al generar; plantillas sólo por whitelist clave→clase; componentes del pdf-kit reutilizados sin HTML de usuario.
