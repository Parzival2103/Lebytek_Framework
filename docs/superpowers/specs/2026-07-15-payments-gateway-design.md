# Pasarela de pagos con Stripe (puerto abierto en el framework)

**Fecha:** 2026-07-15
**Estado:** Diseño aprobado (corrigido post-review: claim atómico, money guard, `pay_events`, Ignored→200)
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
| Estrategia git | Rama dedicada `feature/payments-gateway` con **commits separados**: framework (`src/` + plataforma) vs tenant Lebytek (`app/`). |
| Secuencia vs split | **Implementar pagos ANTES** del plan `2026-07-15-framework-portal-separation`. En el monorepo actual, con frontera limpia por directorios para que el carve posterior arrastre pagos sin fricción. Ver sección "Alineación con el split". |

## Patrón de referencia

Replica el subsistema **Integrations** ya existente:
`MessageChannelInterface` → `PaymentGatewayInterface`, `ChannelRegistry` →
`PaymentGatewayRegistry`, `IntegrationsFactory` (`match($driver)`) → `PaymentsFactory`,
`config/integrations.php` → `config/payments.php`, adaptadores concretos en
`src/Infrastructure/`. Mismo estilo de lazy-resolution, credenciales sólo en `.env`.

**Pagos = módulo de plataforma opcional, idéntico en forma a `integrations`.** Se activa
por `config/vertical.php` (`vertical.modules.payments`), su binding en `container.php` va
*gated* por ese flag, y su SQL es un **módulo de plataforma** (`database/schema/modules/`),
no negocio. Esta equivalencia con `integrations` es la que garantiza que el split
framework↔portal lo carve limpio (ver sección siguiente).

---

## Alineación con el split framework↔portal

> **Fuente:** `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`
> (modelo de ownership, líneas 59–83 y 205–223).

**Secuencia acordada:** pagos se implementa **antes** de ejecutar el split. Por tanto,
todo vive en el monorepo actual con las convenciones de hoy (módulos resueltos por
`Installer`/`ROOT_PATH`; **`PackagePaths` aún no existe**, se crea en el Task 3 del split).
La única exigencia es **colocar cada archivo del lado correcto de la futura frontera**,
igual que ya se hace con `integrations.sql`, para que el carve del split lo arrastre sin
tocar nada.

### Ownership de cada artefacto de pagos

| Artefacto | Lado de la frontera | Dónde se coloca hoy (monorepo) | Qué hará el split |
|-----------|---------------------|--------------------------------|-------------------|
| `src/Domain/Payments/**`, `src/Application/Payments/**`, `src/Infrastructure/Payments/**` | **Framework** (plataforma) | `src/` | Viaja al paquete `lebytek/framework` |
| `database/schema/modules/payments.sql` (tabla `pay_events`) | **Framework** (plataforma) | `database/schema/modules/` junto a `integrations.sql` | Va al paquete; se listará en la resolución `PackagePaths`/BOUNDARY del split como módulo plataforma |
| `config/modules/payments.php` (manifiesto) | **Consumidor** (config) | `config/modules/` | El skeleton lo ship como módulo plataforma; su `bootstrap_sql` lo resolverá `PackagePaths::resolveDataFile` (paquete primero) |
| `config/payments.php` (mapa de gateways) | **Consumidor** (config) | `config/` junto a `config/integrations.php` | Skeleton ship default (OFF); Portal con valores reales |
| `vertical.modules.payments` | **Consumidor** (config) | `config/vertical.php` | Skeleton **OFF**; Portal **ON** |
| Binding `PaymentGatewayRegistry` en `container.php` | **Consumidor** (config) | `config/container.php`, *gated* por el flag vertical | Skeleton lo conserva pero OFF (como integrations) |
| `stripe/stripe-php` | **Framework** (dependencia) | `composer.json` raíz (require) | Va al `composer.json` del paquete; el Portal lo hereda transitivamente |
| Vars `STRIPE_*`, `PAYMENTS_*` | **Framework/plataforma** | `.env.example` del harness (como `GREEN_API_*`) | Skeleton `.env.example` (plantilla plataforma) + Portal `.env` reales |
| Columnas `metodo_pago`/`payment_provider`/`payment_ref` en `dom_mkt_ordenes` + migración `*mkt*` | **Portal** (negocio) | `database/migrations/` (negocio) | Va a `Lebytek_Portal` |
| `App\Application\Marketing\*` (use cases pago), `StripeWebhookController`, rutas, vistas | **Portal** (negocio) | `app/`, `routes/`, `app/Presentation/Views/` | Va a `Lebytek_Portal` |

### Reglas de frontera (no violar)

- **Prohibido** meter cualquier clase `App\…` de membresía dentro de `src/`, ni SQL de
  pagos de plataforma bajo un path de negocio. El puerto no conoce membresías.
- **Prohibido** que `config/payments.php` o `payments.sql` acaben como SoT duplicada en el
  Portal: el SQL de plataforma vive en el paquete; el Portal solo lo consume.
- La rama `feature/payments-gateway` debe **mergear antes** de arrancar la rama del split.
  Tras el Task 8 del split, cualquier cambio a `App\…\Marketing` en el repo Framework se
  rechaza; el wiring Portal de pagos caería en esa prohibición si llega tarde.
- **Handoff al plan de split:** al ejecutar el split habrá que añadir `payments.sql` a la
  lista de módulos plataforma del BOUNDARY / `PackagePaths` y a los módulos que el skeleton
  ship (OFF), en paralelo a `integrations.sql`. Es una nota para ese plan, no trabajo de
  esta spec.

---

## Sección 1 — Capa de framework (`src/`, `Lebytek\Framework\`)

### Dominio — `src/Domain/Payments/`
- `PaymentGatewayInterface` (puerto):
  - `key(): string` — `'stripe'`, futuro `'paypal'`, `'mercadopago'`.
  - `createCheckout(CheckoutRequest $req): CheckoutSession` — crea sesión de pago
    alojada; devuelve URL de redirección + id de sesión del proveedor.
  - `parseWebhook(string $payload, string $signature): PaymentEvent` — **verifica la
    firma** y normaliza el evento a un VO agnóstico del proveedor.
- `SupportsSubscriptions` — **marker vacío en v1** (YAGNI). No declarar firmas fake de
  `createSubscription`/`cancelSubscription` hasta fase 2; basta con el marker para que un
  gateway futuro pueda `implements SupportsSubscriptions` sin contaminar el puerto de pago único.
- Value Objects normalizados (agnósticos del proveedor):
  - `CheckoutRequest` — monto, moneda, descripción, email del cliente,
    `success_url`/`cancel_url`, referencia externa (order public_id), metadata,
    `mode` (`payment` | `subscription`).
  - `CheckoutSession` — id de sesión del proveedor + URL de redirección.
  - `PaymentEvent` — `type` (enum), `providerEventId` (idempotencia), `externalRef`,
    monto, moneda, estado crudo del proveedor.
  - `PaymentEventType` (enum) — `CheckoutCompleted`, `PaymentFailed`, `Ignored`
    (eventos Stripe no suscritos se normalizan a `Ignored`, **nunca** lanzan → HTTP 200).
  - `Money` — monto en unidades menores + moneda. **v1 acotado a `mxn`** (2 decimales;
    zero-decimal/3-decimal quedan fuera de alcance).
- `PaymentEventLogRepositoryInterface` — ledger atómico de `provider`+`event_id` UNIQUE.
  El puerto expone `tryClaim(...) : bool` (INSERT; duplicate → `false`), **no** el patrón
  check-then-act `hasProcessed` → work → `markProcessed`.

### Aplicación — `src/Application/Payments/`
- `PaymentGatewayRegistry` — clon de `ChannelRegistry`: `has()`, `get()`, `driver()`,
  lazy + memoizado, construido desde `config/payments.php`.
- `PaymentsFactory` — clon de `IntegrationsFactory`: lee `config/payments.php`,
  construye gateways con `match($driver) { 'stripe' => new StripeGateway(...) }`.
  Credenciales sólo desde `.env`. Driver no soportado → excepción.

> **Ownership:** `config/payments.php` es **config del consumidor** (skeleton/Portal lo
> ship), no del paquete — igual que `config/integrations.php`. El código de `src/Payments/`
> lo *lee*, no lo posee. Ver "Alineación con el split".

### Infraestructura — `src/Infrastructure/Payments/`
- `StripeGateway implements PaymentGatewayInterface` — envuelve `stripe/stripe-php`.
  `createCheckout` → `\Stripe\Checkout\Session::create`; `parseWebhook` →
  `\Stripe\Webhook::constructEvent` (payload + firma + `webhook_secret`) y mapea a
  `PaymentEvent`. **Ningún código fuera de este archivo importa el SDK de Stripe.**
- `PdoPaymentEventLogRepository implements PaymentEventLogRepositoryInterface` — sobre
  la tabla **`pay_events`** (prefijo `pay_`, paralelo a `int_*` de integrations; **no**
  `fw_payment_events`). Schema = módulo de plataforma
  (`database/schema/modules/payments.sql`), no negocio.
- `StripeGateway::createCheckout` pasa `idempotency_key` de sesión Stripe =
  `order_public_id` (evita sesiones duplicadas si el cliente reintenta el POST).

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
   │                              → StripeGateway.parseWebhook (firma; Ignored→200)
   │                              → ConfirmarPagoStripe (idempotente)
   │                                   → tryClaim(event_id) atómico; duplicate → return
   │                                   → validar amount/currency vs precio_snapshot
   │                                   → markPaid PRIMERO (dinero ya cobrado)
   │                                   → si tenant: activatePlan con Idempotency-Key estable
   │                                   → si no tenant / fallo API: paid + error ops (no throw)
   └─ elige "Transferencia" → flujo actual intacto (pending_transfer + alerta WA + autorización manual)
```

### Casos de uso — `app/Application/Marketing/`
- `IniciarPagoStripeUseCase` — construye `CheckoutRequest` (monto = `precio_snapshot`,
  moneda, `success_url`/`cancel_url`, `metadata.order_public_id`), llama
  `registry->get('stripe')->createCheckout()`, guarda `payment_ref`, devuelve la URL.
- `ConfirmarPagoStripeUseCase` — recibe el `PaymentEvent` del webhook. Orden obligatorio:
  1. `Ignored` / claim duplicate → return (HTTP 200, sin side-effects).
  2. `tryClaim(provider, event_id)` atómico; si `false` → ya procesado.
  3. Localizar orden por `externalRef` (`order_public_id`).
  4. Validar `amount_total`/`currency` vs `precio_snapshot` + `PAYMENTS_CURRENCY` (mxn).
  5. `PaymentFailed` → registrar en ledger (ya claimed); dejar `pending_payment` (reintento
     de checkout permitido); **no** throw.
  6. `CheckoutCompleted` + `pending_payment` → `markPaid` **antes** de llamar api;
     activación best-effort; **nunca throw** tras claim exitoso (Stripe no debe reintentar
     side-effects parciales).
  7. `Idempotency-Key` hacia api = UUID determinista derivado de
     `activate-plan|{order_public_id}` (vía overload en `LebytekApiClient::activatePlan`).

### Reutilización de la activación
Nuevo `ActivateMembershipFromOrderService` (no acoplar lógica frágil a un método privado
extraído de `AutorizarOrden`):
- `fromManualAuthorize(order, actorId)` — activa api **antes** de `markPaid` (si api falla,
  la orden sigue pendiente; flujo admin actual).
- `fromConfirmedPayment(order, actorId, idempotencyKey)` — `markPaid` **antes** de api
  (dinero ya cobrado); fallo api → `setApiActivationError`, no revierte `paid`.
Actor webhook: `MembershipOrderActors::SYSTEM_WEBHOOK = 0` (`authorized_by` BIGINT NULL
sin FK; en admin UI mostrar como “Sistema / Stripe”). Auto-activación best-effort si hay
`api_tenant_public_id`; si no, `paid` + ops manual.

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
- **Plataforma (módulo nuevo `payments`)**: tabla **`pay_events`**
  (`id`, `provider`, `event_id`, UNIQUE(`provider`,`event_id`), `order_ref`, `type`,
  `payload_hash`, `meta`, `processed_at`). Bootstrap en
  `database/schema/modules/payments.sql` + manifiesto `config/modules/payments.php`
  (`obligatorio => false`). Prefijo `pay_` paralelo a `int_*` (integrations).
- **Negocio (Portal)**: migración incremental `*mkt*` que añade a `dom_mkt_ordenes` las
  columnas `metodo_pago`, `payment_provider`, `payment_ref` y admite `pending_payment`. Se
  lista en `config/modules/marketing.php` (`migraciones[]`). Única SQL de pagos de negocio.

### Config / DI / entorno
> Todo lo de esta subsección es **config del consumidor** (skeleton/Portal), no del paquete.
> El paquete solo lleva copias no-deploy para el harness de tests. Espejo exacto de
> `integrations`.

- `config/vertical.php`: nuevo flag `vertical.modules.payments` — **OFF en skeleton**, ON
  en Portal. Gobierna el binding y las rutas de pago (como `marketing`/`integrations`).
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
- `config/container.php`: bind `PaymentGatewayRegistry` vía `PaymentsFactory` **gated por
  `vertical.modules.payments`** (como el bloque `integrations`); registrar
  `IniciarPagoStripeUseCase`, `ConfirmarPagoStripeUseCase`, `StripeWebhookController`, repos.
- `.env.example`: las vars `STRIPE_*` / `PAYMENTS_*` son **credenciales de una capacidad de
  plataforma** (como `GREEN_API_*`), no vars de producto (`LEBYTEK_API_*`/`MKT_*`). Van al
  `.env.example` del **harness framework/skeleton**; el Portal las repite con valores reales
  en su `.env`. **Nunca** con valores reales al repo.
- `composer.json`: `require stripe/stripe-php` en el `composer.json` del **paquete framework**
  (porque `StripeGateway` vive en `src/`); el Portal lo hereda transitivamente.

### Testing (patrón `php tests/run.php Marketing`)
- **Framework**: `StripeGateway::parseWebhook` (firma válida/inválida; evento no mapeado →
  `Ignored`), `PaymentGatewayRegistry`, `PaymentsFactory`, VOs + igualdad de `Money`,
  `PdoPaymentEventLogRepository::tryClaim` (duplicate → false).
- **App**: `ConfirmarPagoStripeUseCase` — claim atómico, monto/moneda mismatch, duplicate
  event, markPaid-antes-de-activate, sin throw tras claim, activate con Idempotency-Key
  estable; `IniciarPagoStripeUseCase` (CheckoutRequest + session idempotency);
  `ActivateMembershipFromOrderService`; bifurcación compra; webhook 400/200 sin leak de
  `$e->getMessage()` hacia el cliente.

### Estructura git — rama `feature/payments-gateway`, commits separados por frontera
En el **monorepo actual** (antes del split), una rama con dos grupos de commits separados
**por lado de la futura frontera** (ver tabla de ownership arriba), para que el carge del
split arrastre cada grupo sin editar nada.
- **Grupo A (lado framework/plataforma)**: `src/` (puerto + VOs + registry + factory +
  `StripeGateway` + repo de eventos), `database/schema/modules/payments.sql`,
  `config/modules/payments.php`, `require stripe/stripe-php` en el `composer.json` del
  paquete, y las vars `STRIPE_*` en el `.env.example` del harness. Tests de framework.
- **Grupo B (lado Portal/negocio)**: migración `*mkt*` de columnas en `dom_mkt_ordenes`,
  use cases `App\Application\Marketing\*`, repos, `CompraController`,
  `StripeWebhookController`, rutas, vistas (selector de método + éxito/cancelado). Tests de
  app.
- **Config del consumidor** (`config/payments.php`, `config/vertical.php`,
  `config/container.php` gated): edits en el monorepo hoy; el split los reubica en
  skeleton (OFF) / Portal (ON).

Regla de merge: esta rama **mergea antes** de abrir la rama del split. Resultado: pagos
queda ya partido por la frontera, y el carve framework↔portal lo separa mecánicamente.

## Alcance fuera de v1 (fase 2) — deuda nombrada (no bloquear ship)
| Deuda | Severidad | Acción mínima post-v1 |
|-------|-----------|------------------------|
| Cola async para webhook (activatePlan + email síncronos hoy) | Media | Job/cola cuando exista en monorepo |
| Purge / retención de `pay_events` | Baja | Script purge + nota ops |
| TTL / limpieza `pending_payment` huérfanas | Media | Query manual ops; job TTL opcional |
| Poll status / Session retrieve en página éxito | Media | Poll `findByPublicId` o retrieve session |
| Monedas zero-decimal / 3-decimal | Baja | Solo `mxn` en v1; ampliar `Money` después |
| Reembolsos y conciliación | Media | Fase 2 |
| `SupportsSubscriptions` con firmas reales | Baja | Fase 2 |
| `PaymentsFactory::$cached` reset en tests paralelos | Baja | `resetCached()` en harness si hace falta |

## Riesgos / notas
- **Seguridad del webhook**: firma obligatoria; ruta sin CSRF (middleware opt-in). Claim
  atómico en `pay_events` (`UNIQUE(provider,event_id)`). Eventos no suscritos → 200
  `Ignored` (nunca 400 por tipo). Respuestas 400 **sin** filtrar `$e->getMessage()` al
  cliente (mensaje genérico).
- **Invariante de dinero**: rechazar `CheckoutCompleted` si
  `amount_total`/`currency` ≠ snapshot de la orden (tras claim: registrar y no activar).
- **Idempotencia api**: `Idempotency-Key` estable (no UUID fresco) en el path Stripe;
  admin transfer puede seguir con UUID fresco (replay semántico api ya cubierto).
- **Activación sin tenant**: best-effort; orden `paid` + ops manual.
- **Raw body**: `Request` del framework no consume `php://input` hoy; documentar + test
  de contrato en el controller webhook.
- **Secretos**: `.env` nunca se commitea; sólo `.env.example` con placeholders.
- **Actor `SYSTEM_WEBHOOK=0`**: semántica documentada en admin UI (“Sistema / Stripe”).
