<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\LebytekApi;

final class LebytekApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeoutSeconds = 30,
        private readonly int $maxRetries = 3,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function health(?string $actingTenantPublicId = null): array
    {
        return $this->request('GET', '/health', headers: $this->tenantHeaders($actingTenantPublicId));
    }

    /**
     * @return array<string, mixed>
     */
    public function provisionTenant(string $name, string $slug, string $externalRef): array
    {
        return $this->request('POST', '/tenants', [
            'name' => $name,
            'slug' => $slug,
            'externalRef' => $externalRef,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTenant(string $publicId): array
    {
        return $this->request('GET', '/tenants/'.$publicId);
    }

    /**
     * @return array<string, mixed>
     */
    public function createInstance(string $tenantPublicId, string $label, string $externalRef, string $purpose = 'demo'): array
    {
        return $this->request('POST', '/instances', [
            'label' => $label,
            'externalRef' => $externalRef,
            'purpose' => $purpose,
        ], $this->tenantHeaders($tenantPublicId));
    }

    /**
     * @param  list<string>|null  $abilities
     * @return array<string, mixed>
     */
    public function issueTenantToken(string $tenantPublicId, string $name, ?array $abilities = null): array
    {
        $body = ['name' => $name];
        if ($abilities !== null) {
            $body['abilities'] = $abilities;
        }

        return $this->request('POST', '/tenants/'.$tenantPublicId.'/tokens', $body);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
    ): array {
        if ($this->token === '') {
            throw new LebytekApiException('LEBYTEK_API_TOKEN is not configured.');
        }

        $url = rtrim($this->baseUrl, '/').$path;
        $write = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        $baseHeaders = [
            'Authorization: Bearer '.$this->token,
            'Accept: application/json',
        ];

        if ($write && $method !== 'DELETE') {
            $baseHeaders[] = 'Content-Type: application/json';
        }

        if ($write) {
            $baseHeaders[] = 'Idempotency-Key: '.$this->newUuid();
        }

        $allHeaders = array_merge($baseHeaders, $headers);
        $attempt = 0;
        $delayMs = 500;

        while (true) {
            $attempt++;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_HTTPHEADER => $allHeaders,
            ]);

            if ($body !== null && $method !== 'DELETE') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
            }

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                if ($attempt < $this->maxRetries) {
                    usleep($delayMs * 1000);
                    $delayMs *= 2;
                    continue;
                }
                throw new LebytekApiException('Connection failed: '.$curlError, 0);
            }

            $decoded = json_decode($raw, true) ?? ['message' => $raw];

            if ($status === 429 || $status >= 500) {
                if ($attempt < $this->maxRetries) {
                    usleep($delayMs * 1000);
                    $delayMs *= 2;
                    continue;
                }
            }

            if ($status >= 400) {
                throw new LebytekApiException(
                    is_string($decoded['message'] ?? null) ? $decoded['message'] : 'API error',
                    $status,
                    is_array($decoded['errors'] ?? null) ? $decoded['errors'] : null,
                );
            }

            return $decoded;
        }
    }

    /** @return list<string> */
    private function tenantHeaders(?string $actingTenantPublicId): array
    {
        return $actingTenantPublicId !== null
            ? ['X-Tenant-Id: '.$actingTenantPublicId]
            : [];
    }

    private function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
