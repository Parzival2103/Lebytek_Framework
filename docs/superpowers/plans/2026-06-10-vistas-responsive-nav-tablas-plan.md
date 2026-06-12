# Vistas responsive: navegación (`nav_*`) y tablas del CRUD Engine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hacer responsivas las tres navegaciones del panel (`nav_side`, `nav_top`, `nav_bottom`) bajo un único breakpoint de 992px y permitir que las tablas del CRUD Engine colapsen columnas en móvil mediante la extensión Responsive de DataTables, sin tocar el flujo server-side existente.

**Architecture:** El panel es PHP 8.1 (MVC + Onion), vistas PHP/Bootstrap 5 y JS vanilla modular (sin jQuery global). Se reestructura `nav_top` para que su menú actúe como drawer lateral en móvil (reutilizando el patrón overlay del módulo `Sidebar`), se renderiza `nav_top` como fallback de escritorio en el layout `bottom`, y se carga jQuery+DataTables **solo** en `crud/index.php` en modo "solo-responsive" (sin paginar/buscar/ordenar — eso ya lo hace el servidor). Un nuevo campo opcional `list.columns[].priority` se propaga desde `CrudTableBuilder` hasta atributos `data-priority` en los `<th>`.

**Tech Stack:** PHP 8.1, Bootstrap 5.3.3, JS vanilla (IIFE modules), DataTables 2.x + extensión Responsive (CDN, scopeado a CRUD), runner de tests propio (`tests/run.php` + `tests/lib/microtest.php`, estilo `test()`/`assert_same`).

---

## Convenciones de este repo (leer antes de empezar)

- **Tests:** NO es PHPUnit puro. Los archivos terminan en `Test.php`, usan funciones globales `test(string $name, callable $fn)`, `assert_same($expected, $actual, $msg)`, `assert_true($cond, $msg)`. Se ejecutan con:
  ```bash
  php tests/run.php <substring-del-path>
  ```
  El filtro es un substring del path del archivo (p. ej. `CrudTableBuilderPriority`). Sin filtro corre toda la suite.
- **Vistas:** PHP plano + Bootstrap 5. Escapado siempre con `ViewHelper::e(...)`.
- **Breakpoint objetivo:** `lg` = 992px. Bootstrap usa `<992px` (`991.98px`) como "móvil". El sidebar ya usa `@media (max-width: 991.98px)`; alinear todo a ese valor.
- **Sin jQuery global:** jQuery/DataTables se cargan únicamente dentro de `crud/index.php`.
- **Commits frecuentes:** un commit por tarea como mínimo. Mensajes en español, estilo `feat(...)`, `fix(...)`, `docs(...)`.
- **VPS auto-pull de `main`:** los pushes van directos sin PR. Commitea solo cuando la tarea esté verde.

---

## File Structure

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `app/Application/Services/CrudTableBuilder.php` | Modificar | Propagar `priority` (int) al arreglo de columnas en modo no-agrupado |
| `tests/Crud/Table/CrudTableBuilderPriorityTest.php` | Crear | Verificar propagación/omisión de `priority` |
| `app/Presentation/Views/admin/crud/index.php` | Modificar | Emitir `data-priority` en `<th>`, `id="crudTable"`, cargar+inicializar DataTables Responsive scopeado |
| `config/cruds/demo_productos.json` | Modificar | Ejemplo del campo `list.columns[].priority` |
| `public/assets/css/crud-engine.css` | Modificar | Overrides de DataTables Responsive (child-row) para tema claro/oscuro |
| `app/Presentation/Views/partials/nav_top.php` | Modificar | Separar links de menú (drawer móvil) de las acciones (siempre visibles) |
| `app/Presentation/Views/partials/nav_bottom.php` | Modificar | `d-md-none` → `d-lg-none` en barra y paneles |
| `app/Presentation/Views/layouts/base.php` | Modificar | Rama `bottom`: renderizar `nav_top` (escritorio) + `nav_bottom` (móvil) |
| `public/assets/css/app.css` | Modificar | Drawer de `nav_top` <992px; alinear breakpoint de `layout-bottom-wrapper` a 992px |
| `public/assets/js/app.js` | Modificar | Nuevo módulo `NavDrawer` (toggler de `nav_top` + overlay + Escape + click-fuera) |
| `docs/core/ui_ux.md` | Modificar | Documentar comportamiento responsive de las 3 navegaciones y breakpoint 992px |
| `docs/modules/crud/` (doc de columnas) | Modificar | Documentar `list.columns[].priority` con ejemplo |

**Orden de construcción:** primero la cadena CRUD (PHP con TDD → vista → assets → CSS), luego la navegación (`nav_top` markup → CSS → JS → layout `bottom` → `nav_bottom`), y por último la documentación. Cada bloque deja la app en estado funcional.

---

## Área C — Tablas CRUD (Tareas 1–5)

### Task 1: Propagar `priority` en `CrudTableBuilder` (TDD)

**Files:**
- Test: `tests/Crud/Table/CrudTableBuilderPriorityTest.php` (crear)
- Modify: `app/Application/Services/CrudTableBuilder.php:66-76` (rama no-agrupada del bucle de columnas)

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Crud/Table/CrudTableBuilderPriorityTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudTableBuilder;
use App\Domain\Entities\CrudResourceDefinition;
use App\Kernel\Helpers\Paginator;

function tbp_paginator(): Paginator
{
    return new Paginator(total: 0, perPage: 15, currentPage: 1, baseUrl: '/admin/crud/demo');
}

function tbp_def(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => '',
            'columns' => [
                ['name' => 'id', 'label' => 'ID', 'priority' => 1],
                ['name' => 'nombre', 'label' => 'Nombre'],
                ['name' => 'precio_venta', 'label' => 'Precio', 'format' => 'money', 'priority' => 3],
            ],
        ],
    ]);
}

test('CrudTableBuilder: propaga priority como int cuando está declarado', function (): void {
    $builder = new CrudTableBuilder();
    $vm = $builder->build(
        definition: tbp_def(), rows: [], paginator: tbp_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    $cols = $vm['columns'];
    assert_same(1, $cols[0]['priority'] ?? null, 'columna id propaga priority=1');
    assert_same(3, $cols[2]['priority'] ?? null, 'columna precio_venta propaga priority=3');
});

test('CrudTableBuilder: omite priority cuando no está declarado', function (): void {
    $builder = new CrudTableBuilder();
    $vm = $builder->build(
        definition: tbp_def(), rows: [], paginator: tbp_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    $cols = $vm['columns'];
    assert_true(!array_key_exists('priority', $cols[1]), 'columna nombre no tiene clave priority');
});

test('CrudTableBuilder: priority no-numérico se ignora', function (): void {
    $builder = new CrudTableBuilder();
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => '',
            'columns' => [
                ['name' => 'id', 'label' => 'ID', 'priority' => 'alta'],
            ],
        ],
    ]);
    $vm = $builder->build(
        definition: $def, rows: [], paginator: tbp_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    assert_true(!array_key_exists('priority', $vm['columns'][0]), 'priority no-numérico se ignora');
});
```

- [ ] **Step 2: Ejecutar el test y verificar que falla**

Run: `php tests/run.php CrudTableBuilderPriority`
Expected: FAIL en los dos primeros tests (`priority` no existe en `$cols`), con mensajes `expected 1 got NULL` / la clave existe. (El tercero podría pasar trivialmente.)

- [ ] **Step 3: Implementar el cambio mínimo**

En `app/Application/Services/CrudTableBuilder.php`, dentro del bloque `else` (no-agrupado), reemplazar el push directo del arreglo por una variable a la que se le añade `priority` condicionalmente. Cambiar este bloque (líneas ~67-74):

```php
            foreach ($definition->listColumns() as $column) {
                $columns[] = [
                    'name' => (string) ($column['name'] ?? ''),
                    'label' => (string) ($column['label'] ?? ($column['name'] ?? '')),
                    'sortable' => (bool) ($column['sortable'] ?? false),
                    'format' => (string) ($column['format'] ?? ''),
                    'badge' => is_array($column['badge'] ?? null) ? $column['badge'] : [],
                ];
            }
```

por:

```php
            foreach ($definition->listColumns() as $column) {
                $built = [
                    'name' => (string) ($column['name'] ?? ''),
                    'label' => (string) ($column['label'] ?? ($column['name'] ?? '')),
                    'sortable' => (bool) ($column['sortable'] ?? false),
                    'format' => (string) ($column['format'] ?? ''),
                    'badge' => is_array($column['badge'] ?? null) ? $column['badge'] : [],
                ];
                if (array_key_exists('priority', $column) && is_numeric($column['priority'])) {
                    $built['priority'] = (int) $column['priority'];
                }
                $columns[] = $built;
            }
```

- [ ] **Step 4: Ejecutar el test y verificar que pasa**

Run: `php tests/run.php CrudTableBuilderPriority`
Expected: PASS (3 passed, 0 failed).

- [ ] **Step 5: Verificar que no hay regresión en el resto de la suite del builder**

Run: `php tests/run.php CrudTableBuilder`
Expected: todos PASS (incluye `CrudTableBuilderSummaryTest`, 4 tests previos + los 3 nuevos).

- [ ] **Step 6: Commit**

```bash
git add app/Application/Services/CrudTableBuilder.php tests/Crud/Table/CrudTableBuilderPriorityTest.php
git commit -m "feat(crud): propaga list.columns[].priority en CrudTableBuilder

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Renderizar `data-priority` e `id="crudTable"` en la tabla CRUD

**Files:**
- Modify: `app/Presentation/Views/admin/crud/index.php:105` (apertura de `<table>`)
- Modify: `app/Presentation/Views/admin/crud/index.php:108-118` (cabeceras `<th>`)

- [ ] **Step 1: Añadir `id` a la tabla**

En `index.php`, localizar la línea 105:

```php
                <table class="<?= ViewHelper::e($tableClass) ?>">
```

reemplazar por:

```php
                <table id="crudTable" class="<?= ViewHelper::e($tableClass) ?>">
```

- [ ] **Step 2: Emitir `data-priority="1"` en la columna de checkbox de selección**

Localizar el bloque (líneas ~108-112):

```php
                            <?php if (!empty($selectable)): ?>
                                <th class="px-3" style="width:2.5rem">
                                    <input type="checkbox" class="form-check-input" data-crud-select-all aria-label="Seleccionar todo">
                                </th>
                            <?php endif; ?>
```

reemplazar la apertura del `<th>` para fijar prioridad alta (siempre visible):

```php
                            <?php if (!empty($selectable)): ?>
                                <th class="px-3" style="width:2.5rem" data-priority="1">
                                    <input type="checkbox" class="form-check-input" data-crud-select-all aria-label="Seleccionar todo">
                                </th>
                            <?php endif; ?>
```

- [ ] **Step 3: Emitir `data-priority` por columna de datos (cuando esté declarado)**

Localizar el bucle de cabeceras (líneas ~113-115):

```php
                            <?php foreach (($columns ?? []) as $column): ?>
                                <th class="px-3 text-nowrap"><?= ViewHelper::e((string) ($column['label'] ?? '')) ?></th>
                            <?php endforeach; ?>
```

reemplazar por:

```php
                            <?php foreach (($columns ?? []) as $idx => $column): ?>
                                <?php
                                    // priority explícito de config; si no, la 1ª columna de datos
                                    // queda alta (2) por defecto y el resto colapsa por ancho.
                                    $thPriority = isset($column['priority'])
                                        ? (int) $column['priority']
                                        : ($idx === 0 ? 2 : null);
                                ?>
                                <th class="px-3 text-nowrap"<?= $thPriority !== null ? ' data-priority="' . $thPriority . '"' : '' ?>><?= ViewHelper::e((string) ($column['label'] ?? '')) ?></th>
                            <?php endforeach; ?>
```

- [ ] **Step 4: Emitir `data-priority="1"` en la columna de Acciones**

Localizar (líneas ~116-118):

```php
                            <?php if (!$grouped): ?>
                                <th class="text-end px-3 text-nowrap ct-col-actions">Acciones</th>
                            <?php endif; ?>
```

reemplazar por:

```php
                            <?php if (!$grouped): ?>
                                <th class="text-end px-3 text-nowrap ct-col-actions" data-priority="1">Acciones</th>
                            <?php endif; ?>
```

- [ ] **Step 5: Verificar sintaxis PHP de la vista**

Run: `php -l app/Presentation/Views/admin/crud/index.php`
Expected: `No syntax errors detected in app/Presentation/Views/admin/crud/index.php`

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Views/admin/crud/index.php
git commit -m "feat(crud): emite data-priority e id en la tabla de listado

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Cargar e inicializar DataTables Responsive (scopeado a CRUD)

**Files:**
- Modify: `app/Presentation/Views/admin/crud/index.php:219` (al final, tras `crud-engine.js`)

- [ ] **Step 1: Añadir assets de DataTables + inicialización solo-responsive**

En `index.php`, localizar la última línea (219):

```php
<script src="<?= ViewHelper::asset('js/crud-engine.js') ?>"></script>
```

añadir **debajo** el siguiente bloque. Va envuelto en `<?php if (!empty($rows)): ?>` porque el estado vacío usa un `<td colspan>` que rompería el conteo de columnas de DataTables:

```php
<script src="<?= ViewHelper::asset('js/crud-engine.js') ?>"></script>

<?php if (!empty($rows) && !$grouped): ?>
<!-- DataTables Responsive — solo en listados CRUD (no global). Modo solo-responsive:
     el servidor ya resuelve búsqueda/orden/filtros/paginación/totales. -->
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.3/css/responsive.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/responsive.bootstrap5.min.js"></script>
<script>
(function () {
  function initCrudResponsive() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) return;
    var sel = '#crudTable';
    if (!jQuery(sel).length || jQuery.fn.DataTable.isDataTable(sel)) return;
    jQuery(sel).DataTable({
      responsive: { details: { type: 'inline' } },
      paging: false,
      searching: false,
      info: false,
      ordering: false,
      lengthChange: false,
      autoWidth: false,
      columnDefs: [{ orderable: false, targets: '_all' }]
    });
  }
  if (document.readyState !== 'loading') {
    initCrudResponsive();
  } else {
    document.addEventListener('DOMContentLoaded', initCrudResponsive);
  }
})();
</script>
<?php endif; ?>
```

- [ ] **Step 2: Verificar sintaxis PHP de la vista**

Run: `php -l app/Presentation/Views/admin/crud/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verificación manual en navegador (móvil)**

Levantar el servidor: `php -S localhost:8000 -t public`
Abrir `http://localhost:8000/admin/crud/demo_productos`, iniciar sesión y reducir el ancho de la ventana a <992px (DevTools, modo dispositivo).
Expected:
- La tabla **no** muestra scroll horizontal por defecto.
- Aparece un control de expansión (▶) en las filas; al hacer click se despliega un child-row con las columnas ocultas.
- Las columnas de checkbox y "Acciones" permanecen visibles.
- Búsqueda/orden/filtros/paginación (formulario GET) siguen funcionando al recargar.

> Nota de verificación (riesgo §7.3 del spec): si el control de expansión choca con el checkbox de la primera columna, anotarlo en la checklist del Área D. La mitigación documentada es subir la prioridad del checkbox y dejar el control en la primera columna de datos; no se aplica salvo que la verificación lo exija.

- [ ] **Step 4: Commit**

```bash
git add app/Presentation/Views/admin/crud/index.php
git commit -m "feat(crud): carga DataTables Responsive scopeada al listado CRUD

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Estilos del child-row para tema claro/oscuro

**Files:**
- Modify: `public/assets/css/crud-engine.css` (añadir al final)

- [ ] **Step 1: Añadir overrides de DataTables Responsive**

Al final de `public/assets/css/crud-engine.css`, añadir:

```css

/* ============================================================
   DataTables Responsive — child-row (detalle expandido)
   Respeta variables de tema y data-bs-theme (claro/oscuro).
   ============================================================ */

/* Control de expansión: usa el color primario del tema */
.crud-engine table.dataTable > tbody > tr > td.dtr-control:before,
.crud-engine table.dataTable > tbody > tr.dtr-expanded > td.dtr-control:before {
    background-color: var(--bs-primary, #0d6efd);
    border-color: var(--bs-primary, #0d6efd);
    box-shadow: none;
}

/* Lista de detalle del child-row */
.crud-engine table.dataTable > tbody > tr.child ul.dtr-details {
    width: 100%;
}

.crud-engine table.dataTable > tbody > tr.child ul.dtr-details > li {
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    padding: 0.4rem 0;
}

.crud-engine table.dataTable > tbody > tr.child ul.dtr-details > li:last-child {
    border-bottom: none;
}

.crud-engine table.dataTable > tbody > tr.child span.dtr-title {
    color: var(--bs-secondary-color);
    min-width: 35%;
    font-weight: 600;
}

/* Dark mode: fondo del child-row alineado al cuerpo */
[data-bs-theme="dark"] .crud-engine table.dataTable > tbody > tr.child,
[data-bs-theme="dark"] .crud-engine table.dataTable > tbody > tr.child td {
    background: var(--bs-body-bg, #212529);
}

[data-bs-theme="dark"] .crud-engine table.dataTable > tbody > tr.child ul.dtr-details > li {
    border-bottom-color: var(--bs-border-color-translucent, rgba(255, 255, 255, 0.12));
}
```

- [ ] **Step 2: Verificación manual claro/oscuro**

Con el servidor levantado y el listado CRUD en <992px:
1. Expandir una fila → el detalle se ve con buen contraste en tema claro.
2. Cambiar a tema oscuro (botón de tema). Expandir otra fila → el child-row usa fondo oscuro y separadores tenues; el control (▶) usa el color primario.
Expected: sin texto ilegible ni fondos blancos en oscuro.

- [ ] **Step 3: Commit**

```bash
git add public/assets/css/crud-engine.css
git commit -m "style(crud): child-row de DataTables Responsive respeta el tema

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Ejemplo de `priority` en config + verificación end-to-end del Área C

**Files:**
- Modify: `config/cruds/demo_productos.json:45-82` (arreglo `list.columns`)

- [ ] **Step 1: Declarar `priority` en columnas del demo**

En `config/cruds/demo_productos.json`, dentro de `list.columns`, añadir `"priority"` a tres columnas. Reemplazar el arreglo de columnas (líneas ~45-82) por:

```json
    "columns": [
      {
        "name": "id",
        "label": "ID",
        "sortable": true,
        "priority": 1
      },
      {
        "name": "codigo",
        "label": "Código",
        "searchable": true,
        "sortable": true,
        "priority": 2
      },
      {
        "name": "nombre",
        "label": "Nombre",
        "searchable": true,
        "sortable": true,
        "priority": 2
      },
      {
        "name": "precio_venta",
        "label": "Precio",
        "format": "money",
        "sortable": true
      },
      {
        "name": "stock_actual",
        "label": "Stock",
        "sortable": true
      },
      {
        "name": "status",
        "label": "Estado",
        "badge": {
          "activo": "success",
          "inactivo": "secondary"
        },
        "priority": 3
      }
    ],
```

- [ ] **Step 2: Validar que el JSON es correcto**

Run: `php -r "json_decode(file_get_contents('config/cruds/demo_productos.json'), false, 512, JSON_THROW_ON_ERROR); echo 'JSON OK';"`
Expected: `JSON OK`.

- [ ] **Step 3: Verificación manual del colapso dirigido por prioridad**

Con el servidor levantado, abrir `/admin/crud/demo_productos` en <992px y estrechar progresivamente la ventana.
Expected:
- `ID`, `Código`, `Nombre` (priority 1-2) y la columna `Acciones`/checkbox tardan más en colapsar.
- `Precio` y `Stock` (sin priority → colapsan primero) se ocultan antes y aparecen en el detalle.
- La fila de totales (`tfoot`) sigue mostrando sus valores correctamente.

- [ ] **Step 4: Commit**

```bash
git add config/cruds/demo_productos.json
git commit -m "docs(crud): ejemplo de list.columns[].priority en demo_productos

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Área A — `nav_top` como drawer en móvil (Tareas 6–8)

> **Causa raíz (spec §5.1):** las acciones (tema/estilos/usuario) y los links de menú viven juntos dentro de `#topNavMenu`; el `collapse` de Bootstrap se despliega dentro del alto fijo de `.topnav` (`--topbar-height: 60px`) → recortado. Solución: sacar las acciones del colapso (siempre visibles) y convertir `#topNavMenu` en un drawer lateral en <992px reutilizando el patrón overlay del módulo `Sidebar`.

### Task 6: Reestructurar `nav_top.php` (separar menú de acciones)

**Files:**
- Modify: `app/Presentation/Views/partials/nav_top.php` (reescritura completa del marcado)

- [ ] **Step 1: Reescribir `nav_top.php`**

Reemplazar **todo** el contenido de `app/Presentation/Views/partials/nav_top.php` por:

```php
<?php
use App\Kernel\Helpers\ViewHelper;

$uri = $currentUri ?? (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$menuItems = $menuFiltrado ?? [];
?>

<nav class="navbar navbar-expand-lg topnav ct-topbar shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/admin/dashboard">
            <?php if (!empty($empresaLogo)): ?>
                <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="Logo" height="32">
            <?php else: ?>
                <i class="bi bi-grid-3x3-gap-fill"></i>
            <?php endif; ?>
            <span class="fw-bold"><?= ViewHelper::e($empresaNombre) ?></span>
        </a>

        <!-- Hamburguesa: abre el drawer en móvil (<992px). Wired por NavDrawer en app.js -->
        <button class="navbar-toggler topnav-toggle" type="button"
                id="topNavToggle" aria-controls="topNavMenu"
                aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menú de links: inline en escritorio, drawer lateral en móvil.
             OJO: sin clase `collapse` para poder animar el drawer (translateX). -->
        <div class="navbar-collapse topnav-drawer" id="topNavMenu">
            <ul class="navbar-nav me-auto gap-1">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (!empty($item['submenu'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= str_starts_with($uri, $item['match'] ?? '') ? 'active' : '' ?>"
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                            <?= ViewHelper::e($item['label']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($item['submenu'] as $sub): ?>
                                <li><a class="dropdown-item <?= str_starts_with($uri, $sub['url']) ? 'active' : '' ?>"
                                       href="<?= ViewHelper::e($sub['url']) ?>">
                                    <i class="bi <?= ViewHelper::e($sub['icon'] ?? 'bi-dash') ?> me-2"></i>
                                    <?= ViewHelper::e($sub['label']) ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <?php $topLeafUrl = (string) ($item['url'] ?? ''); ?>
                        <a class="nav-link <?= str_starts_with($uri, $item['match'] ?? $item['url'] ?? '') ? 'active' : '' ?>"
                           href="<?= ViewHelper::e($topLeafUrl !== '' ? $topLeafUrl : '#') ?>">
                            <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                            <?= ViewHelper::e($item['label']) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Acciones: SIEMPRE visibles en la barra superior (fuera del drawer) -->
        <div class="topnav-actions d-flex align-items-center gap-2">
            <button class="btn btn-ghost topbar-btn" id="themeToggle" title="Tema">
                <i class="bi bi-moon-stars"></i>
            </button>
            <button class="btn btn-ghost topbar-btn" id="stylePanelBtn" title="Personalizar interfaz">
                <i class="bi bi-palette"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-ghost topbar-btn d-flex align-items-center gap-2"
                        data-bs-toggle="dropdown">
                    <div class="topbar-avatar"><?= strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1)) ?></div>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header"><?= ViewHelper::e($usuario['nombreCompleto'] ?? '') ?></h6></li>
                    <li><a class="dropdown-item" href="/admin/ajustes"><i class="bi bi-gear me-2"></i>Ajustes</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="/logout" class="m-0" onsubmit="return confirm('¿Cerrar sesión?');">
                            <?= ViewHelper::csrfField() ?>
                            <button type="submit" class="dropdown-item text-danger d-flex align-items-center w-100 border-0 bg-transparent text-start py-2 px-3">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
```

Cambios clave respecto al original:
- El botón hamburguesa tiene `id="topNavToggle"` y **ya no** usa `data-bs-toggle="collapse"` (lo maneja `NavDrawer`).
- `#topNavMenu` perdió la clase `collapse` y ganó `topnav-drawer`; contiene **solo** los links.
- Las acciones se movieron a `.topnav-actions`, fuera de `#topNavMenu`, siempre visibles.

- [ ] **Step 2: Verificar sintaxis PHP**

Run: `php -l app/Presentation/Views/partials/nav_top.php`
Expected: `No syntax errors detected`.

> En este punto el escritorio podría verse algo distinto hasta aplicar el CSS de la Task 7. La verificación visual completa va en la Task 7.

- [ ] **Step 3: Commit**

```bash
git add app/Presentation/Views/partials/nav_top.php
git commit -m "refactor(nav_top): separa links de menú de acciones (drawer móvil)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: CSS del drawer `nav_top` (<992px) y barra horizontal (≥992px)

**Files:**
- Modify: `public/assets/css/app.css:492` (tras el bloque `.layout-top-wrapper`)

- [ ] **Step 1: Añadir reglas del drawer de `nav_top`**

En `public/assets/css/app.css`, localizar el bloque `.layout-top-wrapper` (líneas ~492-494):

```css
.layout-top-wrapper {
  background: var(--bs-body-bg);
}
```

añadir **inmediatamente después**:

```css

/* ── nav_top: barra horizontal en escritorio (≥992px) ──────────
   `.navbar-expand-lg .navbar-collapse` ya despliega el menú inline,
   y `.navbar-expand-lg .navbar-toggler` oculta la hamburguesa. */
.topnav .topnav-actions { flex-shrink: 0; }

/* ── nav_top: drawer lateral en móvil (<992px) ─────────────────
   Reutiliza el overlay `.sidebar-overlay` (creado por NavDrawer en JS). */
@media (max-width: 991.98px) {
  .topnav .topnav-drawer {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sidebar-width);
    max-width: 85vw;
    background: var(--app-navbar-bg, var(--sidebar-bg, #1a1d2e));
    box-shadow: 4px 0 32px rgba(0, 0, 0, 0.25);
    transform: translateX(-100%);
    transition: transform var(--transition-med);
    will-change: transform;
    z-index: 1041;
    overflow-y: auto;
    padding: 1rem 0.75rem;
    align-items: stretch;
  }

  .topnav .topnav-drawer.open {
    transform: translateX(0);
  }

  /* En el drawer los links se apilan verticalmente */
  .topnav .topnav-drawer .navbar-nav {
    flex-direction: column;
    width: 100%;
  }

  /* Dropdowns de menú dentro del drawer: en flujo, no flotantes */
  .topnav .topnav-drawer .dropdown-menu {
    position: static !important;
    transform: none !important;
    border: none;
    background: rgba(255, 255, 255, 0.04);
    box-shadow: none;
    padding-left: 0.5rem;
  }
}
```

- [ ] **Step 2: Verificación manual — escritorio (≥992px)**

Cambiar el layout del panel a "top" (Style Panel → layout, o `config/vertical.php`/ajustes según corresponda) y abrir el panel con ancho ≥992px.
Expected:
- La barra superior se ve igual que antes: links de menú a la izquierda, acciones (tema/estilos/usuario) a la derecha.
- La hamburguesa **no** aparece.
- Los dropdowns de submenú funcionan como antes.

- [ ] **Step 3: Verificación manual — móvil (<992px)**

Reducir a <992px en el layout "top".
Expected:
- Aparece la hamburguesa; los links del menú **no** se ven inline.
- Las acciones (tema/estilos/usuario) siguen visibles en la barra.
- (El click de la hamburguesa aún no abre nada hasta la Task 8.)

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/app.css
git commit -m "style(nav_top): drawer lateral en móvil, barra horizontal en escritorio

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Módulo `NavDrawer` en `app.js` (abrir/cerrar drawer de `nav_top`)

**Files:**
- Modify: `public/assets/js/app.js` (nuevo módulo + registro en init + export)

- [ ] **Step 1: Añadir el módulo `NavDrawer`**

En `public/assets/js/app.js`, insertar el siguiente módulo **antes** del bloque `INICIALIZACIÓN GLOBAL` (antes de `document.addEventListener('DOMContentLoaded', ...)`, alrededor de la línea 866):

```javascript
/* ============================================================
   MÓDULO: NavDrawer — drawer de nav_top en móvil (<992px)
   Reutiliza el overlay `.sidebar-overlay` (mismo estilo que el sidebar).
   ============================================================ */
const NavDrawer = (() => {
  function init() {
    const toggle = document.getElementById('topNavToggle');
    const drawer = document.getElementById('topNavMenu');
    if (!toggle || !drawer) return;

    // Reutiliza el overlay del sidebar si ya existe; si no, lo crea.
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      document.body.appendChild(overlay);
    }

    function open() {
      drawer.classList.add('open');
      overlay.classList.add('active');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }

    function close() {
      drawer.classList.remove('open');
      overlay.classList.remove('active');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', () => {
      drawer.classList.contains('open') ? close() : open();
    });

    overlay.addEventListener('click', close);

    // Cerrar al navegar desde un link del drawer
    drawer.querySelectorAll('a.nav-link:not(.dropdown-toggle), a.dropdown-item').forEach(a => {
      a.addEventListener('click', close);
    });

    // Cerrar con Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && drawer.classList.contains('open')) close();
    });
  }

  return { init };
})();
```

- [ ] **Step 2: Registrar `NavDrawer.init()` en la inicialización global**

Localizar el bloque `DOMContentLoaded` (línea ~870). Tras `BottomNav.init();` añadir `NavDrawer.init();`:

```javascript
  BottomNav.init();
  NavDrawer.init();
  AlertManager.init();
```

- [ ] **Step 3: Exportar `NavDrawer` en `window.App`**

Localizar el objeto export (línea ~907) y añadir `NavDrawer`:

```javascript
window.App = {
  Sidebar,
  ThemeToggle,
  StylePanel,
  ColorPicker,
  AjustesAccordion,
  BottomNav,
  NavDrawer,
  CsrfFetch,
  ConfirmModal,
};
```

- [ ] **Step 4: Verificación manual — drawer funcional (móvil, layout top)**

Servidor levantado, layout "top", ancho <992px:
Expected:
- Click en hamburguesa → entra el drawer desde la izquierda con overlay oscuro.
- Click en overlay / `Escape` / click en un link → cierra el drawer.
- Los dropdowns de submenú dentro del drawer se abren (en flujo, no flotantes).
- En ≥992px la hamburguesa no aparece y nada del drawer interfiere.

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/app.js
git commit -m "feat(nav_top): NavDrawer abre/cierra el menú móvil con overlay y Escape

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Área B — `nav_bottom` + fallback de escritorio (Tareas 9–10)

> **Causa raíz (spec §5.2):** la bottombar es `d-md-none` y el layout `bottom` no renderiza navegación para escritorio → sin menú en pantallas anchas. Solución: en el layout `bottom` renderizar `nav_top` (solo escritorio) **y** `nav_bottom` (solo móvil), y alinear el breakpoint de la bottombar a 992px.

### Task 9: Layout `bottom` renderiza `nav_top` (escritorio) + `nav_bottom` (móvil)

**Files:**
- Modify: `app/Presentation/Views/layouts/base.php:73-82` (rama `MENU_LAYOUT_BOTTOM`)

- [ ] **Step 1: Reescribir la rama `bottom` de `base.php`**

En `app/Presentation/Views/layouts/base.php`, localizar la rama (líneas ~73-82):

```php
<?php elseif (($menuLayout ?? '') === AppConstants::MENU_LAYOUT_BOTTOM): ?>
    <div class="layout-bottom-wrapper">
        <main class="main-content container-fluid">
            <?= ViewHelper::partial('flash_alerts', compact('flashAll')) ?>
            <?= ViewHelper::partial('breadcrumb', ['titulo' => $titulo ?? '']) ?>
            <?= $content ?? '' ?>
        </main>
        <?= ViewHelper::partial('footer') ?>
    </div>
    <?= ViewHelper::partial('nav_bottom', compact('usuario', 'empresaNombre', 'menuFiltrado', 'currentUri')) ?>
```

reemplazar por:

```php
<?php elseif (($menuLayout ?? '') === AppConstants::MENU_LAYOUT_BOTTOM): ?>
    <!-- Escritorio (≥992px): barra superior como fallback de navegación -->
    <div class="d-none d-lg-block">
        <?= ViewHelper::partial('nav_top', compact('usuario', 'empresaNombre', 'empresaLogo', 'menuFiltrado', 'currentUri')) ?>
    </div>
    <div class="layout-bottom-wrapper">
        <main class="main-content container-fluid">
            <?= ViewHelper::partial('flash_alerts', compact('flashAll')) ?>
            <?= ViewHelper::partial('breadcrumb', ['titulo' => $titulo ?? '']) ?>
            <?= $content ?? '' ?>
        </main>
        <?= ViewHelper::partial('footer') ?>
    </div>
    <!-- Móvil (<992px): barra inferior fija -->
    <?= ViewHelper::partial('nav_bottom', compact('usuario', 'empresaNombre', 'menuFiltrado', 'currentUri')) ?>
```

- [ ] **Step 2: Verificar sintaxis PHP**

Run: `php -l app/Presentation/Views/layouts/base.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Presentation/Views/layouts/base.php
git commit -m "feat(layout): rama bottom renderiza nav_top en escritorio

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 10: Alinear breakpoint de `nav_bottom` a 992px

**Files:**
- Modify: `app/Presentation/Views/partials/nav_bottom.php:20,30,55` (`d-md-none` → `d-lg-none`)
- Modify: `public/assets/css/app.css:675-679` (media query 768px → 992px)

- [ ] **Step 1: Cambiar `d-md-none` → `d-lg-none` en `nav_bottom.php`**

En `app/Presentation/Views/partials/nav_bottom.php` hay tres ocurrencias de `d-md-none`. Reemplazar las tres por `d-lg-none`:

Línea ~20 (panel de submenú):
```php
<div id="bottomnavSubPanel" class="bottomnav-sub-panel d-lg-none" role="dialog" aria-label="Submenú" hidden>
```

Línea ~30 (panel "más"):
```php
<div id="bottomnavMorePanel" class="bottomnav-more-panel d-lg-none" role="dialog" aria-label="Más navegación">
```

Línea ~55 (barra inferior):
```php
<nav class="bottomnav ct-bottombar d-flex d-lg-none fixed-bottom">
```

> Usar `Edit` con `replace_all` en `d-md-none` → `d-lg-none` dentro de este archivo cubre las tres a la vez.

- [ ] **Step 2: Alinear el media query del padding del wrapper a 992px**

En `public/assets/css/app.css`, localizar (líneas ~674-679):

```css
/* En pantallas md+ la bottom nav se oculta → quitar el padding extra */
@media (min-width: 768px) {
  .layout-bottom-wrapper {
    padding-bottom: 0;
  }
}
```

reemplazar por:

```css
/* En pantallas lg+ la bottom nav se oculta → quitar el padding extra */
@media (min-width: 992px) {
  .layout-bottom-wrapper {
    padding-bottom: 0;
  }
}
```

- [ ] **Step 3: Verificar sintaxis PHP**

Run: `php -l app/Presentation/Views/partials/nav_bottom.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Verificación manual — layout bottom en ambos tamaños**

Servidor levantado, layout "bottom":
- **<992px:** se ve la barra inferior; navega; panel "Más" y submenús funcionan; el contenido no queda tapado por la barra fija (hay padding inferior).
- **≥992px:** la barra inferior está oculta; se ve la barra superior (`nav_top`) y navega; sin padding inferior sobrante.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/partials/nav_bottom.php public/assets/css/app.css
git commit -m "fix(nav_bottom): alinea el breakpoint móvil a 992px (lg)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Área D — Documentación y verificación (Tarea 11)

### Task 11: Documentar comportamiento responsive y el campo `priority`

**Files:**
- Modify: `docs/core/ui_ux.md` (sección de navegación responsive)
- Modify: doc del CRUD Engine (campo `priority`) — ver Step 2 para localizar el archivo

- [ ] **Step 1: Documentar las 3 navegaciones en `docs/core/ui_ux.md`**

Añadir al final de `docs/core/ui_ux.md` la siguiente sección:

```markdown

## Navegación responsive (breakpoint único: 992px / `lg`)

Las tres navegaciones cambian de modo móvil↔escritorio en **992px** (`lg` de Bootstrap). "Responsive" significa lo mismo en todo el panel.

| Layout | < 992px (móvil) | ≥ 992px (escritorio) |
|---|---|---|
| `side` | Sidebar como drawer (botón en `topbar`), overlay + Escape | Sidebar fijo, colapsable a iconos |
| `top` | `nav_top` se vuelve drawer lateral (hamburguesa `#topNavToggle`); las acciones (tema/estilos/usuario) quedan siempre en la barra | Barra horizontal con dropdowns (sin cambios) |
| `bottom` | Barra inferior fija (`nav_bottom`) con panel "Más" y submenús | Fallback a barra superior `nav_top`; la bottombar se oculta |

Detalles de implementación:
- El drawer de `nav_top` reutiliza el overlay `.sidebar-overlay` y el módulo `NavDrawer` (`public/assets/js/app.js`).
- `#topNavMenu` contiene **solo** los links de menú (sin la clase `collapse`, para animar el drawer con `translateX`); las acciones viven en `.topnav-actions`, fuera del drawer.
- En el layout `bottom`, `base.php` renderiza `nav_top` envuelto en `d-none d-lg-block` y `nav_bottom` con `d-lg-none`.
- El `padding-bottom` del contenido en layout `bottom` se retira en `@media (min-width: 992px)`.
```

- [ ] **Step 2: Localizar y actualizar la doc del CRUD Engine para `priority`**

Run: `grep -rl "list.columns\|columns\[\]\|priority\|sortable" docs/modules/crud/ 2>/dev/null; ls docs/modules/crud/`
Elegir el archivo que documenta las columnas de listado (p. ej. el de configuración del recurso). Añadir esta subsección:

```markdown

### Columnas responsive: `list.columns[].priority`

Campo **opcional** (entero) por columna. Controla qué columnas permanecen visibles cuando la tabla colapsa en móvil (DataTables Responsive, solo en `crud/index.php`).

- **Convención DataTables:** menor número = mayor prioridad (más probable que siga visible). Las columnas sin `priority` colapsan primero, por orden de aparición.
- **Defaults aplicados por el motor** cuando no se declara `priority`:
  - Columna de checkbox de selección → siempre visible (`data-priority="1"`).
  - Columna de **Acciones** → siempre visible (`data-priority="1"`).
  - 1ª columna de datos → prioridad alta por defecto (`data-priority="2"`).
- Las columnas ocultas se muestran como detalle al hacer click en la fila.

Ejemplo (`config/cruds/demo_productos.json`):

\```json
"columns": [
  { "name": "id",     "label": "ID",     "sortable": true, "priority": 1 },
  { "name": "codigo", "label": "Código", "sortable": true, "priority": 2 },
  { "name": "nombre", "label": "Nombre", "sortable": true, "priority": 2 },
  { "name": "precio_venta", "label": "Precio", "format": "money", "sortable": true },
  { "name": "stock_actual", "label": "Stock", "sortable": true },
  { "name": "status", "label": "Estado", "priority": 3 }
]
\```

> El flujo server-side (búsqueda, orden, filtros, paginación, totales) no cambia: DataTables se inicializa en modo solo-responsive (`paging/searching/info/ordering/lengthChange: false`).
```

- [ ] **Step 3: Verificar que la documentación quedó coherente**

Run: `grep -n "992\|priority\|NavDrawer" docs/core/ui_ux.md`
Expected: las nuevas referencias aparecen.

- [ ] **Step 4: Commit**

```bash
git add docs/core/ui_ux.md docs/modules/crud/
git commit -m "docs(ui): navegación responsive 992px y campo list.columns[].priority

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Checklist de verificación manual / E2E final (spec §6)

Tras completar todas las tareas, recorrer esta lista en navegador (DevTools, alternando <992px y ≥992px, y tema claro/oscuro):

- [ ] **Layout `side`** — móvil: drawer del sidebar abre/cierra (overlay + Escape). Escritorio: sidebar fijo y colapsable. Sin regresión.
- [ ] **Layout `top`** — móvil: hamburguesa abre el drawer con los links; acciones visibles; cierra con overlay/Escape/link. Escritorio: barra horizontal idéntica con dropdowns.
- [ ] **Layout `bottom`** — móvil: barra inferior + "Más" + submenús; contenido no tapado. Escritorio: barra superior `nav_top`; bottombar oculta.
- [ ] **Tabla CRUD (`/admin/crud/demo_productos`) móvil** — sin scroll horizontal por defecto; click en fila despliega detalle; columnas con `priority` y checkbox/Acciones visibles; totales (`tfoot`) correctos.
- [ ] **Acciones por fila desde el detalle expandido** — editar/eliminar/transiciones disparan el `#confirmModal` y se ejecutan (CSRF OK) también cuando la fila está colapsada.
- [ ] **Tema claro/oscuro** — child-row y controles de DataTables respetan `data-bs-theme` en todos los anteriores.
- [ ] **Sin regresión server-side** — búsqueda, orden, filtros, paginación y totales del CRUD siguen funcionando vía formulario GET.

---

## Self-Review (cobertura del spec)

- **§5.1 Área A (nav_top drawer):** Tasks 6 (markup), 7 (CSS ≥/<992px), 8 (NavDrawer JS). ✔
- **§5.2 Área B (nav_bottom + fallback escritorio):** Tasks 9 (base.php renderiza ambas), 10 (breakpoint 992px + padding). ✔
- **§5.3 Área C (DataTables Responsive):** Tasks 1 (priority en builder, TDD), 2 (data-priority render), 3 (carga+init scopeada solo-responsive), 4 (dark mode child-row), 5 (ejemplo config). ✔
- **§5.4 / §6 Área D (docs + verificación):** Task 11 + checklist final. ✔
- **§4.1 breakpoint único 992px:** Tasks 7, 9, 10. ✔
- **§4.2 carga scopeada de DataTables:** Task 3 (envuelto en `if (!empty($rows) && !$grouped)`, solo en `crud/index.php`). ✔
- **§4.3 modo solo-responsive:** Task 3 (`paging/searching/info/ordering/lengthChange:false`). ✔
- **§4.4 / §5.3.3 columnas prioritarias + defaults:** Tasks 1, 2 (default: 1ª col=2, checkbox/acciones=1). ✔
- **§7 riesgos:** tfoot (conteo de columnas verificado: thead/tfoot coinciden con/ sin `selectable`), jQuery scopeado, child-row CSRF (delegación a nivel `document` en `ConfirmForms`/`ConfirmModal`, verificación manual en checklist), markup desktop ≥992px conservado vía `.navbar-expand-lg`. ✔

**Consistencia de nombres:** `#topNavToggle` (botón), `#topNavMenu`/`.topnav-drawer` (drawer), `.topnav-actions` (acciones), `NavDrawer` (módulo JS), `#crudTable` (tabla), `data-priority` (atributo) — usados de forma idéntica en todas las tareas.
```
