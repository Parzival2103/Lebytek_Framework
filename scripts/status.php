<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| status.php — Imprime el estado del despliegue (DeploymentStatus)
|--------------------------------------------------------------------------
| Uso: php scripts/status.php
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;
use App\Kernel\Container\Container;
use App\Application\Install\DeploymentStatus;

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

$container = new Container();
(require ROOT_PATH . '/config/container.php')($container);
/** @var DeploymentStatus $status */
$status = $container->get(DeploymentStatus::class);
$r = $status->reporte();

echo "=== Estado del despliegue ===\n\n";
echo "Plataforma: v{$r['plataformaVersion']}\n\n";

echo "Módulos:\n";
foreach ($r['modulos'] as $clave => $m) {
    $inst = $m['instalada'] ?? '(no instalado)';
    $flag = $m['actualizacionDisponible'] ? '  ⬆ actualización disponible' : '';
    $act  = $m['activo'] ? 'activo' : 'inactivo';
    echo "  - {$clave}: declarada {$m['declarada']} / instalada {$inst} [{$act}]{$flag}\n";
}

echo "\nMigraciones pendientes: " . count($r['migracionesPendientes']) . "\n";
foreach ($r['migracionesPendientes'] as $p) { echo "  - [{$p['modulo']}] {$p['archivo']}\n"; }

echo "\nChecksums modificados: " . count($r['checksumsModificados']) . "\n";
foreach ($r['checksumsModificados'] as $c) { echo "  - [{$c['modulo']}] {$c['archivo']}\n"; }

echo "\nHealth checks:\n";
foreach ($r['healthChecks'] as $h) {
    echo '  [' . ($h['ok'] ? 'OK ' : 'XX ') . "] {$h['clave']}: {$h['detalle']}\n";
}
