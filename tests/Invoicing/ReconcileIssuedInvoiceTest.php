<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\ReconcileIssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

require_once __DIR__ . '/Support/InMemoryInvoiceEventLog.php';

function task10_provider(): InvoiceProviderInterface
{
    return new class implements InvoiceProviderInterface {
        public int $createCalls = 0;

        public function key(): string
        {
            return 'facturapi';
        }

        public function createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice
        {
            $this->createCalls++;

            return new IssuedInvoice('inv_unexpected', 'uuid_unexpected', InvoiceStatus::Valid, sourceRef: $draft->sourceRef());
        }

        public function externalIdForIssue(string $idempotencyKey): string
        {
            return 'fake-external:' . $idempotencyKey;
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

function task10_registry(InvoiceProviderInterface $provider): InvoiceProviderRegistry
{
    return new InvoiceProviderRegistry([
        'facturapi' => ['driver' => 'facturapi', 'factory' => static fn (): InvoiceProviderInterface => $provider],
    ]);
}

function task10_use_case(
    InvoiceEventLogRepositoryInterface $events,
    InvoiceProviderInterface $provider,
): ReconcileIssuedInvoice {
    return new ReconcileIssuedInvoice(
        events: $events,
        registry: task10_registry($provider),
        defaultProviderKey: 'facturapi',
    );
}

test('ReconcileIssuedInvoice promueve needs_reconcile a issued sin crear factura', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:partial', 'order:task10', 'invoice'));
    $events->markNeedsReconcile('facturapi', 'idem:partial', new IssuedInvoice(
        providerInvoiceId: 'inv_remote_observed',
        uuid: 'uuid_remote_observed',
        status: InvoiceStatus::NeedsReconcile,
        sourceRef: 'order:task10',
    ));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    $issued = $useCase->handle('idem:partial');

    assert_same('inv_remote_observed', $issued->providerInvoiceId());
    assert_same(InvoiceStatus::Valid, $issued->status());
    assert_same(0, $provider->createCalls);
    assert_same(InvoiceStatus::Valid, $events->findByIdempotencyKey('facturapi', 'idem:partial')?->status());
});

test('ReconcileIssuedInvoice devuelve facturas ya finalizadas as-is', function (): void {
    $invoice = new IssuedInvoice(
        providerInvoiceId: 'inv_cancelled',
        uuid: 'uuid_cancelled',
        status: InvoiceStatus::Canceled,
        sourceRef: 'order:task10',
    );
    $events = new class($invoice) implements InvoiceEventLogRepositoryInterface {
        public int $markIssuedCalls = 0;

        public function __construct(private readonly IssuedInvoice $invoice)
        {
        }

        public function hasProcessed(string $provider, string $idempotencyKey): bool
        {
            return true;
        }

        public function tryClaim(
            string $provider,
            string $idempotencyKey,
            string $sourceRef,
            string $type,
            array $meta = [],
        ): bool {
            return false;
        }

        public function releaseClaim(string $provider, string $idempotencyKey): void
        {
        }

        public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
            $this->markIssuedCalls++;

            throw new RuntimeException('finalized invoice must not be promoted');
        }

        public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
        {
        }

        public function attachProviderInvoiceId(
            string $provider,
            string $idempotencyKey,
            string $providerInvoiceId,
            array $meta = [],
        ): void {
            throw new RuntimeException('finalized invoice must not be attached');
        }

        public function findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice
        {
            return $this->invoice;
        }

        public function findIssuedBySourceRef(string $sourceRef): array
        {
            return [];
        }

        public function findNeedsReconcile(string $provider, int $limit = 100): array
        {
            return [];
        }
    };
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    $result = $useCase->handle('idem:cancelled');

    assert_same($invoice, $result);
    assert_same(0, $events->markIssuedCalls);
    assert_same(0, $provider->createCalls);
});

test('ReconcileIssuedInvoice lista needs_reconcile por provider y limite', function (): void {
    $events = new InMemoryInvoiceEventLog();
    foreach (['a', 'b'] as $suffix) {
        assert_true($events->tryClaim('facturapi', 'idem:'.$suffix, 'order:task10:'.$suffix, 'invoice'));
        $events->markNeedsReconcile('facturapi', 'idem:'.$suffix, new IssuedInvoice(
            providerInvoiceId: 'inv_'.$suffix,
            uuid: 'uuid_'.$suffix,
            status: InvoiceStatus::NeedsReconcile,
            sourceRef: 'order:task10:'.$suffix,
        ));
    }
    assert_true($events->tryClaim('other', 'idem:other', 'order:task10:other', 'invoice'));
    $events->markNeedsReconcile('other', 'idem:other', new IssuedInvoice(
        providerInvoiceId: 'inv_other',
        uuid: 'uuid_other',
        status: InvoiceStatus::NeedsReconcile,
        sourceRef: 'order:task10:other',
    ));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    $pending = $useCase->listNeedsReconcile('facturapi', 1);

    assert_same(1, count($pending));
    assert_same('inv_a', $pending[0]->providerInvoiceId());
    assert_same(0, $provider->createCalls);
});

test('ReconcileIssuedInvoice reporta InvoiceSourceNotFound cuando el ledger no expone factura reconciliable', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:claimed-only', 'order:task10', 'invoice'));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    assert_throws(
        InvoiceSourceNotFound::class,
        fn () => $useCase->handle('idem:claimed-only'),
        'claimed-only rows are indistinguishable from absent rows through the current port',
    );
    assert_same(0, $provider->createCalls);
});
