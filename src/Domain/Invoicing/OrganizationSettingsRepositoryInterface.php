<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\OrganizationSettings;

interface OrganizationSettingsRepositoryInterface
{
    public function get(string $providerKey, string $externalOrgId = ''): ?OrganizationSettings;

    public function upsert(OrganizationSettings $settings): void;
}
