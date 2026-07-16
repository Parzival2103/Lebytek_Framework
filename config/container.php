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

    // ── Módulo Pagos (binding condicional al toggle; ver config/modules/payments.php) ──
    if ((bool) Config::get('vertical.modules.payments', false)) {
        $container->singleton(
            \Lebytek\Framework\Application\Payments\PaymentGatewayRegistry::class,
            static fn () => \Lebytek\Framework\Application\Payments\PaymentsFactory::registry()
        );
        $container->singleton(
            \Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface::class,
            static fn () => new \Lebytek\Framework\Infrastructure\Payments\PdoPaymentEventLogRepository()
        );
    }

    // ── Módulo Marketing (bindings condicionales al toggle; ver config/modules/marketing.php) ──
    if ((bool) Config::get('vertical.modules.marketing', false)) {
        // Registro puro de variantes de landing — único `require` del manifiesto (Anti-deuda §A).
        $container->singleton(\App\Domain\Marketing\LandingVariantRegistry::class, function () {
            /** @var array{catalog:list<string>,reveal_id_map:array<string,string>,variants:array<string,array<string,mixed>>} $cfg */
            $cfg = require __DIR__ . '/marketing/landing_variants.php';

            return new \App\Domain\Marketing\LandingVariantRegistry($cfg);
        });

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

        $container->singleton(\App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoVariantWeightRepository());

        $container->singleton(\App\Application\Marketing\LandingExperimentAssigner::class,
            fn(Container $c) => new \App\Application\Marketing\LandingExperimentAssigner(
                $c->get(\App\Domain\Marketing\LandingVariantRegistry::class),
                $c->get(\App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface::class),
                require __DIR__ . '/marketing/landing_experiments.php'));

        $container->singleton(\App\Application\Marketing\MergeLandingVariantUseCase::class,
            fn(Container $c) => new \App\Application\Marketing\MergeLandingVariantUseCase(
                $c->get(\App\Domain\Marketing\LandingVariantRegistry::class)));

        $container->singleton(\App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoLandingMetricsRepository());

        // Colector de métricas (Task 6) — rate limit sys_kv en Infrastructure,
        // interfaz en Domain (Anti-deuda §L/O). Nunca Session/CompraController::allowPost.
        $container->singleton(\App\Domain\Marketing\Contracts\CollectRateLimiterInterface::class, function () {
            $exp = require __DIR__ . '/marketing/landing_experiments.php';

            return new \App\Infrastructure\Marketing\SysKvCollectRateLimiter(
                (int) ($exp['collect_max_per_hour'] ?? 120),
                3600,
            );
        });

        $container->singleton(\App\Application\Marketing\CollectLandingMetricsUseCase::class,
            fn(Container $c) => new \App\Application\Marketing\CollectLandingMetricsUseCase(
                $c->get(\App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface::class),
                $c->get(\App\Domain\Marketing\LandingVariantRegistry::class),
                $c->get(\App\Domain\Marketing\Contracts\CollectRateLimiterInterface::class),
                require __DIR__ . '/marketing/landing_experiments.php'));

        $container->bind(\App\Presentation\Controllers\Publico\LandingMetricsController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LandingMetricsController(
                $c->get(\App\Application\Marketing\CollectLandingMetricsUseCase::class),
                require __DIR__ . '/marketing/landing_experiments.php'));

        $container->singleton(\App\Presentation\Marketing\LandingSectionRenderer::class,
            fn() => new \App\Presentation\Marketing\LandingSectionRenderer());

        $container->bind(\App\Presentation\Controllers\Publico\LandingController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LandingController(
                $c->get(ConfiguracionService::class),
                $c->get(\App\Application\Marketing\RenderLandingUseCase::class),
                $c->get(\App\Application\Marketing\LandingExperimentAssigner::class),
                $c->get(\App\Application\Marketing\MergeLandingVariantUseCase::class),
                $c->get(\App\Presentation\Marketing\LandingSectionRenderer::class)));

        $container->singleton(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoLeadRepository());

        $container->singleton(\App\Domain\Marketing\Contracts\PlantillaRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoPlantillaRepository());

        $container->singleton(\App\Domain\Marketing\Contracts\MembershipRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoMembershipRepository());

        $container->singleton(\App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoChurnMetricsRepository());

        $container->singleton(\App\Infrastructure\Marketing\MarketingChurnDashboardProvider::class,
            fn(Container $c) => new \App\Infrastructure\Marketing\MarketingChurnDashboardProvider(
                $c->get(\App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface::class),
            ));

        $container->singleton(\App\Application\Marketing\MarketingMailRenderer::class, fn (Container $c) => new \App\Application\Marketing\MarketingMailRenderer(
            $c->get(\App\Domain\Marketing\Contracts\PlantillaRepositoryInterface::class),
            $c->get(\Lebytek\Framework\Domain\Interfaces\MailerInterface::class),
        ));

        $container->singleton(\App\Application\Marketing\StartMembershipGraceService::class, fn (Container $c) => new \App\Application\Marketing\StartMembershipGraceService(
            $c->get(\App\Domain\Marketing\Contracts\MembershipRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface::class),
            $c->get(\App\Application\Marketing\MarketingMailRenderer::class),
        ));

        $container->singleton(\App\Application\Marketing\RecoverMembershipPaymentService::class, fn (Container $c) => new \App\Application\Marketing\RecoverMembershipPaymentService(
            $c->get(\App\Domain\Marketing\Contracts\MembershipRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface::class),
            $c->get(\App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::class),
            $c->get(\Lebytek\Framework\Application\Payments\PaymentGatewayRegistry::class),
        ));

        $container->singleton(\App\Application\Marketing\ExpireMembershipGraceService::class, fn (Container $c) => new \App\Application\Marketing\ExpireMembershipGraceService(
            $c->get(\App\Domain\Marketing\Contracts\MembershipRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
            $c->get(\App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::class),
            $c->get(\App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface::class),
            $c->get(\App\Application\Marketing\MarketingMailRenderer::class),
        ));

        $container->bind(\App\Presentation\Controllers\Publico\MembresiaPagoController::class, fn (Container $c) => new \App\Presentation\Controllers\Publico\MembresiaPagoController(
            $c->get(\App\Application\Marketing\RecoverMembershipPaymentService::class),
        ));

        $container->singleton(\App\Application\Marketing\CapturarLeadUseCase::class, function (Container $c) {
            $destinoInterno = (string) $c->get(ConfiguracionService::class)->get('mkt_mail_from', '');
            return new \App\Application\Marketing\CapturarLeadUseCase([
                new \App\Infrastructure\Marketing\LeadCapture\PersistLeadHandler(
                    $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class)),
                new \App\Infrastructure\Marketing\LeadCapture\NotifyInternalHandler(
                    $c->get(\Lebytek\Framework\Domain\Interfaces\MailerInterface::class),
                    $destinoInterno),
                new \App\Infrastructure\Marketing\LeadCapture\AutoresponderHandler(
                    $c->get(\App\Application\Marketing\MarketingMailRenderer::class)),
            ]);
        });

        $container->bind(\App\Presentation\Controllers\Publico\LeadController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LeadController(
                $c->get(\App\Application\Marketing\CapturarLeadUseCase::class),
                $c->get(\App\Domain\Marketing\LandingVariantRegistry::class),
                $c->get(\App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface::class)));

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
            $c->get(\App\Application\Marketing\MarketingMailRenderer::class),
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

        $container->singleton(\App\Domain\Marketing\Contracts\LeadTeamAlertNotifierInterface::class, function () {
            $cfg = [
                'base_url' => (string) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_BASE_URL', 'https://api.green-api.com'),
                'instance_id' => (string) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_INSTANCE', ''),
                'token' => (string) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_TOKEN', ''),
                'timeout' => (int) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_TIMEOUT', 15),
            ];
            $channel = new \Lebytek\Framework\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel(
                new \Lebytek\Framework\Infrastructure\Integrations\Http\HttpApiConnector($cfg['timeout']),
                $cfg
            );
            $enabled = (bool) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_ENABLED', false);
            return new \App\Infrastructure\Marketing\LeadVerifiedWhatsAppNotifier($channel, $enabled);
        });

        $container->singleton(\App\Application\Marketing\VerificarLeadEmailUseCase::class, fn (Container $c) => new \App\Application\Marketing\VerificarLeadEmailUseCase(
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadTeamAlertNotifierInterface::class),
        ));

        $container->bind(\App\Presentation\Controllers\Publico\LeadEmailVerificationController::class, fn (Container $c) => new \App\Presentation\Controllers\Publico\LeadEmailVerificationController(
            $c->get(\App\Application\Marketing\VerificarLeadEmailUseCase::class)
        ));

        $container->singleton(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoMembershipOrderRepository());

        $container->singleton(\App\Domain\Marketing\Contracts\PurchaseTeamAlertNotifierInterface::class, function () {
            $cfg = [
                'base_url' => (string) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_BASE_URL', 'https://api.green-api.com'),
                'instance_id' => (string) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_INSTANCE', ''),
                'token' => (string) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_TOKEN', ''),
                'timeout' => (int) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_TIMEOUT', 15),
            ];
            $channel = new \Lebytek\Framework\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel(
                new \Lebytek\Framework\Infrastructure\Integrations\Http\HttpApiConnector($cfg['timeout']),
                $cfg
            );
            $enabled = (bool) \Lebytek\Framework\Kernel\EnvLoader::get('GREEN_API_ENABLED', false);
            return new \App\Infrastructure\Marketing\PurchaseWhatsAppNotifier($channel, $enabled);
        });

        $container->singleton(\App\Application\Marketing\CrearOrdenMembresiaUseCase::class, fn (Container $c) => new \App\Application\Marketing\CrearOrdenMembresiaUseCase(
            $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\PurchaseTeamAlertNotifierInterface::class),
        ));

        $container->singleton(\App\Application\Marketing\ActivateMembershipFromOrderService::class, fn (Container $c) => new \App\Application\Marketing\ActivateMembershipFromOrderService(
            $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
            $c->get(\App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::class),
            $c->get(\App\Application\Marketing\MarketingMailRenderer::class),
            $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class),
        ));

        $container->singleton(\App\Application\Marketing\AutorizarOrdenMembresiaUseCase::class, fn (Container $c) => new \App\Application\Marketing\AutorizarOrdenMembresiaUseCase(
            $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
            $c->get(\App\Application\Marketing\ActivateMembershipFromOrderService::class),
        ));

        $container->singleton(\App\Application\Marketing\ActivarPlanOrdenPagadaUseCase::class, fn (Container $c) => new \App\Application\Marketing\ActivarPlanOrdenPagadaUseCase(
            $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
            $c->get(\App\Application\Marketing\ActivateMembershipFromOrderService::class),
        ));

        // Stripe es opcional: si el módulo payments está OFF (transfer-only), no se
        // resuelve IniciarPagoStripeUseCase (que exige PaymentGatewayRegistry, sólo
        // vinculado con payments ON) para no romper el flujo de transferencia.
        $container->bind(\App\Presentation\Controllers\Publico\CompraController::class, function (Container $c) {
            $iniciarPago = ((bool) Config::get('vertical.modules.payments', false))
                ? $c->get(\App\Application\Marketing\IniciarPagoStripeUseCase::class)
                : null;

            return new \App\Presentation\Controllers\Publico\CompraController(
                $c->get(ConfiguracionService::class),
                $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class),
                $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
                $c->get(\App\Application\Marketing\CrearOrdenMembresiaUseCase::class),
                $iniciarPago,
            );
        });

        $container->bind(\App\Presentation\Controllers\Admin\MarketingOrdenesController::class, fn (Container $c) => new \App\Presentation\Controllers\Admin\MarketingOrdenesController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
            $c->get(\App\Application\Marketing\AutorizarOrdenMembresiaUseCase::class),
            $c->get(\App\Application\Marketing\ActivarPlanOrdenPagadaUseCase::class),
        ));

        if ((bool) Config::get('vertical.modules.payments', false)) {
            $container->singleton(\App\Application\Marketing\IniciarPagoStripeUseCase::class, fn (Container $c) => new \App\Application\Marketing\IniciarPagoStripeUseCase(
                $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
                $c->get(\Lebytek\Framework\Application\Payments\PaymentGatewayRegistry::class),
            ));

            $container->singleton(\App\Application\Marketing\ConfirmarPagoStripeUseCase::class, fn (Container $c) => new \App\Application\Marketing\ConfirmarPagoStripeUseCase(
                $c->get(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class),
                $c->get(\Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface::class),
                $c->get(\App\Application\Marketing\ActivateMembershipFromOrderService::class),
                $c->get(\App\Domain\Marketing\Contracts\MembershipRepositoryInterface::class),
                $c->get(\App\Application\Marketing\StartMembershipGraceService::class),
                $c->get(\App\Application\Marketing\RecoverMembershipPaymentService::class),
            ));

            $container->bind(\App\Presentation\Controllers\Publico\StripeWebhookController::class, fn (Container $c) => new \App\Presentation\Controllers\Publico\StripeWebhookController(
                $c->get(\Lebytek\Framework\Application\Payments\PaymentGatewayRegistry::class),
                $c->get(\App\Application\Marketing\ConfirmarPagoStripeUseCase::class),
            ));
        }

        // ── Task 9: Ops UI accept/reject de propuestas de reponderación ──
        $container->singleton(\App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface::class,
            fn() => new \App\Infrastructure\Marketing\PdoVariantProposalRepository());

        $container->singleton(\App\Application\Marketing\AcceptVariantProposalUseCase::class, fn (Container $c) => new \App\Application\Marketing\AcceptVariantProposalUseCase(
            $c->get(\App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface::class),
        ));

        $container->singleton(\App\Application\Marketing\RejectVariantProposalUseCase::class, fn (Container $c) => new \App\Application\Marketing\RejectVariantProposalUseCase(
            $c->get(\App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface::class),
        ));

        $container->bind(\App\Presentation\Controllers\Admin\MarketingExperimentsController::class, fn (Container $c) => new \App\Presentation\Controllers\Admin\MarketingExperimentsController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface::class),
            $c->get(\App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface::class),
            $c->get(\App\Application\Marketing\AcceptVariantProposalUseCase::class),
            $c->get(\App\Application\Marketing\RejectVariantProposalUseCase::class),
            require __DIR__ . '/marketing/landing_experiments.php',
        ));
    }

    // Portal waapi (vhost dedicado: marketing off + WAAPI_PORTAL_ENABLED=true)
    $waapiPortalEnabled = filter_var(
        (string) \Lebytek\Framework\Kernel\EnvLoader::get('WAAPI_PORTAL_ENABLED', 'false'),
        FILTER_VALIDATE_BOOLEAN,
    );
    $marketingEnabled = (bool) Config::get('vertical.modules.marketing', false);

    if ($waapiPortalEnabled || $marketingEnabled) {
        $container->singleton(\App\Infrastructure\Integrations\LebytekApi\ClientTenantApiClient::class, fn () => new \App\Infrastructure\Integrations\LebytekApi\ClientTenantApiClient(
            transport: new \App\Infrastructure\Integrations\LebytekApi\CurlLebytekApiTransport(
                (int) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
            ),
            baseUrl: (string) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_URL', ''),
            timeoutSeconds: (int) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
        ));

        $container->singleton(\App\Application\Marketing\WaapiPortalSession::class, fn (Container $c) => new \App\Application\Marketing\WaapiPortalSession(
            $c->get(\App\Infrastructure\Integrations\LebytekApi\ClientTenantApiClient::class),
        ));

        $container->bind(\App\Presentation\Controllers\Publico\WaapiPortalController::class, fn (Container $c) => new \App\Presentation\Controllers\Publico\WaapiPortalController(
            $c->get(ConfiguracionService::class),
            $c->get(\App\Application\Marketing\WaapiPortalSession::class),
            $c->get(\App\Infrastructure\Integrations\LebytekApi\ClientTenantApiClient::class),
        ));
    }
};
