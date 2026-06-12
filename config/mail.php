<?php

use App\Kernel\EnvLoader;

return [
    'driver'       => EnvLoader::get('MAIL_DRIVER', 'log'),
    'host'         => EnvLoader::get('MAIL_HOST', ''),
    'port'         => (int) EnvLoader::get('MAIL_PORT', 587),
    'username'     => (string) EnvLoader::get('MAIL_USERNAME', ''),
    'password'     => (string) EnvLoader::get('MAIL_PASSWORD', ''),
    'from_address' => EnvLoader::get('MAIL_FROM_ADDRESS', 'noreply@localhost'),
    'from_name'    => EnvLoader::get('MAIL_FROM_NAME', 'Sistema Administrativo'),
];
