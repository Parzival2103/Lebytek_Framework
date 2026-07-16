<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Payments;

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\SupportsSubscriptions;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

final class StripeGateway implements PaymentGatewayInterface, SupportsSubscriptions
{
    /** @param array{secret_key?: string, webhook_secret?: string, currency?: string} $config */
    public function __construct(private readonly array $config)
    {
        Stripe::setApiKey((string) ($config['secret_key'] ?? ''));
    }

    public function key(): string { return 'stripe'; }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $money = $request->money();
        $session = Session::create(
            [
                'mode' => $request->mode(),
                'customer_email' => $request->customerEmail(),
                'success_url' => $request->successUrl(),
                'cancel_url' => $request->cancelUrl(),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $money->currency(),
                        'unit_amount' => $money->amountMinor(),
                        'product_data' => ['name' => $request->description()],
                    ],
                ]],
                'metadata' => array_merge($request->metadata(), [
                    'order_public_id' => $request->externalRef(),
                ]),
            ],
            ['idempotency_key' => $request->externalRef()],
        );

        return new CheckoutSession(
            (string) $session->id,
            (string) $session->url,
        );
    }

    /** @param array{price_id:string,customer_email:string,success_url:string,cancel_url:string,external_ref:string,metadata?:array<string,string>} $input */
    public function createSubscriptionCheckout(array $input): CheckoutSession
    {
        $metadata = array_merge($input['metadata'] ?? [], [
            'order_public_id' => $input['external_ref'],
        ]);
        $session = Session::create(
            [
                'mode' => 'subscription',
                'customer_email' => $input['customer_email'],
                'success_url' => $input['success_url'],
                'cancel_url' => $input['cancel_url'],
                'line_items' => [[
                    'price' => $input['price_id'],
                    'quantity' => 1,
                ]],
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ],
            ['idempotency_key' => $input['external_ref']],
        );

        return new CheckoutSession(
            (string) $session->id,
            (string) $session->url,
        );
    }

    public function parseWebhook(string $payload, string $signature): PaymentEvent
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                (string) ($this->config['webhook_secret'] ?? ''),
            );
        } catch (SignatureVerificationException $exception) {
            throw new \UnexpectedValueException('Invalid Stripe webhook signature', 0, $exception);
        }

        $type = match ($event->type) {
            'checkout.session.completed' => PaymentEventType::CheckoutCompleted,
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed' => PaymentEventType::PaymentFailed,
            'invoice.paid' => PaymentEventType::InvoicePaid,
            'invoice.payment_failed' => PaymentEventType::InvoicePaymentFailed,
            default => PaymentEventType::Ignored,
        };

        $object = $event->data->object;
        $externalRef = $this->extractExternalRef($object);
        $subscriptionId = $this->extractString($object, 'subscription');
        $customerId = $this->extractString($object, 'customer');

        if ($type === PaymentEventType::Ignored) {
            return new PaymentEvent(
                type: $type,
                providerEventId: (string) $event->id,
                externalRef: $externalRef,
                money: new Money(0, 'mxn'),
                rawStatus: (string) $event->type,
                subscriptionId: $subscriptionId,
                customerId: $customerId,
            );
        }

        $currency = strtolower((string) ($object->currency ?? ($this->config['currency'] ?? 'mxn')));
        $amount = (int) ($object->amount_total ?? $object->amount_paid ?? $object->amount_due ?? $object->amount ?? 0);
        if ($currency !== 'mxn') {
            $amount = 0;
        }

        return new PaymentEvent(
            type: $type,
            providerEventId: (string) $event->id,
            externalRef: $externalRef,
            money: new Money($amount, 'mxn'),
            rawStatus: (string) ($object->payment_status ?? $object->status ?? $event->type),
            subscriptionId: $subscriptionId,
            customerId: $customerId,
        );
    }

    private function extractExternalRef(object $object): string
    {
        $metadata = $object->metadata ?? null;
        if (is_array($metadata)) {
            return (string) ($metadata['order_public_id'] ?? '');
        }
        if (is_object($metadata)) {
            return (string) ($metadata->order_public_id ?? '');
        }

        return '';
    }

    private function extractString(object $object, string $field): ?string
    {
        if (! isset($object->{$field})) {
            return null;
        }
        $value = $object->{$field};
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
