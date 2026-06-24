# Módulo `pdf-kit` (Kit de PDF) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the optional, self-contained `pdf-kit` module (Fase 0 of the Reportes spec): a hardened `dompdf` wrapper plus a library of atomic document components that any module can use to emit a PDF, with zero knowledge of the CRUD Engine or Reportes.

**Architecture:** Onion layers inside `/app`. Domain holds pure value-object components (`PdfDocument`, `PdfBlock`s) and two interfaces (`PdfTemplateInterface`, `PdfEngineInterface`). Application holds the `PdfComponentRenderer` (block VO → escaped HTML via partials), the `PdfRenderingService` (orchestrator) and the `PdfTemplateRegistry` (whitelist key → template class). Infrastructure holds `DompdfRenderer` (hardened) and `PdfStorage`. Presentation holds the HTML skeleton and one partial per component. The module is declared as a manifest and toggled in `config/vertical.php`. It owns no DB tables and no routes — other modules consume `PdfRenderingService` from the container.

**Tech Stack:** PHP 8.1+, `dompdf/dompdf ^3.1` (already in `composer.json` and installed under `vendor/`), the project's custom DI container (`config/container.php`), and the flat test harness `php tests/run.php` (no PHPUnit). Reference module: Calendario (`config/modules/calendario.php`, `app/Domain/Calendar/`, `app/Application/Services/Calendar*`).

---

## Conventions used by this plan (read once)

- **Namespaces → paths** are PSR-4-style via `app/Kernel/Autoloader.php`: `App\Domain\Pdf\Foo` ⇒ `app/Domain/Pdf/Foo.php`. Always `declare(strict_types=1);`.
- **Tests** are plain files ending in `Test.php` anywhere under `tests/`. They call the global helpers from `tests/lib/microtest.php`: `test(name, fn)`, `assert_true`, `assert_same`, `assert_null`, `assert_throws`. `ROOT_PATH` is defined by `tests/lib/bootstrap.php` (the repo root). The autoloader is loaded automatically — just `use` classes.
- **Run a single test file:** `php tests/run.php <substring-of-path>` runs only files whose path contains the substring. `php tests/run.php` runs everything. Exit code is non-zero if any test fails.
- **Escaping:** every piece of data text rendered into HTML passes through `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')` inside the partial. The renderer never concatenates raw user data into markup.
- **Exceptions:** validation/whitelist failures throw `App\Domain\Exceptions\ValidationException` (constructor: `(string $message = '', array $errors = [], int $code = 422)`).
- **Commits:** Conventional Commits, scope `pdf`. End every commit message with the trailer:
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`

---

## File Structure

**Domain — `app/Domain/Pdf/` (pure VOs + interfaces, no dependencies):**
- `PdfBlock.php` — marker interface; every component implements it (`type(): string`).
- `PdfPageSetup.php` — VO: paper size, orientation, margins (+ `fromArray`).
- `PdfHeader.php`, `PdfLogo.php`, `PdfText.php`, `PdfDataTable.php`, `PdfIndicatorCard.php`, `PdfTotalsBlock.php`, `PdfSignatureBlock.php`, `PdfFooter.php`, `PdfSpacer.php`, `PdfPageBreak.php` — atomic component VOs.
- `PdfDocument.php` — builder: a `PdfPageSetup` + ordered `PdfBlock[]`.
- `PdfTemplateInterface.php` — `compose(array $payload): PdfDocument`.
- `PdfEngineInterface.php` — `render(string $html, PdfPageSetup $setup): string` (bytes).

**Application — `app/Application/Pdf/`:**
- `PdfComponentRenderer.php` — maps each block VO to its escaped-HTML partial; owns value formatting (`money`/`date`/`datetime`/`number`).
- `PdfRenderingService.php` — orchestrator: `renderDocument(PdfDocument)` and `renderTemplate(string $key, array $payload)` → wrap body in skeleton → `PdfEngineInterface` → bytes.
- `PdfTemplateRegistry.php` — resolves key → `PdfTemplateInterface` from an injected whitelist map.
- `Templates/DemoReporteTemplate.php` — demo template (Fase 0 deliverable).

**Infrastructure — `app/Infrastructure/Pdf/`:**
- `DompdfRenderer.php` — `implements PdfEngineInterface`, hardened (`isRemoteEnabled=false`, `isPhpEnabled=false`, `chroot` to repo root, local fonts).
- `PdfStorage.php` — persist bytes under `storage/pdf/` with a safe filename.

**Presentation — `app/Presentation/Views/`:**
- `pdf/document.php` — HTML skeleton (`<html><head><style>print CSS</style></head><body>{body}</body></html>`).
- `partials/pdf/components/*.php` — one partial per component (the reusable atomic library).

**Config:**
- `config/modules/pdf-kit.php` — manifest (`requiere: [core]`, no cruds/providers/bootstrap_sql).
- `config/pdf.php` — paper/orientation/margins/font defaults.
- `config/pdf_templates.php` — whitelist key → template FQCN.
- `config/vertical.php` — add `modules.pdf_kit => true` (modify).
- `config/container.php` — register all kit services (modify).

**Tests — `tests/Pdf/`:**
- `PdfPageSetupTest.php`, `PdfDocumentTest.php`, `PdfComponentRendererTest.php`, `PdfTemplateRegistryTest.php`, `DompdfRendererTest.php`, `PdfRenderingServiceTest.php`.

---

## Task 1: Module manifest + vertical toggle (scaffold, no logic yet)

**Files:**
- Create: `config/modules/pdf-kit.php`
- Modify: `config/vertical.php`

- [ ] **Step 1: Create the manifest**

Create `config/modules/pdf-kit.php`:

```php
<?php

declare(strict_types=1);

// Manifiesto del módulo Kit de PDF. Envoltorio endurecido de dompdf + biblioteca
// de componentes atómicos de documento. No conoce el CRUD Engine ni Reportes:
// cualquier módulo resuelve PdfRenderingService del contenedor para emitir un PDF.
// Sin tablas (no bootstrap_sql), sin rutas, sin providers de dashboard.
return [
    'clave'         => 'pdf-kit',
    'nombre'        => 'Kit de PDF',
    'descripcion'   => 'Envoltorio endurecido de dompdf + componentes atómicos de documento + servicio de render.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => null,
    'cruds'         => [],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
```

- [ ] **Step 2: Toggle the module in the vertical profile**

In `config/vertical.php`, inside the `'modules' => [ ... ]` array, add the `pdf_kit` line after `calendario`:

```php
    'modules' => [
        'dashboard'      => true,
        'administracion' => true,
        'calendario'     => true,
        'pdf_kit'        => true,
    ],
```

- [ ] **Step 3: Verify the manifest parses**

The autoloader self-registers via `spl_autoload_register` on `require` (it needs `ROOT_PATH` and `APP_PATH` defined). Run:

```bash
php -r "define('ROOT_PATH', getcwd()); define('APP_PATH', getcwd().'/app'); require 'app/Kernel/Autoloader.php'; \$m = \App\Application\Install\ModuleManifest::fromArray(require 'config/modules/pdf-kit.php'); echo \$m->clave.' '.\$m->version.PHP_EOL;"
```

Expected output: `pdf-kit 1.0.0` (no exception).

- [ ] **Step 4: Commit**

```bash
git add config/modules/pdf-kit.php config/vertical.php
git commit -m "feat(pdf): add pdf-kit module manifest and vertical toggle

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: `PdfPageSetup` value object

**Files:**
- Create: `app/Domain/Pdf/PdfPageSetup.php`
- Test: `tests/Pdf/PdfPageSetupTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Pdf/PdfPageSetupTest.php`:

```php
<?php
declare(strict_types=1);

use App\Domain\Pdf\PdfPageSetup;

test('PdfPageSetup expone defaults A4 vertical', function (): void {
    $s = new PdfPageSetup();
    assert_same('A4', $s->size());
    assert_same('portrait', $s->orientation());
    assert_same(36, $s->margins()['top']);
});

test('PdfPageSetup normaliza orientación inválida a portrait', function (): void {
    $s = new PdfPageSetup('A4', 'diagonal');
    assert_same('portrait', $s->orientation());
});

test('PdfPageSetup::fromArray lee tamaño, orientación y márgenes', function (): void {
    $s = PdfPageSetup::fromArray([
        'size' => 'letter',
        'orientation' => 'landscape',
        'margins' => ['top' => 10, 'right' => 20, 'bottom' => 30, 'left' => 40],
    ]);
    assert_same('letter', $s->size());
    assert_same('landscape', $s->orientation());
    assert_same(20, $s->margins()['right']);
    assert_same('10px 20px 30px 40px', $s->marginsCss());
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/PdfPageSetup`
Expected: FAIL — `Class "App\Domain\Pdf\PdfPageSetup" not found`.

- [ ] **Step 3: Implement the VO**

Create `app/Domain/Pdf/PdfPageSetup.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/**
 * Configuración de página para un documento PDF: tamaño de papel, orientación y
 * márgenes (en px, interpretados por dompdf vía CSS @page). VO inmutable.
 */
final class PdfPageSetup
{
    private const DEFAULT_MARGINS = ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36];

    private string $size;
    private string $orientation;
    /** @var array{top:int,right:int,bottom:int,left:int} */
    private array $margins;

    /** @param array<string,int>|null $margins */
    public function __construct(string $size = 'A4', string $orientation = 'portrait', ?array $margins = null)
    {
        $this->size = $size !== '' ? $size : 'A4';
        $this->orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';

        $m = $margins ?? self::DEFAULT_MARGINS;
        $this->margins = [
            'top'    => (int) ($m['top']    ?? self::DEFAULT_MARGINS['top']),
            'right'  => (int) ($m['right']  ?? self::DEFAULT_MARGINS['right']),
            'bottom' => (int) ($m['bottom'] ?? self::DEFAULT_MARGINS['bottom']),
            'left'   => (int) ($m['left']   ?? self::DEFAULT_MARGINS['left']),
        ];
    }

    /** @param array<string,mixed> $c */
    public static function fromArray(array $c): self
    {
        $margins = is_array($c['margins'] ?? null) ? $c['margins'] : null;
        return new self(
            (string) ($c['size'] ?? 'A4'),
            (string) ($c['orientation'] ?? 'portrait'),
            $margins,
        );
    }

    public function size(): string { return $this->size; }
    public function orientation(): string { return $this->orientation; }

    /** @return array{top:int,right:int,bottom:int,left:int} */
    public function margins(): array { return $this->margins; }

    /** Márgenes como shorthand CSS `top right bottom left` en px. */
    public function marginsCss(): string
    {
        return sprintf(
            '%dpx %dpx %dpx %dpx',
            $this->margins['top'],
            $this->margins['right'],
            $this->margins['bottom'],
            $this->margins['left'],
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/run.php Pdf/PdfPageSetup`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Pdf/PdfPageSetup.php tests/Pdf/PdfPageSetupTest.php
git commit -m "feat(pdf): add PdfPageSetup value object

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: `PdfBlock` interface + the ten component VOs

These are tiny, immutable data holders. They share one marker interface so the renderer and `PdfDocument` can treat them uniformly.

**Files:**
- Create: `app/Domain/Pdf/PdfBlock.php`
- Create: `app/Domain/Pdf/PdfHeader.php`, `PdfLogo.php`, `PdfText.php`, `PdfDataTable.php`, `PdfIndicatorCard.php`, `PdfTotalsBlock.php`, `PdfSignatureBlock.php`, `PdfFooter.php`, `PdfSpacer.php`, `PdfPageBreak.php`
- Test: `tests/Pdf/PdfDocumentTest.php` (covers blocks + document in Task 4; this task adds block assertions)

- [ ] **Step 1: Write the failing test**

Create `tests/Pdf/PdfDocumentTest.php` with the block assertions (the document assertions are added in Task 4):

```php
<?php
declare(strict_types=1);

use App\Domain\Pdf\PdfBlock;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfText;
use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfIndicatorCard;
use App\Domain\Pdf\PdfTotalsBlock;
use App\Domain\Pdf\PdfSignatureBlock;
use App\Domain\Pdf\PdfLogo;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfPageBreak;

test('cada componente es un PdfBlock y reporta su type()', function (): void {
    $blocks = [
        'header'    => new PdfHeader('Título', 'Sub'),
        'logo'      => new PdfLogo('/tmp/logo.png', 40),
        'text'      => new PdfText('hola'),
        'table'     => new PdfDataTable([['name' => 'id', 'label' => 'N°']], [['id' => 1]]),
        'indicator' => new PdfIndicatorCard('Total', '10', 'money'),
        'totals'    => new PdfTotalsBlock([['label' => 'Total', 'value' => 5, 'format' => 'money']]),
        'signature' => new PdfSignatureBlock(['Firma cliente']),
        'footer'    => new PdfFooter('pie'),
        'spacer'    => new PdfSpacer(20),
        'pagebreak' => new PdfPageBreak(),
    ];
    foreach ($blocks as $expectedType => $block) {
        assert_true($block instanceof PdfBlock, get_class($block) . ' no es PdfBlock');
        assert_same($expectedType, $block->type());
    }
});

test('PdfDataTable conserva columnas y filas', function (): void {
    $t = new PdfDataTable(
        [['name' => 'total', 'label' => 'Total', 'format' => 'money']],
        [['total' => 1200.5]]
    );
    assert_same('money', $t->columns()[0]['format']);
    assert_same(1200.5, $t->rows()[0]['total']);
});

test('PdfText normaliza estilo desconocido a normal', function (): void {
    assert_same('normal', (new PdfText('x', 'fancy'))->style());
    assert_same('bold', (new PdfText('x', 'bold'))->style());
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/PdfDocument`
Expected: FAIL — `Interface "App\Domain\Pdf\PdfBlock" not found`.

- [ ] **Step 3: Implement the interface and all ten VOs**

Create `app/Domain/Pdf/PdfBlock.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/** Marcador para todo componente atómico de un documento PDF. */
interface PdfBlock
{
    /** Slug estable del tipo de bloque (header, logo, text, table, ...). */
    public function type(): string;
}
```

Create `app/Domain/Pdf/PdfHeader.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfHeader implements PdfBlock
{
    public function __construct(
        private readonly string $title,
        private readonly string $subtitle = '',
    ) {}

    public function type(): string { return 'header'; }
    public function title(): string { return $this->title; }
    public function subtitle(): string { return $this->subtitle; }
}
```

Create `app/Domain/Pdf/PdfLogo.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/** Logo por ruta local o data-URI (nunca URL remota: dompdf va con isRemoteEnabled=false). */
final class PdfLogo implements PdfBlock
{
    public function __construct(
        private readonly string $src,
        private readonly int $height = 40,
    ) {}

    public function type(): string { return 'logo'; }
    public function src(): string { return $this->src; }
    public function height(): int { return $this->height; }
}
```

Create `app/Domain/Pdf/PdfText.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfText implements PdfBlock
{
    private const STYLES = ['normal', 'muted', 'bold'];

    private string $style;

    public function __construct(
        private readonly string $text,
        string $style = 'normal',
    ) {
        $this->style = in_array($style, self::STYLES, true) ? $style : 'normal';
    }

    public function type(): string { return 'text'; }
    public function text(): string { return $this->text; }
    public function style(): string { return $this->style; }
}
```

Create `app/Domain/Pdf/PdfDataTable.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/**
 * Tabla de datos. Columnas: lista de ['name','label','format'?]; el renderer aplica
 * el formato (money/date/datetime/number). Filas: lista de mapas columna=>valor.
 */
final class PdfDataTable implements PdfBlock
{
    /**
     * @param list<array{name:string,label:string,format?:string}> $columns
     * @param list<array<string,mixed>> $rows
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $rows,
    ) {}

    public function type(): string { return 'table'; }

    /** @return list<array{name:string,label:string,format?:string}> */
    public function columns(): array { return $this->columns; }

    /** @return list<array<string,mixed>> */
    public function rows(): array { return $this->rows; }
}
```

Create `app/Domain/Pdf/PdfIndicatorCard.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/** Tarjeta KPI: etiqueta + valor ya calculado + formato de presentación. */
final class PdfIndicatorCard implements PdfBlock
{
    public function __construct(
        private readonly string $label,
        private readonly string $value,
        private readonly string $format = 'raw',
    ) {}

    public function type(): string { return 'indicator'; }
    public function label(): string { return $this->label; }
    public function value(): string { return $this->value; }
    public function format(): string { return $this->format; }
}
```

Create `app/Domain/Pdf/PdfTotalsBlock.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/** Bloque de totales: lista de ['label','value','format'?]. */
final class PdfTotalsBlock implements PdfBlock
{
    /** @param list<array{label:string,value:mixed,format?:string}> $totals */
    public function __construct(
        private readonly array $totals,
    ) {}

    public function type(): string { return 'totals'; }

    /** @return list<array{label:string,value:mixed,format?:string}> */
    public function totals(): array { return $this->totals; }
}
```

Create `app/Domain/Pdf/PdfSignatureBlock.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/** Líneas de firma (cada string es una etiqueta bajo una línea para firmar). */
final class PdfSignatureBlock implements PdfBlock
{
    /** @param list<string> $labels */
    public function __construct(
        private readonly array $labels,
    ) {}

    public function type(): string { return 'signature'; }

    /** @return list<string> */
    public function labels(): array { return $this->labels; }
}
```

Create `app/Domain/Pdf/PdfFooter.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfFooter implements PdfBlock
{
    public function __construct(
        private readonly string $text,
    ) {}

    public function type(): string { return 'footer'; }
    public function text(): string { return $this->text; }
}
```

Create `app/Domain/Pdf/PdfSpacer.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfSpacer implements PdfBlock
{
    public function __construct(
        private readonly int $height = 12,
    ) {}

    public function type(): string { return 'spacer'; }
    public function height(): int { return $this->height; }
}
```

Create `app/Domain/Pdf/PdfPageBreak.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfPageBreak implements PdfBlock
{
    public function type(): string { return 'pagebreak'; }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/run.php Pdf/PdfDocument`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Pdf/PdfBlock.php app/Domain/Pdf/Pdf*.php tests/Pdf/PdfDocumentTest.php
git commit -m "feat(pdf): add PdfBlock interface and ten atomic component VOs

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: `PdfDocument` builder

**Files:**
- Create: `app/Domain/Pdf/PdfDocument.php`
- Test: `tests/Pdf/PdfDocumentTest.php` (append)

- [ ] **Step 1: Add the failing test**

Append to `tests/Pdf/PdfDocumentTest.php`:

```php
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfPageSetup;

test('PdfDocument acumula bloques en orden y expone su setup', function (): void {
    $doc = PdfDocument::make(new PdfPageSetup('A4', 'landscape'))
        ->add(new PdfHeader('Reporte'))
        ->add(new PdfText('cuerpo'))
        ->add(new PdfFooter('pie'));

    assert_same('landscape', $doc->setup()->orientation());
    assert_same(3, count($doc->blocks()));
    assert_same('header', $doc->blocks()[0]->type());
    assert_same('footer', $doc->blocks()[2]->type());
});

test('PdfDocument::make sin setup usa A4 vertical por defecto', function (): void {
    $doc = PdfDocument::make();
    assert_same('A4', $doc->setup()->size());
    assert_same([], $doc->blocks());
});
```

(The `use App\Domain\Pdf\PdfHeader;`, `PdfText`, `PdfFooter` imports already exist at the top of the file from Task 3 — do not duplicate them; add only the two new `use` lines above.)

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/PdfDocument`
Expected: FAIL — `Class "App\Domain\Pdf\PdfDocument" not found`.

- [ ] **Step 3: Implement the builder**

Create `app/Domain/Pdf/PdfDocument.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/** Documento PDF: una página configurada + una lista ordenada de bloques atómicos. */
final class PdfDocument
{
    /** @var list<PdfBlock> */
    private array $blocks = [];

    public function __construct(
        private readonly PdfPageSetup $setup,
    ) {}

    public static function make(?PdfPageSetup $setup = null): self
    {
        return new self($setup ?? new PdfPageSetup());
    }

    public function add(PdfBlock $block): self
    {
        $this->blocks[] = $block;
        return $this;
    }

    public function setup(): PdfPageSetup { return $this->setup; }

    /** @return list<PdfBlock> */
    public function blocks(): array { return $this->blocks; }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/run.php Pdf/PdfDocument`
Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Pdf/PdfDocument.php tests/Pdf/PdfDocumentTest.php
git commit -m "feat(pdf): add PdfDocument builder

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Domain interfaces `PdfTemplateInterface` + `PdfEngineInterface`

**Files:**
- Create: `app/Domain/Pdf/PdfTemplateInterface.php`
- Create: `app/Domain/Pdf/PdfEngineInterface.php`

No standalone test — these are exercised by the registry (Task 8), the engine (Task 9) and the service (Task 10).

- [ ] **Step 1: Create `PdfTemplateInterface`**

Create `app/Domain/Pdf/PdfTemplateInterface.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/**
 * Plantilla del programador: recibe un payload de datos y compone un PdfDocument.
 * El kit nunca recibe HTML de usuario; toda la estructura del documento vive aquí.
 */
interface PdfTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument;

    /** Modos soportados ('coleccion' | 'registro'); usado por Reportes para validar. */
    public function supports(string $mode): bool;
}
```

- [ ] **Step 2: Create `PdfEngineInterface`**

Create `app/Domain/Pdf/PdfEngineInterface.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Pdf;

/** Motor de render: HTML + configuración de página → bytes del PDF. */
interface PdfEngineInterface
{
    /** @return string bytes binarios del PDF (empiezan con "%PDF"). */
    public function render(string $html, PdfPageSetup $setup): string;
}
```

- [ ] **Step 3: Verify both files parse**

Run: `php -l app/Domain/Pdf/PdfTemplateInterface.php && php -l app/Domain/Pdf/PdfEngineInterface.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Pdf/PdfTemplateInterface.php app/Domain/Pdf/PdfEngineInterface.php
git commit -m "feat(pdf): add PdfTemplateInterface and PdfEngineInterface

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: Component partials (the reusable atomic HTML library)

These are PHP view fragments. Each receives plain scalars/arrays and **escapes every data value** with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. The renderer (Task 7) computes already-formatted strings for table/totals cells, so partials only escape.

**Files:**
- Create: `app/Presentation/Views/partials/pdf/components/header.php`
- Create: `.../logo.php`, `text.php`, `data_table.php`, `indicator.php`, `totals.php`, `signature.php`, `footer.php`, `spacer.php`, `pagebreak.php`

No standalone test — covered by `PdfComponentRendererTest` in Task 7.

- [ ] **Step 1: Create `header.php`**

```php
<?php
/** @var string $title @var string $subtitle */
?>
<div class="pdf-header">
  <h1 class="pdf-h1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
  <?php if ($subtitle !== ''): ?>
    <p class="pdf-subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
</div>
```

- [ ] **Step 2: Create `logo.php`**

```php
<?php
/** @var string $src @var int $height */
?>
<?php if ($src !== ''): ?>
  <div class="pdf-logo">
    <img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" style="height: <?= (int) $height ?>px;" alt="">
  </div>
<?php endif; ?>
```

- [ ] **Step 3: Create `text.php`**

```php
<?php
/** @var string $text @var string $style */
$cls = 'pdf-text pdf-text-' . preg_replace('/[^a-z]/', '', $style);
?>
<p class="<?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></p>
```

- [ ] **Step 4: Create `data_table.php`**

The renderer passes `$headers` (list of label strings) and `$matrix` (list of rows; each row is a list of already-formatted cell strings).

```php
<?php
/** @var list<string> $headers @var list<list<string>> $matrix */
?>
<table class="pdf-table">
  <thead>
    <tr>
      <?php foreach ($headers as $h): ?>
        <th><?= htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php if ($matrix === []): ?>
      <tr><td class="pdf-empty" colspan="<?= max(1, count($headers)) ?>">Sin datos.</td></tr>
    <?php else: ?>
      <?php foreach ($matrix as $row): ?>
        <tr>
          <?php foreach ($row as $cell): ?>
            <td><?= htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
```

- [ ] **Step 5: Create `indicator.php`**

```php
<?php
/** @var string $label @var string $value */
?>
<div class="pdf-indicator">
  <div class="pdf-indicator-value"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></div>
  <div class="pdf-indicator-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
</div>
```

- [ ] **Step 6: Create `totals.php`**

The renderer passes `$rows` (list of `['label' => string, 'value' => string]`, value already formatted).

```php
<?php
/** @var list<array{label:string,value:string}> $rows */
?>
<table class="pdf-totals">
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="pdf-totals-label"><?= htmlspecialchars((string) ($r['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
      <td class="pdf-totals-value"><?= htmlspecialchars((string) ($r['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
  <?php endforeach; ?>
</table>
```

- [ ] **Step 7: Create `signature.php`**

```php
<?php
/** @var list<string> $labels */
?>
<div class="pdf-signatures">
  <?php foreach ($labels as $label): ?>
    <div class="pdf-signature">
      <div class="pdf-signature-line">&nbsp;</div>
      <div class="pdf-signature-label"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  <?php endforeach; ?>
</div>
```

- [ ] **Step 8: Create `footer.php`**

```php
<?php
/** @var string $text */
?>
<div class="pdf-footer"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></div>
```

- [ ] **Step 9: Create `spacer.php` and `pagebreak.php`**

`spacer.php`:

```php
<?php
/** @var int $height */
?>
<div class="pdf-spacer" style="height: <?= (int) $height ?>px;"></div>
```

`pagebreak.php`:

```php
<?php ?>
<div class="pdf-pagebreak" style="page-break-after: always;"></div>
```

- [ ] **Step 10: Verify all partials parse**

Run: `for f in app/Presentation/Views/partials/pdf/components/*.php; do php -l "$f" || exit 1; done`
Expected: `No syntax errors detected` for each of the 10 files.

- [ ] **Step 11: Commit**

```bash
git add app/Presentation/Views/partials/pdf/components/
git commit -m "feat(pdf): add atomic component partials with HTML escaping

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: `PdfComponentRenderer` (block VO → escaped HTML)

Maps each `PdfBlock` to its partial, computes value formatting (`money`/`date`/`datetime`/`number`/`raw`) for tables and totals, and concatenates the result.

**Files:**
- Create: `app/Application/Pdf/PdfComponentRenderer.php`
- Test: `tests/Pdf/PdfComponentRendererTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Pdf/PdfComponentRendererTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application\Pdf\PdfComponentRenderer;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfText;
use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfTotalsBlock;
use App\Domain\Pdf\PdfPageBreak;

test('renderiza header escapando el título', function (): void {
    $html = (new PdfComponentRenderer())->renderBlocks([new PdfHeader('<b>A&B</b>', 'sub')]);
    assert_true(str_contains($html, '&lt;b&gt;A&amp;B&lt;/b&gt;'), 'título debe ir escapado');
    assert_true(str_contains($html, 'sub'), 'subtítulo presente');
});

test('renderiza tabla con formato money y escapa contenido', function (): void {
    $table = new PdfDataTable(
        [['name' => 'cliente', 'label' => 'Cliente'], ['name' => 'total', 'label' => 'Total', 'format' => 'money']],
        [['cliente' => 'Ana <x>', 'total' => 1200.5]]
    );
    $html = (new PdfComponentRenderer())->renderBlocks([$table]);
    assert_true(str_contains($html, 'Ana &lt;x&gt;'), 'celda de texto escapada');
    assert_true(str_contains($html, '$1,200.50'), 'formato money aplicado');
});

test('formatea date y datetime', function (): void {
    $r = new PdfComponentRenderer();
    assert_same('2026-06-14', $r->formatValue('2026-06-14 09:30:00', 'date'));
    assert_same('2026-06-14 09:30', $r->formatValue('2026-06-14 09:30:00', 'datetime'));
    assert_same('1,234', $r->formatValue(1234, 'number'));
    assert_same('hola', $r->formatValue('hola', 'raw'));
});

test('renderiza totales y un page break', function (): void {
    $html = (new PdfComponentRenderer())->renderBlocks([
        new PdfTotalsBlock([['label' => 'Total', 'value' => 50, 'format' => 'money']]),
        new PdfPageBreak(),
    ]);
    assert_true(str_contains($html, 'Total'), 'etiqueta total');
    assert_true(str_contains($html, '$50.00'), 'valor total con formato');
    assert_true(str_contains($html, 'page-break-after'), 'page break presente');
});

test('un PdfText con estilo desconocido cae a normal sin romper el HTML', function (): void {
    $html = (new PdfComponentRenderer())->renderBlocks([new PdfText('cuerpo', 'fancy')]);
    assert_true(str_contains($html, 'pdf-text-normal'), 'estilo normalizado');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/PdfComponentRenderer`
Expected: FAIL — `Class "App\Application\Pdf\PdfComponentRenderer" not found`.

- [ ] **Step 3: Implement the renderer**

Create `app/Application/Pdf/PdfComponentRenderer.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Pdf\PdfBlock;
use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfIndicatorCard;
use App\Domain\Pdf\PdfLogo;
use App\Domain\Pdf\PdfPageBreak;
use App\Domain\Pdf\PdfSignatureBlock;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfText;
use App\Domain\Pdf\PdfTotalsBlock;

/**
 * Convierte bloques de documento (VOs puros) en HTML pensado para dompdf. Toda
 * presentación vive en partials bajo Views/partials/pdf/components; aquí solo se
 * preparan los datos (incluido el formateo de valores) y se delega el escape de
 * texto al partial. Sin recursos remotos, sin HTML de usuario.
 */
final class PdfComponentRenderer
{
    private const COMPONENTS_DIR = ROOT_PATH . '/app/Presentation/Views/partials/pdf/components/';

    /** @param list<PdfBlock> $blocks */
    public function renderBlocks(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }
        return $html;
    }

    public function renderBlock(PdfBlock $block): string
    {
        return match (true) {
            $block instanceof PdfHeader        => $this->partial('header', ['title' => $block->title(), 'subtitle' => $block->subtitle()]),
            $block instanceof PdfLogo          => $this->partial('logo', ['src' => $block->src(), 'height' => $block->height()]),
            $block instanceof PdfText          => $this->partial('text', ['text' => $block->text(), 'style' => $block->style()]),
            $block instanceof PdfDataTable     => $this->renderTable($block),
            $block instanceof PdfIndicatorCard => $this->partial('indicator', ['label' => $block->label(), 'value' => $this->formatValue($block->value(), $block->format())]),
            $block instanceof PdfTotalsBlock   => $this->renderTotals($block),
            $block instanceof PdfSignatureBlock => $this->partial('signature', ['labels' => $block->labels()]),
            $block instanceof PdfFooter        => $this->partial('footer', ['text' => $block->text()]),
            $block instanceof PdfSpacer        => $this->partial('spacer', ['height' => $block->height()]),
            $block instanceof PdfPageBreak     => $this->partial('pagebreak', []),
            default                            => '',
        };
    }

    private function renderTable(PdfDataTable $table): string
    {
        $headers = array_map(static fn(array $c): string => (string) ($c['label'] ?? ''), $table->columns());

        $matrix = [];
        foreach ($table->rows() as $row) {
            $cells = [];
            foreach ($table->columns() as $col) {
                $name = (string) ($col['name'] ?? '');
                $format = (string) ($col['format'] ?? 'raw');
                $cells[] = $this->formatValue($row[$name] ?? '', $format);
            }
            $matrix[] = $cells;
        }

        return $this->partial('data_table', ['headers' => $headers, 'matrix' => $matrix]);
    }

    private function renderTotals(PdfTotalsBlock $totals): string
    {
        $rows = [];
        foreach ($totals->totals() as $t) {
            $rows[] = [
                'label' => (string) ($t['label'] ?? ''),
                'value' => $this->formatValue($t['value'] ?? '', (string) ($t['format'] ?? 'raw')),
            ];
        }
        return $this->partial('totals', ['rows' => $rows]);
    }

    /** Formatea un valor escalar según el formato declarado. Devuelve siempre string. */
    public function formatValue(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($format) {
            'money'    => '$' . number_format((float) $value, 2),
            'number'   => number_format((float) $value),
            'date'     => $this->formatDate((string) $value, 'Y-m-d'),
            'datetime' => $this->formatDate((string) $value, 'Y-m-d H:i'),
            default    => (string) $value,
        };
    }

    private function formatDate(string $value, string $fmt): string
    {
        $ts = strtotime($value);
        return $ts === false ? $value : date($fmt, $ts);
    }

    /** @param array<string,mixed> $vars */
    private function partial(string $name, array $vars): string
    {
        $file = self::COMPONENTS_DIR . $name . '.php';
        if (!is_readable($file)) {
            return '';
        }
        extract($vars, EXTR_OVERWRITE);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/run.php Pdf/PdfComponentRenderer`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Pdf/PdfComponentRenderer.php tests/Pdf/PdfComponentRendererTest.php
git commit -m "feat(pdf): add PdfComponentRenderer with value formatting and escaping

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: `PdfTemplateRegistry` (whitelist key → template class)

Resolves a template by key from an injected map (no FQCN ever comes from user data — same policy as CRUD handlers).

**Files:**
- Create: `app/Application/Pdf/PdfTemplateRegistry.php`
- Test: `tests/Pdf/PdfTemplateRegistryTest.php`
- Test fixture: `tests/fixtures/pdf_templates.php`

- [ ] **Step 1: Write the failing test**

Create `tests/fixtures/pdf_templates.php` (a valid + an invalid stand-in template):

```php
<?php
declare(strict_types=1);

use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfTemplateInterface;

final class FixtureOkTemplate implements PdfTemplateInterface
{
    public function compose(array $payload): PdfDocument
    {
        return PdfDocument::make()->add(new PdfHeader((string) ($payload['title'] ?? 'Demo')));
    }

    public function supports(string $mode): bool
    {
        return $mode === 'coleccion';
    }
}

final class FixtureNotATemplate
{
}
```

Create `tests/Pdf/PdfTemplateRegistryTest.php`:

```php
<?php
declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/pdf_templates.php';

use App\Application\Pdf\PdfTemplateRegistry;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Pdf\PdfTemplateInterface;

function ptr_registry(): PdfTemplateRegistry
{
    return new PdfTemplateRegistry([
        'ok'       => FixtureOkTemplate::class,
        'broken'   => FixtureNotATemplate::class,
        'missing'  => 'App\\Nope\\DoesNotExist',
    ]);
}

test('resuelve una clave válida a una instancia PdfTemplateInterface', function (): void {
    $tpl = ptr_registry()->resolve('ok');
    assert_true($tpl instanceof PdfTemplateInterface, 'instancia de plantilla');
    assert_true($tpl->supports('coleccion'), 'soporta colección');
});

test('clave inexistente lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => ptr_registry()->resolve('fantasma'));
});

test('clase que no implementa la interfaz lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => ptr_registry()->resolve('broken'));
});

test('clase inexistente lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => ptr_registry()->resolve('missing'));
});

test('has() refleja presencia en el whitelist', function (): void {
    assert_true(ptr_registry()->has('ok'));
    assert_true(!ptr_registry()->has('fantasma'));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/PdfTemplateRegistry`
Expected: FAIL — `Class "App\Application\Pdf\PdfTemplateRegistry" not found`.

- [ ] **Step 3: Implement the registry**

Create `app/Application/Pdf/PdfTemplateRegistry.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Exceptions\ValidationException;
use App\Domain\Pdf\PdfTemplateInterface;

/**
 * Whitelist clave → clase de plantilla. Misma política que los handlers del CRUD
 * Engine: jamás se acepta un FQCN proveniente de datos de usuario; solo claves que
 * el programador registró en config/pdf_templates.php.
 */
final class PdfTemplateRegistry
{
    /** @var array<string,class-string> */
    private array $map;

    /** @param array<string,string> $map clave => FQCN de PdfTemplateInterface */
    public function __construct(array $map)
    {
        $clean = [];
        foreach ($map as $key => $class) {
            $key = (string) $key;
            if ($key !== '' && is_string($class) && $class !== '') {
                $clean[$key] = $class;
            }
        }
        $this->map = $clean;
    }

    public function has(string $key): bool
    {
        return isset($this->map[$key]);
    }

    public function resolve(string $key): PdfTemplateInterface
    {
        $class = $this->map[$key] ?? null;
        if ($class === null) {
            throw new ValidationException("No existe la plantilla PDF '{$key}'.");
        }
        if (!class_exists($class)) {
            throw new ValidationException("La plantilla PDF '{$key}' apunta a una clase inexistente.");
        }
        $instance = new $class();
        if (!$instance instanceof PdfTemplateInterface) {
            throw new ValidationException("La plantilla PDF '{$key}' no implementa PdfTemplateInterface.");
        }
        return $instance;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/run.php Pdf/PdfTemplateRegistry`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Pdf/PdfTemplateRegistry.php tests/Pdf/PdfTemplateRegistryTest.php tests/fixtures/pdf_templates.php
git commit -m "feat(pdf): add PdfTemplateRegistry whitelist resolver

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: `DompdfRenderer` (hardened `PdfEngineInterface`) + `PdfStorage`

**Files:**
- Create: `app/Infrastructure/Pdf/DompdfRenderer.php`
- Create: `app/Infrastructure/Pdf/PdfStorage.php`
- Test: `tests/Pdf/DompdfRendererTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Pdf/DompdfRendererTest.php`:

```php
<?php
declare(strict_types=1);

use App\Infrastructure\Pdf\DompdfRenderer;
use App\Infrastructure\Pdf\PdfStorage;
use App\Domain\Pdf\PdfPageSetup;

test('DompdfRenderer produce bytes que empiezan con %PDF', function (): void {
    $bytes = (new DompdfRenderer())->render(
        '<html><body><h1>Hola</h1></body></html>',
        new PdfPageSetup('A4', 'portrait')
    );
    assert_true(strlen($bytes) > 100, 'el PDF no debe estar vacío');
    assert_same('%PDF', substr($bytes, 0, 4));
});

test('PdfStorage guarda bytes y devuelve una ruta legible', function (): void {
    $path = (new PdfStorage())->save("%PDF-1.7\nx", 'prueba demo.pdf');
    assert_true(is_readable($path), 'el archivo guardado debe existir');
    assert_same('%PDF', substr((string) file_get_contents($path), 0, 4));
    @unlink($path);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/DompdfRenderer`
Expected: FAIL — `Class "App\Infrastructure\Pdf\DompdfRenderer" not found`.

- [ ] **Step 3: Implement the renderer**

Create `app/Infrastructure/Pdf/DompdfRenderer.php`:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Pdf\PdfEngineInterface;
use App\Domain\Pdf\PdfPageSetup;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Motor dompdf endurecido: sin recursos remotos, sin PHP embebido, chroot al repo
 * y fuentes locales. Recibe HTML ya escapado por el renderer; nunca HTML de usuario.
 */
final class DompdfRenderer implements PdfEngineInterface
{
    public function __construct(
        private readonly string $defaultFont = 'DejaVu Sans',
    ) {}

    public function render(string $html, PdfPageSetup $setup): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', ROOT_PATH);
        $options->set('defaultFont', $this->defaultFont);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($setup->size(), $setup->orientation());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
```

- [ ] **Step 4: Implement `PdfStorage`**

Create `app/Infrastructure/Pdf/PdfStorage.php`:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Pdf;

/**
 * Persiste bytes de PDF bajo storage/pdf con un nombre saneado. Útil para reportes
 * archivables / auditoría. Opcional: la descarga directa no necesita guardar.
 */
final class PdfStorage
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (ROOT_PATH . '/storage/pdf');
    }

    /** Guarda los bytes y devuelve la ruta absoluta del archivo. */
    public function save(string $bytes, string $filename): string
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }

        $safe = $this->safeName($filename);
        $path = $this->dir . '/' . $safe;
        file_put_contents($path, $bytes);

        return $path;
    }

    private function safeName(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?? 'documento';
        $base = trim($base, '-') ?: 'documento';
        return $base . '-' . date('Ymd-His') . '.pdf';
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php tests/run.php Pdf/DompdfRenderer`
Expected: PASS (2 tests). If dompdf throws on missing font cache, confirm `vendor/dompdf/dompdf` exists (it does) and that `storage/` is writable.

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Pdf/DompdfRenderer.php app/Infrastructure/Pdf/PdfStorage.php tests/Pdf/DompdfRendererTest.php
git commit -m "feat(pdf): add hardened DompdfRenderer and PdfStorage

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 10: HTML skeleton + `PdfRenderingService` (orchestrator)

Wraps the rendered block HTML in the document skeleton (print CSS) and drives the engine. Also resolves and renders templates by key.

**Files:**
- Create: `app/Presentation/Views/pdf/document.php`
- Create: `app/Application/Pdf/PdfRenderingService.php`
- Test: `tests/Pdf/PdfRenderingServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Pdf/PdfRenderingServiceTest.php`. It uses a fake engine (to assert the assembled HTML) and the registry fixture from Task 8.

```php
<?php
declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/pdf_templates.php';

use App\Application\Pdf\PdfComponentRenderer;
use App\Application\Pdf\PdfRenderingService;
use App\Application\Pdf\PdfTemplateRegistry;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfEngineInterface;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfPageSetup;

/** Motor falso: captura el HTML y la configuración recibidos. */
final class SpyEngine implements PdfEngineInterface
{
    public string $html = '';
    public ?PdfPageSetup $setup = null;

    public function render(string $html, PdfPageSetup $setup): string
    {
        $this->html = $html;
        $this->setup = $setup;
        return "%PDF-fake";
    }
}

function prs_service(PdfEngineInterface $engine): PdfRenderingService
{
    return new PdfRenderingService(
        new PdfComponentRenderer(),
        $engine,
        new PdfTemplateRegistry(['ok' => FixtureOkTemplate::class]),
        ['font' => 'DejaVu Sans']
    );
}

test('renderDocument envuelve los bloques en el esqueleto y pasa el setup', function (): void {
    $spy = new SpyEngine();
    $doc = PdfDocument::make(new PdfPageSetup('A4', 'landscape'))->add(new PdfHeader('Reporte'));

    $bytes = prs_service($spy)->renderDocument($doc);

    assert_same('%PDF-fake', $bytes);
    assert_true(str_contains($spy->html, '<html'), 'incluye esqueleto html');
    assert_true(str_contains($spy->html, 'Reporte'), 'incluye el cuerpo renderizado');
    assert_same('landscape', $spy->setup?->orientation());
});

test('renderTemplate resuelve la clave, compone y renderiza', function (): void {
    $spy = new SpyEngine();
    $bytes = prs_service($spy)->renderTemplate('ok', ['title' => 'Desde plantilla']);

    assert_same('%PDF-fake', $bytes);
    assert_true(str_contains($spy->html, 'Desde plantilla'), 'compose() usó el payload');
});

test('renderTemplate con motor real produce %PDF', function (): void {
    $service = new PdfRenderingService(
        new PdfComponentRenderer(),
        new \App\Infrastructure\Pdf\DompdfRenderer(),
        new PdfTemplateRegistry(['ok' => FixtureOkTemplate::class]),
        []
    );
    $bytes = $service->renderTemplate('ok', ['title' => 'Real']);
    assert_same('%PDF', substr($bytes, 0, 4));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/PdfRenderingService`
Expected: FAIL — `Class "App\Application\Pdf\PdfRenderingService" not found`.

- [ ] **Step 3: Create the HTML skeleton**

Create `app/Presentation/Views/pdf/document.php`. It receives `$bodyHtml` (already-escaped component HTML — echoed raw) and `$font`.

```php
<?php
/** @var string $bodyHtml @var string $font */
$font = $font !== '' ? $font : 'DejaVu Sans';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }
  body { font-family: "<?= htmlspecialchars($font, ENT_QUOTES, 'UTF-8') ?>", sans-serif; font-size: 12px; color: #1a1a1a; }
  .pdf-h1 { font-size: 20px; margin: 0 0 2px; }
  .pdf-subtitle { color: #666; margin: 0 0 8px; }
  .pdf-text { margin: 4px 0; }
  .pdf-text-muted { color: #777; }
  .pdf-text-bold { font-weight: bold; }
  .pdf-logo img { display: block; }
  .pdf-spacer { display: block; }
  .pdf-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  .pdf-table th, .pdf-table td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
  .pdf-table th { background: #f2f2f2; }
  .pdf-empty { text-align: center; color: #999; }
  .pdf-indicator { display: inline-block; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; margin: 4px 6px 4px 0; }
  .pdf-indicator-value { font-size: 18px; font-weight: bold; }
  .pdf-indicator-label { font-size: 10px; color: #666; text-transform: uppercase; }
  .pdf-totals { margin: 8px 0; }
  .pdf-totals-label { padding: 2px 10px 2px 0; color: #555; }
  .pdf-totals-value { padding: 2px 0; font-weight: bold; text-align: right; }
  .pdf-signatures { margin-top: 40px; }
  .pdf-signature { display: inline-block; width: 45%; margin: 0 2% 16px; text-align: center; }
  .pdf-signature-line { border-top: 1px solid #333; margin-bottom: 4px; }
  .pdf-signature-label { font-size: 10px; color: #555; }
  .pdf-footer { margin-top: 16px; padding-top: 6px; border-top: 1px solid #ddd; font-size: 10px; color: #888; text-align: center; }
</style>
</head>
<body>
<?= $bodyHtml ?>
</body>
</html>
```

- [ ] **Step 4: Implement the service**

Create `app/Application/Pdf/PdfRenderingService.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfEngineInterface;

/**
 * Orquesta el render: arma el HTML (esqueleto + bloques) y delega en el motor para
 * obtener bytes. Punto de entrada para cualquier módulo que quiera emitir un PDF,
 * sea desde un PdfDocument ya armado o desde una plantilla registrada por clave.
 */
final class PdfRenderingService
{
    private const SKELETON = ROOT_PATH . '/app/Presentation/Views/pdf/document.php';

    /** @param array<string,mixed> $config defaults de config/pdf.php (font, etc.) */
    public function __construct(
        private readonly PdfComponentRenderer $renderer,
        private readonly PdfEngineInterface $engine,
        private readonly PdfTemplateRegistry $registry,
        private readonly array $config = [],
    ) {}

    public function renderDocument(PdfDocument $document): string
    {
        $bodyHtml = $this->renderer->renderBlocks($document->blocks());
        $html = $this->wrap($bodyHtml);
        return $this->engine->render($html, $document->setup());
    }

    /** @param array<string,mixed> $payload */
    public function renderTemplate(string $key, array $payload): string
    {
        $document = $this->registry->resolve($key)->compose($payload);
        return $this->renderDocument($document);
    }

    private function wrap(string $bodyHtml): string
    {
        $font = (string) ($this->config['font'] ?? 'DejaVu Sans');
        ob_start();
        include self::SKELETON;
        return (string) ob_get_clean();
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php tests/run.php Pdf/PdfRenderingService`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Views/pdf/document.php app/Application/Pdf/PdfRenderingService.php tests/Pdf/PdfRenderingServiceTest.php
git commit -m "feat(pdf): add document skeleton and PdfRenderingService orchestrator

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 11: Demo template + config files

Provides the Fase 0 deliverable: a real template registered by key that any module (or a smoke check) can render.

**Files:**
- Create: `app/Application/Pdf/Templates/DemoReporteTemplate.php`
- Create: `config/pdf.php`
- Create: `config/pdf_templates.php`
- Test: `tests/Pdf/PdfRenderingServiceTest.php` (append an integration assertion against the real config wiring)

- [ ] **Step 1: Write the failing test**

Append to `tests/Pdf/PdfRenderingServiceTest.php`:

```php
test('la plantilla demo registrada en config produce un PDF de colección', function (): void {
    $map = require ROOT_PATH . '/config/pdf_templates.php';
    $pdfConfig = require ROOT_PATH . '/config/pdf.php';

    $service = new PdfRenderingService(
        new PdfComponentRenderer(),
        new \App\Infrastructure\Pdf\DompdfRenderer(),
        new PdfTemplateRegistry($map),
        $pdfConfig
    );

    $bytes = $service->renderTemplate('demo_reporte', [
        'titulo'  => 'Pedidos del mes',
        'columns' => [['name' => 'cliente', 'label' => 'Cliente'], ['name' => 'total', 'label' => 'Total', 'format' => 'money']],
        'rows'    => [['cliente' => 'Ana', 'total' => 1200.5], ['cliente' => 'Beto', 'total' => 980]],
    ]);

    assert_same('%PDF', substr($bytes, 0, 4));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Pdf/PdfRenderingService`
Expected: FAIL — `require` of `config/pdf_templates.php` errors (file missing) or template key `demo_reporte` not found.

- [ ] **Step 3: Implement the demo template**

Create `app/Application/Pdf/Templates/DemoReporteTemplate.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Pdf\Templates;

use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfPageSetup;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfTemplateInterface;

/**
 * Plantilla demo de colección: cabecera + tabla de filas. Sirve de ejemplo mínimo
 * del kit y de objetivo de prueba para Fase 0. Reportes (Fase 1) aporta plantillas
 * más ricas; el HTML/diseño siempre lo define el programador, nunca el usuario.
 */
final class DemoReporteTemplate implements PdfTemplateInterface
{
    public function compose(array $payload): PdfDocument
    {
        $titulo  = (string) ($payload['titulo'] ?? 'Reporte');
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];
        $rows    = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        return PdfDocument::make(new PdfPageSetup('A4', 'portrait'))
            ->add(new PdfHeader($titulo, 'Documento de demostración del Kit de PDF'))
            ->add(new PdfSpacer(8))
            ->add(new PdfDataTable($columns, $rows))
            ->add(new PdfFooter('Generado por el Kit de PDF'));
    }

    public function supports(string $mode): bool
    {
        return $mode === 'coleccion';
    }
}
```

- [ ] **Step 4: Create `config/pdf.php`**

```php
<?php

declare(strict_types=1);

// Defaults del Kit de PDF: papel, orientación, márgenes y fuente. La "marca" del
// documento (logo, empresa, colores) NO se fija aquí: cada módulo que emite un PDF
// la pasa en el payload (clave 'marca'), típicamente leída de cfg_configuraciones
// vía ConfiguracionService. Así el kit no depende de la base de datos.
return [
    'paper'       => 'A4',
    'orientation' => 'portrait',
    'margins'     => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
    'font'        => 'DejaVu Sans',
];
```

- [ ] **Step 5: Create `config/pdf_templates.php`**

```php
<?php

declare(strict_types=1);

use App\Application\Pdf\Templates\DemoReporteTemplate;

// Whitelist de plantillas PDF: clave estable => clase PdfTemplateInterface.
// NUNCA se acepta un FQCN proveniente de datos de usuario; solo estas claves.
// Reportes y otros módulos añaden sus plantillas aquí.
return [
    'demo_reporte' => DemoReporteTemplate::class,
];
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php tests/run.php Pdf/PdfRenderingService`
Expected: PASS (4 tests including the new integration one).

- [ ] **Step 7: Commit**

```bash
git add app/Application/Pdf/Templates/DemoReporteTemplate.php config/pdf.php config/pdf_templates.php tests/Pdf/PdfRenderingServiceTest.php
git commit -m "feat(pdf): add demo template and pdf config files

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 12: DI container wiring

Register every kit service so other modules resolve `PdfRenderingService` from the container. Follow the existing Calendario block style in `config/container.php`.

**Files:**
- Modify: `config/container.php`

- [ ] **Step 1: Read the Calendario block for placement and style**

Open `config/container.php` and locate the comment line `// ── Módulo Calendario ──` (around line 226). The new block goes immediately after the Calendario service registrations (before the controller `bind(...)` section is fine; keep it next to other module service singletons).

- [ ] **Step 2: Add the pdf-kit registrations**

Insert this block after the Calendario service singletons (adjust surrounding whitespace to match the file). `$container` and the `Container` type alias are already in scope in this file (same as the Calendario block).

```php
    // ── Módulo Kit de PDF ───────────────────────────────────────────────────
    $container->singleton(\App\Application\Pdf\PdfComponentRenderer::class, fn() => new \App\Application\Pdf\PdfComponentRenderer());

    $container->singleton(\App\Domain\Pdf\PdfEngineInterface::class, fn() => new \App\Infrastructure\Pdf\DompdfRenderer(
        (string) (require ROOT_PATH . '/config/pdf.php')['font']
    ));

    $container->singleton(\App\Application\Pdf\PdfTemplateRegistry::class, fn() => new \App\Application\Pdf\PdfTemplateRegistry(
        require ROOT_PATH . '/config/pdf_templates.php'
    ));

    $container->singleton(\App\Infrastructure\Pdf\PdfStorage::class, fn() => new \App\Infrastructure\Pdf\PdfStorage());

    $container->singleton(\App\Application\Pdf\PdfRenderingService::class, fn(Container $c) => new \App\Application\Pdf\PdfRenderingService(
        $c->get(\App\Application\Pdf\PdfComponentRenderer::class),
        $c->get(\App\Domain\Pdf\PdfEngineInterface::class),
        $c->get(\App\Application\Pdf\PdfTemplateRegistry::class),
        require ROOT_PATH . '/config/pdf.php'
    ));
```

- [ ] **Step 3: Verify the container resolves the service**

`config/container.php` returns a closure `function (Container $container): void` that registers bindings into a container you pass in. Invoking it only *registers* (binding closures are lazy), so resolving `PdfRenderingService` instantiates just its chain (renderer, dompdf engine, registry, config) — no DB needed. Run:

```bash
php -r "define('ROOT_PATH', getcwd()); define('APP_PATH', getcwd().'/app'); require 'app/Kernel/Autoloader.php'; \$c = new \App\Kernel\Container\Container(); (require 'config/container.php')(\$c); var_dump(\$c->get(\App\Application\Pdf\PdfRenderingService::class) instanceof \App\Application\Pdf\PdfRenderingService);"
```

Expected: `bool(true)`.

- [ ] **Step 4: Run the full suite to confirm nothing regressed**

Run: `php tests/run.php`
Expected: all tests pass, including the new `Pdf/*` tests, and the existing suite is unchanged (`0 failed`).

- [ ] **Step 5: Commit**

```bash
git add config/container.php
git commit -m "feat(pdf): wire pdf-kit services into the DI container

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 13: Documentation

Document the module so other verticals (and the upcoming Reportes plan) know how to consume it. Mirror the calendario doc location.

**Files:**
- Create: `docs/modules/modulo-pdf-kit.md`

- [ ] **Step 1: Write the module doc**

Create `docs/modules/modulo-pdf-kit.md`:

```markdown
# Módulo Kit de PDF (`pdf-kit`)

Capa opcional y desacoplada para emitir PDFs. No conoce el CRUD Engine ni Reportes:
cualquier módulo resuelve `PdfRenderingService` del contenedor y emite un documento.

## Modelo

- Un **documento** (`PdfDocument`) es una `PdfPageSetup` + una lista ordenada de
  **bloques** atómicos (`PdfHeader`, `PdfDataTable`, `PdfIndicatorCard`, `PdfTotalsBlock`,
  `PdfSignatureBlock`, `PdfText`, `PdfLogo`, `PdfFooter`, `PdfSpacer`, `PdfPageBreak`).
- Una **plantilla** (`PdfTemplateInterface`) compone un `PdfDocument` a partir de un
  payload de datos. La estructura/diseño es 100% del programador; el kit nunca recibe
  HTML de usuario.
- El **renderer** (`PdfComponentRenderer`) convierte bloques a HTML escapado vía
  partials en `app/Presentation/Views/partials/pdf/components/`.
- El **motor** (`DompdfRenderer`) está endurecido: `isRemoteEnabled=false`,
  `isPhpEnabled=false`, `chroot` al repo, fuentes locales.

## Uso desde otro módulo

```php
$pdf = $pdfRenderingService->renderTemplate('demo_reporte', [
    'titulo'  => 'Pedidos del mes',
    'columns' => [['name' => 'cliente', 'label' => 'Cliente'],
                  ['name' => 'total', 'label' => 'Total', 'format' => 'money']],
    'rows'    => [['cliente' => 'Ana', 'total' => 1200.5]],
]);
return $response->download($pdf, 'reporte.pdf');
```

## Registrar una plantilla

1. Implementa `PdfTemplateInterface` (`compose()` + `supports()`).
2. Añade `clave => Clase::class` en `config/pdf_templates.php` (whitelist; nunca un
   FQCN desde datos de usuario).

## Configuración

- `config/pdf.php` — papel, orientación, márgenes, fuente por defecto.
- `config/modules/pdf-kit.php` — manifiesto (`requiere: [core]`, sin tablas/rutas).
- Toggle: `modules.pdf_kit` en `config/vertical.php`.

## Pruebas

`php tests/run.php Pdf` — VOs, renderer (escape + formato), registry (whitelist),
motor dompdf (`%PDF`), servicio (esqueleto + plantilla demo end-to-end).
```

- [ ] **Step 2: Commit**

```bash
git add docs/modules/modulo-pdf-kit.md
git commit -m "docs(pdf): document the pdf-kit module

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Final verification

- [ ] **Run the complete test suite:** `php tests/run.php`
  Expected: `0 failed`, including all `Pdf/*` tests.
- [ ] **Lint every new PHP file:** `for f in $(git diff --name-only HEAD~12 -- '*.php'); do php -l "$f" || break; done`
  Expected: `No syntax errors detected` for each.
- [ ] **Confirm the deliverable:** a registered template (`demo_reporte`) renders to bytes starting with `%PDF` (asserted by `Task 11` integration test) — the Fase 0 acceptance criterion from the spec.

---

## Spec coverage map (Fase 0 only)

| Spec item (§) | Task |
|---|---|
| `pdf-kit` manifest, `requiere: [core]`, no bootstrap_sql (§3.5, §2) | Task 1 |
| Toggle `modules.pdf_kit` (§2) | Task 1 |
| `PdfPageSetup` (§3.1) | Task 2 |
| Atomic component VOs ×10 (§3.1) | Task 3 |
| `PdfDocument` builder (§3.1) | Task 4 |
| `PdfTemplateInterface`, `PdfEngineInterface` (§3.1) | Task 5 |
| Component partials = reusable atomic library (§3.4) | Task 6 |
| `PdfComponentRenderer` + escaping + formats (§3.2, §3.6) | Task 7 |
| `PdfTemplateRegistry` whitelist, no FQCN from user (§3.2, §10) | Task 8 |
| Hardened `DompdfRenderer` + `PdfStorage` (§3.3, §3.6) | Task 9 |
| `pdf/document.php` skeleton + `PdfRenderingService` (§3.2, §3.4) | Task 10 |
| `config/pdf.php`, `config/pdf_templates.php`, demo template (§3.5) | Task 11 |
| DI wiring so other modules consume the kit (§3.7) | Task 12 |
| Tests: renderer HTML, dompdf `%PDF` (§7 "Kit") | Tasks 2–11 |
| Module doc | Task 13 |

**Out of scope here (covered by the follow-up Reportes plan, Fases 1–3):** `config/reportes/*.json` loader/validator, `rep_reportes` table + repository, builder UI, `BuildReporteDataUseCase` / `GenerarReporteUseCase`, `ReportesController`, routes, RBAC `reportes.*`, the `registro` mode CRUD action, brand resolution from `cfg_configuraciones`, and the module bootstrap SQL/menu.
