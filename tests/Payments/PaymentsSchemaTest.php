<?php
declare(strict_types=1);

test('payments bootstrap SQL crea pay_events idempotente', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/payments.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `pay_events`'));
    assert_true(str_contains($sql, 'UNIQUE KEY `uq_pay_events_provider_event`'));
    assert_true(! str_contains($sql, 'fw_payment_events'));
});

test('PdoPaymentEventLogRepository implementa tryClaim', function (): void {
    $ref = new ReflectionClass(\Lebytek\Framework\Infrastructure\Payments\PdoPaymentEventLogRepository::class);
    assert_true($ref->implementsInterface(\Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface::class));
    assert_true($ref->hasMethod('tryClaim'));
    assert_true(! $ref->hasMethod('markProcessed'));
});
