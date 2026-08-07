<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\SyncOrganizationSettingsFromConfig;
use Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\OrganizationSettings;
use Lebytek\Framework\Kernel\Config\Config;

final class Task13OrganizationSettingsRepository implements OrganizationSettingsRepositoryInterface
{
    /** @var array<string, OrganizationSettings> */
    private array $settings = [];

    public function get(string $providerKey, string $externalOrgId = ''): ?OrganizationSettings
    {
        return $this->settings[$providerKey."\0".$externalOrgId] ?? null;
    }

    public function upsert(OrganizationSettings $settings): void
    {
        $this->settings[$settings->providerKey()."\0".($settings->externalOrgId() ?? '')] = $settings;
    }
}

test('SyncOrganizationSettingsFromConfig upserts default Facturapi organization mode', function (): void {
    $originalMode = Config::get('invoicing.providers.facturapi.config.mode', 'test');
    $repository = new Task13OrganizationSettingsRepository();

    try {
        Config::set('invoicing.providers.facturapi.config.mode', 'live');

        (new SyncOrganizationSettingsFromConfig($repository))->sync();

        $settings = $repository->get('facturapi', '');
        assert_true($settings instanceof OrganizationSettings);
        assert_same('facturapi', $settings->providerKey());
        assert_same('', $settings->externalOrgId());
        assert_same('live', $settings->mode());
    } finally {
        Config::set('invoicing.providers.facturapi.config.mode', $originalMode);
    }
});
