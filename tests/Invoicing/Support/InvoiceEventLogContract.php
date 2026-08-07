<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

function run_invoice_event_log_contract(InvoiceEventLogRepositoryInterface $events): void
{
    $suffix = bin2hex(random_bytes(4));
    $provider = 'facturapi';
    $sourceRef = 'invoice-contract-'.$suffix;

    assert_throws(RuntimeException::class, fn () => $events->markIssued(
        $provider,
        'missing-issued-'.$suffix,
        new IssuedInvoice(
            'inv_missing_issued_'.$suffix,
            'uuid-missing-issued-'.$suffix,
            InvoiceStatus::Valid,
        ),
    ), 'markIssued must fail closed when claim row is missing');
    assert_throws(RuntimeException::class, fn () => $events->markNeedsReconcile(
        $provider,
        'missing-reconcile-'.$suffix,
        new IssuedInvoice(
            'inv_missing_reconcile_'.$suffix,
            'uuid-missing-reconcile-'.$suffix,
            InvoiceStatus::NeedsReconcile,
        ),
    ), 'markNeedsReconcile must fail closed when claim row is missing');

    $claimKey = 'claim-'.$suffix;
    assert_false($events->hasProcessed($provider, $claimKey), 'new claim must not be processed');
    assert_true($events->tryClaim($provider, $claimKey, $sourceRef, 'membership', ['step' => 'claim']));
    assert_false($events->tryClaim($provider, $claimKey, $sourceRef, 'membership'));
    assert_false($events->hasProcessed($provider, $claimKey), 'claimed without provider id is not processed');
    assert_null($events->findByIdempotencyKey($provider, $claimKey), 'claimed without provider id is not findable as issued invoice');

    $events->releaseClaim($provider, $claimKey);
    assert_false($events->hasProcessed($provider, $claimKey), 'released claim must not be processed');
    assert_true($events->tryClaim($provider, $claimKey, $sourceRef, 'membership'));

    $issuedKey = 'issued-'.$suffix;
    assert_true($events->tryClaim($provider, $issuedKey, $sourceRef, 'membership'));
    $events->markIssued($provider, $issuedKey, new IssuedInvoice(
        'inv_issued_'.$suffix,
        'uuid-issued-'.$suffix,
        InvoiceStatus::Valid,
        'F-100',
        $sourceRef,
        meta: ['issued' => true],
    ));

    assert_true($events->hasProcessed($provider, $issuedKey), 'issued row has provider id');
    $issued = $events->findByIdempotencyKey($provider, $issuedKey);
    assert_true($issued instanceof IssuedInvoice, 'issued row must hydrate');
    assert_same('inv_issued_'.$suffix, $issued->providerInvoiceId());
    assert_same('uuid-issued-'.$suffix, $issued->uuid());
    assert_same(InvoiceStatus::Valid, $issued->status());
    assert_same($sourceRef, $issued->sourceRef());

    $reconcileKey = 'reconcile-'.$suffix;
    assert_true($events->tryClaim($provider, $reconcileKey, $sourceRef, 'membership'));
    $events->markNeedsReconcile($provider, $reconcileKey, new IssuedInvoice(
        'inv_reconcile_'.$suffix,
        'uuid-reconcile-'.$suffix,
        InvoiceStatus::NeedsReconcile,
        'F-200',
        $sourceRef,
        meta: ['reconcile' => true],
    ));

    assert_true($events->hasProcessed($provider, $reconcileKey), 'needs_reconcile row has provider id');
    $reconcile = $events->findByIdempotencyKey($provider, $reconcileKey);
    assert_true($reconcile instanceof IssuedInvoice, 'needs_reconcile row must hydrate');
    assert_same('inv_reconcile_'.$suffix, $reconcile->providerInvoiceId());
    assert_same(InvoiceStatus::NeedsReconcile, $reconcile->status());

    $bySource = $events->findIssuedBySourceRef($sourceRef);
    assert_same(2, count($bySource), 'source lookup returns rows with provider ids only');
    assert_same('inv_issued_'.$suffix, $bySource[0]->providerInvoiceId(), 'source lookup is id ASC');
    assert_same('inv_reconcile_'.$suffix, $bySource[1]->providerInvoiceId(), 'source lookup includes reconcile row');

    $issuedMismatchKey = 'issued-mismatch-'.$suffix;
    assert_true($events->tryClaim($provider, $issuedMismatchKey, $sourceRef.'-issued-mismatch', 'membership'));
    $events->markIssued($provider, $issuedMismatchKey, new IssuedInvoice(
        'inv_locked_issued_'.$suffix,
        'uuid-locked-issued-'.$suffix,
        InvoiceStatus::Valid,
        null,
        $sourceRef.'-issued-mismatch',
    ));
    assert_throws(RuntimeException::class, fn () => $events->markNeedsReconcile(
        $provider,
        $issuedMismatchKey,
        new IssuedInvoice(
            'inv_conflict_issued_'.$suffix,
            'uuid-conflict-issued-'.$suffix,
            InvoiceStatus::NeedsReconcile,
            null,
            $sourceRef.'-issued-mismatch',
        ),
    ), 'markNeedsReconcile must not overwrite a different provider invoice id');
    $issuedAfterConflict = $events->findByIdempotencyKey($provider, $issuedMismatchKey);
    assert_true($issuedAfterConflict instanceof IssuedInvoice, 'issued mismatch row remains findable');
    assert_same('inv_locked_issued_'.$suffix, $issuedAfterConflict->providerInvoiceId(), 'markNeedsReconcile conflict preserves provider invoice id');
    assert_same(InvoiceStatus::Valid, $issuedAfterConflict->status(), 'markNeedsReconcile conflict preserves status');

    $reconcileMismatchKey = 'reconcile-mismatch-'.$suffix;
    assert_true($events->tryClaim($provider, $reconcileMismatchKey, $sourceRef.'-reconcile-mismatch', 'membership'));
    $events->markNeedsReconcile($provider, $reconcileMismatchKey, new IssuedInvoice(
        'inv_locked_reconcile_'.$suffix,
        'uuid-locked-reconcile-'.$suffix,
        InvoiceStatus::NeedsReconcile,
        null,
        $sourceRef.'-reconcile-mismatch',
    ));
    assert_throws(RuntimeException::class, fn () => $events->markIssued(
        $provider,
        $reconcileMismatchKey,
        new IssuedInvoice(
            'inv_conflict_reconcile_'.$suffix,
            'uuid-conflict-reconcile-'.$suffix,
            InvoiceStatus::Valid,
            null,
            $sourceRef.'-reconcile-mismatch',
        ),
    ), 'markIssued must not overwrite a different provider invoice id');
    $reconcileAfterConflict = $events->findByIdempotencyKey($provider, $reconcileMismatchKey);
    assert_true($reconcileAfterConflict instanceof IssuedInvoice, 'reconcile mismatch row remains findable');
    assert_same('inv_locked_reconcile_'.$suffix, $reconcileAfterConflict->providerInvoiceId(), 'markIssued conflict preserves provider invoice id');
    assert_same(InvoiceStatus::NeedsReconcile, $reconcileAfterConflict->status(), 'markIssued conflict preserves status');
    $events->markIssued($provider, $reconcileMismatchKey, new IssuedInvoice(
        'inv_locked_reconcile_'.$suffix,
        'uuid-reconciled-issued-'.$suffix,
        InvoiceStatus::Valid,
        null,
        $sourceRef.'-reconcile-mismatch',
    ));
    $sameProviderUpdate = $events->findByIdempotencyKey($provider, $reconcileMismatchKey);
    assert_true($sameProviderUpdate instanceof IssuedInvoice, 'same provider id update remains findable');
    assert_same('inv_locked_reconcile_'.$suffix, $sameProviderUpdate->providerInvoiceId(), 'same provider id update keeps provider invoice id');
    assert_same(InvoiceStatus::Valid, $sameProviderUpdate->status(), 'same provider id update can move needs_reconcile to issued');

    foreach (['extra-a', 'extra-b'] as $name) {
        $key = $name.'-'.$suffix;
        assert_true($events->tryClaim($provider, $key, $sourceRef.'-'.$name, 'membership'));
        $events->markNeedsReconcile($provider, $key, new IssuedInvoice(
            'inv_'.$name.'_'.$suffix,
            'uuid-'.$name.'-'.$suffix,
            InvoiceStatus::NeedsReconcile,
            null,
            $sourceRef.'-'.$name,
        ));
    }

    $needsReconcile = $events->findNeedsReconcile($provider, 2);
    assert_same(2, count($needsReconcile), 'findNeedsReconcile honors limit');
    foreach ($needsReconcile as $invoice) {
        assert_same(InvoiceStatus::NeedsReconcile, $invoice->status(), 'findNeedsReconcile returns only reconcile rows');
    }
    assert_same('inv_reconcile_'.$suffix, $needsReconcile[0]->providerInvoiceId(), 'findNeedsReconcile is id ASC');
    assert_same('inv_extra-a_'.$suffix, $needsReconcile[1]->providerInvoiceId(), 'findNeedsReconcile limit keeps id order');

    $events->releaseClaim($provider, $issuedKey);
    assert_true($events->hasProcessed($provider, $issuedKey), 'releaseClaim must not delete issued rows');
    $events->releaseClaim($provider, $reconcileKey);
    assert_true($events->hasProcessed($provider, $reconcileKey), 'releaseClaim must not delete reconcile rows');
}
