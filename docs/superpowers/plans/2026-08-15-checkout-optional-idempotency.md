# Checkout optional idempotency — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** [`docs/superpowers/specs/2026-08-15-checkout-optional-idempotency-design.md`](../specs/2026-08-15-checkout-optional-idempotency-design.md)

**Goal:** Permitir que `createCheckout` use una `idempotency_key` distinta de `externalRef` sin romper callers actuales, para que Portal pueda reabrir cobros con nueva Checkout Session.

**Architecture:** Añadir `?string $idempotencyKey = null` a `CheckoutRequest`. `StripeGateway::createCheckout` usa esa key si viene no vacía; si no, `externalRef()` (comportamiento actual). Metadata `order_public_id` sigue siendo `externalRef()`.

**Tech Stack:** PHP ≥8.2, stripe-php ^16, microtest (`php tests/run.php`).

**Rama:** `feat/checkout-optional-idempotency` desde `origin/main`.  
**Release:** PATCH **`1.2.12`** (tip actual declara `1.2.11`). Tag `v1.2.12` post-merge en `main`.

---

## Global Constraints

- PHP `>=8.2`; `declare(strict_types=1);` en archivos nuevos/editados.
- **Breaking: no** — default `null` = comportamiento actual.
- No editar consumers (Portal) en este plan; solo Framework.
- Semver trío sincronizado: `composer.json` / `config/app.php` / `skeleton/config/app.php`.
- Tag `v*` solo post-merge en `main` (no desde feature branch).
- Commits en inglés o estilo del repo (`feat:` / `test:` / `chore(release):`).

---

## File Structure

**Modificar:**
- `src/Domain/Payments/ValueObjects/CheckoutRequest.php` — 9º param + getter
- `src/Infrastructure/Payments/StripeGateway.php` — `idempotency_key` condicional
- `tests/Payments/PaymentValueObjectsTest.php` — getter / default null
- `tests/Payments/StripeGatewayTest.php` — source assert actualizado
- `composer.json`, `config/app.php`, `skeleton/config/app.php` — `1.2.12`

**Crear:**
- (ninguno obligatorio; tests viven en archivos existentes)

---

### Task 1: Tests RED — CheckoutRequest + StripeGateway source

**Files:**
- Modify: `tests/Payments/PaymentValueObjectsTest.php`
- Modify: `tests/Payments/StripeGatewayTest.php`

**Interfaces:**
- Produces: asserts que fallan hasta Task 2

- [ ] **Step 1: Ampliar `PaymentValueObjectsTest`**

Añadir tras el test de mode:

```php
test('CheckoutRequest idempotencyKey es null por defecto y acepta override', function (): void {
    $base = [
        'money' => Money::fromMajor(100.0, 'mxn'),
        'description' => 'Cobro',
        'customerEmail' => 'a@b.com',
        'successUrl' => 'https://example.com/ok',
        'cancelUrl' => 'https://example.com/ko',
        'externalRef' => '01JCOBROREF00000000000001',
        'metadata' => ['cobro_public_id' => '01JCOBROREF00000000000001'],
        'mode' => 'payment',
    ];
    $default = new CheckoutRequest(...$base);
    assert_true($default->idempotencyKey() === null);

    $custom = new CheckoutRequest(...$base, idempotencyKey: '01JCOBROREF00000000000001-a2');
    assert_same('01JCOBROREF00000000000001-a2', $custom->idempotencyKey());
});
```

> Nota: PHP named spread — construir con named args explícitos si el unpack posicional no aplica:

```php
test('CheckoutRequest idempotencyKey es null por defecto y acepta override', function (): void {
    $default = new CheckoutRequest(
        money: Money::fromMajor(100.0, 'mxn'),
        description: 'Cobro',
        customerEmail: 'a@b.com',
        successUrl: 'https://example.com/ok',
        cancelUrl: 'https://example.com/ko',
        externalRef: '01JCOBROREF00000000000001',
        metadata: ['cobro_public_id' => '01JCOBROREF00000000000001'],
        mode: 'payment',
    );
    assert_true($default->idempotencyKey() === null);

    $custom = new CheckoutRequest(
        money: Money::fromMajor(100.0, 'mxn'),
        description: 'Cobro',
        customerEmail: 'a@b.com',
        successUrl: 'https://example.com/ok',
        cancelUrl: 'https://example.com/ko',
        externalRef: '01JCOBROREF00000000000001',
        metadata: ['cobro_public_id' => '01JCOBROREF00000000000001'],
        mode: 'payment',
        idempotencyKey: '01JCOBROREF00000000000001-a2',
    );
    assert_same('01JCOBROREF00000000000001-a2', $custom->idempotencyKey());
});
```

- [ ] **Step 2: Actualizar assert de StripeGateway**

Reemplazar el test `createCheckout documenta idempotency_key = externalRef` por:

```php
test('StripeGateway createCheckout usa idempotencyKey o cae a externalRef', function (): void {
    $source = (string) file_get_contents(ROOT_PATH . '/src/Infrastructure/Payments/StripeGateway.php');
    assert_true(str_contains($source, 'idempotency_key'));
    assert_true(str_contains($source, 'idempotencyKey()'));
    assert_true(str_contains($source, 'externalRef()'));
});
```

- [ ] **Step 3: RED**

```bash
php tests/run.php PaymentValueObjects
php tests/run.php StripeGateway
```

Expected: FAIL — `idempotencyKey` undefined / source assert fails.

---

### Task 2: Implement CheckoutRequest + StripeGateway

**Files:**
- Modify: `src/Domain/Payments/ValueObjects/CheckoutRequest.php`
- Modify: `src/Infrastructure/Payments/StripeGateway.php`

- [ ] **Step 1: CheckoutRequest**

```php
public function __construct(
    private readonly Money $money,
    private readonly string $description,
    private readonly string $customerEmail,
    private readonly string $successUrl,
    private readonly string $cancelUrl,
    private readonly string $externalRef,
    private readonly array $metadata,
    private readonly string $mode,
    private readonly ?string $idempotencyKey = null,
) {
    if (! in_array($mode, ['payment', 'subscription'], true)) {
        throw new \InvalidArgumentException('mode must be payment or subscription');
    }
}

public function idempotencyKey(): ?string { return $this->idempotencyKey; }
```

- [ ] **Step 2: StripeGateway::createCheckout**

Reemplazar options:

```php
$idempotencyKey = trim((string) ($request->idempotencyKey() ?? ''));
if ($idempotencyKey === '') {
    $idempotencyKey = $request->externalRef();
}

$session = Session::create(
    [ /* ... sin cambio en params ... */ ],
    ['idempotency_key' => $idempotencyKey],
);
```

Metadata merge con `order_public_id => externalRef()` **sin cambio**.

- [ ] **Step 3: GREEN**

```bash
php tests/run.php PaymentValueObjects
php tests/run.php StripeGateway
php tests/run.php Payments
```

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Domain/Payments/ValueObjects/CheckoutRequest.php src/Infrastructure/Payments/StripeGateway.php tests/Payments/PaymentValueObjectsTest.php tests/Payments/StripeGatewayTest.php
git commit -m "feat(payments): optional CheckoutRequest idempotencyKey"
```

---

### Task 3: Spec en repo + semver 1.2.12

**Files:**
- Add (si aún untracked): `docs/superpowers/specs/2026-08-15-checkout-optional-idempotency-design.md`
- Modify: `composer.json`, `config/app.php`, `skeleton/config/app.php` → `1.2.12`

- [ ] **Step 1: Commit design spec** (si no está en main)

```bash
git add docs/superpowers/specs/2026-08-15-checkout-optional-idempotency-design.md
git commit -m "docs: checkout optional idempotency design"
```

- [ ] **Step 2: Bump trío a 1.2.12**

En los tres archivos, `1.2.11` → `1.2.12` (o tip real si ya avanzó).

- [ ] **Step 3: PlatformVersionSemver**

```bash
php tests/run.php PlatformVersionSemver
```

Expected: PASS @ `1.2.12`

- [ ] **Step 4: Commit + PR**

```bash
git add composer.json config/app.php skeleton/config/app.php
git commit -m "chore(release): bump platform version to 1.2.12"
git push -u origin HEAD
gh pr create --title "feat(payments): optional checkout idempotency key" --body "..."
```

- [ ] **Step 5: Post-merge (operador)**

```bash
git checkout main && git pull
git tag -a v1.2.12 -m "v1.2.12 optional CheckoutRequest idempotencyKey"
git push origin v1.2.12
```

---

## Self-Review

| Criterio spec | Task |
|---------------|------|
| Param opcional + getter | 1–2 |
| Gateway usa key o externalRef | 2 |
| Metadata order_public_id intacta | 2 (sin tocar merge) |
| Callers sin key sin cambio | default null |
| Tests ambos caminos | 1–2 |
| Release semver | 3 → `1.2.12` |

---

## Execution Handoff

Plan saved. Prefer **inline execution** in Framework session (small surface). After tag, Portal: `composer update lebytek/framework` + Tasks 5/9 del plan de cobros.
