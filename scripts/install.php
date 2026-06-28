<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| install.php — Instalador con tracking real (motor Installer)
|--------------------------------------------------------------------------
| Uso:
|   php scripts/install.php                       (instala/actualiza core + opcionales activos)
|   php scripts/install.php --modules=core,crud-engine
|   php scripts/install.php --dry-run             (muestra el plan, no ejecuta)
|   php scripts/install.php --baseline            (adopta deploy legacy sin re-ejecutar)
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Application\Install\Installer;
use Lebytek\Framework\Application\Install\ModuleRegistry;
use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;

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

// Argumentos.
$args     = array_slice($argv, 1);
$dryRun   = in_array('--dry-run', $args, true);
$baseline = in_array('--baseline', $args, true);
$modules  = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--modules=')) {
        $modules = array_values(array_filter(array_map('trim', explode(',', substr($a, strlen('--modules='))))));
    }
}

// Schema base SIEMPRE primero (crea cfg_migraciones/cfg_modulos si faltan).
echo "=== Instalación Lebytek ===\n\n→ Schema base\n";
(new SqlFileRunner())->ejecutar(ROOT_PATH . '/database/schema/schema.sql');
echo "   ✓ schema.sql\n\n";

// Contenedor / motor.
$container = new Container();
(require ROOT_PATH . '/config/container.php')($container);
/** @var Installer $installer */
$installer  = $container->get(Installer::class);
/** @var ModuleRegistry $registry */
$registry   = $container->get(ModuleRegistry::class);

// Selección por defecto: todos los módulos declarados (core + opcionales).
$seleccion = $modules ?? array_keys($registry->all());

foreach ($seleccion as $claveModulo) {
    $manifest = $registry->get($claveModulo);
    if ($manifest?->bootstrapSql === null) {
        continue;
    }
    $rutaBootstrap = ROOT_PATH . '/' . $manifest->bootstrapSql;
    if (!is_file($rutaBootstrap)) {
        fwrite(STDERR, "Bootstrap SQL no encontrado: {$manifest->bootstrapSql}\n");
        exit(1);
    }
    echo "→ Bootstrap módulo [{$claveModulo}]\n";
    (new SqlFileRunner())->ejecutar($rutaBootstrap);
    echo "   ✓ {$manifest->bootstrapSql}\n\n";
}

if ($baseline) {
    echo "→ Baseline (adoptando deploy existente)\n";
    $installer->baseline();
    echo "   ✓ Migraciones presentes marcadas como aplicadas; módulos registrados.\n";
    echo "\n=== Listo ===\n";
    exit(0);
}

$plan = $installer->plan($seleccion);

echo "→ Plan (" . ($plan->nueva ? 'instalación nueva' : 'actualización') . ")\n";
echo "   Migraciones pendientes: " . count($plan->migracionesPendientes) . "\n";
foreach ($plan->migracionesPendientes as $m) { echo "     - [{$m['modulo']}] {$m['archivo']}\n"; }
echo "   Seeds pendientes: " . count($plan->seedsPendientes) . "\n";
foreach ($plan->seedsPendientes as $s) { echo "     - [{$s['modulo']}] {$s['archivo']}\n"; }
echo "   Módulos a registrar: " . implode(', ', array_map(fn($x) => $x['clave'] . '@' . $x['version'], $plan->modulos)) . "\n";
if ($plan->checksumsModificados !== []) {
    echo "   ⚠ Checksums modificados tras aplicar (NO se re-ejecutan):\n";
    foreach ($plan->checksumsModificados as $c) { echo "     - [{$c['modulo']}] {$c['archivo']}\n"; }
}

if ($dryRun) {
    echo "\n(dry-run: no se ejecutó nada)\n";
    exit(0);
}

echo "\n→ Aplicando…\n";
$installer->aplicar($plan);
echo "   ✓ Aplicado y registrado en cfg_migraciones / cfg_modulos.\n";
echo "\n=== Instalación completada ===\n";
