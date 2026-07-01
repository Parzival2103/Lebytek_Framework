<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\LebytekApi;

final class CurlLebytekApiTransport implements LebytekApiTransport
{
    public function __construct(
        private readonly int $timeoutSeconds = 30,
    ) {}

    /**
     * @param  list<string>  $headers
     * @return array{status: int, body: string, error: string}
     */
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null && $method !== 'DELETE') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['status' => 0, 'body' => '', 'error' => $curlError];
        }

        return ['status' => $status, 'body' => (string) $raw, 'error' => ''];
    }
}
