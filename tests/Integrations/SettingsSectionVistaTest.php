<?php
// tests/Integrations/SettingsSectionVistaTest.php
declare(strict_types=1);

use App\Infrastructure\Integrations\Settings\IntegrationsWhatsappSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider;

test('la sección de integraciones declara permiso y vista custom', function () {
    $p = new IntegrationsWhatsappSettingsProvider();
    assert_same('integrations.configurar', $p->permiso());
    assert_true($p->vista() !== null, 'integraciones usa vista custom');
    assert_same([], $p->campos());
});

test('las secciones declarativas de marketing devuelven vista() null', function () {
    assert_null((new MarketingCorreoSettingsProvider())->vista());
});
