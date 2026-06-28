<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Integrations;

use Lebytek\Framework\Domain\Integrations\IntegrationAccount;
use Lebytek\Framework\Domain\Integrations\IntegrationAccountRepositoryInterface;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Domain\Integrations\MessageSenderInterface;
use Lebytek\Framework\Domain\Integrations\PartnerConnectorInterface;
use Lebytek\Framework\Kernel\Security\SignedToken;

final class DemoProvisioningService
{
    public function __construct(
        private readonly IntegrationAccountRepositoryInterface $accounts,
        private readonly PartnerConnectorInterface $partner,
        private readonly MessageSenderInterface $dispatcher,
        private readonly string $appUrl,
    ) {
    }

    public function provisionAuto(int $leadId, string $leadNombre, string $leadEmail): IntegrationAccount
    {
        $creds = $this->partner->createInstance('Demo - ' . $leadNombre);
        return $this->persistAndNotify($leadId, $leadNombre, $leadEmail, $creds['instance_id'], $creds['token'], 'partner_api');
    }

    public function provisionManual(int $leadId, string $leadNombre, string $leadEmail, string $instanceId, string $token): IntegrationAccount
    {
        return $this->persistAndNotify($leadId, $leadNombre, $leadEmail, $instanceId, $token, 'manual');
    }

    private function persistAndNotify(int $leadId, string $nombre, string $email, string $instanceId, string $token, string $via): IntegrationAccount
    {
        $draft = new IntegrationAccount(0, 'green_api', 'Demo - ' . $nombre, $instanceId, $token, false, $leadId, 'provisioning', $via);
        $id = $this->accounts->save($draft);
        $saved = new IntegrationAccount($id, 'green_api', $draft->label, $instanceId, $token, false, $leadId, 'provisioning', $via);
        $this->sendDemoEmail($saved, $nombre, $email);
        return $saved;
    }

    private function sendDemoEmail(IntegrationAccount $acc, string $nombre, string $email): void
    {
        $link = rtrim($this->appUrl, '/') . '/wa/activar/' . SignedToken::make($acc->id);
        $body = sprintf(
            "Hola %s,\n\nTu demo de WhatsApp está lista. Activa tu instancia escaneando el código QR en este enlace:\n%s\n\nGracias.",
            $nombre,
            $link
        );
        $this->dispatcher->send(new MessageRequest(
            channel: 'email',
            recipient: $email,
            body: $body,
            meta: ['source' => 'integrations:demo_provision', 'subject' => 'Activa tu demo de WhatsApp', 'account_id' => $acc->id]
        ));
    }
}
