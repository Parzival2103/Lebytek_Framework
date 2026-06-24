<?php

use App\Kernel\EnvLoader;

return [
    'registro' => [
        // Registro público apagado por defecto; se enciende por .env.
        'habilitado'  => (bool) EnvLoader::get('REGISTRO_HABILITADO', false),
        'rol_default' => 'usuario',
    ],
    'tokens' => [
        'recuperacion_ttl_min' => 60,
        'verificacion_ttl_min' => 1440,
        'max_por_hora'         => 3,
    ],
    'login' => [
        'habilitado'   => (bool) EnvLoader::get('LOGIN_RATE_LIMIT_ENABLED', true),
        'max_intentos' => (int) EnvLoader::get('LOGIN_MAX_INTENTOS', 5),
        'ventana_min'  => (int) EnvLoader::get('LOGIN_VENTANA_MIN', 15),
    ],
];
