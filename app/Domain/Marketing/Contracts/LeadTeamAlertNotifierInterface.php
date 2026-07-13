<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface LeadTeamAlertNotifierInterface
{
    /** @param array<string, mixed> $lead */
    public function notifyLeadVerified(array $lead): void;
}
