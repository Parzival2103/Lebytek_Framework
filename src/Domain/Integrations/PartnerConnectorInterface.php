<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Integrations;

interface PartnerConnectorInterface
{
    public function isAvailable(): bool;

    /** @return array{instance_id:string, token:string} */
    public function createInstance(string $label): array;
}
