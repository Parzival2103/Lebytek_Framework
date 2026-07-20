<?php

declare(strict_types=1);

use App\Infrastructure\Marketing\LeadVerifiedWhatsAppNotifier;
use Lebytek\Framework\Domain\Integrations\MessageChannelInterface;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Domain\Integrations\MessageResult;

final class FakeWhatsAppChannel implements MessageChannelInterface
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

function setLeadWaEnv(string $numbers, string $appUrl = 'https://lebytek.com'): void
{
    $_ENV['MKT_ALERT_WHATSAPP_NUMBERS'] = $numbers;
    putenv('MKT_ALERT_WHATSAPP_NUMBERS=' . $numbers);
    $_ENV['APP_URL'] = $appUrl;
    putenv('APP_URL=' . $appUrl);
}

test('notifier sends WhatsApp to each configured number with lead details', function (): void {
    setLeadWaEnv('5211111111111,5212222222222');

    $channel = new FakeWhatsAppChannel();
    $notifier = new LeadVerifiedWhatsAppNotifier($channel, true);

    $notifier->notifyLeadVerified([
        'id'       => 42,
        'nombre'   => 'María López',
        'email'    => 'maria@test.com',
        'telefono' => '5512345678',
        'mensaje'  => 'Quiero demo',
    ]);

    assert_same(2, count($channel->requests));
    assert_same('5211111111111', $channel->requests[0]->recipient);
    assert_same('5212222222222', $channel->requests[1]->recipient);
    assert_same('whatsapp', $channel->requests[0]->channel);
    assert_same('whatsapp', $channel->requests[1]->channel);
    assert_true(str_contains($channel->requests[0]->body, 'María López'));
    assert_true(str_contains($channel->requests[0]->body, 'maria@test.com'));
    assert_true(str_contains($channel->requests[0]->body, '5512345678'));
    assert_true(str_contains($channel->requests[0]->body, 'https://lebytek.com/admin/crud/mkt_leads/42'));
    assert_same('lead_email_verified', $channel->requests[0]->meta['source']);
    assert_same(42, $channel->requests[0]->meta['record_id']);
});

test('notifier skips send when MKT_ALERT_WHATSAPP_NUMBERS is empty', function (): void {
    setLeadWaEnv('');

    $channel = new FakeWhatsAppChannel();
    $notifier = new LeadVerifiedWhatsAppNotifier($channel, true);

    $notifier->notifyLeadVerified([
        'id'     => 1,
        'nombre' => 'Ana',
        'email'  => 'ana@test.com',
    ]);

    assert_same(0, count($channel->requests));
});

test('notifier skips send when disabled', function (): void {
    setLeadWaEnv('5211111111111');

    $channel = new FakeWhatsAppChannel();
    $notifier = new LeadVerifiedWhatsAppNotifier($channel, false);

    $notifier->notifyLeadVerified([
        'id'     => 1,
        'nombre' => 'Ana',
        'email'  => 'ana@test.com',
    ]);

    assert_same(0, count($channel->requests));
});
