<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\IssueInvoiceFromSource;
use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAlreadyProcessed;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceDraftInvalid;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNeedsReconcile;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\PaymentForm;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Address;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;

require_once __DIR__ . '/Support/InMemoryInvoiceEventLog.php';

function task9_valid_customer(
    string $taxId = 'ACM010101ABC',
    string $legalName = 'ACME SA DE CV',
    string $zip = '01000',
): FiscalCustomer {
    return new FiscalCustomer(
        legalName: $legalName,
        taxId: $taxId,
        taxSystem: '601',
        address: new Address(zip: $zip),
    );
}

/** @param InvoiceTax[]|null $taxes */
function task9_valid_item(
    float $quantity = 1.0,
    string $productKey = '80101500',
    ?array $taxes = null,
    bool $taxExempt = false,
): InvoiceItem {
    return new InvoiceItem(
        quantity: $quantity,
        description: 'Servicio mensual',
        productKey: $productKey,
        unitPrice: Money::fromMinor(100000, 'MXN'),
        taxes: $taxes ?? [new InvoiceTax(type: 'IVA', rate: 0.16, factor: 'Tasa')],
        taxExempt: $taxExempt,
    );
}

/** @param InvoiceItem[]|null $items */
function task9_valid_draft(
    ?FiscalCustomer $customer = null,
    ?array $items = null,
    string $currency = 'MXN',
): InvoiceDraft {
    return new InvoiceDraft(
        sourceRef: 'order:task9',
        customer: $customer ?? task9_valid_customer(),
        items: $items ?? [task9_valid_item()],
        paymentForm: PaymentForm::Transferencia,
        cfdiUse: CfdiUse::G01,
        currency: $currency,
    );
}

function task9_source(?InvoiceDraft $draft): InvoiceableSourceInterface
{
    return new class($draft) implements InvoiceableSourceInterface {
        public int $findCalls = 0;

        public function __construct(private readonly ?InvoiceDraft $draft)
        {
        }

        public function findDraft(string $sourceRef): ?InvoiceDraft
        {
            $this->findCalls++;

            return $this->draft;
        }
    };
}

function task9_provider(
    ?IssuedInvoice $invoice = null,
    ?Throwable $createFailure = null,
): InvoiceProviderInterface {
    return new class($invoice, $createFailure) implements InvoiceProviderInterface {
        public int $createCalls = 0;

        public function __construct(
            private readonly ?IssuedInvoice $invoice,
            public ?Throwable $createFailure,
        ) {
        }

        public function key(): string
        {
            return 'facturapi';
        }

        public function createInvoice(InvoiceDraft $draft): IssuedInvoice
        {
            $this->createCalls++;
            if ($this->createFailure !== null) {
                throw $this->createFailure;
            }

            return $this->invoice ?? new IssuedInvoice(
                providerInvoiceId: 'inv_task9',
                uuid: 'uuid_task9',
                status: InvoiceStatus::Valid,
                sourceRef: $draft->sourceRef(),
            );
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
    };
}

function task9_registry(InvoiceProviderInterface $provider, string $key = 'facturapi'): InvoiceProviderRegistry
{
    return new InvoiceProviderRegistry([
        $key => ['driver' => 'facturapi', 'factory' => static fn (): InvoiceProviderInterface => $provider],
    ]);
}

function task9_failing_mark_needs_reconcile_log(InMemoryInvoiceEventLog $inner): InvoiceEventLogRepositoryInterface
{
    return new class($inner) implements InvoiceEventLogRepositoryInterface {
        public int $releaseCalls = 0;
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
            $this->releaseCalls++;
            $this->inner->releaseClaim($provider, $idempotencyKey);
        }

        public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            throw new RuntimeException('simulated markIssued failure');
        }

        public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->markNeedsReconcileCalls++;

            throw new RuntimeException('simulated markNeedsReconcile failure');
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
    };
}

function task9_failing_mark_issued_log(InMemoryInvoiceEventLog $inner): InvoiceEventLogRepositoryInterface
{
    return new class($inner) implements InvoiceEventLogRepositoryInterface {
        public int $releaseCalls = 0;
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
            $this->releaseCalls++;
            $this->inner->releaseClaim($provider, $idempotencyKey);
        }

        public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->markIssuedCalls++;

            throw new RuntimeException('simulated markIssued failure');
        }

        public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->markNeedsReconcileCalls++;
            $this->inner->markNeedsReconcile($provider, $idempotencyKey, $invoice);
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
    };
}

test('InvoiceDraftValidator rechaza drafts fiscales inválidos', function (): void {
    $validator = new InvoiceDraftValidator();

    $invalidDrafts = [
        task9_valid_draft(customer: task9_valid_customer(taxId: 'BAD')),
        task9_valid_draft(customer: task9_valid_customer(zip: '1234')),
        task9_valid_draft(customer: task9_valid_customer(legalName: '  ')),
        task9_valid_draft(items: []),
        task9_valid_draft(items: [task9_valid_item(quantity: 0.0)]),
        task9_valid_draft(items: [task9_valid_item(productKey: 'bad')]),
        task9_valid_draft(currency: 'USD'),
        task9_valid_draft(items: [task9_valid_item(taxes: [])]),
    ];

    foreach ($invalidDrafts as $draft) {
        assert_throws(InvoiceDraftInvalid::class, fn () => $validator->validate($draft));
    }
});

test('InvoiceDraftValidator permite item exento sin impuestos', function (): void {
    $validator = new InvoiceDraftValidator();

    $validator->validate(task9_valid_draft(items: [task9_valid_item(taxes: [], taxExempt: true)]));

    assert_true(true);
});

test('IssueInvoiceFromSource emite y marca issued en camino feliz', function (): void {
    $events = new InMemoryInvoiceEventLog();
    $source = task9_source(task9_valid_draft());
    $provider = task9_provider();
    $useCase = new IssueInvoiceFromSource(
        source: $source,
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    $issued = $useCase->handle('order:task9', 'idem:happy');

    assert_same('inv_task9', $issued->providerInvoiceId());
    assert_same(1, $provider->createCalls);
    assert_same('inv_task9', $events->findByIdempotencyKey('facturapi', 'idem:happy')?->providerInvoiceId());
});

test('IssueInvoiceFromSource rechaza claim existente sin provider id', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:claimed', 'order:task9', 'invoice'));
    $source = task9_source(task9_valid_draft());
    $provider = task9_provider();
    $useCase = new IssueInvoiceFromSource(
        source: $source,
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    assert_throws(InvoiceAlreadyProcessed::class, fn () => $useCase->handle('order:task9', 'idem:claimed'));
    assert_same(0, $provider->createCalls);
    assert_same(0, $source->findCalls);
});

test('IssueInvoiceFromSource devuelve replay issued sin crear otra factura', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:issued', 'order:task9', 'invoice'));
    $events->markIssued('facturapi', 'idem:issued', new IssuedInvoice(
        providerInvoiceId: 'inv_existing',
        uuid: 'uuid_existing',
        status: InvoiceStatus::Valid,
        sourceRef: 'order:task9',
    ));
    $provider = task9_provider();
    $useCase = new IssueInvoiceFromSource(
        source: task9_source(task9_valid_draft()),
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    $issued = $useCase->handle('order:task9', 'idem:issued');

    assert_same('inv_existing', $issued->providerInvoiceId());
    assert_same(0, $provider->createCalls);
});

test('IssueInvoiceFromSource libera claim si createInvoice falla sin provider id', function (): void {
    $events = new InMemoryInvoiceEventLog();
    $provider = task9_provider(createFailure: new RuntimeException('remote down'));
    $useCase = new IssueInvoiceFromSource(
        source: task9_source(task9_valid_draft()),
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    assert_throws(RuntimeException::class, fn () => $useCase->handle('order:task9', 'idem:create-fails'));

    $provider->createFailure = null;
    $issued = $useCase->handle('order:task9', 'idem:create-fails');
    assert_same('inv_task9', $issued->providerInvoiceId());
    assert_same(2, $provider->createCalls);
});

test('IssueInvoiceFromSource marca needs_reconcile si markIssued falla tras create y replay no recrea', function (): void {
    $inner = new InMemoryInvoiceEventLog();
    $events = task9_failing_mark_issued_log($inner);
    $provider = task9_provider(new IssuedInvoice(
        providerInvoiceId: 'inv_remote_observed',
        uuid: 'uuid_remote_observed',
        status: InvoiceStatus::Valid,
        sourceRef: 'order:task9',
    ));
    $useCase = new IssueInvoiceFromSource(
        source: task9_source(task9_valid_draft()),
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    assert_throws(InvoiceNeedsReconcile::class, fn () => $useCase->handle('order:task9', 'idem:partial'));

    assert_same(1, $provider->createCalls);
    assert_same(1, $events->markIssuedCalls);
    assert_same(1, $events->markNeedsReconcileCalls);
    assert_same(0, $events->releaseCalls);

    $replayed = $useCase->handle('order:task9', 'idem:partial');

    assert_same('inv_remote_observed', $replayed->providerInvoiceId());
    assert_same(InvoiceStatus::NeedsReconcile, $replayed->status());
    assert_same(1, $provider->createCalls);
});

test('IssueInvoiceFromSource lanza InvoiceNeedsReconcile aunque markNeedsReconcile falle tras create', function (): void {
    $inner = new InMemoryInvoiceEventLog();
    $events = task9_failing_mark_needs_reconcile_log($inner);
    $provider = task9_provider(new IssuedInvoice(
        providerInvoiceId: 'inv_reconcile_fail',
        uuid: 'uuid_reconcile_fail',
        status: InvoiceStatus::Valid,
        sourceRef: 'order:task9',
    ));
    $useCase = new IssueInvoiceFromSource(
        source: task9_source(task9_valid_draft()),
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    $thrown = null;
    try {
        $useCase->handle('order:task9', 'idem:reconcile-fails');
    } catch (InvoiceNeedsReconcile $e) {
        $thrown = $e;
    }

    assert_true($thrown instanceof InvoiceNeedsReconcile);
    assert_true(str_contains($thrown->getMessage(), 'inv_reconcile_fail'));
    assert_same(1, $provider->createCalls);
    assert_same(1, $events->markNeedsReconcileCalls);
    assert_same(0, $events->releaseCalls);
});
