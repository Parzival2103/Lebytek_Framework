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
