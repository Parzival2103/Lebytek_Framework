<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\IssueInvoiceFromSource;
use Lebytek\Framework\Application\Invoicing\ReconcileIssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNeedsReconcile;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\PaymentForm;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Address;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;
use Lebytek\Framework\Infrastructure\Invoicing\Facturapi\FacturapiTransportInterface;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider;

require_once __DIR__ . '/Support/InMemoryInvoiceEventLog.php';

final class Task12InvoiceSource implements InvoiceableSourceInterface
{
    /** @param array<string, InvoiceDraft> $drafts */
    public function __construct(private readonly array $drafts)
    {
    }

    public function findDraft(string $sourceRef): ?InvoiceDraft
    {
        return $this->drafts[$sourceRef] ?? null;
    }
}

final class Task12FakeFacturapiTransport implements FacturapiTransportInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $createdPayloads = [];

    /** @param array<string, mixed> $payload */
    public function create(array $payload): array
    {
        $this->createdPayloads[] = $payload;
        $sequence = count($this->createdPayloads);

        return [
            'id' => 'fact_smoke_' . $sequence,
            'uuid' => 'uuid-smoke-' . $sequence,
            'status' => 'valid',
            'folio_number' => 1200 + $sequence,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function cancel(string $providerInvoiceId, array $payload): array
    {
        throw new RuntimeException('cancel is outside this smoke');
    }

    public function pdf(string $providerInvoiceId): string
    {
        throw new RuntimeException('pdf download is outside this smoke');
    }

    public function xml(string $providerInvoiceId): string
    {
        throw new RuntimeException('xml download is outside this smoke');
    }

    public function email(string $providerInvoiceId, string $email): array
    {
        throw new RuntimeException('email send is outside this smoke');
    }
}

final class Task12FailFirstMarkIssuedEventLog implements InvoiceEventLogRepositoryInterface
{
    public int $markIssuedCalls = 0;
    public int $markNeedsReconcileCalls = 0;

    public function __construct(private readonly InMemoryInvoiceEventLog $inner)
    {
    }

    public function hasProcessed(string $provider, string $idempotencyKey): bool
    {
        return $this->inner->hasProcessed($provider, $idempotencyKey);
    }

    public function tryClaim(
        string $provider,
        string $idempotencyKey,
        string $sourceRef,
        string $type,
        array $meta = [],
    ): bool {
        return $this->inner->tryClaim($provider, $idempotencyKey, $sourceRef, $type, $meta);
    }

    public function releaseClaim(string $provider, string $idempotencyKey): void
    {
        $this->inner->releaseClaim($provider, $idempotencyKey);
    }

    public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
        $this->markIssuedCalls++;
        if ($this->markIssuedCalls === 1) {
            throw new RuntimeException('simulated markIssued failure');
        }

        $this->inner->markIssued($provider, $idempotencyKey, $invoice);
    }

    public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
        $this->markNeedsReconcileCalls++;
        $this->inner->markNeedsReconcile($provider, $idempotencyKey, $invoice);
    }

    public function attachProviderInvoiceId(
        string $provider,
        string $idempotencyKey,
        string $providerInvoiceId,
        array $meta = [],
    ): void {
        $this->inner->attachProviderInvoiceId($provider, $idempotencyKey, $providerInvoiceId, $meta);
    }

    public function findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice
    {
        return $this->inner->findByIdempotencyKey($provider, $idempotencyKey);
    }

    public function findIssuedBySourceRef(string $sourceRef): array
    {
        return $this->inner->findIssuedBySourceRef($sourceRef);
    }

    public function findNeedsReconcile(string $provider, int $limit = 100): array
    {
        return $this->inner->findNeedsReconcile($provider, $limit);
    }
}

function task12_draft(string $sourceRef): InvoiceDraft
{
    return new InvoiceDraft(
        sourceRef: $sourceRef,
        customer: new FiscalCustomer(
            legalName: 'ACME SA DE CV',
            taxId: 'ACM010101ABC',
            taxSystem: '601',
            address: new Address(zip: '01000', country: 'MEX'),
            email: 'billing@example.test',
        ),
        items: [
            new InvoiceItem(
                quantity: 1.0,
                description: 'Servicio de plataforma',
                productKey: '81112100',
                unitPrice: Money::fromMinor(150000, 'MXN'),
                taxes: [new InvoiceTax(type: 'IVA', rate: 0.16, factor: 'Tasa')],
                unitKey: 'E48',
            ),
        ],
        paymentForm: PaymentForm::Transferencia,
        cfdiUse: CfdiUse::G03,
    );
}

function task12_registry(InvoiceProviderInterface $provider): InvoiceProviderRegistry
{
    return new InvoiceProviderRegistry([
        'facturapi' => ['driver' => 'facturapi', 'factory' => static fn (): InvoiceProviderInterface => $provider],
    ]);
}

function task12_issue_use_case(
    InvoiceEventLogRepositoryInterface $events,
    InvoiceProviderInterface $provider,
    InvoiceableSourceInterface $source,
): IssueInvoiceFromSource {
    return new IssueInvoiceFromSource(
        source: $source,
        events: $events,
        registry: task12_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );
}

function task12_reconcile_use_case(
    InvoiceEventLogRepositoryInterface $events,
    InvoiceProviderInterface $provider,
): ReconcileIssuedInvoice {
    return new ReconcileIssuedInvoice(
        events: $events,
        registry: task12_registry($provider),
        defaultProviderKey: 'facturapi',
    );
}

test('Invoicing smoke emite con Facturapi fake transport y queda issued', function (): void {
    $events = new InMemoryInvoiceEventLog();
    $transport = new Task12FakeFacturapiTransport();
    $provider = new FacturapiInvoiceProvider($transport);
    $sourceRef = 'order:task12:happy';
    $issue = task12_issue_use_case($events, $provider, new Task12InvoiceSource([
        $sourceRef => task12_draft($sourceRef),
    ]));

    $issued = $issue->handle($sourceRef, 'idem:task12:happy');

    assert_same('fact_smoke_1', $issued->providerInvoiceId());
    assert_same(InvoiceStatus::Valid, $issued->status());
    assert_same(1, count($transport->createdPayloads));
    assert_same(InvoiceStatus::Valid, $events->findByIdempotencyKey('facturapi', 'idem:task12:happy')?->status());
});

test('Invoicing smoke reconcilia markIssued fallido y replay no crea otra factura', function (): void {
    $inner = new InMemoryInvoiceEventLog();
    $events = new Task12FailFirstMarkIssuedEventLog($inner);
    $transport = new Task12FakeFacturapiTransport();
    $provider = new FacturapiInvoiceProvider($transport);
    $sourceRef = 'order:task12:reconcile';
    $source = new Task12InvoiceSource([$sourceRef => task12_draft($sourceRef)]);
    $issue = task12_issue_use_case($events, $provider, $source);
    $reconcile = task12_reconcile_use_case($events, $provider);

    assert_throws(
        InvoiceNeedsReconcile::class,
        fn () => $issue->handle($sourceRef, 'idem:task12:reconcile'),
    );
    assert_same(1, count($transport->createdPayloads));
    assert_same(InvoiceStatus::NeedsReconcile, $inner->findByIdempotencyKey('facturapi', 'idem:task12:reconcile')?->status());

    $reconciled = $reconcile->handle('idem:task12:reconcile');
    $replayed = $issue->handle($sourceRef, 'idem:task12:reconcile');

    assert_same('fact_smoke_1', $reconciled->providerInvoiceId());
    assert_same(InvoiceStatus::Valid, $reconciled->status());
    assert_same('fact_smoke_1', $replayed->providerInvoiceId());
    assert_same(InvoiceStatus::Valid, $replayed->status());
    assert_same(1, count($transport->createdPayloads));
    assert_same(2, $events->markIssuedCalls);
    assert_same(1, $events->markNeedsReconcileCalls);
});
