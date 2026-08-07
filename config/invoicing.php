<?php
declare(strict_types=1);

use Lebytek\Framework\Kernel\EnvLoader;

return [
    'providers' => [
        'facturapi' => [
            'driver'  => 'facturapi',
            'class'   => 'Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider',
            'enabled' => (bool) EnvLoader::get('FACTURAPI_ENABLED', false),
            'config'  => [
                'secret_key' => EnvLoader::get('FACTURAPI_SECRET_KEY', ''),
                'mode'       => EnvLoader::get('FACTURAPI_MODE', 'test'),
            ],
        ],
    ],
    'default' => EnvLoader::get('INVOICING_DEFAULT_PROVIDER', 'facturapi'),
];
