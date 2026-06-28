<?php

declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Integrations\GreenApi\GreenApiAccountClient;

final class FakeQrHttp implements \Lebytek\Framework\Domain\Integrations\ApiConnectorInterface
{
    public function __construct(private readonly array $response)
    {
    }

    public function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        return $this->response;
    }
}

test('fetchQr devuelve base64 cuando type es qrCode', function () {
    $http = new FakeQrHttp([
        'status' => 200,
        'body'   => '{"type":"qrCode","message":"abc123"}',
        'json'   => ['type' => 'qrCode', 'message' => 'abc123'],
    ]);
    $client = new GreenApiAccountClient($http, 'https://api.green-api.com');
    $res = $client->fetchQr('1101', 'tok');

    assert_true($res['ok']);
    assert_same('abc123', $res['qr_base64']);
    assert_same('https://qr.green-api.com/waInstance1101/tok', $res['qr_url']);
    assert_same(null, $res['error']);
});

test('fetchQr informa si la instancia ya está autorizada', function () {
    $http = new FakeQrHttp([
        'status' => 200,
        'body'   => '{"type":"alreadyLogged","message":"instance account already authorized"}',
        'json'   => ['type' => 'alreadyLogged', 'message' => 'instance account already authorized'],
    ]);
    $client = new GreenApiAccountClient($http, 'https://api.green-api.com');
    $res = $client->fetchQr('1101', 'tok');

    assert_true($res['ok'] === false);
    assert_same(null, $res['qr_base64']);
    assert_same('alreadyLogged', $res['api_type']);
    assert_same(null, $res['error']);
});

test('resolveActivationPhase detecta sincronizacion tras escaneo', function () {
    $http = new class implements \Lebytek\Framework\Domain\Integrations\ApiConnectorInterface {
        private int $calls = 0;
        public function request(string $method, string $url, array $payload = [], array $headers = []): array
        {
            $this->calls++;
            if (str_contains($url, '/qr/')) {
                return [
                    'status' => 200,
                    'body'   => '{"type":"alreadyLogged","message":"instance account already authorized"}',
                    'json'   => ['type' => 'alreadyLogged', 'message' => 'instance account already authorized'],
                ];
            }
            return [
                'status' => 200,
                'body'   => '{"stateInstance":"starting"}',
                'json'   => ['stateInstance' => 'starting'],
            ];
        }
    };
    $client = new GreenApiAccountClient($http, 'https://api.green-api.com');
    $phase = $client->resolveActivationPhase('1101', 'tok');

    assert_same('syncing', $phase['phase']);
    assert_true(str_contains((string) $phase['message'], 'escaneado'));
});

test('resolveActivationPhase detecta instancia lista', function () {
    $http = new class implements \Lebytek\Framework\Domain\Integrations\ApiConnectorInterface {
        public function request(string $method, string $url, array $payload = [], array $headers = []): array
        {
            if (str_contains($url, '/qr/')) {
                return ['status' => 200, 'body' => '{"type":"alreadyLogged"}', 'json' => ['type' => 'alreadyLogged']];
            }
            return ['status' => 200, 'body' => '{"stateInstance":"authorized"}', 'json' => ['stateInstance' => 'authorized']];
        }
    };
    $client = new GreenApiAccountClient($http, 'https://api.green-api.com');
    $phase = $client->resolveActivationPhase('1101', 'tok');

    assert_same('ready', $phase['phase']);
});

test('fetchQr informa error de transporte', function () {
    $http = new FakeQrHttp(['status' => 0, 'body' => 'timeout', 'json' => []]);
    $client = new GreenApiAccountClient($http, 'https://api.green-api.com');
    $res = $client->fetchQr('1101', 'tok');

    assert_true($res['ok'] === false);
    assert_true(str_contains((string) $res['error'], 'No se pudo contactar'));
});
