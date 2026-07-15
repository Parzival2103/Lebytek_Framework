# Pasarela de pagos con Stripe (puerto abierto en el framework)

**Fecha:** 2026-07-15
**Estado:** Diseño aprobado
**Rama de trabajo:** `feature/payments-gateway`

## Objetivo

Implementar pagos con tarjeta para la compra de membresías usando Stripe, dejando
la abstracción de pasarela **abierta en el framework** (`src/`) para poder añadir en
el futuro otros proveedores (PayPal, Mercado Pago, etc.) sin reescribir el flujo desde
cero. La empresa Lebytek es cliente de su propio framework: el puerto y los adaptadores
son reutilizables por cualquier tenant; el wiring "pago confirmado → activar plan" es
específico de Lebytek y vive en `app/`.

## Decisiones tomadas (brainstorming)

| Decisión | Elección |
|----------|----------|
| Modelo de cobro | Diseñar el puerto para **pago único y suscripción**; implementar **pago único** primero (suscripción = fase 2). |
| Convivencia con transferencia | **Coexisten**: el cliente elige tarjeta (Stripe, auto-activación) o transferencia (flujo manual actual). Ambos terminan en la misma orden y activación de plan. |
| Ubicación de adaptadores | Puerto + registro + **adaptadores en el framework** (`src/Infrastructure/Payments/`). Máxima reutilización; lo específico de Lebytek en `app/`. |
| Captura de tarjeta | **Stripe Checkout alojado** (redirección). PCI SAQ-A mínimo, cero datos de tarjeta en el servidor. |
| Cliente Stripe | **SDK oficial `stripe/stripe-php`** vía Composer (verificación de firma de webhook, idempotencia, reintentos). Envuelto tras el puerto. |
| Estrategia git | Rama dedicada `feature/payments-gateway` con **commits separados**: framework (mergeable a `main`) vs tenant Lebytek. |

## Patrón de referencia

Replica el subsistema **Integrations** ya existente:
`MessageChannelInterface` → `PaymentGatewayInterface`, `ChannelRegistry` →
`PaymentGatewayRegistry`, `IntegrationsFactory` (`match($driver)`) → `PaymentsFactory`,
`config/integrations.php` → `config/payments.php`, adaptadores concretos en
`src/Infrastructure/`. Mismo estilo de lazy-resolution, credenciales sólo en `.env`.

---

## Sección 1 — Capa de framework (`src/`, `Lebytek\Framework\`)

### Dominio — `src/Domain/Payments/`
- `PaymentGatewayInterface` (puerto):
  - `key(): string` — `'stripe'`, futuro `'paypal'`, `'mercadopago'`.
  - `createCheckout(CheckoutRequest $req): CheckoutSession` — crea sesión de pago
    alojada; devuelve URL de redirección + id de sesión del proveedor.
  - `parseWebhook(string $payload, string $signature): PaymentEvent` — **verifica la
    firma** y normaliza el evento a un VO agnóstico del proveedor.
- `SupportsSubscriptions` (sub-interfaz, **fase 2**): `createSubscription()`,
  `cancelSubscription()`. Mantiene los gateways de pago único libres de métodos de
  suscripción. No se implementa en v1 (YAGNI), pero el puerto queda abierto para ambos.
- Value Objects normalizados (agnósticos del proveedor):
  - `CheckoutRequest` — monto, moneda, descripción, email del cliente,
    `success_url`/`cancel_url`, referencia externa (order public_id), metadata,
    `mode` (`payment` | `subscription`).
  - `CheckoutSession` — id de sesión del proveedor + URL de redirección.
  - `PaymentEvent` — `type` (enum), `providerEventId` (idempotencia), `externalRef`,
    monto, moneda, estado crudo del proveedor.
  - `PaymentEventType` (enum) — `CheckoutCompleted`, `PaymentFailed`, … (extensible).
  - `Money` — monto en unidades menores + moneda.
- `PaymentEventLogRepositoryInterface` — persistencia de `provider_event_id` únicos
  (idempotencia + auditoría de webhooks).

### Aplicación — `src/Application/Payments/`
- `PaymentGatewayRegistry` — clon de `ChannelRegistry`: `has()`, `get()`, `driver()`,
  lazy + memoizado, construido desde `config/payments.php`.
- `PaymentsFactory` — clon de `IntegrationsFactory`: lee `config/payments.php`,
  construye gateways con `match($driver) { 'stripe' => new StripeGateway(...) }`.
  Credenciales sólo desde `.env`. Driver no soportado → excepción.

### Infraestructura — `src/Infrastructure/Payments/`
- `StripeGateway implements PaymentGatewayInterface` — envuelve `stripe/stripe-php`.
  `createCheckout` → `\Stripe\Checkout\Session::create`; `parseWebhook` →
  `\Stripe\Webhook::constructEvent` (payload + firma + `webhook_secret`) y mapea a
  `PaymentEvent`. **Ningún código fuera de este archivo importa el SDK de Stripe.**
- `PdoPaymentEventLogRepository implements PaymentEventLogRepositoryInterface` — sobre
  la tabla `fw_payment_events`.

**Invariante clave:** el framework sabe *cobrar y confirmar un pago*, pero no conoce
membresías Lebytek. La semántica "pago confirmado → activar plan" vive íntegra en `app/`.

---

## Sección 2 — Capa de app (`app/`, `App\`, tenant Lebytek)

### Flujo end-to-end

```
Cliente en /comprar/{slug}
   ├─ elige "Tarjeta"      → CrearOrden (status: pending_payment, sin alerta WA)
   │                         → IniciarPagoStripe → CheckoutSession → redirect a Stripe
   │                         → Stripe cobra → redirect a /pago/exito (pantalla "confirmando…")
   │                         → [async] webhook POST /webhooks/stripe
   │                              → StripeGateway.parseWebhook (verifica firma)
   │                              → ConfirmarPagoStripe (idempotente)
   │                                   → markPaid
   │                                   → si tenant asociado: activar plan + email (auto)
   │                                   → si no: queda "paid", avisa al equipo (activación manual)
   └─ elige "Transferencia" → flujo actual intacto (pending_transfer + alerta WA + autorización manual)
```

### Casos de uso — `app/Application/Marketing/`
- `IniciarPagoStripeUseCase` — construye `CheckoutRequest` (monto = `precio_snapshot`,
  moneda, `success_url`/`cancel_url`, `metadata.order_public_id`), llama
  `registry->get('stripe')->createCheckout()`, guarda `payment_ref`, devuelve la URL.
- `ConfirmarPagoStripeUseCase` — recibe el `PaymentEvent` normalizado del webhook.
  Comprueba idempotencia contra `fw_payment_events`; localiza la orden por metadata;
  en `CheckoutCompleted`: `markPaid` → intenta activación (best-effort).

### Reutilización de la activación
`AutorizarOrdenMembresiaUseCase` hoy exige usuario (`authorizedBy`) y tenant asociado,
y lanza excepción si faltan. Se **extrae su núcleo** a un método reutilizable
`activarPlanYNotificar(order, actorId)` invocable tanto por la autorización manual del
admin como por la confirmación por webhook, usando un **actor de sistema**
(`authorized_by` nullable / sentinela). La auto-activación por webhook es **best-effort**:
si el tenant no está asociado, la orden queda `paid` y ops la termina con el flujo manual
existente. La idempotencia + las transiciones de estado evitan la doble activación.

### Controllers y rutas — `app/`
- `CompraController` — `show` renderiza el selector de método; `submit` bifurca
  (stripe/transfer). Añade `pagoExito()` y `pagoCancelado()`.
- `StripeWebhookController` (nuevo) — lee el body crudo + header `Stripe-Signature`,
  delega en el registry + `ConfirmarPagoStripeUseCase`, responde 200/400. **Sin CSRF**
  (server-to-server, validado por firma).
- Rutas nuevas:
  - `GET /comprar/orden/{publicId}/pago/exito`
  - `GET /comprar/orden/{publicId}/pago/cancelado`
  - `POST /webhooks/stripe` (excluida de CSRF)

### Repos / orden — `app/`
- `dom_mkt_ordenes`: nuevas columnas `metodo_pago` (`transfer|stripe`),
  `payment_provider`, `payment_ref`; nuevo status `pending_payment`.
- `MembershipOrderRepositoryInterface` + `PdoMembershipOrderRepository`:
  `markPaymentPending()`, `findByPaymentRef()` / búsqueda por metadata.
- `CrearOrdenMembresiaUseCase`: parametrizar método de pago (stripe → `pending_payment`,
  sin alerta de transferencia WhatsApp).

---

## Sección 3 — Datos, config/DI, git y testing

### Modelo de datos
- **Framework** (módulo nuevo `payments`): tabla `fw_payment_events`
  (`id`, `provider`, `event_id` UNIQUE, `order_ref`, `type`, `payload_hash`,
  `processed_at`). Bootstrap en `database/schema/modules/payments.sql` +
  manifiesto `config/modules/payments.php` (`obligatorio => false`, registra el binding
  del registry).
- **App/marketing**: migración incremental que añade a `dom_mkt_ordenes` las columnas
  `metodo_pago`, `payment_provider`, `payment_ref` y admite `pending_payment`. Se lista
  en `config/modules/marketing.php` (`migraciones[]`).

### Config / DI / entorno
- `config/payments.php` (clon de `integrations.php`):
  ```php
  'gateways' => [
    'stripe' => [
      'driver'  => 'stripe',
      'class'   => StripeGateway::class,
      'enabled' => (bool) EnvLoader::get('STRIPE_ENABLED', false),
      'config'  => [
        'secret_key'     => EnvLoader::get('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => EnvLoader::get('STRIPE_WEBHOOK_SECRET', ''),
        'currency'       => EnvLoader::get('PAYMENTS_CURRENCY', 'mxn'),
      ],
    ],
  ],
  'default' => EnvLoader::get('PAYMENTS_DEFAULT_GATEWAY', 'stripe'),
  ```
- `config/container.php`: bind `PaymentGatewayRegistry` vía `PaymentsFactory`; registrar
  `IniciarPagoStripeUseCase`, `ConfirmarPagoStripeUseCase`, `StripeWebhookController`,
  repos.
- `.env.example`: `STRIPE_ENABLED`, `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`,
  `STRIPE_WEBHOOK_SECRET`, `PAYMENTS_CURRENCY`. **Nunca** con valores reales al repo.
- `composer.json`: `require stripe/stripe-php`.

### Testing (patrón `php tests/run.php Marketing`)
- **Framework**: `StripeGateway::parseWebhook` (firma válida/inválida con fixtures),
  `PaymentGatewayRegistry` (resolución/lazy), `PaymentsFactory` (`match` de driver +
  driver no soportado lanza), VOs.
- **App**: `ConfirmarPagoStripeUseCase` (idempotencia: evento repetido no reactiva;
  transición de estados; auto-activación con/sin tenant), `IniciarPagoStripeUseCase`
  (arma `CheckoutRequest` correcto), bifurcación de `CompraController`. Gateway y repos
  mockeados.

### Estructura git — rama `feature/payments-gateway`, commits separados
- **Grupo A (framework)**: `src/` (puerto + VOs + registry + factory + `StripeGateway` +
  repo de eventos), `config/payments.php`, módulo `payments` (`config/modules/payments.php`
  + `database/schema/modules/payments.sql`), `composer require stripe/stripe-php`, tests
  de framework. Autocontenido y mergeable a `main` sin arrastrar nada de Lebytek.
- **Grupo B (app/tenant Lebytek)**: migración de columnas en `dom_mkt_ordenes`, use cases,
  repos, `CompraController`, `StripeWebhookController`, rutas, vistas (selector de método +
  éxito/cancelado), `.env.example`, tests de app.

Resultado: `main` recibe una capacidad de pagos genérica y reutilizable; la rama conserva
el wiring Lebytek identificable — la separación "main (framework base) vs rama (tenant
Lebytek)" pedida.

## Alcance fuera de v1 (fase 2)
- Suscripciones recurrentes de Stripe (auto-renovación, fallos de cobro, portal del
  cliente) vía `SupportsSubscriptions`.
- Adaptadores PayPal / Mercado Pago (sólo requieren implementar el puerto + registrar
  driver en `config/payments.php`, sin tocar `app/`).
- Reembolsos y conciliación.

## Riesgos / notas
- **Seguridad del webhook**: la verificación de firma es obligatoria; la ruta se excluye
  de CSRF pero valida `Stripe-Signature`. Idempotencia por `fw_payment_events.event_id`.
- **Activación sin tenant**: la auto-activación por webhook depende de que el tenant esté
  asociado a la orden; si no, se degrada limpiamente al flujo manual de ops (sin bloquear
  la confirmación del pago).
- **Secretos**: `.env` nunca se commitea; sólo `.env.example` con placeholders.
