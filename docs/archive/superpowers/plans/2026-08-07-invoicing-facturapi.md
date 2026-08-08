# Invoicing (Facturapi) Implementation Plan

> **Spec:** [`docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`](../specs/2026-08-07-invoicing-facturapi-design.md)  
> **Amendments:** this plan supersedes the spec where the two disagree (see § Design amendments).  
> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Implement **one task per subagent**. Steps use checkbox (`- [x]`) syntax.

**Goal:** Ship an optional Framework `invoicing` vertical (OFF by default) with Domain ports, Facturapi CFDI tipo I scaffold (create/cancel/PDF/XML/email), `InvoiceableSourceInterface` orchestration, `inv_*` platform tables, consumer connection docs — **safe under partial failure** (no double-stamp), with contracts that stay extensible, and **explicit mitigations** for the highest-risk technical debt.

**Architecture:** Mirror Payments: Domain ports + VOs → Application factory/registry + use cases → Infrastructure `FacturapiInvoiceProvider` (SDK behind a transport seam). Consumers implement `InvoiceableSourceInterface` (`dom_*` / tabla X → `InvoiceDraft`). No Portal business rules in this package.

**Tech Stack:** PHP `>=8.2`, Composer, `facturapi/facturapi-php` ^4, harness `php tests/run.php` + `tests/lib/microtest.php`, PDO/MySQL via module `bootstrap_sql`.

---

## How to read this plan (subagents)

Each task is a **self-contained mission**. Before coding, the assigned agent must:

1. Read **Mission**, **Why this piece exists**, **Depends on**, **Unblocks**, **Owns**, **Contract**, **Do not**.
2. Implement only files listed under **Owns** (plus tests named there).
3. Satisfy **Done when** and run the listed commands.
4. Commit with the suggested message, then stop — do not start the next task.

**Orchestrator rule:** run tasks in numeric order unless two tasks are marked **parallel-safe**. Never start a task whose **Depends on** is incomplete.

---

## Design amendments (post PR #91 critique)

These rules are **binding for implementation** even if the original spec is softer:

| # | Topic | Rule |
|---|--------|------|
| A1 | Partial failure after remote create | If `createInvoice` already returned a provider id, **never** `releaseClaim`. Persist `provider_invoice_id` + status `needs_reconcile` (or equivalent) and throw a typed error. Only `releaseClaim` when the provider was **not** called successfully. |
| A2 | `source_ref` authority | Issue may log many rows per `source_ref`. **Cancel / download / email require `providerInvoiceId`** unless exactly one issued row exists for that ref; otherwise throw `InvoiceAmbiguousSource` (do not pick “latest” silently). |
| A3 | Orphan claims | Rows claimed without `provider_invoice_id` are incomplete. Status values: `claimed`, `issued`, `needs_reconcile`, `canceled`. No blind retry of create after timeout. |
| A4 | Taxes | `InvoiceItem` carries optional tax lines; Facturapi payload maps them. Validator requires ≥1 tax line in v1 **or** `taxExempt=true`. |
| A5 | Currency | `Money` stores currency string (uppercase). **v1 MXN-only enforcement lives in `InvoiceDraftValidator`**. |
| A6 | Org settings schema | `inv_organizations` unique on `(provider_key, external_org_id)` with `external_org_id` default `''` for “default org”. |
| A7 | Unknown provider status | `InvoiceStatus::fromProvider` → `Unknown` (or throw); never silent map to `Pending`. |
| A8 | Money construction | Prefer `Money::fromMinor(int, string)`. |
| A9 | Semver / PHP | Shipping requires a **major** (or clearly documented breaking) release for PHP `>=8.2`. Do not bump packaged `version` inside code tasks; Task 14 documents the release strategy. |
| A10 | Async status | v1 records status at create time only. Do not assume status is immutable forever. |

---

## Technical debt register

| ID | Debt | Severity | Mitigation in this plan | Residual (accepted) |
|----|------|----------|-------------------------|---------------------|
| D1 | `needs_reconcile` without close path | High | **Task 10** `ReconcileIssuedInvoice` + `findNeedsReconcile` | No auto job / webhook |
| D2 | InMemory ≠ PDO behavior drift | High | **Task 5** shared ledger contract suite | Full MySQL suite still needs integration env |
| D3 | Issue use case unbound / easy to miswire | High | **Task 13** factory helper + conditional DI | Consumer still must bind `InvoiceableSourceInterface` |
| D4 | Spec vs plan dual source of truth | Med | **Task 1** amendments pointer on spec | Spec body not fully rewritten |
| D5 | Tax mapping silent bugs | Med | **Task 7** golden IVA 16% + exento fixtures | No full SAT catalog |
| D6 | Pieces green, system fragile | Med | **Task 12** smoke Issue+reconcile+fake transport | No live Facturapi CI |
| D7 | PHP 8.2 + module in one bag | Med | **Task 14** explicit release strategy note | Consumers without invoicing still pay floor when they upgrade |
| D8 | Dual Money / dual ledgers vs Payments | Low | Docs “do not share” (**Task 14**) | No shared abstraction in v1 |
| D9 | Fat `InvoiceProviderInterface` | Low | Doc note future ISP (**Task 14**) | No split in v1 |
| D10 | Async status / webhooks | Low | A10 + future name in docs | Out of scope |

---

## System map (how pieces fit)

```
Task 1   Config / vertical / composer + spec amendments pointer
Task 2   Domain VOs + enums + exceptions
Task 3   Domain ports (incl. reconcile reads)
Task 4   SQL inv_*
Task 5   PDO repos + InMemory + ledger CONTRACT suite     ← mitigates D2
Task 6   InvoiceProviderRegistry
Task 7   Facturapi transport + provider + golden tax fixtures ← mitigates D5
Task 8   InvoicingFactory
Task 9   IssueInvoiceFromSource + validator (A1)
Task 10  ReconcileIssuedInvoice                             ← mitigates D1
Task 11  Cancel / download / email (A2)
Task 12  Smoke integration (Issue + Reconcile + fake)       ← mitigates D6
Task 13  Container gated DI + conditional Issue bind          ← mitigates D3
Task 14  Docs + runbook + release strategy + debt residuals ← mitigates D4/D7/D8

Parallel-safe: Task 7 may start after Task 3 even if Task 5/6 in flight.
Factory (8) MUST wait for provider (7). Issue (9) MUST wait for 5+6+8.
Reconcile (10) MUST wait for 5+9. Smoke (12) MUST wait for 9+10+7.
Container (13) MUST wait for 10+11. Docs (14) last.
```

**Data / control flow at runtime:**

```
Consumer InvoiceableSourceInterface
        → IssueInvoiceFromSource
            → tryClaim (inv_events)
            → validate draft
            → InvoiceProviderRegistry → FacturapiInvoiceProvider
            → markIssued | markNeedsReconcile (never release after remote success)
        → on needs_reconcile: ReconcileIssuedInvoice (promote / safe return)
        → consumer persists business side in dom_*
```

---

## Global Constraints

- Spec + **Design amendments** above — do not invent `dom_*` or Portal use cases.
- PHP floor: package `composer.json` and `skeleton/composer.json` → `"php": ">=8.2"`.
- Dependency: `facturapi/facturapi-php` ^4 in root `composer.json` (same pattern as Stripe).
- Vertical key: `invoicing` — OFF in harness + skeleton `config/vertical.php`.
- SQL prefix: `inv_` only (`inv_events`, `inv_organizations`).
- Namespace: `Lebytek\Framework\{Domain,Application,Infrastructure}\Invoicing\…`
- Domain must not import `Facturapi\*` types.
- CFDI scope v1: tipo **I** only.
- Env: `FACTURAPI_SECRET_KEY`, `FACTURAPI_MODE=test`, `FACTURAPI_ENABLED=false`, `INVOICING_DEFAULT_PROVIDER=facturapi`.
- No UI/menu/settings section in this plan.
- Harness: `php tests/run.php Invoicing` must pass; after Task 1 also `SkeletonPurity` + `Payments`.

## File Structure (target)

| Path | Responsibility |
|------|----------------|
| `composer.json` / `skeleton/composer.json` | PHP ≥8.2; Facturapi dep (root) |
| `config/vertical.php`, `skeleton/config/vertical.php` | `invoicing => false` |
| `config/invoicing.php`, `skeleton/config/invoicing.php` | Provider map |
| `config/modules/invoicing.php`, `skeleton/…` | Manifest + `bootstrap_sql` |
| `config/container.php`, `skeleton/config/container.php` | Gated DI + conditional Issue |
| `.env.example`, `skeleton/.env.example` | FACTURAPI_* stubs |
| `database/schema/modules/invoicing.sql` | `inv_events`, `inv_organizations` |
| `src/Domain/Invoicing/**` | Ports, VOs, enums, exceptions |
| `src/Application/Invoicing/**` | Factory, registry, use cases, validator, reconcile |
| `src/Infrastructure/Invoicing/**` | Facturapi adapter, PDO repos, transport |
| `tests/Invoicing/**` | Module tests + contract + smoke |
| `docs/modules/modulo-invoicing.md` | Consumer guide + runbook |
| `docs/ARCHITECTURE-CONSUMER.md` | Ownership row + PHP 8.2 note |
| `docs/core/table-prefix-convention.md` | `inv_` (+ `pay_`) |
| `docs/core/vertical-onboarding.md` | `invoicing` toggle |

---

### Task 1: Foundation — PHP floor, Facturapi dep, vertical stubs + spec pointer

**Mission:** Turn on the empty `invoicing` module slot (OFF), raise the PHP floor, and point the design spec at this plan’s amendments so agents do not implement the softer pre-critique spec (D4).

**Why this piece exists:** Later tasks assume vertical/config/Composer gates. The spec pointer prevents dual-source drift from the first commit.

**Depends on:** nothing.  
**Unblocks:** Tasks 2–14.

**Owns:**
- Modify: `composer.json`, `skeleton/composer.json`
- Create: `config/invoicing.php`, `skeleton/config/invoicing.php`
- Create: `config/modules/invoicing.php`, `skeleton/config/modules/invoicing.php`
- Modify: `config/vertical.php`, `skeleton/config/vertical.php`
- Modify: `.env.example`, `skeleton/.env.example`
- Modify: `tests/Kernel/SkeletonPurityTest.php`
- Create: `tests/Invoicing/InvoicingConfigTest.php`
- Modify: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` (**header only**: status + “Plan amendments A1–A10 supersede this spec where they disagree” + link to plan)
- Update: `composer.lock` via composer

**Contract:**
- `vertical.modules.invoicing=false`; provider disabled by default; manifest `bootstrap_sql` path set.
- Spec header explicitly defers to plan amendments (D4).

**Do not:** create `src/**/Invoicing/**`, SQL, or container bindings; do not rewrite the whole spec body.

- [x] **Step 1: Write failing tests** — `InvoicingConfigTest`; SkeletonPurity includes `invoicing` OFF; optional docs assert that the spec mentions `Design amendments` or `A1`.
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Implement** composer/config/env/vertical + spec header pointer + `composer update facturapi/facturapi-php --with-all-dependencies`.
- [x] **Step 4: Run** InvoicingConfig + SkeletonPurity + Payments — PASS.
- [x] **Step 5: Commit** `feat(invoicing): PHP 8.2 floor, Facturapi dep, vertical OFF stubs`

**Done when:** Gates green; spec header points at plan amendments.

---

### Task 2: Domain vocabulary — VOs, enums, exceptions

**Mission:** Define the immutable language (drafts, money, statuses, errors) that ports and use cases share — with no Infrastructure types.

**Why this piece exists:** Shared dictionary for all later layers; ports (Task 3) only reference these types.

**Depends on:** Task 1.  
**Unblocks:** Tasks 3, 5, 7, 9–12.

**Owns:**
- Create: `src/Domain/Invoicing/ValueObjects/{Money,Address,FiscalCustomer,InvoiceItem,InvoiceTax,InvoiceDraft,IssuedInvoice,InvoiceCancellation,OrganizationSettings}.php`
- Create: `src/Domain/Invoicing/{InvoiceStatus,PaymentForm,CfdiUse}.php`
- Create: `src/Domain/Invoicing/Exceptions/{InvoiceSourceNotFound,InvoiceDraftInvalid,InvoiceProviderException,InvoiceAlreadyProcessed,InvoiceNotCancellable,InvoiceAmbiguousSource,InvoiceNeedsReconcile}.php`
- Create: `tests/Invoicing/InvoiceValueObjectsTest.php`

**Contract:**
- `Money::fromMinor(int, string)`; uppercase currency; **no MXN hard-fail in Money** (A5).
- `InvoiceItem`: `taxes: InvoiceTax[]`, `taxExempt: bool` (A4).
- `InvoiceTax`: `rate`, `type` (e.g. `IVA`), `factor` as needed for mapping.
- `InvoiceStatus`: `Draft`, `Pending`, `Valid`, `Canceled`, `NeedsReconcile`, `Unknown` (A7).
- `InvoiceNeedsReconcile` exception for Issue path after remote success + local persist failure (A1).
- `IssuedInvoice` holds provider id, uuid, status, optional folio/urls/sourceRef/meta.

**Do not:** define ports; do not import SDK; do not write SQL.

- [x] **Step 1: Failing VO tests** (fromMinor; draft defaults; status known+unknown; tax line).
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Implement** types.
- [x] **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat(invoicing): domain VOs, enums, and exceptions`

**Done when:** VO tests green; Domain has zero Facturapi imports.

---

### Task 3: Domain ports — provider, source, ledger, org settings

**Mission:** Freeze interfaces, including reconcile read APIs needed by Task 10 (D1).

**Why this piece exists:** Changing signatures after Task 5/7/10 is expensive. Include reconcile surface now so PDO and use cases do not invent ad-hoc queries later.

**Depends on:** Task 2.  
**Unblocks:** Tasks 5, 6, 7, 9, 10, 11.

**Owns:**
- Create: `src/Domain/Invoicing/InvoiceProviderInterface.php`
- Create: `src/Domain/Invoicing/InvoiceableSourceInterface.php`
- Create: `src/Domain/Invoicing/InvoiceEventLogRepositoryInterface.php`
- Create: `src/Domain/Invoicing/OrganizationSettingsRepositoryInterface.php`
- Create: `tests/Invoicing/InvoicePortsTest.php`

**Contract (method surface):**

```text
InvoiceProviderInterface:
  key(): string
  createInvoice(InvoiceDraft): IssuedInvoice
  cancelInvoice(string $providerInvoiceId, InvoiceCancellation): IssuedInvoice
  downloadPdf(string $providerInvoiceId): string
  downloadXml(string $providerInvoiceId): string
  sendByEmail(string $providerInvoiceId, string $email): void

InvoiceableSourceInterface:
  findDraft(string $sourceRef): ?InvoiceDraft

InvoiceEventLogRepositoryInterface:
  hasProcessed(provider, idempotencyKey): bool
    # true when row exists with provider_invoice_id (issued OR needs_reconcile)
  tryClaim(provider, idempotencyKey, sourceRef, type, meta=[]): bool
  releaseClaim(provider, idempotencyKey): void
  markIssued(provider, idempotencyKey, IssuedInvoice): void
  markNeedsReconcile(provider, idempotencyKey, IssuedInvoice): void
  findByIdempotencyKey(provider, idempotencyKey): ?IssuedInvoice
  findIssuedBySourceRef(sourceRef): array   # 0..n; Application enforces A2
  findNeedsReconcile(provider, limit=100): array  # list IssuedInvoice rows status=needs_reconcile (D1)

OrganizationSettingsRepositoryInterface:
  get(providerKey, externalOrgId=''): ?OrganizationSettings
  upsert(OrganizationSettings): void
```

**Do not:** implement PDO/Facturapi; do not add use cases.  
**Note (D9):** keep provider interface fat for v1; Task 14 documents possible future `SupportsInvoiceDocuments` split.

- [x] **Step 1: Reflection tests** for methods above (include `findNeedsReconcile`).
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Implement** interfaces.
- [x] **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat(invoicing): domain ports for provider, source, and ledger`

**Done when:** Ports frozen including reconcile reads.

---

### Task 4: Platform SQL — `inv_events` + `inv_organizations`

**Mission:** Idempotent DDL for ledger + org cache.

**Why this piece exists:** Bootstrap path from Task 1; UNIQUE + status columns underpin A1/A3/A6 and `findNeedsReconcile`.

**Depends on:** Task 1 (manifest); Task 3 preferred for column semantics.  
**Unblocks:** Task 5.

**Owns:**
- Create: `database/schema/modules/invoicing.sql`
- Create: `tests/Invoicing/InvoicingSchemaTest.php`

**Contract:**

```sql
-- inv_events
-- status: claimed | issued | needs_reconcile | canceled
-- UNIQUE (provider, idempotency_key)
-- INDEX (source_ref)
-- INDEX (provider, status)  -- supports findNeedsReconcile
-- provider_invoice_id / uuid / folio_number nullable until mark*

-- inv_organizations
-- UNIQUE (provider_key, external_org_id)
-- external_org_id VARCHAR NOT NULL DEFAULT ''
-- mode, label, meta JSON
```

**Do not:** add `dom_*`; do not write PHP repos.

- [x] **Step 1: Schema string tests** (tables, uniques, status, index provider+status, no `dom_`).
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Write SQL** (MySQL 8 safe).
- [x] **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat(invoicing): platform SQL inv_events and inv_organizations`

**Done when:** Schema supports claim + reconcile listing.

---

### Task 5: Infrastructure persistence — PDO + InMemory + ledger contract suite (D2)

**Mission:** Implement ledger/org ports and a **shared behavioral contract** so InMemory and PDO cannot diverge on A1 rules.

**Why this piece exists:** Application tests use InMemory; production uses PDO. Without one contract suite, green unit tests can lie (D2).

**Depends on:** Tasks 3 + 4.  
**Unblocks:** Tasks 9, 10, 12, 13.

**Owns:**
- Create: `src/Infrastructure/Invoicing/PdoInvoiceEventLogRepository.php`
- Create: `src/Infrastructure/Invoicing/PdoOrganizationSettingsRepository.php`
- Create: `tests/Invoicing/Support/InMemoryInvoiceEventLog.php`
- Create: `tests/Invoicing/Support/InvoiceEventLogContract.php` — `run_invoice_event_log_contract(InvoiceEventLogRepositoryInterface $events): void`
- Create: `tests/Invoicing/InvoiceEventLogClaimDoubleTest.php` — runs contract on InMemory
- Create: `tests/Invoicing/PdoInvoiceReposReflectionTest.php`
- Create: `tests/Invoicing/PdoInvoiceEventLogContractTest.php` — runs **same** contract on PDO when DB available; otherwise skip with clear message (document skip condition)

**Contract — ledger behavior (must hold for every implementation):**
- `tryClaim` INSERT `claimed`; duplicate → false.
- `releaseClaim` DELETE only `claimed` **without** `provider_invoice_id`; no-op/refuse for issued/needs_reconcile.
- `markIssued` → status `issued`.
- `markNeedsReconcile` → status `needs_reconcile` with provider id set.
- `findByIdempotencyKey` returns VO when provider id present (issued **or** needs_reconcile).
- `findIssuedBySourceRef` returns all with provider id (id ASC).
- `findNeedsReconcile` returns only `needs_reconcile` rows (limit honored).
- `hasProcessed` true iff provider id present.
- Status strings written by repos must be the allowlisted literals above (no free typos).

**Do not:** call Facturapi; do not implement use cases.

- [x] **Step 1: Failing contract + reflection tests**.
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Implement** PDO + InMemory + shared contract runner.
- [x] **Step 4: Run** `php tests/run.php Invoicing` — InMemory contract PASS; PDO contract PASS or intentional skip.
- [x] **Step 5: Commit** `feat(invoicing): PDO/InMemory ledger with shared contract tests`

**Done when:** A1 release/mark rules are asserted by one suite against InMemory; PDO implements the same port and is contract-tested when DB exists.

---

### Task 6: Application — InvoiceProviderRegistry only

**Mission:** Lazy registry by key from injectable factories — no Facturapi imports.

**Why this piece exists:** Decouples provider existence from SDK details. Task 8 fills it; Tasks 9–11 only call `get()`.

**Depends on:** Task 3.  
**Unblocks:** Tasks 8–11.

**Owns:**
- Create: `src/Application/Invoicing/InvoiceProviderRegistry.php`
- Create: `tests/Invoicing/InvoiceProviderRegistryTest.php` (local `FakeInvoiceProvider`)

**Contract:** `has` / `get` (memoized) / `driver`; unknown key → `RuntimeException`.

**Do not:** create `InvoicingFactory`; do not reference `FacturapiInvoiceProvider::class`.

- [x] **Step 1–5:** TDD registry; commit `feat(invoicing): invoice provider registry`

**Done when:** Registry tests green; zero Infrastructure\Invoicing imports.

---

### Task 7: Infrastructure — Facturapi adapter + golden tax fixtures (D5)

**Mission:** Domain ↔ Facturapi mapping behind a transport seam, with **golden payloads** for IVA 16% and tax-exempt items so tax mapping cannot drift silently.

**Why this piece exists:** Only Infrastructure may know SDK types. Golden fixtures mitigate D5 without a SAT catalog.

**Depends on:** Tasks 2 + 3.  
**Unblocks:** Tasks 8, 12.

**Owns:**
- Create: `src/Infrastructure/Invoicing/Facturapi/FacturapiTransportInterface.php`
- Create: `src/Infrastructure/Invoicing/Facturapi/SdkFacturapiTransport.php`
- Create: `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php`
- Create: `tests/Invoicing/FacturapiInvoiceProviderTest.php`
- Create: `tests/Invoicing/fixtures/facturapi_payload_iva16.json` (expected outbound shape keys)
- Create: `tests/Invoicing/fixtures/facturapi_payload_exento.json`

**Contract:**
- Transport methods: create/cancel/pdf/xml/email (arrays/strings).
- Provider `key() === 'facturapi'`; implements port; maps taxes + `taxExempt`.
- Tests capture outbound payload via fake transport and assert against fixtures (stable keys: customer, items[].product, taxes, payment_form, use, currency).
- Exceptions → `InvoiceProviderException` (no secrets).

**Do not:** factory/use cases/`inv_*`.

- [x] **Step 1: Failing tests** including fixture assertions.
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Implement** adapter + fixtures.
- [x] **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat(invoicing): Facturapi adapter with golden tax fixtures`

**Done when:** IVA16 + exento fixtures green; Domain still SDK-free.

---

### Task 8: Application — InvoicingFactory (config → registry)

**Mission:** Build `InvoiceProviderRegistry` from `config/invoicing.php` (enabled drivers only).

**Why this piece exists:** Connects Task 1 config + Task 6 registry + Task 7 provider for DI (Task 13).

**Depends on:** Tasks 6 + 7.  
**Unblocks:** Tasks 9–13.

**Owns:**
- Create: `src/Application/Invoicing/InvoicingFactory.php`
- Create: `tests/Invoicing/InvoicingFactoryTest.php`

**Contract:**
- Mirror `PaymentsFactory`: `resetCached()`, `registry()`, `buildProviders()`.
- Also add **`makeIssueInvoiceFromSource(InvoiceableSourceInterface $source): IssueInvoiceFromSource`** stub? → **No — add in Task 13** once Issue + Reconcile exist, to avoid forward-reference pain. Task 8 only registry building.
- No org upsert here (Task 13).

**Do not:** bind container; do not implement Issue yet.

- [x] **Step 1–5:** TDD factory; commit `feat(invoicing): InvoicingFactory builds provider registry`

**Done when:** Enabled facturapi appears; disabled omitted; unknown driver throws.

---

### Task 9: Application — IssueInvoiceFromSource + draft validator (A1)

**Mission:** Claim → source → validate → create → markIssued, with partial-failure safety (never release after remote success).

**Why this piece exists:** Core consumer-facing write path; incorrect A1 = fiscal double-stamp.

**Depends on:** Tasks 2, 3, 5, 6, 8.  
**Unblocks:** Tasks 10, 12, 14.

**Owns:**
- Create: `src/Application/Invoicing/InvoiceDraftValidator.php`
- Create: `src/Application/Invoicing/IssueInvoiceFromSource.php`
- Create: `tests/Invoicing/IssueInvoiceFromSourceTest.php`

**Contract — validator:** `InvoiceDraftInvalid` on bad taxId/zip/legalName/items/qty/productKey; currency ≠ MXN; taxes missing unless `taxExempt`.

**Contract — handle:**

```text
1. Resolve provider (default Config invoicing.default).
2. tryClaim…
   - false + findByIdempotencyKey has provider id → return existing (covers issued AND needs_reconcile replay)
   - false + no provider id → InvoiceAlreadyProcessed
3. try: findDraft → validate → createInvoice → markIssued → return
4. catch:
     if provider id already observed → markNeedsReconcile + throw InvoiceNeedsReconcile (NO release)
     else → releaseClaim + rethrow
```

**Do not:** cancel/download; do not bind container; do not implement Reconcile (Task 10).

- [x] **Step 1: Failing tests** including create-then-markIssued-failure.
- [x] **Step 2–4:** Implement until PASS.
- [x] **Step 5: Commit** `feat(invoicing): IssueInvoiceFromSource with safe idempotent claims`

**Done when:** A1 partial-failure test green; second handle does not call create again.

---

### Task 10: Application — ReconcileIssuedInvoice (D1)

**Mission:** Close the `needs_reconcile` hole with a safe promote/return use case and a list helper for ops — **no second create**.

**Why this piece exists:** A1 without reconcile leaves zombie fiscal rows and invites dangerous Portal retries (D1).

**Depends on:** Tasks 5 + 9.  
**Unblocks:** Tasks 12, 13, 14 (runbook).

**Owns:**
- Create: `src/Application/Invoicing/ReconcileIssuedInvoice.php`
- Create: `tests/Invoicing/ReconcileIssuedInvoiceTest.php`

**Contract:**

```text
ReconcileIssuedInvoice::handle(string $idempotencyKey, ?string $providerKey = null): IssuedInvoice
  - Load findByIdempotencyKey
  - null → InvoiceSourceNotFound (or InvoiceAlreadyProcessed if claim exists without id — document choice: prefer InvoiceAlreadyProcessed for claimed-only)
  - status issued (Valid/Canceled/etc. already finalized) → return as-is (idempotent)
  - status needs_reconcile / InvoiceStatus::NeedsReconcile → markIssued (promote local row) → return
  - NEVER call provider.createInvoice

Optional thin: listNeedsReconcile(providerKey, limit) wrapping events.findNeedsReconcile for docs/ops examples.
```

**Do not:** call Facturapi retrieve/API sync in v1 (no remote pull); do not add UI/cron.

- [x] **Step 1: Failing tests** (promote needs_reconcile; replay issued; never create; claimed-only path).
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Implement**.
- [x] **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat(invoicing): ReconcileIssuedInvoice closes needs_reconcile safely`

**Done when:** Promote path green; tests prove `createInvoice` is not called.

---

### Task 11: Application — Cancel, download, send (A2)

**Mission:** Thin scaffold ops with strict id resolution (fail closed on ambiguous `source_ref`).

**Why this piece exists:** Completes spec surface without Portal UI; shares `InvoiceIdResolver`.

**Depends on:** Tasks 5, 6, 8, 9.  
**Unblocks:** Task 13.

**Owns:**
- Create: `src/Application/Invoicing/InvoiceIdResolver.php`
- Create: `src/Application/Invoicing/CancelIssuedInvoice.php`
- Create: `src/Application/Invoicing/DownloadInvoiceDocument.php`
- Create: `src/Application/Invoicing/SendInvoiceByEmail.php`
- Create: `tests/Invoicing/InvoiceScaffoldUseCasesTest.php`

**Contract — InvoiceIdResolver:** provider id wins; else source_ref → 1 row ok / 0 not found / &gt;1 `InvoiceAmbiguousSource`.  
Cancel → provider cancel → audit claim best-effort; map not-cancellable. Download pdf|xml. Send delegates.

**Do not:** webhooks/UI; do not reconcile here.

- [x] **Step 1–5:** TDD; commit `feat(invoicing): cancel, download, and email scaffold use cases`

**Done when:** Ambiguous source fails closed; cancel/download/email covered.

---

### Task 12: Smoke integration — Issue + Reconcile + fake transport (D6)

**Mission:** One harness test file that wires real Application classes together (InMemory ledger + Fake/Facturapi provider with fake transport + Issue + Reconcile) to prove the vertical story end-to-end without MySQL or network.

**Why this piece exists:** Tasks 5–11 can each be green while the composition is wrong (D6). This smoke is the pre-DI integration gate.

**Depends on:** Tasks 7, 9, 10 (and 5, 6).  
**Unblocks:** Task 13 confidence; Task 14 “verified flow” docs.

**Owns:**
- Create: `tests/Invoicing/InvoicingSmokeTest.php`
- May reuse Support fakes; **do not** add production code unless a tiny test-only helper is required (prefer none).

**Contract — scenarios:**
1. Happy issue → issued status → download path optional skip.
2. Issue with markIssued failure simulation → `InvoiceNeedsReconcile` → `ReconcileIssuedInvoice` promotes → subsequent Issue same key returns issued **without** second create.
3. Ambiguous source_ref not required here (covered in Task 11).

**Do not:** hit real Facturapi; do not enable vertical in config.

- [x] **Step 1: Write failing smoke tests**.
- [x] **Step 2: Run** — expect FAIL if wiring gaps.
- [x] **Step 3: Fix only if a prior task left a composability bug** (prefer fix in owning layer + re-smoke).
- [x] **Step 4: Run** `php tests/run.php Invoicing/InvoicingSmoke` — PASS.
- [x] **Step 5: Commit** `test(invoicing): smoke Issue+Reconcile against fake transport`

**Done when:** Smoke proves A1 → reconcile → no double create.

---

### Task 13: Container — gated DI, org sync, conditional Issue bind (D3)

**Mission:** Wire platform services when vertical ON; bind `IssueInvoiceFromSource` **only if** consumer registered `InvoiceableSourceInterface`; expose factory helper to avoid hand-wired graphs.

**Why this piece exists:** Asymmetric DX (cancel bound, Issue not) causes miswiring debt (D3). Conditional bind + helper keeps Null-source out of harness while making the happy path hard to get wrong.

**Depends on:** Tasks 5, 8, 10, 11.  
**Unblocks:** Task 14; runtime enablement.

**Owns:**
- Modify: `config/container.php`, `skeleton/config/container.php`
- Modify: `src/Application/Invoicing/InvoicingFactory.php` — add `makeIssueInvoiceFromSource(InvoiceableSourceInterface $source): IssueInvoiceFromSource` (and optionally `makeReconcileIssuedInvoice(): ReconcileIssuedInvoice`)
- Create: `src/Application/Invoicing/SyncOrganizationSettingsFromConfig.php`
- Create: `tests/Invoicing/InvoicingContainerBindingsTest.php`
- Create: `tests/Invoicing/InvoicingFactoryIssueHelperTest.php`

**Contract:**
- Gate: `vertical.modules.invoicing`.
- Always (when gated): registry, event log PDO, org PDO, validator, Cancel/Download/Send, **ReconcileIssuedInvoice**.
- Conditional: if container has `InvoiceableSourceInterface`, bind `IssueInvoiceFromSource` via factory helper.
- Harness: **do not** bind a Null source; tests assert conditional snippet exists in container source.
- Org upsert default `(facturapi, external_org_id='')` after registry build.
- Helper unit test: `makeIssueInvoiceFromSource` returns instance wired with registry+events+validator.

**Do not:** enable vertical; no menu/permisos.

- [x] **Step 1: Failing container string tests + helper test**.
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Implement** bindings + helper + sync.
- [x] **Step 4: Run** container/helper + SkeletonPurity — PASS.
- [x] **Step 5: Commit** `feat(invoicing): gated DI, reconcile bind, conditional Issue helper`

**Done when:** Reconcile bound; Issue conditional; helper tested; purity OFF.

---

### Task 14: Documentation — guide, runbook, release strategy, residuals

**Mission:** Consumer connection docs + ops runbook for `needs_reconcile` + PHP release strategy + explicit accepted debt (D7/D8/D9/D10).

**Why this piece exists:** Module is unusable glue without docs; amendments must live outside the plan archive; release note prevents surprise majors.

**Depends on:** Tasks 1–13 (APIs stable).  
**Unblocks:** plan closure / consumer enablement.

**Owns:**
- Create: `docs/modules/modulo-invoicing.md`
- Modify: `docs/ARCHITECTURE-CONSUMER.md`
- Modify: `docs/core/table-prefix-convention.md`
- Modify: `docs/core/vertical-onboarding.md`
- Modify: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` (status → plan-ready/implemented; keep amendments pointer)
- Create: `tests/Invoicing/InvoicingDocsTest.php`

**Contract — modulo-invoicing.md must include:**
1. Framework vs consumer ownership (no shared Money with Payments — D8)
2. Env vars table
3. Enable vertical + bootstrap SQL
4. Minimal `InvoiceableSourceInterface` example
5. Bind source + rely on conditional `IssueInvoiceFromSource` **or** `InvoicingFactory::makeIssueInvoiceFromSource`
6. Test-mode emission sequence
7. **Runbook A1/D1:** on `InvoiceNeedsReconcile` → call `ReconcileIssuedInvoice`; never re-issue with new idempotency key until ops confirms
8. A2 resolution rules
9. **Release strategy (D7/A9):** recommend major for PHP ≥8.2; note Facturapi follows Stripe `require` pattern (accepted dep weight)
10. Future: webhooks / `RefreshInvoiceStatus` (D10); optional ISP split for documents (D9)
11. Invariants list promoting A1–A3 (survive plan archive)

**Do not:** Portal membership/checkout docs.

- [x] **Step 1: Failing docs tests** (source bind, reconcile runbook, PHP ≥8.2, `inv_`, Invoicing ownership).
- [x] **Step 2: Run** — expect FAIL.
- [x] **Step 3: Write/update docs**.
- [x] **Step 4: Run** `php tests/run.php Invoicing`, `Kernel/SkeletonPurity`, `Payments` — all PASS.
- [x] **Step 5: Commit** `docs(invoicing): module guide, reconcile runbook, release notes`

**Done when:** Docs tests green; full Invoicing suite green; Payments untouched.

---

## Spec coverage checklist

| Requirement | Task |
|-------------|------|
| Vertical OFF + config/env | 1 |
| Spec amendments pointer (D4) | 1, 14 |
| PHP ≥8.2 + Facturapi SDK | 1, 7, 14 |
| Domain VOs/enums/exceptions | 2 |
| Domain ports + reconcile reads | 3 |
| `inv_*` SQL + status index | 4 |
| PDO + InMemory + **contract suite (D2)** | 5 |
| Registry | 6 |
| Facturapi + **golden taxes (D5)** | 7 |
| Factory | 8 |
| Issue + validator + A1 | 9 |
| **Reconcile (D1)** | 10 |
| Cancel/download/email + A2 | 11 |
| **Smoke composition (D6)** | 12 |
| Gated DI + **conditional Issue (D3)** | 13 |
| Docs + runbook + release + residuals | 14 |
| CFDI I only / no dom_* / no webhooks | scope |
| Skeleton purity | 1, 13, 14 |

## Deviations / notes for implementers

1. **Consumer must bind `InvoiceableSourceInterface`**; Framework binds Issue only when that port is present (Task 13 helper).
2. **Org cache v1** uses `external_org_id=''` sentinel; map `null → ''` only in PDO.
3. **Inline customer/product** in Facturapi payload (no Customers/Products sync in v1).
4. **A1 + Task 10 reconcile are release blockers** — do not ship Issue without Reconcile + smoke.
5. **PDO contract tests** may skip without DB; InMemory contract is mandatory in unit harness.
6. Cloud agents need PHP ≥8.2 before harness steps.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-08T12:40:00Z |
| Framework `origin/main` verificado | `5bf0863f45116b3e574a085c0dca2bed46ed983a` |
| Tareas completadas / totales | 14 / 14 |
| Siguiente tarea ejecutable | ninguna — plan completo |
| Prerrequisitos | Cumplidos |
| Bloqueos | ninguno |
| Estado | **completo** — archivado |
| Evidencia | PR [#99](https://github.com/Parzival2103/Lebytek_Framework/pull/99) merge `21edf26`; módulo en `src/Domain/Invoicing/`, `src/Application/Invoicing/`, `src/Infrastructure/Invoicing/`, SQL `database/schema/modules/invoicing.sql`, suite `tests/Invoicing/**` (14 tasks Tasks 1–14 verificados en tip) |

**Nota:** Continuación de hardening en plan `2026-08-08-invoicing-facturapi-production-hardening.md` (0/10).
