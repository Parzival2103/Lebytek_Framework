<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Integrations;

final class IntegrationAccount
{
    public function __construct(
        public readonly int $id,
        public readonly string $provider,
        public readonly string $label,
        public readonly string $instanceId,
        public readonly string $token,
        public readonly bool $isDefault,
        public readonly ?int $leadId,
        public readonly string $status,
        public readonly string $provisionedVia,
    ) {
    }
}
