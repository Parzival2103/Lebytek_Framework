# Manual membership purchase (landing → transfer → admin)

**Date:** 2026-07-14  
**Updated:** 2026-07-14 (hardening Tasks 1–7)  
**Repo:** Lebytek_Framework (`lebytek.com` / waapi tree on `feature/backoffice-api-integration`)  
**Status:** Implemented and hardened in the current code working tree — **ops still pending (Task 8)**  
**Companion spec (api):** `WhatsApiLebytek/docs/superpowers/specs/2026-07-14-plan-activation-and-package-limits-design.md`  
**Shipped commits:** `35f69d3` (feat), `e66a02a` (fixes), merge `5f4e913` → `feature/backoffice-api-integration`

## Implementation status (audit 2026-07-14)

### Done (code in the `feature/backoffice-api-integration` working tree)

| Spec item | Where |
|-----------|--------|
| Query gate `?compras=1` → **Comprar ya** | `LandingController`, `_pricing.php`, `PricingComprasTest` |
| Credentials email **Ver paquetes** → `/?compras=1#paquetes` | `LeadApiProvisioningService::packagesUrl()`, `lead_api_credentials.php` |
| Checkout `GET/POST /comprar/{slug}` + transfer view | `CompraController`, `CrearOrdenMembresiaUseCase`, views `compra_*` |
| Table `dom_mkt_ordenes` + migration + CRUD | `20260714200000_mkt_membership_orders.sql`, `mkt_ordenes.json`, `PdoMembershipOrderRepository` |
| Bank transfer config from env | `BankTransferConfig`, `MKT_BANK_*` |
| WhatsApp purchase alert (once, `transfer_notified_at`) | `PurchaseWhatsAppNotifier` |
| Admin **Autorizar pago** → api `activate-plan` | `AutorizarOrdenMembresiaUseCase`, `MarketingOrdenesController`, flag `MKT_MEMBERSHIP_AUTHORIZE_ENABLED` |
| `LebytekApiClient::activatePlan` | client + `LebytekApiClientTest` |
| Email #3 `membership_activated` | after successful authorize |
| Backfill package slug + `mensajes_mes_limite` | same migration (Starter 5000 / Business 80000 / Enterprise `empresa`) |
| Enterprise CTA = **Contactar** → `#demo` (not buy form) | `_pricing.php` + tests |
| Unit/wiring tests for flow | Marketing + Integration suites listed below |
| WhatsApp admin deep-link | `PurchaseWhatsAppNotifier` now links to `/admin/crud/mkt_ordenes/{id}` |
| Schema bootstrap + commercial prices | `marketing.sql` includes `dom_mkt_ordenes`, slugs, VPS prices 2199/4499 and limits 5000/80000 |
| Permission slug repair | Membership migration uses `slug`; repair migration seeds `marketing.ordenes` |
| Authorize semantic replay + demo guard | `token: null` marks paid without email #3; `paquete_slug=demo` is refused before HTTP |
| Framework contract mirror | `docs/integration/waapi-api-contract.md` mirrors `activate-plan` as **Implementado** |
| Dedicated hardening checklist | `docs/superpowers/plans/2026-07-14-manual-membership-purchase.md` |
| HTTP Feature substitute | `CompraControllerContractTest` covers CSRF, rate limit and purchasable slugs |

### Partial / known gaps

No code-hardening gaps remain after Tasks 1–6. The v1 decision to allow
`pending_transfer` → `paid` while reserving `awaiting_review` remains intentional.

~~Dedicated plan checklist: no plan was written.~~ The plan exists at
`docs/superpowers/plans/2026-07-14-manual-membership-purchase.md`; this design records
its completion status.

### Not done (ops / next)

1. **Api VPS first:** `meta` migration + activate-plan smoke (201 + semantic 200) — see WhatsApiLebytek `2026-07-14-plan-activation-closure.md` Task 4.
2. **Framework VPS:** apply membership + permission repair migrations; fill `MKT_BANK_*` + `MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS` (or fallback alert numbers).
3. **Gate authorize in prod:** keep `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` until api smoke passes **and** hardening Task 2 is deployed, then enable only through Task 8 after Framework VPS setup.
4. **Smoke E2E:** landing `?compras=1` → order → transfer → admin authorize → email #3 with new token (demo instance unchanged).
5. **Do not** merge `feature/backoffice-api-integration` → `main` unless explicitly ordered.

Companion api activate-plan is **implemented** on WhatsApiLebytek `main` (PR #14); Framework client is already wired.

---

## Problem

The public landing already shows commercial packages (`dom_mkt_paquetes`: Starter, Business, Enterprise) but every CTA is **Solicitar demo**. There is no gated path to buy a membership after a successful demo, and no ops workflow for bank-transfer payment → activate the same WhatsApp tenant without asking the customer to re-scan QR.

## Goal

1. Keep demo-first: buy CTAs hidden unless `?compras=1`.
2. After credentials email (#2), add **Ver paquetes** linking to `/?compras=1#paquetes`.
3. **Comprar ya** opens a checkout form (pasarela stub); submit shows bank transfer instructions + how to send the payment proof.
4. Notify admins on WhatsApp when a buyer reaches the transfer view.
5. Admin authorizes the order → upgrade **same** api tenant + instance (no re-QR), revoke old demo tokens, email new membership + token (orchestrated via api; see companion spec).

## Non-goals (v1)

- Mercado Pago / Stripe / PayPal capture (pasarela page is the extension point).
- Customer self-serve plan change without admin.
- Changing Green instance / forcing new QR.
- Merge of Framework feature branch → `main`.

## Inventory (VPS `dom_mkt_paquetes`, 2026-07-14)

| Slug (target) | Nombre | Mensual MXN | Features (claim) | `mensajes_mes_limite` |
|---------------|--------|-------------|------------------|------------------------|
| `demo` | Demo | 0 | 100 msg / 30 días | 100 (`activo=0`, not on landing) |
| `starter` | Starter | 2199 | ~5000 msg, 1 instancia | **5000** (migration backfill) |
| `business` | Business | 4499 | ~80000 msg, até 3 instancias | **80000** (migration backfill) |
| `empresa` | Enterprise | custom | a medida | null OK |

Pricing UI already reads `precio_mensual` / `precio_anual` / `features`. Confirm VPS prices after migration; schema seed may still need commercial price alignment.

## Query gate

| Param | Show buy CTAs |
|-------|----------------|
| absent / `0` / `false` | No — only **Solicitar demo** |
| `compras=1` or `compras=true` | Yes — **Comprar ya** under demo CTA |

Pass flag from `LandingController` → `_pricing.php` (and any other CTA surfaces). Do **not** use the flag for authorization of paid API features.

Credentials email CTA:

```
{LANDING_BASE}/?compras=1#paquetes
```

Label: **Ver paquetes**.

## User flow

```
Landing (?compras=1)#paquetes
  → Comprar ya (plan slug + billing period)   [starter/business]
  → Enterprise: Contactar → #demo
  → GET /comprar/{slug}?ciclo=monthly|annual
  → POST form (personal + fiscal)
  → Order status = pending_transfer
  → WhatsApp alert to ops (.env list)
  → GET /comprar/orden/{publicId}/transferencia  (CLABE + proof guide)
  → Customer sends proof out-of-band (email/WhatsApp ops)
  → Admin CRUD: Autorizar pago  (flag MKT_MEMBERSHIP_AUTHORIZE_ENABLED)
  → Framework calls api activate-plan (platform token)
  → Email #3: membresía + new Bearer token
```

## Checkout form (pasarela v1)

Fields:

| Field | Required |
|-------|----------|
| nombre | yes |
| email | yes |
| telefono | yes |
| empresa | yes |
| direccion | yes |
| rfc | no |
| paquete slug | from route |
| ciclo `monthly` \| `annual` | from query/form |

Layout/copy must leave room for a future embedded Mercado Pago / Stripe / PayPal widget without rewriting the order model.

## Data model — `dom_mkt_ordenes`

Table name shipped: **`dom_mkt_ordenes`**.

| Column | Notes |
|--------|--------|
| `id`, `public_id` (ULID) | Public ref in URLs/emails |
| `paquete_id`, `paquete_slug` | FK + denormalized |
| `ciclo` | `monthly` / `annual` |
| `precio_snapshot` | Decimal locked at submit |
| `mensajes_mes_limite_snapshot` | From package row |
| `nombre`, `email`, `telefono`, `empresa`, `direccion`, `rfc` | Buyer |
| `lead_id` | Nullable FK if email matches a lead |
| `api_tenant_public_id` | Nullable; resolve from lead or later by admin |
| `status` | `pending_transfer`, `awaiting_review`, `paid`, `rejected`, `cancelled` |
| `transfer_notified_at` | When WhatsApp sent |
| `authorized_at`, `authorized_by` | Admin |
| `api_activation_error` | Last api failure text |
| timestamps + soft delete | Consistent with marketing tables |

On form success: `pending_transfer` → show transfer view. v1 authorize may jump `pending_transfer` → `paid` without a separate “comprobante recibido” action (`awaiting_review` reserved).

## Transfer instructions

Config (`.env` + optional `cfg_` / settings provider):

- Bank name, beneficiary, CLABE / account, reference format (`ORD-{public_id}` or email).
- Proof guide text: where to send screenshot (ops email / WhatsApp).

Never hardcode secrets; account numbers are OK in env/config.

## WhatsApp admin alert

Reuse pattern from lead email verification:

- Recipients: `MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS` (fallback `MKT_ALERT_WHATSAPP_NUMBERS`).
- Channel: existing platform Green notifier (`PurchaseWhatsAppNotifier`).
- Trigger: after order persist + before/after rendering transfer view (exactly once; store `transfer_notified_at`).
- Body: order public id, plan, ciclo, nombre, email, telefono, empresa, link to admin order.
- Admin link includes `/admin/crud/mkt_ordenes/{id}` (closed by hardening Task 1).

Failure to notify must **not** roll back the order; log + surface soft warning for ops.

## Admin authorize

CRUD action `autorizar_pago` → `/admin/marketing/ordenes/autorizar?orden_id={id}`  
Permission: `marketing.ordenes`  
Kill switch: `MKT_MEMBERSHIP_AUTHORIZE_ENABLED`

1. Require `status` in `pending_transfer` | `awaiting_review`.
2. Resolve `api_tenant_public_id` (from lead link or admin field). If missing → refuse with clear error (“asociar tenant demo primero”).
3. Call api companion: `POST .../tenants/{publicId}/activate-plan` with platform token (`LEBYTEK_API_*`).
4. On success: store plan metadata; send email `emails/membership_activated` with plan details + **new** token (once).
5. Mark order `paid`, `authorized_at` / `authorized_by`.
6. On api failure: keep order unlocked; set `api_activation_error`; flash error.

Do not trust buyer POST for limits or `commercialStatus`.

## Emails

| Email | When | Status |
|-------|------|--------|
| Credentials (#2) | Existing — **Ver paquetes** CTA | Done |
| Membership (#3) | After authorize — plan, ciclo, cuota, Base URL, new Bearer token, demo revoked note | Done |

## Security (Framework side)

- `compras=1` is UI-only.
- Authorize is RBAC + server-side only (+ feature flag).
- Checkout CSRF + validation; rate-limit POST `/comprar` (session, 10/h).
- Token from api appears only in email #3 / authorize response path — never echo in public transfer view.

## Testing (Framework)

| Intent | Coverage |
|--------|----------|
| Landing without query: no Comprar ya | `PricingComprasTest` |
| Landing `?compras=1`: Comprar ya; Enterprise Contactar | `PricingComprasTest` |
| Credentials email Ver paquetes + `compras=1` | `LeadWelcomeEmailTest` |
| Form → order + transfer view | `CrearOrdenMembresiaUseCaseTest`, `CompraTransferenciaViewTest` |
| WhatsApp notifier once | `PurchaseWhatsAppNotifierTest`, CrearOrden tests |
| Authorize happy path / no tenant / api fail | `AutorizarOrdenMembresiaUseCaseTest` |
| Client activatePlan path | `LebytekApiClientTest` |
| Routes / CRUD wiring | `RoutesWiringTest`, `CrudConfigsTest` |

## Cross-repo contract

| Side | Status |
|------|--------|
| Api `POST …/activate-plan` | Source of truth; shipped on WhatsApiLebytek `main` (PR #14), catalog in `config/plans.php`; VPS closure smoke remains Task 4 |
| Framework `LebytekApiClient::activatePlan` | Shipped (new Idempotency-Key each write → retries use api semantic 200) |
| Framework authorize null-token path | Hardened in Task 2: mark paid without email #3; refuse `demo` before HTTP |
| Framework `docs/integration` activate-plan section | Mirror completed in Task 5; api contract remains the source of truth |

**Payload map:** `planSlug` ← `paquete_slug` (`starter`\|`business`\|`empresa`; never `demo`); `billingCycle` ← `ciclo`; `orderExternalRef` ← `public_id`; `tokenName` ← `membresia-{slug}`; `messagesMonthlyLimit` only for `empresa` with non-null snapshot.

Note: api FormRequest may still accept catalog `demo`; Framework authorize must refuse before calling. Api HTTP 200 and 201 both succeed in `LebytekApiClient`.

Prod sequence (identical wording on both plans + api closure):

```
1. Api activate-plan on main ✅
2. Api VPS meta + smoke (api closure Task 4)
3. Framework hardening Tasks 1–7 (Task 2 = null-token + refuse demo)
4. Framework VPS migrations + MKT_BANK_*
5. MKT_MEMBERSHIP_AUTHORIZE_ENABLED=true (Task 8 after sequence steps 2–4)
```

## Resolved follow-ups (was open)

| Topic | Decision shipped |
|-------|------------------|
| Enterprise CTA | **Contactar** → `#demo` (not checkout form) |
| `awaiting_review` in v1 | Column/status kept; no dedicated transition UI — authorize from `pending_transfer` OK |
| Backfill slug / limits | Same PR/migration as orders table |

## Remaining follow-ups

Tracked in `docs/superpowers/plans/2026-07-14-manual-membership-purchase.md`:

- **Ops smoke only:** complete api closure Task 4 first (VPS `meta`, activate-plan 201 and semantic 200), then Framework Task 8 (migrations/env, enable flag, full landing → order → transfer → authorize → email #3 smoke). No merge to `main` without explicit order.
