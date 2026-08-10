<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing;

use Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\OrganizationSettings;
use Lebytek\Framework\Kernel\Database\Connection;
use PDO;

final class PdoOrganizationSettingsRepository implements OrganizationSettingsRepositoryInterface
{
    public function get(string $providerKey, string $externalOrgId = ''): ?OrganizationSettings
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT provider_key, external_org_id, mode, label, meta
             FROM inv_organizations
             WHERE provider_key = :provider_key
               AND external_org_id = :external_org_id
             LIMIT 1'
        );
        $stmt->execute([
            'provider_key' => $providerKey,
            'external_org_id' => $externalOrgId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function upsert(OrganizationSettings $settings): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO inv_organizations (provider_key, external_org_id, mode, label, meta)
             VALUES (:provider_key, :external_org_id, :mode, :label, :meta)
             ON DUPLICATE KEY UPDATE
                 mode = VALUES(mode),
                 label = VALUES(label),
                 meta = VALUES(meta),
                 updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'provider_key' => $settings->providerKey(),
            'external_org_id' => $settings->externalOrgId() ?? '',
            'mode' => $settings->mode(),
            'label' => $settings->label(),
            'meta' => $this->encodeMeta($settings->meta()),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OrganizationSettings
    {
        $externalOrgId = (string) $row['external_org_id'];

        return new OrganizationSettings(
            (string) $row['provider_key'],
            (string) $row['mode'],
            $externalOrgId !== '' ? $externalOrgId : null,
            $row['label'] !== null ? (string) $row['label'] : null,
            $this->decodeMeta($row['meta'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function encodeMeta(array $meta): ?string
    {
        $meta = InvoiceSecretScrubber::scrubMetaSecrets($meta);

        return $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $meta): array
    {
        if (! is_string($meta) || $meta === '') {
            return [];
        }

        $decoded = json_decode($meta, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
