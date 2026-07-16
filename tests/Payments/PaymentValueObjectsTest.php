<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

test('Money convierte pesos MXN a centavos', function (): void {
    $money = Money::fromMajor(2199.00, 'mxn');
    assert_same(219900, $money->amountMinor());
    assert_same('mxn', $money->currency());
});

test('Money equals compara minor y currency', function (): void {
    assert_true(Money::fromMajor(2199.0, 'mxn')->equals(new Money(219900, 'mxn')));
    assert_true(! Money::fromMajor(2199.0, 'mxn')->equals(new Money(219901, 'mxn')));
});

test('Money rechaza currency distinta de mxn en v1', function (): void {
    assert_throws(\InvalidArgumentException::class, fn () => Money::fromMajor(100, 'usd'));
    assert_throws(\InvalidArgumentException::class, fn () => Money::fromMajor(100, ''));
});

test('CheckoutRequest exige mode payment o subscription', function (): void {
    $req = new CheckoutRequest(
        money: Money::fromMajor(100.0, 'mxn'),
        description: 'Starter mensual',
        customerEmail: 'buyer@example.com',
        successUrl: 'https://lebytek.com/pago/exito',
        cancelUrl: 'https://lebytek.com/pago/cancelado',
        externalRef: '01JABCDEF',
        metadata: ['order_public_id' => '01JABCDEF'],
        mode: 'payment',
    );
    assert_same('payment', $req->mode());
    assert_same('01JABCDEF', $req->externalRef());
});

test('PaymentEvent normaliza tipo completado e Ignored', function (): void {
    $done = new PaymentEvent(
        type: PaymentEventType::CheckoutCompleted,
        providerEventId: 'evt_123',
        externalRef: '01JABCDEF',
        money: Money::fromMajor(2199.0, 'mxn'),
        rawStatus: 'complete',
    );
    assert_same(PaymentEventType::CheckoutCompleted, $done->type());
    $ignored = new PaymentEvent(
        type: PaymentEventType::Ignored,
        providerEventId: 'evt_ignored',
        externalRef: '',
        money: new Money(0, 'mxn'),
        rawStatus: 'customer.created',
    );
    assert_same(PaymentEventType::Ignored, $ignored->type());
});
