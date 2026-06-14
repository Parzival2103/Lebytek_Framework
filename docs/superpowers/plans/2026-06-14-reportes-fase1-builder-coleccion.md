# Reportes — Fase 1: builder de colección Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el módulo `reportes` (Fase 1 del spec `docs/superpowers/specs/2026-06-14-reportes-interfaz-visual-design.md`): catálogo de fuentes sobre el CRUD Engine, tabla `rep_reportes`, un asistente por pasos que arma reportes de **colección** con tratamientos (agrupar/sum/count/avg/min/max/ordenar) y periodo, un índice estilo CRUD, y la plantilla demo `tabla_estadistica`.

**Architecture:** Capa opcional y desacoplada, espejo del módulo Calendario. El programador declara *fuentes reportables* en `config/reportes/{key}.json` que apuntan a un recurso CRUD existente; los datos se leen vía `CrudDataService` (hereda scope, permisos y filtros) — **sin SQL nuevo de negocio**. La lógica de tratamientos y periodo vive en value-objects/servicios puros y testeables (`ReporteAggregator`, `PeriodoResolver`). El render de PDF reutiliza el `pdf-kit` ya construido (`PdfRenderingService`, `PdfTemplateRegistry`, componentes atómicos). Onion: Domain sin dependencias, Infrastructure implementa interfaces de Domain, Presentation orquesta.

**Tech Stack:** PHP 8.1+, contenedor DI propio (`config/container.php`), enrutador propio (`routes/web.php`), `BaseRepository` (PDO vía `Connection::getInstance()`), `dompdf/dompdf ^3.1` a través del `pdf-kit`, y el arnés de pruebas plano `php tests/run.php` (sin PHPUnit). Módulo de referencia: Calendario (`config/modules/calendario.php`, `app/Application/Services/Calendar*`, `database/schema/modules/calendario.sql`).

---

## Conventions used by this plan (read once)

- **Namespaces → paths** PSR-4 vía `app/Kernel/Autoloader.php`: `App\Domain\Reporte\Foo` ⇒ `app/Domain/Reporte/Foo.php`. Siempre `declare(strict_types=1);`.
- **Tests** son archivos que terminan en `Test.php` bajo `tests/`. Usan los helpers globales de `tests/lib/microtest.php`: `test(name, fn)`, `assert_true`, `assert_same`, `assert_null`, `assert_throws`. `ROOT_PATH` lo define `tests/lib/bootstrap.php` (raíz del repo). El autoloader se carga solo; solo haz `use` de las clases.
- **Correr un archivo de test:** `php tests/run.php <substring-del-path>`. Sin argumento corre todo. Exit code ≠ 0 si algo falla.
- **Excepciones de validación:** `App\Domain\Exceptions\ValidationException` — constructor `(string $message = '', array $errors = [], int $code = 422)`.
- **Columnas protegidas** (nunca expuestas a reportes): `id, created_at, created_by, updated_at, updated_by, deleted, deleted_at, deleted_by`.
- **Repositorios PDO** extienden `App\Kernel\BaseClasses\BaseRepository` (constructor sin argumentos; expone `query()`, `queryOne()`, `execute()`, `insert()` protegidos). Se registran en el contenedor con `fn() => new XRepository()` (igual que `LoginIntentoRepository`).
- **Controladores admin** extienden `App\Presentation\Controllers\AdminBaseController`; renderizan con `$this->view(string $view, array $data): Response` y reciben en su constructor `ConfiguracionService` + `AdminNavigationMenuService` (que pasan a `parent::__construct`).
- **Commits:** Conventional Commits, scope `reportes`. Termina cada mensaje con el trailer:
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`

---

## File Structure

**Domain — `app/Domain/Reporte/` (VOs/entidades/interfaces puras):**
- `ReporteFuente.php` — VO desde el JSON del programador (columnas expuestas, group_by, order_by, filtros, periodo, max_rows, plantillas).
- `ReporteGuardado.php` — entidad del reporte guardado (selección del usuario).
- `ReporteTemplateInterface.php` — `extends App\Domain\Pdf\PdfTemplateInterface`, añade `schemaPasos()`.
- `ReporteDataSourceInterface.php` — frontera de lectura de datos (permite testear el use case sin BD).

**Domain interfaces — `app/Domain/Interfaces/`:**
- `ReporteRepositoryInterface.php` — persistencia de `rep_reportes`.

**Application — `app/Application/Reporte/`:**
- `ReporteConfigValidator.php` — valida `config/reportes/{key}.json` contra columnas del recurso.
- `ReporteConfigLoader.php` — carga/cachea fuentes; resuelve el `CrudResourceDefinition`.
- `PeriodoResolver.php` — preset → rango `[from,to]` + label (puro).
- `ReporteAggregator.php` — aplica tratamientos a filas (puro): columnas/filas/totales listos para `PdfDataTable`/`PdfTotalsBlock`.
- `CrudReporteDataSource.php` — adaptador `ReporteDataSourceInterface` sobre `CrudDataService::eventsInRange`.
- `BuildReporteDataUseCase.php` — orquesta intersección + lectura + agregación → payload.
- `GenerarReporteUseCase.php` — payload + marca → `PdfRenderingService::renderTemplate` → bytes.
- `GuardarReporteUseCase.php` — valida la selección del usuario contra la fuente y persiste (crear/actualizar).

**Application/Pdf — `app/Application/Pdf/Templates/`:**
- `TablaEstadisticaTemplate.php` — plantilla demo de colección (`ReporteTemplateInterface`).

**Infrastructure — `app/Infrastructure/Repositories/`:**
- `PdoReporteRepository.php` — `implements ReporteRepositoryInterface` sobre `BaseRepository`.

**Presentation — `app/Presentation/`:**
- `Controllers/Admin/ReportesController.php`.
- `Views/admin/reportes/index.php` — índice estilo CRUD.
- `Views/admin/reportes/builder.php` — asistente por pasos.

**Config / schema:**
- `config/modules/reportes.php` — manifiesto.
- `config/reportes/citas.json` — fuente demo (sobre `demo_citas`).
- `config/pdf_templates.php` — añade `tabla_estadistica` (modify).
- `config/vertical.php` — añade `reportes => true` (modify).
- `config/container.php` — registra servicios/repos/controlador (modify).
- `routes/web.php` — rutas `/admin/reportes/*` (modify).
- `database/schema/modules/reportes.sql` — `rep_reportes`, permisos, menú, reporte demo.

**Tests — `tests/Reporte/`:**
- `ReporteFuenteTest.php`, `ReporteConfigValidatorTest.php`, `ReporteConfigLoaderTest.php`, `PeriodoResolverTest.php`, `ReporteAggregatorTest.php`, `ReporteGuardadoTest.php`, `BuildReporteDataUseCaseTest.php`, `TablaEstadisticaTemplateTest.php`, `GuardarReporteUseCaseTest.php`.

---

## Task 1: Módulo manifest + toggle vertical (scaffold)

**Files:**
- Create: `config/modules/reportes.php`
- Modify: `config/vertical.php`

- [ ] **Step 1: Crear el manifiesto**

Create `config/modules/reportes.php`:

```php
<?php

declare(strict_types=1);

// Manifiesto del módulo Reportes. Capa opcional sobre el CRUD Engine + pdf-kit:
// el programador declara fuentes reportables (config/reportes/*.json) y el usuario
// final arma/guarda reportes (rep_reportes) que se regeneran como PDF.
// Bootstrap (tabla rep_reportes, permisos, menú, reporte demo) en
// database/schema/modules/reportes.sql.
return [
    'clave'         => 'reportes',
    'nombre'        => 'Reportes',
    'descripcion'   => 'Builder de reportes sobre recursos CRUD; genera PDFs con el pdf-kit.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core', 'crud-engine', 'pdf-kit'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/reportes.sql',
    'cruds'         => [],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
```

- [ ] **Step 2: Activar el módulo en el perfil vertical**

In `config/vertical.php`, dentro del array `'modules' => [ ... ]`, añade la línea `reportes` después de `pdf_kit`:

```php
    'modules' => [
        'dashboard'      => true,
        'administracion' => true,
        'calendario'     => true,
        'pdf_kit'        => true,
        'reportes'       => true,
    ],
```

- [ ] **Step 3: Verificar que el manifiesto parsea**

Run:
```bash
php -r "define('ROOT_PATH', getcwd()); define('APP_PATH', getcwd().'/app'); require 'app/Kernel/Autoloader.php'; \$m = \App\Application\Install\ModuleManifest::fromArray(require 'config/modules/reportes.php'); echo \$m->clave.' '.\$m->version.PHP_EOL;"
```
Expected: `reportes 1.0.0` (sin excepción).

- [ ] **Step 4: Commit**

```bash
git add config/modules/reportes.php config/vertical.php
git commit -m "feat(reportes): add module manifest and vertical toggle

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: `ReporteFuente` value object

VO inmutable construido desde el JSON del programador. Expone columnas, tratamientos válidos por tipo, group_by, order_by, filtros, periodo, max_rows y plantillas por modo.

**Files:**
- Create: `app/Domain/Reporte/ReporteFuente.php`
- Test: `tests/Reporte/ReporteFuenteTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Reporte/ReporteFuenteTest.php`:

```php
<?php
declare(strict_types=1);

use App\Domain\Reporte\ReporteFuente;

function rf_config(): array
{
    return [
        'fuente' => ['key' => 'pedidos', 'title' => 'Pedidos', 'resource' => 'demo_pedidos', 'icon' => 'bi-receipt'],
        'modos'  => ['coleccion'],
        'expose' => [
            'columns' => [
                ['name' => 'cliente', 'label' => 'Cliente', 'type' => 'text'],
                ['name' => 'total',   'label' => 'Total',   'type' => 'money', 'treatments' => ['sum', 'avg', 'min', 'max']],
            ],
            'group_by' => ['cliente'],
            'order_by' => ['total'],
            'filters'  => [['field' => 'estado', 'label' => 'Estado']],
            'period'   => ['field' => 'fecha', 'label' => 'Fecha', 'presets' => ['mes', 'todo']],
            'max_rows' => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => []],
    ];
}

test('ReporteFuente expone metadatos básicos', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_same('pedidos', $f->key());
    assert_same('Pedidos', $f->title());
    assert_same('demo_pedidos', $f->resource());
    assert_same(5000, $f->maxRows());
});

test('ReporteFuente conoce columnas, tipos y etiquetas', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_true($f->hasColumn('total'));
    assert_true(!$f->hasColumn('inexistente'));
    assert_same('money', $f->columnType('total'));
    assert_same('Cliente', $f->columnLabel('cliente'));
});

test('ReporteFuente valida tratamientos por columna', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_true($f->allowsTreatment('total', 'sum'));
    assert_true(!$f->allowsTreatment('total', 'mediana'));
    assert_true(!$f->allowsTreatment('cliente', 'sum'));
});

test('ReporteFuente expone group_by, order_by, filtros y periodo', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_same(['cliente'], $f->groupBy());
    assert_same(['total'], $f->orderBy());
    assert_true($f->hasFilter('estado'));
    assert_true($f->hasPeriod());
    assert_same('fecha', $f->periodField());
    assert_same(['mes', 'todo'], $f->periodPresets());
    assert_same(['tabla_estadistica'], $f->templatesFor('coleccion'));
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/ReporteFuente`
Expected: FAIL — `Class "App\Domain\Reporte\ReporteFuente" not found`.

- [ ] **Step 3: Implementar el VO**

Create `app/Domain/Reporte/ReporteFuente.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Reporte;

/**
 * Fuente reportable declarada por el programador en config/reportes/{key}.json.
 * VO inmutable: expone qué columnas/tratamientos/filtros/periodo puede usar el
 * usuario final, sin tocar la base de datos.
 */
final class ReporteFuente
{
    /** Tratamientos numéricos (no aplican a texto). 'count' aplica a cualquier columna. */
    private const NUMERIC_TYPES = ['money', 'number', 'int', 'integer', 'decimal', 'float'];
    private const NUMERIC_TREATMENTS = ['sum', 'avg', 'min', 'max'];

    /** @param array<string,mixed> $expose @param array<string,mixed> $templates */
    private function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $resource,
        private readonly string $icon,
        private readonly array $columns,
        private readonly array $groupBy,
        private readonly array $orderBy,
        private readonly array $filters,
        private readonly array $period,
        private readonly int $maxRows,
        private readonly array $templates,
    ) {}

    /** @param array<string,mixed> $config */
    public static function fromArray(string $key, array $config): self
    {
        $fuente = is_array($config['fuente'] ?? null) ? $config['fuente'] : [];
        $expose = is_array($config['expose'] ?? null) ? $config['expose'] : [];
        $templates = is_array($config['templates'] ?? null) ? $config['templates'] : [];

        $columns = [];
        foreach (is_array($expose['columns'] ?? null) ? $expose['columns'] : [] as $c) {
            if (!is_array($c) || ($c['name'] ?? '') === '') {
                continue;
            }
            $name = (string) $c['name'];
            $columns[$name] = [
                'name'       => $name,
                'label'      => (string) ($c['label'] ?? $name),
                'type'       => (string) ($c['type'] ?? 'text'),
                'treatments' => array_values(array_map('strval', is_array($c['treatments'] ?? null) ? $c['treatments'] : [])),
            ];
        }

        $filters = [];
        foreach (is_array($expose['filters'] ?? null) ? $expose['filters'] : [] as $f) {
            if (is_array($f) && ($f['field'] ?? '') !== '') {
                $filters[(string) $f['field']] = (string) ($f['label'] ?? $f['field']);
            }
        }

        $period = is_array($expose['period'] ?? null) ? $expose['period'] : [];

        return new self(
            (string) ($fuente['key'] ?? $key),
            (string) ($fuente['title'] ?? $key),
            (string) ($fuente['resource'] ?? ''),
            (string) ($fuente['icon'] ?? 'bi-file-earmark-bar-graph'),
            $columns,
            array_values(array_map('strval', is_array($expose['group_by'] ?? null) ? $expose['group_by'] : [])),
            array_values(array_map('strval', is_array($expose['order_by'] ?? null) ? $expose['order_by'] : [])),
            $filters,
            $period,
            (int) ($expose['max_rows'] ?? 5000),
            [
                'coleccion' => array_values(array_map('strval', is_array($templates['coleccion'] ?? null) ? $templates['coleccion'] : [])),
                'registro'  => array_values(array_map('strval', is_array($templates['registro'] ?? null) ? $templates['registro'] : [])),
            ],
        );
    }

    public function key(): string { return $this->key; }
    public function title(): string { return $this->title; }
    public function resource(): string { return $this->resource; }
    public function icon(): string { return $this->icon; }

    /** @return list<array{name:string,label:string,type:string,treatments:list<string>}> */
    public function columns(): array { return array_values($this->columns); }

    public function hasColumn(string $name): bool { return isset($this->columns[$name]); }
    public function columnLabel(string $name): string { return $this->columns[$name]['label'] ?? $name; }
    public function columnType(string $name): string { return $this->columns[$name]['type'] ?? 'text'; }

    public function allowsTreatment(string $name, string $treatment): bool
    {
        if (!isset($this->columns[$name])) {
            return false;
        }
        if ($treatment === 'count') {
            return in_array('count', $this->columns[$name]['treatments'], true);
        }
        if (!in_array($treatment, self::NUMERIC_TREATMENTS, true)) {
            return false;
        }
        $type = $this->columns[$name]['type'];
        return in_array($type, self::NUMERIC_TYPES, true)
            && in_array($treatment, $this->columns[$name]['treatments'], true);
    }

    /** @return list<string> */
    public function groupBy(): array { return $this->groupBy; }
    /** @return list<string> */
    public function orderBy(): array { return $this->orderBy; }
    public function hasFilter(string $field): bool { return isset($this->filters[$field]); }
    /** @return array<string,string> field => label */
    public function filters(): array { return $this->filters; }

    public function hasPeriod(): bool { return ($this->period['field'] ?? '') !== ''; }
    public function periodField(): string { return (string) ($this->period['field'] ?? ''); }
    public function periodLabel(): string { return (string) ($this->period['label'] ?? 'Fecha'); }
    /** @return list<string> */
    public function periodPresets(): array
    {
        return array_values(array_map('strval', is_array($this->period['presets'] ?? null) ? $this->period['presets'] : []));
    }

    public function maxRows(): int { return $this->maxRows; }

    /** @return list<string> */
    public function templatesFor(string $mode): array { return $this->templates[$mode] ?? []; }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/ReporteFuente`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Reporte/ReporteFuente.php tests/Reporte/ReporteFuenteTest.php
git commit -m "feat(reportes): add ReporteFuente value object

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: `ReporteConfigValidator`

Valida un `config/reportes/{key}.json` contra las columnas reales del recurso CRUD (que pasa el loader en Task 4). Espejo de `CalendarConfigValidator`.

**Files:**
- Create: `app/Application/Reporte/ReporteConfigValidator.php`
- Test: `tests/Reporte/ReporteConfigValidatorTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Reporte/ReporteConfigValidatorTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Reporte\ReporteConfigValidator;
use App\Domain\Exceptions\ValidationException;

function rcv_valid_config(): array
{
    return [
        'fuente' => ['key' => 'pedidos', 'resource' => 'demo_pedidos'],
        'expose' => [
            'columns' => [
                ['name' => 'cliente', 'label' => 'Cliente', 'type' => 'text'],
                ['name' => 'total',   'label' => 'Total',   'type' => 'money', 'treatments' => ['sum']],
            ],
            'group_by' => ['cliente'],
            'order_by' => ['total'],
            'filters'  => [['field' => 'estado', 'label' => 'Estado']],
            'period'   => ['field' => 'fecha', 'presets' => ['mes', 'todo']],
            'max_rows' => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica']],
    ];
}

$columns = ['cliente', 'total', 'estado', 'fecha'];

test('config válida no lanza', function () use ($columns): void {
    (new ReporteConfigValidator())->validate(rcv_valid_config(), $columns);
    assert_true(true);
});

test('columna inexistente lanza ValidationException', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['columns'][] = ['name' => 'fantasma', 'type' => 'text'];
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});

test('columna protegida lanza ValidationException', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['columns'][] = ['name' => 'created_by', 'type' => 'number'];
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, array_merge($columns, ['created_by'])));
});

test('tratamiento numérico sobre columna de texto lanza', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['columns'][0]['treatments'] = ['sum']; // cliente es text
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});

test('preset de periodo no soportado lanza', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['period']['presets'] = ['decada'];
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});

test('max_rows ausente lanza', function () use ($columns): void {
    $cfg = rcv_valid_config();
    unset($cfg['expose']['max_rows']);
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/ReporteConfigValidator`
Expected: FAIL — `Class "App\Application\Reporte\ReporteConfigValidator" not found`.

- [ ] **Step 3: Implementar el validador**

Create `app/Application/Reporte/ReporteConfigValidator.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Domain\Exceptions\ValidationException;

/**
 * Valida la configuración de una fuente reportable contra las columnas reales del
 * recurso CRUD. Espejo de CalendarConfigValidator: acumula errores y lanza uno solo.
 */
final class ReporteConfigValidator
{
    private const PROTECTED = [
        'id', 'created_at', 'created_by', 'updated_at', 'updated_by',
        'deleted', 'deleted_at', 'deleted_by',
    ];
    private const NUMERIC_TYPES = ['money', 'number', 'int', 'integer', 'decimal', 'float'];
    private const NUMERIC_TREATMENTS = ['sum', 'avg', 'min', 'max'];
    private const VALID_PRESETS = ['hoy', 'semana', 'mes', 'anio', 'ayer', 'mes_pasado', 'anio_pasado', 'todo'];

    /**
     * @param array<string,mixed> $config
     * @param list<string> $availableColumns columnas conocidas del recurso CRUD
     */
    public function validate(array $config, array $availableColumns): void
    {
        $errors = [];
        $known = array_fill_keys($availableColumns, true);

        $fuente = is_array($config['fuente'] ?? null) ? $config['fuente'] : [];
        if (($fuente['key'] ?? '') === '' || ($fuente['resource'] ?? '') === '') {
            $errors[] = 'fuente.key y fuente.resource son obligatorios.';
        }

        $expose = is_array($config['expose'] ?? null) ? $config['expose'] : [];

        $columnTypes = [];
        $cols = is_array($expose['columns'] ?? null) ? $expose['columns'] : [];
        if ($cols === []) {
            $errors[] = 'expose.columns debe listar al menos una columna.';
        }
        foreach ($cols as $i => $c) {
            if (!is_array($c) || ($c['name'] ?? '') === '') {
                $errors[] = "expose.columns[{$i}].name es obligatorio.";
                continue;
            }
            $name = (string) $c['name'];
            $type = (string) ($c['type'] ?? 'text');
            $columnTypes[$name] = $type;

            if (in_array($name, self::PROTECTED, true)) {
                $errors[] = "expose.columns[{$i}] usa la columna protegida '{$name}'.";
            } elseif (!isset($known[$name])) {
                $errors[] = "expose.columns[{$i}] ('{$name}') no existe en el recurso.";
            }

            foreach (is_array($c['treatments'] ?? null) ? $c['treatments'] : [] as $t) {
                $t = (string) $t;
                if ($t === 'count') {
                    continue;
                }
                if (!in_array($t, self::NUMERIC_TREATMENTS, true)) {
                    $errors[] = "Tratamiento inválido '{$t}' en columna '{$name}'.";
                } elseif (!in_array($type, self::NUMERIC_TYPES, true)) {
                    $errors[] = "Tratamiento '{$t}' requiere columna numérica; '{$name}' es '{$type}'.";
                }
            }
        }

        foreach (['group_by', 'order_by'] as $listKey) {
            foreach (is_array($expose[$listKey] ?? null) ? $expose[$listKey] : [] as $col) {
                if (!isset($known[(string) $col])) {
                    $errors[] = "expose.{$listKey} contiene '{$col}', que no existe en el recurso.";
                }
            }
        }

        foreach (is_array($expose['filters'] ?? null) ? $expose['filters'] : [] as $i => $f) {
            $field = is_array($f) ? (string) ($f['field'] ?? '') : '';
            if ($field === '') {
                $errors[] = "expose.filters[{$i}].field es obligatorio.";
            } elseif (!isset($known[$field])) {
                $errors[] = "expose.filters[{$i}].field ('{$field}') no existe en el recurso.";
            }
        }

        $period = is_array($expose['period'] ?? null) ? $expose['period'] : [];
        if ($period !== []) {
            $pf = (string) ($period['field'] ?? '');
            if ($pf === '' || !isset($known[$pf])) {
                $errors[] = "expose.period.field ('{$pf}') no existe en el recurso.";
            }
            foreach (is_array($period['presets'] ?? null) ? $period['presets'] : [] as $p) {
                if (!in_array((string) $p, self::VALID_PRESETS, true)) {
                    $errors[] = "expose.period.presets contiene preset no soportado ('{$p}').";
                }
            }
        }

        if (!isset($expose['max_rows']) || (int) $expose['max_rows'] <= 0) {
            $errors[] = 'expose.max_rows es obligatorio y debe ser mayor que 0.';
        }

        if ($errors !== []) {
            throw new ValidationException('Configuración de reporte inválida.', $errors);
        }
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/ReporteConfigValidator`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Reporte/ReporteConfigValidator.php tests/Reporte/ReporteConfigValidatorTest.php
git commit -m "feat(reportes): add ReporteConfigValidator

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: `ReporteConfigLoader` + fuente demo `citas.json`

Carga y cachea fuentes desde `config/reportes/`, resolviendo el `CrudResourceDefinition` del recurso para validar columnas. Espejo de `CalendarConfigLoader`.

**Files:**
- Create: `app/Application/Reporte/ReporteConfigLoader.php`
- Create: `config/reportes/citas.json`
- Test: `tests/Reporte/ReporteConfigLoaderTest.php`

- [ ] **Step 1: Crear la fuente demo**

Create `config/reportes/citas.json` (apunta al recurso CRUD existente `demo_citas`):

```json
{
  "fuente": { "key": "citas", "title": "Citas", "resource": "demo_citas", "icon": "bi-calendar-check" },
  "modos": ["coleccion"],
  "expose": {
    "columns": [
      { "name": "cliente",  "label": "Cliente",  "type": "text" },
      { "name": "servicio", "label": "Servicio", "type": "text" },
      { "name": "estado",   "label": "Estado",   "type": "text" }
    ],
    "group_by": ["estado", "servicio"],
    "order_by": ["estado"],
    "filters": [ { "field": "estado", "label": "Estado" } ],
    "period": { "field": "fecha_inicio", "label": "Fecha", "presets": ["hoy", "semana", "mes", "anio", "ayer", "mes_pasado", "anio_pasado", "todo"] },
    "max_rows": 5000
  },
  "templates": { "coleccion": ["tabla_estadistica"], "registro": [] }
}
```

- [ ] **Step 2: Escribir el test que falla**

Create `tests/Reporte/ReporteConfigLoaderTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Reporte\ReporteConfigValidator;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteFuente;

function rcl_loader(): ReporteConfigLoader
{
    return new ReporteConfigLoader(new ReporteConfigValidator());
}

test('carga la fuente demo citas y devuelve un ReporteFuente', function (): void {
    $f = rcl_loader()->load('citas');
    assert_true($f instanceof ReporteFuente);
    assert_same('demo_citas', $f->resource());
    assert_true($f->hasColumn('estado'));
    assert_same('fecha_inicio', $f->periodField());
});

test('una clave inexistente lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => rcl_loader()->load('no_existe'));
});

test('listFuentes incluye la fuente demo', function (): void {
    $fuentes = rcl_loader()->listFuentes();
    assert_true(array_key_exists('citas', $fuentes));
    assert_same('Citas', $fuentes['citas']);
});
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/ReporteConfigLoader`
Expected: FAIL — `Class "App\Application\Reporte\ReporteConfigLoader" not found`.

- [ ] **Step 4: Implementar el loader**

Create `app/Application/Reporte/ReporteConfigLoader.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteFuente;
use App\Kernel\Logging\AppLogger;

/**
 * Carga fuentes reportables desde config/reportes/{key}.json, validándolas contra las
 * columnas declaradas del recurso CRUD (sin tocar la base de datos). Espejo de
 * CalendarConfigLoader.
 */
final class ReporteConfigLoader
{
    private const DIR = ROOT_PATH . '/config/reportes';
    private const CRUD_DIR = ROOT_PATH . '/config/cruds';

    /** @var array<string, ReporteFuente> */
    private array $cache = [];

    public function __construct(
        private readonly ReporteConfigValidator $validator,
    ) {}

    public function load(string $key): ReporteFuente
    {
        $key = trim($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $file = self::DIR . '/' . $key . '.json';
        if ($key === '' || !is_readable($file)) {
            throw new ValidationException("No existe configuración de reporte para {$key}.");
        }

        $raw = file_get_contents($file);
        $config = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($config)) {
            AppLogger::error('Reporte config: JSON inválido', ['key' => $key, 'file' => $file]);
            throw new ValidationException("El JSON de {$key}.json es inválido.");
        }

        $resource = (string) ($config['fuente']['resource'] ?? '');
        $columns = $this->crudDefinition($resource)->columnNames();

        $this->validator->validate($config, $columns);

        $fuente = ReporteFuente::fromArray($key, $config);
        $this->cache[$key] = $fuente;
        return $fuente;
    }

    /**
     * Definición del recurso CRUD subyacente, construida desde su JSON (sin BD).
     */
    public function crudDefinition(string $resource): CrudResourceDefinition
    {
        $resource = trim($resource);
        $file = self::CRUD_DIR . '/' . $resource . '.json';
        if ($resource === '' || !is_readable($file)) {
            throw new ValidationException("No existe configuración CRUD para el recurso {$resource}.");
        }

        $raw = file_get_contents($file);
        $crudConfig = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($crudConfig)) {
            throw new ValidationException("El JSON del recurso CRUD {$resource}.json es inválido.");
        }

        return CrudResourceDefinition::fromArray($crudConfig);
    }

    /** @return array<string,string> key => título */
    public function listFuentes(): array
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
                AppLogger::warning('Reporte config inválida omitida', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        return $out;
    }
}
```

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/ReporteConfigLoader`
Expected: PASS (3 tests). (Requiere que exista `config/cruds/demo_citas.json`, provisto por el módulo Calendario.)

- [ ] **Step 6: Commit**

```bash
git add app/Application/Reporte/ReporteConfigLoader.php config/reportes/citas.json tests/Reporte/ReporteConfigLoaderTest.php
git commit -m "feat(reportes): add ReporteConfigLoader and demo citas source

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: `PeriodoResolver` (preset → rango)

Servicio puro: convierte un preset de periodo en un rango concreto `[from, to]` (formato `Y-m-d H:i:s`) más una etiqueta legible. Acepta un `now` inyectable para tests deterministas.

**Files:**
- Create: `app/Application/Reporte/PeriodoResolver.php`
- Test: `tests/Reporte/PeriodoResolverTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Reporte/PeriodoResolverTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Reporte\PeriodoResolver;

function pr_now(): DateTimeImmutable
{
    // Domingo 14 de junio de 2026, 15:00.
    return new DateTimeImmutable('2026-06-14 15:00:00');
}

test('mes devuelve el primer y último día del mes vigente', function (): void {
    $r = (new PeriodoResolver())->resolve('mes', pr_now());
    assert_same('2026-06-01 00:00:00', $r['from']);
    assert_same('2026-06-30 23:59:59', $r['to']);
    assert_same('Este mes', $r['label']);
});

test('hoy cubre el día completo', function (): void {
    $r = (new PeriodoResolver())->resolve('hoy', pr_now());
    assert_same('2026-06-14 00:00:00', $r['from']);
    assert_same('2026-06-14 23:59:59', $r['to']);
});

test('semana va de lunes a domingo', function (): void {
    $r = (new PeriodoResolver())->resolve('semana', pr_now());
    assert_same('2026-06-08 00:00:00', $r['from']);
    assert_same('2026-06-14 23:59:59', $r['to']);
});

test('mes_pasado devuelve mayo completo', function (): void {
    $r = (new PeriodoResolver())->resolve('mes_pasado', pr_now());
    assert_same('2026-05-01 00:00:00', $r['from']);
    assert_same('2026-05-31 23:59:59', $r['to']);
});

test('anio devuelve el año vigente completo', function (): void {
    $r = (new PeriodoResolver())->resolve('anio', pr_now());
    assert_same('2026-01-01 00:00:00', $r['from']);
    assert_same('2026-12-31 23:59:59', $r['to']);
});

test('anio_pasado devuelve 2025 completo', function (): void {
    $r = (new PeriodoResolver())->resolve('anio_pasado', pr_now());
    assert_same('2025-01-01 00:00:00', $r['from']);
    assert_same('2025-12-31 23:59:59', $r['to']);
});

test('ayer cubre el día anterior', function (): void {
    $r = (new PeriodoResolver())->resolve('ayer', pr_now());
    assert_same('2026-06-13 00:00:00', $r['from']);
    assert_same('2026-06-13 23:59:59', $r['to']);
});

test('todo abarca un rango amplio con etiqueta Todo', function (): void {
    $r = (new PeriodoResolver())->resolve('todo', pr_now());
    assert_same('1970-01-01 00:00:00', $r['from']);
    assert_same('2999-12-31 23:59:59', $r['to']);
    assert_same('Todo', $r['label']);
});

test('preset desconocido cae a todo', function (): void {
    $r = (new PeriodoResolver())->resolve('decada', pr_now());
    assert_same('1970-01-01 00:00:00', $r['from']);
    assert_same('Todo', $r['label']);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/PeriodoResolver`
Expected: FAIL — `Class "App\Application\Reporte\PeriodoResolver" not found`.

- [ ] **Step 3: Implementar el resolver**

Create `app/Application/Reporte/PeriodoResolver.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

/**
 * Convierte un preset de periodo en un rango concreto [from, to] (Y-m-d H:i:s) y una
 * etiqueta legible. Puro: el `now` es inyectable para pruebas deterministas. Los
 * presets relativos se recalculan en cada generación.
 */
final class PeriodoResolver
{
    private const FMT = 'Y-m-d H:i:s';

    /** @return array{from:string,to:string,label:string} */
    public function resolve(string $preset, ?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable('now');

        switch ($preset) {
            case 'hoy':
                return $this->range($now, $now, 'Hoy');

            case 'ayer':
                $y = $now->modify('-1 day');
                return $this->range($y, $y, 'Ayer');

            case 'semana':
                $dow = (int) $now->format('N'); // 1 (lunes) .. 7 (domingo)
                $start = $now->modify('-' . ($dow - 1) . ' days');
                $end = $start->modify('+6 days');
                return $this->range($start, $end, 'Esta semana');

            case 'mes':
                return $this->range(
                    $now->modify('first day of this month'),
                    $now->modify('last day of this month'),
                    'Este mes'
                );

            case 'mes_pasado':
                return $this->range(
                    $now->modify('first day of last month'),
                    $now->modify('last day of last month'),
                    'Mes pasado'
                );

            case 'anio':
                $y = (int) $now->format('Y');
                return $this->range($now->setDate($y, 1, 1), $now->setDate($y, 12, 31), 'Este año');

            case 'anio_pasado':
                $y = (int) $now->format('Y') - 1;
                return $this->range($now->setDate($y, 1, 1), $now->setDate($y, 12, 31), 'Año pasado');

            case 'todo':
            default:
                return [
                    'from'  => '1970-01-01 00:00:00',
                    'to'    => '2999-12-31 23:59:59',
                    'label' => 'Todo',
                ];
        }
    }

    /** @return array{from:string,to:string,label:string} */
    private function range(\DateTimeImmutable $from, \DateTimeImmutable $to, string $label): array
    {
        return [
            'from'  => $from->setTime(0, 0, 0)->format(self::FMT),
            'to'    => $to->setTime(23, 59, 59)->format(self::FMT),
            'label' => $label,
        ];
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/PeriodoResolver`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Reporte/PeriodoResolver.php tests/Reporte/PeriodoResolverTest.php
git commit -m "feat(reportes): add PeriodoResolver

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: `ReporteAggregator` (tratamientos)

Servicio puro: dadas filas crudas, columnas seleccionadas y los tratamientos guardados, produce `columns` (con `format`), `rows` y `totals` listos para `PdfDataTable`/`PdfTotalsBlock`.

Estructura de `tratamientos` (JSON guardado):
```
{
  "group_by": ["estado"],
  "aggregations": [ { "op": "sum", "column": "total" }, { "op": "count", "column": "" } ],
  "order": { "by": "estado", "dir": "asc" }
}
```

**Files:**
- Create: `app/Application/Reporte/ReporteAggregator.php`
- Test: `tests/Reporte/ReporteAggregatorTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Reporte/ReporteAggregatorTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Reporte\ReporteAggregator;

function ra_rows(): array
{
    return [
        ['estado' => 'pagado',    'cliente' => 'Ana', 'total' => 100],
        ['estado' => 'pagado',    'cliente' => 'Ana', 'total' => 50],
        ['estado' => 'pendiente', 'cliente' => 'Beto', 'total' => 30],
    ];
}

function ra_columns(): array
{
    return [
        ['name' => 'estado', 'label' => 'Estado', 'type' => 'text'],
        ['name' => 'total',  'label' => 'Total',  'type' => 'money'],
    ];
}

test('listado plano sin tratamientos devuelve filas tal cual', function (): void {
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), []);
    assert_same(3, count($out['rows']));
    assert_same('money', $out['columns'][1]['format']);
    assert_same(100, $out['rows'][0]['total']);
    assert_same([], $out['totals']);
});

test('agrupa por estado y suma total', function (): void {
    $trat = [
        'group_by' => ['estado'],
        'aggregations' => [['op' => 'sum', 'column' => 'total']],
        'order' => ['by' => 'estado', 'dir' => 'asc'],
    ];
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), $trat);

    // dos grupos: pagado (150), pendiente (30), ordenados asc por estado
    assert_same(2, count($out['rows']));
    assert_same('pagado', $out['rows'][0]['estado']);
    assert_same(150.0, $out['rows'][0]['sum_total']);
    assert_same(30.0, $out['rows'][1]['sum_total']);

    // columnas de salida: estado + "Suma de Total" (money)
    assert_same('estado', $out['columns'][0]['name']);
    assert_same('sum_total', $out['columns'][1]['name']);
    assert_same('Suma de Total', $out['columns'][1]['label']);
    assert_same('money', $out['columns'][1]['format']);

    // total global
    assert_same(180.0, $out['totals'][0]['value']);
    assert_same('money', $out['totals'][0]['format']);
});

test('count cuenta filas del grupo y se formatea como number', function (): void {
    $trat = [
        'group_by' => ['estado'],
        'aggregations' => [['op' => 'count', 'column' => '']],
        'order' => ['by' => 'estado', 'dir' => 'desc'],
    ];
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), $trat);

    // orden desc: pendiente primero
    assert_same('pendiente', $out['rows'][0]['estado']);
    assert_same(1, $out['rows'][0]['count']);
    assert_same(2, $out['rows'][1]['count']);
    assert_same('number', $out['columns'][1]['format']);
    assert_same('Cantidad', $out['columns'][1]['label']);
    assert_same(3, $out['totals'][0]['value']);
});

test('avg, min y max operan sobre el grupo', function (): void {
    $trat = [
        'group_by' => ['estado'],
        'aggregations' => [
            ['op' => 'avg', 'column' => 'total'],
            ['op' => 'min', 'column' => 'total'],
            ['op' => 'max', 'column' => 'total'],
        ],
        'order' => ['by' => 'estado', 'dir' => 'asc'],
    ];
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), $trat);

    // grupo pagado: avg 75, min 50, max 100
    assert_same(75.0, $out['rows'][0]['avg_total']);
    assert_same(50.0, $out['rows'][0]['min_total']);
    assert_same(100.0, $out['rows'][0]['max_total']);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/ReporteAggregator`
Expected: FAIL — `Class "App\Application\Reporte\ReporteAggregator" not found`.

- [ ] **Step 3: Implementar el agregador**

Create `app/Application/Reporte/ReporteAggregator.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

/**
 * Aplica tratamientos (agrupar / sum / count / avg / min / max / ordenar) a filas
 * crudas y produce columnas, filas y totales listos para PdfDataTable/PdfTotalsBlock.
 * Puro: sin BD, sin estado. La agregación ocurre en PHP sobre lo que ya devolvió el
 * recurso CRUD (mismo principio que list.summaries del CRUD Engine).
 */
final class ReporteAggregator
{
    private const NUMERIC_TYPES = ['money', 'number', 'int', 'integer', 'decimal', 'float'];
    private const OP_LABELS = [
        'sum'   => 'Suma de',
        'avg'   => 'Promedio de',
        'min'   => 'Mínimo de',
        'max'   => 'Máximo de',
        'count' => 'Cantidad',
    ];

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,label:string,type:string}> $columns
     * @param array<string,mixed> $tratamientos
     * @return array{columns:list<array{name:string,label:string,format:string}>,rows:list<array<string,mixed>>,totals:list<array{label:string,value:mixed,format:string}>}
     */
    public function apply(array $rows, array $columns, array $tratamientos): array
    {
        $byName = [];
        foreach ($columns as $c) {
            $byName[(string) $c['name']] = $c;
        }

        $groupBy = array_values(array_map('strval', is_array($tratamientos['group_by'] ?? null) ? $tratamientos['group_by'] : []));
        $aggs = is_array($tratamientos['aggregations'] ?? null) ? $tratamientos['aggregations'] : [];
        $order = is_array($tratamientos['order'] ?? null) ? $tratamientos['order'] : null;

        if ($groupBy === [] && $aggs === []) {
            return $this->plainList($rows, $columns, $order);
        }

        return $this->grouped($rows, $byName, $groupBy, $aggs, $order);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,label:string,type:string}> $columns
     * @param array<string,mixed>|null $order
     */
    private function plainList(array $rows, array $columns, ?array $order): array
    {
        $outCols = [];
        foreach ($columns as $c) {
            $outCols[] = [
                'name'   => (string) $c['name'],
                'label'  => (string) $c['label'],
                'format' => $this->formatFor((string) ($c['type'] ?? 'text')),
            ];
        }

        $names = array_map(static fn(array $c): string => (string) $c['name'], $columns);
        $outRows = [];
        foreach ($rows as $row) {
            $picked = [];
            foreach ($names as $n) {
                $picked[$n] = $row[$n] ?? '';
            }
            $outRows[] = $picked;
        }

        $outRows = $this->sortRows($outRows, $order);

        return ['columns' => $outCols, 'rows' => $outRows, 'totals' => []];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array{name:string,label:string,type:string}> $byName
     * @param list<string> $groupBy
     * @param list<mixed> $aggs
     * @param array<string,mixed>|null $order
     */
    private function grouped(array $rows, array $byName, array $groupBy, array $aggs, ?array $order): array
    {
        // Columnas de salida: group_by + una por agregación.
        $outCols = [];
        foreach ($groupBy as $g) {
            $outCols[] = [
                'name'   => $g,
                'label'  => (string) ($byName[$g]['label'] ?? $g),
                'format' => $this->formatFor((string) ($byName[$g]['type'] ?? 'text')),
            ];
        }

        $normAggs = [];
        foreach ($aggs as $a) {
            if (!is_array($a)) {
                continue;
            }
            $op = (string) ($a['op'] ?? '');
            if (!isset(self::OP_LABELS[$op])) {
                continue;
            }
            $col = (string) ($a['column'] ?? '');
            $name = $op === 'count' || $col === '' ? ($op === 'count' ? 'count' : $op) : $op . '_' . $col;
            $label = $op === 'count'
                ? self::OP_LABELS['count']
                : self::OP_LABELS[$op] . ' ' . (string) ($byName[$col]['label'] ?? $col);
            $format = $op === 'count'
                ? 'number'
                : $this->formatFor((string) ($byName[$col]['type'] ?? 'number'));

            $normAggs[] = ['op' => $op, 'column' => $col, 'name' => $name, 'label' => $label, 'format' => $format];
            $outCols[] = ['name' => $name, 'label' => $label, 'format' => $format];
        }

        // Agrupar filas.
        $buckets = [];
        foreach ($rows as $row) {
            $keyParts = [];
            foreach ($groupBy as $g) {
                $keyParts[] = (string) ($row[$g] ?? '');
            }
            $buckets[implode("\x1f", $keyParts)][] = $row;
        }

        $outRows = [];
        foreach ($buckets as $bucketRows) {
            $first = $bucketRows[0];
            $outRow = [];
            foreach ($groupBy as $g) {
                $outRow[$g] = $first[$g] ?? '';
            }
            foreach ($normAggs as $agg) {
                $outRow[$agg['name']] = $this->compute($agg['op'], $agg['column'], $bucketRows);
            }
            $outRows[] = $outRow;
        }

        $outRows = $this->sortRows($outRows, $order);

        // Totales globales (sobre todas las filas).
        $totals = [];
        foreach ($normAggs as $agg) {
            $totals[] = [
                'label'  => $agg['label'],
                'value'  => $this->compute($agg['op'], $agg['column'], $rows),
                'format' => $agg['format'],
            ];
        }

        return ['columns' => $outCols, 'rows' => $outRows, 'totals' => $totals];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return float|int
     */
    private function compute(string $op, string $column, array $rows)
    {
        if ($op === 'count') {
            return count($rows);
        }

        $values = [];
        foreach ($rows as $row) {
            if (array_key_exists($column, $row) && $row[$column] !== null && $row[$column] !== '') {
                $values[] = (float) $row[$column];
            }
        }
        if ($values === []) {
            return 0.0;
        }

        return match ($op) {
            'sum' => array_sum($values),
            'avg' => array_sum($values) / count($values),
            'min' => min($values),
            'max' => max($values),
            default => 0.0,
        };
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed>|null $order
     * @return list<array<string,mixed>>
     */
    private function sortRows(array $rows, ?array $order): array
    {
        $by = (string) ($order['by'] ?? '');
        if ($by === '') {
            return $rows;
        }
        $dir = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? -1 : 1;

        usort($rows, static function (array $a, array $b) use ($by, $dir): int {
            $va = $a[$by] ?? null;
            $vb = $b[$by] ?? null;
            if (is_numeric($va) && is_numeric($vb)) {
                return ((float) $va <=> (float) $vb) * $dir;
            }
            return strcmp((string) $va, (string) $vb) * $dir;
        });

        return $rows;
    }

    private function formatFor(string $type): string
    {
        if (in_array($type, self::NUMERIC_TYPES, true)) {
            return $type === 'money' ? 'money' : 'number';
        }
        return match ($type) {
            'date'     => 'date',
            'datetime' => 'datetime',
            default    => 'raw',
        };
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/ReporteAggregator`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Reporte/ReporteAggregator.php tests/Reporte/ReporteAggregatorTest.php
git commit -m "feat(reportes): add ReporteAggregator for column treatments

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: `ReporteGuardado` entidad

Entidad del reporte guardado por el usuario. Hidrata desde una fila de `rep_reportes` (con columnas JSON) y expone la selección. Inmutable.

**Files:**
- Create: `app/Domain/Reporte/ReporteGuardado.php`
- Test: `tests/Reporte/ReporteGuardadoTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Reporte/ReporteGuardadoTest.php`:

```php
<?php
declare(strict_types=1);

use App\Domain\Reporte\ReporteGuardado;

function rg_row(): array
{
    return [
        'id' => 7,
        'clave' => 'citas_demo',
        'nombre' => 'Citas por estado',
        'fuente_key' => 'citas',
        'modo' => 'coleccion',
        'columnas' => json_encode([['name' => 'estado', 'label' => 'Estado', 'type' => 'text']]),
        'tratamientos' => json_encode(['group_by' => ['estado'], 'aggregations' => [['op' => 'count', 'column' => '']], 'order' => ['by' => 'estado', 'dir' => 'asc']]),
        'filtros' => json_encode([]),
        'periodo' => json_encode(['preset' => 'mes']),
        'opciones' => json_encode(['titulo' => 'Citas por estado', 'orientacion' => 'portrait']),
        'template_key' => 'tabla_estadistica',
        'compartido' => 1,
        'created_by' => 3,
    ];
}

test('hidrata desde una fila decodificando JSON', function (): void {
    $r = ReporteGuardado::fromRow(rg_row());
    assert_same(7, $r->id());
    assert_same('citas', $r->fuenteKey());
    assert_same('coleccion', $r->modo());
    assert_same('estado', $r->columnas()[0]['name']);
    assert_same(['estado'], $r->tratamientos()['group_by']);
    assert_same('mes', $r->periodo()['preset']);
    assert_same('Citas por estado', $r->opciones()['titulo']);
    assert_same('tabla_estadistica', $r->templateKey());
    assert_true($r->compartido());
    assert_same(3, $r->createdBy());
});

test('JSON inválido degrada a array vacío sin romper', function (): void {
    $row = rg_row();
    $row['filtros'] = 'no-json';
    $r = ReporteGuardado::fromRow($row);
    assert_same([], $r->filtros());
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/ReporteGuardado`
Expected: FAIL — `Class "App\Domain\Reporte\ReporteGuardado" not found`.

- [ ] **Step 3: Implementar la entidad**

Create `app/Domain/Reporte/ReporteGuardado.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Reporte;

/**
 * Reporte guardado por un usuario: su selección (columnas, tratamientos, filtros,
 * periodo, opciones) sobre una fuente reportable. Hidrata desde una fila de
 * rep_reportes decodificando las columnas JSON.
 */
final class ReporteGuardado
{
    /**
     * @param list<array<string,mixed>> $columnas
     * @param array<string,mixed> $tratamientos
     * @param array<string,mixed> $filtros
     * @param array<string,mixed> $periodo
     * @param array<string,mixed> $opciones
     */
    public function __construct(
        private readonly int $id,
        private readonly string $nombre,
        private readonly string $fuenteKey,
        private readonly string $modo,
        private readonly array $columnas,
        private readonly array $tratamientos,
        private readonly array $filtros,
        private readonly array $periodo,
        private readonly array $opciones,
        private readonly string $templateKey,
        private readonly bool $compartido,
        private readonly ?int $createdBy,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['nombre'] ?? ''),
            (string) ($row['fuente_key'] ?? ''),
            (string) ($row['modo'] ?? 'coleccion'),
            self::decodeList($row['columnas'] ?? null),
            self::decodeMap($row['tratamientos'] ?? null),
            self::decodeMap($row['filtros'] ?? null),
            self::decodeMap($row['periodo'] ?? null),
            self::decodeMap($row['opciones'] ?? null),
            (string) ($row['template_key'] ?? ''),
            (bool) ($row['compartido'] ?? false),
            isset($row['created_by']) ? (int) $row['created_by'] : null,
        );
    }

    /** @return list<array<string,mixed>> */
    private static function decodeList(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @return array<string,mixed> */
    private static function decodeMap(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        return is_array($decoded) ? $decoded : [];
    }

    public function id(): int { return $this->id; }
    public function nombre(): string { return $this->nombre; }
    public function fuenteKey(): string { return $this->fuenteKey; }
    public function modo(): string { return $this->modo; }
    /** @return list<array<string,mixed>> */
    public function columnas(): array { return $this->columnas; }
    /** @return array<string,mixed> */
    public function tratamientos(): array { return $this->tratamientos; }
    /** @return array<string,mixed> */
    public function filtros(): array { return $this->filtros; }
    /** @return array<string,mixed> */
    public function periodo(): array { return $this->periodo; }
    /** @return array<string,mixed> */
    public function opciones(): array { return $this->opciones; }
    public function templateKey(): string { return $this->templateKey; }
    public function compartido(): bool { return $this->compartido; }
    public function createdBy(): ?int { return $this->createdBy; }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/ReporteGuardado`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Reporte/ReporteGuardado.php tests/Reporte/ReporteGuardadoTest.php
git commit -m "feat(reportes): add ReporteGuardado entity

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: Interfaces de Domain (`ReporteTemplateInterface`, `ReporteDataSourceInterface`, `ReporteRepositoryInterface`)

**Files:**
- Create: `app/Domain/Reporte/ReporteTemplateInterface.php`
- Create: `app/Domain/Reporte/ReporteDataSourceInterface.php`
- Create: `app/Domain/Interfaces/ReporteRepositoryInterface.php`

No test propio — se ejercitan en las tareas siguientes.

- [ ] **Step 1: Crear `ReporteTemplateInterface`**

Create `app/Domain/Reporte/ReporteTemplateInterface.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Reporte;

use App\Domain\Pdf\PdfTemplateInterface;

/**
 * Plantilla de reporte: además de componer un PdfDocument (PdfTemplateInterface),
 * declara el "schema de pasos" que el wizard usa para mostrar/ocultar pasos.
 */
interface ReporteTemplateInterface extends PdfTemplateInterface
{
    /**
     * @return array{modo:string,requiere_periodo:bool,permite_tratamientos:bool,min_columnas:int,max_columnas:int}
     */
    public function schemaPasos(): array;
}
```

- [ ] **Step 2: Crear `ReporteDataSourceInterface`**

Create `app/Domain/Reporte/ReporteDataSourceInterface.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Reporte;

use App\Domain\Entities\CrudResourceDefinition;

/**
 * Frontera de lectura de datos para reportes. La implementación real adapta
 * CrudDataService (scope + filtros del CRUD Engine); los tests inyectan un doble.
 */
interface ReporteDataSourceInterface
{
    /**
     * Filas del recurso dentro de [from, to] sobre $dateColumn, respetando el scope.
     *
     * @param array<string,mixed> $filters columna => valor (igualdad)
     * @return list<array<string,mixed>>
     */
    public function rows(
        CrudResourceDefinition $definition,
        string $dateColumn,
        string $from,
        string $to,
        ?int $userId,
        ?callable $can,
        array $filters
    ): array;
}
```

- [ ] **Step 3: Crear `ReporteRepositoryInterface`**

Create `app/Domain/Interfaces/ReporteRepositoryInterface.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Reporte\ReporteGuardado;

/**
 * Persistencia de reportes guardados (rep_reportes). El scope owner (propios +
 * compartidos) se resuelve aquí; un reporte ajeno no compartido no se devuelve.
 */
interface ReporteRepositoryInterface
{
    /** Reporte visible para el usuario (propio o compartido), o null si no aplica. */
    public function findVisible(int $id, int $userId): ?ReporteGuardado;

    /** @return list<array<string,mixed>> filas crudas para el índice (propios + compartidos). */
    public function listForUser(int $userId): array;

    /** @param array<string,mixed> $data columnas de rep_reportes (JSON ya serializado). */
    public function create(array $data): int;

    /** @param array<string,mixed> $data */
    public function update(int $id, int $userId, array $data): void;

    public function softDelete(int $id, int $userId): void;
}
```

- [ ] **Step 4: Verificar que parsean**

Run:
```bash
php -l app/Domain/Reporte/ReporteTemplateInterface.php && php -l app/Domain/Reporte/ReporteDataSourceInterface.php && php -l app/Domain/Interfaces/ReporteRepositoryInterface.php
```
Expected: `No syntax errors detected` en los tres.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Reporte/ReporteTemplateInterface.php app/Domain/Reporte/ReporteDataSourceInterface.php app/Domain/Interfaces/ReporteRepositoryInterface.php
git commit -m "feat(reportes): add domain interfaces for templates, data source and repository

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: `TablaEstadisticaTemplate` (plantilla demo) + registro en whitelist

Plantilla de colección que compone el PDF desde el payload del builder. Implementa `ReporteTemplateInterface`.

**Files:**
- Create: `app/Application/Pdf/Templates/TablaEstadisticaTemplate.php`
- Modify: `config/pdf_templates.php`
- Test: `tests/Reporte/TablaEstadisticaTemplateTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Reporte/TablaEstadisticaTemplateTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Pdf\Templates\TablaEstadisticaTemplate;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Reporte\ReporteTemplateInterface;

function tet_payload(): array
{
    return [
        'title' => 'Citas por estado',
        'period' => 'Este mes',
        'orientation' => 'portrait',
        'columns' => [
            ['name' => 'estado', 'label' => 'Estado', 'format' => 'raw'],
            ['name' => 'count', 'label' => 'Cantidad', 'format' => 'number'],
        ],
        'rows' => [
            ['estado' => 'pagado', 'count' => 2],
            ['estado' => 'pendiente', 'count' => 1],
        ],
        'totals' => [['label' => 'Cantidad', 'value' => 3, 'format' => 'number']],
        'marca' => ['empresa' => 'Demo S.A.', 'logo' => ''],
    ];
}

test('es una ReporteTemplateInterface y soporta colección', function (): void {
    $t = new TablaEstadisticaTemplate();
    assert_true($t instanceof ReporteTemplateInterface);
    assert_true($t->supports('coleccion'));
    assert_true(!$t->supports('registro'));
});

test('el schema de pasos pide periodo y tratamientos', function (): void {
    $s = (new TablaEstadisticaTemplate())->schemaPasos();
    assert_same('coleccion', $s['modo']);
    assert_true($s['requiere_periodo']);
    assert_true($s['permite_tratamientos']);
});

test('compose devuelve un PdfDocument con header, tabla y totales', function (): void {
    $doc = (new TablaEstadisticaTemplate())->compose(tet_payload());
    assert_true($doc instanceof PdfDocument);
    $types = array_map(static fn($b) => $b->type(), $doc->blocks());
    assert_true(in_array('header', $types, true), 'tiene header');
    assert_true(in_array('table', $types, true), 'tiene tabla');
    assert_true(in_array('totals', $types, true), 'tiene totales');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/TablaEstadisticaTemplate`
Expected: FAIL — `Class "App\Application\Pdf\Templates\TablaEstadisticaTemplate" not found`.

- [ ] **Step 3: Implementar la plantilla**

Create `app/Application/Pdf/Templates/TablaEstadisticaTemplate.php`:

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
use App\Domain\Pdf\PdfTotalsBlock;
use App\Domain\Reporte\ReporteTemplateInterface;

/**
 * Plantilla demo de colección: encabezado con marca + periodo, tabla de datos
 * (agrupada o plana) y bloque de totales. Compone solo componentes del pdf-kit;
 * no genera HTML propio.
 */
final class TablaEstadisticaTemplate implements ReporteTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $orientation = (string) ($payload['orientation'] ?? 'portrait');
        $doc = PdfDocument::make(new PdfPageSetup('A4', $orientation));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $logo = (string) ($marca['logo'] ?? '');
        if ($logo !== '') {
            $doc->add(new PdfLogo($logo, 40));
        }

        $title = (string) ($payload['title'] ?? 'Reporte');
        $subtitle = trim((string) ($marca['empresa'] ?? '') . ' · ' . (string) ($payload['period'] ?? ''), ' ·');
        $doc->add(new PdfHeader($title, $subtitle));
        $doc->add(new PdfSpacer(8));

        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $doc->add(new PdfDataTable($columns, $rows));

        $totals = is_array($payload['totals'] ?? null) ? $payload['totals'] : [];
        if ($totals !== []) {
            $doc->add(new PdfSpacer(8));
            $doc->add(new PdfTotalsBlock($totals));
        }

        $doc->add(new PdfFooter('Generado por Lebytek · ' . date('Y-m-d H:i')));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'coleccion';
    }

    /** @return array{modo:string,requiere_periodo:bool,permite_tratamientos:bool,min_columnas:int,max_columnas:int} */
    public function schemaPasos(): array
    {
        return [
            'modo'                 => 'coleccion',
            'requiere_periodo'     => true,
            'permite_tratamientos' => true,
            'min_columnas'         => 1,
            'max_columnas'         => 12,
        ];
    }
}
```

- [ ] **Step 4: Registrar la plantilla en la whitelist**

In `config/pdf_templates.php`, añade el `use` y la entrada del mapa:

```php
use App\Application\Pdf\Templates\DemoReporteTemplate;
use App\Application\Pdf\Templates\TablaEstadisticaTemplate;

// ...
return [
    'demo_reporte'      => DemoReporteTemplate::class,
    'tabla_estadistica' => TablaEstadisticaTemplate::class,
];
```

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/TablaEstadisticaTemplate`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Application/Pdf/Templates/TablaEstadisticaTemplate.php config/pdf_templates.php tests/Reporte/TablaEstadisticaTemplateTest.php
git commit -m "feat(reportes): add tabla_estadistica demo template

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 10: `CrudReporteDataSource` (adaptador sobre `CrudDataService`)

Implementa `ReporteDataSourceInterface` delegando en `CrudDataService::eventsInRange`, que ya aplica el row-level scope y filtros de igualdad declarados.

**Files:**
- Create: `app/Application/Reporte/CrudReporteDataSource.php`

No test propio (es un adaptador de una línea sobre código ya testeado del CRUD Engine); se ejercita indirectamente en el smoke manual (Task 15).

- [ ] **Step 1: Implementar el adaptador**

Create `app/Application/Reporte/CrudReporteDataSource.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Application\Services\CrudDataService;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Reporte\ReporteDataSourceInterface;

/**
 * Adaptador de lectura de datos para reportes sobre CrudDataService::eventsInRange,
 * que respeta el mismo row-level scope y filtros de igualdad del listado CRUD.
 */
final class CrudReporteDataSource implements ReporteDataSourceInterface
{
    public function __construct(
        private readonly CrudDataService $crudDataService,
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
}
```

- [ ] **Step 2: Verificar que parsea**

Run: `php -l app/Application/Reporte/CrudReporteDataSource.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Application/Reporte/CrudReporteDataSource.php
git commit -m "feat(reportes): add CrudReporteDataSource adapter

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 11: `BuildReporteDataUseCase`

Orquesta: carga la fuente, **interseca** la selección guardada con el `expose` vigente (la config es la fuente de verdad), resuelve el periodo, lee filas (con scope), recorta a `max_rows`, agrega y devuelve el payload de datos (sin marca; eso lo añade Task 12).

**Files:**
- Create: `app/Application/Reporte/BuildReporteDataUseCase.php`
- Test: `tests/Reporte/BuildReporteDataUseCaseTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Reporte/BuildReporteDataUseCaseTest.php` (usa un `ReporteDataSourceInterface` falso; la fuente real `citas` la resuelve el loader desde disco):

```php
<?php
declare(strict_types=1);

use App\Application\Reporte\BuildReporteDataUseCase;
use App\Application\Reporte\PeriodoResolver;
use App\Application\Reporte\ReporteAggregator;
use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Reporte\ReporteConfigValidator;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Reporte\ReporteDataSourceInterface;
use App\Domain\Reporte\ReporteGuardado;

final class FakeReporteDataSource implements ReporteDataSourceInterface
{
    /** @var list<array<string,mixed>> */
    public array $rows;
    public array $lastCall = [];

    public function __construct(array $rows) { $this->rows = $rows; }

    public function rows(CrudResourceDefinition $definition, string $dateColumn, string $from, string $to, ?int $userId, ?callable $can, array $filters): array
    {
        $this->lastCall = compact('dateColumn', 'from', 'to', 'userId', 'filters');
        return $this->rows;
    }
}

function brd_useCase(ReporteDataSourceInterface $source): BuildReporteDataUseCase
{
    return new BuildReporteDataUseCase(
        new ReporteConfigLoader(new ReporteConfigValidator()),
        $source,
        new PeriodoResolver(),
        new ReporteAggregator()
    );
}

function brd_reporte(): ReporteGuardado
{
    return ReporteGuardado::fromRow([
        'id' => 1, 'nombre' => 'Citas por estado', 'fuente_key' => 'citas', 'modo' => 'coleccion',
        'columnas' => json_encode([['name' => 'estado', 'label' => 'Estado', 'type' => 'text']]),
        'tratamientos' => json_encode(['group_by' => ['estado'], 'aggregations' => [['op' => 'count', 'column' => '']], 'order' => ['by' => 'estado', 'dir' => 'asc']]),
        'filtros' => json_encode([]),
        'periodo' => json_encode(['preset' => 'todo']),
        'opciones' => json_encode(['titulo' => 'Citas por estado', 'orientacion' => 'portrait']),
        'template_key' => 'tabla_estadistica', 'compartido' => 1, 'created_by' => 3,
    ]);
}

test('construye el payload agregando por estado', function (): void {
    $source = new FakeReporteDataSource([
        ['estado' => 'pagado'], ['estado' => 'pagado'], ['estado' => 'pendiente'],
    ]);
    $payload = brd_useCase($source)->build(brd_reporte(), 3, fn(string $s): bool => true);

    assert_same('Citas por estado', $payload['title']);
    assert_same('Todo', $payload['period']);
    assert_same(2, count($payload['rows']));
    assert_same(3, $payload['totals'][0]['value']);
    assert_same('fecha_inicio', $source->lastCall['dateColumn']);
});

test('descarta columnas que la fuente ya no expone', function (): void {
    $reporte = ReporteGuardado::fromRow([
        'id' => 1, 'nombre' => 'X', 'fuente_key' => 'citas', 'modo' => 'coleccion',
        'columnas' => json_encode([
            ['name' => 'estado', 'label' => 'Estado', 'type' => 'text'],
            ['name' => 'columna_retirada', 'label' => 'Vieja', 'type' => 'text'],
        ]),
        'tratamientos' => json_encode([]),
        'filtros' => json_encode([]),
        'periodo' => json_encode(['preset' => 'todo']),
        'opciones' => json_encode([]),
        'template_key' => 'tabla_estadistica', 'compartido' => 0, 'created_by' => 3,
    ]);
    $source = new FakeReporteDataSource([['estado' => 'pagado', 'columna_retirada' => 'x']]);
    $payload = brd_useCase($source)->build($reporte, 3, fn(string $s): bool => true);

    $names = array_map(static fn($c) => $c['name'], $payload['columns']);
    assert_true(in_array('estado', $names, true));
    assert_true(!in_array('columna_retirada', $names, true), 'la columna retirada no debe aparecer');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/BuildReporteDataUseCase`
Expected: FAIL — `Class "App\Application\Reporte\BuildReporteDataUseCase" not found`.

- [ ] **Step 3: Implementar el use case**

Create `app/Application/Reporte/BuildReporteDataUseCase.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Domain\Reporte\ReporteDataSourceInterface;
use App\Domain\Reporte\ReporteFuente;
use App\Domain\Reporte\ReporteGuardado;

/**
 * Construye el payload de datos de un reporte de colección: interseca la selección
 * guardada con el expose vigente (config = fuente de verdad), resuelve el periodo,
 * lee filas con scope, recorta a max_rows y agrega. No conoce PDF ni marca.
 */
final class BuildReporteDataUseCase
{
    public function __construct(
        private readonly ReporteConfigLoader $loader,
        private readonly ReporteDataSourceInterface $dataSource,
        private readonly PeriodoResolver $periodos,
        private readonly ReporteAggregator $aggregator,
    ) {}

    /**
     * @return array{title:string,period:string,orientation:string,columns:list<array{name:string,label:string,format:string}>,rows:list<array<string,mixed>>,totals:list<array{label:string,value:mixed,format:string}>,capped:bool}
     */
    public function build(ReporteGuardado $reporte, ?int $userId, callable $can): array
    {
        $fuente = $this->loader->load($reporte->fuenteKey());
        $definition = $this->loader->crudDefinition($fuente->resource());

        $columns = [];
        foreach ($reporte->columnas() as $c) {
            $name = (string) ($c['name'] ?? '');
            if ($fuente->hasColumn($name)) {
                $columns[] = ['name' => $name, 'label' => $fuente->columnLabel($name), 'type' => $fuente->columnType($name)];
            }
        }

        $tratamientos = $this->intersectTreatments($reporte->tratamientos(), $fuente);

        $filtros = [];
        foreach ($reporte->filtros() as $field => $value) {
            $field = (string) $field;
            if ($fuente->hasFilter($field) && $value !== null && $value !== '') {
                $filtros[$field] = $value;
            }
        }

        $preset = (string) ($reporte->periodo()['preset'] ?? 'todo');
        $range = $this->periodos->resolve($preset);

        $rows = $this->dataSource->rows(
            $definition,
            $fuente->periodField(),
            $range['from'],
            $range['to'],
            $userId,
            $can,
            $filtros
        );

        $capped = false;
        if (count($rows) > $fuente->maxRows()) {
            $rows = array_slice($rows, 0, $fuente->maxRows());
            $capped = true;
        }

        $agg = $this->aggregator->apply($rows, $columns, $tratamientos);

        return [
            'title'       => (string) ($reporte->opciones()['titulo'] ?? $reporte->nombre()),
            'period'      => $range['label'],
            'orientation' => (string) ($reporte->opciones()['orientacion'] ?? 'portrait'),
            'columns'     => $agg['columns'],
            'rows'        => $agg['rows'],
            'totals'      => $agg['totals'],
            'capped'      => $capped,
        ];
    }

    /**
     * Re-valida los tratamientos guardados contra el expose vigente.
     *
     * @param array<string,mixed> $tratamientos
     * @return array<string,mixed>
     */
    private function intersectTreatments(array $tratamientos, ReporteFuente $fuente): array
    {
        $groupBy = [];
        foreach (is_array($tratamientos['group_by'] ?? null) ? $tratamientos['group_by'] : [] as $g) {
            if (in_array((string) $g, $fuente->groupBy(), true)) {
                $groupBy[] = (string) $g;
            }
        }

        $aggs = [];
        foreach (is_array($tratamientos['aggregations'] ?? null) ? $tratamientos['aggregations'] : [] as $a) {
            if (!is_array($a)) {
                continue;
            }
            $op = (string) ($a['op'] ?? '');
            $col = (string) ($a['column'] ?? '');
            if ($op === 'count' && $col === '') {
                $aggs[] = ['op' => 'count', 'column' => ''];
            } elseif ($fuente->allowsTreatment($col, $op)) {
                $aggs[] = ['op' => $op, 'column' => $col];
            }
        }

        $order = null;
        if (is_array($tratamientos['order'] ?? null)) {
            $by = (string) ($tratamientos['order']['by'] ?? '');
            // Permite ordenar por una columna de group_by o por order_by expuesto.
            if ($by !== '' && (in_array($by, $groupBy, true) || in_array($by, $fuente->orderBy(), true) || str_contains($by, '_'))) {
                $order = ['by' => $by, 'dir' => (string) ($tratamientos['order']['dir'] ?? 'asc')];
            }
        }

        return ['group_by' => $groupBy, 'aggregations' => $aggs, 'order' => $order];
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/BuildReporteDataUseCase`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Reporte/BuildReporteDataUseCase.php tests/Reporte/BuildReporteDataUseCaseTest.php
git commit -m "feat(reportes): add BuildReporteDataUseCase

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 12: `GenerarReporteUseCase` + `GuardarReporteUseCase`

`GenerarReporteUseCase` añade marca + orientación al payload y delega en `PdfRenderingService::renderTemplate` para obtener bytes. `GuardarReporteUseCase` valida la selección del usuario contra la fuente y serializa para persistir.

**Files:**
- Create: `app/Application/Reporte/GenerarReporteUseCase.php`
- Create: `app/Application/Reporte/GuardarReporteUseCase.php`
- Test: `tests/Reporte/GuardarReporteUseCaseTest.php`

> Nota: `PdfRenderingService::renderTemplate(string $key, array $payload): string` proviene del `pdf-kit` (Fase 0) y resuelve la plantilla por `PdfTemplateRegistry`. Si la firma local difiere, ajústala aquí (es el único punto de acople con el kit).

- [ ] **Step 1: Implementar `GenerarReporteUseCase`**

Create `app/Application/Reporte/GenerarReporteUseCase.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Application\Pdf\PdfRenderingService;
use App\Domain\Reporte\ReporteGuardado;
use App\Kernel\Config\Config;

/**
 * Genera los bytes PDF de un reporte de colección: construye el payload de datos,
 * le añade la marca (de config/cfg) y la orientación, y lo pasa a la plantilla vía
 * PdfRenderingService. La marca nunca proviene de datos de usuario.
 */
final class GenerarReporteUseCase
{
    public function __construct(
        private readonly BuildReporteDataUseCase $builder,
        private readonly PdfRenderingService $pdf,
    ) {}

    /** @return string bytes del PDF (empiezan con "%PDF"). */
    public function generar(ReporteGuardado $reporte, ?int $userId, callable $can): string
    {
        $payload = $this->builder->build($reporte, $userId, $can);
        $payload['marca'] = $this->marca();

        return $this->pdf->renderTemplate($reporte->templateKey(), $payload);
    }

    /** @return array<string,mixed> */
    private function marca(): array
    {
        $marca = Config::get('pdf.marca', []);
        return is_array($marca) ? $marca : [];
    }
}
```

- [ ] **Step 2: Escribir el test que falla para `GuardarReporteUseCase`**

Create `tests/Reporte/GuardarReporteUseCaseTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Reporte\GuardarReporteUseCase;
use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Reporte\ReporteConfigValidator;
use App\Domain\Exceptions\ValidationException;

function gru_useCase(): GuardarReporteUseCase
{
    return new GuardarReporteUseCase(new ReporteConfigLoader(new ReporteConfigValidator()));
}

function gru_input(): array
{
    return [
        'nombre' => 'Citas por estado',
        'fuente_key' => 'citas',
        'template_key' => 'tabla_estadistica',
        'columnas' => [['name' => 'estado']],
        'tratamientos' => ['group_by' => ['estado'], 'aggregations' => [['op' => 'count', 'column' => '']], 'order' => ['by' => 'estado', 'dir' => 'asc']],
        'filtros' => ['estado' => 'pagado'],
        'periodo' => ['preset' => 'mes'],
        'opciones' => ['titulo' => 'Citas por estado', 'orientacion' => 'portrait'],
        'compartido' => true,
    ];
}

test('serializa una selección válida a columnas de rep_reportes', function (): void {
    $data = gru_useCase()->toRow(gru_input(), 3);
    assert_same('citas', $data['fuente_key']);
    assert_same('coleccion', $data['modo']);
    assert_same('tabla_estadistica', $data['template_key']);
    assert_same(1, $data['compartido']);
    assert_same(3, $data['created_by']);
    // columnas JSON: solo las expuestas por la fuente
    $cols = json_decode($data['columnas'], true);
    assert_same('estado', $cols[0]['name']);
    // filtros JSON: solo los declarados
    $filtros = json_decode($data['filtros'], true);
    assert_same('pagado', $filtros['estado']);
});

test('rechaza una plantilla no ofrecida por la fuente', function (): void {
    $input = gru_input();
    $input['template_key'] = 'ticket_compra'; // no está en templates.coleccion de citas
    assert_throws(ValidationException::class, fn() => gru_useCase()->toRow($input, 3));
});

test('rechaza una columna no expuesta', function (): void {
    $input = gru_input();
    $input['columnas'] = [['name' => 'secreto']];
    assert_throws(ValidationException::class, fn() => gru_useCase()->toRow($input, 3));
});

test('rechaza un preset de periodo no ofrecido por la fuente', function (): void {
    $input = gru_input();
    $input['periodo'] = ['preset' => 'decada'];
    assert_throws(ValidationException::class, fn() => gru_useCase()->toRow($input, 3));
});
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php tests/run.php Reporte/GuardarReporteUseCase`
Expected: FAIL — `Class "App\Application\Reporte\GuardarReporteUseCase" not found`.

- [ ] **Step 4: Implementar `GuardarReporteUseCase`**

Create `app/Application/Reporte/GuardarReporteUseCase.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteFuente;

/**
 * Valida la selección del usuario contra la fuente vigente y la serializa a las
 * columnas de rep_reportes (JSON). La config del programador es la fuente de verdad:
 * columnas, tratamientos, filtros, periodo y plantilla deben estar permitidos.
 */
final class GuardarReporteUseCase
{
    public function __construct(
        private readonly ReporteConfigLoader $loader,
    ) {}

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed> columnas de rep_reportes listas para el repositorio
     */
    public function toRow(array $input, int $userId): array
    {
        $fuenteKey = (string) ($input['fuente_key'] ?? '');
        $fuente = $this->loader->load($fuenteKey); // lanza si no existe / inválida

        $errors = [];

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            $errors[] = 'El nombre del reporte es obligatorio.';
        }

        $templateKey = (string) ($input['template_key'] ?? '');
        if (!in_array($templateKey, $fuente->templatesFor('coleccion'), true)) {
            $errors[] = "La plantilla '{$templateKey}' no está disponible para esta fuente.";
        }

        $columnas = [];
        foreach (is_array($input['columnas'] ?? null) ? $input['columnas'] : [] as $c) {
            $name = is_array($c) ? (string) ($c['name'] ?? '') : (string) $c;
            if (!$fuente->hasColumn($name)) {
                $errors[] = "La columna '{$name}' no está expuesta por la fuente.";
                continue;
            }
            $columnas[] = ['name' => $name, 'label' => $fuente->columnLabel($name), 'type' => $fuente->columnType($name)];
        }
        if ($columnas === []) {
            $errors[] = 'Selecciona al menos una columna.';
        }

        $tratamientos = $this->sanitizeTreatments(is_array($input['tratamientos'] ?? null) ? $input['tratamientos'] : [], $fuente, $errors);

        $filtros = [];
        foreach (is_array($input['filtros'] ?? null) ? $input['filtros'] : [] as $field => $value) {
            $field = (string) $field;
            if ($fuente->hasFilter($field) && $value !== null && $value !== '') {
                $filtros[$field] = (string) $value;
            }
        }

        $preset = (string) (($input['periodo']['preset'] ?? 'todo'));
        if ($fuente->hasPeriod() && !in_array($preset, $fuente->periodPresets(), true)) {
            $errors[] = "El periodo '{$preset}' no está disponible para esta fuente.";
        }

        if ($errors !== []) {
            throw new ValidationException('No se pudo guardar el reporte.', $errors);
        }

        $orientacion = (string) (($input['opciones']['orientacion'] ?? 'portrait'));
        $opciones = [
            'titulo'      => trim((string) (($input['opciones']['titulo'] ?? $nombre))),
            'orientacion' => in_array($orientacion, ['portrait', 'landscape'], true) ? $orientacion : 'portrait',
        ];

        return [
            'nombre'       => $nombre,
            'fuente_key'   => $fuenteKey,
            'modo'         => 'coleccion',
            'columnas'     => json_encode($columnas, JSON_UNESCAPED_UNICODE),
            'tratamientos' => json_encode($tratamientos, JSON_UNESCAPED_UNICODE),
            'filtros'      => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            'periodo'      => json_encode(['preset' => $preset], JSON_UNESCAPED_UNICODE),
            'opciones'     => json_encode($opciones, JSON_UNESCAPED_UNICODE),
            'template_key' => $templateKey,
            'compartido'   => !empty($input['compartido']) ? 1 : 0,
            'created_by'   => $userId,
        ];
    }

    /**
     * @param array<string,mixed> $tratamientos
     * @param list<string> $errors
     * @return array<string,mixed>
     */
    private function sanitizeTreatments(array $tratamientos, ReporteFuente $fuente, array &$errors): array
    {
        $groupBy = [];
        foreach (is_array($tratamientos['group_by'] ?? null) ? $tratamientos['group_by'] : [] as $g) {
            $g = (string) $g;
            if (in_array($g, $fuente->groupBy(), true)) {
                $groupBy[] = $g;
            } else {
                $errors[] = "No se puede agrupar por '{$g}'.";
            }
        }

        $aggs = [];
        foreach (is_array($tratamientos['aggregations'] ?? null) ? $tratamientos['aggregations'] : [] as $a) {
            if (!is_array($a)) {
                continue;
            }
            $op = (string) ($a['op'] ?? '');
            $col = (string) ($a['column'] ?? '');
            if ($op === 'count' && $col === '') {
                $aggs[] = ['op' => 'count', 'column' => ''];
            } elseif ($fuente->allowsTreatment($col, $op)) {
                $aggs[] = ['op' => $op, 'column' => $col];
            } else {
                $errors[] = "Tratamiento '{$op}' no permitido en '{$col}'.";
            }
        }

        $order = null;
        if (is_array($tratamientos['order'] ?? null)) {
            $by = (string) ($tratamientos['order']['by'] ?? '');
            $dir = strtolower((string) ($tratamientos['order']['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            if ($by !== '') {
                $order = ['by' => $by, 'dir' => $dir];
            }
        }

        return ['group_by' => $groupBy, 'aggregations' => $aggs, 'order' => $order];
    }
}
```

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php tests/run.php Reporte/GuardarReporteUseCase`
Expected: PASS (4 tests).

- [ ] **Step 6: Verificar `GenerarReporteUseCase` parsea**

Run: `php -l app/Application/Reporte/GenerarReporteUseCase.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
git add app/Application/Reporte/GenerarReporteUseCase.php app/Application/Reporte/GuardarReporteUseCase.php tests/Reporte/GuardarReporteUseCaseTest.php
git commit -m "feat(reportes): add Generar and Guardar report use cases

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 13: `PdoReporteRepository`

Implementa `ReporteRepositoryInterface` sobre `BaseRepository`. Scope owner: `findVisible` y `listForUser` devuelven propios + compartidos.

**Files:**
- Create: `app/Infrastructure/Repositories/PdoReporteRepository.php`

No test unitario (requiere BD MySQL real, ausente en el arnés plano); se valida en el smoke manual (Task 15). El código usa exclusivamente prepared statements de `BaseRepository`.

- [ ] **Step 1: Implementar el repositorio**

Create `app/Infrastructure/Repositories/PdoReporteRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\ReporteRepositoryInterface;
use App\Domain\Reporte\ReporteGuardado;
use App\Kernel\BaseClasses\BaseRepository;

/**
 * Persistencia de rep_reportes. Scope owner: cada quien ve los suyos; compartido=1
 * los publica. Un reporte ajeno no compartido no se devuelve (el controlador lo
 * traduce a 404). Borrado lógico vía columna `deleted`.
 */
final class PdoReporteRepository extends BaseRepository implements ReporteRepositoryInterface
{
    protected string $table = 'rep_reportes';

    public function findVisible(int $id, int $userId): ?ReporteGuardado
    {
        $row = $this->queryOne(
            "SELECT * FROM rep_reportes
             WHERE id = ? AND deleted = 0 AND (created_by = ? OR compartido = 1)
             LIMIT 1",
            [$id, $userId]
        );

        return $row === null ? null : ReporteGuardado::fromRow($row);
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        return $this->query(
            "SELECT id, nombre, fuente_key, modo, template_key, compartido, created_by, updated_at, created_at
             FROM rep_reportes
             WHERE deleted = 0 AND (created_by = ? OR compartido = 1)
             ORDER BY updated_at DESC, created_at DESC",
            [$userId]
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return $this->insert(
            "INSERT INTO rep_reportes
                (nombre, fuente_key, modo, columnas, tratamientos, filtros, periodo, opciones,
                 template_key, compartido, deleted, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?)",
            [
                $data['nombre'], $data['fuente_key'], $data['modo'], $data['columnas'],
                $data['tratamientos'], $data['filtros'], $data['periodo'], $data['opciones'],
                $data['template_key'], $data['compartido'], $data['created_by'],
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, int $userId, array $data): void
    {
        $this->execute(
            "UPDATE rep_reportes SET
                nombre = ?, modo = ?, columnas = ?, tratamientos = ?, filtros = ?,
                periodo = ?, opciones = ?, template_key = ?, compartido = ?,
                updated_at = NOW(), updated_by = ?
             WHERE id = ? AND created_by = ? AND deleted = 0",
            [
                $data['nombre'], $data['modo'], $data['columnas'], $data['tratamientos'],
                $data['filtros'], $data['periodo'], $data['opciones'], $data['template_key'],
                $data['compartido'], $userId, $id, $userId,
            ]
        );
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->execute(
            "UPDATE rep_reportes SET deleted = 1, deleted_at = NOW(), deleted_by = ?
             WHERE id = ? AND created_by = ? AND deleted = 0",
            [$userId, $id, $userId]
        );
    }
}
```

- [ ] **Step 2: Verificar que parsea**

Run: `php -l app/Infrastructure/Repositories/PdoReporteRepository.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Infrastructure/Repositories/PdoReporteRepository.php
git commit -m "feat(reportes): add PdoReporteRepository

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 14: SQL de bootstrap (`rep_reportes`, permisos, menú, reporte demo)

**Files:**
- Create: `database/schema/modules/reportes.sql`

- [ ] **Step 1: Crear el SQL**

Create `database/schema/modules/reportes.sql`:

```sql
-- Bootstrap del módulo reportes.
-- Provee la tabla rep_reportes, los permisos del módulo, la entrada de menú y un
-- reporte demo (compartido) de colección sobre el recurso CRUD demo `demo_citas`.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `rep_reportes` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`         VARCHAR(120)    DEFAULT NULL,
  `nombre`        VARCHAR(150)    NOT NULL,
  `fuente_key`    VARCHAR(120)    NOT NULL,
  `modo`          VARCHAR(20)     NOT NULL DEFAULT 'coleccion',
  `columnas`      JSON            NOT NULL,
  `tratamientos`  JSON            NOT NULL,
  `filtros`       JSON            NOT NULL,
  `periodo`       JSON            NOT NULL,
  `opciones`      JSON            NOT NULL,
  `template_key`  VARCHAR(120)    NOT NULL,
  `compartido`    TINYINT(1)      NOT NULL DEFAULT 0,
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rep_reportes_clave` (`clave`),
  KEY `idx_rep_reportes_fuente` (`fuente_key`),
  KEY `idx_rep_reportes_deleted` (`deleted`),
  KEY `idx_rep_reportes_owner` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver reportes',      'reportes.ver',        'reportes', 'Permite listar y ver reportes guardados'),
('Crear reportes',    'reportes.crear',      'reportes', 'Permite crear reportes'),
('Editar reportes',   'reportes.editar',     'reportes', 'Permite editar reportes propios'),
('Eliminar reportes', 'reportes.eliminar',   'reportes', 'Permite eliminar reportes propios'),
('Generar reportes',  'reportes.generar',    'reportes', 'Permite generar el PDF de un reporte'),
('Compartir reportes','reportes.compartir',  'reportes', 'Permite marcar un reporte como compartido');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'reportes.ver', 'reportes.crear', 'reportes.editar',
  'reportes.eliminar', 'reportes.generar', 'reportes.compartir'
)
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 96, 'reportes', 'Reportes', 'bi-file-earmark-bar-graph', '/admin/reportes', '/admin/reportes', 'reportes.ver', 'reportes', 1);

-- Reporte demo (compartido): "Citas por estado" — agrupa demo_citas por estado y
-- cuenta, periodo "todo". Idempotente vía la clave única.
INSERT IGNORE INTO `rep_reportes`
(`clave`, `nombre`, `fuente_key`, `modo`, `columnas`, `tratamientos`, `filtros`, `periodo`, `opciones`, `template_key`, `compartido`, `deleted`, `created_at`)
VALUES
(
  'demo_citas_por_estado',
  'Citas por estado',
  'citas',
  'coleccion',
  '[{"name":"estado","label":"Estado","type":"text"}]',
  '{"group_by":["estado"],"aggregations":[{"op":"count","column":""}],"order":{"by":"estado","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Citas por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1,
  0,
  NOW()
);

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 2: Verificar referencias del menú**

Confirma que el SQL del calendario usa las mismas columnas de `core_menu_items` (`parent_id, orden, slug, label, icon, url, match, permiso_slug, vertical_module, activo`).

Run: `php -r "echo file_exists('database/schema/modules/reportes.sql') ? 'ok' : 'missing', PHP_EOL;"`
Expected: `ok`.

- [ ] **Step 3: Commit**

```bash
git add database/schema/modules/reportes.sql
git commit -m "feat(reportes): add module bootstrap SQL (table, perms, menu, demo report)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 15: Controlador, vistas, rutas y wiring del contenedor

Pieza de presentación: índice estilo CRUD, wizard, y endpoints de guardar/editar/eliminar/generar. Cierra el cableado en `config/container.php` y `routes/web.php`.

**Files:**
- Create: `app/Presentation/Controllers/Admin/ReportesController.php`
- Create: `app/Presentation/Views/admin/reportes/index.php`
- Create: `app/Presentation/Views/admin/reportes/builder.php`
- Modify: `config/container.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Registrar los servicios en el contenedor**

In `config/container.php`, después del bloque del módulo Calendario (la sección `// ── Módulo Calendario ──`), añade un bloque del módulo Reportes. Usa los mismos estilos de binding ya presentes en el archivo (`$container->singleton(...)`, `fn(Container $c) => ...`, repos PDO con `fn() => new X()`):

```php
    // ── Módulo Reportes ─────────────────────────────────────────────────────
    $container->singleton(\App\Application\Reporte\ReporteConfigValidator::class, fn() => new \App\Application\Reporte\ReporteConfigValidator());
    $container->singleton(\App\Application\Reporte\ReporteConfigLoader::class, fn(Container $c) => new \App\Application\Reporte\ReporteConfigLoader(
        $c->get(\App\Application\Reporte\ReporteConfigValidator::class)
    ));
    $container->singleton(\App\Application\Reporte\PeriodoResolver::class, fn() => new \App\Application\Reporte\PeriodoResolver());
    $container->singleton(\App\Application\Reporte\ReporteAggregator::class, fn() => new \App\Application\Reporte\ReporteAggregator());
    $container->singleton(\App\Domain\Reporte\ReporteDataSourceInterface::class, fn(Container $c) => new \App\Application\Reporte\CrudReporteDataSource(
        $c->get(CrudDataService::class)
    ));
    $container->singleton(\App\Application\Reporte\BuildReporteDataUseCase::class, fn(Container $c) => new \App\Application\Reporte\BuildReporteDataUseCase(
        $c->get(\App\Application\Reporte\ReporteConfigLoader::class),
        $c->get(\App\Domain\Reporte\ReporteDataSourceInterface::class),
        $c->get(\App\Application\Reporte\PeriodoResolver::class),
        $c->get(\App\Application\Reporte\ReporteAggregator::class)
    ));
    $container->singleton(\App\Application\Reporte\GenerarReporteUseCase::class, fn(Container $c) => new \App\Application\Reporte\GenerarReporteUseCase(
        $c->get(\App\Application\Reporte\BuildReporteDataUseCase::class),
        $c->get(\App\Application\Pdf\PdfRenderingService::class)
    ));
    $container->singleton(\App\Application\Reporte\GuardarReporteUseCase::class, fn(Container $c) => new \App\Application\Reporte\GuardarReporteUseCase(
        $c->get(\App\Application\Reporte\ReporteConfigLoader::class)
    ));
    $container->singleton(\App\Domain\Interfaces\ReporteRepositoryInterface::class, fn() => new \App\Infrastructure\Repositories\PdoReporteRepository());
    $container->singleton(\App\Presentation\Controllers\Admin\ReportesController::class, fn(Container $c) => new \App\Presentation\Controllers\Admin\ReportesController(
        $c->get(ConfiguracionService::class),
        $c->get(\App\Application\Services\AdminNavigationMenuService::class),
        $c->get(\App\Application\Reporte\ReporteConfigLoader::class),
        $c->get(\App\Domain\Interfaces\ReporteRepositoryInterface::class),
        $c->get(\App\Application\Reporte\GuardarReporteUseCase::class),
        $c->get(\App\Application\Reporte\GenerarReporteUseCase::class)
    ));
```

> Si `PdfRenderingService` tiene otra clave/namespace en el contenedor (registrado por el `pdf-kit`), usa esa. Verifica con: `grep -n "PdfRenderingService" config/container.php`.

- [ ] **Step 2: Implementar el controlador**

Create `app/Presentation/Controllers/Admin/ReportesController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Reporte\GenerarReporteUseCase;
use App\Application\Reporte\GuardarReporteUseCase;
use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\ReporteRepositoryInterface;
use App\Presentation\Controllers\AdminBaseController;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Domain\Policies\RbacPolicy;

/**
 * Módulo Reportes: índice estilo CRUD + wizard de colección + generación de PDF.
 * Toda lectura de datos pasa por los use cases, que heredan el scope del recurso.
 */
final class ReportesController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly ReporteConfigLoader $loader,
        private readonly ReporteRepositoryInterface $repository,
        private readonly GuardarReporteUseCase $guardar,
        private readonly GenerarReporteUseCase $generar,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(): Response
    {
        $reportes = $this->repository->listForUser($this->userId());

        return $this->view('admin/reportes/index', [
            'titulo'   => 'Reportes',
            'reportes' => $reportes,
        ]);
    }

    public function crear(): Response
    {
        return $this->view('admin/reportes/builder', [
            'titulo'  => 'Nuevo reporte',
            'reporte' => null,
            'fuentes' => $this->loader->listFuentes(),
        ]);
    }

    public function editar(string $id): Response
    {
        $reporte = $this->repository->findVisible((int) $id, $this->userId());
        if ($reporte === null) {
            return $this->notFound();
        }

        return $this->view('admin/reportes/builder', [
            'titulo'  => 'Editar reporte',
            'reporte' => $reporte,
            'fuentes' => $this->loader->listFuentes(),
        ]);
    }

    public function guardar(): Response
    {
        try {
            $data = $this->guardar->toRow($_POST, $this->userId());
            $id = $this->repository->create($data);
            Session::flash('exito', 'Reporte creado.');
            return Response::redirect('/admin/reportes');
        } catch (ValidationException $e) {
            Session::flash('errores', $e->getErrors() ?: [$e->getMessage()]);
            return Response::redirect('/admin/reportes/crear');
        }
    }

    public function actualizar(string $id): Response
    {
        $reporte = $this->repository->findVisible((int) $id, $this->userId());
        if ($reporte === null || $reporte->createdBy() !== $this->userId()) {
            return $this->notFound();
        }

        try {
            $data = $this->guardar->toRow($_POST, $this->userId());
            $this->repository->update((int) $id, $this->userId(), $data);
            Session::flash('exito', 'Reporte actualizado.');
            return Response::redirect('/admin/reportes');
        } catch (ValidationException $e) {
            Session::flash('errores', $e->getErrors() ?: [$e->getMessage()]);
            return Response::redirect('/admin/reportes/' . (int) $id . '/editar');
        }
    }

    public function eliminar(string $id): Response
    {
        $reporte = $this->repository->findVisible((int) $id, $this->userId());
        if ($reporte === null || $reporte->createdBy() !== $this->userId()) {
            return $this->notFound();
        }
        $this->repository->softDelete((int) $id, $this->userId());
        Session::flash('exito', 'Reporte eliminado.');
        return Response::redirect('/admin/reportes');
    }

    public function generar(string $id): Response
    {
        $reporte = $this->repository->findVisible((int) $id, $this->userId());
        if ($reporte === null) {
            return $this->notFound();
        }

        try {
            $bytes = $this->generar->generar($reporte, $this->userId(), $this->canChecker());
        } catch (ValidationException $e) {
            Session::flash('errores', $e->getErrors() ?: [$e->getMessage()]);
            return Response::redirect('/admin/reportes');
        }

        $filename = 'reporte-' . (int) $id . '.pdf';
        // Emisión binaria del PDF. Sigue el mismo patrón que PwaController usa para
        // servir contenido con cabeceras propias (Content-Type + cuerpo crudo).
        return Response::make($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($bytes),
        ]);
    }

    private function userId(): int
    {
        $user = Session::get('auth_user', []);
        return (int) ($user['id'] ?? 0);
    }

    /** Callback de permisos para el scope del recurso (mismo contrato que el CRUD). */
    private function canChecker(): callable
    {
        $rbac = new RbacPolicy(Session::get('auth_permisos', []), Session::get('auth_roles', []));
        return static fn(string $slug): bool => $rbac->puede($slug);
    }

    private function notFound(): Response
    {
        return Response::make('No encontrado', 404);
    }
}
```

> **Verificación de API del framework:** este controlador usa `Response::redirect()`, `Response::make($body, $status, $headers)`, `Session::flash()` y `ValidationException::getErrors()`. Antes de implementar, confirma las firmas exactas con:
> `grep -n "function redirect\|function make\|public function " app/Kernel/Http/Response.php` y
> `grep -n "function flash\|function getErrors" app/Kernel/Security/Session.php app/Domain/Exceptions/ValidationException.php`.
> Si alguna difiere (p.ej. `Response::redirect` se llama distinto, o la emisión binaria usa otro helper como en `PwaController`), ajusta esas llamadas siguiendo el patrón existente. La lógica de negocio (use cases) no cambia.

- [ ] **Step 3: Crear la vista índice**

Create `app/Presentation/Views/admin/reportes/index.php`:

```php
<?php
/** @var array $reportes */
use App\Kernel\Security\Csrf;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Reportes</h1>
  <a href="/admin/reportes/crear" class="btn btn-primary">
    <i class="bi bi-plus-lg"></i> Nuevo reporte
  </a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Nombre</th><th>Fuente</th><th>Plantilla</th><th>Modo</th>
          <th>Compartido</th><th>Actualizado</th><th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($reportes)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay reportes. Crea el primero.</td></tr>
      <?php else: foreach ($reportes as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['fuente_key'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['template_key'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars((string) $r['modo'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= ((int) $r['compartido'] === 1) ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?></td>
          <td class="text-muted small"><?= htmlspecialchars((string) ($r['updated_at'] ?? $r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-end">
            <form method="post" action="/admin/reportes/<?= (int) $r['id'] ?>/generar" class="d-inline">
              <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn btn-sm btn-outline-primary" title="Generar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
            </form>
            <a href="/admin/reportes/<?= (int) $r['id'] ?>/editar" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
            <form method="post" action="/admin/reportes/<?= (int) $r['id'] ?>/eliminar" class="d-inline"
                  data-confirm="¿Eliminar este reporte?">
              <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
```

> **Verificación:** confirma el nombre del token CSRF y su helper. Run: `grep -rn "name=\"_token\"\|csrf\|Csrf::token\|_token" app/Presentation/Views/admin/crud/form.php`. Usa el mismo nombre de campo y helper que el resto de formularios admin (p.ej. si el proyecto usa `csrf_field()` o `$csrf`, sustitúyelo). El atributo `data-confirm` reutiliza el `#confirmModal` global del trabajo reciente; verifica el atributo exacto en `app/Presentation/Views/partials/nav_top.php` o donde se enganchó el modal de logout.

- [ ] **Step 4: Crear la vista builder (wizard)**

Create `app/Presentation/Views/admin/reportes/builder.php`. Wizard por pasos con navegación client-side (Bootstrap). Los pasos Tratamientos y Periodo se muestran/ocultan según la plantilla (atributos `data-*`). El formulario envía la selección como JSON en campos ocultos que el controlador lee de `$_POST`.

```php
<?php
/** @var array $fuentes @var \App\Domain\Reporte\ReporteGuardado|null $reporte */
use App\Kernel\Security\Csrf;

$editId = $reporte?->id();
$action = $editId ? '/admin/reportes/' . $editId : '/admin/reportes';
?>
<h1 class="h4 mb-3"><?= $editId ? 'Editar reporte' : 'Nuevo reporte' ?></h1>

<form id="reporte-form" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

  <!-- Indicador de pasos -->
  <ol class="nav nav-pills gap-2 mb-4" id="wizard-steps">
    <li class="nav-item"><span class="nav-link active" data-step="1">1 · Plantilla</span></li>
    <li class="nav-item"><span class="nav-link" data-step="2">2 · Fuente</span></li>
    <li class="nav-item"><span class="nav-link" data-step="3">3 · Columnas</span></li>
    <li class="nav-item" data-step-tab="tratamientos"><span class="nav-link" data-step="4">4 · Tratamientos</span></li>
    <li class="nav-item"><span class="nav-link" data-step="5">5 · Filtros</span></li>
    <li class="nav-item" data-step-tab="periodo"><span class="nav-link" data-step="6">6 · Periodo</span></li>
    <li class="nav-item"><span class="nav-link" data-step="7">7 · Guardar</span></li>
  </ol>

  <div class="card"><div class="card-body" id="wizard-panes">
    <section data-pane="1">
      <h2 class="h6">Elige una plantilla</h2>
      <select class="form-select" id="campo-plantilla">
        <option value="tabla_estadistica"
          data-requiere-periodo="1" data-permite-tratamientos="1">Tabla estadística (colección)</option>
      </select>
      <p class="text-muted small mt-2">La plantilla decide qué pasos verás a continuación.</p>
    </section>

    <section data-pane="2" hidden>
      <h2 class="h6">Elige una fuente</h2>
      <select class="form-select" id="campo-fuente">
        <option value="">— Selecciona —</option>
        <?php foreach ($fuentes as $key => $title): ?>
          <option value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </section>

    <section data-pane="3" hidden>
      <h2 class="h6">Columnas</h2>
      <div id="lista-columnas" class="text-muted small">Selecciona una fuente para ver sus columnas.</div>
    </section>

    <section data-pane="4" hidden>
      <h2 class="h6">Tratamientos</h2>
      <div id="lista-tratamientos" class="text-muted small">Disponibles según la fuente.</div>
    </section>

    <section data-pane="5" hidden>
      <h2 class="h6">Filtros</h2>
      <div id="lista-filtros" class="text-muted small">Filtros declarados por la fuente.</div>
    </section>

    <section data-pane="6" hidden>
      <h2 class="h6">Periodo</h2>
      <select class="form-select" id="campo-periodo">
        <option value="todo">Todo</option>
        <option value="hoy">Hoy</option>
        <option value="semana">Esta semana</option>
        <option value="mes">Este mes</option>
        <option value="anio">Este año</option>
        <option value="ayer">Ayer</option>
        <option value="mes_pasado">Mes pasado</option>
        <option value="anio_pasado">Año pasado</option>
      </select>
    </section>

    <section data-pane="7" hidden>
      <h2 class="h6">Detalles</h2>
      <div class="mb-2"><label class="form-label">Nombre</label>
        <input type="text" class="form-control" id="campo-nombre" value="<?= htmlspecialchars((string) ($reporte?->nombre() ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="mb-2"><label class="form-label">Título del documento</label>
        <input type="text" class="form-control" id="campo-titulo"></div>
      <div class="mb-2"><label class="form-label">Orientación</label>
        <select class="form-select" id="campo-orientacion"><option value="portrait">Vertical</option><option value="landscape">Horizontal</option></select></div>
      <div class="form-check"><input type="checkbox" class="form-check-input" id="campo-compartido"><label class="form-check-label">Compartir con otros usuarios</label></div>
    </section>
  </div></div>

  <!-- Campos ocultos: el JS los rellena con la selección serializada antes de enviar -->
  <input type="hidden" name="fuente_key" id="hidden-fuente">
  <input type="hidden" name="template_key" id="hidden-template">
  <input type="hidden" name="nombre" id="hidden-nombre">
  <input type="hidden" name="columnas" id="hidden-columnas">
  <input type="hidden" name="tratamientos" id="hidden-tratamientos">
  <input type="hidden" name="filtros" id="hidden-filtros">
  <input type="hidden" name="periodo" id="hidden-periodo">
  <input type="hidden" name="opciones" id="hidden-opciones">
  <input type="hidden" name="compartido" id="hidden-compartido" value="0">

  <div class="d-flex justify-content-between mt-3">
    <button type="button" class="btn btn-outline-secondary" id="btn-atras">← Atrás</button>
    <button type="button" class="btn btn-primary" id="btn-siguiente">Siguiente →</button>
    <button type="submit" class="btn btn-success" id="btn-guardar" hidden>Guardar reporte</button>
  </div>
</form>

<script type="application/json" id="reporte-precarga">
<?= json_encode([
    'fuente_key'   => $reporte?->fuenteKey(),
    'template_key' => $reporte?->templateKey(),
    'columnas'     => $reporte?->columnas() ?? [],
    'tratamientos' => $reporte?->tratamientos() ?? new stdClass(),
    'filtros'      => $reporte?->filtros() ?? new stdClass(),
    'periodo'      => $reporte?->periodo() ?? ['preset' => 'todo'],
    'opciones'     => $reporte?->opciones() ?? new stdClass(),
    'compartido'   => $reporte?->compartido() ?? false,
], JSON_UNESCAPED_UNICODE) ?>
</script>

<script src="/assets/js/reportes-builder.js" defer></script>
```

> **Nota de implementación:** el JS `public/assets/js/reportes-builder.js` (navegación de pasos, carga de columnas/filtros/tratamientos por fuente vía un endpoint de metadatos, y serialización a los campos ocultos) se construye en la Fase 1.5 / pulido. Para esta tarea, crea un `reportes-builder.js` mínimo que: (1) navegue entre paneles con Atrás/Siguiente, (2) oculte los pasos `data-step-tab` cuando la plantilla tenga `data-permite-tratamientos="0"`/`data-requiere-periodo="0"`, y (3) al enviar, copie los valores de los campos visibles a los `hidden-*` como JSON. Mantén el alcance mínimo; el endpoint de metadatos de fuente (columnas/filtros dinámicos) puede stubbearse leyendo un `data-*` precargado por fuente o dejarse para Fase 1.5. El reporte demo sembrado ya permite probar el índice y la generación de PDF sin el JS dinámico.

- [ ] **Step 5: Registrar las rutas**

In `routes/web.php`, dentro del grupo admin (donde están `/dashboard`, `/crud/...`), añade — siguiendo el patrón `[new RbacMiddleware('slug')]` y `CsrfMiddleware::class`:

```php
    $router->get('/reportes',                 [ReportesController::class, 'index'],     [new RbacMiddleware('reportes.ver')]);
    $router->get('/reportes/crear',           [ReportesController::class, 'crear'],     [new RbacMiddleware('reportes.crear')]);
    $router->post('/reportes',                [ReportesController::class, 'guardar'],   [new RbacMiddleware('reportes.crear'), CsrfMiddleware::class]);
    $router->get('/reportes/{id}/editar',     [ReportesController::class, 'editar'],    [new RbacMiddleware('reportes.editar')]);
    $router->post('/reportes/{id}',           [ReportesController::class, 'actualizar'],[new RbacMiddleware('reportes.editar'), CsrfMiddleware::class]);
    $router->post('/reportes/{id}/eliminar',  [ReportesController::class, 'eliminar'],  [new RbacMiddleware('reportes.eliminar'), CsrfMiddleware::class]);
    $router->post('/reportes/{id}/generar',   [ReportesController::class, 'generar'],   [new RbacMiddleware('reportes.generar'), CsrfMiddleware::class]);
```

Asegura el `use App\Presentation\Controllers\Admin\ReportesController;` al inicio de `routes/web.php` (junto a los demás `use` de controladores). Verifica que `RbacMiddleware` y `CsrfMiddleware` ya estén importados (lo están, por las rutas existentes).

- [ ] **Step 6: Verificar que todo parsea y carga**

Run:
```bash
php -l app/Presentation/Controllers/Admin/ReportesController.php
php -l app/Presentation/Views/admin/reportes/index.php
php -l app/Presentation/Views/admin/reportes/builder.php
php -r "require 'vendor/autoload.php';" 2>/dev/null; echo "lint ok"
```
Expected: `No syntax errors detected` en los tres archivos PHP.

- [ ] **Step 7: Smoke manual (servidor local)**

```bash
php scripts/install.php   # aplica el bootstrap SQL del módulo reportes (idempotente)
php -S localhost:8000 -t public
```
En el navegador (autenticado como admin):
1. Ir a `/admin/reportes` → debe listar el reporte demo "Citas por estado".
2. Pulsar **Generar PDF** → descarga un PDF cuyo contenido empieza con `%PDF` y muestra la tabla de citas agrupadas por estado con su conteo.
3. (Opcional) Crear un reporte nuevo desde el wizard mínimo y verificar que aparece en el índice.

- [ ] **Step 8: Commit**

```bash
git add app/Presentation/Controllers/Admin/ReportesController.php app/Presentation/Views/admin/reportes/ config/container.php routes/web.php public/assets/js/reportes-builder.js
git commit -m "feat(reportes): add controller, wizard/index views, routes and DI wiring

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 16: Suite completa + cierre

- [ ] **Step 1: Correr toda la suite**

Run: `php tests/run.php`
Expected: PASS — todos los tests de `tests/Reporte/` (Fuente, Validator, Loader, PeriodoResolver, Aggregator, Guardado, BuildData, TablaEstadistica, GuardarUseCase) y los tests previos del `pdf-kit` y demás módulos.

- [ ] **Step 2: Verificación del módulo en el instalador**

Run: `php -r "define('ROOT_PATH', getcwd()); define('APP_PATH', getcwd().'/app'); require 'app/Kernel/Autoloader.php'; \$m = \App\Application\Install\ModuleManifest::fromArray(require 'config/modules/reportes.php'); echo implode(',', \$m->requiere ?? []), PHP_EOL;"`
Expected: `core,crud-engine,pdf-kit`.

- [ ] **Step 3: Commit final (si quedó algo sin commitear)**

```bash
git status
# si hay cambios sueltos:
git add -A && git commit -m "chore(reportes): fase 1 cierre y verificación

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review (cubierto por el plan)

- **Catálogo de fuentes sobre CRUD** → Tasks 2 (VO), 3 (validator), 4 (loader + `citas.json`).
- **Tabla `rep_reportes`** → Task 14 (schema) + Task 7 (entidad) + Task 13 (repo).
- **Wizard de colección** → Task 15 (vista builder + controlador). El JS dinámico de pasos se acota a un mínimo funcional; el reporte demo permite validar índice + generación sin el JS completo (anotado como Fase 1.5).
- **Tratamientos (agrupar/sum/count/avg/min/max/ordenar)** → Task 6 (`ReporteAggregator`) + intersección en Tasks 11/12.
- **Periodo (relativos/anteriores/todo con max_rows)** → Task 5 (`PeriodoResolver`) + recorte `max_rows` en Task 11.
- **Índice estilo CRUD** → Task 15 (vista index).
- **Plantilla `tabla_estadistica`** → Task 9.
- **Generación de PDF + scope** → Tasks 10–12 (lectura con scope vía `CrudDataService::eventsInRange`) + Task 15 (descarga).
- **RBAC `reportes.*` + scope siempre acotado** → Task 14 (permisos) + Task 15 (rutas con `RbacMiddleware`) + `canChecker()` propagado a la lectura.

**Acoples a verificar durante la ejecución** (anotados en cada tarea, sin cambiar la lógica): firma exacta de `PdfRenderingService::renderTemplate` (Task 12), API de `Response`/`Session`/`ValidationException` y nombre del campo CSRF y del atributo `#confirmModal` (Task 15). La lógica de dominio/aplicación está completamente especificada y es testeable sin esos acoples.
```
