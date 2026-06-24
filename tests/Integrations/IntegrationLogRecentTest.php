<?php
// tests/Integrations/IntegrationLogRecentTest.php
declare(strict_types=1);

use App\Infrastructure\Integrations\Repositories\IntegrationLogRepository;
use App\Kernel\Database\Connection;

test('recent devuelve los últimos envíos, más nuevos primero', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_logs');
    $repo = new IntegrationLogRepository();
    $repo->record('email', 'mailer_adapter', 'a***@x.com', 'sent', 'id1', null, []);
    $repo->record('whatsapp', 'green_api', '52***1234', 'failed', null, 'timeout', []);

    $rows = $repo->recent(10);
    assert_same(2, count($rows));
    assert_same('whatsapp', $rows[0]['channel']);
});

test('recent filtra por canal', function () {
    $rows = (new IntegrationLogRepository())->recent(10, 'email');
    foreach ($rows as $r) {
        assert_same('email', $r['channel']);
    }
});
