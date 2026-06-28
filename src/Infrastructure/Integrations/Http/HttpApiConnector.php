<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Integrations\Http;

use Lebytek\Framework\Domain\Integrations\ApiConnectorInterface;

/*
|--------------------------------------------------------------------------
| HttpApiConnector — cliente HTTP genérico (cURL) con manejo uniforme.
|--------------------------------------------------------------------------
| Timeout configurable. Nunca lanza por fallo de red: devuelve status 0.
| El cuerpo JSON se decodifica en 'json' (array vacío si no aplica).
*/
final class HttpApiConnector implements ApiConnectorInterface
{
    public function __construct(private readonly int $defaultTimeout = 15)
    {
    }

    public function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        $ch = curl_init();

        $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->defaultTimeout,
            CURLOPT_TIMEOUT        => $this->defaultTimeout,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_POSTFIELDS     => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            return ['status' => 0, 'body' => $error !== '' ? $error : 'transport_error', 'json' => []];
        }

        $bodyStr = (string) $body;
        $decoded = json_decode($bodyStr, true);

        return [
            'status' => $status,
            'body'   => $bodyStr,
            'json'   => is_array($decoded) ? $decoded : [],
        ];
    }
}
