<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\Container\FrameworkServiceProvider;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;

return static function (Container $container): void {
    FrameworkServiceProvider::register($container);

    // Registry de secciones de Ajustes — siempre resoluble; los providers se cargan
    // solo si su módulo está activo (toggle inline). AjustesController lo consume.
    $container->singleton(\Lebytek\Framework\Application\Services\SettingsSectionRegistry::class, function () {
        $providers = [];
        if ((bool) Config::get('vertical.modules.integrations', false)) {
            $providers[] = new \Lebytek\Framework\Infrastructure\Integrations\Settings\IntegrationsWhatsappSettingsProvider();
        }
        return new \Lebytek\Framework\Application\Services\SettingsSectionRegistry($providers);
    });

    // ── Módulo Integraciones (binding condicional al toggle; ver config/modules/integrations.php) ──
    if ((bool) Config::get('vertical.modules.integrations', false)) {
        $container->singleton(
            \Lebytek\Framework\Application\Integrations\NotificationDispatcher::class,
            static fn() => \Lebytek\Framework\Application\Integrations\IntegrationsFactory::dispatcher()
        );

        $container->singleton(\Lebytek\Framework\Domain\Integrations\IntegrationAccountRepositoryInterface::class,
            fn() => new \Lebytek\Framework\Infrastructure\Integrations\Repositories\IntegrationAccountRepository());

        $container->singleton(\Lebytek\Framework\Domain\Integrations\PartnerConnectorInterface::class, function () {
            $base = (array) Config::get('integrations.channels.whatsapp.config', []);
            return new \Lebytek\Framework\Infrastructure\Integrations\Partner\GreenApiPartnerConnector(
                new \Lebytek\Framework\Infrastructure\Integrations\Http\HttpApiConnector((int) ($base['timeout'] ?? 15)),
                (string) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_PARTNER_TOKEN', ''),
                (string) ($base['base_url'] ?? 'https://api.green-api.com')
            );
        });

        $container->singleton(\Lebytek\Framework\Application\Integrations\DemoProvisioningService::class, function (Container $c) {
            return new \Lebytek\Framework\Application\Integrations\DemoProvisioningService(
                $c->get(\Lebytek\Framework\Domain\Integrations\IntegrationAccountRepositoryInterface::class),
                $c->get(\Lebytek\Framework\Domain\Integrations\PartnerConnectorInterface::class),
                \Lebytek\Framework\Application\Integrations\IntegrationsFactory::dispatcher(),
                (string) \Lebytek\Framework\Kernel\EnvLoader::get('APP_URL', '')
            );
        });

        $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\IntegrationsController::class, function (Container $c) {
            return new \Lebytek\Framework\Presentation\Controllers\Admin\IntegrationsController(
                $c->get(ConfiguracionService::class),
                $c->get(AdminNavigationMenuService::class),
                $c->get(\Lebytek\Framework\Domain\Integrations\IntegrationAccountRepositoryInterface::class),
                $c->get(\Lebytek\Framework\Domain\Integrations\PartnerConnectorInterface::class),
                $c->get(\Lebytek\Framework\Application\Integrations\DemoProvisioningService::class),
                new \Lebytek\Framework\Infrastructure\Integrations\Repositories\IntegrationLogRepository()
            );
        });
    }
};
