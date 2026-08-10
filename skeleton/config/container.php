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

    // ── Módulo Facturación (binding condicional al toggle; ver config/modules/invoicing.php) ──
    if ((bool) Config::get('vertical.modules.invoicing', false)) {
        $container->singleton(
            \Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class,
            static fn () => \Lebytek\Framework\Application\Invoicing\InvoicingFactory::registry()
        );
        $container->singleton(
            \Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class,
            static fn () => new \Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository()
        );
        $container->singleton(
            \Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface::class,
            static fn () => new \Lebytek\Framework\Infrastructure\Invoicing\PdoOrganizationSettingsRepository()
        );
        $container->singleton(
            \Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator::class,
            static fn () => new \Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator()
        );
        $container->singleton(
            \Lebytek\Framework\Application\Invoicing\SyncOrganizationSettingsFromConfig::class,
            static fn (Container $c) => new \Lebytek\Framework\Application\Invoicing\SyncOrganizationSettingsFromConfig(
                $c->get(\Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface::class)
            )
        );

        $container->get(\Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class);
        $container->get(\Lebytek\Framework\Application\Invoicing\SyncOrganizationSettingsFromConfig::class)->sync();

        $container->bind(
            \Lebytek\Framework\Application\Invoicing\InvoiceIdResolver::class,
            static fn (Container $c) => new \Lebytek\Framework\Application\Invoicing\InvoiceIdResolver(
                $c->get(\Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class)
            )
        );
        $container->bind(
            \Lebytek\Framework\Application\Invoicing\CancelIssuedInvoice::class,
            static fn (Container $c) => new \Lebytek\Framework\Application\Invoicing\CancelIssuedInvoice(
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class),
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceIdResolver::class),
                $c->get(\Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class),
                (string) Config::get('invoicing.default', 'facturapi')
            )
        );
        $container->bind(
            \Lebytek\Framework\Application\Invoicing\DownloadInvoiceDocument::class,
            static fn (Container $c) => new \Lebytek\Framework\Application\Invoicing\DownloadInvoiceDocument(
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class),
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceIdResolver::class),
                (string) Config::get('invoicing.default', 'facturapi')
            )
        );
        $container->bind(
            \Lebytek\Framework\Application\Invoicing\SendInvoiceByEmail::class,
            static fn (Container $c) => new \Lebytek\Framework\Application\Invoicing\SendInvoiceByEmail(
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class),
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceIdResolver::class),
                (string) Config::get('invoicing.default', 'facturapi')
            )
        );
        $container->bind(
            \Lebytek\Framework\Application\Invoicing\ReconcileIssuedInvoice::class,
            static fn (Container $c) => new \Lebytek\Framework\Application\Invoicing\ReconcileIssuedInvoice(
                $c->get(\Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class),
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class),
                (string) Config::get('invoicing.default', 'facturapi')
            )
        );
        $container->bind(
            \Lebytek\Framework\Application\Invoicing\ApplyInvoiceProviderEvent::class,
            static fn (Container $c) => new \Lebytek\Framework\Application\Invoicing\ApplyInvoiceProviderEvent(
                $c->get(\Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class),
                $c->get(\Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class),
                (string) Config::get('invoicing.default', 'facturapi')
            )
        );

        if ($container->has(\Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface::class)) {
            $container->bind(
                \Lebytek\Framework\Application\Invoicing\IssueInvoiceFromSource::class,
                static fn (Container $c) => \Lebytek\Framework\Application\Invoicing\InvoicingFactory::makeIssueInvoiceFromSource(
                    $c->get(\Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface::class)
                )
            );
        }
    }
};
