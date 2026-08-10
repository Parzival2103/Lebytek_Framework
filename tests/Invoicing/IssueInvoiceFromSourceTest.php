<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\IssueInvoiceFromSource;
use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAlreadyProcessed;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAmbiguousCreate;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceDraftInvalid;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNeedsReconcile;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
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
        /** @var list<string> */
        public array $createIdempotencyKeys = [];
        /** @var list<array{key: string, external_id: string}> */
        public array $externalIdCalls = [];

        public function __construct(
            private readonly ?IssuedInvoice $invoice,
            public ?Throwable $createFailure,
        ) {
        }

        public function key(): string
        {
            return 'facturapi';
        }

        public function createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice
        {
            $this->createCalls++;
            $this->createIdempotencyKeys[] = $idempotencyKey;
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

        public function externalIdForIssue(string $idempotencyKey): string
        {
            $externalId = 'fake-external:' . $idempotencyKey;
            $this->externalIdCalls[] = ['key' => $idempotencyKey, 'external_id' => $externalId];

            return $externalId;
        }

        public function retrieveInvoice(string $providerInvoiceId): IssuedInvoice
        {
            return new IssuedInvoice($providerInvoiceId, 'uuid_task9', InvoiceStatus::Valid);
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
        public int $attachCalls = 0;
        /** @var list<array{provider: string, idempotencyKey: string, providerInvoiceId: string, meta: array<string, mixed>}> */
        public array $attached = [];

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

        public function markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->inner->markCanceled($provider, $idempotencyKey, $invoice);
        }

        public function attachProviderInvoiceId(
            string $provider,
            string $idempotencyKey,
            string $providerInvoiceId,
            array $meta = [],
        ): void {
            $this->attachCalls++;
            $this->attached[] = [
                'provider' => $provider,
                'idempotencyKey' => $idempotencyKey,
                'providerInvoiceId' => $providerInvoiceId,
                'meta' => $meta,
            ];
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

        public function findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow
        {
            return $this->inner->findClaimByIdempotencyKey($provider, $idempotencyKey);
        }

        public function findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow
        {
            return $this->inner->findIssueByProviderInvoiceId($provider, $providerInvoiceId);
        }

        public function findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): array
        {
            return $this->inner->findOrphanClaims($provider, $minAgeSeconds, $limit);
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

        public function markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->inner->markCanceled($provider, $idempotencyKey, $invoice);
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

        public function findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow
        {
            return $this->inner->findClaimByIdempotencyKey($provider, $idempotencyKey);
        }

        public function findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow
        {
            return $this->inner->findIssueByProviderInvoiceId($provider, $providerInvoiceId);
        }

        public function findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): array
        {
            return $this->inner->findOrphanClaims($provider, $minAgeSeconds, $limit);
        }
    };
}

function task9_spy_event_log(InMemoryInvoiceEventLog $inner): InvoiceEventLogRepositoryInterface
{
    return new class($inner) implements InvoiceEventLogRepositoryInterface {
        public int $releaseCalls = 0;
        /** @var list<array{provider: string, idempotencyKey: string, sourceRef: string, type: string, meta: array<string, mixed>}> */
        public array $claims = [];

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
            $claimed = $this->inner->tryClaim($provider, $idempotencyKey, $sourceRef, $type, $meta);
            if ($claimed) {
                $this->claims[] = [
                    'provider' => $provider,
                    'idempotencyKey' => $idempotencyKey,
                    'sourceRef' => $sourceRef,
                    'type' => $type,
                    'meta' => $meta,
                ];
            }

            return $claimed;
        }

        public function releaseClaim(string $provider, string $idempotencyKey): void
        {
            $this->releaseCalls++;
            $this->inner->releaseClaim($provider, $idempotencyKey);
        }

        public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->inner->markIssued($provider, $idempotencyKey, $invoice);
        }

        public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->inner->markNeedsReconcile($provider, $idempotencyKey, $invoice);
        }

        public function markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->inner->markCanceled($provider, $idempotencyKey, $invoice);
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

        public function findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow
        {
            return $this->inner->findClaimByIdempotencyKey($provider, $idempotencyKey);
        }

        public function findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow
        {
            return $this->inner->findIssueByProviderInvoiceId($provider, $providerInvoiceId);
        }

        public function findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): array
        {
            return $this->inner->findOrphanClaims($provider, $minAgeSeconds, $limit);
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

test('IssueInvoiceFromSource libera claim si falla antes de invocar createInvoice', function (): void {
    $inner = new InMemoryInvoiceEventLog();
    $events = task9_spy_event_log($inner);
    $provider = task9_provider();
    $useCase = new IssueInvoiceFromSource(
        source: task9_source(null),
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    assert_throws(InvoiceSourceNotFound::class, fn () => $useCase->handle('order:task9', 'idem:source-missing'));
    assert_same(1, $events->releaseCalls);
    assert_same(0, $provider->createCalls);
});

test('IssueInvoiceFromSource conserva claim si createInvoice queda ambiguo y replay no recrea', function (): void {
    $inner = new InMemoryInvoiceEventLog();
    $events = task9_spy_event_log($inner);
    $provider = task9_provider(createFailure: new RuntimeException('timeout after remote stamp'));
    $useCase = new IssueInvoiceFromSource(
        source: task9_source(task9_valid_draft()),
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    $firstThrown = null;
    try {
        $useCase->handle('order:task9', 'idem:ambiguous');
    } catch (Throwable $e) {
        $firstThrown = $e;
    }

    assert_same(0, $events->releaseCalls, 'ambiguous create must keep the claim');
    assert_true($firstThrown instanceof InvoiceAmbiguousCreate, 'ambiguous create must throw typed exception');
    assert_same(1, $provider->createCalls);
    assert_same('idem:ambiguous', $provider->createIdempotencyKeys[0] ?? null);
    assert_same('fake-external:idem:ambiguous', $events->claims[0]['meta']['external_id'] ?? null);

    $provider->createFailure = null;
    assert_throws(InvoiceAlreadyProcessed::class, fn () => $useCase->handle('order:task9', 'idem:ambiguous'));
    assert_same(1, $provider->createCalls, 'claimed-without-id replay must not call create again');
});

test('IssueInvoiceFromSource persiste external_id por idempotencyKey aunque sourceRef sea el mismo', function (): void {
    $inner = new InMemoryInvoiceEventLog();
    $events = task9_spy_event_log($inner);
    $provider = task9_provider();
    $useCase = new IssueInvoiceFromSource(
        source: task9_source(task9_valid_draft()),
        events: $events,
        registry: task9_registry($provider),
        validator: new InvoiceDraftValidator(),
        defaultProviderKey: 'facturapi',
    );

    $useCase->handle('order:task9', 'idem:first');
    $useCase->handle('order:task9', 'idem:second');

    assert_same(['idem:first', 'idem:second'], $provider->createIdempotencyKeys);
    assert_same('fake-external:idem:first', $events->claims[0]['meta']['external_id'] ?? null);
    assert_same('fake-external:idem:second', $events->claims[1]['meta']['external_id'] ?? null);
    assert_true(
        ($events->claims[0]['meta']['external_id'] ?? null) !== ($events->claims[1]['meta']['external_id'] ?? null),
        'same sourceRef with distinct idempotency keys must produce distinct external_id values',
    );
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
    assert_same('inv_reconcile_fail', $thrown->providerInvoiceId());
    assert_same('facturapi', $thrown->providerKey());
    assert_same('idem:reconcile-fails', $thrown->idempotencyKey());
    assert_same(1, $provider->createCalls);
    assert_same(1, $events->markNeedsReconcileCalls);
    assert_same(1, $events->attachCalls);
    assert_same('inv_reconcile_fail', $events->attached[0]['providerInvoiceId'] ?? null);
    assert_same('fake-external:idem:reconcile-fails', $events->attached[0]['meta']['external_id'] ?? null);
    assert_same('inv_reconcile_fail', $inner->findByIdempotencyKey('facturapi', 'idem:reconcile-fails')?->providerInvoiceId());
    assert_same(InvoiceStatus::NeedsReconcile, $inner->findByIdempotencyKey('facturapi', 'idem:reconcile-fails')?->status());
    assert_same(0, $events->releaseCalls);
});
