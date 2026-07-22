# FPS Plan 04 — Skeleton consumidor mínimo standalone

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar `skeleton/` como plantilla **standalone** para nuevos tenants: CRUD Engine disponible, Payments **OFF**, sin Marketing/`mkt_*`/`assets/publico`/LebytekApi; wrappers y bootstrap correctos; assets de plataforma canónicos; smoke desde copia temporal fuera del monorepo.

**Architecture:** El skeleton es un proyecto Composer (`type: project`) que `require` `lebytek/framework` vía path repo al monorepo padre. Scripts del consumidor son thin wrappers que definen `ROOT_PATH` y delegan en `vendor/lebytek/framework/scripts/*.php` (Tasks Plan 03). Tests del skeleton cargan `ROOT_PATH/vendor/autoload.php` (cierra **D4**). La suite Framework incluye `SkeletonPurityTest` que impide regresión de código Portal. Cierra deuda **D3**, **D7**, **D9**, **D10**.

**Tech Stack:** PHP 8.1+, Composer path repository, microtest (`php tests/run.php SkeletonPurity`).

**Reemplaza para ejecución (parcial):** Task 5 de `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`.

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 04.

**Predecesor obligatorio:** Plan 03 (`2026-07-17-fps-03-package-paths-installer-sql.md`) — `PackagePaths` + scripts/Installer verdes.

**Sucesor:** Plan 05 (`Lebytek_Portal` local).

## Global Constraints

- Rama de trabajo: **`consolidation/framework-portal-separation`**.
- El skeleton **incluye:** CRUD Engine (demo), Integrations genérico (toggle vertical), harness app vacío, assets plataforma canónicos.
- El skeleton **excluye:** Marketing, `mkt_*`, `routes/marketing.php`, `assets/publico/`, landing/leads/membresías, `LebytekApiClient`, `LEBYTEK_API_*` en `.env.example`.
- **`vertical.modules.marketing`** = **`false`**; **`vertical.modules.payments`** = **`false`** (Payments apagado).
- **Prohibido:** merge feature→main, deploy, push remoto, editar `vendor/` manualmente, secretos en commits.
- Gate principal: `php tests/run.php SkeletonPurity` → **`0 failed`**.
- Gate secundario: smoke `composer install` en copia temporal standalone (Task 4).

**Lista canónica de assets plataforma que el skeleton DEBE conservar (D7):**

```text
public/assets/css/app.css
public/assets/css/lebytek-ui.css
public/assets/css/crud-engine.css
public/assets/js/app.js
public/assets/js/crud-engine.js
public/assets/js/calendar.js
public/assets/js/avatar-manager.js
public/assets/js/reportes-builder.js
public/assets/icons/app-icon.svg
public/assets/images/logo.png
```

---

### Task 1: Eliminar código, config y rutas Marketing/Portal (TDD)

**Files:**
- Delete: `skeleton/app/Domain/Marketing/`, `Application/Marketing/`, `Infrastructure/Marketing/`, `Presentation/Controllers/Publico/`, `Presentation/Views/publico/`
- Delete: `skeleton/tests/Marketing/`, `skeleton/routes/marketing.php`, `skeleton/config/modules/marketing.php`, `skeleton/config/cruds/mkt_*.json`
- Modify: `skeleton/config/container.php` (quitar bloque Marketing completo)
- Modify: `skeleton/routes/web.php` (quitar `require marketing.php` / bloque condicional marketing)
- Modify: `skeleton/config/vertical.php` (`marketing` => false, `payments` => false)
- Create: `tests/Kernel/SkeletonPurityTest.php` (versión inicial — tests de código/config)

**Interfaces:**
- Consumes: Plan 03 (`PackagePaths` en paquete).
- Produces: skeleton sin clases `App\*\Marketing`, sin manifiesto marketing, sin rutas públicas Portal.

- [ ] **Step 1: Write the failing purity tests (code + config)**

Create `tests/Kernel/SkeletonPurityTest.php`:

```php
<?php

declare(strict_types=1);

$skeleton = dirname(__DIR__, 2) . '/skeleton';

test('skeleton does not ship App Domain Marketing', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/app/Domain/Marketing'), 'no Domain/Marketing');
    assert_true(!is_dir($skeleton . '/app/Application/Marketing'), 'no Application/Marketing');
    assert_true(!is_dir($skeleton . '/app/Infrastructure/Marketing'), 'no Infrastructure/Marketing');
    assert_true(!is_dir($skeleton . '/app/Presentation/Controllers/Publico'), 'no Controllers/Publico');
    assert_true(!is_dir($skeleton . '/app/Presentation/Views/publico'), 'no Views/publico');
});

test('skeleton does not ship marketing schema or mkt configs', function () use ($skeleton): void {
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing.sql'));
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing_demo.sql'));
    assert_true(!is_file($skeleton . '/config/modules/marketing.php'));
    assert_true(!is_file($skeleton . '/routes/marketing.php'));
    foreach (glob($skeleton . '/config/cruds/mkt_*.json') ?: [] as $_) {
        assert_true(false, 'skeleton must not contain config/cruds/mkt_*.json');
    }
});

test('skeleton does not ship Marketing tests', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/tests/Marketing'));
});

test('skeleton container.php does not reference App Marketing classes', function () use ($skeleton): void {
    $src = (string) file_get_contents($skeleton . '/config/container.php');
    assert_true(
        !str_contains($src, 'App\\Infrastructure\\Marketing')
        && !str_contains($src, 'App\\Domain\\Marketing')
        && !str_contains($src, 'App\\Application\\Marketing'),
        'container.php must not hard-bind Marketing classes'
    );
});

test('skeleton vertical keeps marketing and payments OFF', function () use ($skeleton): void {
    $vertical = require $skeleton . '/config/vertical.php';
    assert_same(false, $vertical['modules']['marketing'] ?? null);
    assert_same(false, $vertical['modules']['payments'] ?? null);
});

test('skeleton does not ship LebytekApi client or env vars', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/app/Infrastructure/Integrations/LebytekApi'));
    $envExample = (string) file_get_contents($skeleton . '/.env.example');
    assert_true(!str_contains($envExample, 'LEBYTEK_API_'), '.env.example must not ship LEBYTEK_API_*');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php SkeletonPurity
```

Expected: FAIL — Marketing dirs, `mkt_*.json`, `container.php` bindings presentes.

- [ ] **Step 3: Remove Marketing / Portal code and config**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
Remove-Item -Recurse -Force skeleton/app/Domain/Marketing -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Application/Marketing -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Infrastructure/Marketing -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Presentation/Controllers/Publico -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Presentation/Views/publico -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/tests/Marketing -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/routes/marketing.php -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/config/modules/marketing.php -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/config/cruds/mkt_*.json -ErrorAction SilentlyContinue
New-Item -ItemType File -Force -Path skeleton/app/Domain/.gitkeep | Out-Null
New-Item -ItemType File -Force -Path skeleton/app/Application/.gitkeep | Out-Null
New-Item -ItemType File -Force -Path skeleton/app/Infrastructure/.gitkeep | Out-Null
New-Item -ItemType File -Force -Path skeleton/app/Presentation/.gitkeep | Out-Null
```

In `skeleton/config/container.php`:

1. **Eliminar** el bloque completo `// ── Módulo Marketing` (desde el comentario hasta el `}` de cierre del `if ((bool) Config::get('vertical.modules.marketing', false))`), incluyendo todos los `$container->singleton` / `$container->bind` de Marketing.

2. En el singleton de `SettingsSectionRegistry`, **eliminar** el `if ((bool) Config::get('vertical.modules.marketing', false)) { ... }` que instancia `MarketingCorreoSettingsProvider`, `MarketingPaquetesSettingsProvider`, `MarketingTrackingSettingsProvider` y `MarketingContenidoSettingsProvider`. Dejar `$providers = []` inicial y solo añadir `IntegrationsWhatsappSettingsProvider` cuando `vertical.modules.integrations` sea true.

Resultado esperado del registry (fragmento):

```php
    $container->singleton(\Lebytek\Framework\Application\Services\SettingsSectionRegistry::class, function () {
        $providers = [];
        if ((bool) Config::get('vertical.modules.integrations', false)) {
            $providers[] = new \Lebytek\Framework\Infrastructure\Integrations\Settings\IntegrationsWhatsappSettingsProvider();
        }
        return new \Lebytek\Framework\Application\Services\SettingsSectionRegistry($providers);
    });
```

No debe quedar ningún `use` ni referencia a `\App\Infrastructure\Marketing\` en el skeleton.

In `skeleton/routes/web.php`: remove:

```php
$marketingActivo = (bool) \Lebytek\Framework\Kernel\Config\Config::get('vertical.modules.marketing', false);
if ($marketingActivo) {
    require ROOT_PATH . '/routes/marketing.php';
}
```

In `skeleton/config/vertical.php`: ensure:

```php
        'marketing'      => false,
        'payments'       => false,
```

- [ ] **Step 4: Run subset of purity tests**

Run:

```powershell
php tests/run.php SkeletonPurity
```

Expected: tests de Task 1 pasan; tests de SQL/assets/bootstrap (Task 2–4) aún fallan si ya están en el archivo — si no existen aún, `0 failed`.

- [ ] **Step 5: Commit**

```powershell
git add skeleton tests/Kernel/SkeletonPurityTest.php
git commit -m "chore(skeleton): strip Marketing code config and routes"
```

---

### Task 2: Limpiar SQL, seeds y migraciones duplicadas (cierra D9)

**Files:**
- Delete: `skeleton/database/schema/modules/marketing.sql`, `marketing_demo.sql` (si quedaron)
- Delete: `skeleton/database/seeds/*.sql` (copias plataforma)
- Delete: `skeleton/public/assets/publico/` (landing assets)
- Modify: `skeleton/database/seeds/README.md`
- Extend: `tests/Kernel/SkeletonPurityTest.php` (tests SQL/seeds/publico)

**Interfaces:**
- Consumes: Task 1 skeleton limpio de Marketing.
- Produces: skeleton sin SoT duplicado de SQL plataforma; seeds de tenant solo si aplica en futuro.

- [ ] **Step 1: Append failing tests for SQL/seeds duplication**

Append to `tests/Kernel/SkeletonPurityTest.php`:

```php
test('skeleton does not ship marketing SQL modules', function () use ($skeleton): void {
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing.sql'));
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing_demo.sql'));
});

test('skeleton does not ship publico landing assets', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/public/assets/publico'));
});

test('skeleton does not duplicate platform seeds as SoT', function () use ($skeleton): void {
    $seedFiles = glob($skeleton . '/database/seeds/*.sql') ?: [];
    assert_true(
        $seedFiles === [],
        'skeleton database/seeds must not ship platform *.sql copies (use package seeds)'
    );
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```powershell
php tests/run.php SkeletonPurity
```

Expected: FAIL on `publico/`, `marketing.sql`, or `database/seeds/*.sql` if still present.

- [ ] **Step 3: Remove duplicated SQL and publico assets**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
Remove-Item -Force skeleton/database/schema/modules/marketing.sql -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/database/schema/modules/marketing_demo.sql -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/public/assets/publico -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/database/seeds/*.sql -ErrorAction SilentlyContinue
```

Replace `skeleton/database/seeds/README.md`:

```markdown
# Seeds

Los seeds de plataforma viven en el paquete `lebytek/framework` (`PackagePaths::seedsDir()`).
No copies aquí `010_*.sql` … `035_*.sql` — usar `php scripts/seed.php` / install del paquete.
Seeds de dominio del tenant (si aplica) sí van en este directorio.
```

- [ ] **Step 4: Run tests — SQL subset must PASS**

Run:

```powershell
php tests/run.php SkeletonPurity
```

Expected: tests Task 1 + Task 2 pasan.

- [ ] **Step 5: Commit**

```powershell
git add skeleton/database/seeds/README.md tests/Kernel/SkeletonPurityTest.php
git commit -m "chore(skeleton): remove duplicated platform SQL and publico assets"
```

---

### Task 3: Assets plataforma canónicos (cierra D7)

**Files:**
- Create: `docs/superpowers/FPS-skeleton-platform-assets.md`
- Verify: los 10 assets listados en Global Constraints existen bajo `skeleton/public/assets/`
- Extend: `tests/Kernel/SkeletonPurityTest.php` (test assets requeridos)

**Interfaces:**
- Consumes: lista canónica D7 / A6 del plan histórico.
- Produces: documento de checklist assets; test que falla si falta algún archivo canónico.

- [ ] **Step 1: Append failing assets test**

Append to `tests/Kernel/SkeletonPurityTest.php`:

```php
test('skeleton ships required platform UI assets', function () use ($skeleton): void {
    $required = [
        'public/assets/css/app.css',
        'public/assets/css/lebytek-ui.css',
        'public/assets/css/crud-engine.css',
        'public/assets/js/app.js',
        'public/assets/js/crud-engine.js',
        'public/assets/js/calendar.js',
        'public/assets/js/avatar-manager.js',
        'public/assets/js/reportes-builder.js',
        'public/assets/icons/app-icon.svg',
        'public/assets/images/logo.png',
    ];
    foreach ($required as $rel) {
        assert_true(is_readable($skeleton . '/' . $rel), "missing platform asset: {$rel}");
    }
});
```

- [ ] **Step 2: Run test to verify pass or fail**

Run:

```powershell
php tests/run.php SkeletonPurity
```

Expected: PASS if assets already present (skeleton currently ships them); if any missing, copy from Framework harness `public/assets/` before Step 4.

- [ ] **Step 3: Write canonical assets checklist doc**

Create `docs/superpowers/FPS-skeleton-platform-assets.md`:

```markdown
# Assets plataforma — checklist skeleton

**Plan:** FPS 04 — Skeleton mínimo  
**Política:** ViewHelper::asset no lee vendor; el consumidor copia esta lista al desplegar.

## Archivos obligatorios

| Path | Rol |
|------|-----|
| `public/assets/css/app.css` | Shell admin |
| `public/assets/css/lebytek-ui.css` | Design system |
| `public/assets/css/crud-engine.css` | CRUD Engine UI |
| `public/assets/js/app.js` | Shell JS |
| `public/assets/js/crud-engine.js` | CRUD Engine JS |
| `public/assets/js/calendar.js` | Módulo calendario |
| `public/assets/js/avatar-manager.js` | Perfil/admin |
| `public/assets/js/reportes-builder.js` | Reportes |
| `public/assets/icons/app-icon.svg` | PWA icon |
| `public/assets/images/logo.png` | Branding default |

## Excluidos del skeleton

- `public/assets/publico/**` — landing Portal (Plan 05)

## Verificación

`php tests/run.php SkeletonPurity` — test `skeleton ships required platform UI assets`.
```

- [ ] **Step 4: Restore any missing asset from harness**

If Step 2 failed, run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
$assets = @(
  'public/assets/css/app.css','public/assets/css/lebytek-ui.css','public/assets/css/crud-engine.css',
  'public/assets/js/app.js','public/assets/js/crud-engine.js','public/assets/js/calendar.js',
  'public/assets/js/avatar-manager.js','public/assets/js/reportes-builder.js',
  'public/assets/icons/app-icon.svg','public/assets/images/logo.png'
)
foreach ($a in $assets) {
  $dst = "skeleton/$a"
  if (-not (Test-Path $dst)) {
    New-Item -ItemType Directory -Force -Path (Split-Path $dst) | Out-Null
    Copy-Item $a $dst -Force
  }
}
php tests/run.php SkeletonPurity
```

Expected: assets test PASS.

- [ ] **Step 5: Commit**

```powershell
git add docs/superpowers/FPS-skeleton-platform-assets.md tests/Kernel/SkeletonPurityTest.php skeleton/public/assets
git commit -m "docs(skeleton): canonical platform assets checklist and test"
```

---

### Task 4: Bootstrap, wrappers Composer y smoke standalone (cierra D4)

**Files:**
- Modify: `skeleton/tests/lib/bootstrap.php`
- Modify: `skeleton/composer.json`
- Create: `skeleton/scripts/migrate.php`, `skeleton/scripts/seed.php`, `skeleton/scripts/install.php`
- Extend: `tests/Kernel/SkeletonPurityTest.php` (bootstrap + wrappers)
- Modify: `.superpowers/sdd/progress.md`

**Interfaces:**
- Consumes: Plan 03 scripts con preamble `if (!defined('ROOT_PATH'))`.
- Produces: skeleton consumible vía `composer install` + autoload desde `ROOT_PATH/vendor/autoload.php`; smoke en copia temporal fuera del monorepo.

- [ ] **Step 1: Append bootstrap and wrapper tests**

Append to `tests/Kernel/SkeletonPurityTest.php`:

```php
test('skeleton test bootstrap loads consumer vendor autoload', function () use ($skeleton): void {
    $boot = $skeleton . '/tests/lib/bootstrap.php';
    assert_true(is_readable($boot));
    $src = (string) file_get_contents($boot);
    assert_true(
        str_contains($src, "ROOT_PATH . '/vendor/autoload.php'")
        || str_contains($src, 'ROOT_PATH . "/vendor/autoload.php"'),
        'bootstrap must load ROOT_PATH/vendor/autoload.php'
    );
    assert_true(
        !str_contains($src, 'dirname(__DIR__, 3)'),
        'bootstrap must not assume monorepo parent vendor'
    );
});

test('skeleton ships thin script wrappers delegating to package', function () use ($skeleton): void {
    foreach (['migrate.php', 'seed.php', 'install.php'] as $script) {
        $path = $skeleton . '/scripts/' . $script;
        assert_true(is_readable($path), "missing skeleton/scripts/{$script}");
        $src = (string) file_get_contents($path);
        assert_true(str_contains($src, "vendor/lebytek/framework/scripts/{$script}"), "wrapper must delegate to package {$script}");
        assert_true(str_contains($src, "define('ROOT_PATH'"), 'wrapper must define ROOT_PATH before require');
    }
});

test('skeleton composer.json requires lebytek/framework via path repo', function () use ($skeleton): void {
    $data = json_decode((string) file_get_contents($skeleton . '/composer.json'), true);
    assert_same('lebytek/skeleton', $data['name'] ?? null);
    assert_true(isset($data['require']['lebytek/framework']));
    $repos = $data['repositories'] ?? [];
    assert_true(count($repos) >= 1);
    assert_same('path', $repos[0]['type'] ?? null);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```powershell
php tests/run.php SkeletonPurity
```

Expected: FAIL — bootstrap usa `dirname(__DIR__, 3)`, faltan wrappers, `composer.json` apunta a VCS remoto.

- [ ] **Step 3: Fix skeleton test bootstrap (D4)**

Replace `skeleton/tests/lib/bootstrap.php`:

```php
<?php

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

require_once ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

$envFile = ROOT_PATH . '/.env';
if (is_readable($envFile)) {
    EnvLoader::load($envFile);
} elseif (is_readable(ROOT_PATH . '/.env.example')) {
    EnvLoader::load(ROOT_PATH . '/.env.example');
}

Config::init(ROOT_PATH . '/config');
$dbConfig = Config::get('database', []);
if (is_array($dbConfig) && $dbConfig !== []) {
    Connection::configure($dbConfig);
}
```

- [ ] **Step 4: Update skeleton composer.json and thin wrappers**

Replace `skeleton/composer.json`:

```json
{
    "name": "lebytek/skeleton",
    "description": "Esqueleto minimo de aplicacion Lebytek (consume lebytek/framework)",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=8.1",
        "lebytek/framework": "*@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "..",
            "options": { "symlink": true }
        }
    ],
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Create `skeleton/scripts/migrate.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/vendor/lebytek/framework/scripts/migrate.php';
```

Create `skeleton/scripts/seed.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/vendor/lebytek/framework/scripts/seed.php';
```

Create `skeleton/scripts/install.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/vendor/lebytek/framework/scripts/install.php';
```

- [ ] **Step 5: Smoke standalone — copia temporal fuera del monorepo**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
$fw = (Get-Location).Path
$tmp = Join-Path $env:TEMP ("lebytek-skeleton-standalone-" + [guid]::NewGuid().ToString('N').Substring(0,8))
Copy-Item -Recurse "$fw\skeleton" $tmp
# Ajustar path repo al framework real (copia está fuera del monorepo)
$composer = Get-Content "$tmp\composer.json" -Raw | ConvertFrom-Json
$composer.repositories[0].url = ($fw -replace '\\','/')
$composer | ConvertTo-Json -Depth 6 | Set-Content "$tmp\composer.json" -Encoding utf8
Set-Location $tmp
composer install --no-interaction 2>&1 | Select-Object -Last 3
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Kernel\\Bootstrap') && class_exists('Lebytek\\Framework\\Kernel\\PackagePaths') ? 'OK' : 'FAIL';"
Set-Location $fw
php tests/run.php SkeletonPurity
Remove-Item -Recurse -Force $tmp
```

Expected: `composer install` exit 0; salida `OK`; `SkeletonPurity` → `0 failed`.

- [ ] **Step 6: Record progress and commit**

Append to `.superpowers/sdd/progress.md`:

```markdown
## Plan 04 — Minimal skeleton (2026-07-17)

- [x] Marketing/mkt_/publico/LebytekApi removed
- [x] Platform SQL duplication removed
- [x] Canonical assets checklist + test
- [x] Bootstrap + wrappers + standalone smoke
- Gate SkeletonPurity: 0 failed
- Siguiente: Plan 05 Lebytek_Portal
```

```powershell
git add skeleton tests/Kernel/SkeletonPurityTest.php .superpowers/sdd/progress.md
git commit -m "chore(skeleton): standalone consumer bootstrap wrappers and purity gate"
```

---

## Self-review (author)

| Requisito roadmap / D3/D4/D7/D9/D10 | Task |
|-------------------------------------|------|
| Sin Marketing / mkt_* / Publico | Task 1 |
| Sin LebytekApi / LEBYTEK_API_* | Task 1 |
| Payments OFF | Task 1 vertical test |
| CRUD Engine + assets canónicos | Task 3 |
| Sin SQL/seeds plataforma duplicados | Task 2 |
| Bootstrap `ROOT_PATH/vendor/autoload.php` | Task 4 |
| Thin wrappers → package scripts | Task 4 |
| Smoke copia temporal standalone | Task 4 Step 5 |
| Gate `SkeletonPurity` 0 failed | Task 4 Step 5 |
| No merge/deploy/vendor/secrets | Global Constraints |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: `$skeleton = dirname(__DIR__, 2) . '/skeleton'` en todos los tests; wrappers delegan a `vendor/lebytek/framework/scripts/{name}.php`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-04-minimal-consumer-skeleton.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
