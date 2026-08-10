<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\ReconcileIssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAmbiguousCreate;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceExternalIdCollision;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderIdConflict;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Kernel\Config\Config;

require_once __DIR__ . '/Support/InMemoryInvoiceEventLog.php';

final class Task10FakeProvider implements InvoiceProviderInterface
{
    public int $createCalls = 0;
    /** @var list<string> */
    public array $createIdempotencyKeys = [];
    /** @var list<string> */
    public array $retrieveCalls = [];
    /** @var list<string> */
    public array $listByExternalIdCalls = [];
    /** @var array<string, IssuedInvoice> */
    public array $retrieveResponses = [];
    /** @var array<string, list<IssuedInvoice>> */
    public array $listResponses = [];
    public ?IssuedInvoice $createResult = null;

    public function key(): string
    {
        return 'facturapi';
    }

    public function createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice
    {
        $this->createCalls++;
        $this->createIdempotencyKeys[] = $idempotencyKey;

        return $this->createResult ?? new IssuedInvoice(
            'inv_unexpected',
            'uuid_unexpected',
            InvoiceStatus::Valid,
            sourceRef: $draft->sourceRef(),
            meta: ['provider_status' => 'valid'],
        );
    }

    public function externalIdForIssue(string $idempotencyKey): string
    {
        return 'fake-external:' . $idempotencyKey;
    }

    public function retrieveInvoice(string $providerInvoiceId): IssuedInvoice
    {
        $this->retrieveCalls[] = $providerInvoiceId;

        return $this->retrieveResponses[$providerInvoiceId]
            ?? new IssuedInvoice($providerInvoiceId, 'uuid_retrieved', InvoiceStatus::Valid, meta: ['provider_status' => 'valid']);
    }

    /** @return IssuedInvoice[] */
    public function listByExternalId(string $externalId): array
    {
        $this->listByExternalIdCalls[] = $externalId;

        return $this->listResponses[$externalId] ?? [];
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

function task10_provider(): Task10FakeProvider
{
    return new Task10FakeProvider();
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
    ?InvoiceableSourceInterface $source = null,
): ReconcileIssuedInvoice {
    return new ReconcileIssuedInvoice(
        events: $events,
        registry: task10_registry($provider),
        defaultProviderKey: 'facturapi',
        source: $source,
        validator: $source !== null ? new InvoiceDraftValidator() : null,
    );
}

function task10_with_min_claim_age(int $seconds, callable $fn): mixed
{
    $original = Config::get('invoicing.reconcile_min_claim_age_seconds', 120);
    Config::set('invoicing.reconcile_min_claim_age_seconds', $seconds);
    try {
        return $fn();
    } finally {
        Config::set('invoicing.reconcile_min_claim_age_seconds', $original);
    }
}

test('ReconcileIssuedInvoice promueve needs_reconcile a issued verificando remoto (A15)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:partial', 'order:task10', 'invoice'));
    $events->markNeedsReconcile('facturapi', 'idem:partial', new IssuedInvoice(
        providerInvoiceId: 'inv_remote_observed',
        uuid: 'uuid_remote_observed',
        status: InvoiceStatus::NeedsReconcile,
        sourceRef: 'order:task10',
    ));
    $provider = task10_provider();
    $provider->retrieveResponses['inv_remote_observed'] = new IssuedInvoice(
        'inv_remote_observed',
        'uuid_remote_observed',
        InvoiceStatus::Valid,
        meta: ['provider_status' => 'valid'],
    );
    $useCase = task10_use_case($events, $provider);

    $issued = $useCase->handle('idem:partial');

    assert_same('inv_remote_observed', $issued->providerInvoiceId());
    assert_same(InvoiceStatus::Valid, $issued->status());
    assert_same(['inv_remote_observed'], $provider->retrieveCalls, 'reconcile must retrieve remote state before promoting (A15)');
    assert_same(0, $provider->createCalls, 'handle() must never createInvoice');
    assert_same(InvoiceStatus::Valid, $events->findByIdempotencyKey('facturapi', 'idem:partial')?->status());
});

test('ReconcileIssuedInvoice preserva pending remoto sin coaccionar a Valid (A16/A27)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:pending', 'order:task10:pending', 'invoice'));
    $events->markNeedsReconcile('facturapi', 'idem:pending', new IssuedInvoice(
        providerInvoiceId: 'inv_pending',
        uuid: 'uuid_pending',
        status: InvoiceStatus::NeedsReconcile,
        sourceRef: 'order:task10:pending',
    ));
    $provider = task10_provider();
    $provider->retrieveResponses['inv_pending'] = new IssuedInvoice(
        'inv_pending',
        'uuid_pending',
        InvoiceStatus::Pending,
        meta: ['provider_status' => 'pending'],
    );
    $useCase = task10_use_case($events, $provider);

    $reconciled = $useCase->handle('idem:pending');

    assert_same(InvoiceStatus::Pending, $reconciled->status(), 'remote pending must not be coerced to Valid');
    assert_same('pending', $reconciled->meta()['provider_status'] ?? null);
    $row = $events->findClaimByIdempotencyKey('facturapi', 'idem:pending');
    assert_same('issued', $row?->ledgerStatus(), 'A27: remote pending promotes the ledger row to issued');
    assert_same(0, $provider->createCalls);

    // Idempotent replay: ledger is already `issued`, handle() must return as-is without a second retrieve.
    $replayed = $useCase->handle('idem:pending');
    assert_same(InvoiceStatus::Pending, $replayed->status());
    assert_same(['inv_pending'], $provider->retrieveCalls, 'second handle() on an issued row must not retrieve again');
});

test('ReconcileIssuedInvoice remoto canceled marca canceled y no Valid', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:remote-canceled', 'order:task10:canceled', 'invoice'));
    $events->markNeedsReconcile('facturapi', 'idem:remote-canceled', new IssuedInvoice(
        providerInvoiceId: 'inv_remote_canceled',
        uuid: 'uuid_remote_canceled',
        status: InvoiceStatus::NeedsReconcile,
        sourceRef: 'order:task10:canceled',
    ));
    $provider = task10_provider();
    $provider->retrieveResponses['inv_remote_canceled'] = new IssuedInvoice(
        'inv_remote_canceled',
        'uuid_remote_canceled',
        InvoiceStatus::Canceled,
        meta: ['provider_status' => 'canceled'],
    );
    $useCase = task10_use_case($events, $provider);

    $reconciled = $useCase->handle('idem:remote-canceled');

    assert_same(InvoiceStatus::Canceled, $reconciled->status());
    assert_same('canceled', $events->findClaimByIdempotencyKey('facturapi', 'idem:remote-canceled')?->ledgerStatus());
    assert_same(0, $provider->createCalls);
});

test('ReconcileIssuedInvoice devuelve facturas ya finalizadas as-is sin volver a llamar al proveedor', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:cancelled', 'order:task10', 'invoice'));
    $events->markCanceled('facturapi', 'idem:cancelled', new IssuedInvoice(
        providerInvoiceId: 'inv_cancelled',
        uuid: 'uuid_cancelled',
        status: InvoiceStatus::Canceled,
        sourceRef: 'order:task10',
    ));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    $result = $useCase->handle('idem:cancelled');

    assert_same('inv_cancelled', $result->providerInvoiceId());
    assert_same(InvoiceStatus::Canceled, $result->status());
    assert_same(0, $provider->createCalls);
    assert_same([], $provider->retrieveCalls, 'terminal ledger rows must not be retrieved again');
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

test('ReconcileIssuedInvoice reporta InvoiceSourceNotFound cuando no existe fila de claim', function (): void {
    $events = new InMemoryInvoiceEventLog();
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    assert_throws(
        InvoiceSourceNotFound::class,
        fn () => $useCase->handle('idem:missing'),
    );
    assert_same(0, $provider->createCalls);
});

test('findClaimByIdempotencyKey ve claims huerfanos que findByIdempotencyKey no ve (A24)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:orphan-visibility', 'order:orphan', 'invoice', ['external_id' => 'ext-1']));

    assert_null($events->findByIdempotencyKey('facturapi', 'idem:orphan-visibility'), 'legacy lookup cannot see orphans');
    $row = $events->findClaimByIdempotencyKey('facturapi', 'idem:orphan-visibility');
    assert_true($row !== null, 'A24 read model must see orphan claims');
    assert_same('claimed', $row->ledgerStatus());
    assert_null($row->providerInvoiceId());
    assert_same('ext-1', $row->meta()['external_id'] ?? null);
});

test('ReconcileIssuedInvoice claim demasiado fresco no llama listByExternalId (A27 race guard)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:fresh', 'order:task10:fresh', 'invoice', ['external_id' => 'fake-external:idem:fresh']));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    task10_with_min_claim_age(999999, function () use ($useCase, $provider): void {
        $thrown = null;
        try {
            $useCase->handle('idem:fresh');
        } catch (InvoiceAmbiguousCreate $e) {
            $thrown = $e;
        }

        assert_true($thrown instanceof InvoiceAmbiguousCreate);
        assert_true(str_contains((string) $thrown->reason(), 'too fresh'));
        assert_same([], $provider->listByExternalIdCalls, 'a too-fresh claim must not call listByExternalId');
        assert_same(0, $provider->createCalls);
    });

    // Claim must survive untouched.
    assert_same('claimed', $events->findClaimByIdempotencyKey('facturapi', 'idem:fresh')?->ledgerStatus());
});

test('ReconcileIssuedInvoice huerfano con list 0 mantiene el claim y lanza AmbiguousCreate (A22)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:zero-hits', 'order:task10:zero', 'invoice', ['external_id' => 'fake-external:idem:zero-hits']));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    task10_with_min_claim_age(0, function () use ($useCase, $provider): void {
        assert_throws(InvoiceAmbiguousCreate::class, fn () => $useCase->handle('idem:zero-hits'));
        assert_same(['fake-external:idem:zero-hits'], $provider->listByExternalIdCalls);
        assert_same(0, $provider->createCalls, 'handle() must never createInvoice, even on zero hits');
    });

    $row = $events->findClaimByIdempotencyKey('facturapi', 'idem:zero-hits');
    assert_same('claimed', $row?->ledgerStatus(), 'claim must be kept for forceReissueOrphanClaim');
});

test('ReconcileIssuedInvoice huerfano con list 1 adjunta id y promueve sin crear (A22)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:one-hit', 'order:task10:one', 'invoice', ['external_id' => 'fake-external:idem:one-hit']));
    $provider = task10_provider();
    $provider->listResponses['fake-external:idem:one-hit'] = [
        new IssuedInvoice('inv_recovered', 'uuid_recovered', InvoiceStatus::Valid),
    ];
    $provider->retrieveResponses['inv_recovered'] = new IssuedInvoice(
        'inv_recovered',
        'uuid_recovered',
        InvoiceStatus::Valid,
        meta: ['provider_status' => 'valid'],
    );
    $useCase = task10_use_case($events, $provider);

    $reconciled = task10_with_min_claim_age(0, fn () => $useCase->handle('idem:one-hit'));

    assert_same('inv_recovered', $reconciled->providerInvoiceId());
    assert_same(InvoiceStatus::Valid, $reconciled->status());
    assert_same(['inv_recovered'], $provider->retrieveCalls, 'orphan recovery must retrieve after attaching (same promotion path)');
    assert_same(0, $provider->createCalls, 'handle() must never createInvoice');
    assert_same('issued', $events->findClaimByIdempotencyKey('facturapi', 'idem:one-hit')?->ledgerStatus());
    assert_same('fake-external:idem:one-hit', $events->findClaimByIdempotencyKey('facturapi', 'idem:one-hit')?->meta()['external_id'] ?? null, 'mark() must preserve meta.external_id');
});

test('ReconcileIssuedInvoice huerfano con list >1 falla cerrado sin elegir id (A23)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:many-hits', 'order:task10:many', 'invoice', ['external_id' => 'fake-external:idem:many-hits']));
    $provider = task10_provider();
    $provider->listResponses['fake-external:idem:many-hits'] = [
        new IssuedInvoice('inv_dup_a', 'uuid_dup_a', InvoiceStatus::Valid),
        new IssuedInvoice('inv_dup_b', 'uuid_dup_b', InvoiceStatus::Valid),
    ];
    $useCase = task10_use_case($events, $provider);

    task10_with_min_claim_age(0, function () use ($useCase, $provider): void {
        $thrown = null;
        try {
            $useCase->handle('idem:many-hits');
        } catch (InvoiceExternalIdCollision $e) {
            $thrown = $e;
        }

        assert_true($thrown instanceof InvoiceExternalIdCollision);
        assert_same('facturapi', $thrown->providerKey());
        assert_same('idem:many-hits', $thrown->idempotencyKey());
        assert_same('fake-external:idem:many-hits', $thrown->externalId());
        assert_same(2, $thrown->matchCount());
        assert_same(0, $provider->createCalls, 'handle() must never createInvoice');
    });
});

test('ReconcileIssuedInvoice reconcileOrphan relee y devuelve sin lanzar cuando pierde la carrera de attach (A27)', function (): void {
    // Simulates the issuing process winning the race: between this call's listByExternalId
    // and its attachProviderInvoiceId, another process attaches a DIFFERENT provider id.
    $orphanEvents = new class extends InMemoryInvoiceEventLog {
        public bool $forceConflictOnce = true;

        public function attachProviderInvoiceId(
            string $provider,
            string $idempotencyKey,
            string $providerInvoiceId,
            array $meta = [],
        ): void {
            if ($this->forceConflictOnce) {
                $this->forceConflictOnce = false;
                parent::attachProviderInvoiceId($provider, $idempotencyKey, 'inv_winner', $meta);
                throw new InvoiceProviderIdConflict(
                    'simulated lost race',
                    $provider,
                    $idempotencyKey,
                    'inv_winner',
                    $providerInvoiceId,
                );
            }

            parent::attachProviderInvoiceId($provider, $idempotencyKey, $providerInvoiceId, $meta);
        }
    };
    assert_true($orphanEvents->tryClaim('facturapi', 'idem:orphan-race', 'order:task10:orphan-race', 'invoice', ['external_id' => 'fake-external:idem:orphan-race']));
    $orphanProvider = task10_provider();
    $orphanProvider->listResponses['fake-external:idem:orphan-race'] = [
        new IssuedInvoice('inv_loser', 'uuid_loser', InvoiceStatus::Valid),
    ];
    $orphanUseCase = task10_use_case($orphanEvents, $orphanProvider);

    $result = task10_with_min_claim_age(0, fn () => $orphanUseCase->handle('idem:orphan-race'));

    assert_same('inv_winner', $result->providerInvoiceId(), 'lost race must re-read and return the winning row, never throw');
    assert_same(0, $orphanProvider->createCalls);
});

test('ReconcileIssuedInvoice forceReissueOrphanClaim rechaza fila no huerfana', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:not-orphan', 'order:task10:not-orphan', 'invoice'));
    $events->markIssued('facturapi', 'idem:not-orphan', new IssuedInvoice('inv_issued', 'uuid_issued', InvoiceStatus::Valid));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    assert_throws(RuntimeException::class, fn () => $useCase->forceReissueOrphanClaim('idem:not-orphan'));
    assert_same(0, $provider->createCalls);
});

test('ReconcileIssuedInvoice forceReissueOrphanClaim rechaza edad insuficiente', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:too-young', 'order:task10:young', 'invoice', ['external_id' => 'fake-external:idem:too-young']));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    task10_with_min_claim_age(999999, function () use ($useCase, $provider): void {
        assert_throws(RuntimeException::class, fn () => $useCase->forceReissueOrphanClaim('idem:too-young'));
        assert_same(0, $provider->createCalls);
    });
});

test('ReconcileIssuedInvoice forceReissueOrphanClaim rechaza si listByExternalId > 0', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:still-remote', 'order:task10:remote', 'invoice', ['external_id' => 'fake-external:idem:still-remote']));
    $provider = task10_provider();
    $provider->listResponses['fake-external:idem:still-remote'] = [
        new IssuedInvoice('inv_exists', 'uuid_exists', InvoiceStatus::Valid),
    ];
    $useCase = task10_use_case($events, $provider);

    task10_with_min_claim_age(0, function () use ($useCase, $provider): void {
        assert_throws(RuntimeException::class, fn () => $useCase->forceReissueOrphanClaim('idem:still-remote'));
        assert_same(0, $provider->createCalls, 'forceReissueOrphanClaim must never create when the remote already has a match');
    });
});

test('ReconcileIssuedInvoice forceReissueOrphanClaim reemite con la misma idempotencyKey cuando 0 hits (A26)', function (): void {
    $events = new InMemoryInvoiceEventLog();
    $sourceRef = 'order:task10:force';
    assert_true($events->tryClaim('facturapi', 'idem:force-reissue', $sourceRef, 'invoice', ['external_id' => 'fake-external:idem:force-reissue']));
    $provider = task10_provider();
    $provider->createResult = new IssuedInvoice(
        'inv_force_reissued',
        'uuid_force_reissued',
        InvoiceStatus::Valid,
        sourceRef: $sourceRef,
        meta: ['provider_status' => 'valid'],
    );
    $draft = task10_draft($sourceRef);
    $source = task10_source([$sourceRef => $draft]);
    $useCase = task10_use_case($events, $provider, $source);

    $reissued = task10_with_min_claim_age(0, fn () => $useCase->forceReissueOrphanClaim('idem:force-reissue'));

    assert_same('inv_force_reissued', $reissued->providerInvoiceId());
    assert_same(InvoiceStatus::Valid, $reissued->status());
    assert_same(1, $provider->createCalls);
    assert_same(['idem:force-reissue'], $provider->createIdempotencyKeys, 'forceReissueOrphanClaim must reuse the SAME idempotencyKey');
    assert_same('issued', $events->findClaimByIdempotencyKey('facturapi', 'idem:force-reissue')?->ledgerStatus());
});

test('ReconcileIssuedInvoice forceReissueOrphanClaim exige source y validator', function (): void {
    $events = new InMemoryInvoiceEventLog();
    assert_true($events->tryClaim('facturapi', 'idem:no-source', 'order:task10:no-source', 'invoice', ['external_id' => 'fake-external:idem:no-source']));
    $provider = task10_provider();
    $useCase = task10_use_case($events, $provider);

    task10_with_min_claim_age(0, function () use ($useCase, $provider): void {
        assert_throws(RuntimeException::class, fn () => $useCase->forceReissueOrphanClaim('idem:no-source'));
        assert_same(0, $provider->createCalls);
    });
});

function task10_draft(string $sourceRef): InvoiceDraft
{
    return new InvoiceDraft(
        sourceRef: $sourceRef,
        customer: new \Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer(
            legalName: 'ACME SA DE CV',
            taxId: 'ACM010101ABC',
            taxSystem: '601',
            address: new \Lebytek\Framework\Domain\Invoicing\ValueObjects\Address(zip: '01000'),
        ),
        items: [
            new \Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem(
                quantity: 1.0,
                description: 'Servicio mensual',
                productKey: '80101500',
                unitPrice: \Lebytek\Framework\Domain\Invoicing\ValueObjects\Money::fromMinor(100000, 'MXN'),
                taxes: [new \Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax(type: 'IVA', rate: 0.16, factor: 'Tasa')],
            ),
        ],
        paymentForm: \Lebytek\Framework\Domain\Invoicing\PaymentForm::Transferencia,
        cfdiUse: \Lebytek\Framework\Domain\Invoicing\CfdiUse::G01,
    );
}

function task10_source(array $drafts): InvoiceableSourceInterface
{
    return new class($drafts) implements InvoiceableSourceInterface {
        /** @param array<string, InvoiceDraft> $drafts */
        public function __construct(private readonly array $drafts)
        {
        }

        public function findDraft(string $sourceRef): ?InvoiceDraft
        {
            return $this->drafts[$sourceRef] ?? null;
        }
    };
}
