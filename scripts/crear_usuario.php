<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| crear_usuario.php — Crea un usuario administrador desde CLI
|--------------------------------------------------------------------------
| Uso: php scripts/crear_usuario.php "Nombre" "Apellido" "email@ejemplo.com" "Password123"
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once ROOT_PATH . '/vendor/autoload.php';

use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;
use App\Kernel\Security\Hash;

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

$argv  = $argv ?? [];
$nombre   = $argv[1] ?? 'Admin';
$apellido = $argv[2] ?? 'Usuario';
$email    = $argv[3] ?? 'admin@sistema.local';
$password = $argv[4] ?? 'Admin123!';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "✗ El correo no tiene un formato válido.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "✗ La contraseña debe tener al menos 8 caracteres.\n";
    exit(1);
}

$pdo  = Connection::getInstance();
$hash = Hash::make($password);

$exists = $pdo->prepare("SELECT id FROM auth_usuarios WHERE email = ? LIMIT 1");
$exists->execute([$email]);

if ($exists->fetchColumn()) {
    echo "⚠  El correo ya está registrado.\n";
    exit(0);
}

$stmt = $pdo->prepare(
    "INSERT INTO auth_usuarios (nombre, apellido, email, password, activo, created_at)
     VALUES (?, ?, ?, ?, 1, NOW())"
);
$stmt->execute([$nombre, $apellido, $email, $hash]);
$id = (int) $pdo->lastInsertId();

// Asignar rol administrador si existe
$rolId = $pdo->query("SELECT id FROM auth_roles WHERE slug = 'administrador' LIMIT 1")->fetchColumn();
if ($rolId) {
    $pdo->prepare("INSERT IGNORE INTO auth_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)")
        ->execute([$id, $rolId]);
}

echo "✓ Usuario creado correctamente.\n";
echo "  ID:       {$id}\n";
echo "  Nombre:   {$nombre} {$apellido}\n";
echo "  Email:    {$email}\n";
echo "  Rol:      administrador\n";
