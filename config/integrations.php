<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\EnvLoader;

/*
|--------------------------------------------------------------------------
| config/integrations.php — mapa de canales, límites y webhooks (stub F2).
|--------------------------------------------------------------------------
| 'class' es el FQCN del canal; lo resuelve ChannelRegistry vía
| IntegrationsFactory. Las credenciales solo viven en .env.
*/
return [
    'channels' => [
        'whatsapp' => [
            'driver'  => 'green_api',
            'class'   => \Lebytek\Framework\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel::class,
            'enabled' => (bool) EnvLoader::get('GREEN_API_ENABLED', false),
            'config'  => [
                'base_url'    => EnvLoader::get('GREEN_API_BASE_URL', 'https://api.green-api.com'),
                'instance_id' => EnvLoader::get('GREEN_API_INSTANCE', ''),
                'token'       => EnvLoader::get('GREEN_API_TOKEN', ''),
                'timeout'     => (int) EnvLoader::get('GREEN_API_TIMEOUT', 15),
            ],
        ],
        'email' => [
            'driver'  => 'mailer_adapter',
            'class'   => \Lebytek\Framework\Infrastructure\Integrations\Channels\EmailChannel::class,
            'enabled' => true,
            'config'  => [],
        ],
    ],

    'rate_limit' => [
        'whatsapp' => ['max' => 30, 'window_seconds' => 60],
    ],

    'activation' => [
        // Enlace placeholder hasta publicar docs propias del módulo.
        'api_docs_url' => EnvLoader::get('INTEGRATIONS_API_DOCS_URL', '/docs/integraciones/whatsapp-api'),
    ],

    // Fase 2 (solo diseño): validadores de webhooks por proveedor.
    'webhooks' => [],
];
