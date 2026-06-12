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
];
