# Manual membership purchase (landing → transfer → admin)

**Date:** 2026-07-14  
**Repo:** Lebytek_Framework (`lebytek.com` / waapi tree on `feature/backoffice-api-integration`)  
**Status:** Approved — implementing  
**Companion spec (api):** `WhatsApiLebytek/docs/superpowers/specs/2026-07-14-plan-activation-and-package-limits-design.md`

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

| Slug (target) | Nombre | Mensual MXN | Features (claim) | `mensajes_mes_limite` today |
|---------------|--------|-------------|------------------|-----------------------------|
| `demo` | Demo | 0 | 100 msg / 30 días | 100 (`activo=0`, not on landing) |
| `starter` | Starter | 2199 | ~5000 msg, 1 instancia | **null** (must backfill) |
| `business` | Business | 4499 | ~80000 msg, até 3 instancias | **null** (must backfill) |
| `empresa` | Enterprise | custom | a medida | null OK |

Pricing UI already reads `precio_mensual` / `precio_anual` / `features`.

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
  → Comprar ya (plan slug + billing period)
  → GET /comprar/{slug}?ciclo=monthly|annual
  → POST form (personal + fiscal)
  → Order status = pending_transfer
  → WhatsApp alert to ops (.env list)
  → GET /comprar/orden/{publicId}/transferencia  (CLABE + proof guide)
  → Customer sends proof out-of-band (email/WhatsApp ops)
  → Admin CRUD: Autorizar pago
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

New table (name can be `dom_mkt_membership_orders` if preferred; keep `dom_*`):

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
| timestamps + soft delete pattern consistent with marketing tables |

On form success: `pending_transfer` → show transfer view; set `awaiting_review` when ops marks “comprobante recibido” (optional v1; can jump `pending_transfer` → `paid` on authorize).

## Transfer instructions

Config (`.env` + optional `cfg_` / settings provider):

- Bank name, beneficiary, CLABE / account, reference format (`ORD-{public_id}` or email).
- Proof guide text: where to send screenshot (ops email / WhatsApp).

Never hardcode secrets; account numbers are OK in env/config.

## WhatsApp admin alert

Reuse pattern from lead email verification:

- Recipients: `MKT_ALERT_WHATSAPP_NUMBERS` **or** dedicated `MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS` (fallback to the former if empty).
- Channel: existing platform Green notifier (`LeadVerifiedWhatsAppNotifier` style).
- Trigger: after order persist + before/after rendering transfer view (exactly once; store `transfer_notified_at`).
- Body (example): order public id, plan, ciclo, nombre, email, telefono, empresa, link to admin order.

Failure to notify must **not** roll back the order; log + surface soft warning for ops.

## Admin authorize

CRUD action on order (permission e.g. `mkt_ordenes.autorizar`):

1. Require `status` in `pending_transfer` | `awaiting_review`.
2. Resolve `api_tenant_public_id` (from lead link or admin field). If missing → refuse with clear error (“asociar tenant demo primero”).
3. Call api companion: `POST .../tenants/{publicId}/activate-plan` with platform token (`LEBYTEK_API_*`).
4. On success: store plan metadata; send email `emails/membership_activated` with plan details + **new** token (once).
5. Mark order `paid`, `authorized_at` / `authorized_by`.
6. On api failure: keep order unlocked; set `api_activation_error`; flash error.

Do not trust buyer POST for limits or `commercialStatus`.

## Emails

| Email | When |
|-------|------|
| Credentials (#2) | Existing — add **Ver paquetes** CTA |
| Membership (#3) | After authorize — plan name, ciclo, cuota, Base URL, new Bearer token, note that demo token was revoked |

## Security (Framework side)

- `compras=1` is UI-only.
- Authorize is RBAC + server-side only.
- Checkout CSRF + validation; rate-limit POST `/comprar`.
- Token from api appears only in email #3 / authorize response path — never echo in public transfer view.

## Testing (Framework)

- Landing without query: no Comprar ya.
- Landing `?compras=1`: Comprar ya for active paid packages; Enterprise CTA policy (contact or same form) documented in plan.
- Credentials email HTML includes Ver paquetes URL with `compras=1`.
- Form → order row + transfer view.
- WhatsApp notifier called once (fake channel).
- Authorize happy path mocked api; authorize without tenant fails.

## Cross-repo contract

Depends on api companion endpoint + plan slug map. Ship api activate-plan before enabling Authorize in prod, or feature-flag the button.

## Open follow-ups (not blocking draft)

- Exact Enterprise CTA (mailto vs form).
- Whether `awaiting_review` is a distinct status in v1.
- Backfill slug/`mensajes_mes_limite` for Starter/Business in same PR as order work.
