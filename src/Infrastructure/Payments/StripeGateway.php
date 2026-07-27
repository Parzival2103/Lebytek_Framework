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
use Stripe\BillingPortal\Session as BillingPortalSession;
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

    public function createSubscriptionCheckout(array $params): CheckoutSession
    {
        $metadata = $params['metadata'] ?? [];
        $metadata['order_public_id'] = $metadata['order_public_id'] ?? (string) ($params['external_ref'] ?? '');

        $session = Session::create(
            [
                'mode' => 'subscription',
                'customer_email' => (string) ($params['customer_email'] ?? ''),
                'success_url' => (string) ($params['success_url'] ?? ''),
                'cancel_url' => (string) ($params['cancel_url'] ?? ''),
                'line_items' => [[
                    'quantity' => 1,
                    'price' => (string) ($params['price_id'] ?? ''),
                ]],
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ],
            ['idempotency_key' => (string) ($params['external_ref'] ?? uniqid('sub_', true))],
        );

        return new CheckoutSession(
            (string) $session->id,
            (string) $session->url,
        );
    }

    public function createBillingPortalSession(array $params): CheckoutSession
    {
        $session = BillingPortalSession::create([
            'customer' => (string) ($params['customer_id'] ?? ''),
            'return_url' => (string) ($params['return_url'] ?? ''),
        ]);

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
            'invoice.paid' => PaymentEventType::InvoicePaid,
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed',
            'invoice.payment_failed' => PaymentEventType::PaymentFailed,
            default => PaymentEventType::Ignored,
        };

        $object = $event->data->object;
        $meta = $this->extractMetadata($object);
        $externalRef = (string) ($meta['order_public_id'] ?? '');
        $membresiaId = (string) ($meta['membresia_id'] ?? '');
        $expectedCurrency = strtolower((string) ($this->config['currency'] ?? 'mxn'));

        if ($type === PaymentEventType::Ignored) {
            return new PaymentEvent(
                type: $type,
                providerEventId: (string) $event->id,
                externalRef: $externalRef,
                money: new Money(0, 'mxn'),
                rawStatus: (string) $event->type,
                membresiaId: $membresiaId !== '' ? $membresiaId : null,
            );
        }

        $currency = strtolower((string) ($object->currency ?? $expectedCurrency));
        $amount = (int) ($object->amount_total ?? $object->amount_paid ?? $object->amount ?? 0);

        if ($currency !== $expectedCurrency) {
            return new PaymentEvent(
                type: PaymentEventType::PaymentFailed,
                providerEventId: (string) $event->id,
                externalRef: $externalRef,
                money: new Money(0, 'mxn'),
                rawStatus: 'unsupported_currency:'.$currency,
                subscriptionId: $this->stringOrNull($object->subscription ?? null),
                customerId: $this->stringOrNull($object->customer ?? null),
                checkoutMode: (string) ($object->mode ?? 'payment'),
                membresiaId: $membresiaId !== '' ? $membresiaId : null,
            );
        }

        return new PaymentEvent(
            type: $type,
            providerEventId: (string) $event->id,
            externalRef: $externalRef,
            money: new Money($amount, $expectedCurrency),
            rawStatus: (string) ($object->payment_status ?? $object->status ?? $event->type),
            subscriptionId: $this->stringOrNull($object->subscription ?? null),
            customerId: $this->stringOrNull($object->customer ?? null),
            checkoutMode: (string) ($object->mode ?? ($type === PaymentEventType::InvoicePaid ? 'subscription' : 'payment')),
            membresiaId: $membresiaId !== '' ? $membresiaId : null,
        );
    }

    /** @return array<string, string> */
    private function extractMetadata(object $object): array
    {
        $out = [];
        $sources = [
            $object->metadata ?? null,
            $object->subscription_details->metadata ?? null,
        ];

        foreach ($sources as $metadata) {
            foreach ($this->metadataToArray($metadata) as $key => $value) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private function metadataToArray(mixed $metadata): array
    {
        if ($metadata === null) {
            return [];
        }
        if (is_array($metadata)) {
            $out = [];
            foreach ($metadata as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $out[(string) $key] = (string) $value;
                }
            }

            return $out;
        }
        if (is_object($metadata)) {
            if (method_exists($metadata, 'toArray')) {
                return $this->metadataToArray($metadata->toArray());
            }

            return $this->metadataToArray(json_decode(json_encode($metadata), true));
        }

        return [];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
