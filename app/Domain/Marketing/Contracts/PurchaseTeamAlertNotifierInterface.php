<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface PurchaseTeamAlertNotifierInterface
{
    /**
     * @param array<string, mixed> $order
     * @return bool true if at least one WhatsApp alert was accepted by the channel
     */
    public function notifyTransferPending(array $order): bool;
}
