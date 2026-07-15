<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\LebytekApi;

final class ClientTenantApiClient
{
    public function __construct(
        private readonly LebytekApiTransport $transport,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function validateToken(string $token): bool
    {
        try {
            $this->listInstances($token);

            return true;
        } catch (LebytekApiException) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInstances(string $token): array
    {
        $decoded = $this->request('GET', '/instances', null, $token);
        $data = $decoded['data'] ?? $decoded;
        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, is_array(...)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getInstance(string $token, string $publicId): array
    {
        return $this->request('GET', '/instances/'.$publicId, null, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function getQr(string $token, string $publicId): array
    {
        return $this->request('GET', '/instances/'.$publicId.'/qr', null, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsage(string $token): array
    {
        return $this->request('GET', '/usage', null, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccountStatus(string $token): array
    {
        return $this->request('POST', '/account/status', [], $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body, string $token): array
    {
        $url = rtrim($this->baseUrl, '/').$path;
        $headers = [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
        ];

        $encodedBody = $body !== null
            ? json_encode($body, JSON_THROW_ON_ERROR)
            : null;

        if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') {
            $headers[] = 'Content-Type: application/json';
            if ($encodedBody === null) {
                $encodedBody = '{}';
            }
        }

        $result = $this->transport->execute($method, $url, $headers, $encodedBody);

        if ($result['status'] === 0 && $result['error'] !== '') {
            throw new LebytekApiException('Connection failed: '.$result['error'], 0);
        }

        $decoded = json_decode($result['body'], true);
        if (! is_array($decoded)) {
            $decoded = ['message' => $result['body']];
        }

        if ($result['status'] >= 400) {
            $message = is_string($decoded['message'] ?? null) ? $decoded['message'] : 'API error';

            throw new LebytekApiException($message, $result['status']);
        }

        return $decoded;
    }
}
