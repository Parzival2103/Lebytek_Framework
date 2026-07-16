# Payments Gateway (Stripe) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Stripe Checkout (hosted redirect) for membership purchases while keeping bank transfer, with a reusable payment gateway port in `src/` and Lebytek-specific wiring in `app/`.

**Architecture:** Mirror the existing **Integrations** subsystem (`MessageChannelInterface` → `PaymentGatewayInterface`, `ChannelRegistry` → `PaymentGatewayRegistry`, `IntegrationsFactory` → `PaymentsFactory`). Platform code lives in `src/` + `database/schema/modules/payments.sql` (`pay_events`); membership checkout, webhooks, and plan activation live in `app/`. Stripe SDK is wrapped only inside `StripeGateway`. Webhook correctness = atomic event claim **before** side-effects, stable api `Idempotency-Key`, money/currency guard, unsupported events → HTTP 200.

**Tech Stack:** PHP 8.1+, Lebytek Onion monorepo, PDO, `stripe/stripe-php`, microtest (`php tests/run.php`), Stripe Checkout (hosted), webhook signature verification.

**Spec:** `docs/superpowers/specs/2026-07-15-payments-gateway-design.md`

**Prerequisite flow (already shipped):** manual membership purchase on `feature/backoffice-api-integration` — `CompraController`, `CrearOrdenMembresiaUseCase`, `AutorizarOrdenMembresiaUseCase`, `dom_mkt_ordenes`, `LebytekApiClient::activatePlan`. See `docs/superpowers/plans/2026-07-14-manual-membership-purchase.md`.

## Global Constraints

- Work on branch **`feature/payments-gateway`** cut from `feature/backoffice-api-integration`.
- **Do not** merge `feature/backoffice-api-integration` → `main` unless the user explicitly orders it.
- **Do not** merge this branch before completing both framework (`src/`) and app (`app/`) groups; merge **before** starting `2026-07-15-framework-portal-separation`.
- **No** Stripe/PayPal/Mercado Pago code outside `src/Infrastructure/Payments/StripeGateway.php`.
- **No** membership/plan logic inside `src/Domain/Payments/` or `src/Application/Payments/`.
- **No** duplicate platform SQL in Portal paths — platform ledger = `database/schema/modules/payments.sql` (`pay_events`); business columns = one `*mkt*` migration.
- Ledger table name is **`pay_events`** (prefijo `pay_`, paralelo a `int_*`). Never `fw_payment_events`.
- v1 implements **one-time payment** only (`mode: payment`); `SupportsSubscriptions` is an **empty marker** (fase 2 — no fake method signatures).
- Currency v1 locked to **`mxn`** (2-decimal). `Money::fromMajor` ×100 is valid only under this constraint.
- Webhook route **without CSRF**; security = `Stripe-Signature` + atomic `pay_events` claim. Bad signature → 400 with **generic** JSON error (never leak `$e->getMessage()`).
- Unsupported Stripe event types → `PaymentEventType::Ignored` → HTTP **200** (never 400 — Stripe must not retry forever).
- Credenciales only in `.env`; `.env.example` gets placeholders only.
- `vertical.modules.payments` **OFF** in skeleton, **ON** in Lebytek Portal deploy config.
- `STRIPE_ENABLED=false` by default until ops configures keys.
- Purchasable slugs remain **`starter`** and **`business`** only (same as transfer flow).
- Auto-activation is **best-effort**: requires `api_tenant_public_id` on the order; otherwise order becomes `paid` and ops finishes manually.
- Actor for webhook activation: `authorized_by = 0` (`MembershipOrderActors::SYSTEM_WEBHOOK`; column already nullable; admin UI label “Sistema / Stripe”).
- **Correctness invariants (blocking):**
  1. Claim event **before** any side-effect (`tryClaim` INSERT UNIQUE; duplicate → return).
  2. After a successful claim, ConfirmarPago **never throws** (Stripe must not re-run side-effects).
  3. Stripe path: `markPaid` **before** `activatePlan` (money already captured).
  4. Stable `Idempotency-Key` for Stripe-triggered `activatePlan` (deterministic UUID from `activate-plan|{order_public_id}`).
  5. Validate `amount_total`/`currency` vs `precio_snapshot` + `PAYMENTS_CURRENCY`.
- **Commits:** separate groups — Grupo A (framework) vs Grupo B (app) vs Grupo C (consumer config). Only commit when the user asks.

## Named technical debt (explicitly out of v1 — do not implement here)

| Deuda | Severidad | Acción post-v1 |
|-------|-----------|----------------|
| Cola async webhook (activate + email sync under Stripe timeout) | Media | Job cuando exista cola en monorepo |
| Purge / retención `pay_events` | Baja | Script purge |
| TTL `pending_payment` huérfanas | Media | Query ops; job TTL opcional |
| Poll / Session.retrieve en `/pago/exito` | Media | Mejora UX |
| Monedas zero-decimal / 3-decimal | Baja | Ampliar `Money` |
| Reembolsos / conciliación | Media | Fase 2 |
| Firmas reales en `SupportsSubscriptions` | Baja | Fase 2 |

---

## File Structure

### Framework (Grupo A — travels to `lebytek/framework` package)

| Path | Role |
|------|------|
| `src/Domain/Payments/PaymentGatewayInterface.php` | Port: `key()`, `createCheckout()`, `parseWebhook()` |
| `src/Domain/Payments/SupportsSubscriptions.php` | Empty marker (fase 2) |
| `src/Domain/Payments/PaymentEventType.php` | `CheckoutCompleted`, `PaymentFailed`, `Ignored` |
| `src/Domain/Payments/ValueObjects/Money.php` | Minor units + currency; `equals()` |
| `src/Domain/Payments/ValueObjects/CheckoutRequest.php` | Normalized checkout input |
| `src/Domain/Payments/ValueObjects/CheckoutSession.php` | Provider session id + redirect URL |
| `src/Domain/Payments/ValueObjects/PaymentEvent.php` | Normalized webhook event |
| `src/Domain/Payments/PaymentEventLogRepositoryInterface.php` | `tryClaim(...) : bool` (+ optional `hasProcessed` read) |
| `src/Application/Payments/PaymentGatewayRegistry.php` | Lazy registry |
| `src/Application/Payments/PaymentsFactory.php` | `match($driver)` + `resetCached()` for tests |
| `src/Infrastructure/Payments/StripeGateway.php` | Only file importing `stripe/stripe-php` |
| `src/Infrastructure/Payments/PdoPaymentEventLogRepository.php` | Atomic claim on `pay_events` |
| `database/schema/modules/payments.sql` | Platform table `pay_events` |
| `config/modules/payments.php` | Module manifest (`obligatorio => false`) |
| `composer.json` | `"stripe/stripe-php": "^16.0"` |
| `tests/Payments/*.php` | Framework unit tests |

### Consumer config (Grupo C — skeleton OFF / Portal ON)

| Path | Role |
|------|------|
| `config/payments.php` | Gateway map |
| `config/vertical.php` | `'payments' => false` (Portal: `true`) |
| `skeleton/config/payments.php` | Skeleton copy (OFF) |
| `skeleton/config/vertical.php` | `'payments' => false` |
| `config/container.php` | Gated bindings when `vertical.modules.payments` |
| `.env.example` | `STRIPE_*`, `PAYMENTS_*` placeholders |

### App / Portal (Grupo B)

| Path | Role |
|------|------|
| `database/migrations/20260715120000_mkt_ordenes_stripe.sql` | Columns + `pending_payment` |
| `config/modules/marketing.php` | Register new migration |
| `app/Domain/Marketing/Contracts/MembershipOrderRepositoryInterface.php` | Payment methods + lookups |
| `app/Infrastructure/Marketing/PdoMembershipOrderRepository.php` | SQL for new columns |
| `app/Application/Marketing/MembershipOrderActors.php` | `SYSTEM_WEBHOOK = 0` |
| `app/Application/Marketing/ActivateMembershipFromOrderService.php` | Shared activation (manual vs paid paths) |
| `app/Application/Marketing/AutorizarOrdenMembresiaUseCase.php` | Delegates to service (manual path) |
| `app/Infrastructure/Integrations/LebytekApi/LebytekApiClient.php` | Optional stable `Idempotency-Key` on `activatePlan` |
| `app/Application/Marketing/CrearOrdenMembresiaUseCase.php` | Parametrize payment method |
| `app/Application/Marketing/IniciarPagoStripeUseCase.php` | Build checkout + redirect URL |
| `app/Application/Marketing/ConfirmarPagoStripeUseCase.php` | Atomic claim + money guard + activation |
| `app/Presentation/Controllers/Publico/CompraController.php` | Method selector + success/cancel |
| `app/Presentation/Controllers/Publico/StripeWebhookController.php` | Raw body + signature |
| `app/Presentation/Views/publico/compra_form.php` | Tarjeta vs transferencia |
| `app/Presentation/Views/publico/compra_pago_exito.php` | "Confirmando pago…" |
| `app/Presentation/Views/publico/compra_pago_cancelado.php` | Cancel message |
| `routes/marketing.php` | New GET/POST routes |
| `tests/Marketing/*Stripe*.php` / related | App tests with mocks |

---

### Task 1: Payment domain VOs and port

**Files:**
- Create: `src/Domain/Payments/PaymentGatewayInterface.php`
- Create: `src/Domain/Payments/SupportsSubscriptions.php`
- Create: `src/Domain/Payments/PaymentEventType.php`
- Create: `src/Domain/Payments/ValueObjects/Money.php`
- Create: `src/Domain/Payments/ValueObjects/CheckoutRequest.php`
- Create: `src/Domain/Payments/ValueObjects/CheckoutSession.php`
- Create: `src/Domain/Payments/ValueObjects/PaymentEvent.php`
- Create: `src/Domain/Payments/PaymentEventLogRepositoryInterface.php`
- Test: `tests/Payments/PaymentValueObjectsTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `PaymentGatewayInterface`, VOs, `PaymentEventLogRepositoryInterface::tryClaim(...): bool`, `hasProcessed(...): bool`

**Acceptance Criteria:**
- `Money::fromMajor(2199, 'mxn')` → 219900 minor; `equals()` compares minor+currency.
- Empty currency or non-`mxn` in constructor/`fromMajor` throws `\InvalidArgumentException` (v1 lock).
- `PaymentEventType` includes `Ignored`.
- `tryClaim` is the write path for idempotency (documented on interface).

- [ ] **Step 1: Write the failing test**

Create `tests/Payments/PaymentValueObjectsTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

test('Money convierte pesos MXN a centavos', function (): void {
    $money = Money::fromMajor(2199.00, 'mxn');
    assert_same(219900, $money->amountMinor());
    assert_same('mxn', $money->currency());
});

test('Money equals compara minor y currency', function (): void {
    assert_true(Money::fromMajor(2199.0, 'mxn')->equals(new Money(219900, 'mxn')));
    assert_true(! Money::fromMajor(2199.0, 'mxn')->equals(new Money(219901, 'mxn')));
});

test('Money rechaza currency distinta de mxn en v1', function (): void {
    assert_throws(\InvalidArgumentException::class, fn () => Money::fromMajor(100, 'usd'));
    assert_throws(\InvalidArgumentException::class, fn () => Money::fromMajor(100, ''));
});

test('CheckoutRequest exige mode payment o subscription', function (): void {
    $req = new CheckoutRequest(
        money: Money::fromMajor(100.0, 'mxn'),
        description: 'Starter mensual',
        customerEmail: 'buyer@example.com',
        successUrl: 'https://lebytek.com/pago/exito',
        cancelUrl: 'https://lebytek.com/pago/cancelado',
        externalRef: '01JABCDEF',
        metadata: ['order_public_id' => '01JABCDEF'],
        mode: 'payment',
    );
    assert_same('payment', $req->mode());
    assert_same('01JABCDEF', $req->externalRef());
});

test('PaymentEvent normaliza tipo completado e Ignored', function (): void {
    $done = new PaymentEvent(
        type: PaymentEventType::CheckoutCompleted,
        providerEventId: 'evt_123',
        externalRef: '01JABCDEF',
        money: Money::fromMajor(2199.0, 'mxn'),
        rawStatus: 'complete',
    );
    assert_same(PaymentEventType::CheckoutCompleted, $done->type());
    $ignored = new PaymentEvent(
        type: PaymentEventType::Ignored,
        providerEventId: 'evt_ignored',
        externalRef: '',
        money: new Money(0, 'mxn'),
        rawStatus: 'customer.created',
    );
    assert_same(PaymentEventType::Ignored, $ignored->type());
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
cd c:/Users/User/OneDrive/Desktop/sistemas/Lebytek_Framework
php tests/run.php Payments/PaymentValueObjectsTest
```

Expected: FAIL — classes not found.

- [ ] **Step 3: Write minimal implementation**

`src/Domain/Payments/PaymentEventType.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

enum PaymentEventType: string
{
    case CheckoutCompleted = 'checkout.completed';
    case PaymentFailed = 'payment.failed';
    case Ignored = 'ignored';
}
```

`src/Domain/Payments/ValueObjects/Money.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

final readonly class Money
{
    private string $currency;

    public function __construct(
        private int $amountMinor,
        string $currency,
    ) {
        $normalized = strtolower($currency);
        if ($normalized !== 'mxn') {
            throw new \InvalidArgumentException('v1 payments only support mxn currency');
        }
        $this->currency = $normalized;
    }

    public static function fromMajor(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function amountMinor(): int { return $this->amountMinor; }
    public function currency(): string { return $this->currency; }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }
}
```

`src/Domain/Payments/ValueObjects/CheckoutRequest.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

final readonly class CheckoutRequest
{
    /** @param array<string, string> $metadata */
    public function __construct(
        private Money $money,
        private string $description,
        private string $customerEmail,
        private string $successUrl,
        private string $cancelUrl,
        private string $externalRef,
        private array $metadata,
        private string $mode,
    ) {
        if (! in_array($mode, ['payment', 'subscription'], true)) {
            throw new \InvalidArgumentException('mode must be payment or subscription');
        }
    }

    public function money(): Money { return $this->money; }
    public function description(): string { return $this->description; }
    public function customerEmail(): string { return $this->customerEmail; }
    public function successUrl(): string { return $this->successUrl; }
    public function cancelUrl(): string { return $this->cancelUrl; }
    public function externalRef(): string { return $this->externalRef; }
    /** @return array<string, string> */
    public function metadata(): array { return $this->metadata; }
    public function mode(): string { return $this->mode; }
}
```

`src/Domain/Payments/ValueObjects/CheckoutSession.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

final readonly class CheckoutSession
{
    public function __construct(
        private string $providerSessionId,
        private string $redirectUrl,
    ) {}

    public function providerSessionId(): string { return $this->providerSessionId; }
    public function redirectUrl(): string { return $this->redirectUrl; }
}
```

`src/Domain/Payments/ValueObjects/PaymentEvent.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

use Lebytek\Framework\Domain\Payments\PaymentEventType;

final readonly class PaymentEvent
{
    public function __construct(
        private PaymentEventType $type,
        private string $providerEventId,
        private string $externalRef,
        private Money $money,
        private string $rawStatus,
    ) {}

    public function type(): PaymentEventType { return $this->type; }
    public function providerEventId(): string { return $this->providerEventId; }
    public function externalRef(): string { return $this->externalRef; }
    public function money(): Money { return $this->money; }
    public function rawStatus(): string { return $this->rawStatus; }
}
```

`src/Domain/Payments/PaymentGatewayInterface.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

interface PaymentGatewayInterface
{
    public function key(): string;

    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    public function parseWebhook(string $payload, string $signature): PaymentEvent;
}
```

`src/Domain/Payments/SupportsSubscriptions.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

/** Marker only in v1. Real create/cancel methods arrive in fase 2. */
interface SupportsSubscriptions {}
```

`src/Domain/Payments/PaymentEventLogRepositoryInterface.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

interface PaymentEventLogRepositoryInterface
{
    public function hasProcessed(string $provider, string $eventId): bool;

    /**
     * Atomic claim: INSERT UNIQUE(provider, event_id).
     * @return true if this caller owns the event; false if already claimed.
     * @param array<string, mixed> $meta
     */
    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool;
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Payments/PaymentValueObjectsTest
```

Expected: PASS.

- [ ] **Step 5: Commit (Grupo A)**

```bash
git add src/Domain/Payments tests/Payments/PaymentValueObjectsTest.php
git commit -m "feat(payments): add domain port and value objects"
```

---

### Task 2: PaymentGatewayRegistry and PaymentsFactory

**Files:**
- Create: `src/Application/Payments/PaymentGatewayRegistry.php`
- Create: `src/Application/Payments/PaymentsFactory.php`
- Test: `tests/Payments/PaymentGatewayRegistryTest.php`
- Test: `tests/Payments/PaymentsFactoryTest.php`

**Interfaces:**
- Consumes: `PaymentGatewayInterface`
- Produces: `PaymentGatewayRegistry::has/get/driver`, `PaymentsFactory::registry()`, `PaymentsFactory::resetCached()`, `PaymentsFactory::buildGateways()`

**Acceptance Criteria:**
- Registry memoizes gateway instances.
- Unsupported driver throws `\RuntimeException`.
- `resetCached()` clears static cache (tests do not leak config).

- [ ] **Step 1: Write failing registry test**

Create `tests/Payments/PaymentGatewayRegistryTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

function fakeGateway(string $key): PaymentGatewayInterface
{
    return new class($key) implements PaymentGatewayInterface {
        public function __construct(private string $k) {}
        public function key(): string { return $this->k; }
        public function createCheckout(CheckoutRequest $r): CheckoutSession {
            return new CheckoutSession('sess_x', 'https://pay.example/redirect');
        }
        public function parseWebhook(string $p, string $s): PaymentEvent {
            return new PaymentEvent(PaymentEventType::CheckoutCompleted, 'evt', 'ref', Money::fromMajor(1, 'mxn'), 'ok');
        }
    };
}

test('PaymentGatewayRegistry memoiza gateways', function (): void {
    $registry = new PaymentGatewayRegistry([
        'stripe' => ['driver' => 'stripe', 'factory' => fn () => fakeGateway('stripe')],
    ]);
    assert_true($registry->has('stripe'));
    assert_true($registry->get('stripe') === $registry->get('stripe'));
    assert_same('stripe', $registry->driver('stripe'));
});
```

Create `tests/Payments/PaymentsFactoryTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Payments\PaymentsFactory;

test('PaymentsFactory lanza si driver no soportado', function (): void {
    PaymentsFactory::resetCached();
    assert_throws(\RuntimeException::class, function (): void {
        PaymentsFactory::buildGateways([
            'bad' => ['driver' => 'unknown', 'enabled' => true, 'config' => []],
        ]);
    });
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php tests/run.php Payments/PaymentGatewayRegistryTest
php tests/run.php Payments/PaymentsFactoryTest
```

- [ ] **Step 3: Implement registry + factory**

`src/Application/Payments/PaymentGatewayRegistry.php` — copy `ChannelRegistry` substituting `PaymentGatewayInterface`.

`src/Application/Payments/PaymentsFactory.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Payments;

use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Infrastructure\Payments\StripeGateway;
use Lebytek\Framework\Kernel\Config\Config;

final class PaymentsFactory
{
    private static ?PaymentGatewayRegistry $cached = null;

    public static function resetCached(): void
    {
        self::$cached = null;
    }

    public static function registry(): PaymentGatewayRegistry
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $config = (array) Config::get('payments', []);
        return self::$cached = new PaymentGatewayRegistry(
            self::buildGateways((array) ($config['gateways'] ?? []))
        );
    }

    /**
     * @param array<string, array{driver?:string, enabled?:bool, config?:array}> $gatewaysConfig
     * @return array<string, array{driver:string, factory:callable():PaymentGatewayInterface}>
     */
    public static function buildGateways(array $gatewaysConfig): array
    {
        $out = [];
        foreach ($gatewaysConfig as $key => $def) {
            if (! (bool) ($def['enabled'] ?? false)) {
                continue;
            }
            $driver = (string) ($def['driver'] ?? $key);
            $cfg = (array) ($def['config'] ?? []);
            $out[$key] = [
                'driver' => $driver,
                'factory' => static function () use ($driver, $cfg): PaymentGatewayInterface {
                    return match ($driver) {
                        'stripe' => new StripeGateway($cfg),
                        default  => throw new \RuntimeException("Driver de pasarela no soportado: {$driver}"),
                    };
                },
            ];
        }
        return $out;
    }
}
```

Stub `src/Infrastructure/Payments/StripeGateway.php` (Task 4 replaces body):

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Payments;

use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

final class StripeGateway implements PaymentGatewayInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function key(): string { return 'stripe'; }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        throw new \RuntimeException('StripeGateway not implemented');
    }

    public function parseWebhook(string $payload, string $signature): PaymentEvent
    {
        throw new \RuntimeException('StripeGateway not implemented');
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
php tests/run.php Payments/PaymentGatewayRegistryTest
php tests/run.php Payments/PaymentsFactoryTest
```

- [ ] **Step 5: Commit (Grupo A)**

```bash
git add src/Application/Payments src/Infrastructure/Payments/StripeGateway.php tests/Payments/PaymentGatewayRegistryTest.php tests/Payments/PaymentsFactoryTest.php
git commit -m "feat(payments): add registry and factory"
```

---

### Task 3: Platform SQL module and event log repository (atomic claim)

**Files:**
- Create: `database/schema/modules/payments.sql`
- Create: `config/modules/payments.php`
- Create: `src/Infrastructure/Payments/PdoPaymentEventLogRepository.php`
- Test: `tests/Payments/PaymentsSchemaTest.php`
- Test: `tests/Payments/PdoPaymentEventLogRepositoryTest.php` (in-memory PDO sqlite OR fake that asserts SQL — prefer behavioral with sqlite if harness allows; else unit double capturing INSERT/exception)

**Interfaces:**
- Consumes: `PaymentEventLogRepositoryInterface`
- Produces: table `pay_events`, `PdoPaymentEventLogRepository::tryClaim`

**Acceptance Criteria:**
- SQL creates `pay_events` with `UNIQUE KEY uq_pay_events_provider_event (provider, event_id)`.
- First `tryClaim` → `true`; second identical claim → `false` (no throw to caller).
- No `markProcessed` write API (removed — claim is the only write).

- [ ] **Step 1: Write failing schema + claim tests**

```php
<?php
declare(strict_types=1);

test('payments bootstrap SQL crea pay_events idempotente', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/payments.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `pay_events`'));
    assert_true(str_contains($sql, 'UNIQUE KEY `uq_pay_events_provider_event`'));
    assert_true(! str_contains($sql, 'fw_payment_events'));
});

test('PdoPaymentEventLogRepository implementa tryClaim', function (): void {
    $ref = new ReflectionClass(\Lebytek\Framework\Infrastructure\Payments\PdoPaymentEventLogRepository::class);
    assert_true($ref->implementsInterface(\Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface::class));
    assert_true($ref->hasMethod('tryClaim'));
    assert_true(! $ref->hasMethod('markProcessed'));
});
```

Add `tests/Payments/PaymentEventLogClaimDoubleTest.php` proving the **contract** callers rely on (double simulating UNIQUE race):

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;

final class InMemoryPaymentEventLog implements PaymentEventLogRepositoryInterface
{
    /** @var array<string, true> */
    private array $claimed = [];

    public function hasProcessed(string $provider, string $eventId): bool
    {
        return isset($this->claimed[$provider."\0".$eventId]);
    }

    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool {
        $key = $provider."\0".$eventId;
        if (isset($this->claimed[$key])) {
            return false;
        }
        $this->claimed[$key] = true;
        return true;
    }
}

test('tryClaim es atómico para el mismo event_id', function (): void {
    $log = new InMemoryPaymentEventLog();
    assert_true($log->tryClaim('stripe', 'evt_1', 'ord', 'checkout.completed', 'hash'));
    assert_true(! $log->tryClaim('stripe', 'evt_1', 'ord', 'checkout.completed', 'hash'));
    assert_true($log->hasProcessed('stripe', 'evt_1'));
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Payments/PaymentsSchemaTest
php tests/run.php Payments/PaymentEventLogClaimDoubleTest
```

- [ ] **Step 3: Create SQL + manifest + repository**

`database/schema/modules/payments.sql`:

```sql
-- database/schema/modules/payments.sql
-- Bootstrap del módulo Pagos (plataforma). Idempotente.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `pay_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`      VARCHAR(40)     NOT NULL,
  `event_id`      VARCHAR(190)    NOT NULL,
  `order_ref`     VARCHAR(64)     DEFAULT NULL,
  `type`          VARCHAR(60)     NOT NULL,
  `payload_hash`  CHAR(64)        NOT NULL,
  `meta`          JSON            DEFAULT NULL,
  `processed_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_events_provider_event` (`provider`, `event_id`),
  KEY `idx_pay_events_order_ref` (`order_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
```

`config/modules/payments.php`:

```php
<?php
declare(strict_types=1);

return [
    'clave'         => 'payments',
    'nombre'        => 'Pagos',
    'descripcion'   => 'Puerto de pasarelas de pago (Stripe v1).',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/payments.sql',
    'cruds'         => [],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
```

`src/Infrastructure/Payments/PdoPaymentEventLogRepository.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Payments;

use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;
use PDOException;

final class PdoPaymentEventLogRepository implements PaymentEventLogRepositoryInterface
{
    public function hasProcessed(string $provider, string $eventId): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM pay_events WHERE provider = :p AND event_id = :e LIMIT 1'
        );
        $stmt->execute(['p' => $provider, 'e' => $eventId]);
        return (bool) $stmt->fetchColumn();
    }

    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool {
        $pdo = Connection::getInstance();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pay_events (provider, event_id, order_ref, type, payload_hash, meta)
                 VALUES (:provider, :event_id, :order_ref, :type, :payload_hash, :meta)'
            );
            $stmt->execute([
                'provider' => $provider,
                'event_id' => $eventId,
                'order_ref' => $orderRef !== '' ? $orderRef : null,
                'type' => $type,
                'payload_hash' => $payloadHash,
                'meta' => $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR),
            ]);
            return true;
        } catch (PDOException $e) {
            // SQLSTATE 23000 = integrity constraint violation (UNIQUE)
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return false;
            }
            throw $e;
        }
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Payments/PaymentsSchemaTest
php tests/run.php Payments/PaymentEventLogClaimDoubleTest
```

- [ ] **Step 5: Commit (Grupo A)**

```bash
git add database/schema/modules/payments.sql config/modules/payments.php src/Infrastructure/Payments/PdoPaymentEventLogRepository.php tests/Payments/PaymentsSchemaTest.php tests/Payments/PaymentEventLogClaimDoubleTest.php
git commit -m "feat(payments): add platform event ledger with atomic tryClaim"
```

---

### Task 4: StripeGateway + Composer dependency

**Files:**
- Modify: `composer.json`
- Modify: `src/Infrastructure/Payments/StripeGateway.php`
- Test: `tests/Payments/StripeGatewayTest.php`
- Create: `tests/Payments/fixtures/stripe_checkout_completed.json`
- Create: `tests/Payments/fixtures/stripe_unmapped_event.json`

**Interfaces:**
- Consumes: `CheckoutRequest`, Stripe SDK
- Produces: `StripeGateway::createCheckout()` (with Stripe `idempotency_key` = `externalRef`), `parseWebhook()` → mapped types or `Ignored` (never throw on unmapped type)

**Acceptance Criteria:**
- Invalid signature → `\UnexpectedValueException`.
- Valid `checkout.session.completed` fixture → `CheckoutCompleted` + non-empty `externalRef` from `metadata.order_public_id`.
- Unmapped event type → `PaymentEventType::Ignored` (no exception).
- `createCheckout` passes `'idempotency_key' => $request->externalRef()` to `Session::create` (assert via partial mock or source contract + documented call).

- [ ] **Step 1: Add dependency**

In `composer.json` require block:

```json
"stripe/stripe-php": "^16.0"
```

```bash
composer update stripe/stripe-php --no-interaction
```

- [ ] **Step 2: Write failing webhook tests**

Create fixtures under `tests/Payments/fixtures/` (minimal Stripe Event JSON). `stripe_checkout_completed.json` must include `id`, `type: checkout.session.completed`, and `data.object` with `metadata.order_public_id`, `amount_total: 219900`, `currency: mxn`, `payment_status: paid`.

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Infrastructure\Payments\StripeGateway;

test('StripeGateway rechaza firma inválida', function (): void {
    $gw = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => 'whsec_test_secret',
        'currency' => 'mxn',
    ]);
    assert_throws(\UnexpectedValueException::class, fn () => $gw->parseWebhook('{}', 'bad_sig'));
});

test('StripeGateway parseWebhook acepta firma válida de fixture', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_checkout_completed.json');
    $sig = \Stripe\Webhook::generateTestHeaderString([
        'payload' => $payload,
        'secret' => $secret,
    ]);
    $gw = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gw->parseWebhook($payload, $sig);
    assert_same(PaymentEventType::CheckoutCompleted, $event->type());
    assert_true($event->externalRef() !== '');
    assert_same(219900, $event->money()->amountMinor());
    assert_same('mxn', $event->money()->currency());
});

test('StripeGateway evento no mapeado devuelve Ignored', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_unmapped_event.json');
    $sig = \Stripe\Webhook::generateTestHeaderString([
        'payload' => $payload,
        'secret' => $secret,
    ]);
    $gw = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gw->parseWebhook($payload, $sig);
    assert_same(PaymentEventType::Ignored, $event->type());
});

test('StripeGateway createCheckout documenta idempotency_key = externalRef', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/src/Infrastructure/Payments/StripeGateway.php');
    assert_true(str_contains($src, 'idempotency_key'));
    assert_true(str_contains($src, 'externalRef()'));
});
```

- [ ] **Step 3: Run test — expect FAIL**

```bash
php tests/run.php Payments/StripeGatewayTest
```

- [ ] **Step 4: Implement StripeGateway**

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Payments;

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

final class StripeGateway implements PaymentGatewayInterface
{
    /** @param array{secret_key?:string, webhook_secret?:string, currency?:string} $config */
    public function __construct(private readonly array $config)
    {
        Stripe::setApiKey((string) ($config['secret_key'] ?? ''));
    }

    public function key(): string { return 'stripe'; }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $money = $request->money();
        $session = Session::create(
            [
                'mode' => $request->mode(),
                'customer_email' => $request->customerEmail(),
                'success_url' => $request->successUrl(),
                'cancel_url' => $request->cancelUrl(),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $money->currency(),
                        'unit_amount' => $money->amountMinor(),
                        'product_data' => ['name' => $request->description()],
                    ],
                ]],
                'metadata' => array_merge($request->metadata(), [
                    'order_public_id' => $request->externalRef(),
                ]),
            ],
            ['idempotency_key' => $request->externalRef()],
        );

        return new CheckoutSession(
            (string) $session->id,
            (string) $session->url,
        );
    }

    public function parseWebhook(string $payload, string $signature): PaymentEvent
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            (string) ($this->config['webhook_secret'] ?? ''),
        );

        $type = match ($event->type) {
            'checkout.session.completed' => PaymentEventType::CheckoutCompleted,
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed' => PaymentEventType::PaymentFailed,
            default => PaymentEventType::Ignored,
        };

        $object = $event->data->object;
        $meta = $object->metadata ?? null;
        $orderRef = '';
        if (is_array($meta)) {
            $orderRef = (string) ($meta['order_public_id'] ?? '');
        } elseif (is_object($meta)) {
            $orderRef = (string) ($meta->order_public_id ?? '');
        }

        if ($type === PaymentEventType::Ignored) {
            return new PaymentEvent(
                type: $type,
                providerEventId: (string) $event->id,
                externalRef: $orderRef,
                money: new Money(0, 'mxn'),
                rawStatus: (string) $event->type,
            );
        }

        $currency = strtolower((string) ($object->currency ?? ($this->config['currency'] ?? 'mxn')));
        $amount = (int) ($object->amount_total ?? $object->amount ?? 0);
        // Non-mxn → amount 0 so ConfirmarPago money.equals(snapshot) fails closed without throwing here.
        if ($currency !== 'mxn') {
            $amount = 0;
        }

        return new PaymentEvent(
            type: $type,
            providerEventId: (string) $event->id,
            externalRef: $orderRef,
            money: new Money($amount, 'mxn'),
            rawStatus: (string) ($object->payment_status ?? $object->status ?? $event->type),
        );
    }
}
```

- [ ] **Step 5: Run test — expect PASS**

```bash
php tests/run.php Payments/StripeGatewayTest
php tests/run.php Payments
```

- [ ] **Step 6: Commit (Grupo A)**

```bash
git add composer.json composer.lock src/Infrastructure/Payments/StripeGateway.php tests/Payments
git commit -m "feat(payments): implement StripeGateway with ignored events and session idempotency"
```

---

### Task 5: Consumer config, env, and DI bindings

**Files:**
- Create: `config/payments.php`
- Create: `skeleton/config/payments.php`
- Modify: `config/vertical.php` — `'payments' => false`
- Modify: `skeleton/config/vertical.php` — `'payments' => false`
- Modify: `config/container.php` — gated payments block
- Modify: `.env.example`

**Interfaces:**
- Consumes: `PaymentsFactory::registry()`, `PdoPaymentEventLogRepository`
- Produces: container resolves `PaymentGatewayRegistry` and `PaymentEventLogRepositoryInterface` only when vertical ON

**Acceptance Criteria:**
- `config/payments.php` defines stripe gateway defaults.
- Skeleton `vertical.modules.payments === false`.
- Container register block is wrapped in `if (Config::get('vertical.modules.payments'))`.

- [ ] **Step 1: Write failing config test**

```php
<?php
declare(strict_types=1);

test('config payments define gateway stripe', function (): void {
    $cfg = require ROOT_PATH . '/config/payments.php';
    assert_true(isset($cfg['gateways']['stripe']));
    assert_same('stripe', $cfg['gateways']['stripe']['driver']);
});

test('vertical skeleton deja payments OFF', function (): void {
    $vertical = require ROOT_PATH . '/config/vertical.php';
    assert_true(($vertical['modules']['payments'] ?? true) === false);
    $skel = require ROOT_PATH . '/skeleton/config/vertical.php';
    assert_true(($skel['modules']['payments'] ?? true) === false);
});

test('container gatea payments por vertical flag', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/config/container.php');
    assert_true(str_contains($src, "vertical.modules.payments"));
    assert_true(str_contains($src, 'PaymentGatewayRegistry'));
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Payments/PaymentsConfigTest
```

- [ ] **Step 3: Add config files**

`config/payments.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Payments\StripeGateway;
use Lebytek\Framework\Kernel\EnvLoader;

return [
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
];
```

Add to `config/vertical.php` / skeleton modules:

```php
'payments' => false,
```

Append to `.env.example`:

```env
# Pagos — plataforma (Stripe Checkout)
STRIPE_ENABLED=false
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
PAYMENTS_CURRENCY=mxn
PAYMENTS_DEFAULT_GATEWAY=stripe
```

Add to `config/container.php`:

```php
if ((bool) Config::get('vertical.modules.payments', false)) {
    $container->singleton(
        \Lebytek\Framework\Application\Payments\PaymentGatewayRegistry::class,
        static fn () => \Lebytek\Framework\Application\Payments\PaymentsFactory::registry()
    );
    $container->singleton(
        \Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface::class,
        static fn () => new \Lebytek\Framework\Infrastructure\Payments\PdoPaymentEventLogRepository()
    );
}
```

Copy payments config to skeleton.

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Payments/PaymentsConfigTest
```

- [ ] **Step 5: Commit (Grupo C)**

```bash
git add config/payments.php config/vertical.php config/container.php skeleton/config/payments.php skeleton/config/vertical.php .env.example tests/Payments/PaymentsConfigTest.php
git commit -m "feat(payments): add consumer config and gated DI bindings"
```

---

### Task 6: Marketing order schema migration (Stripe columns)

**Files:**
- Create: `database/migrations/20260715120000_mkt_ordenes_stripe.sql`
- Modify: `config/modules/marketing.php`
- Test: `tests/Marketing/MembershipOrdersStripeMigrationTest.php`

**Interfaces:**
- Consumes: existing `dom_mkt_ordenes`
- Produces: columns `metodo_pago`, `payment_provider`, `payment_ref`; status value `pending_payment` allowed in app (no DB ENUM constraint today)

**Acceptance Criteria:**
- Migration SQL mentions the three columns.
- Marketing manifest lists the migration file.
- Ops note in SQL comment: verify VPS MySQL/MariaDB supports `ADD COLUMN IF NOT EXISTS`; if not, replace with installer guard / manual check.

- [ ] **Step 1: Write failing migration test**

```php
<?php
declare(strict_types=1);

test('migración stripe añade columnas de pago a dom_mkt_ordenes', function (): void {
    $sql = (string) file_get_contents(
        ROOT_PATH . '/database/migrations/20260715120000_mkt_ordenes_stripe.sql'
    );
    assert_true(str_contains($sql, 'metodo_pago'));
    assert_true(str_contains($sql, 'payment_provider'));
    assert_true(str_contains($sql, 'payment_ref'));
});

test('marketing module lista la migración stripe', function (): void {
    $manifest = require ROOT_PATH . '/config/modules/marketing.php';
    assert_true(in_array('20260715120000_mkt_ordenes_stripe.sql', $manifest['migraciones'], true));
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Marketing/MembershipOrdersStripeMigrationTest
```

- [ ] **Step 3: Create migration**

```sql
-- Stripe / pasarela: columnas de pago en órdenes de membresía.
-- Ops: MariaDB 10.3.3+ / MySQL 8.0.12+ support ADD COLUMN IF NOT EXISTS.
-- If VPS dialect rejects it, run each ADD once guarded by information_schema.

ALTER TABLE `dom_mkt_ordenes`
  ADD COLUMN IF NOT EXISTS `metodo_pago` VARCHAR(20) DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `payment_provider` VARCHAR(40) DEFAULT NULL AFTER `metodo_pago`,
  ADD COLUMN IF NOT EXISTS `payment_ref` VARCHAR(190) DEFAULT NULL AFTER `payment_provider`;

CREATE INDEX IF NOT EXISTS `idx_mkt_ordenes_payment_ref` ON `dom_mkt_ordenes` (`payment_ref`);
```

Register in `config/modules/marketing.php` `migraciones[]`.

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Marketing/MembershipOrdersStripeMigrationTest
```

- [ ] **Step 5: Commit (Grupo B)**

```bash
git add database/migrations/20260715120000_mkt_ordenes_stripe.sql config/modules/marketing.php tests/Marketing/MembershipOrdersStripeMigrationTest.php
git commit -m "feat(mkt): add payment columns to membership orders"
```

---

### Task 7: Extend MembershipOrderRepository

**Files:**
- Modify: `app/Domain/Marketing/Contracts/MembershipOrderRepositoryInterface.php`
- Modify: `app/Infrastructure/Marketing/PdoMembershipOrderRepository.php`
- Modify: in-memory fakes in existing Marketing tests that implement the interface
- Test: `tests/Marketing/MembershipOrderRepositoryStripeTest.php`

**Interfaces:**
- Produces: `markPaymentPending()`, `savePaymentRef()`, `findByPaymentRef()`

**Acceptance Criteria:**
- Interface declares the three methods.
- Existing Marketing tests still pass after fakes gain stub methods.

- [ ] **Step 1: Write failing contract test**

```php
<?php
declare(strict_types=1);

test('MembershipOrderRepositoryInterface expone métodos stripe', function (): void {
    $ref = new ReflectionClass(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class);
    assert_true($ref->hasMethod('markPaymentPending'));
    assert_true($ref->hasMethod('savePaymentRef'));
    assert_true($ref->hasMethod('findByPaymentRef'));
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Marketing/MembershipOrderRepositoryStripeTest
```

- [ ] **Step 3: Extend interface + PDO repo + update fakes**

Add to interface:

```php
/** @param array{metodo_pago:string,payment_provider:?string,payment_ref:?string,status:string} $patch */
public function markPaymentPending(int $orderId, array $patch): void;

public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void;

/** @return array<string, mixed>|null */
public function findByPaymentRef(string $provider, string $paymentRef): ?array;
```

Implement in `PdoMembershipOrderRepository` (same SQL as prior plan draft). Update `MemOrderInMemoryRepo` / `AuthorizeMemOrderRepo` and any other interface implementers with empty/stub methods so the suite compiles.

- [ ] **Step 4: Run tests — expect PASS**

```bash
php tests/run.php Marketing/MembershipOrderRepositoryStripeTest
php tests/run.php Marketing/CrearOrdenMembresiaUseCaseTest
php tests/run.php Marketing/AutorizarOrdenMembresiaUseCaseTest
```

- [ ] **Step 5: Commit (Grupo B)**

```bash
git add app/Domain/Marketing/Contracts/MembershipOrderRepositoryInterface.php app/Infrastructure/Marketing/PdoMembershipOrderRepository.php tests/Marketing
git commit -m "feat(mkt): extend order repository for stripe payment refs"
```

---

### Task 8: ActivateMembershipFromOrderService + stable Idempotency-Key on client

**Files:**
- Create: `app/Application/Marketing/MembershipOrderActors.php`
- Create: `app/Application/Marketing/ActivateMembershipFromOrderService.php`
- Modify: `app/Application/Marketing/AutorizarOrdenMembresiaUseCase.php` — delegate `ejecutar()` to service `fromManualAuthorize`
- Modify: `app/Infrastructure/Integrations/LebytekApi/LebytekApiClient.php` — optional `$idempotencyKey`
- Modify: `tests/Integration/LebytekApiClientTest.php` — assert stable key when provided
- Modify: `tests/Marketing/AutorizarOrdenMembresiaUseCaseTest.php` — still PASS
- Test: `tests/Marketing/ActivateMembershipFromOrderServiceTest.php`

**Interfaces:**
- Consumes: `LebytekApiClient::activatePlan(string $tenantPublicId, array $payload, ?string $idempotencyKey = null)`
- Produces:
  - `ActivateMembershipFromOrderService::fromManualAuthorize(array $order, int $actorId): array` — **activate then markPaid**
  - `ActivateMembershipFromOrderService::fromConfirmedPayment(array $order, int $actorId, string $idempotencyKey): array` — **markPaid then activate**; api failure → `setApiActivationError`, does **not** un-pay
  - `MembershipOrderActors::SYSTEM_WEBHOOK = 0`
  - Helper `ActivateMembershipFromOrderService::stableActivateIdempotencyKey(string $orderPublicId): string` → deterministic UUID

**Acceptance Criteria:**
- Manual path: API fail → order not paid (existing behavior).
- Confirmed-payment path: markPaid runs even if activate throws; stable Idempotency-Key header used.
- `AutorizarOrdenMembresiaUseCaseTest` still PASS.
- Client with explicit key does not mint a random UUID for that request.

- [ ] **Step 1: Write failing tests**

Extend `tests/Integration/LebytekApiClientTest.php` (reuses existing `RecordingTransport`):

```php
test('LebytekApiClient activatePlan usa Idempotency-Key estable si se pasa', function (): void {
    $transport = new RecordingTransport();
    $transport->responses[] = ['status' => 201, 'body' => '{"token":"t"}', 'error' => ''];
    $client = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $client->activatePlan('01JT', ['planSlug' => 'starter'], '11111111-2222-4333-8444-555555555555');
    $headers = implode("\n", $transport->calls[0]['headers']);
    assert_true(str_contains($headers, 'Idempotency-Key: 11111111-2222-4333-8444-555555555555'));
});
```

Create `tests/Marketing/ActivateMembershipFromOrderServiceTest.php` (full doubles — microtest does not share classes across files):

```php
<?php
declare(strict_types=1);

use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Application\Marketing\MembershipOrderActors;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class ActRecTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];
    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses = [];
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{}', 'error' => ''];
    }
}

final class ActMemOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    public bool $paid = false;
    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByPublicId(string $publicId): ?array { return null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void
    {
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['api_activation_error'] = $error;
        }
    }
    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->paid = true;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['status'] = 'paid';
            $this->rows[$orderId]['authorized_by'] = $authorizedBy;
        }
    }
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class ActSpyMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void {}
}

test('stableActivateIdempotencyKey es UUID determinista', function (): void {
    $a = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORD');
    $b = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORD');
    assert_same($a, $b);
    assert_true((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a));
});

test('fromConfirmedPayment marca paid aunque activate falle', function (): void {
    $transport = new ActRecTransport();
    $transport->responses[] = ['status' => 500, 'body' => '{"message":"down"}', 'error' => ''];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $orders = new ActMemOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORD00000000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_payment',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];
    $svc = new ActivateMembershipFromOrderService($orders, $api, new ActSpyMailer());
    $key = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORD00000000000000000001');
    try {
        $svc->fromConfirmedPayment($orders->rows[1], MembershipOrderActors::SYSTEM_WEBHOOK, $key);
        assert_true(false, 'expected LebytekApiException');
    } catch (\Throwable $e) {
        assert_true($orders->paid);
        assert_true(str_contains(implode("\n", $transport->calls[0]['headers']), 'Idempotency-Key: '.$key));
        assert_true(isset($orders->rows[1]['api_activation_error']));
    }
});

test('fromManualAuthorize no marca paid si api falla', function (): void {
    $transport = new ActRecTransport();
    $transport->responses[] = ['status' => 500, 'body' => '{"message":"down"}', 'error' => ''];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $orders = new ActMemOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORD00000000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];
    $svc = new ActivateMembershipFromOrderService($orders, $api, new ActSpyMailer());
    try {
        $svc->fromManualAuthorize($orders->rows[1], 7);
        assert_true(false, 'expected exception');
    } catch (\Throwable) {
        assert_true(! $orders->paid);
    }
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php tests/run.php Marketing/ActivateMembershipFromOrderServiceTest
php tests/run.php Integration/LebytekApiClientTest
```

- [ ] **Step 3: Implement**

`MembershipOrderActors.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Marketing;

final class MembershipOrderActors
{
    /** Sentinel for Stripe webhook / system automations (authorized_by nullable BIGINT). */
    public const SYSTEM_WEBHOOK = 0;
}
```

`LebytekApiClient` — change `activatePlan` and `request`:

```php
public function activatePlan(string $tenantPublicId, array $payload, ?string $idempotencyKey = null): array
{
    return $this->request('POST', '/tenants/'.$tenantPublicId.'/activate-plan', $payload, [], $idempotencyKey);
}

private function request(
    string $method,
    string $path,
    ?array $body = null,
    array $headers = [],
    ?string $idempotencyKey = null,
): array {
    // ... existing header build ...
    if ($write) {
        $baseHeaders[] = 'Idempotency-Key: '.($idempotencyKey ?? $this->newUuid());
    }
    // ... rest unchanged ...
}
```

`ActivateMembershipFromOrderService.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

final class ActivateMembershipFromOrderService
{
    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly LebytekApiClient $api,
        private readonly MailerInterface $mailer,
    ) {}

    /** Deterministic UUIDv5-shaped key from order public_id (activate-plan once per order). */
    public static function stableActivateIdempotencyKey(string $orderPublicId): string
    {
        $hex = hash('sha1', 'activate-plan|'.$orderPublicId);
        $hex[12] = '5'; // version nibble
        $int = hexdec($hex[16]);
        $hex[16] = dechex(($int & 0x3) | 0x8);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /**
     * Admin transfer path: activate FIRST, then markPaid.
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function fromManualAuthorize(array $order, int $actorId): array
    {
        return $this->run($order, $actorId, markPaidFirst: false, idempotencyKey: null);
    }

    /**
     * Stripe path: markPaid FIRST (money captured), then activate with stable key.
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function fromConfirmedPayment(array $order, int $actorId, string $idempotencyKey): array
    {
        return $this->run($order, $actorId, markPaidFirst: true, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function run(array $order, int $actorId, bool $markPaidFirst, ?string $idempotencyKey): array
    {
        $orderId = (int) ($order['id'] ?? 0);
        $tenantPublicId = trim((string) ($order['api_tenant_public_id'] ?? ''));
        if ($tenantPublicId === '') {
            throw new \InvalidArgumentException('Asocia el tenant demo en la orden antes de autorizar.');
        }

        $slug = (string) ($order['paquete_slug'] ?? '');
        if (! in_array($slug, ['starter', 'business', 'empresa'], true)) {
            throw new \InvalidArgumentException(
                'El paquete de la orden no es autorizable vía activate-plan (use starter, business o empresa).'
            );
        }

        $payload = [
            'planSlug' => $slug,
            'billingCycle' => (string) ($order['ciclo'] ?? 'monthly'),
            'orderExternalRef' => (string) ($order['public_id'] ?? ''),
            'tokenName' => 'membresia-'.($slug !== '' ? $slug : 'plan'),
        ];
        if ($slug === 'empresa' && isset($order['mensajes_mes_limite_snapshot']) && $order['mensajes_mes_limite_snapshot'] !== null) {
            $payload['messagesMonthlyLimit'] = (int) $order['mensajes_mes_limite_snapshot'];
        }

        if ($markPaidFirst) {
            $this->orders->markPaid($orderId, $actorId);
        }

        try {
            $response = $this->api->activatePlan($tenantPublicId, $payload, $idempotencyKey);
        } catch (LebytekApiException $e) {
            $this->orders->setApiActivationError($orderId, $e->getMessage());
            throw $e;
        }

        $plainToken = trim((string) ($response['token'] ?? ''));

        if (! $markPaidFirst) {
            $this->orders->markPaid($orderId, $actorId);
        }

        if ($plainToken !== '') {
            try {
                $this->sendMembershipEmail($order, $plainToken);
            } catch (\Throwable $mailError) {
                $this->orders->setApiActivationError($orderId, 'Correo: '.$mailError->getMessage());
            }
        }

        return $this->orders->findById($orderId) ?? $order;
    }

    /** @param array<string, mixed> $order */
    private function sendMembershipEmail(array $order, string $token): void
    {
        $apiBaseUrl = rtrim((string) EnvLoader::get('LEBYTEK_API_URL', 'https://api.lebytek.com/api/v1'), '/');
        $cicloLabel = ($order['ciclo'] ?? '') === 'annual' ? 'Anual' : 'Mensual';
        $planLabel = ucfirst((string) ($order['paquete_slug'] ?? ''));
        $html = ViewHelper::render('emails/membership_activated', [
            'nombre' => (string) ($order['nombre'] ?? ''),
            'planNombre' => $planLabel,
            'ciclo' => $cicloLabel,
            'cuota' => number_format((float) ($order['precio_snapshot'] ?? 0), 2, '.', ','),
            'apiBaseUrl' => $apiBaseUrl,
            'token' => $token,
        ], '');
        $this->mailer->enviar(new MensajeCorreo(
            (string) ($order['email'] ?? ''),
            (string) ($order['nombre'] ?? ''),
            'Tu membresía Lebytek está activa',
            $html,
        ));
    }
}
```

Refactor `AutorizarOrdenMembresiaUseCase::ejecutar` to validate status/`findById`, then `$this->activator->fromManualAuthorize($order, $authorizedBy)`. Inject `ActivateMembershipFromOrderService` (or keep constructing internals — prefer inject service and thin use case).

Wire DI in `config/container.php` for the new service; update `AutorizarOrdenMembresiaUseCase` binding.

- [ ] **Step 4: Run tests — expect PASS**

```bash
php tests/run.php Marketing/ActivateMembershipFromOrderServiceTest
php tests/run.php Marketing/AutorizarOrdenMembresiaUseCaseTest
php tests/run.php Integration/LebytekApiClientTest
```

- [ ] **Step 5: Commit (Grupo B)**

```bash
git add app/Application/Marketing/MembershipOrderActors.php app/Application/Marketing/ActivateMembershipFromOrderService.php app/Application/Marketing/AutorizarOrdenMembresiaUseCase.php app/Infrastructure/Integrations/LebytekApi/LebytekApiClient.php config/container.php tests/Marketing/ActivateMembershipFromOrderServiceTest.php tests/Integration/LebytekApiClientTest.php tests/Marketing/AutorizarOrdenMembresiaUseCaseTest.php
git commit -m "feat(mkt): dedicated activation service with stable api idempotency key"
```

---

### Task 9: CrearOrdenMembresiaUseCase payment method branch

**Files:**
- Modify: `app/Application/Marketing/CrearOrdenMembresiaUseCase.php`
- Modify: `tests/Marketing/CrearOrdenMembresiaUseCaseTest.php` — extend `MemOrderInMemoryRepo` if needed

**Interfaces:**
- Consumes: `$input['metodo_pago']` = `'transfer'|'stripe'`
- Produces: stripe → `status: pending_payment`, `metodo_pago: stripe`, notifier **not** called; transfer → unchanged

**Acceptance Criteria:**
- New test: stripe branch → `pending_payment`, `alert_sent === false`, `notifier->calls` empty, `metodo_pago === 'stripe'`.
- Existing transfer tests still PASS.

- [ ] **Step 1: Add failing test (complete body)**

Append to `tests/Marketing/CrearOrdenMembresiaUseCaseTest.php`:

```php
test('CrearOrden con metodo stripe no envía alerta transferencia', function (): void {
    $orders = new MemOrderInMemoryRepo();
    $content = new MemContentInMemoryRepo([
        'id' => 2, 'slug' => 'starter', 'nombre' => 'Starter',
        'precio_mensual' => '2199', 'precio_anual' => '21990', 'mensajes_mes_limite' => 5000,
    ]);
    $leads = new MemLeadInMemoryRepo([
        'id' => 5, 'email' => 'buyer@test.com', 'api_tenant_public_id' => '01JTENANT0000000000000001',
    ]);
    $notifier = new SpyPurchaseNotifier(true);
    $uc = new CrearOrdenMembresiaUseCase($content, $orders, $leads, $notifier);

    $result = $uc->ejecutar('starter', [
        'nombre' => 'Buyer Test',
        'email' => 'buyer@test.com',
        'telefono' => '5512345678',
        'empresa' => 'ACME',
        'direccion' => 'Calle 1',
        'ciclo' => 'monthly',
        'metodo_pago' => 'stripe',
    ]);

    assert_same('pending_payment', $result['order']['status']);
    assert_same('stripe', $result['order']['metodo_pago'] ?? null);
    assert_true($result['alert_sent'] === false);
    assert_same(0, count($notifier->calls));
    assert_true(($result['order']['transfer_notified_at'] ?? null) === null);
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Marketing/CrearOrdenMembresiaUseCaseTest
```

- [ ] **Step 3: Implement branch**

In `ejecutar()`:

```php
$metodoPago = ($input['metodo_pago'] ?? 'transfer') === 'stripe' ? 'stripe' : 'transfer';
$status = $metodoPago === 'stripe' ? 'pending_payment' : 'pending_transfer';
```

Pass `metodo_pago` + `status` into `create()` data. Wrap WhatsApp notifier:

```php
if ($metodoPago === 'transfer') {
    // existing alert logic
} else {
    $alertSent = false;
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Marketing/CrearOrdenMembresiaUseCaseTest
```

- [ ] **Step 5: Commit (Grupo B)**

```bash
git add app/Application/Marketing/CrearOrdenMembresiaUseCase.php tests/Marketing/CrearOrdenMembresiaUseCaseTest.php
git commit -m "feat(mkt): branch order creation by payment method"
```

---

### Task 10: IniciarPagoStripeUseCase

**Files:**
- Create: `app/Application/Marketing/IniciarPagoStripeUseCase.php`
- Test: `tests/Marketing/IniciarPagoStripeUseCaseTest.php`

**Interfaces:**
- Consumes: `PaymentGatewayRegistry`, `MembershipOrderRepositoryInterface`, `EnvLoader::get('APP_URL')`
- Produces: `ejecutar(int $orderId): string` redirect URL; persists `payment_ref`

**Acceptance Criteria:**
- Builds `CheckoutRequest` with `externalRef` = order `public_id`, money from `precio_snapshot`, mode `payment`.
- Saves `payment_ref` = session id.
- Rejects non-`pending_payment` / non-stripe orders.

- [ ] **Step 1: Write failing test (complete)**

```php
<?php
declare(strict_types=1);

use App\Application\Marketing\IniciarPagoStripeUseCase;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

final class IniciarMemOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    public ?string $savedRef = null;
    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByPublicId(string $publicId): ?array { return null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void {}
    public function markPaid(int $orderId, int $authorizedBy): void {}
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void
    {
        $this->savedRef = $paymentRef;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['payment_provider'] = $provider;
            $this->rows[$orderId]['payment_ref'] = $paymentRef;
        }
    }
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

test('IniciarPagoStripe arma CheckoutRequest con order public_id', function (): void {
    /** @var CheckoutRequest|null $seen */
    $seen = null;
    $gw = new class($seen) implements PaymentGatewayInterface {
        public function __construct(private mixed &$seen) {}
        public function key(): string { return 'stripe'; }
        public function createCheckout(CheckoutRequest $r): CheckoutSession
        {
            $this->seen = $r;
            return new CheckoutSession('cs_test_123', 'https://checkout.stripe.test/pay');
        }
        public function parseWebhook(string $p, string $s): PaymentEvent
        {
            return new PaymentEvent(PaymentEventType::Ignored, 'x', '', Money::fromMajor(0, 'mxn'), 'x');
        }
    };
    $registry = new PaymentGatewayRegistry([
        'stripe' => ['driver' => 'stripe', 'factory' => fn () => $gw],
    ]);
    $orders = new IniciarMemOrders();
    $orders->rows[9] = [
        'id' => 9,
        'public_id' => '01JORDSTRIPE0000000000001',
        'status' => 'pending_payment',
        'metodo_pago' => 'stripe',
        'precio_snapshot' => 2199,
        'paquete_slug' => 'starter',
        'email' => 'buyer@test.com',
    ];
    putenv('APP_URL=https://lebytek.test');
    putenv('PAYMENTS_CURRENCY=mxn');
    $uc = new IniciarPagoStripeUseCase($orders, $registry);
    $url = $uc->ejecutar(9);
    assert_same('https://checkout.stripe.test/pay', $url);
    assert_same('cs_test_123', $orders->savedRef);
    assert_true($seen instanceof CheckoutRequest);
    assert_same('01JORDSTRIPE0000000000001', $seen->externalRef());
    assert_same(219900, $seen->money()->amountMinor());
    assert_same('payment', $seen->mode());
    assert_same('01JORDSTRIPE0000000000001', $seen->metadata()['order_public_id']);
});
```

Note: if `putenv` does not affect `EnvLoader` (cached), set expectations without depending on absolute URLs, or bootstrap EnvLoader like other tests. Assert at least money/externalRef/mode/metadata.

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Marketing/IniciarPagoStripeUseCaseTest
```

- [ ] **Step 3: Implement use case**

```php
<?php
declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Kernel\EnvLoader;

final class IniciarPagoStripeUseCase
{
    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function ejecutar(int $orderId): string
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new \InvalidArgumentException('Orden no encontrada.');
        }
        if (($order['status'] ?? '') !== 'pending_payment') {
            throw new \InvalidArgumentException('La orden no está pendiente de pago con tarjeta.');
        }
        if (($order['metodo_pago'] ?? '') !== 'stripe') {
            throw new \InvalidArgumentException('La orden no usa Stripe.');
        }

        $publicId = (string) ($order['public_id'] ?? '');
        $base = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $gateway = $this->gateways->get('stripe');
        $currency = (string) EnvLoader::get('PAYMENTS_CURRENCY', 'mxn');

        $session = $gateway->createCheckout(new CheckoutRequest(
            money: Money::fromMajor((float) ($order['precio_snapshot'] ?? 0), $currency),
            description: 'Membresía '.($order['paquete_slug'] ?? '').' — Lebytek',
            customerEmail: (string) ($order['email'] ?? ''),
            successUrl: $base.'/comprar/orden/'.$publicId.'/pago/exito',
            cancelUrl: $base.'/comprar/orden/'.$publicId.'/pago/cancelado',
            externalRef: $publicId,
            metadata: ['order_public_id' => $publicId],
            mode: 'payment',
        ));

        $this->orders->savePaymentRef($orderId, 'stripe', $session->providerSessionId());

        return $session->redirectUrl();
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Marketing/IniciarPagoStripeUseCaseTest
```

- [ ] **Step 5: Commit (Grupo B)**

```bash
git add app/Application/Marketing/IniciarPagoStripeUseCase.php tests/Marketing/IniciarPagoStripeUseCaseTest.php
git commit -m "feat(mkt): add IniciarPagoStripeUseCase"
```

---

### Task 11: ConfirmarPagoStripeUseCase (atomic claim + money + no throw after claim)

**Files:**
- Create: `app/Application/Marketing/ConfirmarPagoStripeUseCase.php`
- Test: `tests/Marketing/ConfirmarPagoStripeUseCaseTest.php`

**Interfaces:**
- Consumes: `PaymentEvent`, `PaymentEventLogRepositoryInterface::tryClaim`, `ActivateMembershipFromOrderService::fromConfirmedPayment`, `MembershipOrderRepositoryInterface`
- Produces: `ejecutar(PaymentEvent $event): void` — never throws after successful claim

**Acceptance Criteria:**
1. Duplicate `providerEventId` (`tryClaim` false) → no activation, no exception.
2. `Ignored` → claim optional or early return; no activation.
3. Money/currency mismatch vs snapshot → claim recorded, order **not** paid, no activation, **no throw**.
4. `CheckoutCompleted` + tenant → `markPaid` then activate with stable key; activation call count = 1.
5. `CheckoutCompleted` + no tenant → `markPaid` + `setApiActivationError`, no activate call.
6. Already `paid` → claim + return (no second activate).
7. Activation throws after markPaid → ConfirmarPago catches, logs, **does not rethrow**.
8. `PaymentFailed` → claim; leave `pending_payment`; no activate.

- [ ] **Step 1: Write failing tests (complete scenarios)**

Create `tests/Marketing/ConfirmarPagoStripeUseCaseTest.php`. Copy the Task 8 doubles `ActRecTransport`, mailer, and an orders repo patterned like `ConfirmOrders` below (microtest does not share classes across files). Wire real `ActivateMembershipFromOrderService` + `LebytekApiClient` with recording transport.

```php
<?php
declare(strict_types=1);

use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Application\Marketing\ConfirmarPagoStripeUseCase;
use App\Application\Marketing\MembershipOrderActors;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

final class ConfirmEventLog implements PaymentEventLogRepositoryInterface
{
    /** @var array<string, true> */
    public array $claimed = [];
    public function hasProcessed(string $provider, string $eventId): bool
    {
        return isset($this->claimed[$provider."\0".$eventId]);
    }
    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool {
        $k = $provider."\0".$eventId;
        if (isset($this->claimed[$k])) {
            return false;
        }
        $this->claimed[$k] = true;
        return true;
    }
}

final class ConfirmOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $byPublic = [];
    public bool $paid = false;
    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array
    {
        foreach ($this->byPublic as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
    public function findByPublicId(string $publicId): ?array { return $this->byPublic[$publicId] ?? null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void
    {
        foreach ($this->byPublic as $k => $row) {
            if ((int) ($row['id'] ?? 0) === $orderId) {
                $this->byPublic[$k]['api_activation_error'] = $error;
            }
        }
    }
    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->paid = true;
        foreach ($this->byPublic as $k => $row) {
            if ((int) ($row['id'] ?? 0) === $orderId) {
                $this->byPublic[$k]['status'] = 'paid';
                $this->byPublic[$k]['authorized_by'] = $authorizedBy;
            }
        }
    }
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class ConfirmRecTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];
    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses = [];
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{"token":"t"}', 'error' => ''];
    }
}

final class ConfirmMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void {}
}

function confirmPendingOrder(array $over = []): array
{
    return array_merge([
        'id' => 1,
        'public_id' => '01JORDPAY00000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_payment',
        'metodo_pago' => 'stripe',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ], $over);
}

function makeConfirmar(ConfirmOrders $orders, ConfirmEventLog $log, ConfirmRecTransport $transport): ConfirmarPagoStripeUseCase
{
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $activator = new ActivateMembershipFromOrderService($orders, $api, new ConfirmMailer());
    return new ConfirmarPagoStripeUseCase($orders, $log, $activator);
}

test('evento duplicado no reactiva', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $transport->responses[] = ['status' => 201, 'body' => '{"token":"t1"}', 'error' => ''];
    $uc = makeConfirmar($orders, $log, $transport);
    $event = new PaymentEvent(PaymentEventType::CheckoutCompleted, 'evt_dup', '01JORDPAY00000000000000001', Money::fromMajor(2199, 'mxn'), 'paid');
    $uc->ejecutar($event);
    $uc->ejecutar($event);
    assert_same(1, count($transport->calls));
    assert_true($orders->paid);
});

test('Ignored no llama activate-plan', function (): void {
    $orders = new ConfirmOrders();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(PaymentEventType::Ignored, 'evt_noise', '', Money::fromMajor(0, 'mxn'), 'customer.created'));
    assert_same(0, count($transport->calls));
    assert_true($log->hasProcessed('stripe', 'evt_noise'));
});

test('mismatch de monto no marca paid ni activa', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_bad_amt',
        '01JORDPAY00000000000000001',
        new Money(100, 'mxn'),
        'paid',
    ));
    assert_true(! $orders->paid);
    assert_same(0, count($transport->calls));
    assert_true(isset($orders->byPublic['01JORDPAY00000000000000001']['api_activation_error']));
});

test('CheckoutCompleted con tenant marca paid y activa una vez', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $transport->responses[] = ['status' => 201, 'body' => '{"token":"tok"}', 'error' => ''];
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_ok',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_true($orders->paid);
    assert_same(1, count($transport->calls));
    $key = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORDPAY00000000000000001');
    assert_true(str_contains(implode("\n", $transport->calls[0]['headers']), 'Idempotency-Key: '.$key));
    assert_same(MembershipOrderActors::SYSTEM_WEBHOOK, $orders->byPublic['01JORDPAY00000000000000001']['authorized_by']);
});

test('CheckoutCompleted sin tenant marca paid sin activate', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder(['api_tenant_public_id' => '']);
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_no_tenant',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_true($orders->paid);
    assert_same(0, count($transport->calls));
});

test('orden ya paid es no-op de activate', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder(['status' => 'paid']);
    $orders->paid = true;
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_already',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_same(0, count($transport->calls));
});

test('fallo de activate tras claim no relanza', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $transport->responses[] = ['status' => 500, 'body' => '{"message":"down"}', 'error' => ''];
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_api_down',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_true($orders->paid);
    assert_true(isset($orders->byPublic['01JORDPAY00000000000000001']['api_activation_error']));
});

test('PaymentFailed deja pending_payment', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::PaymentFailed,
        'evt_fail',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'failed',
    ));
    assert_true(! $orders->paid);
    assert_same('pending_payment', $orders->byPublic['01JORDPAY00000000000000001']['status']);
    assert_same(0, count($transport->calls));
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php tests/run.php Marketing/ConfirmarPagoStripeUseCaseTest
```

- [ ] **Step 3: Implement ConfirmarPago**

```php
<?php
declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class ConfirmarPagoStripeUseCase
{
    private const PROVIDER = 'stripe';

    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly PaymentEventLogRepositoryInterface $eventLog,
        private readonly ActivateMembershipFromOrderService $activator,
    ) {}

    public function ejecutar(PaymentEvent $event): void
    {
        if ($event->type() === PaymentEventType::Ignored) {
            // Still claim so Stripe retries of noise are cheap no-ops.
            $this->eventLog->tryClaim(
                self::PROVIDER,
                $event->providerEventId(),
                $event->externalRef(),
                $event->type()->value,
                hash('sha256', $event->providerEventId()),
            );
            return;
        }

        $orderRef = $event->externalRef();
        $claimed = $this->eventLog->tryClaim(
            self::PROVIDER,
            $event->providerEventId(),
            $orderRef,
            $event->type()->value,
            hash('sha256', $event->providerEventId().'|'.$orderRef),
        );
        if (! $claimed) {
            return; // already processed
        }

        // From here: NEVER throw to the webhook controller.
        try {
            $this->processClaimed($event);
        } catch (\Throwable $e) {
            AppLogger::error('[ConfirmarPagoStripe] post-claim failure swallowed', [
                'event_id' => $event->providerEventId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processClaimed(PaymentEvent $event): void
    {
        $order = $this->orders->findByPublicId($event->externalRef());
        if ($order === null) {
            AppLogger::warning('[ConfirmarPagoStripe] order missing', [
                'ref' => $event->externalRef(),
            ]);
            return;
        }

        if ($event->type() === PaymentEventType::PaymentFailed) {
            AppLogger::warning('[ConfirmarPagoStripe] payment failed; order left pending_payment', [
                'order_id' => $order['id'] ?? null,
            ]);
            return;
        }

        if ($event->type() !== PaymentEventType::CheckoutCompleted) {
            return;
        }

        $status = (string) ($order['status'] ?? '');
        if ($status === 'paid') {
            return;
        }
        if ($status !== 'pending_payment') {
            AppLogger::warning('[ConfirmarPagoStripe] unexpected status', ['status' => $status]);
            return;
        }

        $currency = (string) EnvLoader::get('PAYMENTS_CURRENCY', 'mxn');
        $expected = Money::fromMajor((float) ($order['precio_snapshot'] ?? 0), $currency);
        if (! $event->money()->equals($expected)) {
            AppLogger::error('[ConfirmarPagoStripe] amount/currency mismatch', [
                'order_id' => $order['id'] ?? null,
                'expected_minor' => $expected->amountMinor(),
                'got_minor' => $event->money()->amountMinor(),
            ]);
            $this->orders->setApiActivationError(
                (int) $order['id'],
                'Pago Stripe con monto/moneda distinto al snapshot; revisión manual.'
            );
            return;
        }

        $tenant = trim((string) ($order['api_tenant_public_id'] ?? ''));
        if ($tenant === '') {
            $this->orders->markPaid((int) $order['id'], MembershipOrderActors::SYSTEM_WEBHOOK);
            $this->orders->setApiActivationError((int) $order['id'], 'Tenant no asociado; activación manual pendiente.');
            return;
        }

        $key = ActivateMembershipFromOrderService::stableActivateIdempotencyKey((string) $order['public_id']);
        try {
            $this->activator->fromConfirmedPayment($order, MembershipOrderActors::SYSTEM_WEBHOOK, $key);
        } catch (LebytekApiException $e) {
            // markPaid already done inside fromConfirmedPayment; keep 200 to Stripe.
            AppLogger::error('[ConfirmarPagoStripe] activation failed after paid', [
                'order_id' => $order['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
php tests/run.php Marketing/ConfirmarPagoStripeUseCaseTest
```

- [ ] **Step 5: Commit (Grupo B)**

```bash
git add app/Application/Marketing/ConfirmarPagoStripeUseCase.php tests/Marketing/ConfirmarPagoStripeUseCaseTest.php
git commit -m "feat(mkt): confirm stripe payment with atomic claim and money guard"
```

---

### Task 12: CompraController, views, and routes

**Files:**
- Modify: `app/Presentation/Controllers/Publico/CompraController.php`
- Modify: `app/Presentation/Views/publico/compra_form.php`
- Create: `app/Presentation/Views/publico/compra_pago_exito.php`
- Create: `app/Presentation/Views/publico/compra_pago_cancelado.php`
- Modify: `routes/marketing.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/CompraStripeFlowTest.php`

**Interfaces:**
- Consumes: `IniciarPagoStripeUseCase`, `CrearOrdenMembresiaUseCase`
- Produces: routes GET success/cancel; POST submit branches on `metodo_pago`

**Acceptance Criteria:**
- `compra_form.php` contains `name="metodo_pago"` with `stripe` and `transfer` values.
- Routes file registers success + cancel GET paths.
- Success view mentions confirming payment (copy stable string `"confirmando"` case-insensitive).
- Portal deploy note: `vertical.modules.payments => true` (not in skeleton).

- [ ] **Step 1: Write failing tests**

```php
<?php
declare(strict_types=1);

test('compra_form incluye selector tarjeta y transferencia', function (): void {
    $html = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Views/publico/compra_form.php');
    assert_true(str_contains($html, 'name="metodo_pago"'));
    assert_true(str_contains($html, 'value="stripe"'));
    assert_true(str_contains($html, 'value="transfer"'));
});

test('rutas de pago exito y cancelado existen', function (): void {
    $routes = (string) file_get_contents(ROOT_PATH . '/routes/marketing.php');
    assert_true(str_contains($routes, '/comprar/orden/{publicId}/pago/exito'));
    assert_true(str_contains($routes, '/comprar/orden/{publicId}/pago/cancelado'));
    assert_true(str_contains($routes, 'pagoExito'));
    assert_true(str_contains($routes, 'pagoCancelado'));
});

test('vista exito menciona confirmando pago', function (): void {
    $html = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Views/publico/compra_pago_exito.php');
    assert_true(str_contains(mb_strtolower($html), 'confirmando'));
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Marketing/CompraStripeFlowTest
```

- [ ] **Step 3: Update controller, views, routes, DI**

Inject `IniciarPagoStripeUseCase`. After creating order in `submit()`:

```php
$metodo = (string) $request->input('metodo_pago', 'transfer');
// pass metodo_pago into crearOrden input

if ($metodo === 'stripe') {
    $redirectUrl = $this->iniciarPago->ejecutar((int) ($order['id'] ?? 0));
    return $this->redirect($redirectUrl);
}

return $this->redirect('/comprar/orden/'.($order['public_id'] ?? '').'/transferencia');
```

```php
public function pagoExito(Request $request, string $publicId): Response
{
    $order = $this->orders->findByPublicId($publicId);
    return $this->view('publico/compra_pago_exito', [
        'order' => $order,
        'publicId' => $publicId,
    ]);
}

public function pagoCancelado(Request $request, string $publicId): Response
{
    return $this->view('publico/compra_pago_cancelado', ['publicId' => $publicId]);
}
```

Views: radio `metodo_pago`; exito copy “Estamos confirmando tu pago…”, cancelado with link back to comprar.

Routes:

```php
$router->get('/comprar/orden/{publicId}/pago/exito', [CompraController::class, 'pagoExito']);
$router->get('/comprar/orden/{publicId}/pago/cancelado', [CompraController::class, 'pagoCancelado']);
```

DI bind `IniciarPagoStripeUseCase` + update `CompraController`.

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Marketing/CompraStripeFlowTest
```

- [ ] **Step 5: Commit (Grupo B + C container)**

```bash
git add app/Presentation/Controllers/Publico/CompraController.php app/Presentation/Views/publico/compra_form.php app/Presentation/Views/publico/compra_pago_exito.php app/Presentation/Views/publico/compra_pago_cancelado.php routes/marketing.php config/container.php tests/Marketing/CompraStripeFlowTest.php
git commit -m "feat(mkt): checkout UI and stripe redirect flow"
```

---

### Task 13: StripeWebhookController

**Files:**
- Create: `app/Presentation/Controllers/Publico/StripeWebhookController.php`
- Modify: `routes/marketing.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/StripeWebhookControllerTest.php`

**Interfaces:**
- Consumes: raw `php://input`, `Stripe-Signature`, registry, `ConfirmarPagoStripeUseCase`
- Produces: HTTP 200 on success / Ignored; HTTP 400 on bad signature with **generic** error body

**Acceptance Criteria:**
- Route `/webhooks/stripe` POST exists **without** `CsrfMiddleware` on that registration.
- Source does not echo `$e->getMessage()` into JSON (search for generic `'invalid webhook'` or similar).
- Contract: `file_get_contents('php://input')` used (document raw body assumption).
- Bad signature path returns 400; happy path returns 200 (unit with fake gateway if practical; else source + light controller test with injected fakes).

- [ ] **Step 1: Write failing tests**

```php
<?php
declare(strict_types=1);

test('ruta webhook stripe sin CsrfMiddleware', function (): void {
    assert_true(class_exists(\App\Presentation\Controllers\Publico\StripeWebhookController::class));
    $routes = (string) file_get_contents(ROOT_PATH . '/routes/marketing.php');
    assert_true(str_contains($routes, '/webhooks/stripe'));
    assert_true(str_contains($routes, 'StripeWebhookController'));
    // Middleware is opt-in per route group; ensure the webhook line does not attach CsrfMiddleware.
    foreach (preg_split('/\R/', $routes) as $line) {
        if (str_contains($line, '/webhooks/stripe')) {
            assert_true(! str_contains($line, 'CsrfMiddleware'), $line);
        }
    }
});

test('StripeWebhookController no filtra mensajes internos en 400', function (): void {
    $src = (string) file_get_contents(
        ROOT_PATH . '/app/Presentation/Controllers/Publico/StripeWebhookController.php'
    );
    assert_true(str_contains($src, "file_get_contents('php://input')"));
    assert_true(! str_contains($src, 'getMessage()'), 'no leak exception message to client');
    assert_true(str_contains($src, 'invalid webhook') || str_contains($src, '"error"'));
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Marketing/StripeWebhookControllerTest
```

- [ ] **Step 3: Implement controller**

```php
<?php
declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Application\Marketing\ConfirmarPagoStripeUseCase;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class StripeWebhookController extends BaseController
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ConfirmarPagoStripeUseCase $confirmar,
    ) {}

    public function handle(Request $request): Response
    {
        // Framework Request does not consume php://input today — keep reading raw body here.
        $payload = (string) file_get_contents('php://input');
        $signature = (string) ($request->header('Stripe-Signature') ?? $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        try {
            $event = $this->gateways->get('stripe')->parseWebhook($payload, $signature);
            $this->confirmar->ejecutar($event);
        } catch (\Throwable $e) {
            AppLogger::warning('[StripeWebhook] rejected', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'invalid webhook'], 400);
        }

        return Response::json(['received' => true], 200);
    }
}
```

Route:

```php
use App\Presentation\Controllers\Publico\StripeWebhookController;

$router->post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
```

Bind only when `marketing` && `payments` vertical flags are ON.

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Marketing/StripeWebhookControllerTest
```

- [ ] **Step 5: Commit (Grupo B)**

```bash
git add app/Presentation/Controllers/Publico/StripeWebhookController.php routes/marketing.php config/container.php tests/Marketing/StripeWebhookControllerTest.php
git commit -m "feat(mkt): add stripe webhook endpoint without CSRF or error leaks"
```

---

### Task 14: Handoff BOUNDARY to framework-portal-separation plan

**Files:**
- Modify: `docs/superpowers/plans/2026-07-15-framework-portal-separation.md` — add `payments.sql` next to `integrations.sql` in BOUNDARY module list and any PackagePaths test examples that enumerate platform modules
- Modify: `docs/superpowers/specs/2026-07-15-framework-portal-separation-design.md` only if it lists platform modules explicitly

**Acceptance Criteria:**
- Split plan BOUNDARY / modules list includes `payments.sql`.
- Payments plan self-review row points to this task as done.

- [ ] **Step 1: Patch split plan**

In `2026-07-15-framework-portal-separation.md`, change platform modules line from:

```text
`calendario.sql`, `pdf-kit.sql`, `reportes.sql`, `crud-engine.sql`, `integrations.sql`
```

to:

```text
`calendario.sql`, `pdf-kit.sql`, `reportes.sql`, `crud-engine.sql`, `integrations.sql`, `payments.sql`
```

Add a checkbox note under that BOUNDARY section: “Pagos shippeados en monorepo antes del carve — incluir módulo `payments` OFF en skeleton.”

- [ ] **Step 2: Commit (docs)**

```bash
git add docs/superpowers/plans/2026-07-15-framework-portal-separation.md
git commit -m "docs(split): add payments.sql to platform BOUNDARY handoff"
```

---

### Task 15: Full verification and ops checklist (human)

**Files:**
- None (verification only)

**Acceptance Criteria:**
- `php tests/run.php Payments` and `Marketing` and `Integrations` / `Integration` exit with 0 failures.
- Ops checklist completed by a human (agent must not deploy/SSH/push).

- [ ] **Step 1: Run full test suites**

```bash
php tests/run.php Payments
php tests/run.php Marketing
php tests/run.php Integration
php tests/run.php Integrations
```

Expected: all PASS (`M=0`).

- [ ] **Step 2: Local smoke (manual, Stripe test mode)**

1. Portal `.env`: `vertical.modules.payments=true`, `STRIPE_ENABLED=true`, test keys, `PAYMENTS_CURRENCY=mxn`.
2. Apply `payments.sql` + mkt migration.
3. `/?compras=1` → Tarjeta → `4242…` with Stripe CLI forwarding to `/webhooks/stripe`.
4. Confirm order `paid` + single activate-plan (stable Idempotency-Key) when tenant linked.
5. Replay same webhook → 200, no second activation.

- [ ] **Step 3: Ops checklist (human only)**

| Step | Action |
|------|--------|
| VPS | Pull `feature/payments-gateway`; bootstrap payments + marketing migrations |
| Env | `STRIPE_*`, `PAYMENTS_*`, `vertical.modules.payments=true` (assert skeleton false ≠ portal true) |
| Stripe Dashboard | Webhook → `https://lebytek.com/webhooks/stripe`, events: `checkout.session.completed` (+ failed variants if desired) |
| Smoke | Card purchase on staging |
| Assert | Deploy config has payments ON; skeleton remains OFF |

- [ ] **Step 4: Spec status**

Update design status to "Implementado" only after Tasks 1–14 land and tests pass.

---

## Self-Review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| PaymentGatewayInterface + VOs + mxn lock + `Ignored` | Task 1 |
| PaymentGatewayRegistry + PaymentsFactory + `resetCached` | Task 2 |
| `pay_events` + atomic `tryClaim` | Task 3 |
| StripeGateway (+ session idempotency, Ignored) | Task 4 |
| config/payments.php + vertical flag gated DI | Task 5 |
| dom_mkt_ordenes columns + pending_payment | Task 6–7 |
| ActivateMembershipFromOrderService + stable Idempotency-Key | Task 8 |
| CrearOrden metodo_pago branch | Task 9 |
| IniciarPagoStripeUseCase | Task 10 |
| ConfirmarPago: claim→money→markPaid→activate; never throw post-claim | Task 11 |
| Compra UI + routes | Task 12 |
| Webhook controller (no CSRF, no message leak) | Task 13 |
| BOUNDARY handoff `payments.sql` | Task 14 ✓ |
| Transfer flow unchanged | Task 9 default |
| SupportsSubscriptions marker only | Task 1 |
| Named debt (async queue, purge, TTL, poll, refunds) | Global section — not tasks |

**Placeholder scan:** none found — Tasks 1–15 include concrete paths, full test code, implementation code, commands, and formal Acceptance Criteria. Named debt is listed globally (not as TODOs inside tasks).

**Type consistency:** `pay_events`, `tryClaim`, `PaymentEventType::Ignored`, `ActivateMembershipFromOrderService::{fromManualAuthorize,fromConfirmedPayment,stableActivateIdempotencyKey}`, `MembershipOrderActors::SYSTEM_WEBHOOK`, `LebytekApiClient::activatePlan(..., ?string $idempotencyKey)` aligned across Tasks 1–13.

**Review fixes applied (C1–C7):** stable Idempotency-Key; atomic claim before side-effects; money/currency guard; Ignored→200; `pay_events` naming unified in design+plan; markPaid-before-activate on Stripe path; dedicated activation service (not fragile extract); completed tests + formal AC; BOUNDARY handoff Task 14.

---

## Execution Handoff

Plan corrected and saved to `docs/superpowers/plans/2026-07-15-payments-gateway.md` (spec also aligned). Two execution options:

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
