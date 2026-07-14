<?php

declare(strict_types=1);

use App\Application\Marketing\AutorizarOrdenMembresiaUseCase;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class AuthorizeRecordingTransport implements LebytekApiTransport
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

final class AuthorizeMemOrderRepo implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $paid = false;
    public ?string $lastError = null;

    public function create(array $data): int
    {
        return 0;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return null;
    }

    public function markTransferNotified(int $orderId): void {}

    public function setApiActivationError(int $orderId, string $error): void
    {
        $this->lastError = $error;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['api_activation_error'] = $error;
        }
    }

    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->paid = true;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['status'] = 'paid';
            $this->rows[$orderId]['authorized_by'] = $authorizedBy;
        }
    }

    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
}

final class SpyMembershipMailer implements MailerInterface
{
    /** @var list<MensajeCorreo> */
    public array $sent = [];

    public function enviar(MensajeCorreo $mensaje): void
    {
        $this->sent[] = $mensaje;
    }
}

test('AutorizarOrdenMembresiaUseCase happy path activa plan y envía correo', function (): void {
    $transport = new AuthorizeRecordingTransport();
    $transport->responses[] = ['status' => 200, 'body' => '{"token":"99|membership-token"}', 'error' => ''];
    $api = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);

    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORD00000000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'mensajes_mes_limite_snapshot' => 5000,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];

    $mailer = new SpyMembershipMailer();
    $uc = new AutorizarOrdenMembresiaUseCase($orders, $api, $mailer);
    $uc->ejecutar(1, 7);

    assert_same(1, count($transport->calls));
    assert_true(str_contains($transport->calls[0]['url'], '/tenants/01JTENANT0000000000000001/activate-plan'));
    $body = json_decode((string) $transport->calls[0]['body'], true);
    assert_same('starter', $body['planSlug']);
    assert_same('monthly', $body['billingCycle']);
    assert_same('01JORD00000000000000000001', $body['orderExternalRef']);
    assert_same(5000, $body['messagesMonthlyLimit']);
    assert_true($orders->paid);
    assert_same(1, count($mailer->sent));
    assert_true(str_contains($mailer->sent[0]->html, '99|membership-token'));
});

test('AutorizarOrdenMembresiaUseCase falla sin tenant asociado', function (): void {
    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[2] = [
        'id' => 2,
        'public_id' => 'ORD2',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => null,
        'nombre' => 'X',
        'email' => 'x@test.com',
    ];

    $uc = new AutorizarOrdenMembresiaUseCase(
        $orders,
        new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new AuthorizeRecordingTransport()),
        new SpyMembershipMailer(),
    );

    assert_throws(\InvalidArgumentException::class, fn () => $uc->ejecutar(2, 1));
});
