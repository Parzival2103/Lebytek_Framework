# Módulo Calendario — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir un módulo opcional de Calendario que renderiza recursos CRUD existentes como vistas de calendario (mensual/semanal/diaria/tabla) más un widget mini-calendario para el dashboard, sin perder la marca Lebytek.

**Architecture:** Capa de solo-lectura sobre el CRUD Engine. Un calendario se define en `config/calendars/{key}.json` y referencia un recurso CRUD por su `key`; hereda tabla, permisos, scope y formularios. Un caso de uso consulta filas por rango vía el repositorio CRUD (con scope aplicado), las normaliza a eventos y las sirve como JSON a un componente JS propio. La edición reutiliza los endpoints CRUD existentes. El dashboard se extiende con un slot `widgets` retrocompatible.

**Tech Stack:** PHP 8.1+ (cebolla 5 capas), vanilla JS + Bootstrap 5, tests con el micro-harness propio (`tests/run.php` + `tests/lib/microtest.php`), JSON config.

**Spec:** `docs/superpowers/specs/2026-06-09-modulo-calendario-design.md`

---

## Pre-flight (leer antes de empezar)

Antes de la Fase 1, el ejecutor DEBE leer estos archivos para aprender convenciones exactas (firmas, helpers, accesores) que este plan referencia:

- `app/Application/Services/CrudConfigLoader.php` — patrón de carga/caché de JSON (lo replicaremos).
- `app/Application/Services/CrudConfigValidator.php` — patrón de validación y forma de `ValidationException` (`getErrors()`).
- `app/Domain/Entities/CrudResourceDefinition.php` — `fromArray()` y accesores; identificar cómo obtener: nombre de tabla, `permission_prefix`, primary key, nombres de columnas conocidas (list columns + form fields) y bloque `states`. Si no existe un accesor que liste nombres de columnas, se añadirá `columnNames(): array` en la Tarea 1.4.
- `app/Infrastructure/Repositories/GenericCrudRepository.php` — métodos `select*` existentes (firma de conexión PDO, construcción de `WHERE`, binding). La Tarea 2.x añade un método de consulta por rango siguiendo el mismo estilo.
- `app/Application/Services/CrudResourceService.php` — cómo `buildIndexData()` aplica scope (`CrudScopeResolver`) y permisos; reutilizaremos ese mecanismo para el feed de eventos.
- `app/Presentation/Controllers/AdminBaseController.php` y `app/Kernel/BaseClasses/BaseController.php` — `view()`, `currentUser()`, `redirect*`, `verifyCsrf()`, helper de permisos.
- `config/container.php` — patrón de binding de servicios y controladores (buscar las entradas de `CrudController`, `CrudConfigLoader`, `CrudResourceService`).
- `app/Presentation/Controllers/Admin/DashboardController.php`, `app/Application/DTO/Dashboard/DashboardViewModel.php`, `app/Presentation/Views/admin/dashboard/index.php` y `app/Domain/Dashboard/DashboardBuildContext.php` — para la Fase 4 (widget).
- `tests/lib/microtest.php` — funciones `test()`, `assert_same()`, `assert_true()`, `assert_throws()` (confirmar nombres exactos disponibles).

Comando de pruebas (todo el plan): `php tests/run.php <filtro>` desde la raíz del repo. Sin filtro corre todo.

---

## File Structure

**Nuevos archivos:**

| Archivo | Responsabilidad |
|---|---|
| `app/Domain/Calendar/DateRange.php` | Value object de rango de fechas + factories por vista. |
| `app/Domain/Calendar/CalendarEvent.php` | Value object de evento normalizado (`toArray()` para JSON). |
| `app/Domain/Entities/CalendarDefinition.php` | Definición inmutable de un calendario (`fromArray()` + accesores). |
| `app/Application/Services/CalendarConfigValidator.php` | Valida el contrato JSON del calendario. |
| `app/Application/Services/CalendarConfigLoader.php` | Carga/cachea `config/calendars/*.json` + resuelve recurso CRUD. |
| `app/Application/Services/CalendarEventMapper.php` | Fila → `CalendarEvent` (all-day, color, plantilla título, url). |
| `app/Application/UseCases/Calendar/ListarEventosCalendarioUseCase.php` | Orquesta rango + scope + mapeo. |
| `app/Application/Services/CalendarViewModelBuilder.php` | Datos del shell (vistas, filtros, leyenda, capacidades RBAC). |
| `app/Presentation/Controllers/Admin/CalendarioController.php` | `index` (shell) + `events` (JSON). |
| `app/Presentation/Views/admin/calendario/index.php` | Shell: toolbar + contenedor + leyenda + estado vacío. |
| `app/Presentation/Views/partials/dashboard/calendar_mini.php` | Partial del widget mini-calendario. |
| `app/Infrastructure/Dashboard/CalendarDashboardProvider.php` | Proveedor de contribución del widget. |
| `public/assets/js/calendar.js` | Render de los 4 layouts, fetch, navegación, popover. |
| `config/calendars/demo_citas.json` | Calendario demo. |
| `config/cruds/demo_citas.json` | Recurso CRUD demo (tabla con fechas). |
| `config/modules/calendario.php` | Manifiesto del módulo. |
| `database/schema/modules/calendario.sql` | Bootstrap de la tabla demo + permisos + menú. |
| `tests/Calendar/*` | Pruebas unitarias e integración. |

**Modificados:**

| Archivo | Cambio |
|---|---|
| `app/Domain/Dashboard/DashboardContribution.php` | Añadir slot `widgets` (retrocompatible). |
| `app/Application/UseCases/Dashboard/BuildDashboardViewModelUseCase.php` | Fusionar `widgets`. |
| `app/Application/DTO/Dashboard/DashboardViewModel.php` | Exponer `widgets`. |
| `app/Presentation/Views/admin/dashboard/index.php` | Renderizar `widgets`. |
| `config/container.php` | Bindings de servicios + `CalendarioController`. |
| `config/dashboard.php` | Registrar `CalendarDashboardProvider`. |
| `routes/web.php` | Rutas `/admin/calendario/{key}` y `/eventos`. |
| `public/assets/css/lebytek-ui.css` | Estilos del calendario. |
| `config/vertical.php` | Toggle `modules.calendario` (vía instalación). |

---

# FASE 1 — Núcleo de lectura (config + eventos + vista mensual + tabla)

## Task 1.1: `DateRange` value object

**Files:**
- Create: `app/Domain/Calendar/DateRange.php`
- Test: `tests/Calendar/DateRangeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Domain\Calendar\DateRange;

test('DateRange::forMonth abarca el mes completo', function (): void {
    $r = DateRange::forMonth(2026, 6);
    assert_same('2026-06-01 00:00:00', $r->from()->format('Y-m-d H:i:s'), 'inicio de mes');
    assert_same('2026-06-30 23:59:59', $r->to()->format('Y-m-d H:i:s'), 'fin de mes');
});

test('DateRange::forDay abarca un solo día', function (): void {
    $r = DateRange::forDay(new DateTimeImmutable('2026-06-09 14:00:00'));
    assert_same('2026-06-09 00:00:00', $r->from()->format('Y-m-d H:i:s'), 'inicio del día');
    assert_same('2026-06-09 23:59:59', $r->to()->format('Y-m-d H:i:s'), 'fin del día');
});

test('DateRange::forWeek abarca lunes a domingo', function (): void {
    $r = DateRange::forWeek(new DateTimeImmutable('2026-06-09')); // martes
    assert_same('2026-06-08', $r->from()->format('Y-m-d'), 'lunes');
    assert_same('2026-06-14', $r->to()->format('Y-m-d'), 'domingo');
});

test('DateRange::fromStrings parsea desde/hasta y normaliza límites', function (): void {
    $r = DateRange::fromStrings('2026-06-01', '2026-06-30');
    assert_same('2026-06-01 00:00:00', $r->from()->format('Y-m-d H:i:s'), 'desde 00:00');
    assert_same('2026-06-30 23:59:59', $r->to()->format('Y-m-d H:i:s'), 'hasta 23:59:59');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Calendar/DateRangeTest`
Expected: FAIL ("Class App\Domain\Calendar\DateRange not found").

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Calendar;

use DateTimeImmutable;

final class DateRange
{
    public function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to,
    ) {}

    public function from(): DateTimeImmutable { return $this->from; }
    public function to(): DateTimeImmutable { return $this->to; }

    public static function forMonth(int $year, int $month): self
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $last  = $first->modify('last day of this month')->setTime(23, 59, 59);
        return new self($first, $last);
    }

    public static function forDay(DateTimeImmutable $day): self
    {
        return new self($day->setTime(0, 0, 0), $day->setTime(23, 59, 59));
    }

    public static function forWeek(DateTimeImmutable $anchor): self
    {
        $monday = $anchor->modify(($anchor->format('N') === '1') ? 'today' : 'last monday')->setTime(0, 0, 0);
        $sunday = $monday->modify('+6 days')->setTime(23, 59, 59);
        return new self($monday, $sunday);
    }

    public static function fromStrings(string $from, string $to): self
    {
        $f = (new DateTimeImmutable($from))->setTime(0, 0, 0);
        $t = (new DateTimeImmutable($to))->setTime(23, 59, 59);
        return new self($f, $t);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Calendar/DateRangeTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Calendar/DateRange.php tests/Calendar/DateRangeTest.php
git commit -m "feat(calendario): DateRange value object con factories por vista"
```

---

## Task 1.2: `CalendarEvent` value object

**Files:**
- Create: `app/Domain/Calendar/CalendarEvent.php`
- Test: `tests/Calendar/CalendarEventTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Domain\Calendar\CalendarEvent;

test('CalendarEvent::toArray expone forma JSON estable', function (): void {
    $e = new CalendarEvent(
        id: 7, title: 'Cita López', start: '2026-06-09 10:00:00',
        end: '2026-06-09 11:00:00', allDay: false, color: 'success',
        url: '/admin/crud/demo_citas/7'
    );
    assert_same(
        ['id' => 7, 'title' => 'Cita López', 'start' => '2026-06-09 10:00:00',
         'end' => '2026-06-09 11:00:00', 'allDay' => false, 'color' => 'success',
         'url' => '/admin/crud/demo_citas/7'],
        $e->toArray(),
        'forma JSON'
    );
});

test('CalendarEvent admite end nulo', function (): void {
    $e = new CalendarEvent(id: 1, title: 'X', start: '2026-06-09', end: null,
        allDay: true, color: 'primary', url: '/x');
    assert_same(null, $e->toArray()['end'], 'end nulo se preserva');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Calendar/CalendarEventTest`
Expected: FAIL (clase no encontrada).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Calendar;

final class CalendarEvent
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $start,
        public readonly ?string $end,
        public readonly bool $allDay,
        public readonly string $color,
        public readonly string $url,
    ) {}

    /** @return array{id:int,title:string,start:string,end:?string,allDay:bool,color:string,url:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'start' => $this->start,
            'end' => $this->end, 'allDay' => $this->allDay, 'color' => $this->color,
            'url' => $this->url,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Calendar/CalendarEventTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Calendar/CalendarEvent.php tests/Calendar/CalendarEventTest.php
git commit -m "feat(calendario): CalendarEvent value object"
```

---

## Task 1.3: `CalendarDefinition` entity (`fromArray` + accesores)

**Files:**
- Create: `app/Domain/Entities/CalendarDefinition.php`
- Test: `tests/Calendar/CalendarDefinitionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Domain\Entities\CalendarDefinition;

function cd_sample(): array
{
    return [
        'calendar' => ['key' => 'citas', 'title' => 'Agenda de Citas', 'resource' => 'demo_citas', 'icon' => 'bi-calendar-event'],
        'mapping' => [
            'start' => 'fecha_inicio', 'end' => 'fecha_fin', 'all_day' => false,
            'title' => '{cliente} — {servicio}',
            'color' => ['by' => 'estado', 'map' => ['pendiente' => 'warning', 'confirmada' => 'success']],
        ],
        'views' => ['default' => 'month', 'enabled' => ['month', 'week', 'day', 'table']],
        'interaction' => ['create_on_slot' => true, 'open_detail' => true, 'edit_from_event' => true],
        'filters' => [['field' => 'estado', 'label' => 'Estado']],
        'dashboard_widget' => true,
    ];
}

test('CalendarDefinition::fromArray expone accesores', function (): void {
    $d = CalendarDefinition::fromArray(cd_sample());
    assert_same('citas', $d->key(), 'key');
    assert_same('demo_citas', $d->resource(), 'resource');
    assert_same('fecha_inicio', $d->mappingStart(), 'start');
    assert_same('fecha_fin', $d->mappingEnd(), 'end');
    assert_same(false, $d->mappingAllDay(), 'all_day');
    assert_same('{cliente} — {servicio}', $d->mappingTitle(), 'title');
    assert_same('estado', $d->colorBy(), 'color.by');
    assert_same('success', $d->colorMap()['confirmada'] ?? null, 'color.map');
    assert_same('month', $d->viewsDefault(), 'default view');
    assert_same(['month', 'week', 'day', 'table'], $d->viewsEnabled(), 'enabled views');
    assert_true($d->dashboardWidget(), 'dashboard_widget');
});

test('CalendarDefinition aplica defaults cuando faltan opcionales', function (): void {
    $d = CalendarDefinition::fromArray([
        'calendar' => ['key' => 'k', 'title' => 'T', 'resource' => 'r'],
        'mapping' => ['start' => 'fecha'],
        'views' => ['default' => 'month', 'enabled' => ['month']],
    ]);
    assert_same(null, $d->mappingEnd(), 'end por defecto null');
    assert_same('fixed', $d->colorBy(), 'color.by por defecto fixed');
    assert_same(false, $d->dashboardWidget(), 'dashboard_widget por defecto false');
    assert_same([], $d->filters(), 'filters por defecto vacío');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Calendar/CalendarDefinitionTest`
Expected: FAIL (clase no encontrada).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class CalendarDefinition
{
    /**
     * @param list<array{field:string,label:string}> $filters
     * @param array<string,string> $colorMap
     */
    private function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $resource,
        private readonly string $icon,
        private readonly string $mappingStart,
        private readonly ?string $mappingEnd,
        private readonly ?bool $mappingAllDay,
        private readonly string $mappingTitle,
        private readonly string $colorBy,
        private readonly array $colorMap,
        private readonly string $colorFixed,
        private readonly string $viewsDefault,
        private readonly array $viewsEnabled,
        private readonly bool $createOnSlot,
        private readonly bool $openDetail,
        private readonly bool $editFromEvent,
        private readonly array $filters,
        private readonly bool $dashboardWidget,
    ) {}

    public static function fromArray(array $c): self
    {
        $cal = $c['calendar'] ?? [];
        $map = $c['mapping'] ?? [];
        $color = $map['color'] ?? [];
        $views = $c['views'] ?? [];
        $inter = $c['interaction'] ?? [];

        return new self(
            key:           (string)($cal['key'] ?? ''),
            title:         (string)($cal['title'] ?? ''),
            resource:      (string)($cal['resource'] ?? ''),
            icon:          (string)($cal['icon'] ?? 'bi-calendar3'),
            mappingStart:  (string)($map['start'] ?? ''),
            mappingEnd:    isset($map['end']) && $map['end'] !== '' ? (string)$map['end'] : null,
            mappingAllDay: array_key_exists('all_day', $map) ? (bool)$map['all_day'] : null,
            mappingTitle:  (string)($map['title'] ?? ''),
            colorBy:       (string)($color['by'] ?? 'fixed'),
            colorMap:      is_array($color['map'] ?? null) ? $color['map'] : [],
            colorFixed:    (string)($color['value'] ?? 'primary'),
            viewsDefault:  (string)($views['default'] ?? 'month'),
            viewsEnabled:  array_values(array_map('strval', $views['enabled'] ?? ['month'])),
            createOnSlot:  (bool)($inter['create_on_slot'] ?? false),
            openDetail:    (bool)($inter['open_detail'] ?? true),
            editFromEvent: (bool)($inter['edit_from_event'] ?? false),
            filters:       array_values($c['filters'] ?? []),
            dashboardWidget: (bool)($c['dashboard_widget'] ?? false),
        );
    }

    public function key(): string { return $this->key; }
    public function title(): string { return $this->title; }
    public function resource(): string { return $this->resource; }
    public function icon(): string { return $this->icon; }
    public function mappingStart(): string { return $this->mappingStart; }
    public function mappingEnd(): ?string { return $this->mappingEnd; }
    public function mappingAllDay(): ?bool { return $this->mappingAllDay; }
    public function mappingTitle(): string { return $this->mappingTitle; }
    public function colorBy(): string { return $this->colorBy; }
    public function colorMap(): array { return $this->colorMap; }
    public function colorFixed(): string { return $this->colorFixed; }
    public function viewsDefault(): string { return $this->viewsDefault; }
    public function viewsEnabled(): array { return $this->viewsEnabled; }
    public function createOnSlot(): bool { return $this->createOnSlot; }
    public function openDetail(): bool { return $this->openDetail; }
    public function editFromEvent(): bool { return $this->editFromEvent; }
    public function filters(): array { return $this->filters; }
    public function dashboardWidget(): bool { return $this->dashboardWidget; }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Calendar/CalendarDefinitionTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/CalendarDefinition.php tests/Calendar/CalendarDefinitionTest.php
git commit -m "feat(calendario): CalendarDefinition entity con fromArray y accesores"
```

---

## Task 1.4: `CalendarConfigValidator`

**Files:**
- Create: `app/Application/Services/CalendarConfigValidator.php`
- Test: `tests/Calendar/CalendarConfigValidatorTest.php`
- Read first: `app/Application/Services/CrudConfigValidator.php` (forma de `ValidationException::getErrors()`).

El validador recibe el array de config **y** la lista de columnas disponibles del recurso CRUD (las pasa el loader). Valida estructura + existencia de columnas de `mapping` en esa lista.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Application\Services\CalendarConfigValidator;
use App\Domain\Exceptions\ValidationException;

function cv_cols(): array { return ['id', 'cliente', 'servicio', 'estado', 'fecha_inicio', 'fecha_fin']; }

function cv_valid(): array
{
    return [
        'calendar' => ['key' => 'citas', 'title' => 'Agenda', 'resource' => 'demo_citas'],
        'mapping' => ['start' => 'fecha_inicio', 'end' => 'fecha_fin', 'title' => '{cliente}',
                      'color' => ['by' => 'estado', 'map' => ['pendiente' => 'warning']]],
        'views' => ['default' => 'month', 'enabled' => ['month', 'table']],
    ];
}

test('CalendarConfigValidator acepta config válida', function (): void {
    (new CalendarConfigValidator())->validate(cv_valid(), cv_cols());
    assert_true(true, 'no lanzó excepción');
});

test('CalendarConfigValidator rechaza columna start inexistente', function (): void {
    $cfg = cv_valid();
    $cfg['mapping']['start'] = 'no_existe';
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});

test('CalendarConfigValidator rechaza vista default fuera de enabled', function (): void {
    $cfg = cv_valid();
    $cfg['views']['default'] = 'week';
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});

test('CalendarConfigValidator rechaza vista no soportada', function (): void {
    $cfg = cv_valid();
    $cfg['views']['enabled'] = ['month', 'galaxia'];
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});

test('CalendarConfigValidator rechaza color.by=field sin field válido', function (): void {
    $cfg = cv_valid();
    $cfg['mapping']['color'] = ['by' => 'field', 'field' => 'no_existe'];
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});
```

> Si `assert_throws` no existe en `tests/lib/microtest.php`, usar el patrón try/catch que ya empleen otros tests de validación (revisar `tests/Crud/*` para el helper real) y ajustar estas 4 aserciones en consecuencia.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Calendar/CalendarConfigValidatorTest`
Expected: FAIL (clase no encontrada).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\ValidationException;

final class CalendarConfigValidator
{
    private const VALID_VIEWS = ['month', 'week', 'day', 'table'];
    private const VALID_COLOR_BY = ['estado', 'field', 'fixed'];

    /**
     * @param array<string,mixed> $config
     * @param list<string> $availableColumns columnas conocidas del recurso CRUD
     */
    public function validate(array $config, array $availableColumns): void
    {
        $errors = [];

        $cal = $config['calendar'] ?? null;
        if (!is_array($cal) || ($cal['key'] ?? '') === '' || ($cal['resource'] ?? '') === '') {
            $errors[] = 'calendar.key y calendar.resource son obligatorios.';
        }

        $map = $config['mapping'] ?? [];
        $start = (string)($map['start'] ?? '');
        if ($start === '') {
            $errors[] = 'mapping.start es obligatorio.';
        } elseif (!in_array($start, $availableColumns, true)) {
            $errors[] = "mapping.start ('{$start}') no existe en el recurso.";
        }

        $end = $map['end'] ?? null;
        if ($end !== null && $end !== '' && !in_array((string)$end, $availableColumns, true)) {
            $errors[] = "mapping.end ('{$end}') no existe en el recurso.";
        }

        $color = $map['color'] ?? [];
        $by = (string)($color['by'] ?? 'fixed');
        if (!in_array($by, self::VALID_COLOR_BY, true)) {
            $errors[] = "mapping.color.by inválido ('{$by}').";
        }
        if ($by === 'field' && !in_array((string)($color['field'] ?? ''), $availableColumns, true)) {
            $errors[] = 'mapping.color.by=field requiere un field existente.';
        }

        $views = $config['views'] ?? [];
        $enabled = $views['enabled'] ?? [];
        if (!is_array($enabled) || $enabled === []) {
            $errors[] = 'views.enabled debe listar al menos una vista.';
        } else {
            foreach ($enabled as $v) {
                if (!in_array((string)$v, self::VALID_VIEWS, true)) {
                    $errors[] = "views.enabled contiene vista no soportada ('{$v}').";
                }
            }
        }
        $default = (string)($views['default'] ?? '');
        if ($default === '' || (is_array($enabled) && !in_array($default, array_map('strval', $enabled), true))) {
            $errors[] = "views.default ('{$default}') debe estar en views.enabled.";
        }

        foreach (($config['filters'] ?? []) as $i => $f) {
            if (!is_array($f) || ($f['field'] ?? '') === '') {
                $errors[] = "filters[{$i}].field es obligatorio.";
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Configuración de calendario inválida.', $errors);
        }
    }
}
```

> Ajustar el constructor de `ValidationException` a la firma real observada en `CrudConfigValidator` (mensaje + array de errores).

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Calendar/CalendarConfigValidatorTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CalendarConfigValidator.php tests/Calendar/CalendarConfigValidatorTest.php
git commit -m "feat(calendario): CalendarConfigValidator de contrato + columnas"
```

---

## Task 1.5: `CalendarConfigLoader`

**Files:**
- Create: `app/Application/Services/CalendarConfigLoader.php`
- Read first: `CrudConfigLoader.php` (replicar patrón), `CrudConfigLoader::load()` para obtener la `CrudResourceDefinition` y de ahí las columnas conocidas.

El loader: lee `config/calendars/{key}.json`, obtiene la `CrudResourceDefinition` del recurso vía `CrudConfigLoader`, deriva la lista de columnas conocidas (list columns + form fields del CRUD), valida con `CalendarConfigValidator`, y devuelve `CalendarDefinition`.

> **Columnas conocidas:** Inspeccionar `CrudResourceDefinition`. Si ya hay un accesor que devuelva nombres de columnas (list + form), usarlo. Si no, añadir `public function columnNames(): array` que una los `name` de `list.columns` y `form.fields` (dedupe + incluir primary key), con su propio test en `tests/Crud/`.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Application\Services\CalendarConfigLoader;
use App\Application\Services\CalendarConfigValidator;
use App\Application\Services\CrudConfigLoader;
use App\Application\Services\CrudConfigValidator;
use App\Domain\Entities\CalendarDefinition;

test('CalendarConfigLoader carga el calendario demo_citas y resuelve su definición', function (): void {
    $loader = new CalendarConfigLoader(
        new CrudConfigLoader(new CrudConfigValidator()),
        new CalendarConfigValidator()
    );
    $def = $loader->load('demo_citas');
    assert_true($def instanceof CalendarDefinition, 'devuelve CalendarDefinition');
    assert_same('demo_citas', $def->key(), 'key del archivo coincide');
    assert_same('demo_citas', $def->resource(), 'apunta al recurso CRUD');
});
```

> Este test depende de `config/calendars/demo_citas.json` y `config/cruds/demo_citas.json` (Task 1.8). Si se ejecuta antes, créalos primero o marca el test pendiente hasta 1.8.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Calendar/CalendarConfigLoaderTest`
Expected: FAIL (clase no encontrada).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CalendarDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Logging\AppLogger;

final class CalendarConfigLoader
{
    private const DIR = ROOT_PATH . '/config/calendars';

    /** @var array<string, CalendarDefinition> */
    private array $cache = [];

    public function __construct(
        private readonly CrudConfigLoader $crudLoader,
        private readonly CalendarConfigValidator $validator,
    ) {}

    public function load(string $key): CalendarDefinition
    {
        $key = trim($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $file = self::DIR . '/' . $key . '.json';
        if (!is_readable($file)) {
            throw new ValidationException("No existe configuración de calendario para {$key}.");
        }

        $raw = file_get_contents($file);
        $config = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($config)) {
            AppLogger::error('Calendar config: JSON inválido', ['key' => $key, 'file' => $file]);
            throw new ValidationException("El JSON de {$key}.json es inválido.");
        }

        $resourceKey = (string)($config['calendar']['resource'] ?? '');
        $crudDef = $this->crudLoader->load($resourceKey); // lanza si el recurso no existe
        $columns = $crudDef->columnNames();

        $this->validator->validate($config, $columns);

        $definition = CalendarDefinition::fromArray($config);
        if ($definition->key() !== $key) {
            throw new ValidationException("calendar.key ({$definition->key()}) debe coincidir con el archivo ({$key}).");
        }

        $this->cache[$key] = $definition;
        return $definition;
    }

    /** @return array<string,string> key => título */
    public function listCalendars(): array
    {
        $out = [];
        if (!is_dir(self::DIR)) {
            return $out;
        }
        foreach (scandir(self::DIR) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $key = pathinfo($file, PATHINFO_FILENAME);
            try {
                $out[$key] = $this->load($key)->title();
            } catch (\Throwable $e) {
                AppLogger::warning('Calendar config inválida omitida', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run test to verify it passes** (tras crear los demos en 1.8)

Run: `php tests/run.php Calendar/CalendarConfigLoaderTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CalendarConfigLoader.php tests/Calendar/CalendarConfigLoaderTest.php
git commit -m "feat(calendario): CalendarConfigLoader (resuelve recurso CRUD + valida)"
```

---

## Task 1.6: `CalendarEventMapper`

**Files:**
- Create: `app/Application/Services/CalendarEventMapper.php`
- Test: `tests/Calendar/CalendarEventMapperTest.php`

Convierte filas (`array<string,scalar>`) + `CalendarDefinition` + nombre del recurso → `list<CalendarEvent>`. Reglas: título por plantilla `{col}`; all-day según `mapping.all_day` o por ausencia de hora; color por `estado`/`field`/`fixed`; url a `/admin/crud/{resource}/{id}`.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Application\Services\CalendarEventMapper;
use App\Domain\Entities\CalendarDefinition;

function map_def(array $overrides = []): CalendarDefinition
{
    return CalendarDefinition::fromArray(array_replace_recursive([
        'calendar' => ['key' => 'citas', 'title' => 'A', 'resource' => 'demo_citas'],
        'mapping' => ['start' => 'fecha_inicio', 'end' => 'fecha_fin', 'title' => '{cliente} — {servicio}',
                      'color' => ['by' => 'estado', 'map' => ['pendiente' => 'warning', 'confirmada' => 'success']]],
        'views' => ['default' => 'month', 'enabled' => ['month']],
    ], $overrides));
}

test('CalendarEventMapper mapea fila con plantilla, color por estado y url', function (): void {
    $rows = [[
        'id' => 5, 'cliente' => 'López', 'servicio' => 'Corte', 'estado' => 'confirmada',
        'fecha_inicio' => '2026-06-09 10:00:00', 'fecha_fin' => '2026-06-09 11:00:00',
    ]];
    $events = (new CalendarEventMapper())->map($rows, map_def(), 'demo_citas');
    assert_same(1, count($events), 'un evento');
    $a = $events[0]->toArray();
    assert_same('López — Corte', $a['title'], 'título por plantilla');
    assert_same('success', $a['color'], 'color por estado');
    assert_same('/admin/crud/demo_citas/5', $a['url'], 'url a show');
    assert_same(false, $a['allDay'], 'datetime => no all-day');
});

test('CalendarEventMapper marca all-day cuando la fecha no tiene hora', function (): void {
    $rows = [['id' => 1, 'cliente' => 'X', 'servicio' => 'Y', 'estado' => 'pendiente',
              'fecha_inicio' => '2026-06-09', 'fecha_fin' => null]];
    $events = (new CalendarEventMapper())->map($rows, map_def(), 'demo_citas');
    assert_same(true, $events[0]->toArray()['allDay'], 'fecha sin hora => all-day');
    assert_same(null, $events[0]->toArray()['end'], 'end nulo');
});

test('CalendarEventMapper color fixed usa el valor configurado', function (): void {
    $def = map_def(['mapping' => ['color' => ['by' => 'fixed', 'value' => 'info', 'map' => []]]]);
    $rows = [['id' => 1, 'cliente' => 'X', 'servicio' => 'Y', 'estado' => 'pendiente',
              'fecha_inicio' => '2026-06-09 09:00:00', 'fecha_fin' => null]];
    assert_same('info', (new CalendarEventMapper())->map($rows, $def, 'demo_citas')[0]->toArray()['color'], 'color fijo');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Calendar/CalendarEventMapperTest`
Expected: FAIL (clase no encontrada).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Calendar\CalendarEvent;
use App\Domain\Entities\CalendarDefinition;

final class CalendarEventMapper
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<CalendarEvent>
     */
    public function map(array $rows, CalendarDefinition $def, string $resource): array
    {
        $events = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $start = (string)($row[$def->mappingStart()] ?? '');
            if ($start === '') {
                continue;
            }
            $endCol = $def->mappingEnd();
            $end = ($endCol !== null && isset($row[$endCol]) && $row[$endCol] !== null && $row[$endCol] !== '')
                ? (string)$row[$endCol] : null;

            $events[] = new CalendarEvent(
                id: $id,
                title: $this->title($def, $row, $id),
                start: $start,
                end: $end,
                allDay: $this->allDay($def, $start),
                color: $this->color($def, $row),
                url: '/admin/crud/' . $resource . '/' . $id,
            );
        }
        return $events;
    }

    private function title(CalendarDefinition $def, array $row, int $id): string
    {
        $tpl = $def->mappingTitle();
        if ($tpl === '') {
            return '#' . $id;
        }
        return (string) preg_replace_callback('/\{(\w+)\}/', static function (array $m) use ($row): string {
            return (string)($row[$m[1]] ?? '');
        }, $tpl);
    }

    private function allDay(CalendarDefinition $def, string $start): bool
    {
        $explicit = $def->mappingAllDay();
        if ($explicit !== null) {
            return $explicit;
        }
        // Sin hora (solo fecha) => all-day.
        return !str_contains($start, ':');
    }

    private function color(CalendarDefinition $def, array $row): string
    {
        return match ($def->colorBy()) {
            'estado' => (string)($def->colorMap()[(string)($row['estado'] ?? '')] ?? 'secondary'),
            'field'  => (string)($row['estado'] ?? 'secondary'), // sobreescrito abajo si hay field
            default  => $def->colorFixed(),
        };
    }
}
```

> Nota: para `color.by=field` el color sale de una columna arbitraria. Si se necesita, extender `CalendarDefinition` con `colorField()` y leer `$row[$def->colorField()]`. No está en los tests de esta tarea (YAGNI hasta que un calendario real lo use).

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Calendar/CalendarEventMapperTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/CalendarEventMapper.php tests/Calendar/CalendarEventMapperTest.php
git commit -m "feat(calendario): CalendarEventMapper (título, all-day, color, url)"
```

---

## Task 1.7: Consulta por rango en el repositorio + `ListarEventosCalendarioUseCase`

**Files:**
- Modify: `app/Infrastructure/Repositories/GenericCrudRepository.php`
- Create: `app/Application/UseCases/Calendar/ListarEventosCalendarioUseCase.php`
- Read first: `GenericCrudRepository.php` (firma de `select*`, conexión PDO, binding), `CrudResourceService.php` (cómo aplica `CrudScopeResolver` + permisos al construir el `WHERE`).

**Objetivo:** método de repositorio que devuelve filas de la tabla del recurso donde `mapping.start BETWEEN :desde AND :hasta`, **respetando el mismo `WHERE` de scope** que el listado CRUD. El UseCase debe reutilizar el camino de scope existente (no reimplementarlo): preferir extender `CrudResourceService` con un método `eventosCalendario(...)` que arme el `WHERE` scoped (igual que `buildIndexData`) + el rango, y delegue al repositorio; el UseCase llama a ese método y pasa el resultado por `CalendarEventMapper`.

- [ ] **Step 1: Add range query to repository**

Añadir a `GenericCrudRepository`, replicando el estilo de los `select*` existentes (mismo manejo de tabla validada y binding):

```php
/**
 * Filas dentro de un rango de fechas sobre $dateColumn, con $where adicional (scope).
 * @param array<string,mixed> $bindings bindings del WHERE de scope
 * @return list<array<string,mixed>>
 */
public function selectInDateRange(
    string $table,
    string $dateColumn,
    string $from,
    string $to,
    string $extraWhere = '',
    array $bindings = [],
    int $limit = 2000
): array {
    $sql = 'SELECT * FROM `' . $this->safeIdentifier($table) . '`'
         . ' WHERE `' . $this->safeIdentifier($dateColumn) . '` BETWEEN :__from AND :__to';
    if ($extraWhere !== '') {
        $sql .= ' AND (' . $extraWhere . ')';
    }
    $sql .= ' ORDER BY `' . $this->safeIdentifier($dateColumn) . '` ASC LIMIT ' . (int)$limit;

    $stmt = $this->connection->prepare($sql);
    $stmt->execute(array_merge($bindings, ['__from' => $from, '__to' => $to]));
    return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}
```

> Usar el helper de identificador seguro que ya exista en el repo (mismo que usan los otros `select*`). Si se llama distinto a `safeIdentifier`, ajustar.

- [ ] **Step 2: Write the failing test for the use case**

`tests/Calendar/ListarEventosCalendarioUseCaseTest.php` — test de integración ligero que use un doble del servicio CRUD que devuelva filas fijas y verifique que el UseCase las pasa por el mapper y respeta el rango. Seguir el estilo de doubles ya usado en `tests/Crud/` (revisar un test de servicio existente para el patrón de stub/fake del repositorio o servicio).

```php
<?php
declare(strict_types=1);

use App\Application\UseCases\Calendar\ListarEventosCalendarioUseCase;
use App\Domain\Calendar\DateRange;

test('UseCase devuelve eventos normalizados a partir de filas scoped', function (): void {
    // Fake del servicio que entrega filas (sustituye el acceso a BD).
    $fakeCrud = new class {
        public array $lastArgs = [];
        public function eventosCalendario(string $resource, string $dateCol, DateRange $range, ?int $userId): array {
            $this->lastArgs = compact('resource', 'dateCol', 'userId');
            return [[
                'id' => 1, 'cliente' => 'López', 'servicio' => 'Corte', 'estado' => 'confirmada',
                'fecha_inicio' => '2026-06-09 10:00:00', 'fecha_fin' => '2026-06-09 11:00:00',
            ]];
        }
    };
    // El UseCase real recibe CrudResourceService; aquí inyectar el fake si la firma lo permite,
    // o construir el UseCase con sus colaboradores reales y un repositorio fake (ver tests/Crud/*).
    // ... (ajustar construcción al patrón real del repo)
    assert_true(true, 'placeholder: implementar con el doble acorde al patrón de tests/Crud');
});
```

> **Importante:** el ejecutor debe sustituir el cuerpo de este test por uno real siguiendo el patrón de dobles que use el repo (revisar `tests/Crud/` para ver cómo se fabrican fakes de servicios/repositorios). No dejar el `assert_true(true)` en el commit final.

- [ ] **Step 3: Implement the use case**

```php
<?php
declare(strict_types=1);

namespace App\Application\UseCases\Calendar;

use App\Application\Services\CalendarConfigLoader;
use App\Application\Services\CalendarEventMapper;
use App\Application\Services\CrudResourceService;
use App\Domain\Calendar\DateRange;

final class ListarEventosCalendarioUseCase
{
    public function __construct(
        private readonly CalendarConfigLoader $calendarLoader,
        private readonly CrudResourceService $crudService,
        private readonly CalendarEventMapper $mapper,
    ) {}

    /** @return list<array<string,mixed>> eventos en forma JSON */
    public function execute(string $calendarKey, DateRange $range, ?int $userId): array
    {
        $def = $this->calendarLoader->load($calendarKey);
        $rows = $this->crudService->eventosCalendario(
            $def->resource(), $def->mappingStart(), $range, $userId
        );
        $events = $this->mapper->map($rows, $def, $def->resource());
        return array_map(static fn($e) => $e->toArray(), $events);
    }
}
```

- [ ] **Step 4: Add `eventosCalendario()` to `CrudResourceService`**

Añadir un método que reproduzca el armado de `WHERE` scoped de `buildIndexData` (mismos `CrudScopeResolver` + permisos del usuario), construya el rango y delegue en `GenericCrudRepository::selectInDateRange`. Reutilizar los colaboradores ya inyectados en el servicio. Firma:

```php
public function eventosCalendario(string $resource, string $dateColumn, DateRange $range, ?int $userId): array
```

> Leer `buildIndexData()` y extraer el cálculo de `(extraWhere, bindings)` scoped a un helper privado compartido para no duplicar la lógica de scope (DRY).

- [ ] **Step 5: Run tests**

Run: `php tests/run.php Calendar/`
Expected: PASS (incluido el UseCase test ya implementado de verdad).

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Repositories/GenericCrudRepository.php app/Application/UseCases/Calendar/ListarEventosCalendarioUseCase.php app/Application/Services/CrudResourceService.php tests/Calendar/ListarEventosCalendarioUseCaseTest.php
git commit -m "feat(calendario): feed de eventos por rango con scope CRUD reutilizado"
```

---

## Task 1.8: Recurso CRUD demo + calendario demo + schema

**Files:**
- Create: `config/cruds/demo_citas.json`, `config/calendars/demo_citas.json`
- Create: `database/schema/modules/calendario.sql`
- Read first: `config/cruds/demo_productos.json` (estructura completa de un recurso CRUD con states), `database/schema/modules/crud-engine.sql` (estilo de bootstrap + permisos + menú).

- [ ] **Step 1: Crear el recurso CRUD demo `config/cruds/demo_citas.json`**

Recurso con tabla `dom_demo_citas`, `permission_prefix: demo_citas`, columnas `cliente`, `servicio`, `estado` (states pendiente/confirmada/cancelada con badges), `fecha_inicio` (DATETIME), `fecha_fin` (DATETIME), `created_by`. Form con esos campos. Copiar la estructura de `demo_productos.json` y adaptar. Incluir `list.columns`, `form.fields`, `states`, y `detail.tabs` (general + history).

- [ ] **Step 2: Crear el calendario `config/calendars/demo_citas.json`**

```json
{
  "calendar": { "key": "demo_citas", "title": "Agenda de Citas", "resource": "demo_citas", "icon": "bi-calendar-event" },
  "mapping": {
    "start": "fecha_inicio", "end": "fecha_fin", "all_day": false,
    "title": "{cliente} — {servicio}",
    "color": { "by": "estado", "map": { "pendiente": "warning", "confirmada": "success", "cancelada": "secondary" } }
  },
  "views": { "default": "month", "enabled": ["month", "week", "day", "table"] },
  "interaction": { "create_on_slot": true, "open_detail": true, "edit_from_event": true },
  "filters": [ { "field": "estado", "label": "Estado" } ],
  "dashboard_widget": true
}
```

- [ ] **Step 3: Crear `database/schema/modules/calendario.sql`**

Tabla `dom_demo_citas` (id, cliente, servicio, estado, fecha_inicio DATETIME, fecha_fin DATETIME NULL, created_by, timestamps), permisos `demo_citas.ver/.crear/.editar/.eliminar` asignados al rol admin, y entrada de menú a `/admin/calendario/demo_citas`. Seguir el estilo de `database/schema/modules/crud-engine.sql`. Incluir 4-6 citas de ejemplo en el mes actual.

- [ ] **Step 4: Verify config loads**

Run: `php tests/run.php Calendar/CalendarConfigLoaderTest`
Expected: PASS (ahora que existen los configs demo).

- [ ] **Step 5: Commit**

```bash
git add config/cruds/demo_citas.json config/calendars/demo_citas.json database/schema/modules/calendario.sql
git commit -m "feat(calendario): recurso CRUD demo_citas + calendario demo + schema"
```

---

## Task 1.9: `CalendarioController` + rutas + bindings + vista mensual/tabla (lectura)

**Files:**
- Create: `app/Presentation/Controllers/Admin/CalendarioController.php`
- Create: `app/Presentation/Views/admin/calendario/index.php`
- Create: `app/Application/Services/CalendarViewModelBuilder.php`
- Create: `public/assets/js/calendar.js`
- Modify: `routes/web.php`, `config/container.php`, `public/assets/css/lebytek-ui.css`
- Read first: `CrudController.php` (patrón controlador, `currentUser()`, `view()`), `config/container.php` (binding de `CrudController`), `app/Presentation/Views/admin/crud/index.php` (estética de tabla + cómo se cargan assets), `app/Presentation/Views/layouts/base.php` (cómo se incluyen JS/CSS).

- [ ] **Step 1: Controller**

```php
<?php
declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\CalendarConfigLoader;
use App\Application\Services\CalendarViewModelBuilder;
use App\Application\Services\ConfiguracionService;
use App\Application\UseCases\Calendar\ListarEventosCalendarioUseCase;
use App\Domain\Calendar\DateRange;
use App\Domain\Exceptions\AccesoException;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Presentation\Controllers\AdminBaseController;

final class CalendarioController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly CalendarConfigLoader $calendarLoader,
        private readonly CalendarViewModelBuilder $viewModelBuilder,
        private readonly ListarEventosCalendarioUseCase $listarEventos,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        try {
            $key = (string) $request->param('key');
            $permisos = (array) ($this->currentUser()['permisos'] ?? \App\Kernel\Security\Session::get('auth_permisos', []));
            $data = $this->viewModelBuilder->build($key, $permisos);
            $data['titulo'] = $data['title'] . ' - Calendario';
            return $this->view('admin/calendario/index', $data);
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/dashboard', 'error', $e->getMessage());
        }
    }

    public function events(Request $request): Response
    {
        try {
            $key = (string) $request->param('key');
            $q = $request->all();
            $range = isset($q['desde'], $q['hasta'])
                ? DateRange::fromStrings((string)$q['desde'], (string)$q['hasta'])
                : DateRange::forMonth((int)date('Y'), (int)date('n'));
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $events = $this->listarEventos->execute($key, $range, $userId > 0 ? $userId : null);
            return Response::json(['eventos' => $events]);
        } catch (AccesoException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (ValidationException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }
}
```

> Ajustar `Response::json(...)` a la firma real (revisar `Response`). Ajustar la obtención de permisos al helper real (`currentUser()` vs `Session`).

- [ ] **Step 2: `CalendarViewModelBuilder`**

Construye datos del shell: carga `CalendarDefinition`, verifica permiso `{prefix}.ver` (lanza `AccesoException` si falta), y arma: `title`, `key`, `views` (default+enabled), `filters` (con opciones), `legend` (de `color.map` con labels de states), `capabilities` (`canCreate`/`canEdit`/`canDelete` según permisos del recurso) y `feedUrl`/`resource`. Leer `RbacPolicy`/helper de permisos para el patrón de chequeo.

- [ ] **Step 3: Routes** — añadir en `routes/web.php` dentro del grupo `/admin` (tras las rutas `/crud`):

```php
$router->get('/calendario/{key}',         [CalendarioController::class, 'index']);
$router->get('/calendario/{key}/eventos', [CalendarioController::class, 'events']);
```

Y `use App\Presentation\Controllers\Admin\CalendarioController;` arriba.

- [ ] **Step 4: Container bindings** — en `config/container.php`, registrar (siguiendo el patrón de `CrudController`/`CrudConfigLoader`): `CalendarConfigLoader`, `CalendarConfigValidator`, `CalendarEventMapper`, `CalendarViewModelBuilder`, `ListarEventosCalendarioUseCase`, `CalendarioController`.

- [ ] **Step 5: View shell `admin/calendario/index.php`**

Toolbar Bootstrap (botones prev/hoy/siguiente, selector de vista entre `views.enabled`, filtros, leyenda de colores), un `<div id="lebytek-calendar" data-feed="/admin/calendario/{key}/eventos" data-resource="..." data-default-view="..." data-can-create="..." ...></div>`, estado vacío, y `<script src="/assets/js/calendar.js" defer></script>`. Pasar capacidades vía `data-*`.

- [ ] **Step 6: `calendar.js` — vista mensual + tabla (solo lectura en esta tarea)**

Vanilla JS: lee `data-*`, hace `fetch(feed?desde=&hasta=)`, renderiza la rejilla mensual (semanas × días con píldoras de eventos coloreadas con clases `bg-{tone}` de Bootstrap) y la vista tabla (lista ordenada por fecha). Selector de vista cambia el render. Navegación prev/hoy/siguiente recalcula rango y re-fetch. Sin interacciones de edición todavía (se añaden en Fase 3). Click en evento → navegar a `event.url` (show CRUD) como fallback de lectura.

- [ ] **Step 7: CSS** — añadir a `lebytek-ui.css` clases `.lebytek-calendar`, rejilla (`display:grid; grid-template-columns: repeat(7, 1fr)`), celdas de día, píldoras de evento, "+N más", usando tokens/variables del design system existente.

- [ ] **Step 8: Manual smoke test**

Run: `php -S localhost:8000 -t public` y abrir `http://localhost:8000/admin/calendario/demo_citas` (tras instalar el schema demo). Verificar mes con eventos y cambio a tabla.

- [ ] **Step 9: Commit**

```bash
git add app/Presentation/Controllers/Admin/CalendarioController.php app/Presentation/Views/admin/calendario/index.php app/Application/Services/CalendarViewModelBuilder.php public/assets/js/calendar.js routes/web.php config/container.php public/assets/css/lebytek-ui.css
git commit -m "feat(calendario): controller, rutas, shell y vistas mensual/tabla (lectura)"
```

---

# FASE 2 — Vistas semanal / diaria + filtros + leyenda

## Task 2.1: Rango por vista en el feed (semana/día)

**Files:**
- Modify: `app/Presentation/Controllers/Admin/CalendarioController.php` (ya acepta `desde/hasta`; sin cambio si el front envía el rango correcto).
- Modify: `public/assets/js/calendar.js`

- [ ] **Step 1:** En `calendar.js`, al cambiar a vista `week`/`day`, calcular `desde/hasta` con la semana (lunes-domingo) o el día y re-fetch. Reutilizar la lógica de rango ya existente.
- [ ] **Step 2:** Render `week` (timed): rejilla de 7 columnas × filas horarias (00–23) cuando el calendario es `timed`; eventos posicionados por hora de `start`/`end`. Si `all_day`: lista por día en columnas.
- [ ] **Step 3:** Render `day` (timed): una columna × filas horarias; o lista si `all_day`.
- [ ] **Step 4:** Indicar al front si el calendario es timed/all-day vía `data-all-day` (derivado en `CalendarViewModelBuilder` de `mapping.all_day` o del tipo de columna). Añadir ese `data-*` al shell.
- [ ] **Step 5: Manual smoke test** de las tres vistas con `demo_citas`.
- [ ] **Step 6: Commit**

```bash
git add public/assets/js/calendar.js app/Application/Services/CalendarViewModelBuilder.php app/Presentation/Views/admin/calendario/index.php
git commit -m "feat(calendario): vistas semanal y diaria (timed/all-day)"
```

## Task 2.2: Filtros + leyenda aplicados al feed

**Files:**
- Modify: `app/Application/UseCases/Calendar/ListarEventosCalendarioUseCase.php` / `CrudResourceService::eventosCalendario` (aceptar filtros), `public/assets/js/calendar.js`, `CalendarViewModelBuilder`.

- [ ] **Step 1:** `eventosCalendario` acepta `array $filters` y los añade al `WHERE` scoped (igualdad simple sobre columnas declaradas en `filters[]`), con binding seguro. Test en `tests/Calendar/` con un fake que verifique el filtro propagado.
- [ ] **Step 2:** El feed (`events`) lee filtros del query y los pasa al UseCase.
- [ ] **Step 3:** `calendar.js` envía los filtros activos de la toolbar en el `fetch`.
- [ ] **Step 4:** Leyenda: render desde `legend` del view-model (color.map + labels de states).
- [ ] **Step 5: Commit**

```bash
git add app/Application app/Presentation public/assets/js/calendar.js tests/Calendar/
git commit -m "feat(calendario): filtros server-side + leyenda de colores"
```

---

# FASE 3 — Interacciones de edición (gateadas por RBAC)

> Toda escritura reutiliza endpoints CRUD existentes (`/admin/crud/{resource}/...`) con CSRF y `#confirmModal`. El calendario NO añade endpoints de escritura.

## Task 3.1: Popover de evento (ver detalle + acciones según capacidades)

**Files:**
- Modify: `public/assets/js/calendar.js`, `app/Presentation/Views/admin/calendario/index.php`
- Read first: `app/Presentation/Views/admin/crud/partials/actions_row.php` y `app/Presentation/Views/partials/confirm_modal.php` (reutilizar el modal de confirmación unificado y el patrón de forms POST con CSRF).

- [ ] **Step 1:** Click en evento abre un popover Bootstrap con: título, fechas, enlace "Ver" → `event.url` (siempre, si `open_detail`).
- [ ] **Step 2:** Si `data-can-edit`: botón "Editar" → navega a `/admin/crud/{resource}/{id}/editar`.
- [ ] **Step 3:** Si `data-can-delete`: botón "Eliminar" → dispara el `#confirmModal` y postea a `/admin/crud/{resource}/{id}/eliminar` con token CSRF (incluir el token en el shell vía `data-csrf` o input oculto, igual que el CRUD).
- [ ] **Step 4: Manual smoke test:** ver/editar/eliminar desde el popover; verificar que sin permiso no aparecen los botones.
- [ ] **Step 5: Commit**

```bash
git add public/assets/js/calendar.js app/Presentation/Views/admin/calendario/index.php
git commit -m "feat(calendario): popover de evento con ver/editar/eliminar reutilizando CRUD"
```

## Task 3.2: Crear desde slot vacío

**Files:**
- Modify: `public/assets/js/calendar.js`

- [ ] **Step 1:** Si `data-can-create`: click en un día/franja vacía navega a `/admin/crud/{resource}/crear?fecha=YYYY-MM-DD[ HH:MM]`, precargando la fecha del slot en el parámetro.
- [ ] **Step 2:** Verificar que el form CRUD respeta el parámetro `fecha` para precargar el campo `mapping.start`. Si el form no precarga por query, añadir soporte mínimo en `CrudResourceService::buildCreateData` para hidratar campos desde el query (solo columnas del form) — con test en `tests/Crud/`.
- [ ] **Step 3: Manual smoke test:** crear cita desde un día vacío.
- [ ] **Step 4: Commit**

```bash
git add public/assets/js/calendar.js app/Application/Services/CrudResourceService.php tests/Crud/
git commit -m "feat(calendario): crear evento desde slot vacío (precarga de fecha)"
```

---

# FASE 4 — Dashboard widget + módulo + docs

## Task 4.1: Slot `widgets` en el contrato de dashboard

**Files:**
- Modify: `app/Domain/Dashboard/DashboardContribution.php`
- Modify: `app/Application/UseCases/Dashboard/BuildDashboardViewModelUseCase.php`
- Modify: `app/Application/DTO/Dashboard/DashboardViewModel.php`
- Test: `tests/Dashboard/DashboardWidgetsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Domain\Dashboard\DashboardBuildContext;
use App\Domain\Dashboard\DashboardContribution;
use App\Domain\Interfaces\DashboardContributionProviderInterface;
use App\Application\UseCases\Dashboard\BuildDashboardViewModelUseCase;

test('BuildDashboardViewModelUseCase fusiona widgets de los proveedores', function (): void {
    $provider = new class implements DashboardContributionProviderInterface {
        public function priority(): int { return 50; }
        public function contribute(DashboardBuildContext $c): DashboardContribution {
            return new DashboardContribution(
                kpis: [], activityItems: [], quickAccess: [], statusBlock: null,
                widgets: [['partial' => 'dashboard/calendar_mini', 'data' => ['key' => 'demo_citas']]]
            );
        }
    };
    $useCase = new BuildDashboardViewModelUseCase([$provider]);
    $vm = $useCase->execute(new DashboardBuildContext(/* args reales según la clase */));
    assert_same('dashboard/calendar_mini', $vm->widgets[0]['partial'] ?? null, 'widget fusionado');
});

test('DashboardContribution::vacia no aporta widgets', function (): void {
    assert_same([], DashboardContribution::vacia()->widgets, 'vacía => sin widgets');
});
```

> Ajustar el constructor de `DashboardBuildContext` a su firma real (revisar la clase).

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Dashboard/DashboardWidgetsTest`
Expected: FAIL (parámetro `widgets` inexistente).

- [ ] **Step 3: Extend `DashboardContribution`** — añadir parámetro `widgets` (default `[]`) al constructor y a `vacia()`:

```php
/**
 * @param list<array{partial:string,data:array<string,mixed>}> $widgets
 */
public function __construct(
    public readonly array $kpis,
    public readonly array $activityItems,
    public readonly array $quickAccess,
    public readonly ?array $statusBlock = null,
    public readonly array $widgets = [],
) {}

public static function vacia(): self
{
    return new self([], [], [], null, []);
}
```

- [ ] **Step 4: Merge in the use case** — en `BuildDashboardViewModelUseCase::execute`, acumular `$widgets[] = $row;` sobre `$c->widgets` y pasar `widgets: $widgets` al `DashboardViewModel`.

- [ ] **Step 5: Expose in `DashboardViewModel`** — añadir propiedad `public readonly array $widgets` (default `[]`) al DTO.

- [ ] **Step 6: Run test to verify it passes**

Run: `php tests/run.php Dashboard/`
Expected: PASS (incluidos los tests de dashboard ya existentes, sin regresión).

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Dashboard/DashboardContribution.php app/Application/UseCases/Dashboard/BuildDashboardViewModelUseCase.php app/Application/DTO/Dashboard/DashboardViewModel.php tests/Dashboard/DashboardWidgetsTest.php
git commit -m "feat(dashboard): slot widgets retrocompatible en el contrato de contribución"
```

## Task 4.2: `CalendarDashboardProvider` + partial mini-calendario

**Files:**
- Create: `app/Infrastructure/Dashboard/CalendarDashboardProvider.php`
- Create: `app/Presentation/Views/partials/dashboard/calendar_mini.php`
- Modify: `config/dashboard.php`, `app/Presentation/Views/admin/dashboard/index.php`, `config/container.php`
- Read first: `DefaultPlatformDashboardProvider.php` (patrón), `admin/dashboard/index.php` (cómo se renderizan secciones).

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Infrastructure\Dashboard\CalendarDashboardProvider;
use App\Domain\Dashboard\DashboardBuildContext;

test('CalendarDashboardProvider devuelve vacía sin permiso del recurso', function (): void {
    // Context sin permisos.
    $provider = new CalendarDashboardProvider(/* CalendarConfigLoader real o fake */);
    $contrib = $provider->contribute(new DashboardBuildContext(/* sin permisos */));
    assert_same([], $contrib->widgets, 'sin permiso => sin widget');
});

test('CalendarDashboardProvider aporta widget calendar_mini con permiso', function (): void {
    $provider = new CalendarDashboardProvider(/* loader que liste demo_citas con dashboard_widget=true */);
    $contrib = $provider->contribute(new DashboardBuildContext(/* con demo_citas.ver */));
    assert_same('dashboard/calendar_mini', $contrib->widgets[0]['partial'] ?? null, 'widget presente');
    assert_same('demo_citas', $contrib->widgets[0]['data']['key'] ?? null, 'apunta al calendario');
});
```

> Ajustar a la firma real de `DashboardBuildContext` y al método de chequeo de permiso (`tienePermiso`). Usar un fake de `CalendarConfigLoader` si construir el real es costoso.

- [ ] **Step 2: Implement provider** — `priority()` ~60; en `contribute()`, listar calendarios con `dashboard_widget=true` (vía `CalendarConfigLoader::listCalendars()` + cargar cada def), filtrar los que el usuario puede ver (`$context->tienePermiso($prefix.'.ver')` — derivar prefix del recurso CRUD del calendario), y aportar un widget por calendario `['partial' => 'dashboard/calendar_mini', 'data' => ['key' => ..., 'title' => ..., 'url' => '/admin/calendario/'.$key]]`. Si ninguno aplica → `DashboardContribution::vacia()`.

- [ ] **Step 3: Partial `calendar_mini.php`** — mini rejilla mensual del mes actual; el contenedor entero es `<a href="{url}">`. Carga eventos del mes vía el mismo feed con un pequeño script inline o marcadores precalculados pasados en `data`. Mantenerlo solo-vista (sin navegación de meses). Usar clases Lebytek compactas.

- [ ] **Step 4: Render widgets en el dashboard** — en `admin/dashboard/index.php`, iterar `$widgets` (del view-model) e incluir cada `partial` whitelisteado: validar que `partial` empiece por `dashboard/` y exista en `Views/partials/` antes de `include` (evitar path traversal).

- [ ] **Step 5: Register** — añadir `CalendarDashboardProvider::class` a `config/dashboard.php` (`providers`) y su binding en `config/container.php`.

- [ ] **Step 6: Run tests + smoke**

Run: `php tests/run.php Dashboard/`
Expected: PASS. Smoke: dashboard muestra el mini-calendario y al hacer click va a `/admin/calendario/demo_citas`.

- [ ] **Step 7: Commit**

```bash
git add app/Infrastructure/Dashboard/CalendarDashboardProvider.php app/Presentation/Views/partials/dashboard/calendar_mini.php config/dashboard.php app/Presentation/Views/admin/dashboard/index.php config/container.php tests/Dashboard/
git commit -m "feat(calendario): widget mini-calendario solo-vista en dashboard"
```

## Task 4.3: Manifiesto del módulo + instalación

**Files:**
- Create: `config/modules/calendario.php`
- Modify: instalación/`config/vertical.php` según el mecanismo de módulos.
- Read first: `config/modules/crud-engine.php` (estructura de manifiesto) y cómo el instalador consume `config/modules/*.php` (buscar el cargador de manifiestos / SchemaBootstrap).

- [ ] **Step 1:** Crear `config/modules/calendario.php`:

```php
<?php
declare(strict_types=1);

return [
    'clave'         => 'calendario',
    'nombre'        => 'Calendario',
    'descripcion'   => 'Vistas de calendario (mes/semana/día/tabla) sobre recursos CRUD + widget de dashboard.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core', 'crud-engine'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/calendario.sql',
    'cruds'         => ['demo_citas'],
    'calendars'     => ['demo_citas'],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [\App\Infrastructure\Dashboard\CalendarDashboardProvider::class],
];
```

- [ ] **Step 2:** Verificar que el toggle `modules.calendario` en `config/vertical.php` (o el flujo de instalación) habilita/inhabilita el módulo y que `SchemaBootstrap` corre el `bootstrap_sql`. Añadir/ajustar test en `tests/Install/` siguiendo `SchemaBootstrapTest.php`.

- [ ] **Step 3:** Run: `php tests/run.php Install/` — Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add config/modules/calendario.php config/vertical.php tests/Install/
git commit -m "feat(calendario): manifiesto de módulo e integración de instalación"
```

## Task 4.4: Documentación

**Files:**
- Create: `docs/modules/modulo-calendario.md`
- Modify: `docs/modules/uso-de-modulo-dominio.md` (referencia al calendario como capacidad), `CLAUDE.md` (sección "Platform Modules" → añadir Calendario).

- [ ] **Step 1:** Escribir `docs/modules/modulo-calendario.md`: propósito, esquema completo de `config/calendars/{key}.json` (tabla de campos del spec), las 4 vistas, modelo de interacción/RBAC, integración dashboard, instalación, y un ejemplo end-to-end con `demo_citas`. Incluir el contrato del slot `widgets`.
- [ ] **Step 2:** Añadir entrada de Calendario en `CLAUDE.md` (Platform Modules) y enlace en `uso-de-modulo-dominio.md`.
- [ ] **Step 3: Commit**

```bash
git add docs/modules/modulo-calendario.md docs/modules/uso-de-modulo-dominio.md CLAUDE.md
git commit -m "docs(calendario): guía del módulo y referencias"
```

---

## Cierre

- [ ] **Run full suite:** `php tests/run.php` — Expected: todo verde, sin regresiones en `Crud/`, `Dashboard/`, `Install/`.
- [ ] **Smoke final:** mes/semana/día/tabla en `/admin/calendario/demo_citas`; crear/editar/eliminar; widget en dashboard; toggle del módulo en `vertical.php`.
- [ ] Considerar `superpowers:requesting-code-review` antes de dar por cerrado.

---

## Notas de diseño aplicadas

- **DRY:** la lógica de scope vive una sola vez en `CrudResourceService` (helper compartido por `buildIndexData` y `eventosCalendario`). El calendario nunca arma SQL ni reimplementa permisos.
- **YAGNI:** sin drag&drop, sin recurrencia, sin multi-fuente, sin preferencias por usuario (ver "Fuera de alcance" del spec).
- **TDD:** value objects, validador y mapper se construyen test-first; las piezas de UI (JS/vistas) y wiring se validan con smoke + tests de integración con dobles.
- **Retrocompatibilidad:** el slot `widgets` tiene default `[]`; los proveedores y vistas existentes del dashboard no cambian de comportamiento.
