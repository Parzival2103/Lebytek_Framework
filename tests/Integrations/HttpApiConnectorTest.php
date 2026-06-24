<?php
// tests/Integrations/HttpApiConnectorTest.php
declare(strict_types=1);

use App\Infrastructure\Integrations\Http\HttpApiConnector;

test('un host inalcanzable devuelve status 0 sin lanzar excepción', function (): void {
    $connector = new HttpApiConnector(1); // timeout 1s
    // Puerto 9 (discard) en loopback: conexión rechazada/timeout determinista.
    $res = $connector->request('POST', 'http://127.0.0.1:9/nope', ['a' => 1]);
    assert_same(0, $res['status'], 'status 0 en fallo de transporte');
    assert_true(is_array($res['json']), 'json siempre es array');
});
