<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\ApplyInvoiceProviderEvent;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceProviderEvent;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Infrastructure\Invoicing\Facturapi\FacturapiTransportInterface;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiWebhookSignature;

require_once __DIR__ . '/Support/InMemoryInvoiceEventLog.php';

final class Task9WebhookTransport implements FacturapiTransportInterface
{
    public int $retrieveCalls = 0;
    /** @var array<string, array<string, mixed>> */
    public array $retrieveResponses = [];

    public function create(array $payload): array
    {
        return [];
    }

    public function retrieve(string $providerInvoiceId): array
    {
        $this->retrieveCalls++;

        return $this->retrieveResponses[$providerInvoiceId] ?? [
            'id' => $providerInvoiceId,
            'uuid' => 'uuid_' . $providerInvoiceId,
            'status' => 'valid',
        ];
    }

    public function listByExternalId(string $externalId): array
    {
        return [];
    }

    public function cancel(string $providerInvoiceId, array $payload): array
    {
        return ['id' => $providerInvoiceId, 'uuid' => 'uuid_cancelled', 'status' => 'canceled'];
    }

    public function pdf(string $providerInvoiceId): string
    {
        return 'pdf';
    }

    public function xml(string $providerInvoiceId): string
    {
        return 'xml';
    }

    public function email(string $providerInvoiceId, string $email): array
    {
        return [];
    }
}

final class Task9WebhookProvider implements InvoiceProviderInterface
{
    public int $retrieveCalls = 0;
    /** @var array<string, IssuedInvoice> */
    public array $retrieveResponses = [];

    public function key(): string
    {
        return 'facturapi';
    }

    public function createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice
    {
        return new IssuedInvoice('inv_unexpected', 'uuid_unexpected', InvoiceStatus::Valid);
    }

    public function externalIdForIssue(string $idempotencyKey): string
    {
        return 'fake-external:' . $idempotencyKey;
    }

    public function retrieveInvoice(string $providerInvoiceId): IssuedInvoice
    {
        $this->retrieveCalls++;

        return $this->retrieveResponses[$providerInvoiceId] ?? new IssuedInvoice(
            $providerInvoiceId,
            'uuid_' . $providerInvoiceId,
            InvoiceStatus::Valid,
            meta: ['provider_status' => 'valid'],
        );
    }

    public function listByExternalId(string $externalId): array
    {
        return [];
    }

    public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice
    {
        return new IssuedInvoice($providerInvoiceId, 'uuid_cancelled', InvoiceStatus::Canceled);
    }

    public function downloadPdf(string $providerInvoiceId): string
    {
        return 'pdf';
    }

    public function downloadXml(string $providerInvoiceId): string
    {
        return 'xml';
    }

    public function sendByEmail(string $providerInvoiceId, string $email): void
    {
    }
}

function task9_webhook_signature(string $rawBody, string $secret, bool $withPrefix = true): string
{
    $signature = hash_hmac('sha256', $rawBody, $secret);

    return $withPrefix ? 'sha256=' . $signature : $signature;
}

function task9_webhook_registry(InvoiceProviderInterface $provider): InvoiceProviderRegistry
{
    return new InvoiceProviderRegistry([
        'facturapi' => ['driver' => 'facturapi', 'factory' => static fn (): InvoiceProviderInterface => $provider],
    ]);
}

test('FacturapiWebhookSignature fails closed for invalid signatures and accepts supported formats', function (): void {
    $rawBody = '{"id":"evt_sig","type":"invoice.status_updated"}';
    $secret = 'whsec_task9';

    assert_throws(InvoiceProviderException::class, function () use ($rawBody, $secret): void {
        FacturapiWebhookSignature::assertValid($rawBody, 'sha256=invalid', $secret);
    });

    FacturapiWebhookSignature::assertValid($rawBody, task9_webhook_signature($rawBody, $secret), $secret);
    FacturapiWebhookSignature::assertValid($rawBody, task9_webhook_signature($rawBody, $secret, withPrefix: false), $secret);
});

test('FacturapiInvoiceProvider parseWebhook validates signature and keeps only safe invoice event fields', function (): void {
    $secret = 'whsec_task9';
    $rawBody = json_encode([
        'id' => 'evt_safe',
        'type' => 'invoice.status_updated',
        'data' => [
            'object' => [
                'id' => 'inv_safe',
                'status' => 'valid',
                'customer' => ['tax_id' => 'ACM010101ABC', 'legal_name' => 'ACME SA DE CV'],
                'items' => [['description' => 'Servicio mensual']],
                'pdf_url' => 'https://example.test/invoice.pdf',
                'xml_url' => 'https://example.test/invoice.xml',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $provider = new FacturapiInvoiceProvider(new Task9WebhookTransport(), webhookSecret: $secret);
    $event = $provider->parseWebhook($rawBody, task9_webhook_signature($rawBody, $secret));

    assert_same('evt_safe', $event->providerEventId());
    assert_same('invoice.status_updated', $event->type());
    assert_same('inv_safe', $event->providerInvoiceId());
    assert_same('valid', $event->status());
    assert_false(isset($event->meta()['customer']), 'customer payload must not be retained');
    assert_false(isset($event->meta()['items']), 'line items must not be retained');
    assert_false(isset($event->meta()['tax_id']), 'RFC/tax id must not be retained');
    assert_false(isset($event->meta()['pdf_url']), 'PDF URL must not be retained');
    assert_false(isset($event->meta()['xml_url']), 'XML URL must not be retained');
});

test('ApplyInvoiceProviderEvent applies a valid webhook over a pending issued row with safe metadata', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'issue:pending', 'order:task9:webhook', 'invoice'));
    $events->markIssued('facturapi', 'issue:pending', new IssuedInvoice(
        'inv_async',
        'uuid_pending',
        InvoiceStatus::Pending,
        meta: ['provider_status' => 'pending'],
    ));

    $provider = new Task9WebhookProvider();
    $provider->retrieveResponses['inv_async'] = new IssuedInvoice(
        'inv_async',
        'uuid_valid',
        InvoiceStatus::Valid,
        meta: ['provider_status' => 'valid'],
    );
    $useCase = new ApplyInvoiceProviderEvent($events, task9_webhook_registry($provider), 'facturapi');

    $result = $useCase->handle(new InvoiceProviderEvent(
        providerEventId: 'evt_valid',
        type: 'invoice.status_updated',
        providerInvoiceId: 'inv_async',
        status: 'valid',
        meta: ['tax_id' => 'ACM010101ABC', 'items' => [['description' => 'Servicio mensual']]],
    ));

    assert_same(InvoiceStatus::Valid, $result?->status());
    assert_same('valid', $events->findByIdempotencyKey('facturapi', 'issue:pending')?->meta()['provider_status'] ?? null);
    $webhookClaim = $events->findClaimByIdempotencyKey('facturapi', 'webhook:evt_valid');
    assert_same('webhook', $webhookClaim?->type());
    assert_false(isset($webhookClaim?->meta()['tax_id']), 'webhook claim meta must exclude RFC/tax id');
    assert_false(isset($webhookClaim?->meta()['items']), 'webhook claim meta must exclude line items');
});

test('ApplyInvoiceProviderEvent ignores replay of the same provider event id', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'issue:replay', 'order:task9:webhook-replay', 'invoice'));
    $events->markIssued('facturapi', 'issue:replay', new IssuedInvoice(
        'inv_replay',
        'uuid_pending',
        InvoiceStatus::Pending,
        meta: ['provider_status' => 'pending'],
    ));

    $provider = new Task9WebhookProvider();
    $provider->retrieveResponses['inv_replay'] = new IssuedInvoice(
        'inv_replay',
        'uuid_valid',
        InvoiceStatus::Valid,
        meta: ['provider_status' => 'valid'],
    );
    $useCase = new ApplyInvoiceProviderEvent($events, task9_webhook_registry($provider), 'facturapi');
    $event = new InvoiceProviderEvent('evt_replay', 'invoice.status_updated', 'inv_replay', 'valid');

    $first = $useCase->handle($event);
    $second = $useCase->handle($event);

    assert_same(InvoiceStatus::Valid, $first?->status());
    assert_null($second, 'replayed webhook should not apply a second time');
    assert_same(1, $provider->retrieveCalls, 'replayed webhook must not retrieve or mark again');
});

test('ApplyInvoiceProviderEvent maps canceled webhook status to a canceled ledger row', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'issue:cancel-webhook', 'order:task9:webhook-cancel', 'invoice'));
    $events->markIssued('facturapi', 'issue:cancel-webhook', new IssuedInvoice(
        'inv_cancel_webhook',
        'uuid_valid',
        InvoiceStatus::Valid,
        meta: ['provider_status' => 'valid'],
    ));

    $provider = new Task9WebhookProvider();
    $provider->retrieveResponses['inv_cancel_webhook'] = new IssuedInvoice(
        'inv_cancel_webhook',
        'uuid_valid',
        InvoiceStatus::Valid,
        meta: ['provider_status' => 'valid'],
    );
    $useCase = new ApplyInvoiceProviderEvent($events, task9_webhook_registry($provider), 'facturapi');

    $result = $useCase->handle(new InvoiceProviderEvent(
        'evt_canceled',
        'invoice.status_updated',
        'inv_cancel_webhook',
        'canceled',
    ));

    assert_same(InvoiceStatus::Canceled, $result?->status());
    assert_same('canceled', $result?->meta()['provider_status'] ?? null);
});
