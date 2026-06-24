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
class SpyLogRepo implements IntegrationLogRepositoryInterface
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
    public function recent(int $limit = 50, ?string $channel = null): array { return []; }
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
