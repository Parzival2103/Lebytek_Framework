# Membership Recurring, Dunning 48h, Conversion & Churn Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire demo→paid lead conversion and churn KPIs, make `dom_mkt_plantillas` the sole marketing-mail path, then add Stripe Subscriptions with 48h dunning (soft-cancel + permanent reactivation) without marking decline as churn.

**Architecture:** Portal owns membership state (`dom_mkt_membresias`), lead conversion columns, mail renderer, dunning jobs, and webhook side-effects. Framework `src/` only extends the payments port (subscription checkout + invoice webhook types) without membership rules. WhatsApiLebytek adds soft-cancel / reactivate commercial endpoints that revoke tokens and keep the Green instance. Delivery is layered: conversion → plantillas → snapshots → subscriptions → dunning → reactivate; **do not** ship dunning HTML as new PHP views.

**Tech Stack:** PHP 8.1+, Lebytek Onion monorepo (`app/` + `src/`), PDO, `stripe/stripe-php`, microtest (`php tests/run.php`), Pest (WhatsApiLebytek), CLI cron scripts under `scripts/`.

**Spec:** `docs/superpowers/specs/2026-07-15-membership-recurring-dunning-churn-design.md`

**Prerequisites (already shipped):**
- Payments fase 1: `PaymentGatewayInterface`, `StripeGateway` (mode `payment`), `pay_events`, `ConfirmarPagoStripeUseCase`, `ActivateMembershipFromOrderService`
- Manual membership: `dom_mkt_ordenes`, CRUD leads/órdenes
- Lead churn columns: `plan_slug`, `converted_at`, `cancelled_at`, `demo_expires_at`
- Api activate-plan: `commercial_status=active` + token rotate

## Scope note (one plan, six shippable phases)

This spec is one commercial epic with **sequential dependencies** (dunning **blocked** on plantillas). Keep a single plan; cut **PRs/commits by phase** (A→F). Do **not** start Phase E until Phase B is green.

| Phase | Shippable alone? | Depends on |
|-------|------------------|------------|
| A — Lead conversion + CRUD | Yes | Paid activate already in prod |
| B — Plantillas + renderer | Yes | — |
| C — Snapshot job | Yes | A (for real `converted_at` data) |
| D — Api soft-cancel / reactivate | Yes (api repo) | — |
| E — Stripe Subscriptions + `dom_mkt_membresias` | Yes | A, payments v1 |
| F — Dunning 48h + reactivation | No | B + D + E |

## Global Constraints

- Branch **`feature/membership-recurring-dunning`** cut from `feature/backoffice-api-integration` (Framework). Api work on a sibling branch from `main` in WhatsApiLebytek.
- **Do not** merge Framework `feature/backoffice-api-integration` → `main` unless the user explicitly orders it.
- **PHP 8.1** on VPS FPM — no `readonly class`, no enums backed features that need 8.2+ beyond what the repo already uses (`PaymentEventType` enum is already in tree).
- **No** membership/dunning rules inside `src/Domain/Payments/` or `StripeGateway` beyond normalizing webhook types and subscription checkout.
- **No** new marketing email PHP views for dunning — only claves `membership_payment_failed` / `membership_cancelled_reactivate` via `MarketingMailRenderer`.
- Soft-cancel = revoke client tokens + `commercial_status=cancelled`; **keep** Green instance and tenant row.
- Decline / `past_due` / grace **never** sets `cancelled_at` or counts as churn.
- Grace = **48 hours fixed** from failure; clicking retry without paying does **not** extend.
- Transferencia bancaria: no Stripe subscription / no auto-dunning (document gap; ops manual).
- Purchasable slugs remain **`starter`**, **`business`**, **`empresa`** (same as activate today).
- Currency v1 locked to **`mxn`**.
- Webhook: claim `pay_events` **before** side-effects; unsupported → `Ignored` → HTTP 200.
- `visible_when` is **equality-only** (`CrudActionDefinition::equalityMatches`); use `converted_at: ""` + `plan_slug: "demo"` (null stringifies to `""`).
- Only commit when the user asks.

## Named technical debt (do not implement here)

| Deuda | Acción post-v1 |
|-------|----------------|
| Lead `estado = convertido` | Optional short-term; UI uses `plan_slug`/`converted_at` |
| Transferencia recurrente automática | Follow-up proceso ops |
| Stripe Smart Retries beyond webhook align | Document later |
| WYSIWYG / A/B plantillas | Out of scope |
| Auth emails (`verificacion`/`recuperacion`) → plantillas | Stay in `src/` |
| Gate message-send on `commercial_status` in api | Optional hardening |
| Preview HTML in CRUD plantillas | Optional |

---

## File Structure

### Phase A — Lead conversion (Portal)

| Path | Role |
|------|------|
| `app/Domain/Marketing/Contracts/LeadRepositoryInterface.php` | Add `markConverted` / `markCancelled` |
| `app/Infrastructure/Marketing/PdoLeadRepository.php` | SQL for conversion / cancel |
| `app/Application/Marketing/ActivateMembershipFromOrderService.php` | Call `markConverted` after successful activate |
| `app/Application/Marketing/LeadApiDeprovisioningService.php` | Refuse if converted |
| `config/cruds/mkt_leads.json` | Hide `dar_baja_demo_api` when converted |
| `app/Infrastructure/Marketing/PdoChurnMetricsRepository.php` | `countActiveDemos` excludes converted |
| `tests/Marketing/LeadConversionOnActivateTest.php` | Conversion + hide deprovision |

### Phase B — Plantillas (Portal)

| Path | Role |
|------|------|
| `database/migrations/20260715200000_mkt_plantillas_unique_clave.sql` | UNIQUE(`clave`) |
| `database/migrations/20260715200100_mkt_plantillas_seed_catalog.sql` | Idempotent seed by clave |
| `config/modules/marketing.php` | Register migrations |
| `app/Domain/Marketing/Contracts/PlantillaRepositoryInterface.php` | `findByClave` |
| `app/Infrastructure/Marketing/PdoPlantillaRepository.php` | PDO impl |
| `app/Application/Marketing/MarketingMailRenderer.php` | Render + send by clave |
| `config/container.php` | Bind repo + renderer |
| Migrate callers: `AutoresponderHandler`, `LeadApiProvisioningService`, `ActivateMembershipFromOrderService` | Drop `ViewHelper::render('emails/…')` |
| `tests/Marketing/MarketingMailRendererTest.php` | Substitution + fallback |

### Phase C — Snapshots (Portal)

| Path | Role |
|------|------|
| `app/Application/Marketing/ComputeChurnSnapshotService.php` | Paid churn + demo conversion math |
| `scripts/compute-churn-snapshot.php` | Cron entry |
| `tests/Marketing/ComputeChurnSnapshotServiceTest.php` | KPI definitions |

### Phase D — Api soft-cancel (WhatsApiLebytek)

| Path | Role |
|------|------|
| `app/Services/CancelCommercialService.php` | Revoke tokens + `cancelled` |
| `app/Services/ReactivateCommercialService.php` | `active` + issue token |
| `app/Http/Controllers/Api/V1/TenantController.php` | New actions |
| `routes/api.php` | Routes |
| `docs/integration/waapi-api-contract.md` | Contract |
| `tests/Feature/Api/TenantCommercialLifecycleTest.php` | Feature tests |
| Portal: `LebytekApiClient::cancelCommercial` / `reactivateCommercial` | HTTP client |

### Phase E — Subscriptions + membresías (Framework src + Portal)

| Path | Role |
|------|------|
| `src/Domain/Payments/SupportsSubscriptions.php` | Real methods (fase 2) |
| `src/Domain/Payments/PaymentEventType.php` | `InvoicePaid`, `InvoicePaymentFailed` |
| `src/Domain/Payments/ValueObjects/PaymentEvent.php` | Optional subscription/invoice ids |
| `src/Infrastructure/Payments/StripeGateway.php` | Subscription checkout + invoice parse |
| `database/migrations/20260715210000_mkt_membresias.sql` | `dom_mkt_membresias` |
| `app/Domain/Marketing/Contracts/MembershipRepositoryInterface.php` | CRUD membresía |
| `app/Infrastructure/Marketing/PdoMembershipRepository.php` | PDO |
| `app/Application/Marketing/IniciarPagoStripeUseCase.php` | Branch subscription mode |
| `app/Application/Marketing/ConfirmarPagoStripeUseCase.php` | Handle invoice events + upsert membresía |
| `config/payments.php` / `.env.example` | Stripe Price IDs per plan/ciclo |

### Phase F — Dunning + reactivation (Portal)

| Path | Role |
|------|------|
| `app/Application/Marketing/StartMembershipGraceService.php` | past_due + correo #1 |
| `app/Application/Marketing/ExpireMembershipGraceService.php` | soft-cancel + correo #2 |
| `app/Application/Marketing/RecoverMembershipPaymentService.php` | Clear grace / reactivate |
| `scripts/expire-membership-grace.php` | Cron every 15–60 min |
| Public routes: retry / reactivate payment | Signed token URLs |
| `tests/Marketing/MembershipDunningTest.php` | Grace clock + churn rules |

---

### Task 1: Lead `markConverted` + activate wiring

**Files:**
- Modify: `app/Domain/Marketing/Contracts/LeadRepositoryInterface.php`
- Modify: `app/Infrastructure/Marketing/PdoLeadRepository.php`
- Modify: `app/Application/Marketing/ActivateMembershipFromOrderService.php`
- Test: `tests/Marketing/LeadConversionOnActivateTest.php`

**Interfaces:**
- Consumes: existing `MembershipOrderRepositoryInterface`, `LebytekApiClient::activatePlan`
- Produces: `LeadRepositoryInterface::markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void` — sets `plan_slug`, `converted_at = NOW()` only if null, `demo_expires_at = NULL`

- [ ] **Step 1: Write the failing test**

Create `tests/Marketing/LeadConversionOnActivateTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class ConvLeadRepo implements LeadRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    public int $markConvertedCalls = 0;

    public function guardar(LeadDraft $draft, ?array $emailVerification = null): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByEmailVerifyToken(string $token): ?array { return null; }
    public function incrementEmailVerifyAttempts(int $leadId): void {}
    public function markEmailVerified(int $leadId): void {}
    public function markApiProvisioned(int $leadId, string $tenantPublicId, string $externalRef, string $instancePublicId = '', ?int $paqueteId = null, string $planSlug = 'demo', int $demoDays = 30): void {}
    public function markApiProvisionError(int $leadId, string $error): void {}
    public function markApiDeprovisionInitiated(int $leadId): void {}
    public function markApiDeprovisionCompleted(int $leadId): void {}
    public function findDemosOlderThanDays(int $days): array { return []; }
    public function findDemosExpired(): array { return []; }
    public function findPendingDeprovisions(): array { return []; }
    public function findDemoPackageBySlug(string $slug): ?array { return null; }
    public function findLatestByEmail(string $email): ?array { return null; }

    public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void
    {
        $this->markConvertedCalls++;
        if (! isset($this->rows[$leadId])) {
            return;
        }
        if (($this->rows[$leadId]['converted_at'] ?? null) === null) {
            $this->rows[$leadId]['converted_at'] = '2026-07-15 12:00:00';
        }
        $this->rows[$leadId]['plan_slug'] = $planSlug;
        $this->rows[$leadId]['demo_expires_at'] = null;
        if ($paqueteId !== null) {
            $this->rows[$leadId]['paquete_id'] = $paqueteId;
        }
    }

    public function markCancelled(int $leadId): void
    {
        if (isset($this->rows[$leadId])) {
            $this->rows[$leadId]['cancelled_at'] = '2026-07-15 12:00:00';
        }
    }
}

final class ConvOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByPublicId(string $publicId): ?array { return null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void {}
    public function clearApiActivationError(int $orderId): void {}
    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->rows[$orderId]['status'] = 'paid';
    }
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class ConvTransport implements LebytekApiTransport
{
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        return ['status' => 200, 'body' => '{"token":"tok_plain","created":true}', 'error' => ''];
    }
}

final class ConvMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void {}
}

test('fromConfirmedPayment marca lead convertido con plan paid', function (): void {
    $leads = new ConvLeadRepo();
    $leads->rows[7] = [
        'id' => 7,
        'plan_slug' => 'demo',
        'converted_at' => null,
        'demo_expires_at' => '2026-08-01 00:00:00',
    ];
    $orders = new ConvOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORDCONV',
        'lead_id' => 7,
        'api_tenant_public_id' => '01TENANT',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'nombre' => 'Ana',
        'email' => 'ana@example.com',
        'precio_snapshot' => 499,
        'status' => 'pending_payment',
    ];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new ConvTransport());
    $svc = new ActivateMembershipFromOrderService($orders, $api, new ConvMailer(), $leads);

    $svc->fromConfirmedPayment($orders->rows[1], 0, ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORDCONV'));

    assert_same(1, $leads->markConvertedCalls);
    assert_same('starter', $leads->rows[7]['plan_slug']);
    assert_true($leads->rows[7]['converted_at'] !== null);
    assert_true($leads->rows[7]['demo_expires_at'] === null);
});

test('markConverted es idempotente si converted_at ya existe', function (): void {
    $leads = new ConvLeadRepo();
    $leads->rows[7] = [
        'id' => 7,
        'plan_slug' => 'starter',
        'converted_at' => '2026-07-01 10:00:00',
        'demo_expires_at' => null,
    ];
    $leads->markConverted(7, 'business');
    assert_same('2026-07-01 10:00:00', $leads->rows[7]['converted_at']);
    assert_same('business', $leads->rows[7]['plan_slug']);
});

test('activate sin lead_id no falla ni llama markConverted', function (): void {
    $leads = new ConvLeadRepo();
    $orders = new ConvOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JNLEAD',
        'lead_id' => null,
        'api_tenant_public_id' => '01TENANT',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'nombre' => 'Ana',
        'email' => 'ana@example.com',
        'precio_snapshot' => 499,
        'status' => 'pending_payment',
    ];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new ConvTransport());
    $svc = new ActivateMembershipFromOrderService($orders, $api, new ConvMailer(), $leads);
    $svc->fromConfirmedPayment($orders->rows[1], 0, 'key');
    assert_same(0, $leads->markConvertedCalls);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/LeadConversionOnActivateTest`

Expected: FAIL — `markConverted` missing on interface / wrong `ActivateMembershipFromOrderService` constructor arity.

- [ ] **Step 3: Write minimal implementation**

Add to `LeadRepositoryInterface`:

```php
public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void;

public function markCancelled(int $leadId): void;
```

`PdoLeadRepository::markConverted`:

```php
public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void
{
    $pdo = Connection::getInstance();
    $sql = 'UPDATE dom_mkt_leads SET
                plan_slug = :plan_slug,
                demo_expires_at = NULL,
                converted_at = COALESCE(converted_at, NOW())';
    if ($paqueteId !== null) {
        $sql .= ', paquete_id = :paquete_id';
    }
    $sql .= ' WHERE id = :id AND deleted = 0';
    $stmt = $pdo->prepare($sql);
    $params = ['plan_slug' => $planSlug, 'id' => $leadId];
    if ($paqueteId !== null) {
        $params['paquete_id'] = $paqueteId;
    }
    $stmt->execute($params);
}

public function markCancelled(int $leadId): void
{
    $pdo = Connection::getInstance();
    $stmt = $pdo->prepare(
        'UPDATE dom_mkt_leads SET cancelled_at = COALESCE(cancelled_at, NOW())
         WHERE id = :id AND deleted = 0'
    );
    $stmt->execute(['id' => $leadId]);
}
```

Update `ActivateMembershipFromOrderService` constructor to accept `LeadRepositoryInterface $leads` (4th dependency). After successful `activatePlan` in both `run` and `fromPaidRetry`, before/after email:

```php
$leadId = (int) ($order['lead_id'] ?? 0);
if ($leadId > 0) {
    $this->leads->markConverted($leadId, $slug);
} else {
    // ops: order without lead — conversion KPI skips; log if a logger is available
}
```

Update `config/container.php` binding for `ActivateMembershipFromOrderService` to inject `LeadRepositoryInterface`.

Update every test fake that implements `LeadRepositoryInterface` or constructs `ActivateMembershipFromOrderService` (at least `ActivateMembershipFromOrderServiceTest.php`, `AutorizarOrdenMembresiaUseCaseTest.php`, `ConfirmarPagoStripeUseCaseTest.php`) with stub `markConverted` / `markCancelled` and the new constructor arg.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Marketing/LeadConversionOnActivateTest`

Expected: PASS

Also: `php tests/run.php Marketing/ActivateMembershipFromOrderServiceTest` → PASS

- [ ] **Step 5: Commit** (only if user asks)

```bash
git add app/Domain/Marketing/Contracts/LeadRepositoryInterface.php \
  app/Infrastructure/Marketing/PdoLeadRepository.php \
  app/Application/Marketing/ActivateMembershipFromOrderService.php \
  config/container.php \
  tests/Marketing/LeadConversionOnActivateTest.php \
  tests/Marketing/ActivateMembershipFromOrderServiceTest.php \
  tests/Marketing/AutorizarOrdenMembresiaUseCaseTest.php \
  tests/Marketing/ConfirmarPagoStripeUseCaseTest.php
git commit -m "feat(marketing): mark lead converted on successful activate-plan"
```

---

### Task 2: Hide “Dar de baja demo” + server guard

**Files:**
- Modify: `config/cruds/mkt_leads.json`
- Modify: `app/Application/Marketing/LeadApiDeprovisioningService.php`
- Modify: `tests/Integration/LeadApiDeprovisioningServiceTest.php` (or new unit test with fake)

**Interfaces:**
- Consumes: `LeadRepositoryInterface::findById` (returns `converted_at`, `plan_slug`)
- Produces: deprovision throws if converted; CRUD action only when demo

- [ ] **Step 1: Write the failing test**

Add to a new or existing test file `tests/Marketing/LeadDeprovisionGuardTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Marketing\LeadApiDeprovisioningService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;

final class DepGuardLeads implements LeadRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function guardar(LeadDraft $draft, ?array $emailVerification = null): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByEmailVerifyToken(string $token): ?array { return null; }
    public function incrementEmailVerifyAttempts(int $leadId): void {}
    public function markEmailVerified(int $leadId): void {}
    public function markApiProvisioned(int $leadId, string $tenantPublicId, string $externalRef, string $instancePublicId = '', ?int $paqueteId = null, string $planSlug = 'demo', int $demoDays = 30): void {}
    public function markApiProvisionError(int $leadId, string $error): void {}
    public function markApiDeprovisionInitiated(int $leadId): void {}
    public function markApiDeprovisionCompleted(int $leadId): void {}
    public function findDemosOlderThanDays(int $days): array { return []; }
    public function findDemosExpired(): array { return []; }
    public function findPendingDeprovisions(): array { return []; }
    public function findDemoPackageBySlug(string $slug): ?array { return null; }
    public function findLatestByEmail(string $email): ?array { return null; }
    public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void {}
    public function markCancelled(int $leadId): void {}
}

final class DepGuardTransport implements LebytekApiTransport
{
    public int $calls = 0;
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls++;
        return ['status' => 200, 'body' => '{}', 'error' => ''];
    }
}

test('deprovision rechaza lead convertido', function (): void {
    $leads = new DepGuardLeads();
    $leads->rows[1] = [
        'id' => 1,
        'api_tenant_public_id' => '01T',
        'api_instance_public_id' => '01I',
        'api_lifecycle_status' => 'provisioned',
        'converted_at' => '2026-07-15 12:00:00',
        'plan_slug' => 'starter',
    ];
    $transport = new DepGuardTransport();
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $svc = new LeadApiDeprovisioningService($api, $leads);

    $threw = false;
    try {
        $svc->deprovisionLead(1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
        assert_true(str_contains($e->getMessage(), 'convertido') || str_contains($e->getMessage(), 'membresía'));
    }
    assert_true($threw);
    assert_same(0, $transport->calls);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/LeadDeprovisionGuardTest`

Expected: FAIL — deprovision still calls API / does not throw.

- [ ] **Step 3: Write minimal implementation**

In `LeadApiDeprovisioningService::deprovisionLead`, after loading lead:

```php
if (($lead['converted_at'] ?? null) !== null && (string) $lead['converted_at'] !== '') {
    throw new \InvalidArgumentException(
        'Este lead ya tiene membresía convertida. Usa el flujo de baja paid / dunning, no Dar de baja demo.'
    );
}
if ((string) ($lead['plan_slug'] ?? 'demo') !== 'demo') {
    throw new \InvalidArgumentException(
        'Solo se puede dar de baja demo cuando plan_slug es demo.'
    );
}
```

In `config/cruds/mkt_leads.json`, change the action to:

```json
{
  "name": "dar_baja_demo_api",
  "type": "link",
  "label": "Dar de baja demo",
  "icon": "bi-cloud-slash",
  "route": "/admin/marketing/leads/deprovision-api?lead_id={id}",
  "permission": "marketing.leads",
  "visible_when": {
    "estado": "demo_enviada",
    "plan_slug": "demo",
    "converted_at": ""
  }
}
```

(`equalityMatches` casts null `converted_at` to `""`, so converted rows hide the button.)

Also update `countActiveDemos` in `PdoChurnMetricsRepository`:

```php
"SELECT COUNT(*) FROM dom_mkt_leads
 WHERE deleted = 0
   AND estado = 'demo_enviada'
   AND api_tenant_public_id IS NOT NULL
   AND converted_at IS NULL"
```

- [ ] **Step 4: Run tests**

Run: `php tests/run.php Marketing/LeadDeprovisionGuardTest`

Expected: PASS

- [ ] **Step 5: Commit** (only if user asks)

```bash
git add config/cruds/mkt_leads.json \
  app/Application/Marketing/LeadApiDeprovisioningService.php \
  app/Infrastructure/Marketing/PdoChurnMetricsRepository.php \
  tests/Marketing/LeadDeprovisionGuardTest.php
git commit -m "fix(marketing): hide and block demo deprovision after conversion"
```

---

### Task 3: Plantilla repo + UNIQUE clave + seed catalog

**Files:**
- Create: `database/migrations/20260715200000_mkt_plantillas_unique_clave.sql`
- Create: `database/migrations/20260715200100_mkt_plantillas_seed_catalog.sql`
- Modify: `config/modules/marketing.php`
- Create: `app/Domain/Marketing/Contracts/PlantillaRepositoryInterface.php`
- Create: `app/Infrastructure/Marketing/PdoPlantillaRepository.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/PlantillaRepositoryTest.php` (or renderer tests cover find)

**Interfaces:**
- Produces: `PlantillaRepositoryInterface::findActiveByClave(string $clave): ?array` returning `{id,clave,asunto,cuerpo,activo}`

- [ ] **Step 1: Write migrations**

`20260715200000_mkt_plantillas_unique_clave.sql`:

```sql
-- Idempotent unique on clave (drop non-unique index if present).
ALTER TABLE `dom_mkt_plantillas`
  DROP INDEX `idx_mkt_plantillas_clave`,
  ADD UNIQUE KEY `uq_mkt_plantillas_clave` (`clave`);
```

If DROP INDEX fails on fresh installs that never had the old name, prefer a safer install note: on fresh `marketing.sql` change `KEY` to `UNIQUE KEY` and make the migration:

```sql
-- Skip if unique already exists (Installer runs once).
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'dom_mkt_plantillas'
    AND index_name = 'uq_mkt_plantillas_clave'
);
-- Use a procedure or document manual ops; simplest path for this repo:
ALTER TABLE `dom_mkt_plantillas` ADD UNIQUE KEY `uq_mkt_plantillas_clave` (`clave`);
```

Also update `database/schema/modules/marketing.sql` line for `dom_mkt_plantillas` to `UNIQUE KEY uq_mkt_plantillas_clave (clave)` for greenfield installs.

`20260715200100_mkt_plantillas_seed_catalog.sql` — seed **by clave** (not “table empty”). Include HTML copied from current views for the three existing mails; dunning bodies can be simpler HTML with `{{vars}}`.

```sql
INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'lead_welcome', 'Recibimos tu solicitud — Lebytek', '<p>Hola {{nombre}},</p><p>Recibimos tu solicitud. Te contactaremos pronto.</p>', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'lead_welcome');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'lead_api_credentials', 'Tus credenciales demo — Lebytek', '<p>Hola {{nombre}},</p><p>Token: {{token}}</p><p>API: {{api_base_url}}</p>', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'lead_api_credentials');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'membership_activated', 'Tu membresía Lebytek está activa', '<p>Hola {{nombre}},</p><p>Plan {{plan}} ({{ciclo}}). Token: {{token}}. API: {{api_base_url}}</p>', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'membership_activated');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'membership_payment_failed', 'Problema con tu pago — acción requerida', '<p>Hola {{nombre}},</p><p>No pudimos cobrar tu plan {{plan}} ({{ciclo}}). Tienes {{grace_hours}} horas para actualizar el pago:</p><p><a href="{{retry_url}}">Reintentar pago</a></p>', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'membership_payment_failed');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'membership_cancelled_reactivate', 'Tu cuenta fue cancelada — puedes reactivar', '<p>Hola {{nombre}},</p><p>Cancelamos {{cuenta}} por falta de pago. Reactiva cuando quieras:</p><p><a href="{{retry_url}}">Reactivar membresía</a></p>', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'membership_cancelled_reactivate');

-- Align legacy stub key if present
UPDATE `dom_mkt_plantillas`
SET `clave` = 'lead_welcome',
    `asunto` = 'Recibimos tu solicitud — Lebytek'
WHERE `clave` = 'lead_autoresponder'
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'lead_welcome') t);
```

**Important:** For production-quality HTML, copy the full markup from `app/Presentation/Views/emails/{lead_welcome,lead_api_credentials,membership_activated}.php` into the seed `cuerpo` (replace PHP echo with `{{var}}`). The snippets above are the minimum contract; the implementing agent must paste real HTML from those three files so ops sees the same look in CRUD.

Register both migrations in `config/modules/marketing.php` `migraciones` array.

- [ ] **Step 2: Plantilla interface + PDO**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface PlantillaRepositoryInterface
{
    /** @return array{id:int,clave:string,asunto:string,cuerpo:string,activo:int}|null */
    public function findActiveByClave(string $clave): ?array;
}
```

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoPlantillaRepository implements PlantillaRepositoryInterface
{
    public function findActiveByClave(string $clave): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT id, clave, asunto, cuerpo, activo
             FROM dom_mkt_plantillas
             WHERE clave = :clave AND activo = 1 AND deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['clave' => $clave]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
```

Bind in `config/container.php`.

- [ ] **Step 3: Commit** (only if user asks)

```bash
git add database/migrations/20260715200000_mkt_plantillas_unique_clave.sql \
  database/migrations/20260715200100_mkt_plantillas_seed_catalog.sql \
  database/schema/modules/marketing.sql \
  config/modules/marketing.php \
  app/Domain/Marketing/Contracts/PlantillaRepositoryInterface.php \
  app/Infrastructure/Marketing/PdoPlantillaRepository.php \
  config/container.php
git commit -m "feat(marketing): unique plantilla claves and catalog seed"
```

---

### Task 4: MarketingMailRenderer + migrate three senders

**Files:**
- Create: `app/Application/Marketing/MarketingMailRenderer.php`
- Modify: `app/Infrastructure/Marketing/LeadCapture/AutoresponderHandler.php`
- Modify: `app/Application/Marketing/LeadApiProvisioningService.php`
- Modify: `app/Application/Marketing/ActivateMembershipFromOrderService.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/MarketingMailRendererTest.php`

**Interfaces:**
- Consumes: `PlantillaRepositoryInterface::findActiveByClave`, `MailerInterface::enviar`
- Produces: `MarketingMailRenderer::send(string $clave, string $toEmail, string $toName, array $vars): void`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Application\Marketing\MarketingMailRenderer;
use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class FakePlantillas implements PlantillaRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $byClave = [];

    public function findActiveByClave(string $clave): ?array
    {
        return $this->byClave[$clave] ?? null;
    }
}

final class CapturingMailer implements MailerInterface
{
    /** @var list<MensajeCorreo> */
    public array $sent = [];

    public function enviar(MensajeCorreo $mensaje): void
    {
        $this->sent[] = $mensaje;
    }
}

test('renderer sustituye vars y envia asunto/cuerpo de plantilla', function (): void {
    $repo = new FakePlantillas();
    $repo->byClave['membership_payment_failed'] = [
        'id' => 1,
        'clave' => 'membership_payment_failed',
        'asunto' => 'Hola {{nombre}} — pago',
        'cuerpo' => '<p>{{plan}} <a href="{{retry_url}}">x</a></p>',
        'activo' => 1,
    ];
    $mailer = new CapturingMailer();
    $renderer = new MarketingMailRenderer($repo, $mailer);

    $renderer->send('membership_payment_failed', 'a@b.c', 'Ana', [
        'nombre' => 'Ana',
        'plan' => 'starter',
        'retry_url' => 'https://lebytek.com/r',
    ]);

    assert_same(1, count($mailer->sent));
    assert_same('Hola Ana — pago', $mailer->sent[0]->asunto);
    assert_true(str_contains($mailer->sent[0]->html, 'starter'));
    assert_true(str_contains($mailer->sent[0]->html, 'https://lebytek.com/r'));
});

test('renderer escapa HTML en vars de usuario', function (): void {
    $repo = new FakePlantillas();
    $repo->byClave['lead_welcome'] = [
        'id' => 2, 'clave' => 'lead_welcome', 'asunto' => 'x', 'cuerpo' => '{{nombre}}', 'activo' => 1,
    ];
    $mailer = new CapturingMailer();
    $renderer = new MarketingMailRenderer($repo, $mailer);
    $renderer->send('lead_welcome', 'a@b.c', 'x', ['nombre' => '<script>']);
    assert_true(str_contains($mailer->sent[0]->html, '&lt;script&gt;'));
    assert_false(str_contains($mailer->sent[0]->html, '<script>'));
});

test('renderer lanza si clave ausente y sin fallback', function (): void {
    $renderer = new MarketingMailRenderer(new FakePlantillas(), new CapturingMailer());
    $threw = false;
    try {
        $renderer->send('missing_key', 'a@b.c', 'x', []);
    } catch (\RuntimeException $e) {
        $threw = true;
    }
    assert_true($threw);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/MarketingMailRendererTest`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement renderer**

```php
<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

final class MarketingMailRenderer
{
    /** @var array<string, string> clave => vista PHP relativa (migración) */
    private const FALLBACK_VIEWS = [
        'lead_welcome' => 'emails/lead_welcome',
        'lead_api_credentials' => 'emails/lead_api_credentials',
        'membership_activated' => 'emails/membership_activated',
    ];

    public function __construct(
        private readonly PlantillaRepositoryInterface $plantillas,
        private readonly MailerInterface $mailer,
    ) {}

    /** @param array<string, scalar|null> $vars */
    public function send(string $clave, string $toEmail, string $toName, array $vars): void
    {
        $row = $this->plantillas->findActiveByClave($clave);
        $safe = [];
        foreach ($vars as $k => $v) {
            $safe[$k] = htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($row === null) {
            if (! isset(self::FALLBACK_VIEWS[$clave])) {
                throw new \RuntimeException('Plantilla de correo no encontrada: '.$clave);
            }
            // Migration window only — log when a logger exists; keep warning visible in error_log
            error_log('[MarketingMailRenderer] fallback PHP view for clave='.$clave);
            $html = ViewHelper::render(self::FALLBACK_VIEWS[$clave], $this->viewDataForFallback($clave, $vars), '');
            $asunto = $this->fallbackSubject($clave);
            $this->mailer->enviar(new MensajeCorreo($toEmail, $toName, $asunto, $html));

            return;
        }

        $asunto = $this->replaceVars((string) $row['asunto'], $safe);
        $cuerpo = $this->replaceVars((string) $row['cuerpo'], $safe);
        $this->mailer->enviar(new MensajeCorreo($toEmail, $toName, $asunto, $cuerpo));
    }

    /** @param array<string, string> $safe */
    private function replaceVars(string $template, array $safe): string
    {
        $out = $template;
        foreach ($safe as $key => $value) {
            $out = str_replace('{{'.$key.'}}', $value, $out);
        }

        return $out;
    }

    /** @param array<string, scalar|null> $vars */
    private function viewDataForFallback(string $clave, array $vars): array
    {
        // Map {{plan}} → planNombre etc. for legacy views during migration
        if ($clave === 'membership_activated') {
            return [
                'nombre' => (string) ($vars['nombre'] ?? ''),
                'planNombre' => (string) ($vars['plan'] ?? $vars['planNombre'] ?? ''),
                'ciclo' => (string) ($vars['ciclo'] ?? ''),
                'cuota' => (string) ($vars['cuota'] ?? ''),
                'apiBaseUrl' => (string) ($vars['api_base_url'] ?? $vars['apiBaseUrl'] ?? ''),
                'token' => (string) ($vars['token'] ?? ''),
            ];
        }
        if ($clave === 'lead_api_credentials') {
            return [
                'nombre' => (string) ($vars['nombre'] ?? ''),
                'token' => (string) ($vars['token'] ?? ''),
                'apiBaseUrl' => (string) ($vars['api_base_url'] ?? ''),
            ];
        }

        return [
            'nombre' => (string) ($vars['nombre'] ?? ''),
        ];
    }

    private function fallbackSubject(string $clave): string
    {
        return match ($clave) {
            'lead_welcome' => 'Recibimos tu solicitud — Lebytek',
            'lead_api_credentials' => 'Tus credenciales demo — Lebytek',
            'membership_activated' => 'Tu membresía Lebytek está activa',
            default => 'Lebytek',
        };
    }
}
```

Migrate callers to `$this->mailRenderer->send(...)` with the clave map from the spec. Remove direct `ViewHelper::render('emails/…')` from the three services (fallback remains inside renderer only).

Wire DI: inject `MarketingMailRenderer` into the three callers; bind in `container.php`.

- [ ] **Step 4: Run tests**

```bash
php tests/run.php Marketing/MarketingMailRendererTest
php tests/run.php Marketing/ActivateMembershipFromOrderServiceTest
php tests/run.php Marketing/LeadConversionOnActivateTest
php tests/run.php Integration/LeadApiProvisioningServiceTest
```

Expected: PASS. Done criterion for Phase B: no caller outside `MarketingMailRenderer` uses `ViewHelper::render('emails/…')` for those three claves (grep to confirm).

- [ ] **Step 5: Commit** (only if user asks)

```bash
git add app/Application/Marketing/MarketingMailRenderer.php \
  app/Infrastructure/Marketing/LeadCapture/AutoresponderHandler.php \
  app/Application/Marketing/LeadApiProvisioningService.php \
  app/Application/Marketing/ActivateMembershipFromOrderService.php \
  config/container.php \
  tests/Marketing/MarketingMailRendererTest.php
git commit -m "feat(marketing): MarketingMailRenderer and migrate transactional mails"
```

---

### Task 5: Compute churn snapshot service + cron script

**Files:**
- Create: `app/Application/Marketing/ComputeChurnSnapshotService.php`
- Create: `scripts/compute-churn-snapshot.php`
- Extend: `ChurnMetricsRepositoryInterface` with count helpers if needed
- Test: `tests/Marketing/ComputeChurnSnapshotServiceTest.php`

**Interfaces:**
- Consumes: PDO / repo counts for period
- Produces: `ComputeChurnSnapshotService::computeFor(int $year, int $month): array` then `saveChurnSnapshot`

Definitions (from spec):

```
demos_started   = leads with demo_started_at (or provision) in period
demos_converted = leads with converted_at in period
demo_conversion_pct = 100 * demos_converted / max(demos_started, 1)  OR null if started=0

clients_start = leads with converted_at < period_start AND (cancelled_at IS NULL OR cancelled_at >= period_start)
clients_lost  = leads with converted_at IS NOT NULL AND cancelled_at in period
churn_rate_pct = 100 * clients_lost / max(clients_start, 1)

Exclude past_due from clients_lost (cancelled_at only).
```

- [ ] **Step 1: Write failing unit test with an in-memory fake repo** that returns fixed counts; assert formulas.

```php
test('snapshot usa converted_at para conversion y cancelled_at para churn paid', function (): void {
    $fake = new class {
        public function countDemosStarted(int $y, int $m): int { return 10; }
        public function countDemosConverted(int $y, int $m): int { return 2; }
        public function countClientsStart(int $y, int $m): int { return 20; }
        public function countClientsLost(int $y, int $m): int { return 1; }
        public function countAtRisk(): int { return 3; }
        public function countActiveByUsage(int $y, int $m): int { return 15; }
        /** @var array<string,mixed>|null */
        public ?array $saved = null;
        public function saveChurnSnapshot(array $data): void { $this->saved = $data; }
    };
    // Wire ComputeChurnSnapshotService to accept these methods via ChurnMetricsRepositoryInterface extensions
    $svc = new \App\Application\Marketing\ComputeChurnSnapshotService($fake);
    $svc->computeAndSave(2026, 6);
    assert_same(20.0, (float) $fake->saved['demo_conversion_pct']); // 2/10*100
    assert_same(5.0, (float) $fake->saved['churn_rate_pct']); // 1/20*100
});
```

Extend `ChurnMetricsRepositoryInterface` + `PdoChurnMetricsRepository` with the count methods used above (SQL filtered by `YEAR()/MONTH()` on the relevant timestamps).

- [ ] **Step 2: Implement service + `scripts/compute-churn-snapshot.php`**

Script header cron example:

```php
#!/usr/bin/env php
<?php
// Cron (VPS): 15 3 1 * * php /path/to/scripts/compute-churn-snapshot.php
// Computes previous calendar month by default; optional args: YYYY MM
```

Bootstrap like other `scripts/*.php` in this repo (autoload + EnvLoader + Connection).

- [ ] **Step 3: Run tests**

`php tests/run.php Marketing/ComputeChurnSnapshotServiceTest` → PASS

- [ ] **Step 4: Commit** (only if user asks)

```bash
git commit -m "feat(marketing): compute paid churn and demo conversion snapshots"
```

---

### Task 6: Api soft-cancel + reactivate (WhatsApiLebytek)

**Repo:** `WhatsApiLebytek` (not Framework).

**Files:**
- Create: `app/Services/CancelCommercialService.php`
- Create: `app/Services/ReactivateCommercialService.php`
- Modify: `app/Http/Controllers/Api/V1/TenantController.php`
- Modify: `routes/api.php`
- Modify: `docs/integration/waapi-api-contract.md`
- Test: `tests/Feature/Api/TenantCommercialLifecycleTest.php`
- Portal client: `LebytekApiClient` methods

**Interfaces:**
- Produces:
  - `POST /api/v1/tenants/{publicId}/cancel-commercial` → `{ commercialStatus: "cancelled", tokensRevoked: N }`
  - `POST /api/v1/tenants/{publicId}/reactivate-commercial` → `{ commercialStatus: "active", token: "..." }` (platform auth)
- Consumes: `TenantTokenService::revokeClientTokens` / `issue`

- [ ] **Step 1: Write failing Pest feature tests**

```php
it('cancel-commercial sets cancelled and revokes tokens without deleting instance', function () {
    // platform Sanctum token + tenant with instance + client token
    // POST cancel-commercial
    // assert commercial_status cancelled, client token 401, instance still exists
});

it('reactivate-commercial restores active and issues a new token', function () {
    // from cancelled tenant
    // assert active + non-null token + same instance public_id
});
```

- [ ] **Step 2: Implement services**

`CancelCommercialService::cancel(Tenant $tenant, ?string $reason = null): array`:
- lock tenant row
- set `commercial_status = cancelled`, merge `meta.cancelled_at`, `meta.cancel_reason`
- `$revoked = $tokens->revokeClientTokens($tenant)`
- **do not** SoftDelete tenant; **do not** destroy instances
- return counts

`ReactivateCommercialService::reactivate(Tenant $tenant, string $tokenName = 'membresia-reactivated'): array`:
- set `commercial_status = active`, clear cancel meta keys
- revoke + issue (same pattern as activate-plan)
- return `{ token, commercialStatus }`

Platform middleware / `tenants.gestionar` same as activate-plan. Idempotency-Key middleware on both routes.

- [ ] **Step 3: Document contract** in `waapi-api-contract.md` (mirror activate-plan style). Sync copy into Framework `docs/integration/` if that mirror exists.

- [ ] **Step 4: Portal client**

```php
public function cancelCommercial(string $tenantPublicId, ?string $idempotencyKey = null): array
{
    return $this->request('POST', '/tenants/'.$tenantPublicId.'/cancel-commercial', [], [], $idempotencyKey);
}

public function reactivateCommercial(string $tenantPublicId, array $payload = [], ?string $idempotencyKey = null): array
{
    return $this->request('POST', '/tenants/'.$tenantPublicId.'/reactivate-commercial', $payload, [], $idempotencyKey);
}
```

- [ ] **Step 5: Run**

```bash
# WhatsApiLebytek
php artisan test --filter=TenantCommercialLifecycleTest
```

Expected: PASS

- [ ] **Step 6: Commit** in each repo when user asks.

---

### Task 7: `dom_mkt_membresias` + repository

**Files:**
- Create: `database/migrations/20260715210000_mkt_membresias.sql`
- Modify: `config/modules/marketing.php`
- Create: `app/Domain/Marketing/Contracts/MembershipRepositoryInterface.php`
- Create: `app/Infrastructure/Marketing/PdoMembershipRepository.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/MembershipRepositoryContractTest.php` (fake-first; PDO optional)

**Schema:**

```sql
CREATE TABLE IF NOT EXISTS `dom_mkt_membresias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NULL,
  `api_tenant_public_id` CHAR(26) NOT NULL,
  `plan_slug` VARCHAR(40) NOT NULL,
  `ciclo` ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
  `status` ENUM('active','past_due','cancelled') NOT NULL DEFAULT 'active',
  `stripe_customer_id` VARCHAR(64) NULL,
  `stripe_subscription_id` VARCHAR(64) NULL,
  `current_period_end` DATETIME NULL,
  `grace_started_at` DATETIME NULL,
  `grace_ends_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `reactivation_token_hash` CHAR(64) NULL,
  `retry_token_hash` CHAR(64) NULL,
  `retry_expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_membresias_tenant` (`api_tenant_public_id`),
  UNIQUE KEY `uq_mkt_membresias_stripe_sub` (`stripe_subscription_id`),
  KEY `idx_mkt_membresias_grace` (`status`, `grace_ends_at`),
  KEY `idx_mkt_membresias_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Interface methods:**

```php
public function upsertFromActivation(array $data): int;
public function findByTenantPublicId(string $tenantPublicId): ?array;
public function findByStripeSubscriptionId(string $subscriptionId): ?array;
public function findByRetryTokenHash(string $hash): ?array;
public function findByReactivationTokenHash(string $hash): ?array;
public function markPastDue(int $id, \DateTimeInterface $graceEndsAt, string $retryTokenHash): void;
public function clearGrace(int $id): void;
public function markCancelled(int $id, string $reactivationTokenHash): void;
public function markActive(int $id, ?\DateTimeInterface $periodEnd = null): void;
/** @return list<array<string,mixed>> */
public function findGraceExpired(\DateTimeInterface $now): array;
```

- [ ] **Step 1–4:** TDD with fake → PDO → register migration → commit when asked.

After first successful activate (Task 1 path), also upsert membresía `status=active` (Phase E wiring in Task 8 can own “create on subscription”; for transfer-only paid activates still create a membresía row **without** stripe ids so churn/cancel ops have a home).

---

### Task 8: Stripe subscription checkout + webhook invoice types

**Files:**
- Modify: `src/Domain/Payments/SupportsSubscriptions.php`
- Modify: `src/Domain/Payments/PaymentEventType.php`
- Modify: `src/Domain/Payments/ValueObjects/PaymentEvent.php`
- Modify: `src/Infrastructure/Payments/StripeGateway.php`
- Modify: `app/Application/Marketing/IniciarPagoStripeUseCase.php`
- Modify: `app/Application/Marketing/ConfirmarPagoStripeUseCase.php`
- Modify: `config/payments.php`, `.env.example`
- Test: `tests/Payments/StripeGatewaySubscriptionTest.php`, `tests/Marketing/ConfirmarPagoStripeSubscriptionTest.php`

**Interfaces:**
- Extend empty marker:

```php
namespace Lebytek\Framework\Domain\Payments;

/** Implemented by gateways that can create subscription Checkout sessions. */
interface SupportsSubscriptions
{
    /**
     * @param array{price_id:string,customer_email:string,success_url:string,cancel_url:string,external_ref:string,metadata?:array<string,string>} $input
     */
    public function createSubscriptionCheckout(array $input): \Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
}
```

`StripeGateway implements PaymentGatewayInterface, SupportsSubscriptions`:
- `createSubscriptionCheckout` uses Checkout `mode=subscription` + `line_items: [[price => price_id, quantity => 1]]` (no `price_data`).
- `parseWebhook` map:
  - `invoice.paid` → `PaymentEventType::InvoicePaid`
  - `invoice.payment_failed` → `PaymentEventType::InvoicePaymentFailed`
  - keep existing checkout / payment_intent mappings
- Extend `PaymentEvent` with optional `subscriptionId(): ?string`, `customerId(): ?string` (default null for one-shot). PHP 8.1: add nullable constructor params at the end.

Env Price map (Portal config, not inside StripeGateway):

```
STRIPE_PRICE_STARTER_MONTHLY=price_...
STRIPE_PRICE_STARTER_ANNUAL=price_...
STRIPE_PRICE_BUSINESS_MONTHLY=price_...
STRIPE_PRICE_BUSINESS_ANNUAL=price_...
```

`IniciarPagoStripeUseCase`: if order `metodo_pago=card` and feature flag / always for card path in v2, resolve price id from slug+ciclo and call `createSubscriptionCheckout` when gateway `instanceof SupportsSubscriptions`; else keep one-shot `createCheckout` for backward compatibility behind `PAYMENTS_SUBSCRIPTION_CHECKOUT=true`.

`ConfirmarPagoStripeUseCase`:
- `InvoicePaid` (first or renewal): claim event → find membresía by subscription id or order via metadata → activate / markActive / markConverted / clearGrace; create renewal `dom_mkt_ordenes` row for audit when renewal (minimal: status paid + payment_ref = invoice id).
- `InvoicePaymentFailed`: claim → `StartMembershipGraceService` (Task 9).

Money guard: for invoices use `amount_due`/`amount_paid` vs expected plan price when available; if metadata lacks order, skip money guard and rely on Stripe Price id match.

- [ ] **Tests:** parseWebhook for `invoice.payment_failed` returns `InvoicePaymentFailed` with subscription id; ConfirmarPago does not set `cancelled_at`.

- [ ] **Commit** when asked: separate Grupo A (`src/`) vs Grupo B (`app/`).

---

### Task 9: Dunning 48h — start grace + expire job

**Files:**
- Create: `app/Application/Marketing/StartMembershipGraceService.php`
- Create: `app/Application/Marketing/ExpireMembershipGraceService.php`
- Create: `scripts/expire-membership-grace.php`
- Public: retry route + controller
- Test: `tests/Marketing/MembershipDunningTest.php`

**Interfaces:**
- `StartMembershipGraceService::handle(array $membresia, string $rawEventId): void`
- `ExpireMembershipGraceService::expireDue(\DateTimeInterface $now): int` — returns processed count

**Start grace algorithm:**

1. If status already `cancelled`, no-op.
2. If already `past_due` with future `grace_ends_at`, **do not** reset the clock (idempotent webhook).
3. Else: `status=past_due`, `grace_started_at=now`, `grace_ends_at=now+48h`.
4. Generate raw retry token (32 bytes hex); store `hash('sha256', $raw)`; `retry_expires_at=grace_ends_at`.
5. `upsertRiskSignal(leadId, tenantId, 'payment_failed', 'high', ...)`.
6. `MarketingMailRenderer::send('membership_payment_failed', …, ['nombre','plan','ciclo','retry_url','grace_hours'=>48,'cuenta'=>'Lebytek'])`.
7. **Do not** touch `cancelled_at` / lead cancel.

**Retry URL:** `/membresia/reintentar-pago?t={rawToken}` — validates hash + not expired; starts Stripe Customer Portal or new Checkout to pay open invoice (prefer Stripe Billing Portal session if customer id present; else Checkout mode subscription with same price). Paying success path → Task 10 clear grace.

**Expire algorithm (`scripts/expire-membership-grace.php` every 15–60 min):**

1. `findGraceExpired(now)` where `status=past_due AND grace_ends_at <= now`.
2. Per row: `LebytekApiClient::cancelCommercial(tenant)`.
3. Generate permanent reactivation token; `markCancelled` on membresía + `leads->markCancelled(leadId)`.
4. Resolve risk signal (`resolved_at=NOW()` — add `resolveOpenRiskSignal` to churn repo).
5. Send `membership_cancelled_reactivate` with permanent URL `/membresia/reactivar?t={rawToken}`.
6. Now counts as churn (via `cancelled_at`).

**Tests:**

```php
test('payment_failed pone past_due sin cancelled_at', …);
test('segundo payment_failed no extiende gracia', …);
test('expire tras 48h soft-cancel y cancelled_at', …);
test('click retry sin pagar no mueve grace_ends_at', …);
```

- [ ] **Commit** when asked.

---

### Task 10: Recover payment + permanent reactivation

**Files:**
- Create: `app/Application/Marketing/RecoverMembershipPaymentService.php`
- Routes/controller for `/membresia/reactivar`
- Wire `InvoicePaid` / Checkout success into recover
- Test: extend `MembershipDunningTest.php`

**Recover (within grace or post-cancel):**

1. Resolve membresía by retry or reactivation token (or webhook subscription id).
2. If was `past_due`: `clearGrace` → `markActive`; resolve risk signal; optional confirmation mail (reuse `membership_activated` **without** inventing a new PHP view — only if product wants it; otherwise skip).
3. If was `cancelled`: `LebytekApiClient::reactivateCommercial` → `markActive` → clear `cancelled_at` on lead (`UPDATE … SET cancelled_at=NULL` — add `clearCancelled` on lead repo).
4. Invalidate retry token (set hash null); keep or rotate reactivation token after success.

**Acceptance mapping:**

| Spec criterion | Task |
|----------------|------|
| Activate sets `plan_slug` + `converted_at`; no demo baja | 1–2 |
| Demos activas exclude converted | 2 |
| Snapshot conversion/churn semantics | 5 |
| CRUD plantillas seeded + editable affects send | 3–4 |
| Three mails via renderer | 4 |
| `invoice.payment_failed` → past_due + mail #1; no cancel | 8–9 |
| 48h → soft-cancel + mail #2 + churn | 6, 9 |
| Permanent link reactivates | 6, 10 |

---

## Self-review

### 1. Spec coverage

| Spec section | Task(s) |
|--------------|---------|
| Flujo A conversión lead + CRUD | 1, 2 |
| Plantillas renderer + seed + migrate 3 consumers | 3, 4 |
| Metrics / snapshots | 5 (+ active demos SQL in 2) |
| Flujo B Subscriptions | 7, 8 |
| Flujo C Dunning 48h | 9 |
| Soft-cancel api | 6 |
| Reactivation permanent | 10 |
| Non-goals (no merge main, no WYSIWYG, no auth mail migrate, no Green delete) | Global Constraints |
| Ownership framework vs portal vs api | File Structure + Tasks 6/8 |

### 2. Placeholder scan

No TBD/TODO steps; each task has concrete paths, signatures, and test/run commands. Seed HTML for the three legacy mails must be copied from existing views at implementation time (called out explicitly in Task 3).

### 3. Type consistency

- `markConverted` / `markCancelled` / `clearCancelled` on leads
- `MarketingMailRenderer::send(clave, email, name, vars)`
- Plantilla claves: `lead_welcome`, `lead_api_credentials`, `membership_activated`, `membership_payment_failed`, `membership_cancelled_reactivate`
- Membresía statuses: `active` \| `past_due` \| `cancelled`
- Api: `cancel-commercial` / `reactivate-commercial`
- PaymentEventType: `InvoicePaid` / `InvoicePaymentFailed` added alongside existing cases

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-15-membership-recurring-dunning-churn.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration  
2. **Inline Execution** — execute tasks in this session using executing-plans, batch with checkpoints  

**Which approach?**
