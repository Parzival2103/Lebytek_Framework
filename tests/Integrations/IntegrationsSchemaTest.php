<?php
// tests/Integrations/IntegrationsSchemaTest.php
declare(strict_types=1);

test('el bootstrap SQL de integrations es idempotente y crea int_logs', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/integrations.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `int_logs`'), 'crea int_logs idempotente');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `int_accounts`'), 'crea int_accounts idempotente');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `auth_permisos`'), 'inserta permisos idempotente');
    assert_true(str_contains($sql, 'integrations.enviar'), 'incluye permiso integrations.enviar');
    assert_true(str_contains($sql, 'recipient_masked'), 'columna recipient_masked presente');
});

test('IntegrationLogRepository implementa el puerto del dominio', function (): void {
    $ref = new ReflectionClass(\Lebytek\Framework\Infrastructure\Integrations\Repositories\IntegrationLogRepository::class);
    assert_true(
        $ref->implementsInterface(\Lebytek\Framework\Domain\Integrations\IntegrationLogRepositoryInterface::class),
        'implementa IntegrationLogRepositoryInterface'
    );
});
