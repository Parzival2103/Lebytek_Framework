# Invoicing (Facturapi) Implementation Plan

> **Spec:** [`docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`](../specs/2026-08-07-invoicing-facturapi-design.md)  
> **Amendments:** this plan supersedes the spec where the two disagree (see § Design amendments).  
> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Implement **one task per subagent**. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ship an optional Framework `invoicing` vertical (OFF by default) with Domain ports, Facturapi CFDI tipo I scaffold (create/cancel/PDF/XML/email), `InvoiceableSourceInterface` orchestration, `inv_*` platform tables, consumer connection docs — **safe under partial failure** (no double-stamp), with contracts that stay extensible.

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
| A1 | Partial failure after remote create | If `createInvoice` already returned a provider id, **never** `releaseClaim`. Persist `provider_invoice_id` + status `needs_reconcile` (or equivalent) and throw `InvoiceProviderException` / typed reconcile error. Only `releaseClaim` when the provider was **not** called successfully. |
| A2 | `source_ref` authority | Issue may log many rows per `source_ref`. **Cancel / download / email require `providerInvoiceId`** unless exactly one issued row exists for that ref; otherwise throw a clear domain error (do not pick “latest” silently). |
| A3 | Orphan claims | Rows claimed without `provider_invoice_id` are incomplete. Expose `status` values: `claimed`, `issued`, `needs_reconcile`, `canceled`. Document that ops/retry after timeout must not blind-retry create; prefer reconcile or release only if confirmed no remote invoice. |
| A4 | Taxes | `InvoiceItem` carries optional tax lines; Facturapi payload maps them. Validator requires ≥1 tax line in v1 **or** an explicit `taxExempt` flag on the item (document default IVA 16% mapping in adapter tests). |
| A5 | Currency | `Money` stores currency string (uppercase). **v1 MXN-only enforcement lives in `InvoiceDraftValidator`**, not as a permanent Domain hard-fail that blocks future currencies. |
| A6 | Org settings schema | `inv_organizations` unique on `(provider_key, external_org_id)` with `external_org_id` default `''` for “default org”, so multi-RFC can land later without breaking rename. |
| A7 | Unknown provider status | `InvoiceStatus::fromProvider` must **not** silently map unknown → `Pending`. Use `Unknown` case or throw; adapter tests cover both known and unknown strings. |
| A8 | Money construction | Prefer `Money::fromMinor(int, string)`. `fromMajor` may exist for tests but must document float risk; consumer sources should pass minor units or decimal strings if added later. |
| A9 | Semver / PHP | Shipping this module requires a **major** (or clearly documented breaking) release because of PHP `>=8.2`. Do not bump packaged `version` inside these tasks; Task 12 docs must state the release prerequisite. |
| A10 | Async status | v1 records status at create time only. Webhooks / `RefreshInvoiceStatus` are **out of scope** but Domain `InvoiceStatus` and ledger columns must not assume status is immutable forever. |

---

## System map (how pieces fit)

```
Task 1  Config / vertical / composer          ← foundation gates
Task 2  Domain VOs + enums + exceptions       ← vocabulary
Task 3  Domain ports                          ← contracts everyone implements
Task 4  SQL inv_*                             ← persistence shape
Task 5  PDO repos + in-memory doubles         ← ledger + org cache
Task 6  InvoiceProviderRegistry               ← lazy multi-provider slot
Task 7  Facturapi transport + provider        ← only Infrastructure SDK touch
Task 8  InvoicingFactory                      ← config → registry (needs Task 7)
Task 9  IssueInvoiceFromSource + validator    ← core orchestration (A1)
Task 10 Cancel / download / email             ← scaffold ops (A2)
Task 11 Container bindings                    ← gated DI
Task 12 Docs + ownership                      ← consumer connection

Parallel-safe after Task 3: none recommended for first pass (keep serial).
Parallel-safe after Task 5+6 complete: Task 7 can proceed while docs stubs wait.
Factory (8) MUST wait for provider (7). Issue (9) MUST wait for 5+6+8.
```

**Data / control flow at runtime:**

```
Consumer InvoiceableSourceInterface
        → IssueInvoiceFromSource
            → tryClaim (inv_events)
            → validate draft
            → InvoiceProviderRegistry → FacturapiInvoiceProvider
            → markIssued | markNeedsReconcile (never release after remote success)
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
| `config/container.php`, `skeleton/config/container.php` | Gated DI |
| `.env.example`, `skeleton/.env.example` | FACTURAPI_* stubs |
| `database/schema/modules/invoicing.sql` | `inv_events`, `inv_organizations` |
| `src/Domain/Invoicing/**` | Ports, VOs, enums, exceptions |
| `src/Application/Invoicing/**` | Factory, registry, use cases, validator |
| `src/Infrastructure/Invoicing/**` | Facturapi adapter, PDO repos, transport |
| `tests/Invoicing/**` | Module tests |
| `docs/modules/modulo-invoicing.md` | Consumer guide |
| `docs/ARCHITECTURE-CONSUMER.md` | Ownership row + PHP 8.2 note |
| `docs/core/table-prefix-convention.md` | `inv_` (+ `pay_`) |
| `docs/core/vertical-onboarding.md` | `invoicing` toggle |

---

### Task 1: Foundation — PHP floor, Facturapi dep, vertical/config stubs

**Mission:** Turn on the empty `invoicing` module slot (OFF) and raise the PHP floor so later tasks can depend on SDK + vertical gates.

**Why this piece exists:** Every later task assumes `vertical.modules.invoicing === false` by default, a loadable `config/invoicing.php`, and Composer able to autoload `facturapi/*`. Without this, Domain/Infrastructure work has nowhere to hang.

**Depends on:** nothing (first task).  
**Unblocks:** Tasks 2–12 (config shape + purity expectations).

**Owns:**
- Modify: `composer.json`, `skeleton/composer.json`
- Create: `config/invoicing.php`, `skeleton/config/invoicing.php`
- Create: `config/modules/invoicing.php`, `skeleton/config/modules/invoicing.php`
- Modify: `config/vertical.php`, `skeleton/config/vertical.php`
- Modify: `.env.example`, `skeleton/.env.example`
- Modify: `tests/Kernel/SkeletonPurityTest.php`
- Create: `tests/Invoicing/InvoicingConfigTest.php`
- Update: `composer.lock` via composer

**Contract:**
- Produces: `vertical.modules.invoicing=false`; `config/invoicing.php` with `providers.facturapi.enabled` default false; module manifest `bootstrap_sql` → `database/schema/modules/invoicing.sql` (file created in Task 4).
- Consumes: existing EnvLoader / vertical pattern from Payments.

**Do not:** create `src/**/Invoicing/**`, SQL, or container bindings yet.

- [ ] **Step 1: Write failing tests** — `InvoicingConfigTest` (manifest keys, provider disabled, vertical OFF); extend SkeletonPurity to assert `invoicing` OFF (rename old marketing/payments-only test to include invoicing; delete duplicate).
- [ ] **Step 2: Run** `php tests/run.php Invoicing/InvoicingConfig` and `php tests/run.php Kernel/SkeletonPurity` — expect FAIL.
- [ ] **Step 3: Implement** PHP `>=8.2`, require `facturapi/facturapi-php` ^4.0, vertical key, configs (mirror Payments shape), env stubs, `composer update facturapi/facturapi-php --with-all-dependencies`.
- [ ] **Step 4: Run** same tests + `php tests/run.php Payments` — expect PASS.
- [ ] **Step 5: Commit** `feat(invoicing): PHP 8.2 floor, Facturapi dep, vertical OFF stubs`

**Done when:** InvoicingConfig + SkeletonPurity + Payments green; `invoicing` key present and false in harness and skeleton.

---

### Task 2: Domain vocabulary — VOs, enums, exceptions

**Mission:** Define the immutable language (drafts, money, statuses, errors) that ports and use cases share — with no Infrastructure types.

**Why this piece exists:** Application and Infrastructure must speak SAT-oriented Domain types, not Facturapi arrays. This task is the shared dictionary; ports (Task 3) only reference these types.

**Depends on:** Task 1 (autoload / suite folder).  
**Unblocks:** Tasks 3, 5, 7, 9, 10.

**Owns:**
- Create: `src/Domain/Invoicing/ValueObjects/{Money,Address,FiscalCustomer,InvoiceItem,InvoiceTax,InvoiceDraft,IssuedInvoice,InvoiceCancellation,OrganizationSettings}.php`
- Create: `src/Domain/Invoicing/{InvoiceStatus,PaymentForm,CfdiUse}.php`
- Create: `src/Domain/Invoicing/Exceptions/{InvoiceSourceNotFound,InvoiceDraftInvalid,InvoiceProviderException,InvoiceAlreadyProcessed,InvoiceNotCancellable,InvoiceAmbiguousSource}.php`
- Create: `tests/Invoicing/InvoiceValueObjectsTest.php`

**Contract:**
- `Money::fromMinor(int $amountMinor, string $currency)`; currency stored uppercase; **no MXN hard-fail in Money** (A5).
- `InvoiceItem` includes `taxes: InvoiceTax[]` and optional `taxExempt: bool` (A4).
- `InvoiceTax`: `rate` (e.g. 0.16), `type` (e.g. `IVA`), `factor` (`Tasa`/`Exento` as needed for mapping).
- `InvoiceStatus`: `Draft`, `Pending`, `Valid`, `Canceled`, `NeedsReconcile`, `Unknown` — `fromProvider()` maps known Facturapi strings; unknown → `Unknown` (A7).
- `IssuedInvoice` includes `providerInvoiceId`, `uuid`, `status`, optional folio/urls/sourceRef/meta.
- Exceptions: final RuntimeException subclasses listed above (include `InvoiceAmbiguousSource` for A2).

**Do not:** define ports/interfaces here; do not import SDK; do not write SQL.

- [ ] **Step 1: Write failing VO tests** (Money fromMinor; draft defaults G01/MXN; status fromProvider known + unknown; item with tax line).
- [ ] **Step 2: Run** `php tests/run.php Invoicing/InvoiceValueObjects` — expect FAIL.
- [ ] **Step 3: Implement** types as readonly/final where consistent with Payments style; Spanish enum case names OK if backed by SAT codes (`PaymentForm::TransferenciaElectronica = '03'`, etc.).
- [ ] **Step 4: Run** — expect PASS.
- [ ] **Step 5: Commit** `feat(invoicing): domain VOs, enums, and exceptions`

**Done when:** VO tests green; no file under Domain references Facturapi.

---

### Task 3: Domain ports — provider, source, ledger, org settings

**Mission:** Freeze the interfaces that Infrastructure and Application will implement/call.

**Why this piece exists:** This is the seam between layers. Task 5 implements ledger/org ports; Task 7 implements provider; Task 9 calls source + ledger + registry. Changing signatures after Task 5/7 is expensive — get them right here.

**Depends on:** Task 2.  
**Unblocks:** Tasks 5, 6, 7, 9, 10.

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
  tryClaim(provider, idempotencyKey, sourceRef, type, meta=[]): bool
  releaseClaim(provider, idempotencyKey): void
  markIssued(provider, idempotencyKey, IssuedInvoice): void
  markNeedsReconcile(provider, idempotencyKey, IssuedInvoice): void   # A1
  findByIdempotencyKey(provider, idempotencyKey): ?IssuedInvoice
  findIssuedBySourceRef(sourceRef): array  # 0..n IssuedInvoice; Application enforces A2

OrganizationSettingsRepositoryInterface:
  get(providerKey, externalOrgId=''): ?OrganizationSettings
  upsert(OrganizationSettings): void
```

**Do not:** implement PDO or Facturapi; do not add Application use cases.

- [ ] **Step 1: Reflection tests** asserting methods above exist.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** interfaces exactly.
- [ ] **Step 4: Run** — expect PASS.
- [ ] **Step 5: Commit** `feat(invoicing): domain ports for provider, source, and ledger`

**Done when:** Port tests green; `markNeedsReconcile` and `findIssuedBySourceRef` are part of the frozen contract.

---

### Task 4: Platform SQL — `inv_events` + `inv_organizations`

**Mission:** Ship idempotent DDL for the platform ledger and org cache used by Task 5.

**Why this piece exists:** Installer/module bootstrap loads `bootstrap_sql` from Task 1 manifest. Application idempotency (Task 9) is meaningless without UNIQUE(provider, idempotency_key) and status columns that support A1/A3/A6.

**Depends on:** Task 1 (manifest path). Task 3 optional but preferred so column names match port semantics.  
**Unblocks:** Task 5, install paths.

**Owns:**
- Create: `database/schema/modules/invoicing.sql`
- Create: `tests/Invoicing/InvoicingSchemaTest.php`

**Contract (schema):**

```sql
-- inv_events: claim row
-- status: claimed | issued | needs_reconcile | canceled (VARCHAR)
-- UNIQUE (provider, idempotency_key)
-- INDEX (source_ref)
-- provider_invoice_id / uuid / folio_number nullable until markIssued / markNeedsReconcile

-- inv_organizations:
-- UNIQUE (provider_key, external_org_id)
-- external_org_id VARCHAR NOT NULL DEFAULT ''
-- mode test|live, label, meta JSON
```

**Do not:** add `dom_*` tables; do not write PHP repos here.

- [ ] **Step 1: Schema string tests** (CREATE IF NOT EXISTS, unique keys, no `dom_`, status column present).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Write SQL** (MySQL 8 compatible; no illegal TEXT defaults).
- [ ] **Step 4: Run** schema + config tests — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): platform SQL inv_events and inv_organizations`

**Done when:** Schema test green; unique keys match A1/A6; status column exists.

---

### Task 5: Infrastructure persistence — PDO repos + in-memory test double

**Mission:** Implement ledger + org settings ports against `inv_*`, plus a reusable in-memory ledger for Application tests.

**Why this piece exists:** Task 9’s idempotency algorithm must run against a real claim/release/mark API. The in-memory double lets Task 9 test A1 without MySQL. PDO impl is what production consumers get via DI (Task 11).

**Depends on:** Tasks 3 + 4.  
**Unblocks:** Tasks 9, 10, 11.

**Owns:**
- Create: `src/Infrastructure/Invoicing/PdoInvoiceEventLogRepository.php`
- Create: `src/Infrastructure/Invoicing/PdoOrganizationSettingsRepository.php`
- Create: `tests/Invoicing/Support/InMemoryInvoiceEventLog.php`
- Create: `tests/Invoicing/InvoiceEventLogClaimDoubleTest.php`
- Create: `tests/Invoicing/PdoInvoiceReposReflectionTest.php`

**Contract:**
- `tryClaim`: INSERT status=`claimed`; duplicate key → false (same SQLSTATE handling as `PdoPaymentEventLogRepository`).
- `releaseClaim`: DELETE only rows still `claimed` **without** `provider_invoice_id` (refuse to delete issued/needs_reconcile).
- `markIssued`: UPDATE ids + status=`issued`.
- `markNeedsReconcile`: UPDATE ids + status=`needs_reconcile` (A1).
- `findByIdempotencyKey`: return IssuedInvoice when `provider_invoice_id` IS NOT NULL (issued **or** needs_reconcile).
- `findIssuedBySourceRef`: all rows with provider_invoice_id for that ref (ordered id ASC).
- Org repo: get/upsert with `(provider_key, external_org_id)`.
- Use `Connection::getInstance()` like Payments.

**Do not:** call Facturapi; do not implement use cases.

- [ ] **Step 1: Failing reflection + in-memory claim tests** (claim success, conflict, release, markIssued, markNeedsReconcile, release refused after mark).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** PDO + InMemory helper.
- [ ] **Step 4: Run** `php tests/run.php Invoicing` — claim/reflection PASS.
- [ ] **Step 5: Commit** `feat(invoicing): PDO event log and organization settings repos`

**Done when:** In-memory double covers A1 release rules; PDO classes implement ports.

---

### Task 6: Application — InvoiceProviderRegistry only

**Mission:** Ship a lazy registry that resolves providers by key from injectable factory closures.

**Why this piece exists:** Decouples “which providers exist” from “how Facturapi works”. Task 8 fills the registry via factory; Task 9/10 only depend on `InvoiceProviderRegistry::get()`. **Do not** construct Facturapi here.

**Depends on:** Task 3 (`InvoiceProviderInterface`).  
**Unblocks:** Tasks 8, 9, 10.

**Owns:**
- Create: `src/Application/Invoicing/InvoiceProviderRegistry.php`
- Create: `tests/Invoicing/InvoiceProviderRegistryTest.php` (includes a local `FakeInvoiceProvider`)

**Contract:**
- Constructor: `array<string, array{driver:string, factory:callable():InvoiceProviderInterface}>`
- `has`, `get` (lazy memoize), `driver`
- Unknown key → `RuntimeException` with clear message

**Do not:** create `InvoicingFactory`; do not reference `FacturapiInvoiceProvider::class`.

- [ ] **Step 1: Failing registry test** with Fake provider.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** registry only.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): invoice provider registry`

**Done when:** Registry tests green; zero imports from Infrastructure\Invoicing.

---

### Task 7: Infrastructure — Facturapi transport + provider adapter

**Mission:** Map Domain drafts ↔ Facturapi SDK behind `FacturapiTransportInterface`, including tax lines.

**Why this piece exists:** Sole place allowed to know SDK types. Task 8’s factory will `new FacturapiInvoiceProvider($cfg)`. Application never imports this namespace for business rules.

**Depends on:** Tasks 2 + 3. (Can start after Task 3 even if Task 5/6 in progress, but commit independently.)  
**Unblocks:** Task 8.

**Owns:**
- Create: `src/Infrastructure/Invoicing/Facturapi/FacturapiTransportInterface.php`
- Create: `src/Infrastructure/Invoicing/Facturapi/SdkFacturapiTransport.php`
- Create: `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php`
- Create: `tests/Invoicing/FacturapiInvoiceProviderTest.php`

**Contract:**
- Transport: `createInvoice`, `cancelInvoice`, `downloadPdf`, `downloadXml`, `sendByEmail` — arrays/strings, no Domain types.
- Provider: implements `InvoiceProviderInterface`; `key() === 'facturapi'`.
- Payload: **inline** customer + product (no Customers/Products sync API in v1); map `InvoiceTax` → Facturapi tax structure; respect `taxExempt`.
- Catch `\Facturapi\Exceptions\FacturapiException` → `InvoiceProviderException` (no secrets in message).
- Map provider status via `InvoiceStatus::fromProvider`.

**Do not:** implement `InvoicingFactory` or use cases; do not touch `inv_*`.

- [ ] **Step 1: Failing tests** with fake transport (create returns id/uuid/status; cancel; pdf/xml; tax line present in outbound payload assertion).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** transport + provider.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): Facturapi provider adapter and transport seam`

**Done when:** Provider tests green with fake transport; Domain still SDK-free.

---

### Task 8: Application — InvoicingFactory (config → registry)

**Mission:** Read `config/invoicing.php`, skip disabled drivers, build `InvoiceProviderRegistry` with Facturapi closures.

**Why this piece exists:** Connects Task 1 config + Task 6 registry + Task 7 provider. Container (Task 11) calls this factory; use cases never instantiate providers themselves.

**Depends on:** Tasks 6 + 7.  
**Unblocks:** Tasks 9, 10, 11.

**Owns:**
- Create: `src/Application/Invoicing/InvoicingFactory.php`
- Create: `tests/Invoicing/InvoicingFactoryTest.php`

**Contract:**
- Mirror `PaymentsFactory`: `resetCached()`, `registry()`, `buildProviders(array $config): InvoiceProviderRegistry`.
- Driver `facturapi` only in v1; unknown driver → throw; disabled → skip.
- Factory does **not** upsert org settings (Task 11 does that on DI build).

**Do not:** bind container; do not implement Issue/Cancel use cases.

- [ ] **Step 1: Failing factory tests** (enabled builds key; disabled omitted; unknown driver throws).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** factory.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): InvoicingFactory builds provider registry`

**Done when:** Factory tests green; registry contains facturapi only when enabled.

---

### Task 9: Application — IssueInvoiceFromSource + draft validator (A1 critical)

**Mission:** Orchestrate claim → load source draft → validate → create → markIssued, with **partial-failure safety** so a successful remote create cannot be released and retried into a double CFDI.

**Why this piece exists:** This is the product of the vertical for consumers. It is the only happy-path write that stamps invoices. Getting A1 wrong makes the module unsafe to enable.

**Depends on:** Tasks 2, 3, 5 (InMemory ledger), 6, 8 (registry via fake or factory).  
**Unblocks:** Task 12 examples; consumers.

**Owns:**
- Create: `src/Application/Invoicing/InvoiceDraftValidator.php`
- Create: `src/Application/Invoicing/IssueInvoiceFromSource.php`
- Create: `tests/Invoicing/IssueInvoiceFromSourceTest.php`

**Contract — validator:** throws `InvoiceDraftInvalid` when:
- empty/`taxId` length &lt; 12
- empty zip / legalName
- empty items
- item quantity ≤ 0 or missing productKey
- currency ≠ `MXN` (A5)
- item has no taxes and `taxExempt !== true` (A4)

**Contract — handle(`sourceRef`, `idempotencyKey`, `?providerKey`): IssuedInvoice`:**

```text
1. Resolve provider from registry (default Config invoicing.default).
2. tryClaim(provider, key, sourceRef, 'issue')
   - false + findByIdempotencyKey has provider id → return existing (replay)
   - false + no provider id → InvoiceAlreadyProcessed
3. try:
     draft = source.findDraft; null → InvoiceSourceNotFound
     validator.validate(draft)
     issued = provider.createInvoice(draft)   # remote may succeed here
     markIssued(...)
     return issued
4. catch:
     if issued/provider id already observed → markNeedsReconcile + throw (NO releaseClaim)  # A1
     else → releaseClaim + rethrow
```

Tests **must** include: happy path; missing source; invalid draft + release; idempotent replay; claim in progress; **create succeeds then markIssued fails → needs_reconcile, second handle does not call create again**.

**Do not:** implement cancel/download; do not bind container.

- [ ] **Step 1: Write failing orchestration tests** (including A1 partial failure).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** validator + use case exactly per contract.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): IssueInvoiceFromSource with safe idempotent claims`

**Done when:** All Issue tests green, especially create-then-markIssued-failure.

---

### Task 10: Application — Cancel, download, send (A2)

**Mission:** Thin use cases for cancel/PDF/XML/email with strict id resolution (no silent “latest” pick).

**Why this piece exists:** Completes the scaffold surface promised in the spec without expanding into Portal UI. Shares resolution rules so cancel cannot hit the wrong CFDI when multiple issues share a `source_ref`.

**Depends on:** Tasks 5, 6, 8, 9 (status/IssuedInvoice shapes).  
**Unblocks:** Task 11 (optional bind), Task 12 docs.

**Owns:**
- Create: `src/Application/Invoicing/InvoiceIdResolver.php`
- Create: `src/Application/Invoicing/CancelIssuedInvoice.php`
- Create: `src/Application/Invoicing/DownloadInvoiceDocument.php`
- Create: `src/Application/Invoicing/SendInvoiceByEmail.php`
- Create: `tests/Invoicing/InvoiceScaffoldUseCasesTest.php`

**Contract — InvoiceIdResolver:**
- If `providerInvoiceId` provided → use it.
- Else if `sourceRef` provided → `findIssuedBySourceRef`:
  - 1 row → use its id
  - 0 rows → `InvoiceSourceNotFound`
  - &gt;1 rows → `InvoiceAmbiguousSource` (A2)
- Cancel: resolve → `cancelInvoice` → audit claim `cancel:{id}` best-effort `markIssued` with canceled status; if invoice not cancellable map to `InvoiceNotCancellable`.
- Download: `pdf`|`xml` only.
- Send: delegate `sendByEmail`.

**Do not:** add webhooks or UI.

- [ ] **Step 1: Failing tests** (cancel ok; ambiguous source_ref; pdf/xml bytes; email invoked; not cancellable).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** resolver + three use cases.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): cancel, download, and email scaffold use cases`

**Done when:** Ambiguous `source_ref` fails closed; cancel/download/email covered.

---

### Task 11: Container — gated DI + org settings sync

**Mission:** Wire registry, PDO repos, and scaffold use cases that do not need a consumer source; sync default org row from config mode.

**Why this piece exists:** Consumers enable the vertical and get platform services. `InvoiceableSourceInterface` + `IssueInvoiceFromSource` stay **consumer-bound** (no Null source). Docs (Task 12) show the exact bind snippet.

**Depends on:** Tasks 5, 8, 10.  
**Unblocks:** Task 12; runtime enablement.

**Owns:**
- Modify: `config/container.php`, `skeleton/config/container.php`
- Create: `src/Application/Invoicing/SyncOrganizationSettingsFromConfig.php` (thin)
- Create: `tests/Invoicing/InvoicingContainerBindingsTest.php`

**Contract:**
- Bindings only inside `if (Config::get('vertical.modules.invoicing', false))` (mirror payments).
- Bind: `InvoiceProviderRegistry`, event log port → PDO, org port → PDO, `CancelIssuedInvoice`, `DownloadInvoiceDocument`, `SendInvoiceByEmail`, `InvoiceDraftValidator`.
- **Do not** bind `InvoiceableSourceInterface` or `IssueInvoiceFromSource` in Framework harness.
- After building registry, upsert default `OrganizationSettings(providerKey: facturapi, externalOrgId: '', mode from config)`.

**Do not:** enable vertical; do not add menu/permisos.

- [ ] **Step 1: Failing source-string tests** for harness + skeleton container gates.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Add gated blocks** + sync helper.
- [ ] **Step 4: Run** container tests + SkeletonPurity — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): gated container bindings and org settings sync`

**Done when:** Both containers mention the gate; purity still OFF.

---

### Task 12: Documentation — consumer connection + ownership + release note

**Mission:** Document how a consumer implements the source, binds Issue, enables the vertical, and respects A1/A2/PHP 8.2 major release.

**Why this piece exists:** Without this, the module is unusable glue. Also records ownership so Portal work does not leak into Framework.

**Depends on:** Tasks 1–11 complete (or at least APIs stable through 11).  
**Unblocks:** plan closure / human enablement on a consumer.

**Owns:**
- Create: `docs/modules/modulo-invoicing.md`
- Modify: `docs/ARCHITECTURE-CONSUMER.md`
- Modify: `docs/core/table-prefix-convention.md`
- Modify: `docs/core/vertical-onboarding.md`
- Modify: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` (status → implemented when code lands; note amendments pointer)
- Create: `tests/Invoicing/InvoicingDocsTest.php`

**Contract — modulo-invoicing.md must include:**
1. Framework vs consumer ownership
2. Env vars table
3. Enable vertical + bootstrap SQL
4. Minimal `InvoiceableSourceInterface` example
5. Exact `container.php` bindings for source + `IssueInvoiceFromSource`
6. Call sequence test-mode emission
7. Idempotency / A1 (never retry create after needs_reconcile without ops)
8. A2 resolution rules for cancel/download
9. PHP ≥8.2 **breaking release** prerequisite (A9)

**Do not:** document Portal membership/checkout flows.

- [ ] **Step 1: Failing docs presence tests**.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Write/update docs**.
- [ ] **Step 4: Run** `php tests/run.php Invoicing`, `Kernel/SkeletonPurity`, `Payments` — all PASS.
- [ ] **Step 5: Commit** `docs(invoicing): module guide and architecture ownership`

**Done when:** Docs tests green; full Invoicing suite green; no Payments regression.

---

## Spec coverage checklist

| Requirement | Task |
|-------------|------|
| Vertical OFF + config/env | 1 |
| PHP ≥8.2 + Facturapi SDK | 1, 7 |
| Domain VOs/enums/exceptions (+ taxes, Unknown, Ambiguous) | 2 |
| Domain ports (+ markNeedsReconcile, findIssuedBySourceRef) | 3 |
| `inv_*` SQL (status, org unique pair) | 4 |
| PDO + in-memory ledger | 5 |
| Registry | 6 |
| Facturapi adapter + taxes mapping | 7 |
| Factory | 8 |
| Issue + validator + A1 | 9 |
| Cancel/download/email + A2 | 10 |
| Gated container | 11 |
| Docs + prefix + ARCHITECTURE + major note | 12 |
| CFDI I only / no dom_* / no webhooks | scope of all tasks |
| Skeleton purity | 1, 11, 12 |

## Deviations / notes for implementers

1. **`IssueInvoiceFromSource` is not auto-bound** — consumer binds source + use case (Tasks 11–12).
2. **Org cache v1** stores default org (`external_org_id=''`) + mode; live Organizations API sync is future work.
3. **Inline customer/product** in Facturapi payload (no Customers/Products sync in v1).
4. **A1 is non-negotiable** — treat double-stamp prevention as a release blocker.
5. Cloud agents without PHP must install PHP ≥8.2 before harness steps.

## Estado de ejecución

- **Reconciled:** 2026-08-07 (plan restructured; not yet executed).
- **Completed / total:** 0 / 12
- **Next executable task:** Task 1 (Foundation)
- **Blockers:** none for Task 1; human chooses execution mode (subagent-driven vs sequential).
- **Note:** Original PR #91 plan (11 tasks) replaced by this 12-task agent-oriented plan with stability amendments A1–A10.
