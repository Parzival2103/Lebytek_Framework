<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Kernel\EnvLoader;

final class StartMembershipGraceService
{
    private const GRACE_HOURS = 48;

    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly LeadRepositoryInterface $leads,
        private readonly ChurnMetricsRepositoryInterface $churnMetrics,
        private readonly MarketingMailRenderer $mailRenderer,
    ) {}

    /** @param array<string, mixed> $membresia */
    public function handle(array $membresia, string $rawEventId): void
    {
        $id = (int) ($membresia['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $status = (string) ($membresia['status'] ?? '');
        if ($status === 'cancelled') {
            return;
        }

        if ($status === 'past_due') {
            $graceEnds = $membresia['grace_ends_at'] ?? null;
            if ($graceEnds !== null && strtotime((string) $graceEnds) > time()) {
                return;
            }
        }

        $graceEndsAt = (new \DateTimeImmutable('+'.self::GRACE_HOURS.' hours'));
        $rawToken = bin2hex(random_bytes(32));
        $retryHash = hash('sha256', $rawToken);

        $this->memberships->markPastDue($id, $graceEndsAt, $retryHash);

        $leadId = isset($membresia['lead_id']) ? (int) $membresia['lead_id'] : 0;
        $tenantId = (string) ($membresia['api_tenant_public_id'] ?? '');
        $this->churnMetrics->upsertRiskSignal(
            $leadId > 0 ? $leadId : null,
            $tenantId !== '' ? $tenantId : null,
            'payment_failed',
            'high',
            ['provider_event_id' => $rawEventId],
        );

        $lead = $leadId > 0 ? $this->leads->findById($leadId) : null;
        $nombre = (string) ($lead['nombre'] ?? 'Cliente');
        $email = (string) ($lead['email'] ?? '');
        if ($email === '') {
            return;
        }

        $baseUrl = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $planSlug = (string) ($membresia['plan_slug'] ?? '');
        $ciclo = (string) ($membresia['ciclo'] ?? 'monthly');
        $cicloLabel = $ciclo === 'annual' ? 'Anual' : 'Mensual';

        $this->mailRenderer->send('membership_payment_failed', $email, $nombre, [
            'nombre' => $nombre,
            'plan' => ucfirst($planSlug),
            'ciclo' => $cicloLabel,
            'grace_hours' => (string) self::GRACE_HOURS,
            'retry_url' => $baseUrl.'/membresia/reintentar-pago?t='.$rawToken,
            'cuenta' => 'Lebytek',
        ]);
    }
}
