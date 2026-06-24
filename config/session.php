<?php

use App\Kernel\EnvLoader;

return [
    'lifetime'          => EnvLoader::get('SESSION_LIFETIME', 120),
    'remember_lifetime' => (int) EnvLoader::get('SESSION_REMEMBER_LIFETIME', 43200),
    'secure'            => EnvLoader::get('SESSION_SECURE', false),
    'http_only'         => EnvLoader::get('SESSION_HTTP_ONLY', true),
];
