<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudActionContext;
use App\Domain\Interfaces\CrudActionHandlerInterface;
use App\Infrastructure\Repositories\GenericCrudRepository;

/**
 * Acción demo: alterna el estado activo/inactivo de un producto.
 * Ejemplo de escape hatch — la lógica vive aquí, no en el core.
 */
final class DemoProductoToggleStatusHandler implements CrudActionHandlerInterface
{
    public function handle(CrudActionContext $ctx): void
    {
        $record = $ctx->record() ?? [];
        $next = ((string) ($record['status'] ?? 'activo')) === 'activo' ? 'inactivo' : 'activo';

        (new GenericCrudRepository())->updateRecord(
            $ctx->table(),
            $ctx->primaryKey(),
            $ctx->recordId(),
            ['status' => $next, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $ctx->userId()]
        );
    }
}
