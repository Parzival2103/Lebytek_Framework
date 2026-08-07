# Task 9 Report: IssueInvoiceFromSource + draft validator (A1)

## Status

Implemented:

- `src/Application/Invoicing/InvoiceDraftValidator.php`
- `src/Application/Invoicing/IssueInvoiceFromSource.php`
- `tests/Invoicing/IssueInvoiceFromSourceTest.php`

No cancel/download/reconcile/container wiring was added.

## TDD evidence

1. RED: after adding `tests/Invoicing/IssueInvoiceFromSourceTest.php`, ran:
   - `php tests/run.php Invoicing/IssueInvoiceFromSource`
   - Result: `0 passed, 7 failed`
   - Expected failure reason: missing `InvoiceDraftValidator` and `IssueInvoiceFromSource` classes.
2. GREEN: after implementing the validator and use case, ran:
   - `php tests/run.php Invoicing/IssueInvoiceFromSource`
   - Result: `7 passed, 0 failed`
3. Integration verification:
   - `php tests/run.php Invoicing`
   - Result: `40 passed, 0 failed`
   - Note: PDO contract path reported DB unavailable and skipped the live DB contract.
4. Full harness:
   - `php tests/run.php`
   - Result: `666 passed, 7 failed`
   - The 7 failures are existing DB connection failures in integration tests outside Task 9 (`Connection refused`).

## A1 behavior covered

- Happy path: claim -> source draft -> validate -> provider create -> mark issued -> return issued invoice.
- Claim replay with provider id: returns existing invoice and does not call `createInvoice`.
- Claim replay without provider id: throws `InvoiceAlreadyProcessed` and does not call source/provider.
- `createInvoice` failure before provider id: releases claim and rethrows; a later retry can claim and create.
- Critical partial failure: `createInvoice` succeeds, `markIssued` throws, use case calls `markNeedsReconcile`, throws `InvoiceNeedsReconcile`, does not release the claim, and a second `handle` returns the needs_reconcile invoice without another provider create.

## Validator contract

`InvoiceDraftValidator` throws `InvoiceDraftInvalid` for:

- invalid RFC/taxId
- invalid ZIP
- blank legal name
- empty items
- item quantity <= 0
- invalid SAT product key
- draft currency other than MXN
- missing item taxes unless `taxExempt` is true

## Config/default provider

`IssueInvoiceFromSource` accepts `?string $defaultProviderKey` in the constructor for hermetic unit tests. If neither `handle(..., providerKey: ...)` nor constructor default is provided, it falls back to `Config::get('invoicing.default', 'facturapi')`.
