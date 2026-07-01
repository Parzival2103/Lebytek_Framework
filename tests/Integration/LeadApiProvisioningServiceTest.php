<?php

declare(strict_types=1);

use App\Application\Marketing\LeadApiProvisioningService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\LeadApiLifecycleStatus;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class InMemoryLeadRepo implements LeadRepositoryInterface
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
        $this->rows[$id]['api_provision_error'] = null;
        $this->rows[$id]['api_lifecycle_status'] = LeadApiLifecycleStatus::PROVISION_INITIATED;
        $this->rows[$id]['estado'] = 'demo_enviada';
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
        return [];
    }

    public function findDemosOlderThanDays(int $days): array
    {
        return [];
    }
}

final class SequenceTransport implements LebytekApiTransport
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

final class LeadApiSpyMailer implements MailerInterface
{
    public ?MensajeCorreo $last = null;

    public function enviar(MensajeCorreo $m): void
    {
        $this->last = $m;
    }
}

test('LeadApiProvisioningService full flow persists lead and sends email', function () {
    $_ENV['LEBYTEK_API_URL'] = 'https://api.test/v1';
    $_ENV['MKT_EMAIL_DOCS_URL'] = 'https://docs.lebytek.com';

    $repo = new InMemoryLeadRepo();
    $repo->rows[5] = ['id' => 5, 'nombre' => 'Ana', 'email' => 'ana@test.com', 'api_tenant_public_id' => null];
    $transport = new SequenceTransport([
        ['status' => 201, 'body' => '{"publicId":"01JTENANT"}', 'error' => ''],
        ['status' => 202, 'body' => '{"publicId":"01JINST"}', 'error' => ''],
        ['status' => 201, 'body' => '{"token":"12|abc"}', 'error' => ''],
    ]);
    $api = new LebytekApiClient('https://api.test/v1', 'plat', 5, 1, $transport);
    $mailer = new LeadApiSpyMailer();
    $svc = new LeadApiProvisioningService($api, $repo, $mailer);
    $result = $svc->provisionLead(5);
    assert_same('ok', $result['status']);
    assert_same('01JTENANT', $repo->rows[5]['api_tenant_public_id']);
    assert_same('01JINST', $repo->rows[5]['api_instance_public_id']);
    assert_same('demo_enviada', $repo->rows[5]['estado']);
    assert_same(LeadApiLifecycleStatus::PROVISION_INITIATED, $repo->rows[5]['api_lifecycle_status']);
    assert_true($mailer->last !== null);
    assert_same('Tu acceso a la API está listo — Lebytek', $mailer->last->asunto);
    assert_true(str_contains($mailer->last->html, '12|abc'));
    assert_true(str_contains($mailer->last->html, 'https://api.test/v1'));
    assert_true(str_contains($mailer->last->html, 'https://docs.lebytek.com'));
    assert_true(str_contains($mailer->last->html, 'Ver documentación'));
    assert_true(! str_contains(strtolower($mailer->last->html), 'dashboard'));
    assert_true(! str_contains(strtolower($mailer->last->html), 'waapi'));
});

test('LeadApiProvisioningService skips when already provisioned', function () {
    $repo = new InMemoryLeadRepo();
    $repo->rows[5] = ['id' => 5, 'nombre' => 'Ana', 'email' => 'a@t.com', 'api_tenant_public_id' => 'EXISTING'];
    $transport = new SequenceTransport([]);
    $api = new LebytekApiClient('https://api.test/v1', 'plat', 5, 1, $transport);
    $mailer = new LeadApiSpyMailer();
    $svc = new LeadApiProvisioningService($api, $repo, $mailer);
    $result = $svc->provisionLead(5);
    assert_same('skipped', $result['status']);
    assert_same(0, count($transport->responses));
    assert_null($mailer->last);
});

test('LeadApiProvisioningService persists api_provision_error on failure', function () {
    $repo = new InMemoryLeadRepo();
    $repo->rows[5] = ['id' => 5, 'nombre' => 'Ana', 'email' => 'a@t.com', 'api_tenant_public_id' => null];
    $transport = new SequenceTransport([
        ['status' => 422, 'body' => '{"message":"slug taken"}', 'error' => ''],
    ]);
    $api = new LebytekApiClient('https://api.test/v1', 'plat', 5, 1, $transport);
    $svc = new LeadApiProvisioningService($api, $repo, new LeadApiSpyMailer());
    assert_throws(LebytekApiException::class, fn () => $svc->provisionLead(5));
    assert_same('slug taken', $repo->rows[5]['api_provision_error']);
});
