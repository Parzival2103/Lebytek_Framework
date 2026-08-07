# Task 12 Report: Smoke integration — Issue + Reconcile + fake transport

## Summary
- Added `tests/Invoicing/InvoicingSmokeTest.php`.
- Wired real `IssueInvoiceFromSource`, `ReconcileIssuedInvoice`, `InvoiceDraftValidator`, `InvoiceProviderRegistry`, `InMemoryInvoiceEventLog`, and `FacturapiInvoiceProvider`.
- Used a local fake `FacturapiTransportInterface`; no real Facturapi calls and no vertical enablement.

## Scenarios covered
- Happy issue returns `InvoiceStatus::Valid` and stores issued ledger state.
- Simulated first `markIssued` failure raises `InvoiceNeedsReconcile`, stores `needs_reconcile`, reconciles to issued, and replay returns issued without a second create.

## Verification
- `php tests/run.php Invoicing/InvoicingSmoke` — 2 passed, 0 failed.
- `php tests/run.php Invoicing` — 53 passed, 0 failed; PDO contract skipped because DB is unavailable.
