<?php
// tests/Integrations/MessageResultTest.php
declare(strict_types=1);

use App\Domain\Integrations\MessageResult;

test('MessageResult::sent marca ok y conserva el id de proveedor', function (): void {
    $r = MessageResult::sent('ABC123', ['idMessage' => 'ABC123']);
    assert_true($r->ok, 'sent debe ser ok');
    assert_same('ABC123', $r->providerMessageId, 'conserva providerMessageId');
    assert_null($r->error, 'sent no tiene error');
    assert_same(['idMessage' => 'ABC123'], $r->rawResponse, 'conserva rawResponse');
});

test('MessageResult::failed marca no-ok y conserva el error', function (): void {
    $r = MessageResult::failed('timeout', ['x' => 1]);
    assert_true($r->ok === false, 'failed no debe ser ok');
    assert_null($r->providerMessageId, 'failed no tiene providerMessageId');
    assert_same('timeout', $r->error, 'conserva error');
    assert_same(['x' => 1], $r->rawResponse, 'conserva rawResponse');
});
