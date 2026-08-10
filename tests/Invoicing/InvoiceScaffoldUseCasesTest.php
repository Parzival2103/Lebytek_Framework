<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\CancelIssuedInvoice;
use Lebytek\Framework\Application\Invoicing\DownloadInvoiceDocument;
use Lebytek\Framework\Application\Invoicing\InvoiceIdResolver;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\SendInvoiceByEmail;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAlreadyProcessed;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAmbiguousSource;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceDraftInvalid;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNotCancellable;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

require_once __DIR__ . '/Support/InMemoryInvoiceEventLog.php';

final class Task11Trace
{
    /** @var list<string> */
    public array $events = [];
}

final class Task11Provider implements InvoiceProviderInterface
{
    /** @var list<string> */
    public array $cancelCalls = [];
    /** @var list<string> */
    public array $retrieveCalls = [];
    /** @var list<string> */
    public array $downloadPdfCalls = [];
    /** @var list<string> */
    public array $downloadXmlCalls = [];
    /** @var list<array{id: string, email: string}> */
    public array $emailCalls = [];
    public ?Throwable $cancelFailure = null;
    /** @var array<string, IssuedInvoice> */
    public array $retrievedInvoices = [];

    public function __construct(private readonly ?Task11Trace $trace = null)
    {
    }

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

    public function retrieveInvoice(string $providerInvoiceId): IssuedInvoice
    {
        $this->retrieveCalls[] = $providerInvoiceId;
        if ($this->trace !== null) {
            $this->trace->events[] = 'provider.retrieve:' . $providerInvoiceId;
        }

        return $this->retrievedInvoices[$providerInvoiceId]
            ?? task11_invoice($providerInvoiceId, InvoiceStatus::Valid, sourceRef: null);
    }

    public function listByExternalId(string $externalId): array
    {
        return [];
    }

    public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice
    {
        $this->cancelCalls[] = $providerInvoiceId;
        if ($this->trace !== null) {
            $this->trace->events[] = 'provider.cancel:' . $providerInvoiceId;
        }
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

final class Task11EventLog extends InMemoryInvoiceEventLog implements InvoiceEventLogRepositoryInterface
{
    /** @var array<string, IssuedInvoice[]> */
    public array $issuedBySourceRef = [];
    /** @var list<string> */
    public array $findSourceCalls = [];
    /** @var list<array{provider: string, idempotencyKey: string, sourceRef: string, type: string, meta: array<string, mixed>}> */
    public array $claims = [];
    /** @var list<string> */
    public array $canceledMarks = [];
    public bool $throwOnTryClaim = false;

    public function __construct(private readonly ?Task11Trace $trace = null)
    {
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

        if ($this->trace !== null) {
            $this->trace->events[] = 'events.tryClaim:' . $idempotencyKey;
        }

        $this->claims[] = [
            'provider' => $provider,
            'idempotencyKey' => $idempotencyKey,
            'sourceRef' => $sourceRef,
            'type' => $type,
            'meta' => $meta,
        ];

        return parent::tryClaim($provider, $idempotencyKey, $sourceRef, $type, $meta);
    }

    public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
        if ($this->trace !== null) {
            $this->trace->events[] = 'events.markIssued:' . $idempotencyKey;
        }
        parent::markIssued($provider, $idempotencyKey, $invoice);
    }

    public function markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
        if ($this->trace !== null) {
            $this->trace->events[] = 'events.markCanceled:' . $idempotencyKey;
        }
        $this->canceledMarks[] = $idempotencyKey;
        parent::markCanceled($provider, $idempotencyKey, $invoice);
    }

    public function findIssuedBySourceRef(string $sourceRef): array
    {
        $this->findSourceCalls[] = $sourceRef;

        return $this->issuedBySourceRef[$sourceRef] ?? parent::findIssuedBySourceRef($sourceRef);
    }

    public function findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow
    {
        if ($this->trace !== null) {
            $this->trace->events[] = 'events.findIssue:' . $providerInvoiceId;
        }

        return parent::findIssueByProviderInvoiceId($provider, $providerInvoiceId);
    }

    public function seedIssued(
        string $provider,
        string $idempotencyKey,
        IssuedInvoice $invoice,
        string $type = 'membership',
    ): void {
        parent::tryClaim($provider, $idempotencyKey, $invoice->sourceRef() ?? '', $type);
        parent::markIssued($provider, $idempotencyKey, $invoice);
    }

    public function seedCancelClaim(string $provider, string $providerInvoiceId): void
    {
        parent::tryClaim($provider, 'cancel:' . $providerInvoiceId, '', 'cancel', [
            'providerInvoiceId' => $providerInvoiceId,
        ]);
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

test('InvoiceCancellation valida motivos SAT y sustitucion requerida', function (): void {
    $valid = new InvoiceCancellation(' 02 ');

    assert_same('02', $valid->motive());
    assert_null($valid->substitution());
    assert_throws(InvoiceDraftInvalid::class, fn () => new InvoiceCancellation('05'));
    assert_throws(InvoiceDraftInvalid::class, fn () => new InvoiceCancellation('01'));
    assert_throws(InvoiceDraftInvalid::class, fn () => new InvoiceCancellation('01', '   '));
    assert_same('uuid-substitution', (new InvoiceCancellation('01', ' uuid-substitution '))->substitution());
});

test('CancelIssuedInvoice reclama antes de cancelar y marca la fila de issue como canceled', function (): void {
    $trace = new Task11Trace();
    $events = new Task11EventLog($trace);
    $events->seedIssued('facturapi', 'issue-key-cancel', task11_invoice('inv_cancel', sourceRef: 'order:cancel'));
    $provider = new Task11Provider($trace);
    $useCase = new CancelIssuedInvoice(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        events: $events,
        defaultProviderKey: 'facturapi',
    );

    $canceled = $useCase->handle(new InvoiceCancellation('02'), sourceRef: 'order:cancel');

    assert_same(InvoiceStatus::Canceled, $canceled->status());
    assert_same(['inv_cancel'], $provider->cancelCalls);
    assert_same([
        'events.findIssue:inv_cancel',
        'events.tryClaim:cancel:inv_cancel',
        'provider.cancel:inv_cancel',
        'events.markCanceled:issue-key-cancel',
        'events.markIssued:cancel:inv_cancel',
    ], $trace->events);
    assert_same('cancel:inv_cancel', $events->claims[0]['idempotencyKey'] ?? null);
    assert_same('order:cancel', $events->claims[0]['sourceRef'] ?? null);
    assert_same('cancel', $events->claims[0]['type'] ?? null);
    assert_same('inv_cancel', $events->claims[0]['meta']['providerInvoiceId'] ?? null);
    assert_same('02', $events->claims[0]['meta']['motive'] ?? null);
    assert_same(['issue-key-cancel'], $events->canceledMarks);

    $issueRow = $events->findIssueByProviderInvoiceId('facturapi', 'inv_cancel');
    assert_true($issueRow !== null, 'canceled issue row must be findable by provider invoice id');
    assert_same('issue-key-cancel', $issueRow->idempotencyKey());
    assert_same('canceled', $issueRow->ledgerStatus());

    $cancelClaim = $events->findClaimByIdempotencyKey('facturapi', 'cancel:inv_cancel');
    assert_true($cancelClaim !== null, 'cancel audit claim must be recorded');
    assert_same('issued', $cancelClaim->ledgerStatus(), 'cancel audit row records success without markCanceled');
});

test('CancelIssuedInvoice replay localmente cancelado no llama de nuevo al proveedor', function (): void {
    $events = new Task11EventLog();
    $events->seedIssued('facturapi', 'issue-key-replay', task11_invoice('inv_replay', sourceRef: 'order:replay'));
    $provider = new Task11Provider();
    $useCase = new CancelIssuedInvoice(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        events: $events,
        defaultProviderKey: 'facturapi',
    );

    $first = $useCase->handle(new InvoiceCancellation('02'), sourceRef: 'order:replay');
    $second = $useCase->handle(new InvoiceCancellation('02'), providerInvoiceId: 'inv_replay');

    assert_same(InvoiceStatus::Canceled, $first->status());
    assert_same(InvoiceStatus::Canceled, $second->status());
    assert_same(['inv_replay'], $provider->cancelCalls);
    assert_same([], $provider->retrieveCalls);
});

test('CancelIssuedInvoice con claim de cancelacion existente solo retorna si remoto ya esta cancelado', function (): void {
    $events = new Task11EventLog();
    $events->seedIssued('facturapi', 'issue-key-remote-canceled', task11_invoice('inv_remote_canceled'));
    $events->seedCancelClaim('facturapi', 'inv_remote_canceled');
    $provider = new Task11Provider();
    $provider->retrievedInvoices['inv_remote_canceled'] = task11_invoice(
        'inv_remote_canceled',
        InvoiceStatus::Canceled,
        sourceRef: null,
        uuid: 'uuid_remote_canceled',
    );
    $useCase = new CancelIssuedInvoice(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        events: $events,
        defaultProviderKey: 'facturapi',
    );

    $canceled = $useCase->handle(new InvoiceCancellation('02'), providerInvoiceId: 'inv_remote_canceled');

    assert_same(InvoiceStatus::Canceled, $canceled->status());
    assert_same([], $provider->cancelCalls);
    assert_same(['inv_remote_canceled'], $provider->retrieveCalls);
    assert_same(['issue-key-remote-canceled'], $events->canceledMarks);

    $events = new Task11EventLog();
    $events->seedIssued('facturapi', 'issue-key-inflight', task11_invoice('inv_inflight'));
    $events->seedCancelClaim('facturapi', 'inv_inflight');
    $provider = new Task11Provider();
    $useCase = new CancelIssuedInvoice(
        registry: task11_registry($provider),
        resolver: task11_resolver($events),
        events: $events,
        defaultProviderKey: 'facturapi',
    );

    assert_throws(
        InvoiceAlreadyProcessed::class,
        fn () => $useCase->handle(new InvoiceCancellation('02'), providerInvoiceId: 'inv_inflight'),
    );
    assert_same([], $provider->cancelCalls);
    assert_same(['inv_inflight'], $provider->retrieveCalls);
});

test('CancelIssuedInvoice mapea errores provider not-cancellable al dominio', function (): void {
    $events = new Task11EventLog();
    $events->seedIssued('facturapi', 'issue-key-locked', task11_invoice('inv_locked'));
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
    assert_same('cancel:inv_locked', $events->claims[0]['idempotencyKey'] ?? null);
    assert_same(['inv_locked'], $provider->cancelCalls);
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
