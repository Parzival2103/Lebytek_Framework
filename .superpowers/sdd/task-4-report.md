# Task 4 Report — Platform SQL `inv_events` + `inv_organizations`

**Branch:** `cursor/invoicing-facturapi-impl-03e4`  
**Date:** 2026-08-07

## Deliverables

| File | Action |
|------|--------|
| `database/schema/modules/invoicing.sql` | Created |
| `tests/Invoicing/InvoicingSchemaTest.php` | Created |

## TDD evidence

### Step 1 — Schema string tests (RED)

Added `InvoicingSchemaTest.php` with three tests:

1. `inv_events`: idempotent CREATE, UNIQUE `(provider, idempotency_key)`, indexes on `source_ref` and `(provider, status)`, nullable mark* columns, status literals documented.
2. `inv_organizations`: idempotent CREATE, UNIQUE `(provider_key, external_org_id)`, `external_org_id NOT NULL DEFAULT ''`, `mode` / `label` / `meta JSON`.
3. No `dom_*` tables.

### Step 2 — Run (expect FAIL)

```text
$ php tests/run.php Invoicing
18 passed, 2 failed
```

Failures: `file_get_contents(.../invoicing.sql): No such file or directory` on the two schema tests (third test passed vacuously on empty string).

### Step 3 — Write SQL

Created `database/schema/modules/invoicing.sql` mirroring `payments.sql` style:

- MySQL 8 safe: `SET NAMES utf8mb4`, `FOREIGN_KEY_CHECKS`, `CREATE TABLE IF NOT EXISTS`, InnoDB utf8mb4_unicode_ci.
- `inv_events`: ledger columns per spec; `status` default `claimed`; comment documents `claimed | issued | needs_reconcile | canceled`.
- `inv_organizations`: org cache with composite unique for default org (`external_org_id = ''`).

### Step 4 — Run (expect PASS)

```text
$ php tests/run.php Invoicing
20 passed, 0 failed
```

(17 existing Invoicing tests + 3 new schema tests.)

## Contract checklist

- [x] `inv_events` status: claimed | issued | needs_reconcile | canceled
- [x] UNIQUE `(provider, idempotency_key)`
- [x] INDEX `(source_ref)`
- [x] INDEX `(provider, status)` for `findNeedsReconcile`
- [x] `provider_invoice_id` / `uuid` / `folio_number` nullable until mark*
- [x] `inv_organizations` UNIQUE `(provider_key, external_org_id)`
- [x] `external_org_id` VARCHAR NOT NULL DEFAULT `''`
- [x] `mode`, `label`, `meta` JSON
- [x] No `dom_*` tables
- [x] No PHP repos (Task 5)

## Commit

```
feat(invoicing): platform SQL inv_events and inv_organizations
```

Not pushed (per task instructions).
