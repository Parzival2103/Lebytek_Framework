# FPS Plan 01 — Payments y Stripe genéricos

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Trasladar el módulo **Payments genérico** (puerto, StripeGateway, ledger `pay_events`, config/tests) desde `feature/backoffice-api-integration` a la rama `consolidation/framework-portal-separation`, **apagado por defecto** y **sin** código de membresías/checkout Portal.

**Architecture:** Réplica del subsistema Integrations: `PaymentGatewayInterface` → registry → factory → `StripeGateway`. Todo vive en `src/` + `database/schema/modules/payments.sql`. Config del consumidor (`config/payments.php`, `vertical.modules.payments=false`) gobierna el binding. El Portal añadirá use cases Marketing en Plan 05.

**Tech Stack:** PHP 8.1+, PDO, `stripe/stripe-php` ^16.0, microtest (`php tests/run.php Payments`).

**Spec:** `docs/superpowers/specs/2026-07-15-payments-gateway-design.md`

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 01

**Predecesor obligatorio:** Plan 00 (`2026-07-17-fps-00-inventory-consolidation-branch.md`) — rama `consolidation/framework-portal-separation` + manifiesto BOUNDARY.

**Sucesor:** Plan 02 (`2026-07-17-fps-02-platform-stabilization.md`).

## Global Constraints

- Rama de trabajo: **`consolidation/framework-portal-separation`** (checkout al iniciar).
- **`main` puede contener wiring parcial de Payments** (p. ej. bloque condicional en `config/container.php`, flags en `vertical.php`) **sin** el árbol completo `src/Domain/Payments/` — no asumir ausencia total. Comparar con `git diff main dad0590 -- <path>` y copiar **selectivamente** solo paths allowlist; no checkout ciego del monorepo feature.
- SHA fuente de paths exclusivos de feature: **`dad059056d26b6eb527815f85cf71ecd507a57fe`** (`feature/backoffice-api-integration`).
- Traslado: **`git checkout dad0590 -- <path>`** solo para paths en allowlist BOUNDARY; archivos mixtos se editan a mano.
- **`vertical.modules.payments`** = **`false`** en harness y skeleton; **`STRIPE_ENABLED=false`** en `.env.example`.
- Tabla ledger: **`pay_events`** (prefijo `pay_`). Nunca `fw_payment_events`.
- v1 moneda: **`mxn`** únicamente. `SupportsSubscriptions` = marker vacío.
- **Prohibido** en este plan: cualquier path `app/**`, `*mkt*`, `ConfirmarPagoStripeUseCase`, `IniciarPagoStripeUseCase`, `StripeWebhookController`, rutas checkout, bindings Marketing en `container.php`.
- **Prohibido** merge feature→main, deploy, push remoto, editar `vendor/` manualmente.
- Gate principal: `php tests/run.php Payments` → **`0 failed`**.
- Gate secundario post-`composer install`: suite completa no debe empeorar por Payments.

---

### Task 1: Capa de dominio Payments (VOs, puerto, repo interface)

**Files:**
- Create: `src/Domain/Payments/PaymentGatewayInterface.php`
- Create: `src/Domain/Payments/SupportsSubscriptions.php`
- Create: `src/Domain/Payments/PaymentEventType.php`
- Create: `src/Domain/Payments/PaymentEventLogRepositoryInterface.php`
- Create: `src/Domain/Payments/ValueObjects/Money.php`
- Create: `src/Domain/Payments/ValueObjects/CheckoutRequest.php`
- Create: `src/Domain/Payments/ValueObjects/CheckoutSession.php`
- Create: `src/Domain/Payments/ValueObjects/PaymentEvent.php`
- Test: `tests/Payments/PaymentValueObjectsTest.php`
- Test: `tests/Payments/PaymentEventLogClaimDoubleTest.php` (clase in-memory + test atómico)

**Interfaces:**
- Consumes: autoload `Lebytek\Framework\` existente.
- Produces: `PaymentGatewayInterface`, VOs, `PaymentEventLogRepositoryInterface::tryClaim(...): bool`, enum `PaymentEventType`.

- [ ] **Step 1: Write the failing tests**

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
        successUrl: 'https://example.com/pago/exito',
        cancelUrl: 'https://example.com/pago/cancelado',
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

Create `tests/Payments/PaymentEventLogClaimDoubleTest.php`:

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

- [ ] **Step 2: Run tests to verify they fail**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php PaymentValueObjects
```

Expected: FAIL — clases `Money`, `CheckoutRequest`, etc. not found.

- [ ] **Step 3: Trasladar/implementar capa dominio**

**3a — Inventariar delta (main vs feature vs rama actual):**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git diff --name-only main dad0590 -- src/Domain/Payments/ tests/Payments/PaymentValueObjectsTest.php tests/Payments/PaymentEventLogClaimDoubleTest.php
git diff --stat consolidation/framework-portal-separation dad0590 -- src/Domain/Payments/ 2>$null
```

Expected: lista de archivos allowlist; si un path ya coincide con `dad0590`, omitir checkout de ese path.

**3b — Opción A (preferida): checkout selectivo desde feature SHA**

```powershell
git checkout dad0590 -- `
  src/Domain/Payments/PaymentGatewayInterface.php `
  src/Domain/Payments/SupportsSubscriptions.php `
  src/Domain/Payments/PaymentEventType.php `
  src/Domain/Payments/PaymentEventLogRepositoryInterface.php `
  src/Domain/Payments/ValueObjects/Money.php `
  src/Domain/Payments/ValueObjects/CheckoutRequest.php `
  src/Domain/Payments/ValueObjects/CheckoutSession.php `
  src/Domain/Payments/ValueObjects/PaymentEvent.php
```

**3c — Opción B — crear manualmente** (si checkout falla o el path no existe en `dad0590`). Crear **cada** archivo con el contenido exacto siguiente:

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

`src/Domain/Payments/ValueObjects/Money.php`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

/** PHP 8.1: no `readonly class` (8.2+); use promoted `readonly` props. */
final class Money
{
    private readonly string $currency;

    public function __construct(
        private readonly int $amountMinor,
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

/** PHP 8.1: no `readonly class` (8.2+); use promoted `readonly` props. */
final class CheckoutRequest
{
    /** @param array<string, string> $metadata */
    public function __construct(
        private readonly Money $money,
        private readonly string $description,
        private readonly string $customerEmail,
        private readonly string $successUrl,
        private readonly string $cancelUrl,
        private readonly string $externalRef,
        private readonly array $metadata,
        private readonly string $mode,
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

/** PHP 8.1: no `readonly class` (8.2+); use promoted `readonly` props. */
final class CheckoutSession
{
    public function __construct(
        private readonly string $providerSessionId,
        private readonly string $redirectUrl,
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

/** PHP 8.1: no `readonly class` (8.2+); use promoted `readonly` props. */
final class PaymentEvent
{
    public function __construct(
        private readonly PaymentEventType $type,
        private readonly string $providerEventId,
        private readonly string $externalRef,
        private readonly Money $money,
        private readonly string $rawStatus,
    ) {}

    public function type(): PaymentEventType { return $this->type; }
    public function providerEventId(): string { return $this->providerEventId; }
    public function externalRef(): string { return $this->externalRef; }
    public function money(): Money { return $this->money; }
    public function rawStatus(): string { return $this->rawStatus; }
}
```

**3d — Verificar invariantes:**

- `PaymentEventType`: cases `CheckoutCompleted`, `PaymentFailed`, `Ignored`
- `Money`: lanza `\InvalidArgumentException` si currency ≠ `mxn`
- `PaymentEventLogRepositoryInterface`: `tryClaim` documentado como INSERT UNIQUE
- Namespaces bajo `Lebytek\Framework\Domain\Payments\` únicamente (sin `App\`)

- [ ] **Step 4: Run tests to verify they pass**

Run:

```powershell
php tests/run.php PaymentValueObjects
php tests/run.php PaymentEventLogClaimDouble
```

Expected: `N passed, 0 failed` en ambos.

- [ ] **Step 5: Commit**

```powershell
git add src/Domain/Payments tests/Payments/PaymentValueObjectsTest.php tests/Payments/PaymentEventLogClaimDoubleTest.php
git commit -m "feat(payments): add generic payment domain layer and VOs"
```

---

### Task 2: Application, Infrastructure, schema SQL y dependencia Stripe

**Files:**
- Create: `src/Application/Payments/PaymentGatewayRegistry.php`
- Create: `src/Application/Payments/PaymentsFactory.php`
- Create: `src/Infrastructure/Payments/StripeGateway.php`
- Create: `src/Infrastructure/Payments/PdoPaymentEventLogRepository.php`
- Create: `database/schema/modules/payments.sql`
- Modify: `composer.json` — añadir `"stripe/stripe-php": "^16.0"`
- Test: `tests/Payments/PaymentGatewayRegistryTest.php`
- Test: `tests/Payments/PaymentsFactoryTest.php`
- Test: `tests/Payments/PaymentsSchemaTest.php`
- Test: `tests/Payments/StripeGatewayTest.php`
- Test: `tests/Payments/fixtures/stripe_checkout_completed.json`
- Test: `tests/Payments/fixtures/stripe_unmapped_event.json`

**Interfaces:**
- Consumes: dominio Task 1 (`PaymentGatewayInterface`, VOs, `PaymentEventLogRepositoryInterface`).
- Produces: `PaymentGatewayRegistry::get(string): PaymentGatewayInterface`, `PaymentsFactory::registry()`, `StripeGateway::parseWebhook`, `PdoPaymentEventLogRepository::tryClaim`.

- [ ] **Step 1: Write the failing tests**

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

Create `tests/Payments/PaymentsSchemaTest.php`:

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

Create `tests/Payments/StripeGatewayTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Infrastructure\Payments\StripeGateway;

function stripeTestSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    return "t={$timestamp},v1={$signature}";
}

test('StripeGateway rechaza firma inválida', function (): void {
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => 'whsec_test_secret',
        'currency' => 'mxn',
    ]);
    assert_throws(\UnexpectedValueException::class, fn () => $gateway->parseWebhook('{}', 'bad_sig'));
});

test('StripeGateway parseWebhook acepta firma válida de fixture', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_checkout_completed.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gateway->parseWebhook($payload, $signature);
    assert_same(PaymentEventType::CheckoutCompleted, $event->type());
    assert_true($event->externalRef() !== '');
    assert_same(219900, $event->money()->amountMinor());
    assert_same('mxn', $event->money()->currency());
});

test('StripeGateway evento no mapeado devuelve Ignored', function (): void {
    $secret = 'whsec_test_secret';
    $payload = (string) file_get_contents(ROOT_PATH . '/tests/Payments/fixtures/stripe_unmapped_event.json');
    $signature = stripeTestSignature($payload, $secret);
    $gateway = new StripeGateway([
        'secret_key' => 'sk_test_x',
        'webhook_secret' => $secret,
        'currency' => 'mxn',
    ]);
    $event = $gateway->parseWebhook($payload, $signature);
    assert_same(PaymentEventType::Ignored, $event->type());
});

test('StripeGateway createCheckout documenta idempotency_key = externalRef', function (): void {
    $source = (string) file_get_contents(ROOT_PATH . '/src/Infrastructure/Payments/StripeGateway.php');
    assert_true(str_contains($source, 'idempotency_key'));
    assert_true(str_contains($source, 'externalRef()'));
});
```

Create `tests/Payments/fixtures/stripe_checkout_completed.json`:

```json
{
  "id": "evt_checkout_completed_123",
  "type": "checkout.session.completed",
  "data": {
    "object": {
      "id": "cs_test_123",
      "metadata": {
        "order_public_id": "order_01JABCDEF"
      },
      "amount_total": 219900,
      "currency": "mxn",
      "payment_status": "paid"
    }
  }
}
```

Create `tests/Payments/fixtures/stripe_unmapped_event.json`:

```json
{
  "id": "evt_unmapped_123",
  "type": "customer.created",
  "data": {
    "object": {
      "id": "cus_test",
      "metadata": {}
    }
  }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```powershell
php tests/run.php PaymentGatewayRegistry
php tests/run.php StripeGateway
```

Expected: FAIL — classes not found or `Class "Stripe\Stripe" not found`.

- [ ] **Step 3: Trasladar application/infrastructure/SQL y añadir Composer dep**

```powershell
git checkout dad0590 -- `
  src/Application/Payments/PaymentGatewayRegistry.php `
  src/Application/Payments/PaymentsFactory.php `
  src/Infrastructure/Payments/StripeGateway.php `
  src/Infrastructure/Payments/PdoPaymentEventLogRepository.php `
  database/schema/modules/payments.sql
```

En `composer.json`, dentro de `"require"`, añadir:

```json
"stripe/stripe-php": "^16.0"
```

Instalar dependencias:

```powershell
composer update stripe/stripe-php --no-interaction
composer dump-autoload
```

Verificar `database/schema/modules/payments.sql` contiene tabla `pay_events` con UNIQUE `(provider, event_id)`.

Verificar `StripeGateway.php` importa **solo** desde `Stripe\` y usa `idempotency_key => $request->externalRef()` en `Session::create`.

- [ ] **Step 4: Run tests to verify they pass**

Run:

```powershell
php tests/run.php PaymentGatewayRegistry
php tests/run.php PaymentsFactory
php tests/run.php PaymentsSchema
php tests/run.php StripeGateway
```

Expected: cada filtro termina `0 failed`.

- [ ] **Step 5: Commit**

```powershell
git add src/Application/Payments src/Infrastructure/Payments database/schema/modules/payments.sql composer.json composer.lock tests/Payments
git commit -m "feat(payments): registry, StripeGateway, pay_events schema and tests"
```

---

### Task 3: Config consumidor (OFF por defecto) y gate Payments

**Files:**
- Create: `config/payments.php`
- Create: `config/modules/payments.php`
- Create: `skeleton/config/payments.php`
- Modify: `config/vertical.php` — `'payments' => false`
- Modify: `skeleton/config/vertical.php` — `'payments' => false`
- Modify: `config/container.php` — bloque plataforma **únicamente** (sin bindings `App\`)
- Modify: `.env.example` — bloque `STRIPE_*` / `PAYMENTS_*`
- Test: `tests/Payments/PaymentsConfigTest.php`

**Interfaces:**
- Consumes: `PaymentsFactory::registry()`, `PdoPaymentEventLogRepository`.
- Produces: config cargable vía `Config::get('payments')`; registry resoluble solo si `vertical.modules.payments === true`.

- [ ] **Step 1: Write the failing config test**

Create `tests/Payments/PaymentsConfigTest.php`:

```php
<?php
declare(strict_types=1);

test('config payments define gateway stripe', function (): void {
    $cfg = require ROOT_PATH . '/config/payments.php';
    assert_true(isset($cfg['gateways']['stripe']));
    assert_same('stripe', $cfg['gateways']['stripe']['driver']);
});

test('vertical deja payments OFF en harness y skeleton', function (): void {
    $vertical = require ROOT_PATH . '/config/vertical.php';
    assert_true(($vertical['modules']['payments'] ?? true) === false);
    $skel = require ROOT_PATH . '/skeleton/config/vertical.php';
    assert_true(($skel['modules']['payments'] ?? true) === false);
});

test('container gatea payments por vertical flag sin bindings App Marketing', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/config/container.php');
    assert_true(str_contains($src, 'vertical.modules.payments'));
    assert_true(str_contains($src, 'PaymentGatewayRegistry'));
    assert_true(str_contains($src, 'PaymentEventLogRepositoryInterface'));
    assert_true(! str_contains($src, 'IniciarPagoStripeUseCase'));
    assert_true(! str_contains($src, 'ConfirmarPagoStripeUseCase'));
    assert_true(! str_contains($src, 'StripeWebhookController'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
php tests/run.php PaymentsConfig
```

Expected: FAIL — missing `config/payments.php` or assertions on container.

- [ ] **Step 3: Añadir config consumidor OFF**

Trasladar configs:

```powershell
git checkout dad0590 -- config/payments.php config/modules/payments.php skeleton/config/payments.php
```

En `config/vertical.php` y `skeleton/config/vertical.php`, dentro de `'modules'`, asegurar:

```php
'payments' => false,
```

En `config/container.php`, insertar **solo** este bloque (no copiar bindings Marketing del SHA feature):

```php
    // ── Módulo Pagos (binding condicional al toggle; ver config/modules/payments.php) ──
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

En `.env.example`, añadir al final:

```dotenv
# Pagos (plataforma — OFF por defecto)
STRIPE_ENABLED=false
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
PAYMENTS_CURRENCY=mxn
PAYMENTS_DEFAULT_GATEWAY=stripe
```

Contenido de `config/payments.php`:

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

Contenido de `config/modules/payments.php`:

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

- [ ] **Step 4: Run gate Payments completo**

Run:

```powershell
php tests/run.php Payments
```

Expected: `N passed, 0 failed`.

- [ ] **Step 5: Commit y registrar SDD**

```powershell
git add config/payments.php config/modules/payments.php config/vertical.php config/container.php skeleton/config/payments.php skeleton/config/vertical.php .env.example tests/Payments/PaymentsConfigTest.php
git commit -m "feat(payments): consumer config OFF by default without portal wiring"
```

Append to `.superpowers/sdd/progress.md`:

```markdown
## Plan 01 — Generic Payments (2026-07-17)

- [x] Domain + Application + Infrastructure in src/
- [x] pay_events schema + stripe/stripe-php
- [x] Config OFF; no app/** stripe wiring
- Gate: `php tests/run.php Payments` → 0 failed
```

```powershell
git add .superpowers/sdd/progress.md
git commit -m "docs(fps): record Plan 01 completion"
```

---

## Self-review (author)

| Requisito roadmap / spec | Task |
|--------------------------|------|
| Traslado selectivo; main puede tener wiring parcial Payments | Global Constraints + Task 1 Step 3a |
| Dominio autocontenido (8 archivos + tests) | Task 1 Step 3c |
| Contratos, VOs, registry, factory, repo | Tasks 1–2 |
| StripeGateway genérico configurable | Task 2 |
| Schema `pay_events` + idempotencia tryClaim | Task 2 |
| Config/tests; OFF por defecto | Task 3 |
| Excluye app/**, mkt_*, checkout/membresías | Global Constraints + PaymentsConfigTest |
| Gate `php tests/run.php Payments` 0 failed | Task 3 Step 4 |
| No merge feature→main | Global Constraints |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: `pay_events`, `PaymentEventType::Ignored`, `vertical.modules.payments=false`, SHA `dad0590`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-01-generic-payments-stripe.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
