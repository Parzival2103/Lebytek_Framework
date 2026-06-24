<?php
// tests/Integrations/GreenApiPartnerConnectorTest.php
declare(strict_types=1);

use App\Domain\Integrations\ApiConnectorInterface;
use App\Infrastructure\Integrations\Partner\GreenApiPartnerConnector;

final class FakePartnerHttp implements ApiConnectorInterface
{
    public function __construct(private array $response) {}
    public function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        return $this->response;
    }
}

test('createInstance devuelve instance_id y token del proveedor', function () {
    $http = new FakePartnerHttp(['status' => 200, 'body' => '', 'json' => ['idInstance' => '110999', 'apiTokenInstance' => 'tok-abc']]);
    $c = new GreenApiPartnerConnector($http, 'PARTNER-TOKEN', 'https://api.green-api.com');
    $res = $c->createInstance('Demo - Juan');
    assert_same('110999', $res['instance_id']);
    assert_same('tok-abc', $res['token']);
});

test('isAvailable es false sin partner token', function () {
    $http = new FakePartnerHttp(['status' => 200, 'body' => '', 'json' => []]);
    assert_true((new GreenApiPartnerConnector($http, '', 'https://x'))->isAvailable() === false);
});

test('createInstance lanza si la respuesta no trae credenciales', function () {
    $http = new FakePartnerHttp(['status' => 500, 'body' => 'err', 'json' => []]);
    $c = new GreenApiPartnerConnector($http, 'PARTNER-TOKEN', 'https://x');
    assert_throws(\RuntimeException::class, fn() => $c->createInstance('x'));
});
