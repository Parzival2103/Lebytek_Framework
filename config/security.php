<?php

use Lebytek\Framework\Kernel\EnvLoader;

return [
    'bcrypt_rounds'      => EnvLoader::get('BCRYPT_ROUNDS', 12),
    'csrf_token_length'  => EnvLoader::get('CSRF_TOKEN_LENGTH', 32),
    'max_upload_mb'      => EnvLoader::get('MAX_UPLOAD_MB', 10),
];
