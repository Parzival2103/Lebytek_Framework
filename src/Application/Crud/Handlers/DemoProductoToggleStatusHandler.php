<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Handlers;

use Lebytek\Framework\Application\Crud\Context\CrudActionContext;
use Lebytek\Framework\Domain\Interfaces\CrudActionHandlerInterface;
use Lebytek\Framework\Infrastructure\Repositories\GenericCrudRepository;

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
