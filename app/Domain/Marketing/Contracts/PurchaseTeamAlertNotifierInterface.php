<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface PurchaseTeamAlertNotifierInterface
{
    /** @param array<string, mixed> $order */
    public function notifyTransferPending(array $order): void;
}
