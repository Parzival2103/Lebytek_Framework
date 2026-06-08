<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;
use App\Kernel\Container\Container;
use App\Application\Install\Installer;
use App\Application\Install\ModuleRegistry;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$lockFile = STORAGE_PATH . '/install.lock';

/** Renderiza una vista del wizard dentro del layout. */
function wizard_render(string $vista, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $vista . '.php';
    $contenido = ob_get_clean();
    require __DIR__ . '/views/_layout.php';
    exit;
}

// 1) Ya instalado → solo lectura.
if (is_file($lockFile)) {
    wizard_render('ya_instalado', [
        'tituloPaso'  => 'Ya instalado',
        'lockResumen' => (string) @file_get_contents($lockFile),
    ]);
}

// 2) Token exigido en producción.
$esProd = (string) Config::get('app.env', 'production') === 'production';
if ($esProd) {
    $tokenEsperado = (string) EnvLoader::get('INSTALL_TOKEN', '');
    $tokenRecibido = (string) ($_GET['token'] ?? $_POST['token'] ?? $_SESSION['install_token'] ?? '');
    if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $tokenRecibido)) {
        http_response_code(403);
        echo 'Instalador protegido. Proporcione ?token=INSTALL_TOKEN (definido en .env).';
        exit;
    }
    $_SESSION['install_token'] = $tokenRecibido;
}

// 3) CSRF para POST.
if (!isset($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['install_csrf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        echo 'Token CSRF inválido. Recargue el asistente.';
        exit;
    }
}

// 4) Conexión BD (los pasos la usan; si falla, el paso BD lo reporta).
try {
    Connection::configure([
        'host'     => Config::get('database.host'),
        'port'     => Config::get('database.port'),
        'database' => Config::get('database.database'),
        'username' => Config::get('database.username'),
        'password' => Config::get('database.password'),
        'charset'  => 'utf8mb4',
    ]);
} catch (\Throwable) {
    // Silencioso aquí; el paso de BD comprobará y mostrará el detalle.
}

$container = new Container();
(require ROOT_PATH . '/config/container.php')($container);
/** @var Installer $installer */
$installer = $container->get(Installer::class);
/** @var ModuleRegistry $registry */
$registry  = $container->get(ModuleRegistry::class);

$paso = (string) ($_GET['paso'] ?? 'requisitos');

require __DIR__ . '/steps.php';
