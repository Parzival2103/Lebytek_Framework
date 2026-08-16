<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Infrastructure\Payments\StripeGateway;

function stripeTestSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    return "t={$timestamp},v1={$signature}";
}

test('StripeGateway rechaza firma inválida', function (): void {
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => 'whsec_test_secret',
        'currency' => 'mxn',
    ]);
    assert_throws(\UnexpectedValueException::class, fn () => $gateway->parseWebhook('{}', 'bad_sig'));
});

test('StripeGateway parseWebhook acepta firma válida de fixture', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_checkout_completed.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gateway->parseWebhook($payload, $signature);
    assert_same(PaymentEventType::CheckoutCompleted, $event->type());
    assert_true($event->externalRef() !== '');
    assert_same(219900, $event->money()->amountMinor());
    assert_same('mxn', $event->money()->currency());
});

test('StripeGateway parseWebhook subscription checkout expone subscriptionId', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_checkout_subscription_completed.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gateway->parseWebhook($payload, $signature);
    assert_same(PaymentEventType::CheckoutCompleted, $event->type());
    assert_same('sub_test_456', $event->subscriptionId());
    assert_same('cus_test_789', $event->customerId());
    assert_same('subscription', $event->checkoutMode());
});

test('StripeGateway parseWebhook invoice.paid resuelve order_public_id desde subscription metadata', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_invoice_paid.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gateway->parseWebhook($payload, $signature);
    assert_same(PaymentEventType::InvoicePaid, $event->type());
    assert_same('order_01JABCDEF', $event->externalRef());
    assert_same(219900, $event->money()->amountMinor());
});

test('StripeGateway moneda distinta de mxn devuelve PaymentFailed', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_checkout_completed.json');
    $payload = str_replace('"currency": "mxn"', '"currency": "usd"', $payload);
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gateway->parseWebhook($payload, $signature);
    assert_same(PaymentEventType::PaymentFailed, $event->type());
    assert_true(str_contains($event->rawStatus(), 'unsupported_currency'));
});

test('StripeGateway evento no mapeado devuelve Ignored', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_unmapped_event.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gateway->parseWebhook($payload, $signature);
    assert_same(PaymentEventType::Ignored, $event->type());
});

test('StripeGateway createCheckout usa idempotencyKey o cae a externalRef', function (): void {
    $source = (string) file_get_contents(ROOT_PATH . '/src/Infrastructure/Payments/StripeGateway.php');
    assert_true(str_contains($source, 'idempotency_key'));
    assert_true(str_contains($source, 'idempotencyKey()'));
    assert_true(str_contains($source, 'externalRef()'));
});
