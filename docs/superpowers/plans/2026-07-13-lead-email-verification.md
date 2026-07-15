# Lead Email Verification + WhatsApp Alert — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 6-char email verification on the first demo lead email, a one-time public page that flips the lead to `validada`, and a WhatsApp alert to the tech team via the master Green API instance.

**Architecture:** Verification challenge columns live on `dom_mkt_leads`. Capture generates token+code, welcome email carries both, `VerificarLeadEmailUseCase` validates and burns the challenge, then `LeadVerifiedWhatsAppNotifier` fans out to `MKT_ALERT_WHATSAPP_NUMBERS` through `GreenApiWhatsappChannel` (`GREEN_API_*`).

**Tech Stack:** PHP 8.1+, Lebytek Onion (`app/`), PhpMailer, Green API channel in `src/`, microtest harness (`php tests/run.php Marketing`).

**Spec:** `docs/superpowers/specs/2026-07-13-lead-email-verification-design.md`

---

## File Structure

| Path | Role |
|------|------|
| `database/migrations/20260713120000_mkt_leads_email_verify.sql` | ADD columns |
| `database/schema/modules/marketing.sql` | Keep bootstrap in sync |
| `app/Domain/Marketing/LeadEmailVerification.php` | Alphabet, issue code/token, hash, constants |
| `app/Domain/Marketing/Contracts/LeadRepositoryInterface.php` | +find/verify methods; extend `guardar` |
| `app/Domain/Marketing/ValueObjects/LeadResult.php` | Carry plaintext code+token for autoresponder |
| `app/Infrastructure/Marketing/PdoLeadRepository.php` | Persist challenge; find/mark/increment |
| `app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php` | Issue challenge on save |
| `app/Infrastructure/Marketing/LeadCapture/AutoresponderHandler.php` | Pass code+URL to template |
| `app/Presentation/Views/emails/lead_welcome.php` | Code + verify CTA |
| `app/Application/Marketing/VerificarLeadEmailUseCase.php` | Verify + status + notify |
| `app/Domain/Marketing/Contracts/LeadTeamAlertNotifierInterface.php` | Port for WA alerts |
| `app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php` | Green API fan-out |
| `app/Presentation/Controllers/Publico/LeadEmailVerificationController.php` | GET/POST |
| `app/Presentation/Views/publico/verificar_demo.php` | One-time form UI |
| `routes/marketing.php` | Public routes |
| `config/container.php` | Bindings |
| `.env.example` | `MKT_ALERT_WHATSAPP_NUMBERS` |
| `tests/Marketing/LeadEmailVerificationTest.php` | Domain + use case |
| `tests/Marketing/LeadWelcomeEmailTest.php` | Template asserts |
| `tests/Marketing/LeadCaptureTest.php` | Stubs + persist challenge |
| `tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php` | Notifier |
| `tests/Integration/LeadApi*ServiceTest.php` | Update interface stubs |

---

### Task 1: Domain helper `LeadEmailVerification`

**Files:**
- Create: `app/Domain/Marketing/LeadEmailVerification.php`
- Create: `tests/Marketing/LeadEmailVerificationTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
declare(strict_types=1);

use App\Domain\Marketing\LeadEmailVerification;

test('genera codigo de 6 chars del alfabeto seguro', function (): void {
    $code = LeadEmailVerification::generateCode();
    assert_same(6, strlen($code));
    assert_true((bool) preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $code));
});

test('genera token hex de 64 chars', function (): void {
    $token = LeadEmailVerification::generateToken();
    assert_same(64, strlen($token));
    assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $token));
});

test('hash y verify usan comparacion segura', function (): void {
    $hash = LeadEmailVerification::hashCode('AB12CD');
    assert_true(LeadEmailVerification::codeMatches('AB12CD', $hash));
    assert_true(LeadEmailVerification::codeMatches('ab12cd', $hash)); // case-insensitive
    assert_false(LeadEmailVerification::codeMatches('ZZZZZZ', $hash));
});

test('constantes de politica', function (): void {
    assert_same(24, LeadEmailVerification::TTL_HOURS);
    assert_same(5, LeadEmailVerification::MAX_ATTEMPTS);
});
```

- [ ] **Step 2: Run tests — expect FAIL (class missing)**

```bash
php tests/run.php Marketing/LeadEmailVerificationTest
```

- [ ] **Step 3: Implement domain helper**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Marketing;

final class LeadEmailVerification
{
    public const TTL_HOURS = 24;
    public const MAX_ATTEMPTS = 5;
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generateCode(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 6; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hashCode(string $plain): string
    {
        return hash('sha256', strtoupper(trim($plain)));
    }

    public static function codeMatches(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::hashCode($plain));
    }

    public static function expiresAtFromNow(): string
    {
        return (new \DateTimeImmutable('now'))
            ->modify('+' . self::TTL_HOURS . ' hours')
            ->format('Y-m-d H:i:s');
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
php tests/run.php Marketing/LeadEmailVerificationTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Marketing/LeadEmailVerification.php tests/Marketing/LeadEmailVerificationTest.php
git commit -m "feat(marketing): lead email verification code helper"
```

---

### Task 2: Schema migration

**Files:**
- Create: `database/migrations/20260713120000_mkt_leads_email_verify.sql`
- Modify: `database/schema/modules/marketing.sql` (add same columns to `CREATE TABLE dom_mkt_leads`)

- [ ] **Step 1: Create migration**

```sql
ALTER TABLE `dom_mkt_leads`
  ADD COLUMN `email_verify_token` VARCHAR(64) NULL AFTER `estado`,
  ADD COLUMN `email_verify_code_hash` VARCHAR(64) NULL AFTER `email_verify_token`,
  ADD COLUMN `email_verify_expires_at` DATETIME NULL AFTER `email_verify_code_hash`,
  ADD COLUMN `email_verified_at` DATETIME NULL AFTER `email_verify_expires_at`,
  ADD COLUMN `email_verify_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `email_verified_at`,
  ADD UNIQUE KEY `uq_mkt_leads_email_verify_token` (`email_verify_token`);
```

- [ ] **Step 2: Mirror columns in `marketing.sql` CREATE TABLE** (after `estado`), same types/defaults/unique key.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/20260713120000_mkt_leads_email_verify.sql database/schema/modules/marketing.sql
git commit -m "feat(marketing): email verification columns on leads"
```

---

### Task 3: Repository + LeadResult + PersistLeadHandler

**Files:**
- Modify: `app/Domain/Marketing/ValueObjects/LeadResult.php`
- Modify: `app/Domain/Marketing/Contracts/LeadRepositoryInterface.php`
- Modify: `app/Infrastructure/Marketing/PdoLeadRepository.php`
- Modify: `app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php`
- Modify: `tests/Marketing/LeadCaptureTest.php`
- Modify: `tests/Integration/LeadApiProvisioningServiceTest.php` (stub methods)
- Modify: `tests/Integration/LeadApiDeprovisioningServiceTest.php` (stub methods)

- [ ] **Step 1: Extend `LeadResult`**

```php
final class LeadResult
{
    /** @param array<string,string> $errores */
    public function __construct(
        private readonly bool $ok,
        private readonly ?int $leadId = null,
        private readonly array $errores = [],
        private readonly ?string $emailVerifyToken = null,
        private readonly ?string $emailVerifyCode = null,
    ) {}

    public function ok(): bool { return $this->ok; }
    public function leadId(): ?int { return $this->leadId; }
    /** @return array<string,string> */
    public function errores(): array { return $this->errores; }
    public function emailVerifyToken(): ?string { return $this->emailVerifyToken; }
    public function emailVerifyCode(): ?string { return $this->emailVerifyCode; }

    public function withLeadId(int $id): self
    {
        return new self(true, $id, $this->errores, $this->emailVerifyToken, $this->emailVerifyCode);
    }

    public function withEmailVerification(string $token, string $plainCode): self
    {
        return new self($this->ok, $this->leadId, $this->errores, $token, $plainCode);
    }
}
```

- [ ] **Step 2: Extend `LeadRepositoryInterface`**

Add after `guardar`:

```php
/**
 * @param array{
 *   token: string,
 *   code_hash: string,
 *   expires_at: string
 * }|null $emailVerification
 */
public function guardar(LeadDraft $draft, ?array $emailVerification = null): int;

/** @return array<string, mixed>|null */
public function findByEmailVerifyToken(string $token): ?array;

public function incrementEmailVerifyAttempts(int $leadId): void;

public function markEmailVerified(int $leadId): void;
```

Keep the old `guardar(LeadDraft $draft): int` signature replaced by the two-arg version (second arg optional).

- [ ] **Step 3: Implement PDO methods**

`guardar`: if `$emailVerification !== null`, include columns in INSERT:

```sql
INSERT INTO dom_mkt_leads
  (nombre, email, telefono, mensaje, estado, utm_source, utm_medium, utm_campaign,
   email_verify_token, email_verify_code_hash, email_verify_expires_at, email_verify_attempts)
VALUES (...)
```

`findByEmailVerifyToken`:

```sql
SELECT * FROM dom_mkt_leads
WHERE email_verify_token = :token AND deleted = 0 LIMIT 1
```

`incrementEmailVerifyAttempts`:

```sql
UPDATE dom_mkt_leads
SET email_verify_attempts = email_verify_attempts + 1, updated_at = NOW()
WHERE id = :id
```

`markEmailVerified`:

```sql
UPDATE dom_mkt_leads
SET estado = 'validada',
    email_verified_at = NOW(),
    email_verify_token = NULL,
    email_verify_code_hash = NULL,
    updated_at = NOW()
WHERE id = :id
```

- [ ] **Step 4: Update `PersistLeadHandler`**

```php
use App\Domain\Marketing\LeadEmailVerification;

public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
{
    $token = LeadEmailVerification::generateToken();
    $code  = LeadEmailVerification::generateCode();
    $id = $this->repo->guardar($draft, [
        'token'      => $token,
        'code_hash'  => LeadEmailVerification::hashCode($code),
        'expires_at' => LeadEmailVerification::expiresAtFromNow(),
    ]);
    return $resultadoPrevio
        ->withLeadId($id)
        ->withEmailVerification($token, $code);
}
```

- [ ] **Step 5: Fix all interface stubs** in LeadCaptureTest + Integration tests — add empty methods + optional `guardar` second arg. Update PersistLeadCapture test to assert `emailVerifyCode()` / `emailVerifyToken()` non-null (mock repo can ignore verification array).

Example stub additions:

```php
public function guardar(LeadDraft $draft, ?array $emailVerification = null): int { return 7; }
public function findByEmailVerifyToken(string $token): ?array { return null; }
public function incrementEmailVerifyAttempts(int $leadId): void {}
public function markEmailVerified(int $leadId): void {}
```

- [ ] **Step 6: Run**

```bash
php tests/run.php Marketing
php tests/run.php Integration/LeadApi
```

Expected: PASS (existing + stub updates).

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Marketing/ValueObjects/LeadResult.php \
  app/Domain/Marketing/Contracts/LeadRepositoryInterface.php \
  app/Infrastructure/Marketing/PdoLeadRepository.php \
  app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php \
  tests/Marketing/LeadCaptureTest.php \
  tests/Integration/LeadApiProvisioningServiceTest.php \
  tests/Integration/LeadApiDeprovisioningServiceTest.php
git commit -m "feat(marketing): persist email verification challenge on lead capture"
```

---

### Task 4: Welcome email template + AutoresponderHandler

**Files:**
- Modify: `app/Infrastructure/Marketing/LeadCapture/AutoresponderHandler.php`
- Modify: `app/Presentation/Views/emails/lead_welcome.php`
- Modify: `tests/Marketing/LeadWelcomeEmailTest.php`

- [ ] **Step 1: Update failing template test expectations**

```php
test('lead_welcome email includes verification code and CTA', function (): void {
    $html = ViewHelper::render('emails/lead_welcome', [
        'nombre'        => 'María López',
        'landingUrl'    => 'https://lebytek.com',
        'empresaNombre' => 'Lebytek',
        'codigo'        => 'AB12CD',
        'verifyUrl'     => 'https://lebytek.com/verificar-demo/tokentokentokentokentokentokentokentokentokentokentoken12',
    ], '');

    assert_true(str_contains($html, 'AB12CD'));
    assert_true(str_contains($html, 'Verificar mi correo'));
    assert_true(str_contains($html, '/verificar-demo/'));
    assert_true(str_contains($html, '24 horas'));
});
```

Keep previous asserts for name/branding; secondary CTA to `#paquetes` may remain.

- [ ] **Step 2: Run — expect FAIL**

```bash
php tests/run.php Marketing/LeadWelcomeEmailTest
```

- [ ] **Step 3: Update `AutoresponderHandler`**

```php
$base = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
$token = (string) ($resultadoPrevio->emailVerifyToken() ?? '');
$code  = (string) ($resultadoPrevio->emailVerifyCode() ?? '');
$verifyUrl = $token !== '' ? $base . '/verificar-demo/' . rawurlencode($token) : $base;

$html = ViewHelper::render('emails/lead_welcome', [
    'nombre'        => $draft->nombre(),
    'landingUrl'    => $base,
    'empresaNombre' => null,
    'codigo'        => $code,
    'verifyUrl'     => $verifyUrl,
], '');
```

- [ ] **Step 4: Update `lead_welcome.php`**

Insert after greeting paragraph: a highlighted box with the code (large monospace), primary CTA `verifyUrl` / label `Verificar mi correo`, note “El código caduca en 24 horas”. Update “¿Qué sigue?” items to: verify email → team reviews → credentials by email. Keep secondary packages CTA optional.

Use existing partials `_info_box` / `_cta_button`.

- [ ] **Step 5: Run PASS + commit**

```bash
php tests/run.php Marketing/LeadWelcomeEmailTest
git add app/Infrastructure/Marketing/LeadCapture/AutoresponderHandler.php \
  app/Presentation/Views/emails/lead_welcome.php \
  tests/Marketing/LeadWelcomeEmailTest.php
git commit -m "feat(marketing): welcome email verification code and link"
```

---

### Task 5: `VerificarLeadEmailUseCase`

**Files:**
- Create: `app/Domain/Marketing/Contracts/LeadTeamAlertNotifierInterface.php`
- Create: `app/Application/Marketing/VerificarLeadEmailUseCase.php`
- Create: `tests/Marketing/VerificarLeadEmailUseCaseTest.php`

- [ ] **Step 1: Notifier port**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface LeadTeamAlertNotifierInterface
{
    /** @param array<string, mixed> $lead */
    public function notifyLeadVerified(array $lead): void;
}
```

- [ ] **Step 2: Write use-case tests with in-memory repo + spy notifier**

Cover:

1. Unknown token → status `invalid`
2. Already verified (`email_verified_at` set OR token null after burn) → `already_verified`
3. Expired `expires_at` → `expired`
4. Attempts >= 5 → `locked`
5. Wrong code → `wrong_code`, attempts incremented
6. Right code → `ok`, `markEmailVerified` called, notifier called once
7. Notifier throws → still `ok` (catch/log; verification already marked — use case marks first, then notify inside try/catch)

Result DTO (simple array or class):

```php
/** @return array{status: string, lead?: array<string,mixed>|null, message?: string} */
```

Statuses: `invalid|already_verified|expired|locked|wrong_code|ok`

- [ ] **Step 3: Implement use case**

```php
final class VerificarLeadEmailUseCase
{
    public function __construct(
        private readonly LeadRepositoryInterface $repo,
        private readonly LeadTeamAlertNotifierInterface $notifier,
    ) {}

    public function execute(string $token, ?string $submittedCode = null): array
    {
        $lead = $this->repo->findByEmailVerifyToken($token);
        if ($lead === null) {
            // Also treat burned token: optional lookup by verified? Spec: unknown = invalid.
            return ['status' => 'invalid'];
        }
        if (!empty($lead['email_verified_at'])) {
            return ['status' => 'already_verified', 'lead' => $lead];
        }
        $expires = (string) ($lead['email_verify_expires_at'] ?? '');
        if ($expires !== '' && strtotime($expires) < time()) {
            return ['status' => 'expired', 'lead' => $lead];
        }
        $attempts = (int) ($lead['email_verify_attempts'] ?? 0);
        if ($attempts >= LeadEmailVerification::MAX_ATTEMPTS) {
            return ['status' => 'locked', 'lead' => $lead];
        }
        if ($submittedCode === null) {
            return ['status' => 'form', 'lead' => $lead]; // GET
        }
        $hash = (string) ($lead['email_verify_code_hash'] ?? '');
        if ($hash === '' || !LeadEmailVerification::codeMatches($submittedCode, $hash)) {
            $this->repo->incrementEmailVerifyAttempts((int) $lead['id']);
            return ['status' => 'wrong_code', 'lead' => $lead];
        }
        $this->repo->markEmailVerified((int) $lead['id']);
        $fresh = $this->repo->findById((int) $lead['id']) ?? $lead;
        try {
            $this->notifier->notifyLeadVerified($fresh);
        } catch (\Throwable $e) {
            error_log('[LeadEmailVerify] WhatsApp notify failed: ' . $e->getMessage());
        }
        return ['status' => 'ok', 'lead' => $fresh];
    }
}
```

Note: after `markEmailVerified`, token is NULL — `findById` for fresh row. Spy notifier asserts call with `estado=validada` if mocked mark updates estado.

- [ ] **Step 4: Run PASS + commit**

```bash
php tests/run.php Marketing/VerificarLeadEmailUseCaseTest
git add app/Domain/Marketing/Contracts/LeadTeamAlertNotifierInterface.php \
  app/Application/Marketing/VerificarLeadEmailUseCase.php \
  tests/Marketing/VerificarLeadEmailUseCaseTest.php
git commit -m "feat(marketing): verify lead email use case"
```

---

### Task 6: WhatsApp notifier (master instance)

**Files:**
- Create: `app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php`
- Create: `tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php`
- Modify: `.env.example`

- [ ] **Step 1: Tests with fake `MessageChannelInterface`**

```php
// Fake records MessageRequest list
// Env: putenv / $_ENV['MKT_ALERT_WHATSAPP_NUMBERS'] = '521111,521222';
// APP_URL for admin link
// Assert 2 sends, body contains nombre/email/telefono, channel=whatsapp
// Empty numbers → 0 sends, no throw
```

- [ ] **Step 2: Implement notifier**

```php
final class LeadVerifiedWhatsAppNotifier implements LeadTeamAlertNotifierInterface
{
    public function __construct(
        private readonly MessageChannelInterface $whatsapp,
        private readonly bool $enabled,
    ) {}

    public function notifyLeadVerified(array $lead): void
    {
        if (!$this->enabled) {
            return;
        }
        $raw = (string) EnvLoader::get('MKT_ALERT_WHATSAPP_NUMBERS', '');
        $numbers = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($numbers === []) {
            return;
        }
        $base = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $id = (int) ($lead['id'] ?? 0);
        $adminUrl = $base . '/crud/mkt_leads/' . $id; // match existing CRUD show URL
        $mensaje = trim((string) ($lead['mensaje'] ?? ''));
        if (mb_strlen($mensaje) > 120) {
            $mensaje = mb_substr($mensaje, 0, 117) . '...';
        }
        $body = "Lead verificado (email OK)\n"
            . 'Nombre: ' . ($lead['nombre'] ?? '') . "\n"
            . 'Email: ' . ($lead['email'] ?? '') . "\n"
            . 'Tel: ' . ($lead['telefono'] ?? '-') . "\n"
            . ($mensaje !== '' ? "Mensaje: {$mensaje}\n" : '')
            . "Admin: {$adminUrl}";

        foreach ($numbers as $phone) {
            $result = $this->whatsapp->send(new MessageRequest('whatsapp', $phone, $body, [
                'source' => 'lead_email_verified',
                'record_id' => $id,
            ]));
            if (!$result->ok()) { // check MessageResult API — use succeeded/failed helpers from class
                error_log('[LeadVerifiedWA] fail ' . $phone . ': ' . $result->error());
            }
        }
    }
}
```

Inspect `MessageResult` for exact success/error method names (`sent` vs `ok`) before implementing — use existing channel tests as reference (`MessageResult::sent`, `::failed`).

- [ ] **Step 3: `.env.example`**

```
MKT_ALERT_WHATSAPP_NUMBERS=
# Comma-separated E.164-ish digits. Requires GREEN_API_ENABLED=true + GREEN_API_INSTANCE/TOKEN.
```

- [ ] **Step 4: Run + commit**

```bash
php tests/run.php Marketing/LeadVerifiedWhatsAppNotifierTest
git add app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php \
  tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php .env.example
git commit -m "feat(marketing): WhatsApp team alert on lead email verify"
```

---

### Task 7: Public controller, view, routes, DI

**Files:**
- Create: `app/Presentation/Controllers/Publico/LeadEmailVerificationController.php`
- Create: `app/Presentation/Views/publico/verificar_demo.php`
- Modify: `routes/marketing.php`
- Modify: `config/container.php`

- [ ] **Step 1: Controller**

```php
final class LeadEmailVerificationController extends BaseController
{
    public function __construct(private readonly VerificarLeadEmailUseCase $useCase) {}

    public function show(Request $request): Response
    {
        $token = (string) $request->param('token', '');
        $result = $this->useCase->execute($token, null);
        return $this->renderVerification($result, $token);
    }

    public function submit(Request $request): Response
    {
        $this->verifyCsrf($request);
        $token = (string) $request->param('token', '');
        $code = trim((string) $request->input('codigo', ''));
        $result = $this->useCase->execute($token, $code);
        return $this->renderVerification($result, $token);
    }

    /** @param array<string,mixed> $result */
    private function renderVerification(array $result, string $token): Response
    {
        return $this->view('publico/verificar_demo', [
            'status' => $result['status'],
            'token'  => $token,
            'lead'   => $result['lead'] ?? null,
        ]); // use BaseController view helper pattern used elsewhere
    }
}
```

Confirm how `LandingController` / public views call layout (`view` vs `ViewHelper::render` + layout). Match that pattern exactly.

- [ ] **Step 2: View `verificar_demo.php`**

Bootstrap layout consistent with `publico/layout.php`:

- `form`: show input `codigo` (maxlength 6), submit, CSRF field via framework helper
- `wrong_code`: form + alert
- `ok`: success message (correo verificado; equipo te contactará)
- `already_verified`: info
- `expired` / `locked` / `invalid`: terminal messages, no form

- [ ] **Step 3: Routes in `routes/marketing.php`**

```php
use App\Presentation\Controllers\Publico\LeadEmailVerificationController;

$router->get('/verificar-demo/{token}', [LeadEmailVerificationController::class, 'show']);
$router->post('/verificar-demo/{token}', [LeadEmailVerificationController::class, 'submit'], [CsrfMiddleware::class]);
```

- [ ] **Step 4: Container bindings**

Wire `VerificarLeadEmailUseCase` with `LeadVerifiedWhatsAppNotifier`:

- Build `GreenApiWhatsappChannel` using same config as `config/integrations.php` whatsapp block (Http connector already used by IntegrationsFactory — prefer resolving channel via `IntegrationsFactory` / `ChannelRegistry` if already in container; otherwise construct channel with `CurlApiConnector` or existing HTTP connector from framework).
- `enabled` = `(bool) EnvLoader::get('GREEN_API_ENABLED', false)`
- Bind controller

If IntegrationsFactory is heavy, construct:

```php
new GreenApiWhatsappChannel($http, [
  'base_url' => EnvLoader::get('GREEN_API_BASE_URL', 'https://api.green-api.com'),
  'instance_id' => EnvLoader::get('GREEN_API_INSTANCE', ''),
  'token' => EnvLoader::get('GREEN_API_TOKEN', ''),
  'timeout' => (int) EnvLoader::get('GREEN_API_TIMEOUT', 15),
]);
```

Find how demo WhatsApp handler gets HTTP connector — reuse that wiring.

- [ ] **Step 5: Manual smoke locally (optional)** + run Marketing suite

```bash
php tests/run.php Marketing
```

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Controllers/Publico/LeadEmailVerificationController.php \
  app/Presentation/Views/publico/verificar_demo.php \
  routes/marketing.php config/container.php
git commit -m "feat(marketing): one-time lead email verification page"
```

---

### Task 8: Spec status + deploy checklist note

**Files:**
- Modify: `docs/superpowers/specs/2026-07-13-lead-email-verification-design.md` — Status → Implemented (after code done)
- Optional short note in `docs/integration/lebytek-implementation-real.md` ops section: verify email before provision

- [ ] **Step 1: Update docs (ops path only, keep concise)**
- [ ] **Step 2: Full test run**

```bash
php tests/run.php Marketing
php tests/run.php Integration/LeadApi
```

- [ ] **Step 3: Final commit**

```bash
git commit -m "docs: mark lead email verification as implemented"
```

- [ ] **Step 4: Deploy handoff (human)**

On VPS lebytek.com (`feature/backoffice-api-integration`):

1. Pull branch
2. Run migration `20260713120000_mkt_leads_email_verify.sql`
3. Set `GREEN_API_ENABLED=true`, `GREEN_API_INSTANCE`, `GREEN_API_TOKEN`, `MKT_ALERT_WHATSAPP_NUMBERS`
4. Smoke: submit test lead → open email → verify page → check CRUD `validada` → check WhatsApp

**Do not merge to `main` unless user explicitly orders it.**

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Columns on `dom_mkt_leads` | 2 |
| Issue token+code on capture | 1, 3 |
| Welcome email code + link | 4 |
| NotifyInternal unchanged | — (no change) |
| GET/POST `/verificar-demo/{token}` | 7 |
| One-time + 24h + 5 attempts | 1, 5 |
| `pendiente` → `validada` | 5 |
| WhatsApp master `GREEN_API_*` + env numbers | 6, 7 |
| WhatsApp fail does not roll back | 5 |
| Tests | 1, 3–6 |
| No WhatsApiLebytek / no auto-provision | — out of scope |

## Self-review notes

- No TBD placeholders left.
- `MessageResult` method names must be verified against `src/Domain/Integrations/MessageResult.php` in Task 6.
- CRUD admin URL confirmed against router (`/crud/mkt_leads/{id}`) — if resource slug differs, adjust notifier only.
- All `LeadRepositoryInterface` stubs updated in Task 3 to avoid suite breakage.
