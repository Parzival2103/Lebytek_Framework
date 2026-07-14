<?php

declare(strict_types=1);

use App\Infrastructure\Marketing\PurchaseWhatsAppNotifier;
use Lebytek\Framework\Domain\Integrations\MessageChannelInterface;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Domain\Integrations\MessageResult;

final class PurchaseFakeWhatsAppChannel implements MessageChannelInterface
{
    /** @var list<MessageRequest> */
    public array $requests = [];

    public function key(): string
    {
        return 'whatsapp';
    }

    public function send(MessageRequest $request): MessageResult
    {
        $this->requests[] = $request;

        return MessageResult::sent('fake-id');
    }
}

function setPurchaseWaEnv(string $purchaseNumbers, string $fallbackNumbers = '', string $appUrl = 'https://lebytek.com'): void
{
    $_ENV['MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS'] = $purchaseNumbers;
    putenv('MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS='.$purchaseNumbers);
    $_ENV['MKT_ALERT_WHATSAPP_NUMBERS'] = $fallbackNumbers;
    putenv('MKT_ALERT_WHATSAPP_NUMBERS='.$fallbackNumbers);
    $_ENV['APP_URL'] = $appUrl;
    putenv('APP_URL='.$appUrl);
}

test('PurchaseWhatsAppNotifier sends once per configured number', function (): void {
    setPurchaseWaEnv('5213333333333,5214444444444');

    $channel = new PurchaseFakeWhatsAppChannel();
    $notifier = new PurchaseWhatsAppNotifier($channel, true);

    $notifier->notifyTransferPending([
        'id' => 9,
        'public_id' => '01JORDENTEST00000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'nombre' => 'Juan Pérez',
        'email' => 'juan@test.com',
        'telefono' => '5511111111',
        'empresa' => 'ACME',
    ]);

    assert_same(2, count($channel->requests));
    assert_true(str_contains($channel->requests[0]->body, '01JORDENTEST00000000000001'));
    assert_true(str_contains($channel->requests[0]->body, 'https://lebytek.com/crud/mkt_ordenes/9'));
    assert_same('membership_transfer_pending', $channel->requests[0]->meta['source']);
});

test('PurchaseWhatsAppNotifier falls back to MKT_ALERT when purchase env empty', function (): void {
    setPurchaseWaEnv('', '5215555555555');

    $channel = new PurchaseFakeWhatsAppChannel();
    $notifier = new PurchaseWhatsAppNotifier($channel, true);

    $notifier->notifyTransferPending(['id' => 1, 'public_id' => 'ORD1', 'paquete_slug' => 'starter', 'ciclo' => 'monthly']);

    assert_same(1, count($channel->requests));
    assert_same('5215555555555', $channel->requests[0]->recipient);
});

test('PurchaseWhatsAppNotifier skips when disabled', function (): void {
    setPurchaseWaEnv('5215555555555');

    $channel = new PurchaseFakeWhatsAppChannel();
    $notifier = new PurchaseWhatsAppNotifier($channel, false);

    $notifier->notifyTransferPending(['id' => 1, 'public_id' => 'ORD1']);

    assert_same(0, count($channel->requests));
});
