<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Kernel\EnvLoader;

final class LeadApiProvisioningService
{
    public function __construct(
        private readonly LebytekApiClient $api,
        private readonly LeadRepositoryInterface $leads,
        private readonly MarketingMailRenderer $mailRenderer,
    ) {}

    /**
     * @return array{status: 'ok'|'skipped'|'mail_failed', message?: string}
     */
    public function provisionLead(int $leadId): array
    {
        $lead = $this->leads->findById($leadId);
        if ($lead === null) {
            throw new \InvalidArgumentException('Lead no encontrado.');
        }

        if (! empty($lead['api_tenant_public_id'])) {
            return ['status' => 'skipped'];
        }

        $nombre = (string) ($lead['nombre'] ?? 'Cliente');
        $email = (string) ($lead['email'] ?? '');
        if ($email === '') {
            throw new \InvalidArgumentException('El lead no tiene correo.');
        }

        $externalRef = 'lebytek_lead_'.$leadId;
        $slug = $this->slugFromName($nombre, $leadId);

        try {
            $tenant = $this->api->provisionTenant($nombre, $slug, $externalRef);
            $tenantPublicId = (string) ($tenant['publicId'] ?? '');
            if ($tenantPublicId === '') {
                throw new LebytekApiException('API no devolvió publicId del tenant.');
            }

            $instanceExternalRef = $externalRef.'_instance';
            $instanceResponse = $this->api->createInstance(
                $tenantPublicId,
                'Demo '.$nombre,
                $instanceExternalRef,
                'demo',
            );
            $instancePublicId = (string) ($instanceResponse['publicId'] ?? '');

            $tokenResponse = $this->api->issueTenantToken(
                $tenantPublicId,
                'cliente-'.$slug,
                ['instancias.ver', 'mensajes.enviar', 'mensajes.ver', 'cuenta.ver'],
            );

            $plainToken = (string) ($tokenResponse['token'] ?? '');
            if ($plainToken === '') {
                throw new LebytekApiException('API no devolvió token por-tenant.');
            }

            $demoPackage = $this->leads->findDemoPackageBySlug('demo');
            $demoDays = (int) ($demoPackage['demo_dias'] ?? 30);
            $messageLimit = (int) ($demoPackage['mensajes_mes_limite'] ?? 100);
            $paqueteId = isset($demoPackage['id']) ? (int) $demoPackage['id'] : null;
            $demoStartedAt = (new \DateTimeImmutable())->format('c');
            $demoExpiresAt = (new \DateTimeImmutable("+{$demoDays} days"))->format('c');

            $this->api->updateTenant($tenantPublicId, [
                'commercialStatus' => 'demo',
                'planSlug' => 'demo',
                'planName' => (string) ($demoPackage['nombre'] ?? 'Demo'),
                'demoStartedAt' => $demoStartedAt,
                'demoExpiresAt' => $demoExpiresAt,
                'messagesMonthlyLimit' => $messageLimit,
            ]);

            try {
                $this->sendCredentialsEmail($nombre, $email, $plainToken);
            } catch (\Throwable $mailError) {
                $this->leads->markApiProvisionError($leadId, 'Correo: '.$mailError->getMessage());

                return [
                    'status'  => 'mail_failed',
                    'message' => $mailError->getMessage(),
                ];
            }

            $this->leads->markApiProvisioned(
                $leadId,
                $tenantPublicId,
                $externalRef,
                $instancePublicId,
                $paqueteId,
                'demo',
                $demoDays,
            );

            return ['status' => 'ok'];
        } catch (LebytekApiException $e) {
            $this->leads->markApiProvisionError($leadId, $e->getMessage());
            throw $e;
        }
    }

    private function sendCredentialsEmail(string $nombre, string $email, string $token): void
    {
        $apiBaseUrl = rtrim((string) EnvLoader::get('LEBYTEK_API_URL', 'https://api.lebytek.com/api/v1'), '/');
        $docsUrl = rtrim((string) EnvLoader::get('MKT_EMAIL_DOCS_URL', 'https://docs.lebytek.com'), '/');
        $dashboardUrl = rtrim((string) EnvLoader::get('MKT_EMAIL_DASHBOARD_URL', ''), '/');

        $this->mailRenderer->send('lead_api_credentials', $email, $nombre, [
            'nombre' => $nombre,
            'token' => $token,
            'api_base_url' => $apiBaseUrl,
            'docs_url' => $docsUrl,
            'dashboard_url' => $dashboardUrl,
            'packages_url' => $this->packagesUrl(),
        ]);
    }

    private function slugFromName(string $name, int $leadId): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
        if ($slug === '') {
            $slug = 'lead';
        }

        return substr($slug, 0, 40).'-'.$leadId;
    }

    private function packagesUrl(): string
    {
        $appUrl = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        if ($appUrl === '') {
            return '';
        }

        return $appUrl.'/?compras=1#paquetes';
    }
}
