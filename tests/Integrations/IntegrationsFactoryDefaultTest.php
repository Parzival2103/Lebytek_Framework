<?php
// tests/Integrations/IntegrationsFactoryDefaultTest.php
declare(strict_types=1);

use App\Application\Integrations\IntegrationsFactory;
use App\Domain\Integrations\IntegrationAccount;
use App\Infrastructure\Integrations\Repositories\IntegrationAccountRepository;
use App\Kernel\Database\Connection;

test('IntegrationsFactory::resolveWhatsappConfig usa la instancia default de DB', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    (new IntegrationAccountRepository())->save(new IntegrationAccount(
        0, 'green_api', 'Interna', 'DB-INSTANCE', 'DB-TOKEN', true, null, 'authorized', 'manual'
    ));

    $base = ['instance_id' => 'ENV-INSTANCE', 'token' => 'ENV-TOKEN', 'base_url' => 'https://x', 'timeout' => 15];
    $resolved = IntegrationsFactory::resolveWhatsappConfig($base);
    assert_same('DB-INSTANCE', $resolved['instance_id']);
    assert_same('DB-TOKEN', $resolved['token']);
    assert_same('https://x', $resolved['base_url']);
});

test('IntegrationsFactory::resolveWhatsappConfig cae a .env sin fila default', function () {
    Connection::getInstance()->exec('DELETE FROM int_accounts');
    $base = ['instance_id' => 'ENV-INSTANCE', 'token' => 'ENV-TOKEN', 'base_url' => 'https://x', 'timeout' => 15];
    $resolved = IntegrationsFactory::resolveWhatsappConfig($base);
    assert_same('ENV-INSTANCE', $resolved['instance_id']);
    assert_same('ENV-TOKEN', $resolved['token']);
});
