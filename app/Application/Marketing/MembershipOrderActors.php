<?php

declare(strict_types=1);

namespace App\Application\Marketing;

final class MembershipOrderActors
{
    /** Sentinel for Stripe webhook / system automations (authorized_by nullable BIGINT). */
    public const SYSTEM_WEBHOOK = 0;
}
