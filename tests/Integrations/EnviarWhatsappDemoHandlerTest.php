<?php
// tests/Integrations/EnviarWhatsappDemoHandlerTest.php
declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Handlers\EnviarWhatsappDemoHandler;
use App\Domain\Interfaces\CrudActionHandlerInterface;

function actionContext(?array $record): CrudActionContext
{
    return new CrudActionContext(
        'demo_clientes', 'dom_demo_clientes', 'id',
        1, '127.0.0.1', 10, $record, 'confirmar_wa', []
    );
}

test('el handler está registrado en la whitelist y existe la clase', function (): void {
    $map = require ROOT_PATH . '/config/crud_handlers.php';
    assert_true(isset($map['enviar_whatsapp_demo']), 'clave registrada');
    assert_true(class_exists($map['enviar_whatsapp_demo']), 'clase existe');
});

test('el handler implementa CrudActionHandlerInterface y es instanciable sin args', function (): void {
    $h = new EnviarWhatsappDemoHandler();
    assert_true($h instanceof CrudActionHandlerInterface, 'implementa el contrato');
});

test('sin teléfono el handler no hace nada (no lanza)', function (): void {
    $h = new EnviarWhatsappDemoHandler();
    $h->handle(actionContext(['nombre' => 'Ada', 'telefono' => '']));
    assert_true(true, 'no lanzó con teléfono vacío');
});
