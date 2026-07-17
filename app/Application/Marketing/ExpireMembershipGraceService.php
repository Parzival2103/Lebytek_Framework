<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use Lebytek\Framework\Kernel\EnvLoader;

final class ExpireMembershipGraceService
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly LeadRepositoryInterface $leads,
        private readonly LebytekApiClient $api,
        private readonly ChurnMetricsRepositoryInterface $churnMetrics,
        private readonly MarketingMailRenderer $mailRenderer,
    ) {}

    public function expireDue(\DateTimeInterface $now): int
    {
        $rows = $this->memberships->findGraceExpired($now);
        $count = 0;
        foreach ($rows as $row) {
            $this->expireOne($row);
            $count++;
        }

        return $count;
    }

    /** @param array<string, mixed> $membresia */
    private function expireOne(array $membresia): void
    {
        $id = (int) ($membresia['id'] ?? 0);
        $tenantId = (string) ($membresia['api_tenant_public_id'] ?? '');
        $leadId = isset($membresia['lead_id']) ? (int) $membresia['lead_id'] : 0;

        if ($tenantId !== '') {
            try {
                $this->api->cancelCommercial($tenantId);
            } catch (\Throwable) {
                // Best-effort soft-cancel; churn row still recorded.
            }
        }

        $rawReactivation = bin2hex(random_bytes(32));
        $reactHash = hash('sha256', $rawReactivation);
        $this->memberships->markCancelled($id, $reactHash);

        if ($leadId > 0) {
            $this->leads->markCancelled($leadId);
            $this->churnMetrics->resolveOpenRiskSignal($leadId, $tenantId !== '' ? $tenantId : null, 'payment_failed');
        }

        $lead = $leadId > 0 ? $this->leads->findById($leadId) : null;
        $nombre = (string) ($lead['nombre'] ?? 'Cliente');
        $email = (string) ($lead['email'] ?? '');
        if ($email === '') {
            return;
        }

        $baseUrl = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $this->mailRenderer->send('membership_cancelled_reactivate', $email, $nombre, [
            'nombre' => $nombre,
            'cuenta' => 'Lebytek',
            'retry_url' => $baseUrl.'/membresia/reactivar?t='.$rawReactivation,
        ]);
    }
}
