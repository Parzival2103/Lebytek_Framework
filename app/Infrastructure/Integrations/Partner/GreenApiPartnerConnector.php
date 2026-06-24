<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Partner;

use App\Domain\Integrations\ApiConnectorInterface;
use App\Domain\Integrations\PartnerConnectorInterface;

final class GreenApiPartnerConnector implements PartnerConnectorInterface
{
    public function __construct(
        private readonly ApiConnectorInterface $http,
        private readonly string $partnerToken,
        private readonly string $baseUrl,
    ) {
    }

    public function isAvailable(): bool
    {
        return trim($this->partnerToken) !== '';
    }

    /** @return array{instance_id:string, token:string} */
    public function createInstance(string $label): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Partner API no disponible (sin GREEN_API_PARTNER_TOKEN).');
        }
        $url = rtrim($this->baseUrl, '/') . '/partner/createInstance/' . $this->partnerToken;
        $res = $this->http->request('POST', $url, ['name' => $label]);
        $json = (array) ($res['json'] ?? []);
        $instanceId = (string) ($json['idInstance'] ?? '');
        $token = (string) ($json['apiTokenInstance'] ?? '');
        if ($instanceId === '' || $token === '') {
            throw new \RuntimeException('Partner API: respuesta sin credenciales (status ' . ($res['status'] ?? '?') . ').');
        }
        return ['instance_id' => $instanceId, 'token' => $token];
    }
}
