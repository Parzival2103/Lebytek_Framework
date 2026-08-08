# Invoicing (Facturapi) Production Hardening Plan

> **Spec / v1 plan:** [`docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`](../specs/2026-08-07-invoicing-facturapi-design.md) · [`docs/superpowers/plans/2026-08-07-invoicing-facturapi.md`](2026-08-07-invoicing-facturapi.md)  
> **Amendments:** A11+ in this plan supersede A1/A3/A10 and residual D1/D10 where they disagree (see § Design amendments).  
> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Implement **one task per subagent**. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Close the audited pre-production gaps in the existing Invoicing vertical so CFDI tipo I issue/cancel/reconcile is safe under timeouts, secret leakage, mode/key mismatch, incomplete cancel, missing RBAC contract, and async status — without inventing Portal UI or `dom_*` business.

**Architecture:** Keep the Payments-mirrored stack (Domain ports/VOs → Application use cases/factory → Infrastructure Facturapi transport + PDO `inv_*`). Harden **idempotency and claim lifecycle** first; add `retrieve` so reconcile/webhooks can verify remote state; keep HTTP webhook endpoints and RBAC route wiring in the **consumer**, with Framework owning signature validation + apply-event + permission slugs/docs.

**Tech Stack:** PHP `>=8.2`, `facturapi/facturapi-php` ^4 (`Invoices::retrieve`, `Webhooks::validateSignature` HMAC-SHA256 local/`Facturapi-Signature`), harness `php tests/run.php`, PDO/MySQL `inv_*`, vertical `invoicing` OFF by default.

**Source:** Pre-production audit findings against code at `origin/main` (`60477dc`).  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` base `main`; implement on a `cursor/*` feature branch.  
**Modo:** hardening continuation of v1 scaffold (module code already on `main`).

---

## How to read this plan (subagents)

Each task is a **self-contained mission**. Before coding, the assigned agent must:

1. Read **Mission**, **Why**, **Depends on**, **Unblocks**, **Owns**, **Contract**, **Do not**.
2. Implement only files listed under **Owns** (plus tests named there).
3. Satisfy **Done when** and run the listed commands.
4. Commit with the suggested message, then stop — do not start the next task.

**Orchestrator rule:** run tasks in numeric order unless marked **parallel-safe**. Never start a task whose **Depends on** is incomplete.

**First executable task:** Task 1 (mode/key fail-fast).

---

## Verified code facts (do not re-litigate)

| Finding | Evidence on `main` |
|---------|--------------------|
| Timeout after remote stamp → `$observedInvoice` stays `null` → `releaseClaim` | `IssueInvoiceFromSource` assigns `$observedInvoice` only after successful `createInvoice`; catch else-branch calls `releaseClaim` |
| Docs A3 “no blind retry” ≠ code | Docs invariant A3 vs `IssueInvoiceFromSource` lines that release on any non-observed failure |
| `InvoiceNeedsReconcile` has no typed id | `src/Domain/Invoicing/Exceptions/InvoiceNeedsReconcile.php` empty subclass of `RuntimeException` |
| No `retrieve` on port/transport | `InvoiceProviderInterface` + `FacturapiTransportInterface` lack get/retrieve; SDK has `Invoices::retrieve($id)` |
| Reconcile promotes locally only | `ReconcileIssuedInvoice::handle` calls `markIssued` without provider call |
| `hydrate` forces `issued` → `Valid` | `PdoInvoiceEventLogRepository::domainStatus` / `InMemoryInvoiceEventLog::domainStatus` |
| Cancel claims **after** remote success | `CancelIssuedInvoice::handle` then `auditCancelClaim`; never writes issue row to `canceled` |
| Cancel motive not validated | `InvoiceCancellation` accepts any string; no `01`→substitution rule |
| Secret redact incomplete | `sanitizeSecretTokens` only `sk_(test\|live)_…` |
| PDO `encodeMeta` has no denylist | raw `json_encode($meta)` in event + org repos |
| Mode/key not enforced | `InvoicingFactory::buildProviders` passes any `secret_key`; `fromSecretKey` does not check prefix/empty |
| RBAC empty | `config/modules/invoicing.php` `'permisos' => []`; SQL has no `auth_permisos` inserts |
| Webhooks out of scope in v1 | D10 residual; Payments exposes `parseWebhook` on gateway, no Framework HTTP controller |

**Facturapi API evidence (external):** create accepts `idempotency_key` + `external_id`; list filters by `external_id`; cancel requires `motive` `01|02|03|04` and `substitution` when `01`; webhook header `Facturapi-Signature`; PHP SDK `Webhooks::validateSignature` supports local HMAC-SHA256.

---

## Coverage matrix (🔥 → task → test → acceptance)

| Prioridad | Archivos clave | Tarea | Test (mínimo) | Criterio de aceptación |
|-----------|----------------|-------|---------------|------------------------|
| 1 Timeout / no double stamp | `IssueInvoiceFromSource`, provider `mapDraft`, transport | **3** | Timeout post-create: claim **not** released; second handle does **not** call create | A3 real closed; Facturapi `idempotency_key` sent |
| 2 Last-resort id + typed exception + remote reconcile | `InvoiceNeedsReconcile`, event log, `ReconcileIssuedInvoice`, retrieve | **4**, **5** | Dual mark failure → typed id recoverable; reconcile retrieves remote before promote | Ops can recover without parsing message strings |
| 3 Mode ↔ key prefix + empty key | `InvoicingFactory`, `FacturapiInvoiceProvider::fromSecretKey` | **1** | `mode=test` rejects `sk_live_`; empty key + enabled fails fast | Misconfig cannot stamp silently |
| 4 Cancel complete | `CancelIssuedInvoice`, event log `markCanceled`, `InvoiceCancellation` | **6** | Cancel updates issue row `canceled`; idempotent replay; motive `01` requires substitution | Schema `canceled` written; no blind re-cancel |
| 5 Secret redact + meta denylist | provider sanitize, PDO `encodeMeta` | **2** | `sk_user_*` / `Bearer` redacted; meta keys stripped | No secret persistence/leak in exceptions/meta |
| 6 RBAC slugs + consumer rule | module manifest, SQL, docs | **8** | Manifest/docs assert slugs; schema inserts | Consumer routes must check slugs |
| 7 Webhooks mínimo seguro | signature validator, apply-event UC, config/docs | **9** | Signature fail-closed; idempotent apply; no full fiscal payload logged | Consumer wires HTTP; Framework validates+applies |
| 8 CFDI extras (no full SAT) | `InvoiceDraftValidator`, `mapDraft`, VOs/enums | **7** | `tax_system` / `unit_key` / `payment_method` validated+mapped | Invalid drafts rejected before create |
| 9 CI suite for tests 1–7 + secrets | tests under `tests/Invoicing/**` | **1–9**, gate **10** | `php tests/run.php Invoicing` green | Audit regression set present |

---

## Design amendments (A11+)

These rules are **binding** and supersede softer v1 text where noted:

| # | Topic | Rule | Supersedes |
|---|--------|------|------------|
| A11 | Ambiguous create (timeout/network after request left the process) | If `createInvoice` was **invoked** and failed without a returned `IssuedInvoice`, **do not** `releaseClaim`. Leave claimed-without-id; retry with same key must **not** call create again (`InvoiceAlreadyProcessed` or typed ambiguous-claim error). Only `releaseClaim` when failure happens **before** provider create is called (source missing, validation, registry). | Soft reading of A1/A3 that only protected the “observed id” path |
| A12 | Remote create idempotency | `FacturapiInvoiceProvider::mapDraft` / create payload **must** send Facturapi `idempotency_key` (= local issue idempotency key) and `external_id` (= `sourceRef`, truncated to Facturapi limit ≤100). Port signature may gain an explicit idempotency argument. | v1 “inline payload only” |
| A13 | Typed `InvoiceNeedsReconcile` | Exception **must** expose `providerInvoiceId(): string` (and preferably `idempotencyKey()`, `providerKey()`). Message may still include the id; typed accessors are authoritative for ops/reconcile. | Empty exception class |
| A14 | Last-resort persist | After remote success, if `markIssued` fails then `markNeedsReconcile` fails, attempt a final `attachProviderInvoiceId` (or equivalent) that writes `provider_invoice_id` + `needs_reconcile` with stripped meta. If that also fails, still throw typed `InvoiceNeedsReconcile` — never `releaseClaim`. | Message-only recovery |
| A15 | Reconcile verifies remote | `ReconcileIssuedInvoice` for `NeedsReconcile` **must** `retrieve` remote invoice (when id known) and promote using remote-mapped `IssuedInvoice`. If local row lacks id but typed exception/ops supplies id, attach then retrieve. Do not blindly `markIssued` a stale local VO. | Task 10 v1 “no remote pull” |
| A16 | Pending status fidelity | Persist/hydrate must not coerce provider `pending` → `Valid`. Ledger operational status may remain `issued`/`needs_reconcile`, but `IssuedInvoice::status()` must reflect `meta.provider_status` (or dedicated column) via `InvoiceStatus::fromProvider`. | Silent `domainStatus` map |
| A17 | Cancel lifecycle | Claim `cancel:{providerInvoiceId}` **before** remote cancel. On success, mark the **issue** ledger row `canceled` (by provider invoice id / source). Validate motive `01\|02\|03\|04`; motive `01` requires non-empty `substitution`. If cancel claim already exists and remote/local already canceled, return safely without second remote cancel when evidence says canceled. | Best-effort post-cancel audit only |
| A18 | Mode/key coupling | When provider enabled: non-empty secret; `mode=test` ⇒ `sk_test_…`; `mode=live` ⇒ `sk_live_…`. Fail in factory/`fromSecretKey`. Keep single `FACTURAPI_SECRET_KEY` (one deployment = one mode); do **not** add dual test/live env keys in this plan (YAGNI; document that staging/prod use separate envs). | Silent empty/mismatched keys |
| A19 | Webhook ownership | Framework: signature validation + apply-status use case + env/docs. Consumer: HTTP route (CSRF-exempt), raw body, header `Facturapi-Signature`, RBAC not required for signed webhook (shared-secret auth). Never log full fiscal payload. | D10 “out of scope” for async |
| A20 | RBAC platform contract | Manifest + bootstrap SQL define slugs; docs state consumer **must** protect mutating/download routes with those slugs. No Portal UI in this plan. | Empty `permisos` |

---

## Technical debt register (update)

| ID | Debt | Severity | Mitigation in this plan | Residual (accepted) |
|----|------|----------|-------------------------|---------------------|
| D1 | `needs_reconcile` without remote verify | High | **Tasks 4–5** typed id + retrieve + remote reconcile | No auto cron worker |
| D10 | Async status / webhooks | High→Med | **Task 9** mínimo seguro (signature + apply + docs) | No Framework HTTP controller; no dashboard of webhook deliveries; no live Facturapi CI |
| D11 | Claimed-without-id after timeout (orphan claim) | High | **Task 3** keep claim + Facturapi idempotency_key; optional list-by-`external_id` helper if cheap in Task 5 | Manual ops attach id if remote id unknown and list ambiguous |
| D12 | Pending coerced to Valid on hydrate | High | **Task 5** A16 | Full provider_status column index optional later |
| D13 | Cancel does not update issue row / claim-after | High | **Task 6** | Cancellation receipt download still out of scope |
| D14 | Secret/`Bearer`/`sk_user` leak surface | High | **Task 2** | SDK may still hold secrets in memory |
| D15 | Empty RBAC contract | Med | **Task 8** | Consumer must assign roles; no menu entries |
| D5 | Tax/SAT mapping bugs | Med | **Task 7** minimal fields | Full SAT catalog still out of product scope |
| D2 | InMemory ≠ PDO drift | Med | Extend contract suite in Tasks 5–6 | Full MySQL CI still env-gated |
| D8/D9 | Dual Money / fat provider ISP | Low | unchanged | Accepted |

---

## System map (dependency order)

```
Task 1  Mode/key fail-fast                          ← first executable
Task 2  Secret redact + meta denylist               ← parallel-safe with 1
Task 3  A11/A12 issue timeout + remote idempotency  ← depends 1 (factory stable)
Task 4  Typed InvoiceNeedsReconcile + last-resort attach
Task 5  retrieve + pending hydrate + remote reconcile  ← depends 4
Task 6  Cancel complete (claim-before, markCanceled, motives)
Task 7  CFDI validator/mapping extras                 ← parallel-safe after 3
Task 8  RBAC slugs + SQL + docs contract              ← parallel-safe with 7
Task 9  Webhooks signature + apply-event              ← depends 5
Task 10 Docs/runbook/debt closure + full suite gate   ← last
```

**Runtime happy path after hardening:**

```
IssueInvoiceFromSource
  tryClaim → validate → create(idempotency_key, external_id)
  success → markIssued (preserve provider_status in meta)
  observed success + local fail → markNeedsReconcile / attachProviderInvoiceId → InvoiceNeedsReconcile(typed id)
  create attempted + ambiguous fail → KEEP claim → typed error (no release)

ReconcileIssuedInvoice
  load local → retrieve(remote) → markIssued from remote VO

CancelIssuedInvoice
  resolve id → tryClaim(cancel:id) → validate motive → cancel remote → markCanceled(issue row)

Consumer webhook
  raw body + Facturapi-Signature → Framework validate → ApplyInvoiceProviderEvent → inv_events status sync
```

---

## Global Constraints

- Platform only: `src/`, `database/`, `skeleton/`, `config/`, `tests/`, `docs/`.
- No Marketing, memberships, checkout, CRM fiscal Portal, `dom_*`.
- Domain must not import `Facturapi\*`.
- Vertical `invoicing` remains OFF by default.
- SQL prefix `inv_` only; schema changes via idempotent bootstrap (and migration entry in module manifest if required by install path).
- Never edit `vendor/`.
- Never base work on `feature/backoffice-api-integration`.
- Prefer minimal diffs coherent with Payments + current Invoicing.
- Verify: `php tests/run.php Invoicing`; also `SkeletonPurity` / `Payments` when touching shared gates/config patterns.

## File Structure (target deltas)

| Path | Responsibility |
|------|----------------|
| `src/Domain/Invoicing/InvoiceProviderInterface.php` | Add `retrieveInvoice(string $providerInvoiceId): IssuedInvoice` |
| `src/Domain/Invoicing/InvoiceEventLogRepositoryInterface.php` | Add `attachProviderInvoiceId…`, `markCanceled…`, optional `findByProviderInvoiceId` |
| `src/Domain/Invoicing/Exceptions/InvoiceNeedsReconcile.php` | Typed accessors |
| `src/Domain/Invoicing/Exceptions/InvoiceAmbiguousCreate.php` (new) | Timeout/orphan-claim typed error |
| `src/Domain/Invoicing/ValueObjects/InvoiceCancellation.php` | Motive/substitution invariants |
| `src/Domain/Invoicing/PaymentMethod.php` (new enum) | `PUE`/`PPD` |
| `src/Domain/Invoicing/ValueObjects/InvoiceDraft.php` | Optional `paymentMethod` |
| `src/Application/Invoicing/IssueInvoiceFromSource.php` | A11/A12/A14 behavior |
| `src/Application/Invoicing/ReconcileIssuedInvoice.php` | Remote retrieve before promote |
| `src/Application/Invoicing/CancelIssuedInvoice.php` | Claim-before + markCanceled |
| `src/Application/Invoicing/InvoiceDraftValidator.php` | tax_system / unit_key / payment_method |
| `src/Application/Invoicing/InvoicingFactory.php` | Mode/key enforcement; webhook secret wiring |
| `src/Application/Invoicing/ApplyInvoiceProviderEvent.php` (new) | Webhook apply use case |
| `src/Infrastructure/Invoicing/Facturapi/*` | `retrieve`, optional `listByExternalId`, webhook validate seam |
| `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php` | map retrieve/cancel/idempotency; redact; parseWebhook or delegate |
| `src/Infrastructure/Invoicing/FacturapiWebhookSignature.php` (new) | Local HMAC validate (SDK or pure PHP) |
| `src/Infrastructure/Invoicing/PdoInvoiceEventLogRepository.php` | attach/markCanceled/hydrate pending; meta denylist |
| `tests/Invoicing/Support/InMemoryInvoiceEventLog.php` | Mirror PDO contract |
| `config/invoicing.php`, `.env.example`, skeleton copies | `FACTURAPI_WEBHOOK_SECRET`, mode docs |
| `config/modules/invoicing.php`, `database/schema/modules/invoicing.sql` | RBAC slugs |
| `docs/modules/modulo-invoicing.md` | Hardening runbook + consumer RBAC/webhook wiring |
| `tests/Invoicing/**` | Audit regression suite |

---

### Task 1: Fail-fast `FACTURAPI_MODE` ↔ key prefix + empty secret

**Mission:** Reject enabled Facturapi providers with empty/invalid secrets or mode/prefix mismatch before any network call.

**Why this piece exists:** A live key in test mode (or empty key with `enabled=true`) is a production foot-gun; factory currently constructs providers unchecked.

**Depends on:** nothing.  
**Unblocks:** Task 3 (safe create path assumes configured provider).

**Owns:**
- Modify: `src/Application/Invoicing/InvoicingFactory.php`
- Modify: `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php` (`fromSecretKey`)
- Modify: `src/Infrastructure/Invoicing/Facturapi/SdkFacturapiTransport.php` (optional empty-key guard)
- Modify: `tests/Invoicing/InvoicingFactoryTest.php`
- Create or extend: `tests/Invoicing/FacturapiSecretKeyValidationTest.php`
- Docs touch deferred to Task 10 except if `.env.example` comment needed for mode rule (allowed here)

**Contract:**
- When building enabled `facturapi` provider: `secret_key` trim non-empty.
- `mode` normalized to `test|live` (default `test`).
- `test` ⇒ secret must start with `sk_test_`; `live` ⇒ `sk_live_`.
- Throw `RuntimeException` (or dedicated `InvoiceProviderException`) with **no secret value** in message.
- **Decision (A18):** keep single `FACTURAPI_SECRET_KEY` + `FACTURAPI_MODE`; do not introduce `FACTURAPI_SECRET_KEY_TEST/LIVE` — deployments already isolate envs; dual keys add config surface without solving cross-mode bugs.

**Do not:** change issue/cancel logic; do not enable vertical; do not add webhook secret yet (Task 9).

- [ ] **Step 1: Write failing tests** — enabled + empty key; `mode=test` + `sk_live_x`; `mode=live` + `sk_test_x`; happy `sk_test_` + test.
- [ ] **Step 2: Run** `php tests/run.php Invoicing/InvoicingFactory` (and new file) — expect FAIL.
- [ ] **Step 3: Implement** validation in factory before registering factory closure **and** in `fromSecretKey` (defense in depth).
- [ ] **Step 4: Run** focused tests — PASS.
- [ ] **Step 5: Commit** `fix(invoicing): enforce Facturapi mode/key prefix and reject empty secrets`

**Done when:** Mismatch/empty fail fast; valid test key still builds registry entry.

---

### Task 2: Broaden secret redaction + meta denylist

**Mission:** Stop `sk_user_*`, `Bearer …`, and secret-like meta keys from leaking via exceptions or `inv_*`.meta JSON.

**Why this piece exists:** Current sanitize only covers `sk_test_`/`sk_live_`; PDO encodes meta verbatim.

**Depends on:** nothing (**parallel-safe** with Task 1).  
**Unblocks:** safer logging for Tasks 3–9.

**Owns:**
- Modify: `FacturapiInvoiceProvider::sanitizeSecretTokens` (make package-private testable or extract small helper if needed)
- Modify: `PdoInvoiceEventLogRepository::encodeMeta`
- Modify: `PdoOrganizationSettingsRepository::encodeMeta`
- Modify: `tests/Invoicing/FacturapiInvoiceProviderTest.php`
- Create: `tests/Invoicing/InvoiceMetaDenylistTest.php` (reflection or package-visible helper)

**Contract:**
- Redact at least: `sk_(test|live|user)_[A-Za-z0-9]+`, case-insensitive `Bearer\s+\S+`.
- Denylist meta keys (case-insensitive substring match): `secret`, `token`, `password`, `api_key`, `authorization`, `webhook_secret`.
- Stripped keys dropped (or replaced with `[redacted]`); nested arrays scrubbed one level deep minimum.
- Existing `sk_test` leak test still passes; add `sk_user_` + Bearer cases.

**Do not:** change claim/issue semantics; do not log payloads.

- [ ] **Step 1: Failing tests** for redact + denylist.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** sanitize + encodeMeta scrubbing (shared private trait/helper under Infrastructure\Invoicing OK).
- [ ] **Step 4: Run** Invoicing provider + meta tests — PASS.
- [ ] **Step 5: Commit** `fix(invoicing): redact sk_user/Bearer and denylist secret meta keys`

**Done when:** Regression tests green; no secret substrings in thrown previous messages or encoded meta fixtures.

---

### Task 3: Close A3 real — no release after ambiguous create + remote idempotency

**Mission:** Eliminate double-stamp when `createInvoice` times out after Facturapi already stamped; pass Facturapi `idempotency_key`/`external_id`.

**Why this piece exists:** Highest fiscal risk. Code releases claims whenever `$observedInvoice` is null, including post-create transport timeouts.

**Depends on:** Task 1.  
**Unblocks:** Tasks 4–5 (orphan claims become intentional, not accidental).

**Owns:**
- Modify: `IssueInvoiceFromSource.php`
- Modify: `InvoiceProviderInterface` + all implementers/fakes in tests (`createInvoice` signature — prefer `createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice`)
- Modify: `FacturapiInvoiceProvider::createInvoice` / `mapDraft`
- Modify: golden fixtures `tests/Invoicing/fixtures/facturapi_payload_*.json` (add `idempotency_key` + `external_id`)
- Modify: `tests/Invoicing/IssueInvoiceFromSourceTest.php`
- Create: `src/Domain/Invoicing/Exceptions/InvoiceAmbiguousCreate.php` (or reuse `InvoiceAlreadyProcessed` with distinct message — prefer **new typed** exception for clarity)
- Update: `InvoicePortsTest` for new method signature

**Contract — Issue catch taxonomy:**

```text
tryClaim ok
try:
  findDraft / validate failures → releaseClaim + rethrow
  createInvoice(draft, idempotencyKey) invoked:
     success → markIssued → return
     returns IssuedInvoice then local mark fails → Task 4 path (needs_reconcile)
     throws after invoke (timeout/network/provider) WITHOUT IssuedInvoice:
        DO NOT releaseClaim
        throw InvoiceAmbiguousCreate(provider, idempotencyKey, sourceRef, previous)
replay same key while claimed-without-id → InvoiceAlreadyProcessed / AmbiguousCreate (NO create)
```

**Contract — payload:** Facturapi create body includes `idempotency_key` and `external_id` (`sourceRef` truncated ≤100).

**Do not:** implement retrieve yet (Task 5); do not change cancel.

- [ ] **Step 1: Failing test** — fake provider throws after “create attempted” flag; assert `releaseCalls === 0`, second handle `createCalls` still 1; fixture asserts idempotency/external fields.
- [ ] **Step 2: Run** `php tests/run.php Invoicing/IssueInvoiceFromSource` — FAIL.
- [ ] **Step 3: Implement** A11/A12.
- [ ] **Step 4: Run** Issue + Facturapi provider + ports — PASS.
- [ ] **Step 5: Commit** `fix(invoicing): keep claim on ambiguous create and send Facturapi idempotency_key`

**Done when:** Audit test (1) green: timeout post-create does not release; no second create.

---

### Task 4: Typed `InvoiceNeedsReconcile` + last-resort `attachProviderInvoiceId`

**Mission:** Make dual local persist failure recoverable via typed id and a last-resort ledger attach.

**Why this piece exists:** Today id survives only in exception message; both mark paths can fail and leave claimed-without-id even after remote success.

**Depends on:** Task 3.  
**Unblocks:** Task 5.

**Owns:**
- Modify: `InvoiceNeedsReconcile.php` — constructor `(string $message, string $providerInvoiceId, string $providerKey, string $idempotencyKey, ?Throwable $previous = null)` + accessors
- Modify: `InvoiceEventLogRepositoryInterface` + PDO + InMemory + contract suite — add  
  `attachProviderInvoiceId(string $provider, string $idempotencyKey, string $providerInvoiceId, array $meta = []): void`  
  (sets `provider_invoice_id`, status `needs_reconcile`, merges safe meta; fail closed if conflicting id)
- Modify: `IssueInvoiceFromSource` catch path: markIssued fail → markNeedsReconcile fail → attachProviderInvoiceId → throw typed exception
- Modify: all tests constructing `InvoiceNeedsReconcile`
- Extend: `IssueInvoiceFromSourceTest` dual-failure case to assert `providerInvoiceId()` and that attach was attempted

**Contract:**
- Typed accessors always non-empty for provider invoice id on throw-after-remote-success.
- Attach is best-effort after both marks fail; if attach fails, still throw typed exception (no release).
- Update `InvoicePortsTest` for new repo method.

**Do not:** call retrieve yet; do not promote in Issue.

- [ ] **Step 1: Failing tests** — dual mark failure exposes typed id; attach writes id into ledger (InMemory).
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement**.
- [ ] **Step 4: Run** Issue + contract — PASS.
- [ ] **Step 5: Commit** `fix(invoicing): typed InvoiceNeedsReconcile and last-resort provider id attach`

**Done when:** Audit test (2) green.

---

### Task 5: `retrieve` port + pending hydrate + remote reconcile

**Mission:** Add provider retrieve; preserve `pending`; make `ReconcileIssuedInvoice` verify remote state before promote; support ops attach-from-typed-id.

**Why this piece exists:** A10/D10/D1 — local-only promote can mark issued while remote is still `pending`/`canceled`; hydrate currently forces Valid.

**Depends on:** Task 4.  
**Unblocks:** Task 9 (webhooks apply status), safer cancel idempotency checks.

**Owns:**
- Modify: `InvoiceProviderInterface` + transport + `SdkFacturapiTransport::retrieve` → `$client->Invoices->retrieve($id)`
- Modify: `FacturapiInvoiceProvider::retrieveInvoice` mapping via existing `mapIssuedInvoice`
- Modify: PDO/InMemory `hydrate`/`mark` to store `meta.provider_status` from `IssuedInvoice::status()->value` and restore via `InvoiceStatus::fromProvider` when ledger status is `issued`/`needs_reconcile` (A16)
- Modify: `ReconcileIssuedInvoice::handle` — if NeedsReconcile and id present → `registry->get()->retrieveInvoice` → `markIssued` with remote VO; if canceled remotely → `markCanceled` (method may land in Task 6 — if missing, set status canceled via interim repo API agreed in this task: prefer calling Task 6’s `markCanceled` **or** implement thin `markCanceled` here first)
- Optional: `listByExternalId` on transport for orphan recovery — **include if ≤ small seam**; otherwise residual D11
- Modify: `ReconcileIssuedInvoiceTest`, `InvoiceEventLogContract`, provider tests, ports tests
- Add: pending round-trip test (audit test 3)

**Contract — Reconcile:**

```text
findByIdempotencyKey
  null + no id → InvoiceSourceNotFound / AmbiguousCreate guidance
  status not NeedsReconcile → return as-is (idempotent)
  NeedsReconcile:
    remote = provider.retrieveInvoice(id)
    if remote.status Canceled → markCanceled + return
    else markIssued(remote) → return reloaded
NEVER createInvoice
```

**Do not:** webhook HTTP; do not full SAT catalog.

- [ ] **Step 1: Failing tests** — retrieve mapping; pending survive markIssued+find; reconcile calls retrieve (spy); canceled remote does not become Valid.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement**.
- [ ] **Step 4: Run** Invoicing reconcile/provider/contract — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): retrieve invoices and reconcile against remote status`

**Done when:** Audit tests (2 continuity) + (3) green; Reconcile never creates.

**Note / plan B:** SDK `retrieve` confirmed on FacturAPI/facturapi-php `Invoices::retrieve`. If composer lock pins an older build without it, bump within `^4` in this task and record in commit body.

---

### Task 6: Cancel complete — claim-before, markCanceled, motives, idempotency

**Mission:** Make cancel production-safe: validate SAT motive, claim before remote call, update issue row to `canceled`, safe replay.

**Why this piece exists:** Schema documents `canceled` but nobody writes it; claim-after-success races; motive `01` without substitution is invalid per Facturapi.

**Depends on:** Task 5 (retrieve helps idempotent “already canceled” detection).  
**Unblocks:** Task 10 cancel runbook.

**Owns:**
- Modify: `InvoiceCancellation` — validate motive ∈ {01,02,03,04}; if `01` require non-empty substitution; throw `InvoiceDraftInvalid` or new `InvoiceCancellationInvalid`
- Modify: `CancelIssuedInvoice` flow:

```text
resolve provider + id
if issue row already Canceled → return local/remote snapshot (no remote cancel)
tryClaim(cancel:{id}) == false:
  if already canceled locally/remotely → return safe
  else treat as in-flight / InvoiceAlreadyProcessed (do not blind cancel)
else:
  cancel remote
  markCanceled on issue row(s) with that provider_invoice_id
  mark cancel claim issued/canceled meta
```

- Modify: event log port + PDO + InMemory: `markCanceled(string $provider, string $providerInvoiceId, IssuedInvoice $invoice): void` (UPDATE by `provider_invoice_id`, set status `canceled`)
- Modify: `InvoiceScaffoldUseCasesTest` + contract tests for canceled status
- Tests for audit items (4)(5)(6)

**Contract:** Second cancel with same id does not call provider when local/remote already canceled; motive `01` without substitution fails before claim/remote.

**Do not:** Portal UI; cancellation receipt download.

- [ ] **Step 1: Failing tests** — claim order (spy: claim before cancel); issue row status canceled; replay no second remote; motive 01 requires substitution; invalid motive rejected.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement**.
- [ ] **Step 4: Run** scaffold + contract — PASS.
- [ ] **Step 5: Commit** `fix(invoicing): claim-before cancel, markCanceled, and SAT motive rules`

**Done when:** Audit tests (4)(5)(6) green.

---

### Task 7: Minimal CFDI field validations + mapping (`tax_system`, `unit_key`, `payment_method`)

**Mission:** Reject incomplete drafts before Facturapi and map `payment_method` PUE/PPD.

**Why this piece exists:** Validator omits `tax_system`/`unit_key`; draft has no payment method; Facturapi defaults PUE silently.

**Depends on:** Task 3 (fixtures/payload shape stable) — **parallel-safe** with Tasks 6/8 once Task 3 merged.  
**Unblocks:** fewer silent SAT rejects in prod.

**Owns:**
- Create: `src/Domain/Invoicing/PaymentMethod.php` enum `Pue='PUE'`, `Ppd='PPD'`
- Modify: `InvoiceDraft` — add `paymentMethod: PaymentMethod = Pue`
- Modify: `InvoiceDraftValidator` — `tax_system` ~ `/^\d{3}$/`; each item `unitKey` required non-empty (Facturapi requires unit_key; prefer require explicit, defaulting in mapper to `H87` only if plan chooses — **require explicit** to avoid wrong units); `paymentMethod` must be enum
- Modify: `FacturapiInvoiceProvider::mapDraft` — always send `payment_method`; always send `unit_key` (from item)
- Update fixtures + docs example in Task 10; update VO/validator tests + golden fixtures

**Contract:** Invalid tax_system/unit_key/payment_method → `InvoiceDraftInvalid` with field paths. Full SAT catalog **out of scope**.

**Do not:** download catálogos SAT; do not add régimen whitelist beyond 3-digit shape.

- [ ] **Step 1: Failing validator + golden payload tests**.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** VO/enum/validator/mapper/fixtures.
- [ ] **Step 4: Run** Invoicing validator/provider — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): validate tax_system/unit_key/payment_method for CFDI I`

**Done when:** Extra CFDI checks green; catalog still out of scope (documented).

---

### Task 8: RBAC slugs + hard consumer rule (no Portal UI)

**Mission:** Publish platform permission slugs for invoicing operations and document mandatory consumer route protection.

**Why this piece exists:** Manifest `permisos` is empty; consumers have no stable slug contract.

**Depends on:** nothing from code hardening (**parallel-safe** with 6/7). Prefer after Task 6 so slug list matches real operations.  
**Unblocks:** Task 10 docs cross-links.

**Owns:**
- Modify: `config/modules/invoicing.php`, `skeleton/config/modules/invoicing.php` —  
  `'permisos' => ['invoicing.emitir','invoicing.cancelar','invoicing.descargar','invoicing.enviar','invoicing.reconciliar']`
- Modify: `database/schema/modules/invoicing.sql` — `INSERT IGNORE` into `auth_permisos` + grant to `administrador` (mirror `integrations.sql`)
- Modify: `tests/Invoicing/InvoicingConfigTest.php` / `InvoicingSchemaTest.php` / `InvoicingDocsTest.php`
- Docs body updates can wait for Task 10, but schema/manifest must land here

**Contract — slug meanings:**
- `invoicing.emitir` → Issue
- `invoicing.cancelar` → Cancel
- `invoicing.descargar` → PDF/XML
- `invoicing.enviar` → email
- `invoicing.reconciliar` → reconcile/ops

**Framework vs consumer:** Framework does **not** register HTTP routes. Consumer **must** attach RBAC middleware/checks using these slugs on any admin/API route that invokes the use cases. Webhook endpoint uses signature auth (Task 9), not these slugs.

**Do not:** invent menu UI; do not Portal controllers.

- [ ] **Step 1: Failing config/schema/docs assertions** for slugs presence.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** manifest + SQL inserts.
- [ ] **Step 4: Run** config/schema tests + SkeletonPurity — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): define RBAC permission slugs in manifest and SQL`

**Done when:** Slugs exist in manifest+SQL; tests lock them.

---

### Task 9: Webhooks async mínimo seguro (`Facturapi-Signature`)

**Mission:** Ship Framework signature validation + apply-event use case + config/env/docs wiring so consumers can expose a secure webhook without logging fiscal payloads.

**Why this piece exists:** Pending/canceled transitions arrive async (A10); Payments already patterns `parseWebhook` on the gateway — Invoicing needs the Facturapi analogue before production async reliance.

**Depends on:** Task 5 (retrieve/status fidelity), Task 2 (redaction).  
**Unblocks:** Task 10 webhook runbook.

**Owns:**
- Create: `src/Infrastructure/Invoicing/FacturapiWebhookSignature.php` — `assertValid(string $rawBody, string $signatureHeader, string $webhookSecret): void` using HMAC-SHA256 (`hash_equals`); accept raw hex or `sha256=` prefix (match SDK local verify). Prefer **pure PHP** in Framework to avoid Domain/SDK leakage; may delegate to SDK `Webhooks::validateSignature` only inside Infrastructure with secret from config.
- Create: Domain VO or small DTO `InvoiceProviderEvent` (`providerEventId`, `type`, `providerInvoiceId`, `status`, safe meta only)
- Create: `src/Application/Invoicing/ApplyInvoiceProviderEvent.php` — idempotent `tryClaim(provider, 'webhook:'.$eventId, sourceRef:'', type:'webhook')`; map status → `markIssued` / `markCanceled` / update provider_status via retrieve-or-event object; ignore unknown types safely
- Modify: `FacturapiInvoiceProvider` — `parseWebhook(string $rawBody, string $signature): InvoiceProviderEvent` (validate then decode JSON; extract `id`/`type`/`data.object.id`/`status` only — **do not** keep customer/items/PDF/XML in event meta)
- Modify: `config/invoicing.php` + skeleton + `.env.example` — `FACTURAPI_WEBHOOK_SECRET`
- Modify: container gated bind for Apply use case when vertical ON
- Tests: signature invalid → exception; valid fixture applies pending→valid; replay same event id no double apply; ensure logger/meta exclude RFC/lines (unit assert on stored meta keys)
- Docs wiring snippet deferred to Task 10 but tests may lock key phrases

**Contract — consumer wiring (document, do not implement Portal):**

```text
POST /webhooks/facturapi  (CSRF exempt)
read raw body
signature = header Facturapi-Signature
event = provider.parseWebhook(raw, signature)  // or Framework validator + decode
ApplyInvoiceProviderEvent->handle(event)
respond 200 quickly
never log raw body / customer / items
```

**Compare Payments:** `StripeGateway::parseWebhook` + consumer applies; Framework stays HTTP-agnostic — **same split**.

**Residual out of scope (explicit):** webhook management CRUD against Facturapi API; multi-org webhook secrets rotation UI; persisting full event payloads; automatic retries dashboard; `invoice.failed` deep ops beyond status sync.

**Do not:** add Framework public controller; do not store full fiscal JSON in `inv_events.meta`.

- [ ] **Step 1: Failing tests** for signature + apply idempotency + meta safety.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** validator + parse + Apply UC + config.
- [ ] **Step 4: Run** new webhook tests + Invoicing subset — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): Facturapi webhook signature validation and apply-event use case`

**Done when:** Audit point 7 covered at Framework layer; consumer HTTP remains documented-only.

---

### Task 10: Docs, runbook, debt pointers, full suite gate

**Mission:** Update consumer module docs and debt/amendment pointers so ops/agents follow hardened rules; green full Invoicing suite.

**Why this piece exists:** Without docs, consumers will keep blind-retrying and skipping RBAC/webhooks.

**Depends on:** Tasks 1–9.  
**Unblocks:** plan closure / production enablement checklist.

**Owns:**
- Modify: `docs/modules/modulo-invoicing.md` — A11–A20 runbook; typed reconcile; cancel motives; RBAC hard rule; webhook wiring; env `FACTURAPI_WEBHOOK_SECRET`; pending status; **never re-issue** on ambiguous create
- Modify: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` header — point to hardening plan amendments A11+
- Modify: `docs/superpowers/plans/2026-08-07-invoicing-facturapi.md` debt rows D1/D10 → superseded pointer (short note only; do not rewrite v1 tasks)
- Modify: `tests/Invoicing/InvoicingDocsTest.php` assertions for new mandatory phrases
- Optional: `docs/ARCHITECTURE-CONSUMER.md` one-liner on webhook/RBAC ownership if missing

**Contract — docs must state:**
1. Ambiguous create → do not release / do not new idempotency key
2. `InvoiceNeedsReconcile::providerInvoiceId()`
3. Reconcile retrieves remote
4. Cancel claim-before + motives
5. RBAC slugs mandatory on consumer routes
6. Webhook consumer wiring + no fiscal payload logs
7. Mode/key prefix rule
8. Residuals: full SAT catalog, Framework HTTP webhook controller, live Facturapi CI, cron worker

**Do not:** Portal pages; do not enable vertical in harness.

- [ ] **Step 1: Failing docs tests** for key phrases.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Write docs + pointers**.
- [ ] **Step 4: Run** `php tests/run.php Invoicing` && `php tests/run.php Kernel/SkeletonPurity` && `php tests/run.php Payments` — all PASS.
- [ ] **Step 5: Commit** `docs(invoicing): production hardening runbook, RBAC and webhook wiring`

**Done when:** Full suite green; all 🔥 points mapped to shipped tasks or explicit residual in docs.

---

## Spec / audit coverage checklist

| 🔥 Requirement | Task(s) | Out of scope? |
|----------------|----------|---------------|
| 1 Double stamp timeout (A3 real) | 3 (+12 remote idempotency) | — |
| 2 Last-resort id + typed exception + remote reconcile | 4, 5 | — |
| 3 Mode ↔ key + empty rejection | 1 | Dual env keys rejected (A18) |
| 4 Cancel complete | 6 | Cancellation receipt files |
| 5 Secret redact + meta denylist | 2 | — |
| 6 RBAC slugs + consumer hard rule | 8, 10 | Portal UI/menus |
| 7 Webhooks Facturapi-Signature mínimo | 9, 10 | Framework HTTP controller; delivery admin UI |
| 8 CFDI extras tax_system/unit_key/payment_method | 7 | Full SAT catalog product |
| 9 CI tests 1–7 (+ secrets) | 1–7, 10 gate | Live Facturapi network CI |

---

## Deviations / notes for implementers

1. **v1 plan Task 10** said “no remote pull”; **A15 supersedes** that for production hardening.
2. Changing `InvoiceProviderInterface` is a **semver-significant** surface for anyone who already implemented the port in a consumer — treat release notes accordingly (still inside Invoicing vertical early adoption).
3. Golden fixtures must be updated whenever `mapDraft` gains fields — keep Task 3 and Task 7 coordinated if parallelized incorrectly.
4. InMemory ledger **must** mirror PDO hydrate/attach/markCanceled or D2 returns.
5. Do not “fix” timeout by guessing remote ids from exception messages unless FacturapiException exposes structured id (prefer attach only when `IssuedInvoice` observed or ops/list-by-external_id).

## Verification commands (executor)

```bash
php tests/run.php Invoicing
php tests/run.php Kernel/SkeletonPurity
php tests/run.php Payments
```

Expected: all PASS after Task 10.

## Estado de ejecución

- **Reconciled:** 2026-08-08 (plan authored; not executed).
- **Completed / total:** 0 / 10
- **Next executable task:** Task 1 (mode/key fail-fast); Task 2 parallel-safe.
- **Blockers:** none for Task 1; Facturapi SDK retrieve/validateSignature confirmed via upstream package sources.
- **Human ops residual:** configure `FACTURAPI_WEBHOOK_SECRET` and consumer route; assign RBAC roles.
