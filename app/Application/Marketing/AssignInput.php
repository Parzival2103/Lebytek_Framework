<?php

declare(strict_types=1);

namespace App\Application\Marketing;

/**
 * Plain input DTO for LandingExperimentAssigner::assign(). No Kernel Request
 * type here (Anti-deuda §J) — Presentation extracts cookies/query and builds this.
 */
final class AssignInput
{
    /** @param array<string,string> $cookies raw cookie values keyed by cookie name (lb_vid, lb_var, lb_preview) */
    public function __construct(
        public readonly string $forceVariant,
        public readonly array $cookies,
        public readonly bool $isHttps,
    ) {
    }
}
