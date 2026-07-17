<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Infrastructure\Payments\StripeGateway;

test('StripeGateway parseWebhook maps invoice.payment_failed with subscription id', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH.'/tests/Payments/fixtures/stripe_invoice_payment_failed.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);

    $event = $gateway->parseWebhook($payload, $signature);

    assert_same(PaymentEventType::InvoicePaymentFailed, $event->type());
    assert_same('sub_test_abc123', $event->subscriptionId());
    assert_same('cus_test_xyz', $event->customerId());
    assert_same('01JORDPAY00000000000000001', $event->externalRef());
});

test('StripeGateway parseWebhook maps invoice.paid', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH.'/tests/Payments/fixtures/stripe_invoice_paid.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);

    $event = $gateway->parseWebhook($payload, $signature);

    assert_same(PaymentEventType::InvoicePaid, $event->type());
    assert_same('sub_test_abc123', $event->subscriptionId());
});

test('StripeGateway implements SupportsSubscriptions with createSubscriptionCheckout', function (): void {
    $source = (string) file_get_contents(ROOT_PATH.'/src/Infrastructure/Payments/StripeGateway.php');
    assert_true(str_contains($source, 'createSubscriptionCheckout'));
    assert_true(str_contains($source, "'mode' => 'subscription'"));
    assert_true(str_contains($source, 'invoice.payment_failed'));
});
