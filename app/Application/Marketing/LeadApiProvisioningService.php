<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Kernel\EnvLoader;

final class LeadApiProvisioningService
{
    public function __construct(
        private readonly LebytekApiClient $api,
        private readonly LeadRepositoryInterface $leads,
        private readonly MailerInterface $mailer,
    ) {}

    public function provisionLead(int $leadId): void
    {
        $lead = $this->leads->findById($leadId);
        if ($lead === null) {
            throw new \InvalidArgumentException('Lead no encontrado.');
        }

        if (! empty($lead['api_tenant_public_id'])) {
            return;
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
            $this->sendCredentialsEmail($nombre, $email, $plainToken);
        } catch (LebytekApiException $e) {
            $this->leads->markApiProvisionError($leadId, $e->getMessage());
            throw $e;
        }
    }

    private function sendCredentialsEmail(string $nombre, string $email, string $token): void
    {
        $apiBaseUrl = rtrim((string) EnvLoader::get('LEBYTEK_API_URL', 'https://api.lebytek.com/api/v1'), '/');
        $cuerpo = str_replace(
            ['{{nombre}}', '{{token}}', '{{api_base_url}}'],
            [htmlspecialchars($nombre), htmlspecialchars($token), htmlspecialchars($apiBaseUrl)],
            "Hola {{nombre}},\n\nTu demo está lista. Usa este token para conectar con nuestra API:\n\nToken: {{token}}\nBase URL: {{api_base_url}}\n\nConserva este correo; el token no se vuelve a mostrar.\n\nSaludos,\nEquipo Lebytek"
        );

        $this->mailer->enviar(new MensajeCorreo(
            $email,
            $nombre,
            'Tus credenciales de acceso — Lebytek',
            nl2br($cuerpo)
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
