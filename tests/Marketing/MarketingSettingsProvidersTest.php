<?php
// tests/Marketing/MarketingSettingsProvidersTest.php
declare(strict_types=1);

use App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingPaquetesSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingTrackingSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingContenidoSettingsProvider;
use App\Domain\Interfaces\SettingsSectionProviderInterface;

test('los 4 providers de marketing implementan la interfaz y exigen marketing.gestionar', function (): void {
    foreach ([
        new MarketingCorreoSettingsProvider(),
        new MarketingPaquetesSettingsProvider(),
        new MarketingTrackingSettingsProvider(),
        new MarketingContenidoSettingsProvider(),
    ] as $p) {
        assert_true($p instanceof SettingsSectionProviderInterface, get_class($p) . ' implementa la interfaz');
        assert_same('marketing.gestionar', $p->permiso());
        assert_true($p->clave() !== '', 'tiene clave');
        assert_true(count($p->campos()) > 0, 'declara campos');
    }
});

test('todos los campos de marketing usan prefijo mkt_', function (): void {
    foreach ([
        new MarketingCorreoSettingsProvider(),
        new MarketingPaquetesSettingsProvider(),
        new MarketingTrackingSettingsProvider(),
        new MarketingContenidoSettingsProvider(),
    ] as $p) {
        foreach ($p->campos() as $campo) {
            assert_true(str_starts_with($campo['name'], 'mkt_'), $campo['name'] . ' usa prefijo mkt_');
        }
    }
});
