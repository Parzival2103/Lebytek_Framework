<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\OrganizationSettings;
use Lebytek\Framework\Kernel\Config\Config;

final readonly class SyncOrganizationSettingsFromConfig
{
    public function __construct(private OrganizationSettingsRepositoryInterface $organizations)
    {
    }

    public function sync(): void
    {
        $mode = (string) Config::get('invoicing.providers.facturapi.config.mode', 'test');
        $this->organizations->upsert(new OrganizationSettings(
            providerKey: 'facturapi',
            mode: $mode !== '' ? $mode : 'test',
            externalOrgId: '',
        ));
    }
}
