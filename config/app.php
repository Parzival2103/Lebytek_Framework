<?php

use App\Kernel\EnvLoader;

return [
    'name'     => EnvLoader::get('APP_NAME', 'Sistema Administrativo'),
    'version'  => '1.0.0',
    'env'      => EnvLoader::get('APP_ENV',  'production'),
    'debug'    => EnvLoader::get('APP_DEBUG', false),
    'url'      => EnvLoader::get('APP_URL',  'http://localhost'),
    'key'      => EnvLoader::get('APP_KEY',  ''),
    'timezone' => EnvLoader::get('APP_TIMEZONE', 'America/Mexico_City'),
    'locale'   => EnvLoader::get('APP_LOCALE',   'es'),
];
