<?php
// tests/Integrations/SettingsSectionVistaTest.php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Integrations\Settings\IntegrationsWhatsappSettingsProvider;

test('la sección de integraciones declara permiso y vista custom', function () {
    $p = new IntegrationsWhatsappSettingsProvider();
    assert_same('integrations.configurar', $p->permiso());
    assert_true($p->vista() !== null, 'integraciones usa vista custom');
    assert_same([], $p->campos());
});
