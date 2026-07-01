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
        if ((bool) Config::get('vertical.modules.marketing', false)) {
            $providers = [
                new \App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingPaquetesSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingTrackingSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingContenidoSettingsProvider(),
            ];
        }
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

    // ── Módulo Marketing (bindings condicionales al toggle; ver config/modules/marketing.php) ──
    if ((bool) Config::get('vertical.modules.marketing', false)) {
        $container->singleton(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoMarketingContentRepository());

        $container->singleton(\App\Domain\Marketing\Contracts\LandingContentProviderInterface::class,
            fn(Container $c) => new \App\Infrastructure\Marketing\CrudLandingContentProvider(
                $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class)));

        $container->singleton(\App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class,
            fn(Container $c) => new \App\Infrastructure\Marketing\CrudCommercialPackageSource(
                $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class)));

        $container->singleton(\App\Application\Marketing\RenderLandingUseCase::class,
            fn(Container $c) => new \App\Application\Marketing\RenderLandingUseCase(
                $c->get(\App\Domain\Marketing\Contracts\LandingContentProviderInterface::class),
                $c->get(\App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class)));

        $container->bind(\App\Presentation\Controllers\Publico\LandingController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LandingController(
                $c->get(ConfiguracionService::class),
                $c->get(\App\Application\Marketing\RenderLandingUseCase::class)));

        $container->singleton(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoLeadRepository());

        $container->singleton(\App\Application\Marketing\CapturarLeadUseCase::class, function (Container $c) {
            $destinoInterno = (string) $c->get(ConfiguracionService::class)->get('mkt_mail_from', '');
            return new \App\Application\Marketing\CapturarLeadUseCase([
                new \App\Infrastructure\Marketing\LeadCapture\PersistLeadHandler(
                    $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class)),
                new \App\Infrastructure\Marketing\LeadCapture\NotifyInternalHandler(
                    $c->get(\Lebytek\Framework\Domain\Interfaces\MailerInterface::class),
                    $destinoInterno),
                new \App\Infrastructure\Marketing\LeadCapture\AutoresponderHandler(
                    $c->get(\Lebytek\Framework\Domain\Interfaces\MailerInterface::class)),
            ]);
        });

        $container->bind(\App\Presentation\Controllers\Publico\LeadController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LeadController(
                $c->get(\App\Application\Marketing\CapturarLeadUseCase::class)));

        $container->bind(\App\Presentation\Controllers\Publico\PortalClienteController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\PortalClienteController(
                $c->get(ConfiguracionService::class)));

        $container->singleton(\App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::class, fn () => new \App\Infrastructure\Integrations\LebytekApi\LebytekApiClient(
            baseUrl: (string) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_URL', ''),
            token: (string) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_TOKEN', ''),
            timeoutSeconds: (int) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
            maxRetries: (int) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_RETRY_MAX', 3),
        ));

        $container->singleton(\App\Application\Marketing\LeadApiProvisioningService::class, fn (Container $c) => new \App\Application\Marketing\LeadApiProvisioningService(
            $c->get(\App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
            $c->get(\Lebytek\Framework\Domain\Interfaces\MailerInterface::class),
        ));

        $container->singleton(\App\Application\Marketing\LeadApiDeprovisioningService::class, fn (Container $c) => new \App\Application\Marketing\LeadApiDeprovisioningService(
            $c->get(\App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
        ));

        $container->bind(\App\Presentation\Controllers\Admin\MarketingLeadsController::class, fn (Container $c) => new \App\Presentation\Controllers\Admin\MarketingLeadsController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Application\Marketing\LeadApiProvisioningService::class),
            $c->get(\App\Application\Marketing\LeadApiDeprovisioningService::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
        ));
    }
};
