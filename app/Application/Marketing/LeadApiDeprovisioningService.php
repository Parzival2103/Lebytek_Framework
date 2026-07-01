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

    /** @return array{deleted: int} */
    public function deprovisionLead(int $leadId): array
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
            $deleted = 0;
            $knownInstancePublicId = (string) ($lead['api_instance_public_id'] ?? '');

            if ($knownInstancePublicId !== '') {
                $this->api->deleteInstance($tenantPublicId, $knownInstancePublicId);
                $deleted++;
            }

            $instances = $this->api->listInstances($tenantPublicId);
            foreach ($instances as $instance) {
                $instancePublicId = (string) ($instance['publicId'] ?? '');
                if ($instancePublicId === '' || $instancePublicId === $knownInstancePublicId) {
                    continue;
                }

                $this->api->deleteInstance($tenantPublicId, $instancePublicId);
                $deleted++;
            }

            if ($deleted === 0 && $instances === [] && $knownInstancePublicId === '') {
                throw new LebytekApiException(
                    'No se encontraron instancias WhatsApp para este lead en la API. Revisa api_provision_error o contacta soporte.',
                    404,
                );
            }

            if ($deleted === 0 && $instances !== []) {
                throw new LebytekApiException(
                    'No se pudo encolar la eliminación de ninguna instancia WhatsApp.',
                    500,
                );
            }

            $this->leads->markApiDeprovisioned($leadId);

            return ['deleted' => $deleted];
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
