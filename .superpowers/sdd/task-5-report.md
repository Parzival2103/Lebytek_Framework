# Task 5 Report — retrieve + listByExternalId orphan recovery + pending hydrate + remote reconcile

Plan: `docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md`
Branch: `cursor/invoicing-hardening-p02-p10-896b`
Base: `63f9877` (Task 4 HEAD)
Commit: `fbf9654` — `feat(invoicing): retrieve invoices, listByExternalId orphan recovery, reconcile against remote`

## Status: COMPLETE

## What changed

### Domain (`src/Domain/Invoicing`)

- `InvoiceProviderInterface`: added `retrieveInvoice(string $providerInvoiceId): IssuedInvoice` and
  `listByExternalId(string $externalId): array` as required port methods (alongside the existing
  `externalIdForIssue` from Task 3).
- New `ValueObjects/InvoiceClaimRow.php` (A24 read model): `provider`, `idempotencyKey`, `sourceRef`,
  `type`, `ledgerStatus` (`claimed|issued|needs_reconcile|canceled`), `providerInvoiceId`, `meta`,
  `createdAt`. No filtering on `provider_invoice_id`, so orphan claims are visible.
- `InvoiceEventLogRepositoryInterface`: added `markCanceled(provider, idempotencyKey, IssuedInvoice)`,
  `findClaimByIdempotencyKey`, `findIssueByProviderInvoiceId`, `findOrphanClaims(provider,
  minAgeSeconds, limit)`.
- New `Exceptions/InvoiceExternalIdCollision.php`: fail-closed typed exception when
  `listByExternalId` returns >1 match for one `external_id` (real corruption under A23's 1:1
  guarantee).
- `Exceptions/InvoiceAmbiguousCreate.php`: added optional `?string $reason` + `reason()` accessor for
  richer "too fresh" / "zero hits" messaging.

### Infrastructure

- `Facturapi/FacturapiTransportInterface` + `SdkFacturapiTransport`: added `retrieve` (→
  `$client->Invoices->retrieve($id)`) and `listByExternalId` (→ `$client->Invoices->all(['external_id'
  => ...])`).
- `FacturapiInvoiceProvider`: `retrieveInvoice`/`listByExternalId` implemented via the existing
  `mapIssuedInvoice`, which already stores `meta.provider_status = $rawStatus` (unchanged from prior
  tasks) — this is what lets pending survive hydrate.
- `PdoInvoiceEventLogRepository` (full rewrite): added `STATUS_CANCELED`, `markCanceled`,
  `findClaimByIdempotencyKey`/`findIssueByProviderInvoiceId`/`findOrphanClaims` (with
  `created_at <= NOW() - INTERVAL :minAgeSeconds SECOND`), `hydrateClaimRow`. `mark()` now calls
  `assertCanMark` to fetch **existing** meta and merges it with the incoming `invoice->meta()` via
  `mergeMetaPreservingExternalId` (A25) instead of overwriting the column. `domainStatus()` restores
  `InvoiceStatus::fromProvider($meta['provider_status'])` for `issued`/`needs_reconcile` ledger rows
  instead of hardcoding `Valid`/`NeedsReconcile` (A16).

### Application

- `ReconcileIssuedInvoice` (full rewrite):
  - `handle()` loads an `InvoiceClaimRow` via `findClaimByIdempotencyKey` and branches on
    `ledgerStatus()` exhaustively (`issued|canceled` → reload as-is; `needs_reconcile` with a
    `providerInvoiceId` → `reconcileRemote`; `claimed`, or `needs_reconcile` without a
    `providerInvoiceId` → `reconcileOrphan`). Never calls `createInvoice`.
  - `reconcileRemote`: `retrieveInvoice` then `promoteFromRemote` (Canceled → `markCanceled`;
    everything else, including `pending`, → `markIssued`, letting `meta.provider_status` carry the
    real fiscal state per A16/A27).
  - `reconcileOrphan`: age check first (throws `InvoiceAmbiguousCreate` "too fresh" without ever
    calling `listByExternalId`), then resolves `external_id` (`row.meta.external_id` else
    `provider->externalIdForIssue`), then branches on match count: 0 → keep claim + throw
    `InvoiceAmbiguousCreate`; 1 → `attachProviderInvoiceId` (catching `InvoiceProviderIdConflict` to
    re-read and return the winning row without throwing, per A27) then promote; >1 →
    `InvoiceExternalIdCollision`.
  - `forceReissueOrphanClaim(idempotencyKey)`: separate public method, ordered preconditions —
    `ledgerStatus === 'claimed' && providerInvoiceId === null`, age `>= reconcile_min_claim_age_seconds`,
    `listByExternalId` returns 0 — then rebuilds the draft via the injected
    `InvoiceableSourceInterface`/`InvoiceDraftValidator`, calls `createInvoice` with the **same**
    idempotency key/external_id, and falls back through `markIssued` → `markNeedsReconcile` →
    `attachProviderInvoiceId` → `InvoiceNeedsReconcile` if the local mark fails after a successful
    remote create.
  - `listOrphanClaims()` added for ops visibility.
  - `resolveExternalId` calls `provider->externalIdForIssue(...)`; the Application layer does not
    import `FacturapiExternalId` anywhere.
- `InvoicingFactory::makeReconcileIssuedInvoice(?InvoiceableSourceInterface $source = null)`: now
  optionally wires `source` + a fresh `InvoiceDraftValidator` so `forceReissueOrphanClaim` has what it
  needs; omitting `$source` keeps the constructor call backward compatible for callers that only need
  `handle()`.

### Config

- `config/invoicing.php` + `skeleton/config/invoicing.php`:
  `'reconcile_min_claim_age_seconds' => (int) EnvLoader::get('INVOICING_RECONCILE_MIN_CLAIM_AGE_SECONDS', 120)`.
- `.env.example` + `skeleton/.env.example`: `INVOICING_RECONCILE_MIN_CLAIM_AGE_SECONDS=120`.

### Tests

- `PdoInvoiceEventLogContractTest`: added `DELETE FROM inv_events` before running the shared contract
  against the real MySQL/MariaDB connection to avoid cross-run contamination (was causing an unrelated
  `findNeedsReconcile` id-ordering flake).
- `InMemoryInvoiceEventLog` (full rewrite, mirrors PDO): `markCanceled`, the three new `InvoiceClaimRow`
  lookups, `mark()`/`attachProviderInvoiceId()` merge meta via `mergeMetaPreservingExternalId`,
  `domainStatus()` restores `provider_status`, rows now store `createdAt` for age checks. Class de-finaled
  (was `final`) purely so one A27 race test can compose a conflict-injecting subclass — no production
  code depends on this.
- `FacturapiInvoiceProviderTest`: fake transport implements `retrieve`/`listByExternalId`; new tests for
  `retrieveInvoice` (preserves `pending` via `meta.provider_status`) and `listByExternalId` (0/1/N
  mapping).
- `InvoiceProviderRegistryTest`, `InvoiceScaffoldUseCasesTest`, `IssueInvoiceFromSourceTest`,
  `InvoicingSmokeTest`: anonymous test doubles updated to satisfy the widened interfaces.
- `InvoicingSmokeTest`: corrected an outdated assertion — `findByIdempotencyKey(...)->status()` for a
  remote-`pending` reconcile now correctly returns `InvoiceStatus::Valid` is wrong; the fixed assertion
  checks `findClaimByIdempotencyKey(...)->ledgerStatus()` (`'needs_reconcile'`) separately from
  `findByIdempotencyKey(...)->status()`.
- `ReconcileIssuedInvoiceTest` (full rewrite, ~30 tests): needs_reconcile→issued promotion (A15);
  pending fidelity end-to-end through hydrate (A16/A27); remote canceled; idempotent terminal states;
  `listNeedsReconcile`; `InvoiceSourceNotFound` on missing claim; `findClaimByIdempotencyKey` sees
  orphans that `findByIdempotencyKey` cannot (A24); "too fresh" never calls `listByExternalId`; orphan
  0/1/>1 hits (A22/A23); lost-race attach re-reads and returns without throwing (A27);
  `forceReissueOrphanClaim`'s three ordered preconditions plus the happy path and the
  source/validator-required guard (A26).

## Test summary

```
php tests/run.php Invoicing
94 passed, 0 failed
```

Full suite: `php tests/run.php` → 752 passed, 7 failed. The 7 failures are pre-existing, unrelated to
this task — all `SQLSTATE[42S02]: Base table or view not found: int_accounts`/`int_logs` in the
Integrations module (that module's bootstrap SQL was never run against this local MariaDB instance).
Confirmed by grep: these tables only exist in `database/schema/modules/integrations.sql`, a module this
task does not touch.

## Design amendments covered

- A15: remote verification before promoting `needs_reconcile`.
- A16: `meta.provider_status` round-trips through `mark()`/hydrate; ledger status governs branching,
  not `IssuedInvoice::status()`.
- A22: orphan recovery (claimed-without-id) via `listByExternalId`, `handle()` never creates.
- A23: `external_id` 1:1 relationship — >1 match is fail-closed corruption
  (`InvoiceExternalIdCollision`), never guessed.
- A24: `InvoiceClaimRow` read model + the three new repository lookups.
- A25: `mark()`/`attachProviderInvoiceId()` merge meta instead of overwriting, preserving
  `external_id`.
- A26: `forceReissueOrphanClaim` as a distinct ops-only method with its 3 ordered preconditions.
- A27: reconcile branches on `ledgerStatus`; lost-attach-race re-reads and returns without throwing;
  age guard runs before any `listByExternalId` call.

## Self-review / concerns

- `InMemoryInvoiceEventLog` had to lose its `final` modifier to let one A27 test (lost attach race)
  compose a decorator via inheritance instead of a hand-rolled interface implementation. This is a test
  support class only (`tests/Invoicing/Support/`), not shipped in `src/`; no production code is
  affected. Acceptable but worth flagging since "no final removal without justification" is a
  reasonable general instinct.
- `forceReissueOrphanClaim`'s fallback cascade (`markIssued` fails → `markNeedsReconcile` fails →
  `attachProviderInvoiceId` fails → `InvoiceNeedsReconcile`) mirrors the equivalent cascade already
  established in `IssueInvoiceFromSource` (Task 4), but only the first-level failure path is exercised
  by a test; the second- and third-level fallbacks are copy-pattern, untested here (same gap noted as
  accepted in Task 4's report for its own cascade).
- `reconcileOrphan`'s `attachProviderInvoiceId` catch is narrowed to `InvoiceProviderIdConflict`
  specifically (not `Throwable`), so any other exception from attach still propagates — this is
  intentional per the brief ("conflicto de id → carrera perdida... re-leer"), not a bug.
- Did not touch webhooks (Task 9) or the cancel use case (Task 6), per instructions; `markCanceled` is
  implemented at the port/repository level only, ready for Task 6 to consume.
- Left `.superpowers/sdd/task-4-report.md`'s already-staged-but-uncommitted edit (present before this
  session started) untouched and unstaged so it doesn't get bundled into this commit.

## Report path

`/workspace/.superpowers/sdd/task-5-report.md`
