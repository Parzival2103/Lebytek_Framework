# FPS Plan 03 — PackagePaths, SQL e Installer

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolver SQL y scripts de plataforma **paquete-primero** cuando el Framework vive en `vendor/lebytek/framework`, manteniendo `ROOT_PATH` en el consumidor; validar instalación greenfield y decidir con evidencia el destino de seeds/migraciones legacy.

**Architecture:** `ROOT_PATH` apunta al proyecto consumidor (`.env`, `config/`, `app/`). `PackagePaths::root()` apunta a la raíz del paquete Composer (`lebytek/framework`). `PackagePaths::resolveDataFile()` busca primero en el paquete, luego en `ROOT_PATH` (negocio/overrides). Los scripts `migrate.php`, `seed.php` e `install.php` del paquete usan preamble dual autoload y **no redefinen** `ROOT_PATH` si el wrapper del consumidor ya la definió. `Installer` y `bootstrap_sql` de manifiestos resuelven vía `resolveDataFile`. Cierra deuda **D2** y **D8** del plan histórico.

**Tech Stack:** PHP 8.1+, PDO, microtest (`php tests/run.php`), Composer path repo local.

**Reemplaza para ejecución (parcial):** Tasks 3–4 de `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`.

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 03.

**Predecesor obligatorio:** Plan 02 (`2026-07-17-fps-02-platform-stabilization.md`) — inventario legacy sin borrar; gates `ConfiguracionServiceCache`, `Payments`, `Kernel` y `Auth` verdes; suite completa sin regresiones frente al baseline aunque D2/D8 continúen abiertos.

**Sucesor:** Plan 04 (`2026-07-17-fps-04-minimal-consumer-skeleton.md`).

## Global Constraints

- Rama de trabajo: **`consolidation/framework-portal-separation`** (checkout al iniciar).
- **Invariantes obligatorios:**
  - `ROOT_PATH` = raíz del **consumidor** (`.env`, `config/`, `app/`).
  - `PackagePaths::root()` = raíz del **paquete** (`composer.json` con `"name": "lebytek/framework"`).
  - SQL de plataforma (`schema.sql`, `database/schema/modules/*.sql` plataforma, migrations/seeds plataforma) se resuelve **paquete primero**.
  - SQL de negocio (`dom_*`, `*mkt*`) vive solo en el consumidor (Portal); el paquete no referencia `marketing_demo`.
- **Prohibido:** merge feature→main, deploy, push remoto, SSH, editar `vendor/` manualmente, secretos en commits.
- **Prohibido en este plan:** borrar `database/seeds_legacy/` o `database/migrations_legacy/` (solo decisión documentada en Task 4).
- Gates principales: `php tests/run.php PackagePaths` y `php tests/run.php PlatformSqlResolve` → **`0 failed`** cada uno.
- Comando tests: `php tests/run.php` — línea final `N passed, M failed` con **`M=0`** en subsets afectados.

---

### Task 1: `PackagePaths` — contrato de coexistencia (TDD)

**Files:**
- Create: `src/Kernel/PackagePaths.php`
- Create: `tests/Kernel/PackagePathsTest.php`
- Modify: none of `scripts/migrate.php`, `scripts/seed.php`, `scripts/install.php`, `Installer.php` (Task 2–3)

**Interfaces:**
- Consumes: layout del paquete (`src/Kernel/` bajo raíz con `composer.json` `"name": "lebytek/framework"`).
- Produces:
  - `Lebytek\Framework\Kernel\PackagePaths::root(): string`
  - `Lebytek\Framework\Kernel\PackagePaths::schema(string $relative = 'schema.sql'): string`
  - `Lebytek\Framework\Kernel\PackagePaths::seedsDir(): string`
  - `Lebytek\Framework\Kernel\PackagePaths::moduleSchema(string $moduleFile): string`
  - `Lebytek\Framework\Kernel\PackagePaths::resolveDataFile(string $relative): string` — paquete primero, luego `ROOT_PATH`; lanza `\RuntimeException` si falta en ambos

- [ ] **Step 1: Write the failing test**

Create `tests/Kernel/PackagePathsTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\PackagePaths;

test('PackagePaths root points at package root containing composer.json named lebytek/framework', function (): void {
    $root = PackagePaths::root();
    assert_true(is_dir($root), 'package root must exist');
    $composer = $root . '/composer.json';
    assert_true(is_readable($composer), 'composer.json must exist at package root');
    $data = json_decode((string) file_get_contents($composer), true);
    assert_same('lebytek/framework', $data['name'] ?? null);
});

test('PackagePaths schema resolves platform schema.sql inside the package', function (): void {
    $schema = PackagePaths::schema('schema.sql');
    assert_true(is_readable($schema), 'schema.sql must be readable via PackagePaths');
    assert_true(
        str_contains($schema, DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . 'schema.sql')
        || str_contains($schema, '/database/schema/schema.sql'),
        'path must include database/schema/schema.sql'
    );
});

test('PackagePaths moduleSchema resolves integrations module under package', function (): void {
    $path = PackagePaths::moduleSchema('integrations.sql');
    assert_true(is_readable($path), 'integrations.sql must exist in package modules');
});

test('PackagePaths seedsDir is a directory under the package', function (): void {
    $dir = PackagePaths::seedsDir();
    assert_true(is_dir($dir), 'seeds dir must exist');
});

test('PackagePaths resolveDataFile prefers package for platform module SQL', function (): void {
    $path = PackagePaths::resolveDataFile('database/schema/modules/integrations.sql');
    assert_true(is_readable($path), 'integrations.sql must resolve');
    assert_true(
        str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', PackagePaths::root())),
        'platform SQL must resolve inside the package root'
    );
});

test('PackagePaths resolveDataFile falls back to ROOT_PATH for consumer-only files', function (): void {
    $rel = 'database/schema/modules/__consumer_only_probe__.sql';
    $probe = ROOT_PATH . '/' . $rel;
    $dir = dirname($probe);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($probe, "-- probe\n");
    try {
        $resolved = PackagePaths::resolveDataFile($rel);
        assert_same(str_replace('\\', '/', $probe), str_replace('\\', '/', $resolved));
    } finally {
        @unlink($probe);
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php PackagePaths
```

Expected: FAIL — class `Lebytek\Framework\Kernel\PackagePaths` not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Kernel/PackagePaths.php`:

```php
<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel;

/**
 * Rutas que pertenecen al paquete Composer lebytek/framework.
 * Distinto de ROOT_PATH (raíz del proyecto consumidor).
 *
 * SQL/datos: resolveDataFile busca primero en el paquete, luego en ROOT_PATH
 * (negocio / overrides del consumidor).
 */
final class PackagePaths
{
    public static function root(): string
    {
        // este archivo: {package}/src/Kernel/PackagePaths.php
        return dirname(__DIR__, 2);
    }

    public static function schema(string $relative = 'schema.sql'): string
    {
        return self::root() . '/database/schema/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public static function moduleSchema(string $moduleFile): string
    {
        return self::schema('modules/' . ltrim(str_replace('\\', '/', $moduleFile), '/'));
    }

    public static function seedsDir(): string
    {
        return self::root() . '/database/seeds';
    }

    public static function resolveDataFile(string $relative): string
    {
        $rel = ltrim(str_replace('\\', '/', $relative), '/');
        $inPackage = self::root() . '/' . $rel;
        if (is_readable($inPackage)) {
            return $inPackage;
        }
        if (defined('ROOT_PATH')) {
            $inRoot = ROOT_PATH . '/' . $rel;
            if (is_readable($inRoot)) {
                return $inRoot;
            }
        }
        throw new \RuntimeException(
            "Archivo de datos no encontrado en paquete ni en ROOT_PATH: {$rel}"
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```powershell
php tests/run.php PackagePaths
```

Expected: `N passed, 0 failed`.

- [ ] **Step 5: Commit**

```powershell
git add src/Kernel/PackagePaths.php tests/Kernel/PackagePathsTest.php
git commit -m "feat(kernel): PackagePaths with package-first data resolve"
```

---

### Task 2: `migrate.php` y `seed.php` paquete-primero (cierra D2 parcial)

**Files:**
- Modify: `scripts/migrate.php`
- Modify: `scripts/seed.php`
- Create: `tests/Kernel/PlatformMigratePathsTest.php`
- Grep: `scripts/` — cualquier lectura `ROOT_PATH . '/database/schema/` para SQL plataforma debe migrarse en Task 3

**Interfaces:**
- Consumes: `PackagePaths` (Task 1).
- Produces: scripts plataforma que leen SQL desde el paquete; `.env` y `config/` siguen en `ROOT_PATH`; preamble `if (!defined('ROOT_PATH'))` + autoload dual (`ROOT_PATH/vendor` luego `$packageRoot/vendor`).

- [ ] **Step 1: Write the failing source tests**

Create `tests/Kernel/PlatformMigratePathsTest.php`:

```php
<?php

declare(strict_types=1);

test('platform migrate.php source uses PackagePaths not ROOT_PATH for schema.sql', function (): void {
    $migrate = dirname(__DIR__, 2) . '/scripts/migrate.php';
    assert_true(is_readable($migrate), 'scripts/migrate.php must exist');
    $src = (string) file_get_contents($migrate);
    assert_true(
        str_contains($src, 'PackagePaths::schema'),
        'migrate.php must call PackagePaths::schema'
    );
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'migrate.php must not read schema.sql from consumer ROOT_PATH'
    );
});

test('platform seed.php source uses PackagePaths for schema files', function (): void {
    $seed = dirname(__DIR__, 2) . '/scripts/seed.php';
    assert_true(is_readable($seed), 'scripts/seed.php must exist');
    $src = (string) file_get_contents($seed);
    assert_true(str_contains($src, 'PackagePaths'), 'seed.php must reference PackagePaths');
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'seed.php must not read schema.sql from consumer ROOT_PATH'
    );
    assert_true(
        !str_contains($src, 'marketing_demo'),
        'framework seed.php must not reference marketing_demo'
    );
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```powershell
php tests/run.php PlatformMigratePaths
```

Expected: FAIL — sources still use `ROOT_PATH . '/database/schema/schema.sql'`.

- [ ] **Step 3: Replace `scripts/migrate.php`**

Replace entire file with:

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| migrate.php — Schema SQL base del PAQUETE
|--------------------------------------------------------------------------
| Uso:
|   php scripts/migrate.php
|   php vendor/lebytek/framework/scripts/migrate.php
|
| ROOT_PATH = proyecto consumidor (.env, config)
| PackagePaths = SQL de plataforma
*/

$packageScriptsDir = __DIR__;
$packageRoot = dirname($packageScriptsDir);

if (!defined('ROOT_PATH')) {
    $candidateConsumer = dirname($packageRoot, 3);
    if (
        is_readable($candidateConsumer . '/composer.json')
        && is_dir($packageRoot . '/src')
        && str_contains((string) file_get_contents($candidateConsumer . '/composer.json'), '"type": "project"')
    ) {
        define('ROOT_PATH', $candidateConsumer);
    } else {
        define('ROOT_PATH', $packageRoot);
    }
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    $autoload = $packageRoot . '/vendor/autoload.php';
}
require_once $autoload;

use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\PackagePaths;

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
$sql = file_get_contents(PackagePaths::schema('schema.sql'));

echo "=== Ejecutando migraciones de plataforma ===\n\n";

try {
    $pdo->exec($sql);
    echo "✓ Schema de plataforma aplicado correctamente.\n";
} catch (\PDOException $e) {
    echo "✗ Error al ejecutar el schema: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migración completada ===\n";
```

- [ ] **Step 4: Replace `scripts/seed.php`**

Replace entire file with (mismo preamble `ROOT_PATH` + autoload dual que migrate):

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| seed.php — Bootstrap SQL del PAQUETE
|--------------------------------------------------------------------------
| Uso:
|   php scripts/seed.php
|   php scripts/seed.php --crud-engine
|   php vendor/lebytek/framework/scripts/seed.php
*/

$packageScriptsDir = __DIR__;
$packageRoot = dirname($packageScriptsDir);

if (!defined('ROOT_PATH')) {
    $candidateConsumer = dirname($packageRoot, 3);
    if (
        is_readable($candidateConsumer . '/composer.json')
        && is_dir($packageRoot . '/src')
        && str_contains((string) file_get_contents($candidateConsumer . '/composer.json'), '"type": "project"')
    ) {
        define('ROOT_PATH', $candidateConsumer);
    } else {
        define('ROOT_PATH', $packageRoot);
    }
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    $autoload = $packageRoot . '/vendor/autoload.php';
}
require_once $autoload;

use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\PackagePaths;

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

$runner = new SqlFileRunner();
$incluirCrudEngine = in_array('--crud-engine', $argv ?? [], true);

$archivos = [
    PackagePaths::schema('schema.sql'),
];

if ($incluirCrudEngine) {
    $archivos[] = PackagePaths::moduleSchema('crud-engine.sql');
}

echo '=== Bootstrap SQL — ' . count($archivos) . " archivo(s) ===\n\n";

foreach ($archivos as $path) {
    $name = str_replace(str_replace('\\', '/', PackagePaths::root()) . '/', '', str_replace('\\', '/', $path));
    echo "→ {$name}\n";
    $runner->ejecutar($path);
    echo "   ✓ OK\n";
}

echo "\n=== Bootstrap completado ===\n";
```

- [ ] **Step 5: Run tests to verify they pass**

Run:

```powershell
php tests/run.php PlatformMigratePaths
php tests/run.php PackagePaths
```

Expected: `0 failed` en ambos.

- [ ] **Step 6: Commit**

```powershell
git add scripts/migrate.php scripts/seed.php tests/Kernel/PlatformMigratePathsTest.php
git commit -m "fix(scripts): resolve platform migrate/seed SQL via PackagePaths"
```

---

### Task 3: `install.php`, `Installer` y `bootstrap_sql` (cierra D2, D8)

**Files:**
- Modify: `scripts/install.php`
- Modify: `src/Application/Install/Installer.php`
- Create: `tests/Kernel/PlatformSqlResolveTest.php`
- Extend: `tests/Kernel/PlatformMigratePathsTest.php` (test install.php source)

**Interfaces:**
- Consumes: `PackagePaths` (Task 1), preamble dual (Task 2).
- Produces:
  - `install.php` aplica `PackagePaths::schema('schema.sql')` y `PackagePaths::resolveDataFile($manifest->bootstrapSql)` para cada módulo.
  - `Installer::resolveMigrationFile(string $archivo): string` y `Installer::resolveSeedFile(string $archivo): string` vía `PackagePaths::resolveDataFile`.
  - Constructor `Installer` conserva `$migracionesDir` / `$seedsDir` por BC pero la resolución de rutas ya no concatena esos dirs como SoT.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Kernel/PlatformMigratePathsTest.php`:

```php
test('platform install.php source uses PackagePaths for schema.sql', function (): void {
    $install = dirname(__DIR__, 2) . '/scripts/install.php';
    assert_true(is_readable($install), 'scripts/install.php must exist');
    $src = (string) file_get_contents($install);
    assert_true(
        str_contains($src, 'PackagePaths'),
        'install.php must reference PackagePaths'
    );
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'install.php must not read schema.sql only from ROOT_PATH'
    );
});
```

Create `tests/Kernel/PlatformSqlResolveTest.php`:

```php
<?php

declare(strict_types=1);

test('Installer.php source resolves data files via PackagePaths::resolveDataFile', function (): void {
    $file = dirname(__DIR__, 2) . '/src/Application/Install/Installer.php';
    assert_true(is_readable($file), 'Installer.php must exist');
    $src = (string) file_get_contents($file);
    assert_true(
        str_contains($src, 'PackagePaths::resolveDataFile'),
        'Installer must call PackagePaths::resolveDataFile'
    );
});

test('Installer.php defines resolveMigrationFile and resolveSeedFile helpers', function (): void {
    $file = dirname(__DIR__, 2) . '/src/Application/Install/Installer.php';
    $src = (string) file_get_contents($file);
    assert_true(str_contains($src, 'resolveMigrationFile'), 'Installer must define resolveMigrationFile');
    assert_true(str_contains($src, 'resolveSeedFile'), 'Installer must define resolveSeedFile');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```powershell
php tests/run.php PlatformSqlResolve
php tests/run.php PlatformMigratePaths
```

Expected: FAIL — `install.php` / `Installer.php` aún usan `ROOT_PATH` concatenado.

- [ ] **Step 3: Update `scripts/install.php`**

At top: mismo preamble `ROOT_PATH` + autoload dual que Task 2 (reemplazar `define('ROOT_PATH', dirname(__DIR__))` fijo).

Replace schema base block (lines ~53–56) with:

```php
use Lebytek\Framework\Kernel\PackagePaths;

echo "=== Instalación Lebytek ===\n\n→ Schema base\n";
(new SqlFileRunner())->ejecutar(PackagePaths::schema('schema.sql'));
echo "   ✓ schema.sql\n\n";
```

Replace bootstrap loop (lines ~74–78) with:

```php
    $rutaBootstrap = PackagePaths::resolveDataFile($manifest->bootstrapSql);
    if (!is_readable($rutaBootstrap)) {
        fwrite(STDERR, "Bootstrap SQL no encontrado: {$manifest->bootstrapSql}\n");
        exit(1);
    }
```

- [ ] **Step 4: Update `Installer.php`**

Add import:

```php
use Lebytek\Framework\Kernel\PackagePaths;
```

Add private helpers inside `Installer`:

```php
    private function resolveMigrationFile(string $archivo): string
    {
        return PackagePaths::resolveDataFile('database/migrations/' . ltrim($archivo, '/'));
    }

    private function resolveSeedFile(string $archivo): string
    {
        return PackagePaths::resolveDataFile('database/seeds/' . ltrim($archivo, '/'));
    }
```

In `clasificar()`, replace:

```php
        $ruta     = rtrim($dir, '/\\') . '/' . $archivo;
```

with:

```php
        $ruta = str_contains($dir, 'seeds')
            ? $this->resolveSeedFile($archivo)
            : $this->resolveMigrationFile($archivo);
```

In `baselineArchivo()`, replace:

```php
        $ruta = rtrim($dir, '/\\') . '/' . $archivo;
```

with:

```php
        $ruta = str_contains($dir, 'seeds')
            ? $this->resolveSeedFile($archivo)
            : $this->resolveMigrationFile($archivo);
```

- [ ] **Step 5: Run contract tests — must PASS**

Run:

```powershell
php tests/run.php PlatformSqlResolve
php tests/run.php PlatformMigratePaths
php tests/run.php PackagePaths
php tests/run.php Install
```

Expected: `0 failed` en los cuatro.

- [ ] **Step 6: Commit**

```powershell
git add scripts/install.php src/Application/Install/Installer.php tests/Kernel/PlatformSqlResolveTest.php tests/Kernel/PlatformMigratePathsTest.php
git commit -m "fix(install): resolve platform SQL and bootstrap via PackagePaths"
```

---

### Task 4: Instalación greenfield y decisión legacy por evidencia

**Files:**
- Create: `tests/Install/InstallGreenfieldTest.php`
- Create: `docs/superpowers/FPS-legacy-archival-decision.md`
- Modify: `docs/superpowers/LEGACY-seeds-migrations-inventory.md` (sección decisión Plan 03)
- Modify: `.superpowers/sdd/progress.md`

**Interfaces:**
- Consumes: `PackagePaths`, scripts Tasks 2–3, inventario Plan 02 (`LEGACY-seeds-migrations-inventory.md`).
- Produces: evidencia greenfield (consumidor sin copia local de `schema.sql`); decisión documentada sobre `seeds_legacy/` y `migrations_legacy/`; gates `PackagePaths` + `PlatformSqlResolve` verificados.

- [ ] **Step 1: Write the failing greenfield tests**

Create `tests/Install/InstallGreenfieldTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\PackagePaths;

test('greenfield platform schema resolves from package not consumer copy requirement', function (): void {
    $schema = PackagePaths::schema('schema.sql');
    assert_true(is_readable($schema), 'package schema.sql must be readable');
    assert_true(
        str_starts_with(str_replace('\\', '/', $schema), str_replace('\\', '/', PackagePaths::root())),
        'schema must live under package root'
    );
    $sql = (string) file_get_contents($schema);
    assert_true(str_contains($sql, 'DATOS INICIALES'), 'consolidated bootstrap must include DATOS INICIALES');
    assert_true(str_contains($sql, 'admin@sistema.local'), 'consolidated bootstrap must seed admin user');
});

test('greenfield consumer can resolve crud-engine bootstrap_sql via PackagePaths', function (): void {
    $path = PackagePaths::resolveDataFile('database/schema/modules/crud-engine.sql');
    assert_true(is_readable($path), 'crud-engine bootstrap must resolve from package');
});

test('database/seeds active directory has no loose platform SQL files', function (): void {
    $activeSeeds = glob(PackagePaths::root() . '/database/seeds/*.sql') ?: [];
    assert_same([], $activeSeeds, 'platform seeds consolidated into schema.sql; no loose *.sql in database/seeds/');
});

test('legacy seeds directory is not referenced by Installer manifests', function (): void {
    $modulesDir = ROOT_PATH . '/config/modules';
    foreach (glob($modulesDir . '/*.php') ?: [] as $file) {
        $cfg = require $file;
        if (!is_array($cfg)) {
            continue;
        }
        foreach ($cfg['seeds'] ?? [] as $seedFile) {
            assert_true(
                !str_contains((string) $seedFile, 'seeds_legacy'),
                "manifest must not reference seeds_legacy: {$file}"
            );
        }
    }
});
```

- [ ] **Step 2: Run tests to verify they pass (or fail only if consolidation missing)**

Run:

```powershell
php tests/run.php InstallGreenfield
php tests/run.php PackagePaths
php tests/run.php PlatformSqlResolve
```

Expected: `0 failed` en los tres filtros.

- [ ] **Step 3: Smoke greenfield — consumidor temporal sin `database/schema/`**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
$fw = (Get-Location).Path
$tmp = Join-Path $env:TEMP ("lebytek-fps03-greenfield-" + [guid]::NewGuid().ToString('N').Substring(0,8))
New-Item -ItemType Directory -Force -Path $tmp | Out-Null
New-Item -ItemType Directory -Force -Path "$tmp\config" | Out-Null
New-Item -ItemType Directory -Force -Path "$tmp\storage\logs" | Out-Null
Copy-Item "$fw\.env.example" "$tmp\.env.example"
Copy-Item "$fw\.env.example" "$tmp\.env"
Copy-Item -Recurse "$fw\config\*" "$tmp\config\" -Exclude "modules/marketing.php"
@'
{
    "name": "lebytek/fps03-greenfield-probe",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "lebytek/framework": "*@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "' + ($fw -replace '\\','/') + '",
            "options": { "symlink": true }
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
'@ | Set-Content "$tmp\composer.json" -Encoding utf8
Set-Location $tmp
composer install --no-interaction 2>&1 | Select-Object -Last 3
php -r "require 'vendor/autoload.php'; define('ROOT_PATH', getcwd()); use Lebytek\Framework\Kernel\PackagePaths; echo is_readable(PackagePaths::schema('schema.sql')) ? 'SCHEMA_OK' : 'SCHEMA_FAIL';"
Set-Location $fw
Remove-Item -Recurse -Force $tmp
```

Expected: `composer install` exit 0; salida `SCHEMA_OK`. El consumidor **no** tiene `database/schema/` local; el schema se resuelve solo desde el paquete.

Opcional (si `.env` apunta a MySQL local vacío):

```powershell
# Repetir setup $tmp sin Remove-Item; luego:
Set-Location $tmp
php vendor/lebytek/framework/scripts/migrate.php 2>&1 | Select-Object -Last 5
```

Expected: `✓ Schema de plataforma aplicado correctamente.` Anotar resultado en `FPS-legacy-archival-decision.md`.

- [ ] **Step 4: Documentar decisión legacy con evidencia**

Create `docs/superpowers/FPS-legacy-archival-decision.md`:

```markdown
# Decisión legacy seeds/migrations — Plan 03

**Fecha:** 2026-07-17  
**Evidencia:** `InstallGreenfieldTest` + smoke consumidor temporal sin `database/schema/`.

## Hallazgos

1. Bootstrap greenfield activo: `database/schema/schema.sql` (sección `DATOS INICIALES`).
2. `database/seeds/` activo: sin `*.sql` sueltos (solo `README.md`).
3. Manifiestos `config/modules/*.php`: `seeds` = `[]`; bootstrap vía `bootstrap_sql` resuelto por `PackagePaths`.
4. `database/seeds_legacy/` y `database/migrations_legacy/`: **no** referenciados por Installer ni scripts activos.

## Decisión

| Path | Acción Plan 03 | Acción futura |
|------|----------------|---------------|
| `database/seeds_legacy/` | **Conservar** (referencia histórica) | Permanece hasta plan/aprobación explícita de archivo o eliminación (fuera del roadmap FPS 00–08) |
| `database/migrations_legacy/` | **Conservar** (referencia histórica) | Idem |
| `database/seeds/*.sql` sueltos | **No reintroducir** | N/A |
| `database/schema/schema.sql` | SoT plataforma en paquete | Consumidor no copia como SoT |

## Criterio cumplido

Greenfield no requiere seeds legacy numerados (`010`–`035`). **Ninguna** eliminación ni movimiento físico de legacy en Plan 03 — solo documentación. Archivo destructivo exige spec/plan futuro y sign-off humano.
```

Append to `docs/superpowers/LEGACY-seeds-migrations-inventory.md`:

```markdown
## Decisión aplicada (Plan 03 — 2026-07-17)

Ver `docs/superpowers/FPS-legacy-archival-decision.md`. Resumen: legacy **conservado**, no borrado; greenfield confirmado vía `PackagePaths` + smoke temporal.
```

- [ ] **Step 5: Gate final y commit**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php PackagePaths
php tests/run.php PlatformSqlResolve
php tests/run.php InstallGreenfield
```

Expected: `0 failed` en los tres.

Append to `.superpowers/sdd/progress.md`:

```markdown
## Plan 03 — PackagePaths + Installer (2026-07-17)

- [x] PackagePaths + tests
- [x] migrate.php / seed.php / install.php package-first
- [x] Installer + bootstrap_sql via resolveDataFile
- [x] Greenfield smoke + legacy decision doc
- Gate PackagePaths: 0 failed
- Gate PlatformSqlResolve: 0 failed
- Siguiente: Plan 04 minimal skeleton
```

```powershell
git add tests/Install/InstallGreenfieldTest.php docs/superpowers/FPS-legacy-archival-decision.md docs/superpowers/LEGACY-seeds-migrations-inventory.md .superpowers/sdd/progress.md
git commit -m "docs(fps): greenfield evidence and legacy retention decision"
```

---

## Self-review (author)

| Requisito roadmap / D2/D8 | Task |
|---------------------------|------|
| `PackagePaths` + tests | Task 1 |
| `migrate.php` / `seed.php` paquete-primero | Task 2 |
| `install.php` + `Installer` + `bootstrap_sql` | Task 3 |
| Greenfield + decisión legacy | Task 4 |
| `ROOT_PATH` consumidor / `PackagePaths::root()` paquete | Tasks 1–3 preamble |
| SQL plataforma paquete-primero; negocio consumidor | `resolveDataFile` fallback |
| Gate `PackagePaths` 0 failed | Tasks 1, 4 |
| Gate `PlatformSqlResolve` 0 failed | Tasks 3, 4 |
| No borrar legacy en Plan 03 | Task 4 decisión |
| No merge/deploy/vendor/secrets | Global Constraints |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: mismos nombres `PackagePaths::resolveDataFile`, `resolveMigrationFile`, `resolveSeedFile` en Tasks 3–4.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-03-package-paths-installer-sql.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
