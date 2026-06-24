<?php
// tests/Integrations/DemoProvisioningServiceTest.php
declare(strict_types=1);

use App\Application\Integrations\DemoProvisioningService;
use App\Domain\Integrations\IntegrationAccount;
use App\Domain\Integrations\IntegrationAccountRepositoryInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;
use App\Domain\Integrations\MessageSenderInterface;
use App\Domain\Integrations\PartnerConnectorInterface;

final class FakeAccountRepo implements IntegrationAccountRepositoryInterface
{
    public array $saved = [];
    public function findDefault(string $p): ?IntegrationAccount { return null; }
    public function findById(int $id): ?IntegrationAccount { return null; }
    public function findByLead(int $l, string $p): ?IntegrationAccount { return null; }
    public function save(IntegrationAccount $a): int { $this->saved[] = $a; return 7; }
    public function markDefault(int $id, string $p): void {}
}

final class FakePartner implements PartnerConnectorInterface
{
    public function __construct(private bool $avail) {}
    public function isAvailable(): bool { return $this->avail; }
    public function createInstance(string $label): array { return ['instance_id' => 'AUTO-1', 'token' => 'AUTO-TOK']; }
}

final class SpyMessageSender implements MessageSenderInterface
{
    public ?MessageRequest $last = null;
    public function send(MessageRequest $r): MessageResult { $this->last = $r; return MessageResult::sent('x'); }
}

test('provisionManual guarda cuenta ligada al lead y manda correo con link', function () {
    $repo = new FakeAccountRepo();
    $disp = new SpyMessageSender();
    $svc = new DemoProvisioningService($repo, new FakePartner(false), $disp, 'https://demo.test');

    $acc = $svc->provisionManual(99, 'Juan', 'juan@x.com', 'INST-1', 'TOK-1');
    assert_same(99, $acc->leadId);
    assert_same('manual', $acc->provisionedVia);
    assert_same('INST-1', $repo->saved[0]->instanceId);
    assert_same('email', $disp->last->channel);
    assert_same('juan@x.com', $disp->last->recipient);
    assert_true(str_contains($disp->last->body, '/wa/activar/'), 'el correo debe incluir el link de activación');
});

test('provisionAuto usa partner API para crear la instancia', function () {
    $repo = new FakeAccountRepo();
    $svc = new DemoProvisioningService($repo, new FakePartner(true), new SpyMessageSender(), 'https://demo.test');
    $acc = $svc->provisionAuto(99, 'Juan', 'juan@x.com');
    assert_same('AUTO-1', $acc->instanceId);
    assert_same('partner_api', $acc->provisionedVia);
});
