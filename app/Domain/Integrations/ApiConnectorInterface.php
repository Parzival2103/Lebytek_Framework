<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface ApiConnectorInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<int, string>   $headers
     * @return array{status:int, body:string, json:array}
     */
    public function request(string $method, string $url, array $payload = [], array $headers = []): array;
}
