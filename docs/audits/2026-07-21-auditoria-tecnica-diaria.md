# Auditoría técnica diaria — 2026-07-21

**Ámbito:** `feature/backoffice-api-integration` @ `4789f95` (rama agente `cursor/auditor-a-t-cnica-diaria-e56d`)  
**Comparación:** sin commits de lógica desde 2026-07-18 (solo branding previo).  
**Paralelo:** auditoría `main` @ `2c71d3f` → issue **#23** + draft PR **#24**.

## Resumen ejecutivo

Sin delta de código de negocio desde la auditoría del 2026-07-20. Se reconfirman los criticals de payments/subscription (**issue #21**) y el gap de bootstrap de leads (**issue #23**, también en feature). Se aplica PR de bajo riesgo: URL admin WhatsApp, alineación bootstrap (`dom_mkt_leads` API/churn + Stripe/`dom_mkt_membresias`) y checklist VPS. **No** auto-fix de C1–C6.

## Hallazgos críticos (reconfirmados — no auto-fix)

| ID | Hallazgo | Evidencia | Estado |
|----|----------|-----------|--------|
| C1 | `CheckoutCompleted` con `subscriptionId` es no-op | `ConfirmarPagoStripeUseCase` L81–84 | #21 |
| C2 | `invoice.paid` depende de `metadata.order_public_id` que Stripe no copia a Invoice | `StripeGateway::extractExternalRef` + flujo subscription | #21 |
| C3 | Retry/reactivate crea **nueva** Checkout subscription (`membresia-{tenant}`) | `RecoverMembershipPaymentService::checkoutUrlForMembresia` | #21 |
| C4 | Post-claim swallow → HTTP 200; Stripe no reintenta | `ConfirmarPagoStripeUseCase::ejecutar` L54–61 | #21 |
| C5 | `reactivateCommercial` en catch vacío + `markActive` | `RecoverMembershipPaymentService` L42–54 | #21 |
| C6 | Amount bypass si currency ≠ mxn (amount 0) | `StripeGateway` + guard `amountMinor()>0` | #21 |
| C7 | Bootstrap `dom_mkt_leads` incompleto vs repo (API instance/lifecycle/churn) | `marketing.sql` vs `PdoLeadRepository::markApiProvisioned` | #23 (+ fix en este PR) |

## Hallazgos medios

1. CRUD `mkt_ordenes` form permite editar `status` (incl. `paid`) — bypass de authorize/activar.
2. Auto-link email→`api_tenant_public_id` en `CrearOrdenMembresiaUseCase`.
3. DI latente: recover/membresía requiere `PaymentGatewayRegistry` solo si payments ON.
4. `POST /lead` sin rate limit (CSRF sí).
5. Timestamps migración duplicados `20260715120000_*` (no renombrar sin plan ops).
6. Deploy scripts hardcodean `feature/backoffice-api-integration`; `main` << lineage feature.
7. PRs auditoría draft acumulados: **#12/#14/#17/#18/#19/#20/#22/#24** — consolidar.
8. Logo PNG ~379 KB — LCP landing.

## Mejoras rápidas (este PR)

- [x] `LeadVerifiedWhatsAppNotifier` → `/admin/crud/mkt_leads/{id}`
- [x] Bootstrap: columnas leads API/churn + Stripe en órdenes + tabla `dom_mkt_membresias`
- [x] Tests SchemaBootstrap + LeadVerifiedWA
- [x] `VPS_CHECKLIST.md` payments/dunning + gate `PAYMENTS_SUBSCRIPTION_CHECKOUT=false`

## Riesgos de deploy VPS

- VPS debe seguir en **feature** (`4789f95`+), no `main`.
- Mantener `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` hasta cerrar #21.
- Mantener `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` hasta smoke.
- Fresh install: este PR alinea bootstrap; installs existentes dependen de migraciones ya listadas en `config/modules/marketing.php`.
- Crons dunning/churn documentados en checklist; confirmar crontab operador.

## Archivos involucrados

- `app/Application/Marketing/ConfirmarPagoStripeUseCase.php`
- `app/Application/Marketing/RecoverMembershipPaymentService.php`
- `src/Infrastructure/Payments/StripeGateway.php`
- `app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php`
- `database/schema/modules/marketing.sql`
- `docs/integration/VPS_CHECKLIST.md`
- `tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php`
- `tests/Marketing/SchemaBootstrapTest.php`

## Tests

- LeadVerifiedWhatsAppNotifier + SchemaBootstrap (+ suite Marketing/Payments si entorno lo permite).

## Recomendación final

**crear PR** (fixes bajo riesgo) + **requiere revisión humana** en #21 (criticals subscription) + seguimiento #23 en `main` (manifiestos migraciones). Consolidar drafts de auditoría.
