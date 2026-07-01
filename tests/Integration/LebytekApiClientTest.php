<?php

declare(strict_types=1);

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;

final class RecordingTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];

    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses = [];

    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');

        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{"status":"ok"}', 'error' => ''];
    }
}

test('LebytekApiClient sends Bearer and Idempotency-Key on POST', function () {
    $transport = new RecordingTransport();
    $transport->responses[] = ['status' => 201, 'body' => '{"publicId":"01JTEST"}', 'error' => ''];
    $client = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);
    $client->provisionTenant('Acme', 'acme', 'lebytek_lead_1');
    assert_same(1, count($transport->calls));
    $headers = implode("\n", $transport->calls[0]['headers']);
    assert_true(str_contains($headers, 'Authorization: Bearer platform-token'));
    assert_true(str_contains($headers, 'Idempotency-Key: '));
});

test('LebytekApiClient retries on 429 then succeeds', function () {
    $transport = new RecordingTransport();
    $transport->responses[] = ['status' => 429, 'body' => '{"message":"rate limit"}', 'error' => ''];
    $transport->responses[] = ['status' => 200, 'body' => '{"status":"ok"}', 'error' => ''];
    $client = new LebytekApiClient('https://api.test/v1', 'tok', 5, 3, $transport);
    $client->health();
    assert_same(2, count($transport->calls));
});
