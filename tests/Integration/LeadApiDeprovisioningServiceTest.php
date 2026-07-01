<?php

declare(strict_types=1);

use App\Application\Marketing\LeadApiDeprovisioningService;
use App\Application\Marketing\LeadApiProvisioningService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\LeadApiLifecycleStatus;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class DeprovisionInMemoryLeadRepo implements LeadRepositoryInterface
{
    public array $rows = [];

    public function guardar(\App\Domain\Marketing\ValueObjects\LeadDraft $d): int
    {
        return 1;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function markApiProvisioned(int $id, string $p, string $e, string $instancePublicId = ''): void
    {
        $this->rows[$id]['api_tenant_public_id'] = $p;
        $this->rows[$id]['api_instance_public_id'] = $instancePublicId !== '' ? $instancePublicId : null;
        $this->rows[$id]['external_ref'] = $e;
        $this->rows[$id]['external_ref'] = $e;
        $this->rows[$id]['estado'] = 'demo_enviada';
        $this->rows[$id]['api_lifecycle_status'] = LeadApiLifecycleStatus::PROVISION_INITIATED;
    }

    public function markApiProvisionError(int $id, string $err): void
    {
        $this->rows[$id]['api_provision_error'] = $err;
        $this->rows[$id]['api_lifecycle_status'] = LeadApiLifecycleStatus::NONE;
    }

    public function markApiDeprovisionInitiated(int $id): void
    {
        $this->rows[$id]['api_provision_error'] = null;
        $this->rows[$id]['api_lifecycle_status'] = LeadApiLifecycleStatus::DEPROVISION_INITIATED;
        $this->rows[$id]['estado'] = 'demo_baja_pendiente';
    }

    public function markApiDeprovisionCompleted(int $id): void
    {
        unset($this->rows[$id]['api_tenant_public_id'], $this->rows[$id]['external_ref']);
        $this->rows[$id]['api_provision_error'] = null;
        $this->rows[$id]['api_lifecycle_status'] = LeadApiLifecycleStatus::DEPROVISIONED;
        $this->rows[$id]['estado'] = 'demo_baja';
    }

    public function findPendingDeprovisions(): array
    {
        $pending = [];
        foreach ($this->rows as $row) {
            if (($row['api_lifecycle_status'] ?? '') === LeadApiLifecycleStatus::DEPROVISION_INITIATED) {
                $pending[] = $row;
            }
        }

        return $pending;
    }

    public function findDemosOlderThanDays(int $days): array
    {
        return [];
    }
}

final class DeprovisionSequenceTransport implements LebytekApiTransport
{
    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        return array_shift($this->responses) ?? ['status' => 500, 'body' => '{}', 'error' => ''];
    }
}

final class DeprovisionRecordingTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];

    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses = [];

    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');

        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{"status":"ok"}', 'error' => ''];
    }
}

final class FailingMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $m): void
    {
        throw new \RuntimeException('SMTP down');
    }
}

test('LeadApiProvisioningService returns mail_failed when SMTP fails and does not mark demo_enviada', function () {
    $repo = new DeprovisionInMemoryLeadRepo();
    $repo->rows[5] = ['id' => 5, 'nombre' => 'Ana', 'email' => 'a@t.com', 'estado' => 'validada', 'api_tenant_public_id' => null];
    $transport = new DeprovisionSequenceTransport([
        ['status' => 201, 'body' => '{"publicId":"01JTENANT"}', 'error' => ''],
        ['status' => 202, 'body' => '{"publicId":"01JINST"}', 'error' => ''],
        ['status' => 201, 'body' => '{"token":"12|abc"}', 'error' => ''],
    ]);
    $api = new LebytekApiClient('https://api.test/v1', 'plat', 5, 1, $transport);
    $svc = new LeadApiProvisioningService($api, $repo, new FailingMailer());
    $result = $svc->provisionLead(5);
    assert_same('mail_failed', $result['status']);
    assert_same('validada', $repo->rows[5]['estado']);
    assert_true(! isset($repo->rows[5]['api_tenant_public_id']));
    assert_true(str_contains((string) $repo->rows[5]['api_provision_error'], 'SMTP down'));
});

test('LeadApiDeprovisioningService initiates deprovision without clearing tenant until confirmed', function () {
    $repo = new DeprovisionInMemoryLeadRepo();
    $repo->rows[7] = [
        'id' => 7,
        'nombre' => 'Luis',
        'email' => 'luis@test.com',
        'api_tenant_public_id' => '01JTENANT',
        'api_instance_public_id' => '01JINST',
        'estado' => 'demo_enviada',
    ];
    $transport = new DeprovisionSequenceTransport([
        ['status' => 202, 'body' => '{"accepted":true}', 'error' => ''],
        ['status' => 200, 'body' => '{"data":[]}', 'error' => ''],
    ]);
    $api = new LebytekApiClient('https://api.test/v1', 'plat', 5, 1, $transport);
    $svc = new LeadApiDeprovisioningService($api, $repo);
    $result = $svc->deprovisionLead(7);
    assert_same(1, $result['deleted']);
    assert_same('initiated', $result['status']);
    assert_same('demo_baja_pendiente', $repo->rows[7]['estado']);
    assert_same(LeadApiLifecycleStatus::DEPROVISION_INITIATED, $repo->rows[7]['api_lifecycle_status']);
    assert_same('01JTENANT', $repo->rows[7]['api_tenant_public_id']);
    assert_same(0, count($transport->responses));
});

test('LeadApiDeprovisioningService confirmPendingDeprovisions completes when API has no instances', function () {
    $repo = new DeprovisionInMemoryLeadRepo();
    $repo->rows[7] = [
        'id' => 7,
        'api_tenant_public_id' => '01JTENANT',
        'api_lifecycle_status' => LeadApiLifecycleStatus::DEPROVISION_INITIATED,
        'estado' => 'demo_baja_pendiente',
    ];
    $transport = new DeprovisionSequenceTransport([
        ['status' => 200, 'body' => '{"data":[]}', 'error' => ''],
    ]);
    $api = new LebytekApiClient('https://api.test/v1', 'plat', 5, 1, $transport);
    $svc = new LeadApiDeprovisioningService($api, $repo);
    $result = $svc->confirmPendingDeprovisions();
    assert_same(1, $result['pending']);
    assert_same(1, $result['confirmed']);
    assert_same('demo_baja', $repo->rows[7]['estado']);
    assert_same(LeadApiLifecycleStatus::DEPROVISIONED, $repo->rows[7]['api_lifecycle_status']);
    assert_true(! isset($repo->rows[7]['api_tenant_public_id']));
});

test('LebytekApiClient listInstances sends X-Tenant-Id', function () {
    $transport = new DeprovisionRecordingTransport();
    $transport->responses[] = ['status' => 200, 'body' => '{"data":[]}', 'error' => ''];
    $client = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);
    $instances = $client->listInstances('01JTENANT');
    assert_same([], $instances);
    $headers = implode("\n", $transport->calls[0]['headers']);
    assert_true(str_contains($headers, 'X-Tenant-Id: 01JTENANT'));
});

test('LebytekApiClient deleteInstance uses DELETE', function () {
    $transport = new DeprovisionRecordingTransport();
    $transport->responses[] = ['status' => 202, 'body' => '{"accepted":true}', 'error' => ''];
    $client = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);
    $client->deleteInstance('01JTENANT', '01JINST');
    assert_same('DELETE', $transport->calls[0]['method']);
    assert_true(str_contains($transport->calls[0]['url'], '/instances/01JINST'));
});
