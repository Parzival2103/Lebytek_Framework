# Invoicing (Facturapi) Production Hardening Plan

> **Spec / v1 plan:** [`docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`](../specs/2026-08-07-invoicing-facturapi-design.md) · [`docs/superpowers/plans/2026-08-07-invoicing-facturapi.md`](2026-08-07-invoicing-facturapi.md)  
> **Amendments:** A11+ in this plan supersede A1/A3/A10 and residual D1/D10 where they disagree (see § Design amendments).  
> **Plan audit:** [`docs/audits/2026-08-08-auditoria-plan-invoicing-facturapi-hardening.md`](../../audits/2026-08-08-auditoria-plan-invoicing-facturapi-hardening.md) — A21/A22 supersede A12 truncation and optional `listByExternalId`; **A23–A27** (review of PR #103) fix the `external_id` identity, the orphan lookup port, and the reconcile branch table.  

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
| **Los huérfanos son invisibles a los lookups actuales** | `PdoInvoiceEventLogRepository::findByIdempotencyKey` (~L107) y `findNeedsReconcile` (~L153) filtran `AND provider_invoice_id IS NOT NULL` → un claim sin id devuelve `null` / no aparece. Motiva **A24** |
| **`mark()` pisa la columna `meta` entera** | `PdoInvoiceEventLogRepository::mark()` hace `meta = :meta` con `encodeMeta($invoice->meta())`; no hay merge. Motiva **A25** |
| **El claim precede al create** | `IssueInvoiceFromSource::handle` llama `tryClaim` (L31) antes de `createInvoice` (L52), y **sin meta** → un claim en vuelo es idéntico a un huérfano. Motiva la guarda de edad de **A27** |
| Issue llavea por `idempotencyKey`, no por `sourceRef` | `handle(string $sourceRef, string $idempotencyKey, ...)`; `UNIQUE (provider, idempotency_key)` en `inv_events` → un `sourceRef` admite N filas/facturas. Motiva **A23** |
| Reconcile solo ve el port | `ReconcileIssuedInvoice` recibe `InvoiceProviderRegistry`; `get()` devuelve `InvoiceProviderInterface`. Motiva que `listByExternalId`/`externalIdForIssue` vivan en el port (**A24**) |

**Facturapi API evidence (external):** create accepts `idempotency_key` + `external_id`; list filters by `external_id`; cancel requires `motive` `01|02|03|04` and `substitution` when `01`; webhook header `Facturapi-Signature`; PHP SDK `Webhooks::validateSignature` supports local HMAC-SHA256.

---

## Coverage matrix (🔥 → task → test → acceptance)

| Prioridad | Archivos clave | Tarea | Test (mínimo) | Criterio de aceptación |
|-----------|----------------|-------|---------------|------------------------|
| 1 Timeout / no double stamp | `IssueInvoiceFromSource`, provider `mapDraft`, transport | **3** | Timeout post-create: claim **not** released; second handle does **not** call create; dos emisiones del **mismo** `sourceRef` con distinta `idempotencyKey` producen `external_id` **distintos** (A23) | A3 real closed; Facturapi `idempotency_key` + per-attempt `external_id` (A21/A23) sent y persistido en `meta.external_id` desde el claim (A25) |
| 2 Last-resort id + typed exception + remote reconcile + orphan recovery | `InvoiceNeedsReconcile`, event log (`InvoiceClaimRow` + lookups A24), `ReconcileIssuedInvoice`, retrieve, `listByExternalId` | **4**, **5** | Dual mark failure → typed id recoverable; reconcile retrieves remote; claimed-without-id (leído por `findClaimByIdempotencyKey`, no `findByIdempotencyKey`) recovers via `listByExternalId` before manual ops; claim fresco (< umbral) no se toca | Ops can recover without parsing message strings; no second create; huérfano de 0 hits tiene salida explícita A26 en vez de quedar inemitible |
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
| A12 | Remote create idempotency | `FacturapiInvoiceProvider::mapDraft` / create payload **must** send Facturapi `idempotency_key` (= local issue idempotency key) and a Facturapi `external_id` (≤100). **Encoding of `external_id` is defined by A21** (do **not** truncate raw `sourceRef`). Port signature may gain an explicit idempotency argument. | v1 “inline payload only” |
| A13 | Typed `InvoiceNeedsReconcile` | Exception **must** expose `providerInvoiceId(): string` (and preferably `idempotencyKey()`, `providerKey()`). Message may still include the id; typed accessors are authoritative for ops/reconcile. | Empty exception class |
| A14 | Last-resort persist | After remote success, if `markIssued` fails then `markNeedsReconcile` fails, attempt a final `attachProviderInvoiceId` (or equivalent) that writes `provider_invoice_id` + `needs_reconcile` with stripped meta. If that also fails, still throw typed `InvoiceNeedsReconcile` — never `releaseClaim`. | Message-only recovery |
| A15 | Reconcile verifies remote | `ReconcileIssuedInvoice` for `NeedsReconcile` **must** `retrieve` remote invoice (when id known) and promote using remote-mapped `IssuedInvoice`. If local row lacks id but typed exception/ops supplies id, attach then retrieve. Do not blindly `markIssued` a stale local VO. | Task 10 v1 “no remote pull” |
| A16 | Pending status fidelity | Persist/hydrate must not coerce provider `pending` → `Valid`. Ledger operational status may remain `issued`/`needs_reconcile`, but `IssuedInvoice::status()` must reflect `meta.provider_status` (or dedicated column) via `InvoiceStatus::fromProvider`. | Silent `domainStatus` map |
| A17 | Cancel lifecycle | Claim `cancel:{providerInvoiceId}` **before** remote cancel. On success, mark the **issue** ledger row `canceled` (by provider invoice id / source). Validate motive `01\|02\|03\|04`; motive `01` requires non-empty `substitution`. If cancel claim already exists and remote/local already canceled, return safely without second remote cancel when evidence says canceled. | Best-effort post-cancel audit only |
| A18 | Mode/key coupling | When provider enabled: non-empty secret; `mode=test` ⇒ `sk_test_…`; `mode=live` ⇒ `sk_live_…`. Fail in factory/`fromSecretKey`. Keep single `FACTURAPI_SECRET_KEY` (one deployment = one mode); do **not** add dual test/live env keys in this plan (YAGNI; document that staging/prod use separate envs). | Silent empty/mismatched keys |
| A19 | Webhook ownership | Framework: signature validation + apply-status use case + env/docs. Consumer: HTTP route (CSRF-exempt), raw body, header `Facturapi-Signature`, RBAC not required for signed webhook (shared-secret auth). Never log full fiscal payload. | D10 “out of scope” for async |
| A20 | RBAC platform contract | Manifest + bootstrap SQL define slugs; docs state consumer **must** protect mutating/download routes with those slugs. No Portal UI in this plan. | Empty `permisos` |
| A21 | Deterministic `external_id` (no truncación) — **rev. A23** | Facturapi **no** impone unicidad de `external_id` y el filtro de list es exact match ≤100. **Prohibido** `substr(sourceRef, 0, 100)`. Computar siempre un valor hasheado de longitud fija con prefijo `lebytek:invoice:` + 40 hex (= 56 chars ≤100). **La preimagen del hash la define A23 (NO es `sourceRef`).** Misma función en create y en list/reconcile. Persistir el valor enviado en `meta.external_id` bajo la política de merge de A25. | A12 wording that equated `external_id` with truncated `sourceRef` |
| A22 | Orphan recovery by `external_id` is required — **rev. A24/A27** | Tras create ambiguo (A11) sin `provider_invoice_id` observado, la recuperación automatizada **antes** de intervención manual es: `listByExternalId(A23(providerKey, idempotencyKey))` → 1 hit → `attachProviderInvoiceId` + retrieve/reconcile; 0 hits → mantener claim / `InvoiceAmbiguousCreate` (no create, salvo la remediación explícita de A26); >1 hits → error tipado fail-closed (no elegir id). `listByExternalId` es **obligatorio** en Task 5 en **port + transport + provider** (A24), no opcional. La lectura de la fila de claim usa el read model de A24, no `findByIdempotencyKey`. | D11 “optional list if cheap”; Task 5 optional seam |
| A23 | **`external_id` identifica el intento, no el `sourceRef`** | Un mismo `sourceRef` produce legítimamente **varias** facturas Facturapi: sustitución por motivo `01` (A17/Task 6) y cualquier re-emisión posterior con **nueva** `idempotencyKey` (`IssueInvoiceFromSource` llavea por `idempotencyKey`, no por `sourceRef`; la fila `inv_events` es única por `(provider, idempotency_key)`). Si `external_id` se derivara de `sourceRef`, el orphan recovery o bien recibiría >1 hits y fallaría-cerrado para siempre, o bien adjuntaría el id de una factura **cancelada previa** a un claim nuevo. **Regla:** `external_id = 'lebytek:invoice:' . substr(hash('sha256', $providerKey . "\x1f" . $idempotencyKey), 0, 40)`. Firma del encoder: `FacturapiExternalId::forIssueClaim(string $providerKey, string $idempotencyKey): string` — **no** existe `fromSourceRef`. La cardinalidad 1:1 la garantiza el `idempotency_key` remoto (dos creates con la misma key devuelven la misma factura), por eso >1 sí es corrupción real. `sourceRef` sigue guardándose en la columna `source_ref` para ops; **nunca** entra en la preimagen. | A21/A22 wording basado en `sourceRef`; nota de desviación 7 previa |
| A24 | Claim read model + lookups obligatorios en el port | `findByIdempotencyKey` filtra `provider_invoice_id IS NOT NULL` (PDO línea ~107), así que **un huérfano siempre devuelve `null`** y ninguna rama de A22 es alcanzable a través de él. Task 5 **debe** añadir: (a) VO `InvoiceClaimRow` (`provider`, `idempotencyKey`, `sourceRef`, `type`, `ledgerStatus`, `providerInvoiceId` nullable, `meta`, `createdAt`); (b) `findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?InvoiceClaimRow` (**sin** filtro de id); (c) `findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?InvoiceClaimRow` (Task 6 resuelve la `idempotencyKey` de la fila de issue desde el provider id); (d) `findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): InvoiceClaimRow[]` (barrido ops — `findNeedsReconcile` también filtra `IS NOT NULL` y por tanto **no ve huérfanos**); (e) `listByExternalId` en `InvoiceProviderInterface` además de transport+provider, porque Reconcile lo llama vía `InvoiceProviderRegistry` que devuelve el **port**, no el provider concreto. Mismo requisito para `retrieveInvoice`. Espejo obligatorio en `InMemoryInvoiceEventLog` + contract suite (D2). | Task 5 “optional `findByProviderInvoiceId`”; A22 asumiendo `findByIdempotencyKey` |
| A25 | Política de merge de `meta` | `PdoInvoiceEventLogRepository::mark()` hoy **sobrescribe la columna `meta` completa** con `$invoice->meta()`, así que “keep `meta.external_id`” es inalcanzable tal cual. **Regla:** `mark()` / `attachProviderInvoiceId()` leen la meta existente, hacen merge de `$invoice->meta()` **encima**, preservan explícitamente las claves `external_id` y `provider_status`, y recién entonces aplican el denylist de Task 2. Además `IssueInvoiceFromSource` **debe** llamar `tryClaim(..., meta: ['external_id' => $provider->externalIdForIssue($idempotencyKey)])` (hoy se llama sin meta), de modo que el valor exista desde el instante del claim y sobreviva a un create ambiguo. | Task 5 “keep `meta.external_id`” sin mecanismo |
| A26 | Remediación del huérfano de 0 hits | Con A11 (no release) + A22 (no create) un claim cuyo create **nunca llegó** a Facturapi queda permanentemente inemitible. **Regla:** existe una salida explícita de ops, nunca automática: `ReconcileIssuedInvoice::forceReissueOrphanClaim(string $idempotencyKey, ?string $providerKey = null): IssuedInvoice`, permitida **solo** cuando (1) `listByExternalId` devuelve 0 hits, (2) la edad del claim supera `invoicing.reconcile_min_claim_age_seconds`, y (3) el llamador la invoca explícitamente (no es parte de `handle()`). Reusa la **misma** `idempotencyKey` y el **mismo** `external_id`, nunca genera claves nuevas. Es seguro porque 0 hits prueba que no existe factura remota y el `idempotency_key` remoto cubre la carrera. Requiere RBAC `invoicing.reconciliar` y entrada de runbook (Task 10). | D11 residual “manual ops only”, sin API |
| A27 | Reconcile ramifica por estado de **ledger**, no por `IssuedInvoice::status()` | A16 hace que `IssuedInvoice::status()` refleje el **provider status** (`pending`, `valid`, `canceled`, `unknown`), así que la condición actual `status !== NeedsReconcile → return as-is` y la enumeración `Issued/Canceled` dejan `Pending` y `Unknown` sin rama. **Regla:** ramificar sobre `InvoiceClaimRow::ledgerStatus` (`claimed\|issued\|needs_reconcile\|canceled`), que es exhaustivo y ortogonal al status fiscal. Un remoto `pending` promueve la fila de ledger a `issued` conservando `meta.provider_status = pending` (A16); **no** se coacciona a `valid`. **Guarda de carrera:** `tryClaim` ocurre **antes** de `createInvoice`, por lo que un claim recién creado es indistinguible de un huérfano; Reconcile **debe** ignorar (devolver `InvoiceAmbiguousCreate` “claim too fresh”, sin listar ni adjuntar) todo claim con edad `< invoicing.reconcile_min_claim_age_seconds` (default `120`). El `attachProviderInvoiceId` del path huérfano es **condicional** (`WHERE provider_invoice_id IS NULL`): si el proceso emisor ganó la carrera, se re-lee la fila y se devuelve, **no** se lanza excepción ni se pisa el id. | Task 5 contract “status Issued/Canceled → return as-is” |

---

## Technical debt register (update)

| ID | Debt | Severity | Mitigation in this plan | Residual (accepted) |
|----|------|----------|-------------------------|---------------------|
| D1 | `needs_reconcile` without remote verify | High | **Tasks 4–5** typed id + retrieve + remote reconcile | No auto cron worker |
| D10 | Async status / webhooks | High→Med | **Task 9** mínimo seguro (signature + apply + docs) | No Framework HTTP controller; no dashboard of webhook deliveries; no live Facturapi CI |
| D11 | Claimed-without-id after timeout (orphan claim) | High | **Task 3** keep claim + `idempotency_key` + A21/A23 per-attempt `external_id`; **Task 5** required `listByExternalId` recovery (A22) sobre el read model A24, con guarda de edad A27 y remediación A26 | Sin cron worker: alguien debe invocar el barrido `findOrphanClaims`. `forceReissueOrphanClaim` (A26) es manual y auditada. >1 hits sigue siendo fail-closed (ahora sí improbable: la preimagen es per-attempt, A23) |
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
Task 5  retrieve + listByExternalId orphan recovery + pending hydrate + remote reconcile  ← depends 4
Task 6  Cancel complete (claim-before, markCanceled, motives)
Task 7  CFDI validator/mapping extras                 ← parallel-safe after 3
Task 8  RBAC slugs + SQL + docs contract              ← parallel-safe with 7
Task 9  Webhooks signature + apply-event              ← depends 5
Task 10 Docs/runbook/debt closure + full suite gate   ← last
```

**Runtime happy path after hardening:**

```
IssueInvoiceFromSource
  externalId = provider.externalIdForIssue(idempotencyKey)        // A23, per-attempt
  tryClaim(meta: {external_id: externalId})                       // A25, persisted before create
  validate → create(idempotency_key=idempotencyKey, external_id=externalId)
  success → markIssued (merge meta: preserve external_id + provider_status)   // A25 / A16
  observed success + local fail → markNeedsReconcile / attachProviderInvoiceId → InvoiceNeedsReconcile(typed id)
  create attempted + ambiguous fail → KEEP claim → InvoiceAmbiguousCreate (no release)

ReconcileIssuedInvoice
  row = findClaimByIdempotencyKey(...)      // A24 — NO provider_invoice_id filter
  branch on row.ledgerStatus                // A27 — not on IssuedInvoice::status()
    issued | canceled            → return hydrated row (idempotent)
    needs_reconcile + id present → retrieve(remote) → markIssued|markCanceled from remote VO
    needs_reconcile|claimed, id empty (orphan):
      age < reconcile_min_claim_age_seconds → InvoiceAmbiguousCreate "claim too fresh"  // A27 race guard
      listByExternalId(row.meta.external_id ?? provider.externalIdForIssue(key))
        1 → conditional attach (lost race → re-read + return) → retrieve → promote
        0 → InvoiceAmbiguousCreate (keep claim; ops may call forceReissueOrphanClaim, A26)
       >1 → typed fail-closed (never pick an id)

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
| `src/Domain/Invoicing/InvoiceProviderInterface.php` | Add `retrieveInvoice(string $providerInvoiceId): IssuedInvoice`, `listByExternalId(string $externalId): array`, `externalIdForIssue(string $idempotencyKey): string` — **los tres en el port** (A24): Reconcile los llama vía `InvoiceProviderRegistry`, que devuelve `InvoiceProviderInterface`, no el provider concreto |
| `src/Domain/Invoicing/ValueObjects/InvoiceClaimRow.php` (new) | A24 read model de la fila `inv_events` **sin** filtro `provider_invoice_id IS NOT NULL` (`ledgerStatus`, `providerInvoiceId` nullable, `sourceRef`, `meta`, `createdAt`) |
| `src/Domain/Invoicing/InvoiceEventLogRepositoryInterface.php` | Add `attachProviderInvoiceId…`, `markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void`, y **required** (A24): `findClaimByIdempotencyKey`, `findIssueByProviderInvoiceId`, `findOrphanClaims` |
| `src/Domain/Invoicing/Exceptions/InvoiceNeedsReconcile.php` | Typed accessors |
| `src/Domain/Invoicing/Exceptions/InvoiceAmbiguousCreate.php` (new) | Timeout/orphan-claim typed error |
| `src/Domain/Invoicing/ValueObjects/InvoiceCancellation.php` | Motive/substitution invariants |
| `src/Domain/Invoicing/PaymentMethod.php` (new enum) | `PUE`/`PPD` |
| `src/Domain/Invoicing/ValueObjects/InvoiceDraft.php` | Optional `paymentMethod` |
| `src/Application/Invoicing/IssueInvoiceFromSource.php` | A11/A12/A14 behavior |
| `src/Application/Invoicing/ReconcileIssuedInvoice.php` | Remote retrieve before promote; orphan path A22/A27; `forceReissueOrphanClaim` (A26) |
| `src/Application/Invoicing/CancelIssuedInvoice.php` | Claim-before + markCanceled |
| `src/Application/Invoicing/InvoiceDraftValidator.php` | tax_system / unit_key / payment_method |
| `src/Application/Invoicing/InvoicingFactory.php` | Mode/key enforcement; webhook secret wiring |
| `src/Application/Invoicing/ApplyInvoiceProviderEvent.php` (new) | Webhook apply use case |
| `src/Infrastructure/Invoicing/Facturapi/*` | `retrieve`, **required** `listByExternalId`, webhook validate seam |
| `src/Infrastructure/Invoicing/FacturapiExternalId.php` (new) | A21/A23 encoder: `forIssueClaim(providerKey, idempotencyKey)` → `lebytek:invoice:{hex(sha256(providerKey."\x1f".idempotencyKey))[0:40]}`. **No** existe `fromSourceRef` |
| `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php` | map retrieve/cancel/idempotency; redact; parseWebhook or delegate |
| `src/Infrastructure/Invoicing/FacturapiWebhookSignature.php` (new) | Local HMAC validate (SDK or pure PHP) |
| `src/Infrastructure/Invoicing/PdoInvoiceEventLogRepository.php` | attach/markCanceled/hydrate pending; claim lookups A24; **meta merge A25** (hoy `mark()` pisa la columna entera); meta denylist |
| `config/invoicing.php`, skeleton copy | `reconcile_min_claim_age_seconds` (default `120`, A27) |
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

- [x] **Step 1: Write failing tests** — enabled + empty key; `mode=test` + `sk_live_x`; `mode=live` + `sk_test_x`; happy `sk_test_` + test.
- [x] **Step 2: Run** `php tests/run.php Invoicing/InvoicingFactory` (and new file) — expect FAIL.
- [x] **Step 3: Implement** validation in factory before registering factory closure **and** in `fromSecretKey` (defense in depth).
- [x] **Step 4: Run** focused tests — PASS.
- [x] **Step 5: Commit** `fix(invoicing): enforce Facturapi mode/key prefix and reject empty secrets`

**Done when:** Mismatch/empty fail fast; valid test key still builds registry entry.  
**Completed:** 2026-08-10 — PR #109 (`cursor/invoicing-hardening-p01-mode-key-c292`); `InvoiceProviderException` (no secret in message).

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
- Create: `src/Infrastructure/Invoicing/FacturapiExternalId.php` (A21/**A23** encoder `forIssueClaim(string $providerKey, string $idempotencyKey): string`; unit-tested)
- Modify: `InvoiceProviderInterface` + `FacturapiInvoiceProvider` — add `externalIdForIssue(string $idempotencyKey): string` (delega en `FacturapiExternalId`), para que Application no importe Infrastructure (A24)
- Modify: golden fixtures `tests/Invoicing/fixtures/facturapi_payload_*.json` (add `idempotency_key` + A23 `external_id`)
- Modify: `tests/Invoicing/IssueInvoiceFromSourceTest.php`
- Create: `tests/Invoicing/FacturapiExternalIdTest.php` (longitud ≤100; **misma `idempotencyKey` → mismo valor**; **mismo `sourceRef` con distinta `idempotencyKey` → valores distintos**; distinto `providerKey` → valores distintos)
- Create: `src/Domain/Invoicing/Exceptions/InvoiceAmbiguousCreate.php` (or reuse `InvoiceAlreadyProcessed` with distinct message — prefer **new typed** exception for clarity)
- Update: `InvoicePortsTest` for new method signature

**Contract — Issue catch taxonomy:**

```text
externalId = provider.externalIdForIssue(idempotencyKey)          // A23
tryClaim(provider, idempotencyKey, sourceRef, 'invoice', meta: {external_id: externalId})   // A25
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

**Contract — payload:** Facturapi create body includes `idempotency_key` (= local issue key) and `external_id` = **A23** `$provider->externalIdForIssue($idempotencyKey)` — **never** raw/truncated `sourceRef` y **nunca** derivado de `sourceRef` (ver A23: un `sourceRef` puede tener varias facturas legítimas). El mismo valor se persiste en `meta.external_id` **en el `tryClaim`**, antes del create, para que sobreviva a un create ambiguo (A25). Golden fixtures deben usar la forma hasheada.

**Do not:** implement retrieve/list yet (Task 5); do not change cancel; do not truncate `sourceRef` into `external_id`; do **not** add a `fromSourceRef` encoder.

- [ ] **Step 1: Failing test** — fake provider throws after “create attempted” flag; assert `releaseCalls === 0`, second handle `createCalls` still 1; fixture asserts `idempotency_key` + A23 `external_id`; **el mismo `sourceRef` emitido con dos `idempotencyKey` distintas produce dos `external_id` distintos**; `tryClaim` recibe `meta.external_id`.
- [ ] **Step 2: Run** `php tests/run.php Invoicing/IssueInvoiceFromSource` — FAIL.
- [ ] **Step 3: Implement** A11/A12/A23 encoder + port method + mapDraft fields + claim meta.
- [ ] **Step 4: Run** Issue + Facturapi provider + ports — PASS.
- [ ] **Step 5: Commit** `fix(invoicing): keep claim on ambiguous create and send Facturapi idempotency_key`

**Done when:** Audit test (1) green: timeout post-create does not release; no second create; `external_id` is A23-stable per attempt, ≤100, y ya presente en `meta.external_id` del claim.

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
  (sets `provider_invoice_id`, status `needs_reconcile`, **merge** de meta según A25 — nunca pisa `meta.external_id`). Escritura **condicional**: `WHERE provider_invoice_id IS NULL OR provider_invoice_id = :same`. Si la fila ya tiene un id **distinto**, falla cerrado con excepción tipada (nunca sobrescribe); el path huérfano de Task 5 captura ese caso concreto como “carrera perdida” y re-lee (A27).
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

### Task 5: `retrieve` + required `listByExternalId` orphan recovery + pending hydrate + remote reconcile

**Mission:** Add provider retrieve and **required** list-by-`external_id`; preserve `pending`; make `ReconcileIssuedInvoice` verify remote state before promote; recover claimed-without-id via A21/A22 before requiring manual intervention.

**Why this piece exists:** A10/D10/D1/D11 — local-only promote can mark issued while remote is still `pending`/`canceled`; hydrate currently forces Valid; ambiguous create without observed id needs automated attach via `external_id`.

**Depends on:** Task 4 (and Task 3 A21 encoder).  
**Unblocks:** Task 9 (webhooks apply status), safer cancel idempotency checks.

**Owns:**
- Modify: `InvoiceProviderInterface` + transport + `SdkFacturapiTransport::retrieve` → `$client->Invoices->retrieve($id)`
- Modify: **port** `InvoiceProviderInterface` + transport + provider — **required** `listByExternalId(string $externalId): array` (0..n `IssuedInvoice`; SDK list/filter `external_id` exact match). El port es obligatorio (A24): `ReconcileIssuedInvoice` recibe un `InvoiceProviderRegistry` que devuelve `InvoiceProviderInterface`
- Modify: `FacturapiInvoiceProvider::retrieveInvoice` mapping via existing `mapIssuedInvoice`
- Use: `$provider->externalIdForIssue(...)` de Task 3 — **Application nunca importa `FacturapiExternalId`** (A24)
- Create: `src/Domain/Invoicing/ValueObjects/InvoiceClaimRow.php` (A24 read model)
- Modify: `InvoiceEventLogRepositoryInterface` + PDO + InMemory + contract suite — **required** (A24):
  - `findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?InvoiceClaimRow` — **sin** `AND provider_invoice_id IS NOT NULL`
  - `findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?InvoiceClaimRow` (Task 6 resuelve por aquí la `idempotencyKey` de la fila de issue)
  - `findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): array` — `status='claimed' AND provider_invoice_id IS NULL AND created_at <= NOW() - INTERVAL :minAgeSeconds SECOND`
- Modify: PDO/InMemory `hydrate`/`mark` to store `meta.provider_status` from `IssuedInvoice::status()->value` and restore via `InvoiceStatus::fromProvider` when ledger status is `issued`/`needs_reconcile` (A16); **`mark()` deja de sobrescribir la columna `meta` — merge según A25**, preservando `external_id`
- Modify: event log port — **implement `markCanceled` here** con firma explícita y única (A24):  
  `markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void` (mismo shape que `markIssued`/`markNeedsReconcile`). Task 6 la consume tal cual; **no** existe una variante keyed por `providerInvoiceId` — Task 6 obtiene la `idempotencyKey` con `findIssueByProviderInvoiceId`
- Modify: `config/invoicing.php` + skeleton — `reconcile_min_claim_age_seconds` (default `120`, A27)
- Modify: `ReconcileIssuedInvoice::handle` + `forceReissueOrphanClaim` (A26) — see contract below
- Modify: `ReconcileIssuedInvoiceTest`, `InvoiceEventLogContract`, provider tests, ports tests
- Add: pending round-trip test (audit test 3); orphan recovery tests (A22/A26/A27)

**Contract — Reconcile (A27: ramifica por `ledgerStatus`, no por `IssuedInvoice::status()`):**

```text
row = findClaimByIdempotencyKey(provider, key)          // A24 — sin filtro de id
  null → InvoiceSourceNotFound

switch row.ledgerStatus:                                 // exhaustivo: claimed|issued|needs_reconcile|canceled
  'issued' | 'canceled'  → return hydrated row (terminal, idempotente)

  'needs_reconcile' AND row.providerInvoiceId != null:
      remote = provider.retrieveInvoice(row.providerInvoiceId)
      remote.status == Canceled → markCanceled(provider, key, remote) → return
      remote.status == Pending  → markIssued(provider, key, remote)   → return
            // ledger 'issued' + meta.provider_status='pending' (A16). NO coaccionar a Valid.
      otherwise (Valid/Draft/Unknown) → markIssued(provider, key, remote) → return reloaded

  'claimed' OR ('needs_reconcile' AND providerInvoiceId == null)  → ORPHAN:
      if age(row.createdAt) < config(invoicing.reconcile_min_claim_age_seconds):
          throw InvoiceAmbiguousCreate("claim too fresh")   // A27: puede ser un issue en vuelo
      externalId = row.meta.external_id ?? provider.externalIdForIssue(key)   // A23/A25
      matches = provider.listByExternalId(externalId)
      0  → throw InvoiceAmbiguousCreate (keep claim; NEVER createInvoice desde handle;
                                          salida explícita = forceReissueOrphanClaim, A26)
      1  → attachProviderInvoiceId (condicional)
             conflicto de id  → carrera perdida contra el emisor: re-leer fila y devolverla (A27)
             ok               → retrieve → misma promoción que 'needs_reconcile' arriba
      >1 → throw typed fail-closed (never pick an id; con A23 esto es corrupción real)

handle() NEVER calls createInvoice
```

**Contract — `forceReissueOrphanClaim` (A26, ops-only):** método **separado** de `handle()`. Precondiciones verificadas en orden y todas obligatorias: (1) `ledgerStatus == 'claimed'` y `providerInvoiceId == null`; (2) edad ≥ `reconcile_min_claim_age_seconds`; (3) `listByExternalId` devuelve **0**. Solo entonces re-invoca el create con la **misma** `idempotencyKey` y el **mismo** `external_id`. Es seguro porque 0 hits prueba que no hay factura remota y el `idempotency_key` remoto cubre la carrera. Nunca genera claves nuevas; nunca se invoca automáticamente.

**Do not:** webhook HTTP; do not full SAT catalog; do not treat `listByExternalId` as optional residual; do **not** branch reconcile on `IssuedInvoice::status()`; do **not** llamar `findByIdempotencyKey` para leer huérfanos.

- [ ] **Step 1: Failing tests** — retrieve mapping; pending survive markIssued+find **y reconcile de remoto `pending` deja ledger `issued` + `provider_status=pending`**; reconcile calls retrieve (spy); canceled remote does not become Valid; `findClaimByIdempotencyKey` devuelve la fila **sin** `provider_invoice_id`; orphan claimed-without-id: list 1 → attach + no create; list 0 → AmbiguousCreate keep claim; list >1 → fail-closed; claim **fresco** → “too fresh” sin llamar a list; attach con id ajeno preexistente → re-lee y devuelve (no lanza); `mark()` preserva `meta.external_id`; `forceReissueOrphanClaim` rechaza si edad < umbral o si list > 0.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement**.
- [ ] **Step 4: Run** Invoicing reconcile/provider/contract — PASS.
- [ ] **Step 5: Commit** `feat(invoicing): retrieve invoices, listByExternalId orphan recovery, reconcile against remote`

**Done when:** Audit tests (2 continuity) + (3) green; A22 orphan path green sobre el read model A24; `handle()` never creates; `forceReissueOrphanClaim` cubierto y fail-closed en sus 3 precondiciones.

**Note / plan B:** SDK `retrieve` confirmed on FacturAPI/facturapi-php `Invoices::retrieve`. If composer lock pins an older build without it, bump within `^4` in this task and record in commit body.

---

### Task 6: Cancel complete — claim-before, markCanceled, motives, idempotency

**Mission:** Make cancel production-safe: validate SAT motive, claim before remote call, update issue row to `canceled`, safe replay.

**Why this piece exists:** Schema documents `canceled` but nobody writes it; claim-after-success races; motive `01` without substitution is invalid per Facturapi.

**Depends on:** Task 5 (`markCanceled` port + retrieve for “already canceled” detection).  
**Unblocks:** Task 10 cancel runbook.

**Owns:**
- Modify: `InvoiceCancellation` — validate motive ∈ {01,02,03,04}; if `01` require non-empty substitution; throw `InvoiceDraftInvalid` or new `InvoiceCancellationInvalid`
- Use Task 5’s `markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void` **exactamente con esa firma** (do not invent a second shape). Como `CancelIssuedInvoice` parte de un `providerInvoiceId`, resuelve primero la fila de issue con `findIssueByProviderInvoiceId($provider, $providerInvoiceId)` y usa su `idempotencyKey`. **No** añadir un `markCanceled` keyed por `providerInvoiceId`
- Modify: `CancelIssuedInvoice` flow:

```text
resolve provider + id
issueRow = findIssueByProviderInvoiceId(provider, id)        // A24 — da la idempotencyKey del issue
if issueRow.ledgerStatus == 'canceled' → return snapshot (no remote cancel)
tryClaim(provider, 'cancel:'+id, ...) == false:
  if already canceled locally/remotely → return safe
  else treat as in-flight / InvoiceAlreadyProcessed (do not blind cancel)
else:
  cancel remote
  markCanceled(provider, issueRow.idempotencyKey, canceledInvoice)   // fila de ISSUE, firma Task 5
  mark cancel claim issued/canceled meta                              // fila 'cancel:{id}', meta only
```

**Nota A23:** si la cancelación es por motivo `01` (sustitución), la factura sustituta se emite con una **`idempotencyKey` nueva** y por tanto un `external_id` nuevo — es exactamente el caso que hace inválido derivar `external_id` de `sourceRef`.

- La meta de la fila `cancel:{id}` es auditoría; el estado fiscal vive en la fila de issue. No duplicar `markCanceled` para la fila de claim de cancelación
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
- Modify: `docs/modules/modulo-invoicing.md` — A11–**A27** runbook; A23 `external_id` algorithm (per-attempt); A22/A27 orphan list recovery + guarda de edad; **A26 procedimiento ops** `forceReissueOrphanClaim` (precondiciones, RBAC `invoicing.reconciliar`, por qué es seguro); barrido `findOrphanClaims`; typed reconcile; cancel motives; RBAC hard rule; webhook wiring; env `FACTURAPI_WEBHOOK_SECRET`; `reconcile_min_claim_age_seconds`; pending status; **never re-issue** on ambiguous create salvo A26
- Modify: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` header — point to hardening plan amendments A11+
- Modify: `docs/superpowers/plans/2026-08-07-invoicing-facturapi.md` debt rows D1/D10 → superseded pointer (short note only; do not rewrite v1 tasks)
- Modify: `tests/Invoicing/InvoicingDocsTest.php` assertions for new mandatory phrases
- Optional: `docs/ARCHITECTURE-CONSUMER.md` one-liner on webhook/RBAC ownership if missing

**Contract — docs must state:**
1. Ambiguous create → do not release / do not new idempotency key
2. `InvoiceNeedsReconcile::providerInvoiceId()`
3. Reconcile retrieves remote; orphan claimed-without-id uses `listByExternalId(A23)` before manual ops, y **solo** tras `reconcile_min_claim_age_seconds`
4. `external_id` = `lebytek:invoice:{hex(sha256(providerKey."\x1f".idempotencyKey))[0:40]}` — **por intento**, nunca derivado de `sourceRef` ni truncado (A23: un `sourceRef` puede tener varias facturas legítimas por sustitución/re-emisión)
5. Cancel claim-before + motives; la fila de issue se localiza por `findIssueByProviderInvoiceId`
6. RBAC slugs mandatory on consumer routes
7. Webhook consumer wiring + no fiscal payload logs
8. Mode/key prefix rule
9. **A26:** huérfano con 0 hits no queda inemitible — procedimiento `forceReissueOrphanClaim` con sus 3 precondiciones y por qué no puede doble-timbrar
10. Residuals: full SAT catalog, Framework HTTP webhook controller, live Facturapi CI, cron worker (el barrido `findOrphanClaims` requiere disparador externo)

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
| 1 Double stamp timeout (A3 real) | 3 (+A12/A21/**A23** remote idempotency + per-attempt hashed external_id) | — |
| 2 Last-resort id + typed exception + remote reconcile + orphan list recovery | 4, 5 (A22/**A24**/**A26**/**A27**) | Cron/scheduler del barrido `findOrphanClaims` queda al consumer |
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
5. Do not “fix” timeout by guessing remote ids from exception messages unless FacturapiException exposes structured id (prefer attach when `IssuedInvoice` observed, else **A22** `listByExternalId`).
6. **Plan audit 2026-08-08:** do **not** implement A12 as truncated `sourceRef`. Use **A21/A23**. Do **not** skip `listByExternalId` as “optional if cheap” — **A22** makes it required for Task 5 Done when.
7. **Corregido por A23 (review PR #103).** La redacción previa decía que `external_id` derivaba de `sourceRef` y que por tanto >1 hits era siempre corrupción. Es **falso**: un `sourceRef` produce varias facturas legítimas (sustitución motivo `01`, re-emisión con nueva `idempotencyKey`), así que esa lectura rompía el orphan recovery. Con la preimagen **per-attempt** de A23 la relación `external_id` ↔ factura sí es 1:1 — Facturapi no impone unicidad de `external_id`, pero el `idempotency_key` remoto garantiza que dos creates con la misma key devuelven la misma factura. Solo bajo A23 la cardinalidad >1 es fail-closed legítimo.
8. `findByIdempotencyKey` y `findNeedsReconcile` filtran `provider_invoice_id IS NOT NULL`: **ninguno de los dos puede ver un huérfano**. Todo lectura del path huérfano va por los lookups de **A24**. Si un subagente “implementa A22” encima de `findByIdempotencyKey`, la rama queda muerta y los tests de orphan pasarían solo con dobles de test — exigir cobertura en el contract suite PDO+InMemory.
9. `PdoInvoiceEventLogRepository::mark()` reemplaza la columna `meta` completa. Cualquier instrucción de “preservar `meta.x`” exige el merge de **A25**; no asumir que persiste solo.
10. `ReconcileIssuedInvoice` es provider-agnóstico (recibe `InvoiceProviderRegistry` → `InvoiceProviderInterface`). Todo lo que necesite del proveedor —`retrieveInvoice`, `listByExternalId`, `externalIdForIssue`— va en el **port** (A24). Hoy solo `InvoicingFactory` cruza a Infrastructure; no romper esa frontera.

## Verification commands (executor)

```bash
php tests/run.php Invoicing
php tests/run.php Kernel/SkeletonPurity
php tests/run.php Payments
```

Expected: all PASS after Task 10.

## Estado de ejecución

- **Reconciled:** 2026-08-10 — Task 1 implemented on PR #109 (rebased onto main post-#111).
- **Completed / total:** 1 / 10
- **Next executable task:** Task 2 (secret redaction + meta denylist; parallel-safe) or Task 3 (depends on Task 1 ✅).
- **Blockers:** none for Task 2. Para Tasks 3/5/6 rige **A23–A27**: no ejecutar la redacción previa de A21 (`fromSourceRef`), ni el contrato de Reconcile basado en `findByIdempotencyKey` / `IssuedInvoice::status()`.
- **Human ops residual:** configure `FACTURAPI_WEBHOOK_SECRET` and consumer route; assign RBAC roles; programar el barrido `findOrphanClaims` (no hay cron en Framework); `forceReissueOrphanClaim` (A26) es siempre decisión humana con RBAC `invoicing.reconciliar`.
