# Task 7 Report: Facturapi adapter + golden tax fixtures

## TDD evidence

1. RED: `php tests/run.php Invoicing/FacturapiInvoiceProviderTest.php`
   - Result before implementation: `0 passed, 5 failed`
   - Expected failure: `Facturapi provider infrastructure is not implemented`
2. GREEN focused: `php tests/run.php Invoicing/FacturapiInvoiceProviderTest.php`
   - Result after implementation: `5 passed, 0 failed`
3. Syntax: `php -l` on the three new Infrastructure files and provider test
   - Result: no syntax errors
4. Suite: `php tests/run.php Invoicing`
   - Result: `30 passed, 0 failed`
   - Note: PDO contract reported DB unavailable and skipped the live DB branch, then completed without failures.
5. Boundary check: ripgrep for `Facturapi` under `src/Domain/Invoicing`
   - Result: no matches

## Implemented files

- `src/Infrastructure/Invoicing/Facturapi/FacturapiTransportInterface.php`
- `src/Infrastructure/Invoicing/Facturapi/SdkFacturapiTransport.php`
- `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php`
- `tests/Invoicing/FacturapiInvoiceProviderTest.php`
- `tests/Invoicing/fixtures/facturapi_payload_iva16.json`
- `tests/Invoicing/fixtures/facturapi_payload_exento.json`

## Notes

- Provider key is `facturapi`.
- Transport seam is domain-free and exposes create/cancel/pdf/xml/email with array/string payloads.
- CFDI payload is fixed to tipo `I`.
- IVA 16% and exento outbound payloads are asserted against golden fixtures.
- `Money::fromMinor` values are converted from minor units to Facturapi major decimal `price`.
- `taxExempt=true` maps to an IVA `Exento` tax line.
- Transport failures are wrapped as `InvoiceProviderException` with safe messages.
