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
    'subscription_checkout' => (bool) EnvLoader::get('PAYMENTS_SUBSCRIPTION_CHECKOUT', false),
    'stripe_prices' => [
        'starter' => [
            'monthly' => EnvLoader::get('STRIPE_PRICE_STARTER_MONTHLY', ''),
            'annual' => EnvLoader::get('STRIPE_PRICE_STARTER_ANNUAL', ''),
        ],
        'business' => [
            'monthly' => EnvLoader::get('STRIPE_PRICE_BUSINESS_MONTHLY', ''),
            'annual' => EnvLoader::get('STRIPE_PRICE_BUSINESS_ANNUAL', ''),
        ],
    ],
];
