<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\CancelIssuedInvoice;
use Lebytek\Framework\Application\Invoicing\DownloadInvoiceDocument;
use Lebytek\Framework\Application\Invoicing\InvoiceIdResolver;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\SendInvoiceByEmail;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAmbiguousSource;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNotCancellable;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

final class Task11Provider implements InvoiceProviderInterface
{
    /** @var list<string> */
    public array $cancelCalls = [];
    /** @var list<string> */
    public array $downloadPdfCalls = [];
    /** @var list<string> */
    public array $downloadXmlCalls = [];
    /** @var list<array{id: string, email: string}> */
    public array $emailCalls = [];
    public ?Throwable $cancelFailure = null;

    public function key(): string
    {
        return 'facturapi';
    }

    public function createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice
    {
        throw new RuntimeException('Task 11 must not create invoices');
    }

    public function externalIdForIssue(string $idempotencyKey): string
    {
        return 'fake-external:' . $idempotencyKey;
    }

    public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice
    {
        $this->cancelCalls[] = $providerInvoiceId;
        if ($this->cancelFailure !== null) {
            throw $this->cancelFailure;
        }

        return task11_invoice($providerInvoiceId, InvoiceStatus::Canceled, sourceRef: null, uuid: 'uuid_cancelled');
    }

    public function downloadPdf(string $providerInvoiceId): string
    {
        $this->downloadPdfCalls[] = $providerInvoiceId;

        return '%PDF-' . $providerInvoiceId;
    }

    public function downloadXml(string $providerInvoiceId): string
    {
        $this->downloadXmlCalls[] = $providerInvoiceId;

        return '<xml id="' . $providerInvoiceId . '"/>';
    }

    public function sendByEmail(string $providerInvoiceId, string $email): void
    {
        $this->emailCalls[] = ['id' => $providerInvoiceId, 'email' => $email];
    }
}

final class Task11EventLog implements InvoiceEventLogRepositoryInterface
{
    /** @var array<string, IssuedInvoice[]> */
    public array $issuedBySourceRef = [];
    /** @var list<string> */
    public array $findSourceCalls = [];
    /** @var list<array{provider: string, idempotencyKey: string, sourceRef: string, type: string, meta: array<string, mixed>}> */
    public array $claims = [];
    public bool $throwOnTryClaim = false;

    public function hasProcessed(string $provider, string $idempotencyKey): bool
    {
        return false;
    }

    public function tryClaim(
        string $provider,
        string $idempotencyKey,
        string $sourceRef,
        string $type,
        array $meta = [],
    ): bool {
        if ($this->throwOnTryClaim) {
            throw new RuntimeException('audit ledger down');
        }

        $this->claims[] = [
            'provider' => $provider,
            'idempotencyKey' => $idempotencyKey,
            'sourceRef' => $sourceRef,
            'type' => $type,
            'meta' => $meta,
        ];

        return true;
    }

    public function releaseClaim(string $provider, string $idempotencyKey): void
    {
    }

    public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
    }

    public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
    }

    public function findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice
    {
        return null;
    }

    public function findIssuedBySourceRef(string $sourceRef): array
    {
        $this->findSourceCalls[] = $sourceRef;

        return $this->issuedBySourceRef[$sourceRef] ?? [];
    }

    public function findNeedsReconcile(string $provider, int $limit = 100): array
    {
        return [];
    }
}

function task11_invoice(
    string $providerInvoiceId,
    InvoiceStatus $status = InvoiceStatus::Valid,
    ?string $sourceRef = 'order:task11',
    string $uuid = 'uuid_task11',
): IssuedInvoice {
    return new IssuedInvoice(
        providerInvoiceId: $providerInvoiceId,
        uuid: $uuid,
        status: $status,
        sourceRef: $sourceRef,
    );
}

function task11_registry(Task11Provider $provider): InvoiceProviderRegistry
{
    return new InvoiceProviderRegistry([
        'facturapi' => ['driver' => 'facturapi', 'factory' => static fn (): InvoiceProviderInterface => $provider],
    ]);
}

function task11_resolver(Task11EventLog $events): InvoiceIdResolver
{
    return new InvoiceIdResolver($events);
}

test('InvoiceIdResolver prefiere provider id directo sin consultar source_ref', function (): void {
    $events = new Task11EventLog();
    $events->issuedBySourceRef['order:ambiguous'] = [
        task11_invoice('inv_a', sourceRef: 'order:ambiguous'),
        task11_invoice('inv_b', sourceRef: 'order:ambiguous'),
    ];

    $resolved = task11_resolver($events)->resolve(' inv_direct ', 'order:ambiguous');

    assert_same('inv_direct', $resolved);
    assert_same([], $events->findSourceCalls);
});

test('InvoiceIdResolver resuelve source_ref unico y falla cerrado con cero o multiples matches', function (): void {
    $events = new Task11EventLog();
    $events->issuedBySourceRef['order:single'] = [task11_invoice('inv_single', sourceRef: 'order:single')];
    $events->issuedBySourceRef['order:ambiguous'] = [
        task11_invoice('inv_one', sourceRef: 'order:ambiguous'),
        task11_invoice('inv_two', sourceRef: 'order:ambiguous'),
    ];
    $resolver = task11_resolver($events);

    assert_same('inv_single', $resolver->resolve(null, 'order:single'));
    assert_throws(InvoiceSourceNotFound::class, fn () => $resolver->resolve(null, 'order:missing'));
    assert_throws(InvoiceAmbiguousSource::class, fn () => $resolver->resolve(null, 'order:ambiguous'));
});

test('CancelIssuedInvoice cancela en proveedor y registra audit claim best-effort', function (): void {
    $events = new Task11EventLog();
    $events->issuedBySourceRef['order:cancel'] = [task11_invoice('inv_cancel', sourceRef: 'order:cancel')];
    $provider = new Task11Provider();
    $useCase = new CancelIssuedInvoice(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        events: $events,
        defaultProviderKey: 'facturapi',
    );

    $canceled = $useCase->handle(new InvoiceCancellation('02'), sourceRef: 'order:cancel');

    assert_same(InvoiceStatus::Canceled, $canceled->status());
    assert_same(['inv_cancel'], $provider->cancelCalls);
    assert_same('cancel:inv_cancel', $events->claims[0]['idempotencyKey'] ?? null);
    assert_same('order:cancel', $events->claims[0]['sourceRef'] ?? null);
    assert_same('cancel', $events->claims[0]['type'] ?? null);
    assert_same('inv_cancel', $events->claims[0]['meta']['providerInvoiceId'] ?? null);

    $events->throwOnTryClaim = true;
    $second = $useCase->handle(new InvoiceCancellation('02'), providerInvoiceId: 'inv_direct');

    assert_same('inv_direct', $second->providerInvoiceId());
    assert_same(['inv_cancel', 'inv_direct'], $provider->cancelCalls);
});

test('CancelIssuedInvoice mapea errores provider not-cancellable al dominio', function (): void {
    $events = new Task11EventLog();
    $provider = new Task11Provider();
    $provider->cancelFailure = new InvoiceProviderException('Invoice is not cancellable');
    $useCase = new CancelIssuedInvoice(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        events: $events,
        defaultProviderKey: 'facturapi',
    );

    assert_throws(
        InvoiceNotCancellable::class,
        fn () => $useCase->handle(new InvoiceCancellation('02'), providerInvoiceId: 'inv_locked'),
    );
    assert_same([], $events->claims);
});

test('DownloadInvoiceDocument delega pdf y xml despues de resolver id', function (): void {
    $events = new Task11EventLog();
    $events->issuedBySourceRef['order:download'] = [task11_invoice('inv_download', sourceRef: 'order:download')];
    $provider = new Task11Provider();
    $useCase = new DownloadInvoiceDocument(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        defaultProviderKey: 'facturapi',
    );

    assert_same('%PDF-inv_download', $useCase->handle('pdf', sourceRef: 'order:download'));
    assert_same('<xml id="inv_direct"/>', $useCase->handle('xml', providerInvoiceId: 'inv_direct'));
    assert_throws(InvalidArgumentException::class, fn () => $useCase->handle('json', providerInvoiceId: 'inv_direct'));
    assert_same(['inv_download'], $provider->downloadPdfCalls);
    assert_same(['inv_direct'], $provider->downloadXmlCalls);
});

test('SendInvoiceByEmail resuelve id y delega email al proveedor', function (): void {
    $events = new Task11EventLog();
    $events->issuedBySourceRef['order:email'] = [task11_invoice('inv_email', sourceRef: 'order:email')];
    $provider = new Task11Provider();
    $useCase = new SendInvoiceByEmail(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        defaultProviderKey: 'facturapi',
    );

    $useCase->handle('billing@example.test', sourceRef: 'order:email');

    assert_same([['id' => 'inv_email', 'email' => 'billing@example.test']], $provider->emailCalls);
});
