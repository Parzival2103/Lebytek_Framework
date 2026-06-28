<?php

use Lebytek\Framework\Kernel\EnvLoader;

return [
    'host'     => EnvLoader::get('DB_HOST',     '127.0.0.1'),
    'port'     => EnvLoader::get('DB_PORT',     3306),
    'database' => EnvLoader::get('DB_DATABASE', ''),
    'username' => EnvLoader::get('DB_USERNAME', 'root'),
    'password' => EnvLoader::get('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
];
