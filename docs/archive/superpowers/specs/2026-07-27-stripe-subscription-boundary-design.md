# Stripe subscription — frontera Framework vs Portal (#21)

**Fecha:** 2026-07-27  
**Issue:** [Lebytek_Framework#21](https://github.com/Parzival2103/Lebytek_Framework/issues/21)  
**Gate ops:** `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en VPS hasta cierre #21 + release semver consumido por Portal.

## Objetivo

Cerrar los 6 criticals de subscription/recover/amount sin mezclar negocio Marketing en el paquete
`lebytek/framework`. El Framework publica contratos y parsing Stripe genéricos; Portal posee
use cases, rutas y bindings.

## Frontera

| Capa | Repo | Responsabilidad |
|------|------|-----------------|
| Dominio Payments | Framework `src/Domain/Payments/` | `PaymentEventType`, `PaymentEvent`, `SupportsSubscriptions`, VOs |
| Infra Stripe | Framework `src/Infrastructure/Payments/StripeGateway.php` | Checkout payment/subscription, Billing Portal, parse webhook |
| Ledger idempotencia | Framework `pay_events` + `PaymentEventLogRepositoryInterface` | `tryClaim` / `releaseClaim` |
| Orden → activate-plan | Portal `ConfirmarPagoStripeUseCase` | Validar monto, marcar paid, activar tenant |
| Membresía dunning/recover | Portal `RecoverMembershipPaymentService` | Billing Portal si hay `stripe_customer_id`; bind subscription |
| Rutas públicas | Portal `MembresiaPagoController`, `StripeWebhookController` | Retry/reactivate; HTTP 500 si fallo post-claim |

## Criticals → owner

| ID | Problema | Framework | Portal |
|----|----------|-----------|--------|
| C1 | Subscription first-activation no-op | `PaymentEvent` expone `subscriptionId`; checkout subscription parseado | `ConfirmarPagoStripeUseCase` activa orden en `CheckoutCompleted` aunque haya subscription |
| C2 | `invoice.paid` sin `order_public_id` | `PaymentEventType::InvoicePaid`; metadata en `subscription_data` al crear checkout | Confirmar procesa `InvoicePaid` igual que checkout para orden pendiente |
| C3 | Retry crea subscription nueva | `createBillingPortalSession()` en `SupportsSubscriptions` | `checkoutUrlForMembresia` usa Portal si hay customer; si no, subscription checkout con `membresia_id` en metadata |
| C4 | Post-claim swallow | `releaseClaim()` en repo | Confirmar libera claim y relanza; webhook responde ≠200 |
| C5 | Recover cancelled desync | — | Ya corregido: reactivation fallida no llama `markActive` |
| C6 | Amount bypass moneda ≠ MXN | `parseWebhook` mapea moneda distinta a `PaymentFailed` | Confirmar siempre compara `Money` con snapshot (sin skip por amount 0) |

## Contrato Framework (v1.2.1)

### `PaymentEventType`

- Existentes: `CheckoutCompleted`, `PaymentFailed`, `Ignored`
- Nuevo: `InvoicePaid`

### `PaymentEvent`

Campos opcionales además del contrato v1.1:

- `subscriptionId(): ?string`
- `customerId(): ?string`
- `checkoutMode(): string` — `payment` | `subscription`
- `membresiaId(): ?string` — metadata Portal para recover (no order)

### `SupportsSubscriptions`

```php
/** @param array{price_id:string,customer_email:string,success_url:string,cancel_url:string,external_ref:string,metadata?:array<string,string>} */
createSubscriptionCheckout(array $params): CheckoutSession;

/** @param array{customer_id:string,return_url:string} */
createBillingPortalSession(array $params): CheckoutSession;
```

Subscription checkout **debe** propagar metadata a `subscription_data.metadata` para que
`invoice.paid` resuelva la referencia.

### `PaymentEventLogRepositoryInterface`

```php
releaseClaim(string $provider, string $eventId): void;
```

Elimina el claim cuando el procesamiento falla, permitiendo reintento del proveedor.

## Flujos Portal

### Compra inicial (orden)

1. `IniciarPagoStripeUseCase` — mode `payment` (sin cambio).
2. Webhook `checkout.session.completed` → Confirmar → activate-plan.

### Recover / reactivate (membresía)

1. Si `stripe_customer_id` presente → Billing Portal (actualizar método de pago / subscription existente).
2. Si no → `createSubscriptionCheckout` con `metadata.membresia_id` (no `membresia-{tenant}` como order ref).
3. Webhook con `membresia_id` → `RecoverMembershipPaymentService::recoverAfterSuccessfulPayment`.

## Criterios de aceptación

- [ ] First subscription checkout activa orden y bindea subscription en membresía del tenant
- [ ] `invoice.paid` con metadata de subscription activa orden pendiente
- [ ] Retry con customer existente usa Billing Portal
- [ ] Fallo post-claim → `releaseClaim` + HTTP 500 (Stripe reintenta)
- [ ] Reactivation API fallida deja membresía `cancelled` (regresión C5)
- [ ] Moneda ≠ MXN no activa orden (PaymentFailed + validación Money)
- [ ] Tests Framework `Payments/*` y Portal `ConfirmarPagoStripeUseCase`, `MembershipDunning` verdes

## Fuera de alcance

- Habilitar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` en VPS (humano post-merge)
- Renovaciones recurrentes `invoice.paid` sobre orden ya `paid` (no-op / extensión periodo — backlog)
