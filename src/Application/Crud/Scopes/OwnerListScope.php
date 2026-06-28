<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Scopes;

use Lebytek\Framework\Application\Crud\Context\CrudListContext;
use Lebytek\Framework\Domain\Interfaces\CrudListScopeInterface;

/**
 * Scope built-in de propiedad por usuario. Restringe el listado a las filas
 * cuya columna de autor coincide con el usuario actual, salvo que tenga el
 * permiso de bypass (ve todo). userId null sin bypass => no devuelve filas.
 */
final class OwnerListScope implements CrudListScopeInterface
{
    public function __construct(
        private readonly string $column,
        private readonly bool $hasBypass,
        private readonly ?int $userId
    ) {}

    public function apply(CrudListContext $ctx): void
    {
        if ($this->hasBypass) {
            return;
        }

        if ($this->userId === null) {
            // Política de no-fuga: id imposible para vaciar el listado.
            $ctx->addCondition($this->column, '=', -1);
            return;
        }

        $ctx->addCondition($this->column, '=', $this->userId);
    }
}
