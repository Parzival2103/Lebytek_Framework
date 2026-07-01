<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

final class LeadApiProvisioningService
{
    public function __construct(
        private readonly LebytekApiClient $api,
        private readonly LeadRepositoryInterface $leads,
        private readonly MailerInterface $mailer,
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
            $this->api->createInstance(
                $tenantPublicId,
                'Demo '.$nombre,
                $instanceExternalRef,
                'demo',
            );

            $tokenResponse = $this->api->issueTenantToken(
                $tenantPublicId,
                'cliente-'.$slug,
                ['instancias.ver'],
            );

            $plainToken = (string) ($tokenResponse['token'] ?? '');
            if ($plainToken === '') {
                throw new LebytekApiException('API no devolvió token por-tenant.');
            }

            $this->leads->markApiProvisioned($leadId, $tenantPublicId, $externalRef);

            try {
                $this->sendCredentialsEmail($nombre, $email, $plainToken);
            } catch (\Throwable $mailError) {
                $this->leads->markApiProvisionError($leadId, 'Correo: '.$mailError->getMessage());

                return [
                    'status'  => 'mail_failed',
                    'message' => $mailError->getMessage(),
                ];
            }

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

        $html = ViewHelper::render('emails/lead_api_credentials', [
            'nombre'      => $nombre,
            'token'       => $token,
            'apiBaseUrl'  => $apiBaseUrl,
            'docsUrl'     => $docsUrl,
            'showDocsCta' => $docsUrl !== '',
        ], '');

        $this->mailer->enviar(new MensajeCorreo(
            $email,
            $nombre,
            'Tu acceso a la API está listo — Lebytek',
            $html,
        ));
    }

    private function slugFromName(string $name, int $leadId): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
        if ($slug === '') {
            $slug = 'lead';
        }

        return substr($slug, 0, 40).'-'.$leadId;
    }
}
