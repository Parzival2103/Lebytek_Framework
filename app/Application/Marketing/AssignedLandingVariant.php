<?php

declare(strict_types=1);

namespace App\Application\Marketing;

/**
 * Result of LandingExperimentAssigner::assign(): the resolved variant plus the
 * cookie specs Presentation must apply before the response body.
 */
final class AssignedLandingVariant
{
    /** @param list<CookieSpec> $cookies */
    public function __construct(
        public readonly string $slug,
        public readonly string $shell,
        public readonly bool $isPreview,
        public readonly string $visitorId,
        public readonly array $cookies,
    ) {
    }
}
