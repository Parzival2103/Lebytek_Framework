Status: DONE_WITH_CONCERNS

- Added `ReconcileIssuedInvoice` application use case.
- `handle()` resolves provider key, loads `findByIdempotencyKey`, returns finalized invoices as-is, and promotes `NeedsReconcile` via `markIssued()`.
- Added `listNeedsReconcile()` thin wrapper over `findNeedsReconcile()`.
- No provider create/retrieve/sync path is called; registry is used only for provider-key validation.
- Claimed-only rows remain indistinguishable from absent rows through the current port, so reconcile reports `InvoiceSourceNotFound`.

Verification:
- RED: `php tests/run.php ReconcileIssuedInvoice` failed on missing class.
- GREEN: `php tests/run.php ReconcileIssuedInvoice` -> 4 passed, 0 failed.
- `php tests/run.php Invoicing` -> 45 passed, 0 failed (PDO contract skipped: DB unavailable).
- Full `php tests/run.php` has 7 unrelated DB connection failures in Integrations/PDO tests.
