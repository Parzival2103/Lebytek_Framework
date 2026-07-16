<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;

final class AutorizarOrdenMembresiaUseCase
{
    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly ActivateMembershipFromOrderService $activator,
    ) {}

    /**
     * @return array<string, mixed> orden actualizada
     */
    public function ejecutar(int $orderId, int $authorizedBy): array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new \InvalidArgumentException('Orden no encontrada.');
        }

        $status = (string) ($order['status'] ?? '');
        if (! in_array($status, ['pending_transfer', 'awaiting_review'], true)) {
            throw new \InvalidArgumentException('La orden no está pendiente de autorización.');
        }

        return $this->activator->fromManualAuthorize($order, $authorizedBy);
    }
}
