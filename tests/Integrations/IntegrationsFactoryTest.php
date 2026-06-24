<?php
// tests/Integrations/IntegrationsFactoryTest.php
declare(strict_types=1);

use App\Application\Integrations\IntegrationsFactory;
use App\Domain\Integrations\ApiConnectorInterface;
use App\Infrastructure\Integrations\Channels\EmailChannel;
use App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel;

test('buildChannels solo incluye canales habilitados y construye la clase correcta', function (): void {
    $mailer = new SpyMailer();          // de EmailChannelTest (cargado por el runner)
    $http = new class implements ApiConnectorInterface {
        public function request(string $m, string $u, array $p = [], array $h = []): array { return ['status' => 200, 'body' => '', 'json' => []]; }
    };

    $channelsConfig = [
        'whatsapp' => ['driver' => 'green_api', 'enabled' => false, 'config' => ['base_url' => 'x', 'instance_id' => '1', 'token' => 't']],
        'email'    => ['driver' => 'mailer_adapter', 'enabled' => true, 'config' => []],
    ];

    $built = IntegrationsFactory::buildChannels($channelsConfig, $mailer, $http);
    assert_true(isset($built['email']), 'email habilitado incluido');
    assert_true(isset($built['whatsapp']) === false, 'whatsapp deshabilitado excluido');
    assert_same('mailer_adapter', $built['email']['driver'], 'driver propagado');
    assert_true(($built['email']['factory'])() instanceof EmailChannel, 'factory construye EmailChannel');

    // Y con whatsapp habilitado:
    $channelsConfig['whatsapp']['enabled'] = true;
    $built2 = IntegrationsFactory::buildChannels($channelsConfig, $mailer, $http);
    assert_true(($built2['whatsapp']['factory'])() instanceof GreenApiWhatsappChannel, 'factory construye GreenApiWhatsappChannel');
});
