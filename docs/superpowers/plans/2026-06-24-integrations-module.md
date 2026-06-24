# Módulo `integrations` (Fase 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Fase 1 outbound-messaging core of the `integrations` module: a decoupled façade (`NotificationDispatcher`) that sends messages through interchangeable channels (Green API WhatsApp + an Email adapter), always logs the attempt to `int_logs`, and never propagates provider errors — triggerable from a CRUD row action with zero changes to the CRUD Engine core.

**Architecture:** Onion layering. Domain holds value objects + ports (`MessageRequest`, `MessageResult`, `MessageChannelInterface`, `ApiConnectorInterface`, `IntegrationLogRepositoryInterface`). Application holds the façade and orchestration (`NotificationDispatcher`, `ChannelRegistry`, `RateLimiter`). Infrastructure holds concrete channels, the HTTP connector and the PDO log repository. A static `IntegrationsFactory` wires everything from `config/integrations.php` so both the DI container binding and CRUD-triggered handlers share one construction path.

**Tech Stack:** PHP 8.1+, cURL (no new dependencies), custom DI container, MySQL (`int_*` tables), the in-repo microtest harness (`tests/run.php`).

## Global Constraints

These apply to **every** task. Exact values copied from the spec and verified against the codebase.

- **PHP 8.1+**, `declare(strict_types=1);` at the top of every PHP file.
- **PSR-4 autoload:** namespace `App\` → directory `app/`. New namespaces: `App\Domain\Integrations`, `App\Application\Integrations`, `App\Infrastructure\Integrations` (sub-namespaces `Channels`, `Http`, `Repositories`).
- **Onion dependency rule:** Domain has zero external deps. Application depends only on Domain. Infrastructure implements Domain ports. Config/Kernel wires.
- **CRUD handlers receive NO constructor arguments.** `CrudHandlerRegistry::resolve()` calls `new $class()`. Handlers MUST self-resolve dependencies via `IntegrationsFactory::dispatcher()` (mirrors how `DemoProductoToggleStatusHandler` does `new GenericCrudRepository()`).
- **No global container.** Do not attempt `Container::getInstance()` — it does not exist. Use `IntegrationsFactory` for non-container call sites.
- **Config access:** `App\Kernel\Config::get('key.path', $default)`. Env access: `App\Kernel\EnvLoader::get('KEY', $default)`.
- **DB access:** PDO via `App\Kernel\Database\Connection::getInstance()`. Repositories extend `App\Kernel\BaseClasses\BaseRepository` (gives `protected query/queryOne/execute/insert`).
- **Dispatcher must NEVER throw to the caller.** Catch `\Throwable`, log `failed`, return `MessageResult::failed(...)`.
- **`int_logs` never stores secrets.** Recipient is **masked**; `error` is a sanitized string; tokens live only in `.env`.
- **Table prefix `int_*`** (reserved for integrations per `CLAUDE.md`). `int_*` tables are NOT exposed via the CRUD Engine; the module's own repositories manage them.
- **Bootstrap SQL must be idempotent:** `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`.
- **Module bindings are toggle-guarded:** wrap container bindings in `if ((bool) Config::get('vertical.modules.integrations', false)) { ... }` (mirrors the marketing block in `config/container.php`).
- **Tests:** run with `php tests/run.php <Filter>` (filter is a substring of the file path). Test files end in `Test.php`. API: `test(string $name, callable $fn)`, `assert_true`, `assert_same`, `assert_null`, `assert_throws(string $exceptionClass, callable $fn)`. Unit tests use **fakes**, no DB.
- **Scope:** This plan implements **Fase 1 only**. Webhooks, queue, templates, reminders and multi-account (F2–F4) are explicitly out of scope.

---

## File Structure

**Create (Domain — `app/Domain/Integrations/`):**
- `MessageRequest.php` — VO the business module builds (channel, recipient, body, meta).
- `MessageResult.php` — uniform send result (ok, providerMessageId, error, rawResponse).
- `MessageChannelInterface.php` — channel port (`key()`, `send()`).
- `ApiConnectorInterface.php` — generic HTTP port (`request()`).
- `IntegrationLogRepositoryInterface.php` — log port (`record()`, `countRecent()`).

**Create (Application — `app/Application/Integrations/`):**
- `ChannelRegistry.php` — resolves channel key → channel instance + driver name.
- `RateLimiter.php` — per-channel window limit using the log repo's `countRecent`.
- `NotificationDispatcher.php` — the public façade.
- `IntegrationsFactory.php` — static wiring from `config/integrations.php` (used by container binding + handlers).

**Create (Infrastructure — `app/Infrastructure/Integrations/`):**
- `Http/HttpApiConnector.php` — cURL connector (timeouts, uniform error array).
- `Channels/GreenApiWhatsappChannel.php` — Green API text send + phone→chatId normalization.
- `Channels/EmailChannel.php` — adapts the existing `MailerInterface`.
- `Repositories/IntegrationLogRepository.php` — PDO → `int_logs`.

**Create (demo handler):**
- `app/Application/Crud/Handlers/EnviarWhatsappDemoHandler.php` — thin CRUD action handler.

**Create (config + schema):**
- `config/integrations.php` — channel map, rate limits, webhook stubs.
- `config/modules/integrations.php` — module manifest.
- `database/schema/modules/integrations.sql` — `int_logs` + RBAC permissions (idempotent).

**Modify:**
- `config/container.php` — toggle-guarded `NotificationDispatcher` binding.
- `config/vertical.php` — add `'integrations' => true` toggle.
- `config/crud_handlers.php` — register `enviar_whatsapp_demo`.
- `config/cruds/demo_clientes.json` — add the `confirmar_wa` row action.
- `.env.example` — Green API vars.

**Create (tests — `tests/Integrations/`):**
- `MessageResultTest.php`, `ChannelRegistryTest.php`, `RateLimiterTest.php`,
  `NotificationDispatcherTest.php`, `HttpApiConnectorTest.php`,
  `GreenApiWhatsappChannelTest.php`, `EmailChannelTest.php`,
  `IntegrationsConfigTest.php`, `IntegrationsFactoryTest.php`,
  `IntegrationsSchemaTest.php`, `EnviarWhatsappDemoHandlerTest.php`.

---

## Task 1: Domain contracts (VOs + ports)

**Files:**
- Create: `app/Domain/Integrations/MessageRequest.php`
- Create: `app/Domain/Integrations/MessageResult.php`
- Create: `app/Domain/Integrations/MessageChannelInterface.php`
- Create: `app/Domain/Integrations/ApiConnectorInterface.php`
- Create: `app/Domain/Integrations/IntegrationLogRepositoryInterface.php`
- Test: `tests/Integrations/MessageResultTest.php`

**Interfaces:**
- Consumes: nothing (Domain root).
- Produces:
  - `MessageRequest(string $channel, string $recipient, string $body, array $meta = [])` — public readonly props.
  - `MessageResult` with `bool $ok`, `?string $providerMessageId`, `?string $error`, `array $rawResponse`; static `sent(string $id, array $raw = []): self` and `failed(string $error, array $raw = []): self`.
  - `MessageChannelInterface::key(): string` and `::send(MessageRequest $r): MessageResult`.
  - `ApiConnectorInterface::request(string $method, string $url, array $payload = [], array $headers = []): array` returning `array{status:int, body:string, json:array}`.
  - `IntegrationLogRepositoryInterface::record(string $channel, string $driver, string $recipientMasked, string $status, ?string $providerMessageId, ?string $error, array $meta): void` and `::countRecent(string $channel, int $windowSeconds): int`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/MessageResultTest.php`:

```php
<?php
// tests/Integrations/MessageResultTest.php
declare(strict_types=1);

use App\Domain\Integrations\MessageResult;

test('MessageResult::sent marca ok y conserva el id de proveedor', function (): void {
    $r = MessageResult::sent('ABC123', ['idMessage' => 'ABC123']);
    assert_true($r->ok, 'sent debe ser ok');
    assert_same('ABC123', $r->providerMessageId, 'conserva providerMessageId');
    assert_null($r->error, 'sent no tiene error');
    assert_same(['idMessage' => 'ABC123'], $r->rawResponse, 'conserva rawResponse');
});

test('MessageResult::failed marca no-ok y conserva el error', function (): void {
    $r = MessageResult::failed('timeout', ['x' => 1]);
    assert_true($r->ok === false, 'failed no debe ser ok');
    assert_null($r->providerMessageId, 'failed no tiene providerMessageId');
    assert_same('timeout', $r->error, 'conserva error');
    assert_same(['x' => 1], $r->rawResponse, 'conserva rawResponse');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php MessageResultTest`
Expected: FAIL — `Class "App\Domain\Integrations\MessageResult" not found`.

- [ ] **Step 3: Write the Domain files**

Create `app/Domain/Integrations/MessageRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

/*
|--------------------------------------------------------------------------
| MessageRequest — lo único que un módulo de negocio construye para enviar.
|--------------------------------------------------------------------------
| El caller nunca conoce el proveedor: solo declara canal, destinatario,
| cuerpo y metadatos (source, record_id, subject, dedupe_key, ...).
*/
final class MessageRequest
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $body,
        public readonly array $meta = []
    ) {
    }
}
```

Create `app/Domain/Integrations/MessageResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

/*
|--------------------------------------------------------------------------
| MessageResult — resultado uniforme de un envío (nunca una excepción).
|--------------------------------------------------------------------------
*/
final class MessageResult
{
    /** @param array<string, mixed> $rawResponse */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerMessageId,
        public readonly ?string $error,
        public readonly array $rawResponse = []
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function sent(string $providerMessageId, array $raw = []): self
    {
        return new self(true, $providerMessageId, null, $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function failed(string $error, array $raw = []): self
    {
        return new self(false, null, $error, $raw);
    }
}
```

Create `app/Domain/Integrations/MessageChannelInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface MessageChannelInterface
{
    /** Clave estable del canal, p. ej. "whatsapp" | "email". */
    public function key(): string;

    public function send(MessageRequest $request): MessageResult;
}
```

Create `app/Domain/Integrations/ApiConnectorInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface ApiConnectorInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<int, string>   $headers
     * @return array{status:int, body:string, json:array}
     */
    public function request(string $method, string $url, array $payload = [], array $headers = []): array;
}
```

Create `app/Domain/Integrations/IntegrationLogRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface IntegrationLogRepositoryInterface
{
    /** @param array<string, mixed> $meta */
    public function record(
        string $channel,
        string $driver,
        string $recipientMasked,
        string $status,
        ?string $providerMessageId,
        ?string $error,
        array $meta
    ): void;

    /** Número de envíos del canal en los últimos $windowSeconds (para rate-limit). */
    public function countRecent(string $channel, int $windowSeconds): int;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php MessageResultTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Integrations tests/Integrations/MessageResultTest.php
git commit -m "feat(integrations): contratos de dominio (MessageRequest/Result + puertos)"
```

---

## Task 2: ChannelRegistry

**Files:**
- Create: `app/Application/Integrations/ChannelRegistry.php`
- Test: `tests/Integrations/ChannelRegistryTest.php`

**Interfaces:**
- Consumes: `MessageChannelInterface` (Task 1).
- Produces:
  - `ChannelRegistry(array $definitions)` where `$definitions` is `array<string, array{driver:string, factory:callable():MessageChannelInterface}>`.
  - `has(string $channelKey): bool`
  - `get(string $channelKey): MessageChannelInterface` (memoizes the factory; throws `RuntimeException` if missing).
  - `driver(string $channelKey): string` (returns the driver name; `'unknown'` if missing).

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/ChannelRegistryTest.php`:

```php
<?php
// tests/Integrations/ChannelRegistryTest.php
declare(strict_types=1);

use App\Application\Integrations\ChannelRegistry;
use App\Domain\Integrations\MessageChannelInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;

function fakeChannel(string $key): MessageChannelInterface
{
    return new class($key) implements MessageChannelInterface {
        public function __construct(private string $k) {}
        public function key(): string { return $this->k; }
        public function send(MessageRequest $r): MessageResult { return MessageResult::sent('x'); }
    };
}

test('el registry resuelve un canal registrado y memoiza la instancia', function (): void {
    $registry = new ChannelRegistry([
        'whatsapp' => ['driver' => 'green_api', 'factory' => fn() => fakeChannel('whatsapp')],
    ]);
    assert_true($registry->has('whatsapp'), 'has whatsapp');
    $a = $registry->get('whatsapp');
    $b = $registry->get('whatsapp');
    assert_true($a === $b, 'memoiza la misma instancia');
    assert_same('whatsapp', $a->key(), 'la instancia es el canal correcto');
    assert_same('green_api', $registry->driver('whatsapp'), 'expone el driver');
});

test('el registry reporta canales ausentes y falla al resolverlos', function (): void {
    $registry = new ChannelRegistry([]);
    assert_true($registry->has('whatsapp') === false, 'no tiene whatsapp');
    assert_same('unknown', $registry->driver('whatsapp'), 'driver desconocido');
    assert_throws(\RuntimeException::class, fn() => $registry->get('whatsapp'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php ChannelRegistryTest`
Expected: FAIL — `Class "App\Application\Integrations\ChannelRegistry" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Application/Integrations/ChannelRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Integrations;

use App\Domain\Integrations\MessageChannelInterface;

/*
|--------------------------------------------------------------------------
| ChannelRegistry — resuelve clave de canal → instancia (lazy + memoizada).
|--------------------------------------------------------------------------
| Se construye desde config/integrations.php (vía IntegrationsFactory).
| Guarda el "driver" por canal para el logging, sin acoplar la interfaz
| de canal a ese detalle.
*/
final class ChannelRegistry
{
    /** @var array<string, MessageChannelInterface> */
    private array $resolved = [];

    /**
     * @param array<string, array{driver:string, factory:callable():MessageChannelInterface}> $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    public function has(string $channelKey): bool
    {
        return isset($this->definitions[$channelKey]);
    }

    public function get(string $channelKey): MessageChannelInterface
    {
        if (!$this->has($channelKey)) {
            throw new \RuntimeException("Canal de integración no registrado: {$channelKey}");
        }

        if (!isset($this->resolved[$channelKey])) {
            $factory = $this->definitions[$channelKey]['factory'];
            $this->resolved[$channelKey] = $factory();
        }

        return $this->resolved[$channelKey];
    }

    public function driver(string $channelKey): string
    {
        return (string) ($this->definitions[$channelKey]['driver'] ?? 'unknown');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php ChannelRegistryTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Integrations/ChannelRegistry.php tests/Integrations/ChannelRegistryTest.php
git commit -m "feat(integrations): ChannelRegistry (resolución de canal por clave)"
```

---

## Task 3: RateLimiter

**Files:**
- Create: `app/Application/Integrations/RateLimiter.php`
- Test: `tests/Integrations/RateLimiterTest.php`

**Interfaces:**
- Consumes: `IntegrationLogRepositoryInterface` (Task 1).
- Produces:
  - `RateLimiter(array $limits, IntegrationLogRepositoryInterface $logs)` where `$limits` is `array<string, array{max:int, window_seconds:int}>`.
  - `allow(string $channel): bool` — `true` when no limit configured or `max <= 0`; otherwise `countRecent(channel, window) < max`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/RateLimiterTest.php`:

```php
<?php
// tests/Integrations/RateLimiterTest.php
declare(strict_types=1);

use App\Application\Integrations\RateLimiter;
use App\Domain\Integrations\IntegrationLogRepositoryInterface;

/** Log repo falso que devuelve un conteo fijo. */
function fakeLogRepoReturning(int $count): IntegrationLogRepositoryInterface
{
    return new class($count) implements IntegrationLogRepositoryInterface {
        public function __construct(private int $count) {}
        public function record(string $channel, string $driver, string $recipientMasked, string $status, ?string $providerMessageId, ?string $error, array $meta): void {}
        public function countRecent(string $channel, int $windowSeconds): int { return $this->count; }
    };
}

test('permite cuando el conteo está bajo el máximo', function (): void {
    $rl = new RateLimiter(['whatsapp' => ['max' => 30, 'window_seconds' => 60]], fakeLogRepoReturning(29));
    assert_true($rl->allow('whatsapp'), 'bajo el límite → permite');
});

test('bloquea cuando el conteo alcanza el máximo', function (): void {
    $rl = new RateLimiter(['whatsapp' => ['max' => 30, 'window_seconds' => 60]], fakeLogRepoReturning(30));
    assert_true($rl->allow('whatsapp') === false, 'en el límite → bloquea');
});

test('permite cuando el canal no tiene límite configurado', function (): void {
    $rl = new RateLimiter([], fakeLogRepoReturning(9999));
    assert_true($rl->allow('whatsapp'), 'sin config → permite');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php RateLimiterTest`
Expected: FAIL — `Class "App\Application\Integrations\RateLimiter" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Application/Integrations/RateLimiter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Integrations;

use App\Domain\Integrations\IntegrationLogRepositoryInterface;

/*
|--------------------------------------------------------------------------
| RateLimiter — límite básico por canal (envíos por ventana).
|--------------------------------------------------------------------------
| Reusa int_logs como contador (vía countRecent), evitando infraestructura
| nueva. Patrón de límite por ventana análogo a LoginRateLimitService.
*/
final class RateLimiter
{
    /** @param array<string, array{max:int, window_seconds:int}> $limits */
    public function __construct(
        private readonly array $limits,
        private readonly IntegrationLogRepositoryInterface $logs
    ) {
    }

    public function allow(string $channel): bool
    {
        $cfg = $this->limits[$channel] ?? null;
        if ($cfg === null) {
            return true;
        }

        $max = (int) ($cfg['max'] ?? 0);
        if ($max <= 0) {
            return true;
        }

        $window = (int) ($cfg['window_seconds'] ?? 60);
        return $this->logs->countRecent($channel, $window) < $max;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php RateLimiterTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Integrations/RateLimiter.php tests/Integrations/RateLimiterTest.php
git commit -m "feat(integrations): RateLimiter por canal (cuenta sobre int_logs)"
```

---

## Task 4: NotificationDispatcher (façade)

**Files:**
- Create: `app/Application/Integrations/NotificationDispatcher.php`
- Test: `tests/Integrations/NotificationDispatcherTest.php`

**Interfaces:**
- Consumes: `ChannelRegistry` (Task 2), `RateLimiter` (Task 3), `IntegrationLogRepositoryInterface` (Task 1), `MessageRequest`/`MessageResult`/`MessageChannelInterface` (Task 1).
- Produces:
  - `NotificationDispatcher(ChannelRegistry $channels, IntegrationLogRepositoryInterface $logs, RateLimiter $rateLimiter)`.
  - `send(MessageRequest $request): MessageResult` — resolves channel, enforces rate limit, calls `channel->send()`, **always** records to the log repo with a **masked** recipient, and **never throws** (catches `\Throwable` → `failed`).
  - Status mapping: success → `sent`; rate-limited / unknown channel → `skipped`; provider error or exception → `failed`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/NotificationDispatcherTest.php`:

```php
<?php
// tests/Integrations/NotificationDispatcherTest.php
declare(strict_types=1);

use App\Application\Integrations\ChannelRegistry;
use App\Application\Integrations\NotificationDispatcher;
use App\Application\Integrations\RateLimiter;
use App\Domain\Integrations\IntegrationLogRepositoryInterface;
use App\Domain\Integrations\MessageChannelInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;

/** Log repo falso que captura la última llamada a record(). */
final class SpyLogRepo implements IntegrationLogRepositoryInterface
{
    /** @var array<string,mixed>|null */
    public ?array $last = null;
    public int $recordCalls = 0;
    public function record(string $channel, string $driver, string $recipientMasked, string $status, ?string $providerMessageId, ?string $error, array $meta): void
    {
        $this->recordCalls++;
        $this->last = compact('channel', 'driver', 'recipientMasked', 'status', 'providerMessageId', 'error', 'meta');
    }
    public function countRecent(string $channel, int $windowSeconds): int { return 0; }
}

/** Canal falso configurable. */
function chan(string $key, callable $send): MessageChannelInterface
{
    return new class($key, $send) implements MessageChannelInterface {
        /** @var callable */ private $send;
        public function __construct(private string $k, callable $send) { $this->send = $send; }
        public function key(): string { return $this->k; }
        public function send(MessageRequest $r): MessageResult { return ($this->send)($r); }
    };
}

function makeDispatcher(ChannelRegistry $reg, SpyLogRepo $logs, array $limits = []): NotificationDispatcher
{
    return new NotificationDispatcher($reg, $logs, new RateLimiter($limits, $logs));
}

test('envío exitoso registra "sent" con id de proveedor y enmascara el destinatario', function (): void {
    $logs = new SpyLogRepo();
    $reg = new ChannelRegistry([
        'whatsapp' => ['driver' => 'green_api', 'factory' => fn() => chan('whatsapp', fn() => MessageResult::sent('MSG1'))],
    ]);
    $d = makeDispatcher($reg, $logs);
    $res = $d->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok, 'resultado ok');
    assert_same('MSG1', $res->providerMessageId, 'id propagado');
    assert_same('sent', $logs->last['status'], 'log status sent');
    assert_same('green_api', $logs->last['driver'], 'log driver');
    assert_true(str_contains($logs->last['recipientMasked'], '*'), 'destinatario enmascarado');
    assert_true(str_contains($logs->last['recipientMasked'], '5215512345678') === false, 'no guarda el número en claro');
});

test('canal desconocido devuelve failed y registra "skipped" sin lanzar', function (): void {
    $logs = new SpyLogRepo();
    $d = makeDispatcher(new ChannelRegistry([]), $logs);
    $res = $d->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok');
    assert_same('skipped', $logs->last['status'], 'log skipped');
});

test('rate-limit excedido devuelve failed y registra "skipped"', function (): void {
    $logs = new SpyLogRepo();
    $reg = new ChannelRegistry([
        'whatsapp' => ['driver' => 'green_api', 'factory' => fn() => chan('whatsapp', fn() => MessageResult::sent('X'))],
    ]);
    // max 0 con countRecent 0 → allow() true; usa max 1 y forzamos bloqueo con un repo que cuenta 5.
    $blockingLogs = new class extends SpyLogRepo {
        public function countRecent(string $channel, int $windowSeconds): int { return 5; }
    };
    $d = new NotificationDispatcher($reg, $blockingLogs, new RateLimiter(['whatsapp' => ['max' => 1, 'window_seconds' => 60]], $blockingLogs));
    $res = $d->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok por rate limit');
    assert_same('skipped', $blockingLogs->last['status'], 'log skipped');
});

test('una excepción del canal se captura: failed + log, nunca propaga', function (): void {
    $logs = new SpyLogRepo();
    $reg = new ChannelRegistry([
        'whatsapp' => ['driver' => 'green_api', 'factory' => fn() => chan('whatsapp', function () { throw new \RuntimeException('boom'); })],
    ]);
    $d = makeDispatcher($reg, $logs);
    $res = $d->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok');
    assert_same('failed', $logs->last['status'], 'log failed');
    assert_true($logs->recordCalls === 1, 'registró exactamente una vez');
});

test('un canal que devuelve failed registra "failed"', function (): void {
    $logs = new SpyLogRepo();
    $reg = new ChannelRegistry([
        'whatsapp' => ['driver' => 'green_api', 'factory' => fn() => chan('whatsapp', fn() => MessageResult::failed('http 500'))],
    ]);
    $d = makeDispatcher($reg, $logs);
    $res = $d->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok');
    assert_same('failed', $logs->last['status'], 'log failed');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php NotificationDispatcherTest`
Expected: FAIL — `Class "App\Application\Integrations\NotificationDispatcher" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Application/Integrations/NotificationDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Integrations;

use App\Domain\Integrations\IntegrationLogRepositoryInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;

/*
|--------------------------------------------------------------------------
| NotificationDispatcher — fachada pública de envío.
|--------------------------------------------------------------------------
| Único punto que un módulo de negocio usa para enviar. Resuelve el canal,
| aplica rate-limit, delega el envío y SIEMPRE registra el intento con el
| destinatario enmascarado. NUNCA propaga excepciones: degrada a failed.
*/
final class NotificationDispatcher
{
    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly IntegrationLogRepositoryInterface $logs,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function send(MessageRequest $request): MessageResult
    {
        $channelKey = $request->channel;
        $masked = self::maskRecipient($request->recipient);

        try {
            if (!$this->channels->has($channelKey)) {
                $result = MessageResult::failed("Canal no disponible: {$channelKey}");
                $this->logs->record($channelKey, 'unknown', $masked, 'skipped', null, $result->error, $request->meta);
                return $result;
            }

            $driver = $this->channels->driver($channelKey);

            if (!$this->rateLimiter->allow($channelKey)) {
                $result = MessageResult::failed('rate_limited');
                $this->logs->record($channelKey, $driver, $masked, 'skipped', null, $result->error, $request->meta);
                return $result;
            }

            $result = $this->channels->get($channelKey)->send($request);
            $status = $result->ok ? 'sent' : 'failed';
            $this->logs->record($channelKey, $driver, $masked, $status, $result->providerMessageId, $result->error, $request->meta);
            return $result;
        } catch (\Throwable $e) {
            $result = MessageResult::failed(self::sanitizeError($e->getMessage()));
            $driver = $this->channels->has($channelKey) ? $this->channels->driver($channelKey) : 'unknown';
            $this->logs->record($channelKey, $driver, $masked, 'failed', null, $result->error, $request->meta);
            return $result;
        }
    }

    /** Enmascara teléfono/email conservando los extremos; nunca persiste el valor en claro. */
    private static function maskRecipient(string $recipient): string
    {
        $value = trim($recipient);
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', max($len, 1));
        }
        return substr($value, 0, 2) . str_repeat('*', $len - 4) . substr($value, -2);
    }

    /** Recorta el mensaje de error y evita volcar payloads/secretos largos. */
    private static function sanitizeError(string $message): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        return substr($clean, 0, 480);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php NotificationDispatcherTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Integrations/NotificationDispatcher.php tests/Integrations/NotificationDispatcherTest.php
git commit -m "feat(integrations): NotificationDispatcher (log siempre, nunca propaga, enmascara)"
```

---

## Task 5: HttpApiConnector

**Files:**
- Create: `app/Infrastructure/Integrations/Http/HttpApiConnector.php`
- Test: `tests/Integrations/HttpApiConnectorTest.php`

**Interfaces:**
- Consumes: `ApiConnectorInterface` (Task 1).
- Produces:
  - `HttpApiConnector(int $defaultTimeout = 15)`.
  - `request(string $method, string $url, array $payload = [], array $headers = []): array` — POSTs JSON; on transport failure returns `['status' => 0, 'body' => '<error>', 'json' => []]` (never throws).

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/HttpApiConnectorTest.php`:

```php
<?php
// tests/Integrations/HttpApiConnectorTest.php
declare(strict_types=1);

use App\Infrastructure\Integrations\Http\HttpApiConnector;

test('un host inalcanzable devuelve status 0 sin lanzar excepción', function (): void {
    $connector = new HttpApiConnector(1); // timeout 1s
    // Puerto 9 (discard) en loopback: conexión rechazada/timeout determinista.
    $res = $connector->request('POST', 'http://127.0.0.1:9/nope', ['a' => 1]);
    assert_same(0, $res['status'], 'status 0 en fallo de transporte');
    assert_true(is_array($res['json']), 'json siempre es array');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php HttpApiConnectorTest`
Expected: FAIL — `Class "App\Infrastructure\Integrations\Http\HttpApiConnector" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Infrastructure/Integrations/Http/HttpApiConnector.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Http;

use App\Domain\Integrations\ApiConnectorInterface;

/*
|--------------------------------------------------------------------------
| HttpApiConnector — cliente HTTP genérico (cURL) con manejo uniforme.
|--------------------------------------------------------------------------
| Timeout configurable. Nunca lanza por fallo de red: devuelve status 0.
| El cuerpo JSON se decodifica en 'json' (array vacío si no aplica).
*/
final class HttpApiConnector implements ApiConnectorInterface
{
    public function __construct(private readonly int $defaultTimeout = 15)
    {
    }

    public function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        $ch = curl_init();

        $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->defaultTimeout,
            CURLOPT_TIMEOUT        => $this->defaultTimeout,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_POSTFIELDS     => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            return ['status' => 0, 'body' => $error !== '' ? $error : 'transport_error', 'json' => []];
        }

        $bodyStr = (string) $body;
        $decoded = json_decode($bodyStr, true);

        return [
            'status' => $status,
            'body'   => $bodyStr,
            'json'   => is_array($decoded) ? $decoded : [],
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php HttpApiConnectorTest`
Expected: PASS (1 test). (May take ~1s due to the connection timeout.)

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Integrations/Http/HttpApiConnector.php tests/Integrations/HttpApiConnectorTest.php
git commit -m "feat(integrations): HttpApiConnector (cURL, timeout, errores uniformes)"
```

---

## Task 6: GreenApiWhatsappChannel

**Files:**
- Create: `app/Infrastructure/Integrations/Channels/GreenApiWhatsappChannel.php`
- Test: `tests/Integrations/GreenApiWhatsappChannelTest.php`

**Interfaces:**
- Consumes: `ApiConnectorInterface`, `MessageChannelInterface`, `MessageRequest`, `MessageResult` (Task 1).
- Produces:
  - `GreenApiWhatsappChannel(ApiConnectorInterface $http, array $config)` where `$config` has `base_url`, `instance_id`, `token`.
  - `key(): string` → `'whatsapp'`.
  - `send(MessageRequest $r): MessageResult` — normalizes phone → `<digits>@c.us`, POSTs `sendMessage`, maps `idMessage` → `providerMessageId`. Invalid phone (no digits) → `failed`. Non-2xx or missing `idMessage` → `failed`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/GreenApiWhatsappChannelTest.php`:

```php
<?php
// tests/Integrations/GreenApiWhatsappChannelTest.php
declare(strict_types=1);

use App\Domain\Integrations\ApiConnectorInterface;
use App\Domain\Integrations\MessageRequest;
use App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel;

/** Conector falso que captura la URL/payload y devuelve una respuesta fija. */
final class FakeConnector implements ApiConnectorInterface
{
    public ?string $url = null;
    /** @var array<string,mixed> */
    public array $payload = [];
    /** @param array{status:int, body:string, json:array} $response */
    public function __construct(private array $response) {}
    public function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        $this->url = $url;
        $this->payload = $payload;
        return $this->response;
    }
}

function greenConfig(): array
{
    return ['base_url' => 'https://api.green-api.com', 'instance_id' => '1101', 'token' => 'TKN', 'timeout' => 15];
}

test('key() es "whatsapp"', function (): void {
    $c = new GreenApiWhatsappChannel(new FakeConnector(['status' => 200, 'body' => '', 'json' => []]), greenConfig());
    assert_same('whatsapp', $c->key());
});

test('normaliza el teléfono a chatId <digitos>@c.us y mapea idMessage', function (): void {
    $conn = new FakeConnector(['status' => 200, 'body' => '{"idMessage":"BAE5"}', 'json' => ['idMessage' => 'BAE5']]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', '+52 (55) 1234-5678', 'hola'));
    assert_true($res->ok, 'ok');
    assert_same('BAE5', $res->providerMessageId, 'mapea idMessage');
    assert_same('5255123 45678@c.us'[0], $conn->payload['chatId'][0], 'chatId comienza con dígito'); // sanity
    assert_same('525512345678@c.us', $conn->payload['chatId'], 'chatId solo dígitos + @c.us');
    assert_true(str_contains((string) $conn->url, '/waInstance1101/sendMessage/TKN'), 'URL Green API correcta');
});

test('teléfono sin dígitos → failed sin llamar al proveedor', function (): void {
    $conn = new FakeConnector(['status' => 200, 'body' => '', 'json' => ['idMessage' => 'X']]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', 'sin-numero', 'hola'));
    assert_true($res->ok === false, 'no ok');
    assert_null($conn->url, 'no se llamó al proveedor');
});

test('respuesta no-2xx → failed', function (): void {
    $conn = new FakeConnector(['status' => 500, 'body' => 'err', 'json' => []]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok en 500');
});

test('2xx sin idMessage → failed', function (): void {
    $conn = new FakeConnector(['status' => 200, 'body' => '{}', 'json' => []]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok sin idMessage');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php GreenApiWhatsappChannelTest`
Expected: FAIL — `Class "App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Infrastructure/Integrations/Channels/GreenApiWhatsappChannel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Channels;

use App\Domain\Integrations\ApiConnectorInterface;
use App\Domain\Integrations\MessageChannelInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;

/*
|--------------------------------------------------------------------------
| GreenApiWhatsappChannel — envío de texto por Green API.
|--------------------------------------------------------------------------
| Green API encapsulado aquí: la normalización teléfono → chatId vive
| dentro del canal; el caller solo pasa un teléfono.
|   POST {base_url}/waInstance{instance}/sendMessage/{token}
|   body: { "chatId": "<digitos>@c.us", "message": "<texto>" }
*/
final class GreenApiWhatsappChannel implements MessageChannelInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly ApiConnectorInterface $http,
        private readonly array $config
    ) {
    }

    public function key(): string
    {
        return 'whatsapp';
    }

    public function send(MessageRequest $request): MessageResult
    {
        $chatId = $this->toChatId($request->recipient);
        if ($chatId === null) {
            return MessageResult::failed('Teléfono inválido (sin dígitos)');
        }

        $url = sprintf(
            '%s/waInstance%s/sendMessage/%s',
            rtrim((string) ($this->config['base_url'] ?? ''), '/'),
            (string) ($this->config['instance_id'] ?? ''),
            (string) ($this->config['token'] ?? '')
        );

        $response = $this->http->request('POST', $url, [
            'chatId'  => $chatId,
            'message' => $request->body,
        ]);

        $status = (int) ($response['status'] ?? 0);
        $json = (array) ($response['json'] ?? []);

        if ($status < 200 || $status >= 300) {
            return MessageResult::failed("Green API HTTP {$status}", $response);
        }

        $idMessage = (string) ($json['idMessage'] ?? '');
        if ($idMessage === '') {
            return MessageResult::failed('Respuesta Green API sin idMessage', $response);
        }

        return MessageResult::sent($idMessage, $response);
    }

    /** Convierte un teléfono libre a "<solo-digitos>@c.us"; null si no hay dígitos. */
    private function toChatId(string $recipient): ?string
    {
        $digits = preg_replace('/\D+/', '', $recipient) ?? '';
        if ($digits === '') {
            return null;
        }
        return $digits . '@c.us';
    }
}
```

> Note for the implementer: the `'5255123 45678@c.us'[0]` line in the test is a sanity assertion on the first character only; the meaningful assertion is `'525512345678@c.us'`. The input `+52 (55) 1234-5678` strips to digits `525512345678`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php GreenApiWhatsappChannelTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Integrations/Channels/GreenApiWhatsappChannel.php tests/Integrations/GreenApiWhatsappChannelTest.php
git commit -m "feat(integrations): GreenApiWhatsappChannel (chatId, sendMessage, mapeo)"
```

---

## Task 7: EmailChannel (adapts MailerInterface)

**Files:**
- Create: `app/Infrastructure/Integrations/Channels/EmailChannel.php`
- Test: `tests/Integrations/EmailChannelTest.php`

**Interfaces:**
- Consumes: `MailerInterface` (`App\Domain\Interfaces\MailerInterface`, method `enviar(MensajeCorreo $m): void`), `MensajeCorreo` (`App\Application\DTO\Mail\MensajeCorreo` — ctor `(string $destinatario, string $nombreDestinatario, string $asunto, string $html)`), `MessageChannelInterface`/`MessageRequest`/`MessageResult` (Task 1).
- Produces:
  - `EmailChannel(MailerInterface $mailer)`.
  - `key(): string` → `'email'`.
  - `send(MessageRequest $r): MessageResult` — builds `MensajeCorreo` (subject from `meta['subject']`, default `'Notificación'`; recipient name from `meta['name']`, default `''`), delegates to `$mailer->enviar()`, returns `sent('email')`. Mailer throws → `failed`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/EmailChannelTest.php`:

```php
<?php
// tests/Integrations/EmailChannelTest.php
declare(strict_types=1);

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Interfaces\MailerInterface;
use App\Infrastructure\Integrations\Channels\EmailChannel;

/** Mailer falso que captura el último mensaje o lanza. */
final class SpyMailer implements MailerInterface
{
    public ?MensajeCorreo $last = null;
    public function __construct(private bool $throw = false) {}
    public function enviar(MensajeCorreo $mensaje): void
    {
        if ($this->throw) { throw new \RuntimeException('smtp down'); }
        $this->last = $mensaje;
    }
}

test('key() es "email"', function (): void {
    assert_same('email', (new EmailChannel(new SpyMailer()))->key());
});

test('adapta MessageRequest a MensajeCorreo (asunto desde meta) y delega', function (): void {
    $mailer = new SpyMailer();
    $c = new EmailChannel($mailer);
    $res = $c->send(new MessageRequest('email', 'a@b.com', '<p>hola</p>', ['subject' => 'Bienvenida', 'name' => 'Ada']));
    assert_true($res->ok, 'ok');
    assert_same('a@b.com', $mailer->last->destinatario, 'destinatario');
    assert_same('Ada', $mailer->last->nombreDestinatario, 'nombre');
    assert_same('Bienvenida', $mailer->last->asunto, 'asunto desde meta');
    assert_same('<p>hola</p>', $mailer->last->html, 'cuerpo html');
});

test('si el mailer lanza, devuelve failed sin propagar', function (): void {
    $c = new EmailChannel(new SpyMailer(true));
    $res = $c->send(new MessageRequest('email', 'a@b.com', 'x'));
    assert_true($res->ok === false, 'failed cuando el mailer lanza');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php EmailChannelTest`
Expected: FAIL — `Class "App\Infrastructure\Integrations\Channels\EmailChannel" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Infrastructure/Integrations/Channels/EmailChannel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Channels;

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Integrations\MessageChannelInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;
use App\Domain\Interfaces\MailerInterface;

/*
|--------------------------------------------------------------------------
| EmailChannel — adapta el MailerInterface existente al puerto de canal.
|--------------------------------------------------------------------------
| Demuestra que la abstracción es realmente multi-canal desde el día 1:
| cambiar channel de "whatsapp" a "email" reusa el correo ya configurado.
*/
final class EmailChannel implements MessageChannelInterface
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function key(): string
    {
        return 'email';
    }

    public function send(MessageRequest $request): MessageResult
    {
        try {
            $this->mailer->enviar(new MensajeCorreo(
                $request->recipient,
                (string) ($request->meta['name'] ?? ''),
                (string) ($request->meta['subject'] ?? 'Notificación'),
                $request->body
            ));
            return MessageResult::sent('email');
        } catch (\Throwable $e) {
            return MessageResult::failed($e->getMessage());
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php EmailChannelTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Integrations/Channels/EmailChannel.php tests/Integrations/EmailChannelTest.php
git commit -m "feat(integrations): EmailChannel (adaptador del MailerInterface)"
```

---

## Task 8: `int_logs` schema + IntegrationLogRepository

**Files:**
- Create: `database/schema/modules/integrations.sql`
- Create: `app/Infrastructure/Integrations/Repositories/IntegrationLogRepository.php`
- Test: `tests/Integrations/IntegrationsSchemaTest.php`

**Interfaces:**
- Consumes: `IntegrationLogRepositoryInterface` (Task 1), `BaseRepository` (`App\Kernel\BaseClasses\BaseRepository` — `protected query/queryOne/execute/insert`).
- Produces:
  - `IntegrationLogRepository` implementing the interface against table `int_logs`.
  - SQL file creating `int_logs` (idempotent) + the three RBAC permissions `integrations.ver|enviar|configurar` granted to `administrador` (idempotent).

> The PDO repository is plumbing verified by the acceptance demo (Task 11). The automated test here guards **idempotency** of the schema (acceptance criterion #9), which is pure file inspection — no DB required.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/IntegrationsSchemaTest.php`:

```php
<?php
// tests/Integrations/IntegrationsSchemaTest.php
declare(strict_types=1);

test('el bootstrap SQL de integrations es idempotente y crea int_logs', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/integrations.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `int_logs`'), 'crea int_logs idempotente');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `auth_permisos`'), 'inserta permisos idempotente');
    assert_true(str_contains($sql, 'integrations.enviar'), 'incluye permiso integrations.enviar');
    assert_true(str_contains($sql, 'recipient_masked'), 'columna recipient_masked presente');
});

test('IntegrationLogRepository implementa el puerto del dominio', function (): void {
    $ref = new ReflectionClass(\App\Infrastructure\Integrations\Repositories\IntegrationLogRepository::class);
    assert_true(
        $ref->implementsInterface(\App\Domain\Integrations\IntegrationLogRepositoryInterface::class),
        'implementa IntegrationLogRepositoryInterface'
    );
});
```

> `ROOT_PATH` is defined by the test bootstrap (`tests/lib/bootstrap.php`), same constant used across the suite.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php IntegrationsSchemaTest`
Expected: FAIL — file not found / class not found.

- [ ] **Step 3: Write the SQL**

Create `database/schema/modules/integrations.sql`:

```sql
-- database/schema/modules/integrations.sql
-- Bootstrap del módulo Integraciones y Conectores (Fase 1).
-- Crea la tabla int_logs y los permisos RBAC. Idempotente (re-ejecutable).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `int_logs` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel`             VARCHAR(40)     NOT NULL,
  `driver`              VARCHAR(60)     NOT NULL,
  `recipient_masked`    VARCHAR(190)    NOT NULL,
  `status`              VARCHAR(20)     NOT NULL,
  `provider_message_id` VARCHAR(190)    DEFAULT NULL,
  `error`               VARCHAR(500)    DEFAULT NULL,
  `meta`                JSON            DEFAULT NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`          BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_int_logs_channel` (`channel`, `status`),
  KEY `idx_int_logs_provider_msg` (`provider_message_id`),
  KEY `idx_int_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permisos RBAC ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver integraciones',     'integrations.ver',        'integrations', 'Acceso de lectura al módulo de integraciones'),
('Enviar mensajes',       'integrations.enviar',     'integrations', 'Disparar envíos salientes vía la fachada'),
('Configurar integraciones','integrations.configurar','integrations', 'Gestionar la configuración del módulo');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'integrations.ver','integrations.enviar','integrations.configurar'
)
WHERE `r`.`slug` = 'administrador';

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 4: Write the repository**

Create `app/Infrastructure/Integrations/Repositories/IntegrationLogRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Repositories;

use App\Domain\Integrations\IntegrationLogRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

/*
|--------------------------------------------------------------------------
| IntegrationLogRepository — persiste cada intento de envío en int_logs.
|--------------------------------------------------------------------------
| Las tablas int_* NO se exponen por el CRUD Engine; las gestiona este repo.
*/
final class IntegrationLogRepository extends BaseRepository implements IntegrationLogRepositoryInterface
{
    protected string $table = 'int_logs';

    public function record(
        string $channel,
        string $driver,
        string $recipientMasked,
        string $status,
        ?string $providerMessageId,
        ?string $error,
        array $meta
    ): void {
        $this->execute(
            "INSERT INTO int_logs
                (channel, driver, recipient_masked, status, provider_message_id, error, meta, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
            [
                $channel,
                $driver,
                $recipientMasked,
                $status,
                $providerMessageId,
                $error,
                $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                isset($meta['user_id']) ? (int) $meta['user_id'] : null,
            ]
        );
    }

    public function countRecent(string $channel, int $windowSeconds): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM int_logs
             WHERE channel = ? AND status = 'sent'
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$channel, $windowSeconds]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php IntegrationsSchemaTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/schema/modules/integrations.sql app/Infrastructure/Integrations/Repositories/IntegrationLogRepository.php tests/Integrations/IntegrationsSchemaTest.php
git commit -m "feat(integrations): int_logs schema (idempotente) + IntegrationLogRepository"
```

---

## Task 9: Config files + manifest + `.env.example`

**Files:**
- Create: `config/integrations.php`
- Create: `config/modules/integrations.php`
- Modify: `.env.example`
- Test: `tests/Integrations/IntegrationsConfigTest.php`

**Interfaces:**
- Consumes: `EnvLoader` (`App\Kernel\EnvLoader::get`), the channel classes (Tasks 5–7).
- Produces:
  - `config/integrations.php` returning `['channels' => [...], 'rate_limit' => [...], 'webhooks' => [...]]`.
  - `config/modules/integrations.php` returning the module manifest array (`clave => 'integrations'`, `bootstrap_sql`, `permisos`, ...).

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/IntegrationsConfigTest.php`:

```php
<?php
// tests/Integrations/IntegrationsConfigTest.php
declare(strict_types=1);

test('config/integrations.php define canales whatsapp y email con clases existentes', function (): void {
    $cfg = require ROOT_PATH . '/config/integrations.php';
    assert_true(isset($cfg['channels']['whatsapp']), 'canal whatsapp definido');
    assert_true(isset($cfg['channels']['email']), 'canal email definido');
    assert_same('green_api', $cfg['channels']['whatsapp']['driver'], 'driver whatsapp');
    assert_same('mailer_adapter', $cfg['channels']['email']['driver'], 'driver email');
    assert_true(class_exists($cfg['channels']['whatsapp']['class']), 'clase whatsapp existe');
    assert_true(class_exists($cfg['channels']['email']['class']), 'clase email existe');
    assert_true(isset($cfg['rate_limit']['whatsapp']['max']), 'rate-limit whatsapp definido');
});

test('config/modules/integrations.php es un manifiesto válido', function (): void {
    $m = require ROOT_PATH . '/config/modules/integrations.php';
    assert_same('integrations', $m['clave'], 'clave del módulo');
    assert_true($m['obligatorio'] === false, 'módulo opcional');
    assert_same('database/schema/modules/integrations.sql', $m['bootstrap_sql'], 'apunta a su SQL');
    assert_true(in_array('integrations.enviar', $m['permisos'], true), 'declara el permiso enviar');
    assert_same([], $m['cruds'], 'no expone CRUDs (tablas int_* fuera del Engine)');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php IntegrationsConfigTest`
Expected: FAIL — config files do not exist.

- [ ] **Step 3: Write `config/integrations.php`**

```php
<?php

declare(strict_types=1);

use App\Kernel\EnvLoader;

/*
|--------------------------------------------------------------------------
| config/integrations.php — mapa de canales, límites y webhooks (stub F2).
|--------------------------------------------------------------------------
| 'class' es el FQCN del canal; lo resuelve ChannelRegistry vía
| IntegrationsFactory. Las credenciales solo viven en .env.
*/
return [
    'channels' => [
        'whatsapp' => [
            'driver'  => 'green_api',
            'class'   => \App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel::class,
            'enabled' => (bool) EnvLoader::get('GREEN_API_ENABLED', false),
            'config'  => [
                'base_url'    => EnvLoader::get('GREEN_API_BASE_URL', 'https://api.green-api.com'),
                'instance_id' => EnvLoader::get('GREEN_API_INSTANCE', ''),
                'token'       => EnvLoader::get('GREEN_API_TOKEN', ''),
                'timeout'     => (int) EnvLoader::get('GREEN_API_TIMEOUT', 15),
            ],
        ],
        'email' => [
            'driver'  => 'mailer_adapter',
            'class'   => \App\Infrastructure\Integrations\Channels\EmailChannel::class,
            'enabled' => true,
            'config'  => [],
        ],
    ],

    'rate_limit' => [
        'whatsapp' => ['max' => 30, 'window_seconds' => 60],
    ],

    // Fase 2 (solo diseño): validadores de webhooks por proveedor.
    'webhooks' => [],
];
```

- [ ] **Step 4: Write `config/modules/integrations.php`**

```php
<?php

declare(strict_types=1);

// Manifiesto del módulo Integraciones y Conectores (Fase 1).
// Capa desacoplada para enviar mensajes y consumir APIs externas.
// Bootstrap (tabla int_logs + permisos) en schema/modules/integrations.sql.
return [
    'clave'         => 'integrations',
    'nombre'        => 'Integraciones y Conectores',
    'descripcion'   => 'Capa desacoplada para enviar mensajes, consumir APIs y (F2) recibir webhooks.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/integrations.sql',
    'cruds'         => [],
    'permisos'      => ['integrations.ver', 'integrations.enviar', 'integrations.configurar'],
    'menu'          => [],
    'providers'     => [],
];
```

- [ ] **Step 5: Append Green API vars to `.env.example`**

Add this block at the end of `.env.example` (read the file first to match its existing style):

```dotenv

# ── Integraciones — Green API (WhatsApp) ──
GREEN_API_ENABLED=false
GREEN_API_BASE_URL=https://api.green-api.com
GREEN_API_INSTANCE=
GREEN_API_TOKEN=
GREEN_API_TIMEOUT=15
# Fase 2:
GREEN_API_WEBHOOK_TOKEN=
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php tests/run.php IntegrationsConfigTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add config/integrations.php config/modules/integrations.php .env.example tests/Integrations/IntegrationsConfigTest.php
git commit -m "feat(integrations): config de canales, manifiesto de módulo y .env.example"
```

---

## Task 10: IntegrationsFactory + container binding + vertical toggle

**Files:**
- Create: `app/Application/Integrations/IntegrationsFactory.php`
- Modify: `config/container.php`
- Modify: `config/vertical.php`
- Test: `tests/Integrations/IntegrationsFactoryTest.php`

**Interfaces:**
- Consumes: `ChannelRegistry`, `RateLimiter`, `NotificationDispatcher` (Tasks 2–4), `GreenApiWhatsappChannel`, `EmailChannel` (Tasks 6–7), `IntegrationLogRepository`, `HttpApiConnector` (Tasks 8, 5), `MailerInterface`, `Config`, `Connection`.
- Produces:
  - `IntegrationsFactory::buildChannels(array $channelsConfig, MailerInterface $mailer, ApiConnectorInterface $http): array` — returns `array<string, array{driver:string, factory:callable}>` for **enabled** channels only (ChannelRegistry-ready).
  - `IntegrationsFactory::dispatcher(): NotificationDispatcher` — wires everything from `Config::get('integrations')` + real `IntegrationLogRepository` + real `HttpApiConnector` + the configured mailer.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/IntegrationsFactoryTest.php`:

```php
<?php
// tests/Integrations/IntegrationsFactoryTest.php
declare(strict_types=1);

use App\Application\Integrations\IntegrationsFactory;
use App\Domain\Integrations\ApiConnectorInterface;
use App\Domain\Interfaces\MailerInterface;
use App\Infrastructure\Integrations\Channels\EmailChannel;
use App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel;

test('buildChannels solo incluye canales habilitados y construye la clase correcta', function (): void {
    $mailer = new SpyMailer();          // de EmailChannelTest (cargado por el runner)
    $http = new class implements ApiConnectorInterface {
        public function request(string $m, string $u, array $p = [], array $h = []): array { return ['status' => 200, 'body' => '', 'json' => []]; }
    };

    $channelsConfig = [
        'whatsapp' => ['driver' => 'green_api', 'enabled' => false, 'config' => ['base_url' => 'x', 'instance_id' => '1', 'token' => 't']],
        'email'    => ['driver' => 'mailer_adapter', 'enabled' => true, 'config' => []],
    ];

    $built = IntegrationsFactory::buildChannels($channelsConfig, $mailer, $http);
    assert_true(isset($built['email']), 'email habilitado incluido');
    assert_true(isset($built['whatsapp']) === false, 'whatsapp deshabilitado excluido');
    assert_same('mailer_adapter', $built['email']['driver'], 'driver propagado');
    assert_true(($built['email']['factory'])() instanceof EmailChannel, 'factory construye EmailChannel');

    // Y con whatsapp habilitado:
    $channelsConfig['whatsapp']['enabled'] = true;
    $built2 = IntegrationsFactory::buildChannels($channelsConfig, $mailer, $http);
    assert_true(($built2['whatsapp']['factory'])() instanceof GreenApiWhatsappChannel, 'factory construye GreenApiWhatsappChannel');
});
```

> `SpyMailer` is declared in `tests/Integrations/EmailChannelTest.php`; the runner `require`s all `*Test.php` files in sorted order before executing, and `EmailChannelTest.php` sorts before `IntegrationsFactoryTest.php`, so the class is available.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php IntegrationsFactoryTest`
Expected: FAIL — `Class "App\Application\Integrations\IntegrationsFactory" not found`.

- [ ] **Step 3: Write the factory**

Create `app/Application/Integrations/IntegrationsFactory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Integrations;

use App\Domain\Integrations\ApiConnectorInterface;
use App\Domain\Interfaces\MailerInterface;
use App\Infrastructure\Integrations\Channels\EmailChannel;
use App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel;
use App\Infrastructure\Integrations\Http\HttpApiConnector;
use App\Infrastructure\Integrations\Repositories\IntegrationLogRepository;
use App\Infrastructure\Mail\LogMailer;
use App\Infrastructure\Mail\PhpMailerMailer;
use App\Kernel\Config;

/*
|--------------------------------------------------------------------------
| IntegrationsFactory — única vía de construcción de la fachada.
|--------------------------------------------------------------------------
| Usada por el binding del container y por los CRUD handlers (que se
| instancian con `new $class()` sin DI, por lo que no pueden recibir el
| dispatcher por constructor). Un solo camino de construcción.
*/
final class IntegrationsFactory
{
    private static ?NotificationDispatcher $cached = null;

    public static function dispatcher(): NotificationDispatcher
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $config = (array) Config::get('integrations', []);
        $logs = new IntegrationLogRepository();
        $http = new HttpApiConnector((int) (($config['channels']['whatsapp']['config']['timeout'] ?? 15)));
        $mailer = self::mailer();

        $registry = new ChannelRegistry(
            self::buildChannels((array) ($config['channels'] ?? []), $mailer, $http)
        );
        $rateLimiter = new RateLimiter((array) ($config['rate_limit'] ?? []), $logs);

        return self::$cached = new NotificationDispatcher($registry, $logs, $rateLimiter);
    }

    /**
     * @param array<string, array{driver?:string, enabled?:bool, config?:array}> $channelsConfig
     * @return array<string, array{driver:string, factory:callable():\App\Domain\Integrations\MessageChannelInterface}>
     */
    public static function buildChannels(array $channelsConfig, MailerInterface $mailer, ApiConnectorInterface $http): array
    {
        $out = [];
        foreach ($channelsConfig as $key => $def) {
            if (!(bool) ($def['enabled'] ?? false)) {
                continue;
            }
            $driver = (string) ($def['driver'] ?? $key);
            $cfg = (array) ($def['config'] ?? []);
            $out[$key] = [
                'driver'  => $driver,
                'factory' => static function () use ($driver, $cfg, $mailer, $http) {
                    return match ($driver) {
                        'green_api'      => new GreenApiWhatsappChannel($http, $cfg),
                        'mailer_adapter' => new EmailChannel($mailer),
                        default          => throw new \RuntimeException("Driver de canal no soportado: {$driver}"),
                    };
                },
            ];
        }
        return $out;
    }

    /** Replica la resolución de mailer del container (smtp vs log). */
    private static function mailer(): MailerInterface
    {
        $mailConfig = (array) Config::get('mail', []);
        return ($mailConfig['driver'] ?? 'log') === 'smtp'
            ? new PhpMailerMailer($mailConfig)
            : new LogMailer();
    }
}
```

- [ ] **Step 4: Run the factory test to verify it passes**

Run: `php tests/run.php IntegrationsFactoryTest`
Expected: PASS (1 test).

- [ ] **Step 5: Add the vertical toggle**

In `config/vertical.php`, inside the `'modules'` array, add the `integrations` line after `marketing`:

```php
        'marketing'      => true,
        'integrations'   => true,
```

- [ ] **Step 6: Add the guarded container binding**

In `config/container.php`, find the marketing toggle block:

```php
    // ── Módulo Marketing (bindings condicionales al toggle; ver config/modules/marketing.php) ──
    if ((bool) Config::get('vertical.modules.marketing', false)) {
```

Immediately **before** that marketing block, insert:

```php
    // ── Módulo Integraciones (binding condicional al toggle; ver config/modules/integrations.php) ──
    if ((bool) Config::get('vertical.modules.integrations', false)) {
        $container->singleton(
            \App\Application\Integrations\NotificationDispatcher::class,
            static fn() => \App\Application\Integrations\IntegrationsFactory::dispatcher()
        );
    }
```

- [ ] **Step 7: Run the full suite to confirm nothing regressed**

Run: `php tests/run.php Integrations`
Expected: PASS for all `tests/Integrations/*` files.

- [ ] **Step 8: Commit**

```bash
git add app/Application/Integrations/IntegrationsFactory.php config/container.php config/vertical.php tests/Integrations/IntegrationsFactoryTest.php
git commit -m "feat(integrations): IntegrationsFactory + binding guarded + toggle vertical"
```

---

## Task 11: Demo CRUD action handler (acceptance demo)

**Files:**
- Create: `app/Application/Crud/Handlers/EnviarWhatsappDemoHandler.php`
- Modify: `config/crud_handlers.php`
- Modify: `config/cruds/demo_clientes.json`
- Test: `tests/Integrations/EnviarWhatsappDemoHandlerTest.php`

**Interfaces:**
- Consumes: `CrudActionHandlerInterface` (`App\Domain\Interfaces\CrudActionHandlerInterface` — `handle(CrudActionContext $ctx): void`), `CrudActionContext` (`record()`, `recordId()`, `userId()`), `IntegrationsFactory` (Task 10), `MessageRequest` (Task 1).
- Produces:
  - `EnviarWhatsappDemoHandler` (no-arg constructor) registered as `'enviar_whatsapp_demo'`.
  - A `confirmar_wa` row action on `demo_clientes` gated by `integrations.enviar`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integrations/EnviarWhatsappDemoHandlerTest.php`:

```php
<?php
// tests/Integrations/EnviarWhatsappDemoHandlerTest.php
declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Handlers\EnviarWhatsappDemoHandler;
use App\Domain\Interfaces\CrudActionHandlerInterface;

function actionContext(?array $record): CrudActionContext
{
    return new CrudActionContext(
        'demo_clientes', 'dom_demo_clientes', 'id',
        1, '127.0.0.1', 10, $record, 'confirmar_wa', []
    );
}

test('el handler está registrado en la whitelist y existe la clase', function (): void {
    $map = require ROOT_PATH . '/config/crud_handlers.php';
    assert_true(isset($map['enviar_whatsapp_demo']), 'clave registrada');
    assert_true(class_exists($map['enviar_whatsapp_demo']), 'clase existe');
});

test('el handler implementa CrudActionHandlerInterface y es instanciable sin args', function (): void {
    $h = new EnviarWhatsappDemoHandler();
    assert_true($h instanceof CrudActionHandlerInterface, 'implementa el contrato');
});

test('sin teléfono el handler no hace nada (no lanza)', function (): void {
    $h = new EnviarWhatsappDemoHandler();
    $h->handle(actionContext(['nombre' => 'Ada', 'telefono' => '']));
    assert_true(true, 'no lanzó con teléfono vacío');
});
```

> The empty-phone guard returns before reaching `IntegrationsFactory::dispatcher()`, so no DB/HTTP is touched. The full send path is verified by manual acceptance (below).

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php EnviarWhatsappDemoHandlerTest`
Expected: FAIL — class / whitelist key not found.

- [ ] **Step 3: Write the handler**

Create `app/Application/Crud/Handlers/EnviarWhatsappDemoHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Integrations\IntegrationsFactory;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Interfaces\CrudActionHandlerInterface;

/*
|--------------------------------------------------------------------------
| EnviarWhatsappDemoHandler — handler delgado de demo (módulo de negocio).
|--------------------------------------------------------------------------
| Mapea el registro → MessageRequest y delega en la fachada. No conoce
| Green API. "Qué/a quién" vive aquí; "cómo se envía" en integrations.
| Se instancia con `new $class()` (sin DI): resuelve el dispatcher con
| IntegrationsFactory.
*/
final class EnviarWhatsappDemoHandler implements CrudActionHandlerInterface
{
    public function handle(CrudActionContext $ctx): void
    {
        $record = $ctx->record() ?? [];
        $telefono = trim((string) ($record['telefono'] ?? ''));
        if ($telefono === '') {
            return; // regla de negocio: sin teléfono no hay nada que enviar
        }

        $body = sprintf(
            'Hola %s, confirmamos tu registro. ¡Gracias!',
            (string) ($record['nombre'] ?? '')
        );

        IntegrationsFactory::dispatcher()->send(new MessageRequest(
            channel: 'whatsapp',
            recipient: $telefono,
            body: $body,
            meta: [
                'source'    => 'crud:demo_clientes',
                'record_id' => $ctx->recordId(),
                'user_id'   => $ctx->userId(),
            ]
        ));
    }
}
```

- [ ] **Step 4: Register the handler in the whitelist**

In `config/crud_handlers.php`, add inside the returned array (after the demo entries):

```php
    'enviar_whatsapp_demo' => \App\Application\Crud\Handlers\EnviarWhatsappDemoHandler::class,
```

- [ ] **Step 5: Add the row action to `demo_clientes.json`**

In `config/cruds/demo_clientes.json`, in `actions.row`, add this object after the `contrato` link entry (keep valid JSON — add a comma after the `contrato` object):

```json
      { "name": "confirmar_wa", "type": "handler", "handler": "enviar_whatsapp_demo",
        "label": "Confirmar por WhatsApp", "icon": "bi-whatsapp",
        "permission": "integrations.enviar",
        "confirm": "¿Enviar confirmación por WhatsApp?" }
```

- [ ] **Step 6: Validate JSON + run the test**

Run: `php -r "json_decode(file_get_contents('config/cruds/demo_clientes.json'), true, 512, JSON_THROW_ON_ERROR); echo 'JSON OK';"`
Expected: `JSON OK`.

Run: `php tests/run.php EnviarWhatsappDemoHandlerTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Run the full Integrations suite**

Run: `php tests/run.php Integrations`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Application/Crud/Handlers/EnviarWhatsappDemoHandler.php config/crud_handlers.php config/cruds/demo_clientes.json tests/Integrations/EnviarWhatsappDemoHandlerTest.php
git commit -m "feat(integrations): demo de acción CRUD que dispara WhatsApp (handler delgado)"
```

---

## Manual acceptance (after Task 11)

Run once against a configured environment to confirm the spec's Fase 1 acceptance criteria end-to-end:

1. Apply the schema: ensure `database/schema/modules/integrations.sql` ran (re-run it; it must not error — criterion #9).
2. Set `.env`: `GREEN_API_ENABLED=true` + real `GREEN_API_INSTANCE`/`GREEN_API_TOKEN`.
3. Log in as admin, open `/admin/crud/demo_clientes`, click **Confirmar por WhatsApp** on a row that has a phone. Confirm the modal, verify the message arrives and a `sent` row exists in `int_logs` with a **masked** recipient and no token (criteria #3, #4).
4. Temporarily set a bad token; repeat — the action must NOT error the page; `int_logs` shows `failed` (criterion #5).
5. Confirm `config/vertical.php` `integrations => false` disables the binding without breaking the app (criterion #8).

---

## Self-Review

**Spec coverage (Fase 1):**
- §4 Domain contracts → Task 1. ✓
- §4 `NotificationDispatcher` + `ChannelRegistry` → Tasks 2, 4. ✓
- §4 `HttpApiConnector` → Task 5. ✓
- §4 `GreenApiWhatsappChannel` → Task 6. ✓
- §4 `EmailChannel` adapting `MailerInterface` → Task 7. ✓
- §4/§13 `int_logs` (only new table) → Task 8. ✓
- §4/§11 manifest, toggle, RBAC permissions, `config/integrations.php`, `.env` → Tasks 8, 9, 10. ✓
- §4/§8 CRUD Engine integration via thin handler → Task 11. ✓
- §12.4 bindings → Task 10. ✓
- §16 Fase 1 test harness (fake channel, dispatcher success/fail/rate-limit, masking, no-propagation) → Tasks 2–11 tests. ✓
- §17 acceptance criteria → Manual acceptance section + idempotency test (Task 8). ✓
- Rate-limit (§7.3) → Task 3. ✓
- F2–F4 (webhooks, queue, templates, reminders, multi-account) → **intentionally excluded** (matches "diseñado, no implementado").

**Placeholder scan:** No TBD/TODO; every code step contains full code; every test has real assertions. ✓

**Type consistency:** `MessageResult::sent/failed`, `MessageChannelInterface::key()/send()`, `ApiConnectorInterface::request()` returning `{status,body,json}`, `IntegrationLogRepositoryInterface::record()/countRecent()`, `ChannelRegistry::has()/get()/driver()`, `RateLimiter::allow()`, `NotificationDispatcher::send()`, `IntegrationsFactory::buildChannels()/dispatcher()` — names and signatures are used identically across all tasks. ✓

**Known codebase gotchas addressed:** CRUD handlers built with `new $class()` (no DI) → handled via `IntegrationsFactory::dispatcher()`; no global container → factory reads `Config` directly; tests are DB-free using fakes; bindings toggle-guarded like marketing. ✓
