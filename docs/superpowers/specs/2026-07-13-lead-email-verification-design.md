# Lead email verification + WhatsApp team alert

**Date:** 2026-07-13  
**Repo:** Lebytek_Framework (lebytek.com)  
**Status:** Approved design — pending implementation plan

## Problem

Demo requests from the landing accept any email. Bots and fake inboxes create noise for the ops team. Today the flow is:

1. Landing → lead `pendiente`
2. Welcome email to lead + internal email to `mkt_mail_from`
3. Admin manually sets `validada`
4. Admin provisions demo → credentials email

There is no proof that the lead controls the mailbox before ops spends time on it.

## Goal

Add a bot/fake-email retention step on the **first** email: a 6-character alphanumeric code plus a one-time verification page. Successful verification sets `estado=validada` and notifies the tech team over WhatsApp using the **platform master** Green API instance (not a tenant instance). Ops still reviews and provisions manually; the second email (credentials) is unchanged.

## New flow

```
Landing
  → POST /lead (pendiente)
  → Internal email (NotifyInternal) — unchanged
  → Welcome email #1 (thank-you + code + link)
  → GET/POST /verificar-demo/{token} (one-time page)
  → On success: estado=validada + WhatsApp to MKT_ALERT_WHATSAPP_NUMBERS
  → Tech team reviews in CRUD → Provisionar demo
  → Email #2 (credentials) — unchanged
```

## Approach (chosen)

Store verification fields on `dom_mkt_leads` (one active code per lead). Rejected alternatives: separate verification table (overkill for v1); signed URL without persisted one-time burn (weaker invalidation).

## Data model

New columns on `dom_mkt_leads`:

| Column | Type | Notes |
|--------|------|--------|
| `email_verify_token` | VARCHAR(64) NULL, unique index | Opaque URL token (random). Cleared or left marked after success. |
| `email_verify_code_hash` | VARCHAR(64) NULL | SHA-256 (or equivalent) of the plaintext 6-char code. Never store plaintext. |
| `email_verify_expires_at` | DATETIME NULL | `created_at + 24h` at issuance |
| `email_verified_at` | DATETIME NULL | Set on success |
| `email_verify_attempts` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | Failed POST attempts |

Migration + bootstrap update in `database/schema/modules/marketing.sql`.

## Capture changes

On `PersistLeadHandler` / repository `guardar()` (or a dedicated step before autoresponder):

1. Generate `token` = high-entropy random (e.g. 32 bytes hex).
2. Generate `code` = 6 chars from alphabet `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no `0O1I`).
3. Persist hash + token + expires_at; return plaintext code only to the autoresponder.
4. `NotifyInternalHandler` — unchanged (fires on capture).
5. `AutoresponderHandler` — pass `codigo`, `verifyUrl` into `emails/lead_welcome`.

Welcome email additions (keep existing thank-you copy; update “qué sigue”):

- Display the 6-char code prominently.
- Primary CTA → `{APP_URL}/verificar-demo/{token}` (“Verificar mi correo”).
- Note that the code expires in 24 hours.

## Public verification page

Routes (public, CSRF as per other public forms):

- `GET /verificar-demo/{token}`
- `POST /verificar-demo/{token}` (field: `codigo`)

Controller in `Presentation`; use case in `Application/Marketing` (e.g. `VerificarLeadEmailUseCase`).

| Condition | Response |
|-----------|----------|
| Token missing / unknown | Terminal error (no form) |
| `email_verified_at` already set OR token burned | “Ya verificado” / one-time used |
| `expires_at` past | Expired message |
| `attempts >= 5` | Locked; ask to contact support / new request |
| Code mismatch | Error + increment attempts |
| Code match | Success UI; mark verified |

On success (atomic as practical):

1. `estado = validada`
2. `email_verified_at = now()`
3. Burn verification (`email_verify_token` NULL and/or code hash cleared; attempts frozen)
4. Send WhatsApp alerts
5. WhatsApp failure: log only; **do not** roll back `validada`

Admin may still set `validada` manually in CRUD. Provision action remains `visible_when: estado=validada`.

## WhatsApp team alert

- Channel: existing `GreenApiWhatsappChannel` / `GREEN_API_*` (master instance on lebytek.com).
- Recipients: comma-separated `MKT_ALERT_WHATSAPP_NUMBERS` in `.env`.
- Requires `GREEN_API_ENABLED=true` and valid `GREEN_API_INSTANCE` + `GREEN_API_TOKEN`.
- Message includes: lead name, email, phone, short mensaje excerpt, link to admin lead (CRUD URL).
- Send to each number independently; partial failures logged, others continue.
- No tenant/api.lebytek.com instance involved.

`.env.example` additions:

```
MKT_ALERT_WHATSAPP_NUMBERS=
# GREEN_API_* already documented; must be enabled for alerts
```

## Layers (Onion)

| Layer | Responsibility |
|-------|----------------|
| Presentation | Routes, `LeadEmailVerificationController`, view `publico/verificar_demo.php` |
| Application | Issue codes (capture path), `VerificarLeadEmailUseCase`, WhatsApp notify orchestration |
| Domain | Optional small VO / policy for code alphabet & attempt limits |
| Infrastructure | Repo methods, Green API channel reuse, welcome template |

Do not edit `vendor/`. Prefer app-layer wiring in `config/container.php` and `routes/marketing.php`.

## Error handling & security

- Rate-limit form posts if middleware available; always enforce attempt cap.
- Constant-time hash compare for codes.
- Tokens unguessable; never put plaintext code in the URL.
- CSRF on POST.
- Do not expose whether an email exists beyond the token-bound page.

## Testing

- Unit/feature: issue code → welcome vars include code + URL.
- Verify success → `validada`, `email_verified_at` set, token unusable again.
- Expired / wrong code / max attempts.
- WhatsApp notifier mocked: called with configured numbers and expected body fragments.
- Existing lead capture + credentials flows still green.

## Out of scope (v1)

- Auto-provision on verify
- Self-service “resend code” UI (ops can ask user to submit again or a later patch)
- Changes in WhatsApiLebytek
- Replacing `NotifyInternal` email

## Deploy notes

- Repo: Lebytek_Framework branch `feature/backoffice-api-integration` (no merge to `main` unless user explicitly orders it).
- VPS: lebytek.com — run migration, set env (`GREEN_API_*`, `MKT_ALERT_WHATSAPP_NUMBERS`), pull feature branch.
- Confirm master instance credentials with operator if missing on VPS before go-live of WhatsApp alerts.

## Success criteria

1. First email shows code + verification link.
2. Completing the one-time page moves lead `pendiente` → `validada`.
3. Tech team receives WhatsApp via master instance.
4. Provision + credentials email still work as today after ops action.
