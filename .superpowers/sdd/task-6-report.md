# Task 6 Report — Cancel complete: claim-before, markCanceled, motives, idempotency

Plan: `docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md` (A17)
Branch: `cursor/invoicing-hardening-p02-p10-896b`
Base: `215b65a` (Task 5 HEAD)
Commit: pending — `fix(invoicing): claim-before cancel, markCanceled, and SAT motive rules`

## Status: COMPLETE

## What changed

- `InvoiceCancellation` now validates SAT motives `01|02|03|04`, trims values, and rejects motive `01`
  unless a non-empty substitution UUID is provided. Invalid cancellations throw `InvoiceDraftInvalid`
  before the use case can claim or call a provider.
- `CancelIssuedInvoice` now resolves the original issue ledger row with
  `findIssueByProviderInvoiceId($provider, $providerInvoiceId)` before remote cancellation.
- Local already-canceled issue rows return the local snapshot without remote `retrieve` or `cancel`.
- New cancellations claim `cancel:{providerInvoiceId}` before calling `cancelInvoice`.
- Successful remote cancellation calls
  `markCanceled($provider, $issueRow->idempotencyKey(), $canceledInvoice)` on the original issue row.
- The cancel audit claim is marked with success metadata via `markIssued`; the cancel claim row is not
  marked `canceled`, so the issue row remains the fiscal source of truth.
- If the cancel claim already exists, the use case never blind-cancels. It returns safely only when the
  issue row is already canceled or `retrieveInvoice` proves the remote invoice is already canceled;
  otherwise it throws `InvoiceAlreadyProcessed`.
- `findIssueByProviderInvoiceId` in PDO and in-memory repositories now excludes `type = 'cancel'` rows so
  cancel audit rows cannot shadow the original issue row when they carry the same provider invoice id.

## TDD evidence

Red run before production changes:

```text
php tests/run.php Invoicing
93 passed, 5 failed
```

Expected red failures covered motive normalization/validation, claim-before ordering, issue-row
`markCanceled`, replay without a second remote cancel, remote-canceled duplicate-claim handling, and
claim-before behavior for provider not-cancellable failures.

Green run after implementation:

```text
php tests/run.php Invoicing
98 passed, 0 failed
```

## Concerns / notes

- Provider-id cancellation now fails closed when the original issue ledger row is absent; this is required
  so `markCanceled(provider, issueIdempotencyKey, invoice)` can update the correct issue row and avoid
  untracked remote cancels.
- Scope intentionally excludes Portal UI, cancellation receipt download, Task 7+ work.

## Review fix — cancel audit rows in sourceRef lookup

Commit: `dee2da2` — `fix(invoicing): exclude cancel audit rows from sourceRef lookup`

After cancel, `markCancelClaimIssued` writes a `type='cancel'` audit row sharing `source_ref` and
`provider_invoice_id` with the issue row. `findIssuedBySourceRef` now excludes `type='cancel'` in PDO and
InMemory (mirroring the Task 6 guard on `findIssueByProviderInvoiceId`), so `InvoiceIdResolver` no longer
throws `InvoiceAmbiguousSource` on a unique issue.

Contract coverage: simulated cancel audit row in `InvoiceEventLogContract`; integration assertion in
`CancelIssuedInvoice` test that `resolve(null, sourceRef)` succeeds post-cancel.

```text
php tests/run.php Invoicing
98 passed, 0 failed
```
