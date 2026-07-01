<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\LebytekApi;

interface LebytekApiTransport
{
    /**
     * @param  list<string>  $headers
     * @return array{status: int, body: string, error: string}
     */
    public function execute(string $method, string $url, array $headers, ?string $body): array;
}
