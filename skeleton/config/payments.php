<?php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Payments\StripeGateway;
use Lebytek\Framework\Kernel\EnvLoader;

return [
    'gateways' => [
        'stripe' => [
            'driver'  => 'stripe',
            'class'   => StripeGateway::class,
            'enabled' => (bool) EnvLoader::get('STRIPE_ENABLED', false),
            'config'  => [
                'secret_key'     => EnvLoader::get('STRIPE_SECRET_KEY', ''),
                'webhook_secret' => EnvLoader::get('STRIPE_WEBHOOK_SECRET', ''),
                'currency'       => EnvLoader::get('PAYMENTS_CURRENCY', 'mxn'),
            ],
        ],
    ],
    'default' => EnvLoader::get('PAYMENTS_DEFAULT_GATEWAY', 'stripe'),
];
