<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;

final class ActivarPlanOrdenPagadaUseCase
{
    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly ActivateMembershipFromOrderService $activator,
    ) {}

    /**
     * @return array<string, mixed> orden actualizada
     */
    public function ejecutar(int $orderId, int $actorId): array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new \InvalidArgumentException('Orden no encontrada.');
        }

        return $this->activator->fromPaidRetry($order, $actorId);
    }
}
