<?php
// tests/Integrations/IntegrationAccountRepositoryTest.php
declare(strict_types=1);

use Lebytek\Framework\Domain\Integrations\IntegrationAccount;
use Lebytek\Framework\Infrastructure\Integrations\Repositories\IntegrationAccountRepository;
use Lebytek\Framework\Kernel\Database\Connection;

test('save + findById conserva datos y descifra el token', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    $repo = new IntegrationAccountRepository();

    $id = $repo->save(new IntegrationAccount(
        0, 'green_api', 'Interna', '110100001', 'secreto-token', true, null, 'manual', 'manual'
    ));
    $found = $repo->findById($id);
    assert_true($found !== null, 'debe encontrar la cuenta');
    assert_same('110100001', $found->instanceId);
    assert_same('secreto-token', $found->token);
});

test('el token se guarda cifrado en la columna (no en claro)', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    $repo = new IntegrationAccountRepository();
    $id = $repo->save(new IntegrationAccount(0, 'green_api', 'X', 'i', 'EN-CLARO', false, null, 'manual', 'manual'));
    $raw = $pdo->query("SELECT token_encrypted FROM int_accounts WHERE id={$id}")->fetchColumn();
    assert_true(strpos((string) $raw, 'EN-CLARO') === false, 'el token no debe aparecer en claro');
});

test('markDefault deja solo una instancia por defecto', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    $repo = new IntegrationAccountRepository();
    $a = $repo->save(new IntegrationAccount(0, 'green_api', 'A', 'i1', 't1', true, null, 'manual', 'manual'));
    $b = $repo->save(new IntegrationAccount(0, 'green_api', 'B', 'i2', 't2', false, null, 'manual', 'manual'));
    $repo->markDefault($b, 'green_api');
    assert_same($b, $repo->findDefault('green_api')->id);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM int_accounts WHERE is_default=1')->fetchColumn();
    assert_same(1, $count);
});
