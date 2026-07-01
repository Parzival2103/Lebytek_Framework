<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;

final class LeadApiDeprovisioningService
{
    public function __construct(
        private readonly LebytekApiClient $api,
        private readonly LeadRepositoryInterface $leads,
    ) {}

    public function deprovisionLead(int $leadId): void
    {
        $lead = $this->leads->findById($leadId);
        if ($lead === null) {
            throw new \InvalidArgumentException('Lead no encontrado.');
        }

        $tenantPublicId = (string) ($lead['api_tenant_public_id'] ?? '');
        if ($tenantPublicId === '') {
            throw new \InvalidArgumentException('Este lead no tiene demo activa en la API.');
        }

        try {
            $instances = $this->api->listInstances($tenantPublicId);
            foreach ($instances as $instance) {
                $instancePublicId = (string) ($instance['publicId'] ?? '');
                if ($instancePublicId !== '') {
                    $this->api->deleteInstance($tenantPublicId, $instancePublicId);
                }
            }

            $this->leads->markApiDeprovisioned($leadId);
        } catch (LebytekApiException $e) {
            $this->leads->markApiProvisionError($leadId, 'Baja demo: '.$e->getMessage());
            throw $e;
        }
    }

    /** @return array{processed: int, failed: int, errors: list<string>} */
    public function expireDemosOlderThanDays(int $days = 30): array
    {
        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($this->leads->findDemosOlderThanDays($days) as $lead) {
            $leadId = (int) ($lead['id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }

            try {
                $this->deprovisionLead($leadId);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Lead #{$leadId}: ".$e->getMessage();
            }
        }

        return ['processed' => $processed, 'failed' => $failed, 'errors' => $errors];
    }
}
