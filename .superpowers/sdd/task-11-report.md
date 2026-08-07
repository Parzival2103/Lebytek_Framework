Task 11 complete.

- Added `InvoiceIdResolver` with provider-id precedence and fail-closed `source_ref` lookup.
- Added cancel/download/email application scaffolds; cancel maps not-cancellable provider failures and audits best-effort.
- Added `InvoiceScaffoldUseCasesTest` covering resolver ambiguity/not-found, cancel, pdf/xml download, and email delegation.
- Verification:
  - `php tests/run.php InvoiceScaffoldUseCasesTest` => 6 passed, 0 failed
  - `php tests/run.php Invoicing` => 51 passed, 0 failed
  - `php tests/run.php` => 677 passed, 7 failed (DB connection refused in non-invoicing integration tests)
