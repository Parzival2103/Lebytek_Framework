<?php
// tests/Integrations/GreenApiWhatsappChannelTest.php
declare(strict_types=1);

use App\Domain\Integrations\ApiConnectorInterface;
use App\Domain\Integrations\MessageRequest;
use App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel;

/** Conector falso que captura la URL/payload y devuelve una respuesta fija. */
final class FakeConnector implements ApiConnectorInterface
{
    public ?string $url = null;
    /** @var array<string,mixed> */
    public array $payload = [];
    /** @param array{status:int, body:string, json:array} $response */
    public function __construct(private array $response) {}
    public function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        $this->url = $url;
        $this->payload = $payload;
        return $this->response;
    }
}

function greenConfig(): array
{
    return ['base_url' => 'https://api.green-api.com', 'instance_id' => '1101', 'token' => 'TKN', 'timeout' => 15];
}

test('key() es "whatsapp"', function (): void {
    $c = new GreenApiWhatsappChannel(new FakeConnector(['status' => 200, 'body' => '', 'json' => []]), greenConfig());
    assert_same('whatsapp', $c->key());
});

test('normaliza el teléfono a chatId <digitos>@c.us y mapea idMessage', function (): void {
    $conn = new FakeConnector(['status' => 200, 'body' => '{"idMessage":"BAE5"}', 'json' => ['idMessage' => 'BAE5']]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', '+52 (55) 1234-5678', 'hola'));
    assert_true($res->ok, 'ok');
    assert_same('BAE5', $res->providerMessageId, 'mapea idMessage');
    assert_same('5255123 45678@c.us'[0], $conn->payload['chatId'][0], 'chatId comienza con dígito'); // sanity
    assert_same('525512345678@c.us', $conn->payload['chatId'], 'chatId solo dígitos + @c.us');
    assert_true(str_contains((string) $conn->url, '/waInstance1101/sendMessage/TKN'), 'URL Green API correcta');
});

test('teléfono sin dígitos → failed sin llamar al proveedor', function (): void {
    $conn = new FakeConnector(['status' => 200, 'body' => '', 'json' => ['idMessage' => 'X']]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', 'sin-numero', 'hola'));
    assert_true($res->ok === false, 'no ok');
    assert_null($conn->url, 'no se llamó al proveedor');
});

test('respuesta no-2xx → failed', function (): void {
    $conn = new FakeConnector(['status' => 500, 'body' => 'err', 'json' => []]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok en 500');
});

test('2xx sin idMessage → failed', function (): void {
    $conn = new FakeConnector(['status' => 200, 'body' => '{}', 'json' => []]);
    $c = new GreenApiWhatsappChannel($conn, greenConfig());
    $res = $c->send(new MessageRequest('whatsapp', '5215512345678', 'hola'));
    assert_true($res->ok === false, 'no ok sin idMessage');
});
