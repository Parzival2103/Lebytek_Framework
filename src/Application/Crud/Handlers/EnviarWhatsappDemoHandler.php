<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Handlers;

use Lebytek\Framework\Application\Crud\Context\CrudActionContext;
use Lebytek\Framework\Application\Integrations\IntegrationsFactory;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Domain\Interfaces\CrudActionHandlerInterface;

/*
|--------------------------------------------------------------------------
| EnviarWhatsappDemoHandler — handler delgado de demo (módulo de negocio).
|--------------------------------------------------------------------------
| Mapea el registro → MessageRequest y delega en la fachada. No conoce
| Green API. "Qué/a quién" vive aquí; "cómo se envía" en integrations.
| Se instancia con `new $class()` (sin DI): resuelve el dispatcher con
| IntegrationsFactory.
*/
final class EnviarWhatsappDemoHandler implements CrudActionHandlerInterface
{
    public function handle(CrudActionContext $ctx): void
    {
        $record = $ctx->record() ?? [];
        $telefono = trim((string) ($record['telefono'] ?? ''));
        if ($telefono === '') {
            return; // regla de negocio: sin teléfono no hay nada que enviar
        }

        $body = sprintf(
            'Hola %s, confirmamos tu registro. ¡Gracias!',
            (string) ($record['nombre'] ?? '')
        );

        IntegrationsFactory::dispatcher()->send(new MessageRequest(
            channel: 'whatsapp',
            recipient: $telefono,
            body: $body,
            meta: [
                'source'    => 'crud:demo_clientes',
                'record_id' => $ctx->recordId(),
                'user_id'   => $ctx->userId(),
            ]
        ));
    }
}
