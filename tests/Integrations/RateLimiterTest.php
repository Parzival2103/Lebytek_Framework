<?php
// tests/Integrations/RateLimiterTest.php
declare(strict_types=1);

use Lebytek\Framework\Application\Integrations\RateLimiter;
use Lebytek\Framework\Domain\Integrations\IntegrationLogRepositoryInterface;

/** Log repo falso que devuelve un conteo fijo. */
function fakeLogRepoReturning(int $count): IntegrationLogRepositoryInterface
{
    return new class($count) implements IntegrationLogRepositoryInterface {
        public function __construct(private int $count) {}
        public function record(string $channel, string $driver, string $recipientMasked, string $status, ?string $providerMessageId, ?string $error, array $meta): void {}
        public function countRecent(string $channel, int $windowSeconds): int { return $this->count; }
        public function recent(int $limit = 50, ?string $channel = null): array { return []; }
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
