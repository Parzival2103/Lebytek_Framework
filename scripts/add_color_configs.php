<?php
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');
require_once ROOT_PATH . '/vendor/autoload.php';
use Lebytek\Framework\Kernel\EnvLoader; use Lebytek\Framework\Kernel\Config\Config; use Lebytek\Framework\Kernel\Database\Connection;
EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');
Connection::configure(['host'=>Config::get('database.host'),'port'=>Config::get('database.port'),'database'=>Config::get('database.database'),'username'=>Config::get('database.username'),'password'=>Config::get('database.password'),'charset'=>'utf8mb4']);
$pdo = Connection::getInstance();

// INSERT ... ON DUPLICATE KEY UPDATE garantiza que el valor se establece
// aunque la clave ya existiera con string vacío u otro valor previo
$sql = "INSERT INTO cfg_configuraciones (clave, valor, tipo, descripcion)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            valor       = IF(valor = '' OR valor IS NULL, VALUES(valor), valor),
            tipo        = VALUES(tipo),
            descripcion = VALUES(descripcion)";

$stmt = $pdo->prepare($sql);

$configs = [
    ['navbar_color', '#1a1d2e', 'string', 'Color de fondo del navbar/sidebar'],
    ['body_color',   '#f0f2f5', 'string', 'Color de fondo del área de contenido'],
];

foreach ($configs as $cfg) {
    $stmt->execute($cfg);
    $row = $pdo->query("SELECT valor FROM cfg_configuraciones WHERE clave = '{$cfg[0]}'")->fetch();
    echo "  {$cfg[0]} = {$row['valor']}\n";
}

echo "\nOK: configuraciones de color verificadas/insertadas\n";
