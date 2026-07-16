<?php

declare(strict_types=1);

use App\Application\Marketing\CrearOrdenMembresiaUseCase;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Domain\Marketing\Contracts\PurchaseTeamAlertNotifierInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;

final class MemOrderInMemoryRepo implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    private int $nextId = 1;

    public function create(array $data): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = array_merge($data, ['id' => $id, 'transfer_notified_at' => null]);

        return $id;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findByPublicId(string $publicId): ?array
    {
        foreach ($this->rows as $row) {
            if (($row['public_id'] ?? '') === $publicId) {
                return $row;
            }
        }

        return null;
    }

    public function markTransferNotified(int $orderId): void
    {
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['transfer_notified_at'] = '2026-07-14 12:00:00';
        }
    }

    public function setApiActivationError(int $orderId, string $error): void {}
    public function clearApiActivationError(int $orderId): void {}
    public function markPaid(int $orderId, int $authorizedBy): void {}
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class MemContentInMemoryRepo implements MarketingContentRepositoryInterface
{
    /** @param array<string, mixed>|null $paquete */
    public function __construct(private ?array $paquete) {}

    public function bloquesPorPagina(string $pagina): array
    {
        return [];
    }

    public function paquetesActivos(): array
    {
        return $this->paquete !== null ? [$this->paquete] : [];
    }

    public function findPaqueteBySlug(string $slug, bool $requireActive = true): ?array
    {
        if ($this->paquete === null || ($this->paquete['slug'] ?? '') !== $slug) {
            return null;
        }

        return $this->paquete;
    }
}

final class MemLeadInMemoryRepo implements LeadRepositoryInterface
{
    /** @param array<string, mixed>|null $lead */
    public function __construct(private ?array $lead) {}

    public function guardar(LeadDraft $draft, ?array $emailVerification = null): int
    {
        return 0;
    }

    public function findById(int $id): ?array
    {
        return null;
    }

    public function findByEmailVerifyToken(string $token): ?array
    {
        return null;
    }

    public function incrementEmailVerifyAttempts(int $leadId): void {}
    public function markEmailVerified(int $leadId): void {}
    public function markApiProvisioned(
        int $leadId,
        string $tenantPublicId,
        string $externalRef,
        string $instancePublicId = '',
        ?int $paqueteId = null,
        string $planSlug = 'demo',
        int $demoDays = 30,
    ): void {}
    public function markApiProvisionError(int $leadId, string $error): void {}
    public function markApiDeprovisionInitiated(int $leadId): void {}
    public function markApiDeprovisionCompleted(int $leadId): void {}
    public function findDemosOlderThanDays(int $days): array
    {
        return [];
    }
    public function findDemosExpired(): array
    {
        return [];
    }
    public function findPendingDeprovisions(): array
    {
        return [];
    }
    public function findDemoPackageBySlug(string $slug): ?array
    {
        return null;
    }

    public function findLatestByEmail(string $email): ?array
    {
        if ($this->lead !== null && ($this->lead['email'] ?? '') === $email) {
            return $this->lead;
        }

        return null;
    }
}

final class SpyPurchaseNotifier implements PurchaseTeamAlertNotifierInterface
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function __construct(private readonly bool $ok = true) {}

    public function notifyTransferPending(array $order): bool
    {
        $this->calls[] = $order;

        return $this->ok;
    }
}

test('CrearOrdenMembresiaUseCase crea orden pending_transfer y notifica', function (): void {
    $orders = new MemOrderInMemoryRepo();
    $content = new MemContentInMemoryRepo([
        'id' => 2, 'slug' => 'starter', 'nombre' => 'Starter',
        'precio_mensual' => '2199', 'precio_anual' => '21990', 'mensajes_mes_limite' => 5000,
    ]);
    $leads = new MemLeadInMemoryRepo([
        'id' => 5, 'email' => 'buyer@test.com', 'api_tenant_public_id' => '01JTENANT0000000000000001',
    ]);
    $notifier = new SpyPurchaseNotifier(true);
    $uc = new CrearOrdenMembresiaUseCase($content, $orders, $leads, $notifier);

    $result = $uc->ejecutar('starter', [
        'nombre' => 'Buyer Test',
        'email' => 'buyer@test.com',
        'telefono' => '5512345678',
        'empresa' => 'ACME',
        'direccion' => 'Calle 1',
        'ciclo' => 'monthly',
    ]);
    $order = $result['order'];

    assert_true($result['alert_sent']);
    assert_same('pending_transfer', $order['status']);
    assert_same('starter', $order['paquete_slug']);
    assert_same(2199.0, (float) $order['precio_snapshot']);
    assert_same(5, $order['lead_id']);
    assert_same('01JTENANT0000000000000001', $order['api_tenant_public_id']);
    assert_same(1, count($notifier->calls));
    assert_true(($order['transfer_notified_at'] ?? null) !== null);
});

test('CrearOrdenMembresiaUseCase no marca transfer_notified_at si el aviso falla', function (): void {
    $orders = new MemOrderInMemoryRepo();
    $uc = new CrearOrdenMembresiaUseCase(
        new MemContentInMemoryRepo([
            'id' => 2, 'slug' => 'starter', 'precio_mensual' => '2199', 'precio_anual' => '21990', 'mensajes_mes_limite' => 5000,
        ]),
        $orders,
        new MemLeadInMemoryRepo(null),
        new SpyPurchaseNotifier(false),
    );

    $result = $uc->ejecutar('starter', [
        'nombre' => 'Buyer Test',
        'email' => 'buyer2@test.com',
        'telefono' => '5512345678',
        'empresa' => 'ACME',
        'direccion' => 'Calle 1',
        'ciclo' => 'monthly',
    ]);

    assert_true($result['alert_sent'] === false);
    assert_same('pending_transfer', $result['order']['status']);
    assert_true(($result['order']['transfer_notified_at'] ?? null) === null);
});

test('CrearOrden con metodo stripe no envía alerta transferencia', function (): void {
    $orders = new MemOrderInMemoryRepo();
    $content = new MemContentInMemoryRepo([
        'id' => 2, 'slug' => 'starter', 'nombre' => 'Starter',
        'precio_mensual' => '2199', 'precio_anual' => '21990', 'mensajes_mes_limite' => 5000,
    ]);
    $leads = new MemLeadInMemoryRepo([
        'id' => 5, 'email' => 'buyer@test.com', 'api_tenant_public_id' => '01JTENANT0000000000000001',
    ]);
    $notifier = new SpyPurchaseNotifier(true);
    $uc = new CrearOrdenMembresiaUseCase($content, $orders, $leads, $notifier);

    $result = $uc->ejecutar('starter', [
        'nombre' => 'Buyer Test',
        'email' => 'buyer@test.com',
        'telefono' => '5512345678',
        'empresa' => 'ACME',
        'direccion' => 'Calle 1',
        'ciclo' => 'monthly',
        'metodo_pago' => 'stripe',
    ]);

    assert_same('pending_payment', $result['order']['status']);
    assert_same('stripe', $result['order']['metodo_pago'] ?? null);
    assert_true($result['alert_sent'] === false);
    assert_same(0, count($notifier->calls));
    assert_true(($result['order']['transfer_notified_at'] ?? null) === null);
});

test('CrearOrdenMembresiaUseCase rechaza empresa y precio nulo', function (): void {
    $uc = new CrearOrdenMembresiaUseCase(
        new MemContentInMemoryRepo(['id' => 3, 'slug' => 'empresa', 'precio_mensual' => null, 'precio_anual' => null]),
        new MemOrderInMemoryRepo(),
        new MemLeadInMemoryRepo(null),
        new SpyPurchaseNotifier(),
    );

    assert_throws(\InvalidArgumentException::class, fn () => $uc->ejecutar('empresa', [
        'nombre' => 'X', 'email' => 'x@test.com', 'telefono' => '1', 'empresa' => 'Y', 'direccion' => 'Z', 'ciclo' => 'monthly',
    ]));
});
