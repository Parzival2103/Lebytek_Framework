<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderIdConflict;
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
    assert_throws(RuntimeException::class, fn () => $events->attachProviderInvoiceId(
        $provider,
        'missing-attach-'.$suffix,
        'inv_missing_attach_'.$suffix,
    ), 'attachProviderInvoiceId must fail closed when claim row is missing');

    $claimKey = 'claim-'.$suffix;
    assert_false($events->hasProcessed($provider, $claimKey), 'new claim must not be processed');
    assert_true($events->tryClaim($provider, $claimKey, $sourceRef, 'membership', ['step' => 'claim']));
    assert_false($events->tryClaim($provider, $claimKey, $sourceRef, 'membership'));
    assert_false($events->hasProcessed($provider, $claimKey), 'claimed without provider id is not processed');
    assert_null($events->findByIdempotencyKey($provider, $claimKey), 'claimed without provider id is not findable as issued invoice');

    $events->releaseClaim($provider, $claimKey);
    assert_false($events->hasProcessed($provider, $claimKey), 'released claim must not be processed');
    assert_true($events->tryClaim($provider, $claimKey, $sourceRef, 'membership'));

    $attachKey = 'attach-'.$suffix;
    assert_true($events->tryClaim($provider, $attachKey, $sourceRef.'-attach', 'membership', [
        'external_id' => 'ext-preserved-'.$suffix,
        'claimed' => true,
    ]));
    $events->attachProviderInvoiceId($provider, $attachKey, 'inv_attached_'.$suffix, [
        'external_id' => 'ext-overwrite-'.$suffix,
        'attached' => true,
    ]);
    $attached = $events->findByIdempotencyKey($provider, $attachKey);
    assert_true($attached instanceof IssuedInvoice, 'attached provider id must hydrate');
    assert_same('inv_attached_'.$suffix, $attached->providerInvoiceId());
    assert_same(InvoiceStatus::NeedsReconcile, $attached->status());
    assert_same('ext-preserved-'.$suffix, $attached->meta()['external_id'] ?? null, 'attach must preserve claim external_id');
    assert_true($attached->meta()['claimed'] ?? false, 'attach must merge existing meta');
    assert_true($attached->meta()['attached'] ?? false, 'attach must merge new meta');
    $events->attachProviderInvoiceId($provider, $attachKey, 'inv_attached_'.$suffix, ['same' => true]);
    $attachedAgain = $events->findByIdempotencyKey($provider, $attachKey);
    assert_true($attachedAgain instanceof IssuedInvoice, 'same provider id attach remains findable');
    assert_true($attachedAgain->meta()['same'] ?? false, 'same provider id attach can merge additional meta');

    $attachMismatchKey = 'attach-mismatch-'.$suffix;
    assert_true($events->tryClaim($provider, $attachMismatchKey, $sourceRef.'-attach-mismatch', 'membership'));
    $events->attachProviderInvoiceId($provider, $attachMismatchKey, 'inv_attach_locked_'.$suffix);
    assert_throws(InvoiceProviderIdConflict::class, function () use ($events, $provider, $attachMismatchKey, $suffix): void {
        try {
            $events->attachProviderInvoiceId(
                $provider,
                $attachMismatchKey,
                'inv_attach_conflict_'.$suffix,
            );
        } catch (InvoiceProviderIdConflict $e) {
            assert_same($provider, $e->providerKey());
            assert_same($attachMismatchKey, $e->idempotencyKey());
            assert_same('inv_attach_locked_'.$suffix, $e->existingId());
            assert_same('inv_attach_conflict_'.$suffix, $e->attemptedId());
            throw $e;
        }
    }, 'attachProviderInvoiceId must not overwrite a different provider invoice id');
    $attachedAfterConflict = $events->findByIdempotencyKey($provider, $attachMismatchKey);
    assert_true($attachedAfterConflict instanceof IssuedInvoice, 'attach conflict row remains findable');
    assert_same('inv_attach_locked_'.$suffix, $attachedAfterConflict->providerInvoiceId(), 'attach conflict preserves provider invoice id');

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

    $needsReconcile = $events->findNeedsReconcile($provider, 4);
    assert_same(4, count($needsReconcile), 'findNeedsReconcile honors limit');
    foreach ($needsReconcile as $invoice) {
        assert_same(InvoiceStatus::NeedsReconcile, $invoice->status(), 'findNeedsReconcile returns only reconcile rows');
    }
    assert_same('inv_attached_'.$suffix, $needsReconcile[0]->providerInvoiceId(), 'findNeedsReconcile includes attached rows in id ASC');
    assert_same('inv_attach_locked_'.$suffix, $needsReconcile[1]->providerInvoiceId(), 'findNeedsReconcile keeps attached conflict row in id ASC');
    assert_same('inv_reconcile_'.$suffix, $needsReconcile[2]->providerInvoiceId(), 'findNeedsReconcile keeps marked reconcile row in id ASC');
    assert_same('inv_extra-a_'.$suffix, $needsReconcile[3]->providerInvoiceId(), 'findNeedsReconcile limit keeps id order');

    $events->releaseClaim($provider, $issuedKey);
    assert_true($events->hasProcessed($provider, $issuedKey), 'releaseClaim must not delete issued rows');
    $events->releaseClaim($provider, $reconcileKey);
    assert_true($events->hasProcessed($provider, $reconcileKey), 'releaseClaim must not delete reconcile rows');
}
