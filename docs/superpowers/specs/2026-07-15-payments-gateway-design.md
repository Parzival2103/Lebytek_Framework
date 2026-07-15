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
| `database/schema/modules/payments.sql` (tabla `fw_payment_events`) | **Framework** (plataforma) | `database/schema/modules/` junto a `integrations.sql` | Va al paquete; se listará en la resolución `PackagePaths`/BOUNDARY del split como módulo plataforma |
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

> **Ownership:** `config/payments.php` es **config del consumidor** (skeleton/Portal lo
> ship), no del paquete — igual que `config/integrations.php`. El código de `src/Payments/`
> lo *lee*, no lo posee. Ver "Alineación con el split".

### Infraestructura — `src/Infrastructure/Payments/`
- `StripeGateway implements PaymentGatewayInterface` — envuelve `stripe/stripe-php`.
  `createCheckout` → `\Stripe\Checkout\Session::create`; `parseWebhook` →
  `\Stripe\Webhook::constructEvent` (payload + firma + `webhook_secret`) y mapea a
  `PaymentEvent`. **Ningún código fuera de este archivo importa el SDK de Stripe.**
- `PdoPaymentEventLogRepository implements PaymentEventLogRepositoryInterface` — sobre
  la tabla `fw_payment_events` (**tabla de plataforma**; su prefijo debe seguir la
  convención de las tablas de plataforma existentes —revisar las de `integrations`— en vez
  de asumir `fw_`). El schema es un **módulo de plataforma**
  (`database/schema/modules/payments.sql`), no negocio.

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
- **Plataforma (módulo nuevo `payments`)**: tabla de plataforma para el ledger de eventos
  (`id`, `provider`, `event_id` UNIQUE, `order_ref`, `type`, `payload_hash`,
  `processed_at`). Bootstrap en `database/schema/modules/payments.sql` (SQL de plataforma,
  colocado junto a `integrations.sql`) + manifiesto `config/modules/payments.php`
  (`obligatorio => false`). Hoy lo resuelve el `Installer` por convención de módulos; tras
  el split el `bootstrap_sql` se resolverá `PackagePaths::resolveDataFile` (paquete
  primero). Prefijo de tabla según la convención de plataforma (revisar `integrations`).
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
- **Framework**: `StripeGateway::parseWebhook` (firma válida/inválida con fixtures),
  `PaymentGatewayRegistry` (resolución/lazy), `PaymentsFactory` (`match` de driver +
  driver no soportado lanza), VOs.
- **App**: `ConfirmarPagoStripeUseCase` (idempotencia: evento repetido no reactiva;
  transición de estados; auto-activación con/sin tenant), `IniciarPagoStripeUseCase`
  (arma `CheckoutRequest` correcto), bifurcación de `CompraController`. Gateway y repos
  mockeados.

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
