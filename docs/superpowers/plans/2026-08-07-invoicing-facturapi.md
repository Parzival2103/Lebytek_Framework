# Invoicing (Facturapi) Implementation Plan

**Source spec:** `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md`  ·  **Modo:** normal

**Source audit PR:** ninguno — spec de diseño Facturapi (brainstorm 2026-08-07); no deriva de auditoría diaria

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`da3ab58bd77be95c4003341454d939aa2584a742`); rama de implementación `feature/invoicing-facturapi` (creable desde `main` — verificado `git ls-remote origin refs/heads/main`)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship an optional Framework `invoicing` vertical (OFF by default) with Domain ports, Facturapi CFDI tipo I scaffold (create/cancel/PDF/XML/email), `InvoiceableSourceInterface` orchestration, `inv_*` platform tables, and consumer connection docs.

**Architecture:** Mirror Payments: Domain ports + VOs → Application factory/registry + `IssueInvoiceFromSource` → Infrastructure `FacturapiInvoiceProvider` (official SDK behind a transport seam). Consumers implement `InvoiceableSourceInterface` to map `dom_*` / tabla X → `InvoiceDraft`. No Portal business rules in this package.

**Tech Stack:** PHP `>=8.2`, Composer, `facturapi/facturapi-php` ^4, harness `php tests/run.php` + `tests/lib/microtest.php`, PDO/MySQL platform SQL via module `bootstrap_sql`.

## Global Constraints

- Spec path: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` — follow it; do not invent `dom_*` or Portal use cases.
- PHP floor: package `composer.json` and `skeleton/composer.json` must declare `"php": ">=8.2"`.
- Dependency: `facturapi/facturapi-php` required in root `composer.json` (SDK v4).
- Vertical key: `invoicing` — OFF (`false`) in harness `config/vertical.php` and `skeleton/config/vertical.php`.
- SQL prefix: `inv_` only for platform tables in this module (`inv_events`, `inv_organizations`).
- Namespace: `Lebytek\Framework\{Domain,Application,Infrastructure}\Invoicing\…`
- Domain must not import `Facturapi\*` types.
- CFDI scope v1: tipo **I** only.
- Env: `FACTURAPI_SECRET_KEY`, `FACTURAPI_MODE=test`, `FACTURAPI_ENABLED=false`, `INVOICING_DEFAULT_PROVIDER=facturapi`.
- Do not bump packaged semver `version` in `composer.json` in this plan; the release that ships this work must document PHP ≥8.2 (treat as **major** if any consumer still runs 8.1).
- Harness tests: `php tests/run.php Invoicing` must pass; also re-run `SkeletonPurity` and `Payments` after Task 1.
- No UI/menu/settings section in this plan.

## File Structure

| Path | Responsibility |
|------|----------------|
| `composer.json` | PHP ≥8.2 + `facturapi/facturapi-php` |
| `skeleton/composer.json` | PHP ≥8.2 |
| `config/vertical.php`, `skeleton/config/vertical.php` | `invoicing => false` |
| `config/invoicing.php`, `skeleton/config/invoicing.php` | Provider map |
| `config/modules/invoicing.php`, `skeleton/config/modules/invoicing.php` | Module manifest + bootstrap_sql |
| `config/container.php`, `skeleton/config/container.php` | Gated DI bindings |
| `.env.example`, `skeleton/.env.example` | FACTURAPI_* stubs |
| `database/schema/modules/invoicing.sql` | `inv_events`, `inv_organizations` |
| `src/Domain/Invoicing/**` | Ports, VOs, enums, exceptions |
| `src/Application/Invoicing/**` | Factory, registry, use cases, draft validator |
| `src/Infrastructure/Invoicing/**` | Facturapi adapter, PDO repos, SDK transport |
| `tests/Invoicing/**` | Module tests |
| `tests/Kernel/SkeletonPurityTest.php` | Assert `invoicing` OFF |
| `docs/modules/modulo-invoicing.md` | Consumer connection guide |
| `docs/ARCHITECTURE-CONSUMER.md` | Ownership row |
| `docs/core/table-prefix-convention.md` | `inv_` (+ note `pay_`) |
| `docs/core/vertical-onboarding.md` | Mention `invoicing` toggle |

---

### Task 1: PHP floor, Facturapi dep, vertical/config stubs

**Files:**
- Modify: `composer.json`
- Modify: `skeleton/composer.json`
- Create: `config/invoicing.php`
- Create: `skeleton/config/invoicing.php`
- Create: `config/modules/invoicing.php`
- Create: `skeleton/config/modules/invoicing.php`
- Modify: `config/vertical.php`
- Modify: `skeleton/config/vertical.php`
- Modify: `.env.example`
- Modify: `skeleton/.env.example`
- Modify: `tests/Kernel/SkeletonPurityTest.php`
- Test: `tests/Invoicing/InvoicingConfigTest.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: `vertical.modules.invoicing=false`; loadable `config/invoicing.php` shape; module manifest with `bootstrap_sql` → `database/schema/modules/invoicing.sql` (file may not exist yet until Task 4 — config test only asserts manifest keys).

- [ ] **Step 1: Write failing config + purity tests**

Create `tests/Invoicing/InvoicingConfigTest.php`:

```php
<?php
declare(strict_types=1);

test('config/modules/invoicing.php es un manifiesto válido', function (): void {
    $m = require ROOT_PATH . '/config/modules/invoicing.php';
    assert_same('invoicing', $m['clave']);
    assert_same(false, $m['obligatorio']);
    assert_same('database/schema/modules/invoicing.sql', $m['bootstrap_sql']);
});

test('config/invoicing.php declara provider facturapi deshabilitado por defecto', function (): void {
    $cfg = require ROOT_PATH . '/config/invoicing.php';
    assert_same(false, $cfg['providers']['facturapi']['enabled'] ?? null);
    assert_same('facturapi', $cfg['default'] ?? null);
});

test('vertical harness tiene invoicing OFF', function (): void {
    $v = require ROOT_PATH . '/config/vertical.php';
    assert_same(false, $v['modules']['invoicing'] ?? null);
});
```

In `tests/Kernel/SkeletonPurityTest.php`, extend the existing vertical test:

```php
test('skeleton vertical keeps marketing, payments and invoicing OFF', function () use ($skeleton): void {
    $vertical = require $skeleton . '/config/vertical.php';
    assert_same(false, $vertical['modules']['marketing'] ?? null);
    assert_same(false, $vertical['modules']['payments'] ?? null);
    assert_same(false, $vertical['modules']['invoicing'] ?? null);
});
```

Rename/replace the old test name `skeleton vertical keeps marketing and payments OFF` so there is a single test (delete the old function to avoid duplicate).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Invoicing/InvoicingConfig`
Expected: FAIL (missing `config/modules/invoicing.php` or key `invoicing`).

Run: `php tests/run.php Kernel/SkeletonPurity`
Expected: FAIL on missing `invoicing` key (or assertion false).

- [ ] **Step 3: Implement stubs + composer bump**

`composer.json` — set:

```json
"require": {
    "php": ">=8.2",
    "dompdf/dompdf": "^3.1",
    "phpmailer/phpmailer": "^7.1",
    "stripe/stripe-php": "^16.0",
    "facturapi/facturapi-php": "^4.0"
}
```

`skeleton/composer.json` — set `"php": ">=8.2"`.

`config/vertical.php` and `skeleton/config/vertical.php` — add `'invoicing' => false` next to `'payments' => false`.

Create `config/invoicing.php` and identical `skeleton/config/invoicing.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Kernel\EnvLoader;

return [
    'providers' => [
        'facturapi' => [
            'driver'  => 'facturapi',
            'enabled' => (bool) EnvLoader::get('FACTURAPI_ENABLED', false),
            'config'  => [
                'secret_key' => EnvLoader::get('FACTURAPI_SECRET_KEY', ''),
                'mode'       => EnvLoader::get('FACTURAPI_MODE', 'test'),
            ],
        ],
    ],
    'default' => EnvLoader::get('INVOICING_DEFAULT_PROVIDER', 'facturapi'),
];
```

Create `config/modules/invoicing.php` and `skeleton/config/modules/invoicing.php`:

```php
<?php
declare(strict_types=1);

return [
    'clave'         => 'invoicing',
    'nombre'        => 'Facturación',
    'descripcion'   => 'Puerto de facturación electrónica CFDI (Facturapi v1, tipo I).',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/invoicing.sql',
    'cruds'         => [],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
```

Append to `.env.example` (harness) and `skeleton/.env.example`:

```env
# ── Invoicing — Facturapi (CFDI) ──
FACTURAPI_ENABLED=false
FACTURAPI_SECRET_KEY=
FACTURAPI_MODE=test
INVOICING_DEFAULT_PROVIDER=facturapi
```

Run `composer update facturapi/facturapi-php --with-all-dependencies` (or `composer install`) so lockfile includes the SDK.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/InvoicingConfig`
Expected: PASS

Run: `php tests/run.php Kernel/SkeletonPurity`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock skeleton/composer.json \
  config/invoicing.php skeleton/config/invoicing.php \
  config/modules/invoicing.php skeleton/config/modules/invoicing.php \
  config/vertical.php skeleton/config/vertical.php \
  .env.example skeleton/.env.example \
  tests/Invoicing/InvoicingConfigTest.php tests/Kernel/SkeletonPurityTest.php
git commit -m "feat(invoicing): PHP 8.2 floor, Facturapi dep, vertical OFF stubs"
```

---

### Task 2: Domain value objects, enums, exceptions

**Files:**
- Create: `src/Domain/Invoicing/ValueObjects/Money.php`
- Create: `src/Domain/Invoicing/ValueObjects/Address.php`
- Create: `src/Domain/Invoicing/ValueObjects/FiscalCustomer.php`
- Create: `src/Domain/Invoicing/ValueObjects/InvoiceItem.php`
- Create: `src/Domain/Invoicing/ValueObjects/InvoiceDraft.php`
- Create: `src/Domain/Invoicing/ValueObjects/IssuedInvoice.php`
- Create: `src/Domain/Invoicing/ValueObjects/InvoiceCancellation.php`
- Create: `src/Domain/Invoicing/ValueObjects/OrganizationSettings.php`
- Create: `src/Domain/Invoicing/InvoiceStatus.php`
- Create: `src/Domain/Invoicing/PaymentForm.php`
- Create: `src/Domain/Invoicing/CfdiUse.php`
- Create: `src/Domain/Invoicing/Exceptions/InvoiceSourceNotFound.php`
- Create: `src/Domain/Invoicing/Exceptions/InvoiceDraftInvalid.php`
- Create: `src/Domain/Invoicing/Exceptions/InvoiceProviderException.php`
- Create: `src/Domain/Invoicing/Exceptions/InvoiceAlreadyProcessed.php`
- Create: `src/Domain/Invoicing/Exceptions/InvoiceNotCancellable.php`
- Test: `tests/Invoicing/InvoiceValueObjectsTest.php`

**Interfaces:**
- Consumes: Task 1 harness.
- Produces: immutable VOs/enums used by ports and Application.

- [ ] **Step 1: Write failing VO tests**

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\PaymentForm;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Address;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;

test('Money fromMajor MXN convierte a minor units', function (): void {
    $m = Money::fromMajor(1375.99, 'MXN');
    assert_same(137599, $m->amountMinor());
    assert_same('MXN', $m->currency());
});

test('Money rechaza currency distinta de MXN en v1', function (): void {
    assert_throws(\InvalidArgumentException::class, fn () => Money::fromMajor(10, 'USD'));
});

test('InvoiceDraft expone customer items paymentForm y defaults', function (): void {
    $customer = new FiscalCustomer(
        legalName: 'ACME SA DE CV',
        taxId: 'AAA010101AAA',
        taxSystem: '601',
        address: new Address(zip: '06600'),
        email: 'billing@acme.test',
    );
    $item = new InvoiceItem(
        quantity: 1.0,
        description: 'Servicio',
        productKey: '80101500',
        unitPrice: Money::fromMajor(100.0, 'MXN'),
        unitKey: 'E48',
    );
    $draft = new InvoiceDraft(
        sourceRef: 'order-1',
        customer: $customer,
        items: [$item],
        paymentForm: PaymentForm::TransferenciaElectronica,
    );
    assert_same('order-1', $draft->sourceRef());
    assert_same(CfdiUse::GastosGeneral, $draft->cfdiUse());
    assert_same('MXN', $draft->currency());
    assert_same(1, count($draft->items()));
    assert_same(InvoiceStatus::Valid, InvoiceStatus::fromProvider('valid'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Invoicing/InvoiceValueObjects`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement Domain types**

`PaymentForm` (backed string enum — SAT codes used in scaffold):

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

enum PaymentForm: string
{
    case Efectivo = '01';
    case TransferenciaElectronica = '03';
    case TarjetaCredito = '04';
    case TarjetaDebito = '28';
    case PorDefinir = '99';
}
```

`CfdiUse`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

enum CfdiUse: string
{
    case GastosGeneral = 'G01';
    case SinEfectosFiscales = 'S01';
}
```

`InvoiceStatus`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Valid = 'valid';
    case Canceled = 'canceled';

    public static function fromProvider(string $raw): self
    {
        $normalized = strtolower(trim($raw));
        return match ($normalized) {
            'draft' => self::Draft,
            'pending' => self::Pending,
            'valid' => self::Valid,
            'canceled', 'cancelled' => self::Canceled,
            default => self::Pending,
        };
    }
}
```

`Money` (local VO; uppercase `MXN`):

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class Money
{
    private string $currency;

    public function __construct(
        private int $amountMinor,
        string $currency,
    ) {
        $normalized = strtoupper($currency);
        if ($normalized !== 'MXN') {
            throw new \InvalidArgumentException('v1 invoicing only supports MXN currency');
        }
        $this->currency = $normalized;
    }

    public static function fromMajor(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function amountMinor(): int { return $this->amountMinor; }
    public function amountMajor(): float { return $this->amountMinor / 100; }
    public function currency(): string { return $this->currency; }
}
```

`Address`:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class Address
{
    public function __construct(
        private string $zip,
        private string $country = 'MEX',
        private ?string $street = null,
    ) {
        if (trim($zip) === '') {
            throw new \InvalidArgumentException('Address.zip is required');
        }
    }

    public function zip(): string { return $this->zip; }
    public function country(): string { return $this->country; }
    public function street(): ?string { return $this->street; }
}
```

`FiscalCustomer`, `InvoiceItem`, `InvoiceDraft`, `IssuedInvoice`, `InvoiceCancellation`, `OrganizationSettings` — constructors with getters; `InvoiceDraft` defaults `cfdiUse=CfdiUse::GastosGeneral`, `currency='MXN'`, `metadata=[]`; `IssuedInvoice` holds `providerInvoiceId`, `uuid`, `status: InvoiceStatus`, optional `folioNumber`, `sourceRef`, `pdfUrl`, `xmlUrl`, `meta: array`.

Exceptions — each `final class X extends \RuntimeException` in `Exceptions/`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/InvoiceValueObjects`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Invoicing tests/Invoicing/InvoiceValueObjectsTest.php
git commit -m "feat(invoicing): domain VOs, enums, and exceptions"
```

---

### Task 3: Domain ports (interfaces)

**Files:**
- Create: `src/Domain/Invoicing/InvoiceProviderInterface.php`
- Create: `src/Domain/Invoicing/InvoiceableSourceInterface.php`
- Create: `src/Domain/Invoicing/InvoiceEventLogRepositoryInterface.php`
- Create: `src/Domain/Invoicing/OrganizationSettingsRepositoryInterface.php`
- Test: `tests/Invoicing/InvoicePortsTest.php`

**Interfaces:**
- Consumes: Task 2 VOs.
- Produces: exact method signatures for Infrastructure/Application.

- [ ] **Step 1: Write failing reflection test**

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface;

test('InvoiceProviderInterface declara operaciones scaffold CFDI I', function (): void {
    $ref = new ReflectionClass(InvoiceProviderInterface::class);
    foreach (['key', 'createInvoice', 'cancelInvoice', 'downloadPdf', 'downloadXml', 'sendByEmail'] as $m) {
        assert_true($ref->hasMethod($m), "missing {$m}");
    }
});

test('InvoiceableSourceInterface expone findDraft', function (): void {
    assert_true((new ReflectionClass(InvoiceableSourceInterface::class))->hasMethod('findDraft'));
});

test('InvoiceEventLogRepositoryInterface expone claim markIssued y lookups', function (): void {
    $ref = new ReflectionClass(InvoiceEventLogRepositoryInterface::class);
    foreach (['hasProcessed', 'tryClaim', 'releaseClaim', 'markIssued', 'findByIdempotencyKey', 'findBySourceRef'] as $m) {
        assert_true($ref->hasMethod($m), "missing {$m}");
    }
});

test('OrganizationSettingsRepositoryInterface expone get y upsert', function (): void {
    $ref = new ReflectionClass(OrganizationSettingsRepositoryInterface::class);
    assert_true($ref->hasMethod('get'));
    assert_true($ref->hasMethod('upsert'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Invoicing/InvoicePorts`
Expected: FAIL (interfaces missing).

- [ ] **Step 3: Implement interfaces**

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

interface InvoiceProviderInterface
{
    public function key(): string;

    public function createInvoice(InvoiceDraft $draft): IssuedInvoice;

    public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice;

    public function downloadPdf(string $providerInvoiceId): string;

    public function downloadXml(string $providerInvoiceId): string;

    public function sendByEmail(string $providerInvoiceId, string $email): void;
}
```

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;

interface InvoiceableSourceInterface
{
    public function findDraft(string $sourceRef): ?InvoiceDraft;
}
```

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

interface InvoiceEventLogRepositoryInterface
{
    public function hasProcessed(string $provider, string $idempotencyKey): bool;

    /**
     * @param array<string, mixed> $meta
     */
    public function tryClaim(
        string $provider,
        string $idempotencyKey,
        string $sourceRef,
        string $type,
        array $meta = [],
    ): bool;

    public function releaseClaim(string $provider, string $idempotencyKey): void;

    public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void;

    public function findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice;

    public function findBySourceRef(string $sourceRef): ?IssuedInvoice;
}
```

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\OrganizationSettings;

interface OrganizationSettingsRepositoryInterface
{
    public function get(string $providerKey): ?OrganizationSettings;

    public function upsert(OrganizationSettings $settings): void;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/InvoicePorts`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Invoicing tests/Invoicing/InvoicePortsTest.php
git commit -m "feat(invoicing): domain ports for provider, source, and ledger"
```

---

### Task 4: Platform SQL `inv_*`

**Files:**
- Create: `database/schema/modules/invoicing.sql`
- Test: `tests/Invoicing/InvoicingSchemaTest.php`

**Interfaces:**
- Consumes: Task 1 manifest `bootstrap_sql`.
- Produces: idempotent DDL for `inv_events` and `inv_organizations`.

- [ ] **Step 1: Write failing schema test**

```php
<?php
declare(strict_types=1);

test('invoicing bootstrap SQL crea inv_events e inv_organizations', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/invoicing.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `inv_events`'));
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `inv_organizations`'));
    assert_true(str_contains($sql, 'UNIQUE KEY `uq_inv_events_provider_idempotency`'));
    assert_true(str_contains($sql, 'UNIQUE KEY `uq_inv_organizations_provider`'));
    assert_true(! str_contains($sql, 'dom_'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Invoicing/InvoicingSchema`
Expected: FAIL (file missing).

- [ ] **Step 3: Write SQL**

```sql
-- database/schema/modules/invoicing.sql
-- Bootstrap del módulo Invoicing (plataforma). Idempotente.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `inv_events` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`             VARCHAR(40)     NOT NULL,
  `idempotency_key`      VARCHAR(190)    NOT NULL,
  `source_ref`           VARCHAR(64)     DEFAULT NULL,
  `type`                 VARCHAR(60)     NOT NULL,
  `provider_invoice_id`  VARCHAR(190)    DEFAULT NULL,
  `uuid`                 VARCHAR(50)     DEFAULT NULL,
  `folio_number`         VARCHAR(40)     DEFAULT NULL,
  `status`               VARCHAR(40)     DEFAULT NULL,
  `meta`                 JSON            DEFAULT NULL,
  `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_events_provider_idempotency` (`provider`, `idempotency_key`),
  KEY `idx_inv_events_source_ref` (`source_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inv_organizations` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_key`     VARCHAR(40)     NOT NULL,
  `external_org_id`  VARCHAR(190)    DEFAULT NULL,
  `mode`             VARCHAR(16)     NOT NULL DEFAULT 'test',
  `label`            VARCHAR(120)    DEFAULT NULL,
  `meta`             JSON            DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_organizations_provider` (`provider_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/InvoicingSchema`
Expected: PASS

Also re-run: `php tests/run.php Invoicing/InvoicingConfig`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/schema/modules/invoicing.sql tests/Invoicing/InvoicingSchemaTest.php
git commit -m "feat(invoicing): platform SQL inv_events and inv_organizations"
```

---

### Task 5: PDO repositories + in-memory doubles

**Files:**
- Create: `src/Infrastructure/Invoicing/PdoInvoiceEventLogRepository.php`
- Create: `src/Infrastructure/Invoicing/PdoOrganizationSettingsRepository.php`
- Test: `tests/Invoicing/InvoiceEventLogClaimDoubleTest.php`
- Test: `tests/Invoicing/PdoInvoiceReposReflectionTest.php`

**Interfaces:**
- Consumes: Task 3 ports, Task 2 `IssuedInvoice` / `OrganizationSettings`.
- Produces: PDO impls + `InMemoryInvoiceEventLog` in the claim test file for later Application tests to copy/reuse (keep the in-memory class in the test file; Application tests in Task 8 may duplicate a small stub or require the same file’s class — put the in-memory helper in `tests/Invoicing/Support/InMemoryInvoiceEventLog.php` so Task 8 can `require` it… **better:** define `tests/Invoicing/Support/InMemoryInvoiceEventLog.php` and load it from tests that need it via `require_once`).

- [ ] **Step 1: Write failing tests**

`tests/Invoicing/PdoInvoiceReposReflectionTest.php`:

```php
<?php
declare(strict_types=1);

test('PdoInvoiceEventLogRepository implementa el puerto de ledger', function (): void {
    $ref = new ReflectionClass(\Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository::class);
    assert_true($ref->implementsInterface(\Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class));
});

test('PdoOrganizationSettingsRepository implementa el puerto de org settings', function (): void {
    $ref = new ReflectionClass(\Lebytek\Framework\Infrastructure\Invoicing\PdoOrganizationSettingsRepository::class);
    assert_true($ref->implementsInterface(\Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface::class));
});
```

`tests/Invoicing/Support/InMemoryInvoiceEventLog.php` + claim tests mirroring Payments (store rows with optional `IssuedInvoice` after `markIssued`; `findByIdempotencyKey` returns issued only when `providerInvoiceId` set).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Invoicing/PdoInvoiceRepos`
Expected: FAIL

- [ ] **Step 3: Implement PDO repos**

`PdoInvoiceEventLogRepository`:
- `tryClaim`: `INSERT INTO inv_events (provider, idempotency_key, source_ref, type, meta)` — on duplicate key return `false` (same SQLSTATE handling as `PdoPaymentEventLogRepository`).
- `releaseClaim`: `DELETE FROM inv_events WHERE provider=? AND idempotency_key=?`
- `markIssued`: `UPDATE … SET provider_invoice_id, uuid, folio_number, status, meta, updated_at`
- `findByIdempotencyKey` / `findBySourceRef`: map row → `IssuedInvoice` only if `provider_invoice_id` IS NOT NULL; `findBySourceRef` orders `id DESC LIMIT 1`.

`PdoOrganizationSettingsRepository`:
- `get`: SELECT by `provider_key`
- `upsert`: `INSERT … ON DUPLICATE KEY UPDATE`

Use `Lebytek\Framework\Kernel\Database\Connection::getInstance()` like Payments.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing`
Expected: claim double + reflection PASS (Application tests not yet present).

- [ ] **Step 5: Commit**

```bash
git add src/Infrastructure/Invoicing tests/Invoicing
git commit -m "feat(invoicing): PDO event log and organization settings repos"
```

---

### Task 6: Factory + registry

**Files:**
- Create: `src/Application/Invoicing/InvoiceProviderRegistry.php`
- Create: `src/Application/Invoicing/InvoicingFactory.php`
- Test: `tests/Invoicing/InvoicingFactoryTest.php`
- Test: `tests/Invoicing/InvoiceProviderRegistryTest.php`

**Interfaces:**
- Consumes: `InvoiceProviderInterface`; will construct `FacturapiInvoiceProvider` (Task 7). For Task 6 only, factory `match` may throw `RuntimeException` for `facturapi` until Task 7 lands — **prefer implementing registry first with injectable definitions, then factory in same task after a minimal stub provider OR complete Task 7 before enabling factory match.**

**Order inside this task:** implement registry + tests with a fake provider closure; factory builds real `FacturapiInvoiceProvider` only after Task 7. **Split:** Task 6 = registry + factory structure that references `FacturapiInvoiceProvider::class` — if class missing, combine factory wiring into Task 7. **Resolved:** Task 6 implements registry + factory with `match` → `new FacturapiInvoiceProvider($cfg)` and Task 7 implements that class first if needed.

Adjust: **Task 6 implements registry only + tests with fake.** **Task 7 implements Facturapi provider + completes InvoicingFactory.**

- [ ] **Step 1: Write failing registry test**

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

final class FakeInvoiceProvider implements InvoiceProviderInterface
{
    public function key(): string { return 'fake'; }
    public function createInvoice(InvoiceDraft $draft): IssuedInvoice { throw new \RuntimeException('unused'); }
    public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice { throw new \RuntimeException('unused'); }
    public function downloadPdf(string $providerInvoiceId): string { return '%PDF'; }
    public function downloadXml(string $providerInvoiceId): string { return '<xml/>'; }
    public function sendByEmail(string $providerInvoiceId, string $email): void {}
}

test('InvoiceProviderRegistry resuelve lazy por key', function (): void {
    $reg = new InvoiceProviderRegistry([
        'fake' => [
            'driver' => 'fake',
            'factory' => static fn (): InvoiceProviderInterface => new FakeInvoiceProvider(),
        ],
    ]);
    assert_true($reg->has('fake'));
    assert_same('fake', $reg->get('fake')->key());
    assert_throws(\RuntimeException::class, fn () => $reg->get('missing'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Invoicing/InvoiceProviderRegistry`
Expected: FAIL

- [ ] **Step 3: Implement `InvoiceProviderRegistry`**

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;

final class InvoiceProviderRegistry
{
    /** @var array<string, InvoiceProviderInterface> */
    private array $resolved = [];

    /**
     * @param array<string, array{driver:string, factory:callable():InvoiceProviderInterface}> $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    public function has(string $providerKey): bool
    {
        return isset($this->definitions[$providerKey]);
    }

    public function get(string $providerKey): InvoiceProviderInterface
    {
        if (!$this->has($providerKey)) {
            throw new \RuntimeException("Proveedor de facturación no registrado: {$providerKey}");
        }
        if (!isset($this->resolved[$providerKey])) {
            $factory = $this->definitions[$providerKey]['factory'];
            $this->resolved[$providerKey] = $factory();
        }
        return $this->resolved[$providerKey];
    }

    public function driver(string $providerKey): string
    {
        return (string) ($this->definitions[$providerKey]['driver'] ?? 'unknown');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/InvoiceProviderRegistry`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Application/Invoicing/InvoiceProviderRegistry.php tests/Invoicing/InvoiceProviderRegistryTest.php
git commit -m "feat(invoicing): invoice provider registry"
```

---

### Task 7: Facturapi adapter + factory

**Files:**
- Create: `src/Infrastructure/Invoicing/Facturapi/FacturapiTransportInterface.php`
- Create: `src/Infrastructure/Invoicing/Facturapi/SdkFacturapiTransport.php`
- Create: `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php`
- Create: `src/Application/Invoicing/InvoicingFactory.php`
- Test: `tests/Invoicing/FacturapiInvoiceProviderTest.php`
- Test: `tests/Invoicing/InvoicingFactoryTest.php`

**Interfaces:**
- Consumes: `InvoiceProviderInterface`, VOs, registry.
- Produces: working `FacturapiInvoiceProvider` + `InvoicingFactory::registry()`.

- [ ] **Step 1: Write failing provider + factory tests**

Transport seam:

```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing\Facturapi;

interface FacturapiTransportInterface
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createInvoice(array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function cancelInvoice(string $invoiceId, array $payload): array;

    public function downloadPdf(string $invoiceId): string;

    public function downloadXml(string $invoiceId): string;

    public function sendByEmail(string $invoiceId, ?string $email = null): void;
}
```

Test with a fake transport that returns a fixed invoice array:

```php
$transport = new class implements FacturapiTransportInterface {
    public function createInvoice(array $payload): array {
        return [
            'id' => 'inv_test_1',
            'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
            'folio_number' => 12,
            'status' => 'valid',
        ];
    }
    public function cancelInvoice(string $invoiceId, array $payload): array {
        return ['id' => $invoiceId, 'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE', 'status' => 'canceled'];
    }
    public function downloadPdf(string $invoiceId): string { return '%PDF-fake'; }
    public function downloadXml(string $invoiceId): string { return '<cfdi/>'; }
    public function sendByEmail(string $invoiceId, ?string $email = null): void {}
};
$provider = new FacturapiInvoiceProvider(['secret_key' => 'sk_test', 'mode' => 'test'], $transport);
$issued = $provider->createInvoice($validDraft);
assert_same('inv_test_1', $issued->providerInvoiceId());
assert_same('facturapi', $provider->key());
```

Factory test: `buildProviders` skips disabled; throws on unknown driver; enabled `facturapi` returns registry with key.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Invoicing/FacturapiInvoiceProvider`
Expected: FAIL

- [ ] **Step 3: Implement adapter + factory**

`SdkFacturapiTransport` wraps `new \Facturapi\Facturapi($apiKey)` and maps:
- `Invoices->create($payload)` → array via json encode/decode or `(array)` casting of object
- `Invoices->cancel($id, $payload)`
- `Invoices->downloadPdf` / `downloadXml`
- `Invoices->sendByEmail($id, $email ? ['email' => $email] : [])`

`FacturapiInvoiceProvider`:
- ctor `(array $config, ?FacturapiTransportInterface $transport = null)`
- Map `InvoiceDraft` → Facturapi payload with **inline** customer + inline product (no pre-created IDs):

```php
[
  'customer' => [
    'legal_name' => ...,
    'tax_id' => ...,
    'tax_system' => ...,
    'email' => ...,
    'address' => ['zip' => ..., 'country' => ...],
  ],
  'items' => [[
    'quantity' => ...,
    'product' => [
      'description' => ...,
      'product_key' => ...,
      'price' => $item->unitPrice()->amountMajor(),
      'unit_key' => ...,
    ],
  ]],
  'payment_form' => $draft->paymentForm()->value,
  'use' => $draft->cfdiUse()->value,
  'currency' => $draft->currency(),
]
```

Catch `\Facturapi\Exceptions\FacturapiException` → `InvoiceProviderException`.

`InvoicingFactory` — mirror `PaymentsFactory` (`resetCached`, `registry`, `buildProviders`) with driver `facturapi` only; on build, if `OrganizationSettingsRepositoryInterface` is not injected, **skip** org upsert here (wire upsert in container Task 10 via optional call). Factory only builds providers.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/FacturapiInvoiceProvider`
Run: `php tests/run.php Invoicing/InvoicingFactory`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Infrastructure/Invoicing src/Application/Invoicing/InvoicingFactory.php tests/Invoicing
git commit -m "feat(invoicing): Facturapi provider adapter and factory"
```

---

### Task 8: `IssueInvoiceFromSource` + draft validation

**Files:**
- Create: `src/Application/Invoicing/InvoiceDraftValidator.php`
- Create: `src/Application/Invoicing/IssueInvoiceFromSource.php`
- Test: `tests/Invoicing/IssueInvoiceFromSourceTest.php`

**Interfaces:**
- Consumes: `InvoiceableSourceInterface`, `InvoiceEventLogRepositoryInterface`, `InvoiceProviderRegistry`, exceptions, idempotency rules from spec.
- Produces: `IssueInvoiceFromSource::handle(string $sourceRef, string $idempotencyKey, ?string $providerKey = null): IssuedInvoice`

- [ ] **Step 1: Write failing orchestration tests**

Cover:
1. Happy path: source returns draft → provider create → markIssued → IssuedInvoice
2. Source null → `InvoiceSourceNotFound`
3. Invalid draft (empty taxId) → `InvoiceDraftInvalid` + `releaseClaim`
4. Second call same idempotency key after success → returns previous IssuedInvoice (no second create)
5. Claim exists without provider_invoice_id → `InvoiceAlreadyProcessed`

Use `InMemoryInvoiceEventLog` from Support + Fake provider that counts `createInvoice` calls.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Invoicing/IssueInvoiceFromSource`
Expected: FAIL

- [ ] **Step 3: Implement validator + use case**

`InvoiceDraftValidator::validate(InvoiceDraft $draft): void` throws `InvoiceDraftInvalid` if:
- `taxId` empty or shorter than 12 chars
- `zip` empty
- `items` empty
- any item missing `productKey` or non-positive quantity
- `legalName` empty

`IssueInvoiceFromSource` algorithm (exact):

```php
public function handle(string $sourceRef, string $idempotencyKey, ?string $providerKey = null): IssuedInvoice
{
    $providerKey ??= /* default from Config::get('invoicing.default', 'facturapi') */;
    $provider = $this->registry->get($providerKey);
    $claimed = $this->events->tryClaim($provider->key(), $idempotencyKey, $sourceRef, 'issue');
    if (!$claimed) {
        $existing = $this->events->findByIdempotencyKey($provider->key(), $idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }
        throw new InvoiceAlreadyProcessed('Invoice claim in progress or incomplete for key ' . $idempotencyKey);
    }
    try {
        $draft = $this->source->findDraft($sourceRef);
        if ($draft === null) {
            throw new InvoiceSourceNotFound('No invoiceable source for ref ' . $sourceRef);
        }
        $this->validator->validate($draft);
        $issued = $provider->createInvoice($draft);
        $this->events->markIssued($provider->key(), $idempotencyKey, $issued);
        return $issued;
    } catch (\Throwable $e) {
        $this->events->releaseClaim($provider->key(), $idempotencyKey);
        throw $e;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/IssueInvoiceFromSource`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Application/Invoicing tests/Invoicing/IssueInvoiceFromSourceTest.php tests/Invoicing/Support
git commit -m "feat(invoicing): IssueInvoiceFromSource with idempotent claims"
```

---

### Task 9: Cancel, download, send use cases

**Files:**
- Create: `src/Application/Invoicing/CancelIssuedInvoice.php`
- Create: `src/Application/Invoicing/DownloadInvoiceDocument.php`
- Create: `src/Application/Invoicing/SendInvoiceByEmail.php`
- Test: `tests/Invoicing/InvoiceScaffoldUseCasesTest.php`

**Interfaces:**
- Consumes: registry, event log, `InvoiceCancellation`.
- Produces:
  - `CancelIssuedInvoice::handle(?string $providerInvoiceId, ?string $sourceRef, InvoiceCancellation $cancellation, ?string $providerKey = null): IssuedInvoice`
  - `DownloadInvoiceDocument::handle(string $format, ?string $providerInvoiceId, ?string $sourceRef, ?string $providerKey = null): string` where `$format` is `pdf`|`xml`
  - `SendInvoiceByEmail::handle(string $email, ?string $providerInvoiceId, ?string $sourceRef, ?string $providerKey = null): void`

Resolution helper (private or shared small class `InvoiceIdResolver`): if `providerInvoiceId` null, require `sourceRef` and `findBySourceRef`; if still null throw `InvoiceSourceNotFound`.

Cancel flow (explicit): resolve id → `provider->cancelInvoice` → `tryClaim(provider, 'cancel:'.$id, sourceRef ?: $id, 'cancel')`; if claim succeeds, `markIssued` with canceled `IssuedInvoice`; if claim is false, ignore (audit best-effort). Do not `releaseClaim` on cancel audit failure.

- [ ] **Step 1: Write failing tests** for cancel→canceled status, pdf bytes, xml bytes, sendByEmail invokes transport/provider.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Invoicing/InvoiceScaffoldUseCases`
Expected: FAIL

- [ ] **Step 3: Implement the three use cases**

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/InvoiceScaffoldUseCases`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Application/Invoicing tests/Invoicing/InvoiceScaffoldUseCasesTest.php
git commit -m "feat(invoicing): cancel, download, and email scaffold use cases"
```

---

### Task 10: Container bindings + org settings sync

**Files:**
- Modify: `config/container.php`
- Modify: `skeleton/config/container.php`
- Create: `src/Application/Invoicing/SyncOrganizationSettingsFromConfig.php` (optional thin)
- Test: `tests/Invoicing/InvoicingContainerBindingsTest.php`

**Interfaces:**
- Consumes: factory, repos, use cases, `InvoiceableSourceInterface` **not** bound in Framework harness (consumer binds it). Framework binds:
  - `InvoiceProviderRegistry`
  - `InvoiceEventLogRepositoryInterface` → PDO
  - `OrganizationSettingsRepositoryInterface` → PDO
  - `IssueInvoiceFromSource`, `CancelIssuedInvoice`, `DownloadInvoiceDocument`, `SendInvoiceByEmail` only if a source is bound — **problem:** IssueInvoiceFromSource needs a source.

**Resolved approach:** Framework container binds registry + repos + use cases that do **not** need source. For `IssueInvoiceFromSource`, bind only when consumer registers `InvoiceableSourceInterface`. In harness `config/container.php`, bind registry + repos; document that consumer must bind source + `IssueInvoiceFromSource`.

Alternatively bind `IssueInvoiceFromSource` with a `NullInvoiceableSource` that always returns null — **reject** (surprising). Prefer: bind registry + event log + org repo + cancel/download/send; leave `IssueInvoiceFromSource` and `InvoiceableSourceInterface` for consumer docs.

Also on registry resolution, upsert org settings from config mode:

```php
$org = new OrganizationSettings(
    providerKey: 'facturapi',
    externalOrgId: null,
    mode: (string) ($cfg['mode'] ?? 'test'),
    label: 'Facturapi',
    meta: [],
);
$orgRepo->upsert($org);
```

Call this inside the container singleton factory after building registry (read mode from `Config::get('invoicing.providers.facturapi.config.mode')`).

- [ ] **Step 1: Write failing test** that `config/container.php` source contains `vertical.modules.invoicing` and `InvoiceProviderRegistry::class`.

```php
test('harness container.php declara bloque invoicing gated', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/config/container.php');
    assert_true(str_contains($src, "vertical.modules.invoicing"));
    assert_true(str_contains($src, 'InvoiceProviderRegistry::class'));
    assert_true(str_contains($src, 'InvoiceEventLogRepositoryInterface::class'));
});

test('skeleton container.php declara bloque invoicing gated', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/skeleton/config/container.php');
    assert_true(str_contains($src, "vertical.modules.invoicing"));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Invoicing/InvoicingContainerBindings`
Expected: FAIL

- [ ] **Step 3: Add gated bindings** (mirror payments block) in harness + skeleton `container.php`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Invoicing/InvoicingContainerBindings`
Run: `php tests/run.php Kernel/SkeletonPurity`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/container.php skeleton/config/container.php \
  src/Application/Invoicing/SyncOrganizationSettingsFromConfig.php \
  tests/Invoicing/InvoicingContainerBindingsTest.php
git commit -m "feat(invoicing): gated container bindings and org settings sync"
```

---

### Task 11: Documentation

**Files:**
- Create: `docs/modules/modulo-invoicing.md`
- Modify: `docs/ARCHITECTURE-CONSUMER.md`
- Modify: `docs/core/table-prefix-convention.md`
- Modify: `docs/core/vertical-onboarding.md`
- Modify: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` (status → plan ready / implemented when done)
- Test: `tests/Invoicing/InvoicingDocsTest.php`

**Interfaces:**
- Consumes: all prior deliverables.
- Produces: consumer checklist to implement source → bind → enable vertical → emit in test mode.

- [ ] **Step 1: Write failing docs presence test**

```php
<?php
declare(strict_types=1);

test('modulo-invoicing.md documenta source bind y vertical', function (): void {
    $doc = (string) file_get_contents(ROOT_PATH . '/docs/modules/modulo-invoicing.md');
    assert_true(str_contains($doc, 'InvoiceableSourceInterface'));
    assert_true(str_contains($doc, 'vertical.modules.invoicing'));
    assert_true(str_contains($doc, 'FACTURAPI_SECRET_KEY'));
    assert_true(str_contains($doc, 'IssueInvoiceFromSource'));
});

test('ARCHITECTURE-CONSUMER menciona Invoicing', function (): void {
    $doc = (string) file_get_contents(ROOT_PATH . '/docs/ARCHITECTURE-CONSUMER.md');
    assert_true(str_contains($doc, 'Invoicing'));
});

test('table-prefix-convention documenta inv_', function (): void {
    $doc = (string) file_get_contents(ROOT_PATH . '/docs/core/table-prefix-convention.md');
    assert_true(str_contains($doc, '`inv_`'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Invoicing/InvoicingDocs`
Expected: FAIL

- [ ] **Step 3: Write docs**

`docs/modules/modulo-invoicing.md` must include:
1. What Framework owns vs consumer
2. Env vars table
3. Enable vertical + bootstrap SQL note
4. Minimal `InvoiceableSourceInterface` example class
5. `container.php` bindings for source + `IssueInvoiceFromSource`
6. Call sequence for test-mode emission
7. PHP ≥8.2 requirement

Update `ARCHITECTURE-CONSUMER.md` ownership table:

| Concern | Owner | Namespace / path |
| Invoicing generic (Facturapi, inv_* ledger, source port) | **Framework** | `Lebytek\Framework\Domain\Invoicing\`, OFF by default |
| Domain invoice entities, when-to-stamp rules, UI | **Consumer** | `App\…`, `dom_*` |

`table-prefix-convention.md` — add platform module prefixes section:

| Prefijo | Rol |
| `pay_` | Módulo Payments (p. ej. `pay_events`) |
| `inv_` | Módulo Invoicing (p. ej. `inv_events`, `inv_organizations`) |

`vertical-onboarding.md` §3 — mention optional modules `payments` / `invoicing` stay `false` until configured.

- [ ] **Step 4: Run full Invoicing suite + purity**

Run: `php tests/run.php Invoicing`
Expected: all PASS

Run: `php tests/run.php Kernel/SkeletonPurity`
Expected: PASS

Run: `php tests/run.php Payments`
Expected: PASS (no regressions)

- [ ] **Step 5: Commit**

```bash
git add docs/modules/modulo-invoicing.md docs/ARCHITECTURE-CONSUMER.md \
  docs/core/table-prefix-convention.md docs/core/vertical-onboarding.md \
  docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md \
  tests/Invoicing/InvoicingDocsTest.php
git commit -m "docs(invoicing): module guide and architecture ownership"
```

---

## Spec coverage checklist (self-review)

| Spec requirement | Task |
|------------------|------|
| Vertical OFF + config/env | 1 |
| PHP ≥8.2 + facturapi SDK | 1, 7 |
| Domain ports + VOs + exceptions | 2, 3 |
| `inv_events` / `inv_organizations` | 4, 5 |
| Facturapi create/cancel/pdf/xml/email | 7, 9 |
| `InvoiceableSourceInterface` + `IssueInvoiceFromSource` | 3, 8 |
| Idempotency rules | 5, 8 |
| Factory/registry | 6, 7 |
| Container gated bindings | 10 |
| Docs + prefix + ARCHITECTURE | 11 |
| CFDI I only / no dom_* / no webhooks | enforced by scope of tasks |
| Skeleton purity | 1, 10 |
| Tests `Invoicing/*` | 1–11 |

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-07T12:40:00Z (AUTOMATION-04) |
| Plan creado UTC | 2026-08-07 (PR #94 restructure) |
| Framework `origin/main` verificado | `da3ab58bd77be95c4003341454d939aa2584a742` |
| Tareas completadas / totales | **0 / 11** |
| Modo fuente | normal — spec `2026-08-07-invoicing-facturapi-design.md` (paralelo al audit CRUD del día) |
| Evidencia @ `da3ab58` | `composer.json` `"php": ">=8.1"` (sin bump 8.2); sin `facturapi/facturapi-php`; sin `src/Domain/Invoicing/`; sin `config/modules/invoicing.php`; sin `vertical.modules.invoicing`; sin `database/schema/modules/invoicing.sql` |
| Siguiente tarea ejecutable | **Task 1** — PHP 8.2 floor, Facturapi dep, vertical/config stubs |
| Prerrequisitos | Ninguno en Framework; bump PHP 8.2 es breaking para consumidores 8.1 — documentar en release |
| Bloqueos | Ninguno al planificar; emisión real Facturapi en prod requiere operador (keys VPS); Portal `dom_*` sources fuera de este repo |
| Estado | **Pendiente de implementación en main** |

## Deviations / notes for implementers

1. **`IssueInvoiceFromSource` is not auto-bound** without a consumer `InvoiceableSourceInterface` — documented in Task 10/11 (avoids Null source).
2. **Org cache v1** stores `mode` from env/config; `external_org_id` remains null until a future Organizations retrieve call.
3. **Inline customer/product** in Facturapi payload (no separate Customers/Products sync API in v1).
4. Cloud agents without PHP must install PHP ≥8.2 before running harness steps.
