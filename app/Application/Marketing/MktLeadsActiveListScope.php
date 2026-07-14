<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use Lebytek\Framework\Application\Crud\Context\CrudListContext;
use Lebytek\Framework\Domain\Interfaces\CrudListScopeInterface;

/**
 * Oculta del listado de leads los estados de captación cruda (`pendiente`)
 * y demos dadas de baja (`demo_baja`). Si el usuario filtra explícitamente
 * por uno de esos valores (`f_estado`), se muestran.
 */
final class MktLeadsActiveListScope implements CrudListScopeInterface
{
    /** @var list<string> */
    private const HIDDEN_ESTADOS = ['pendiente', 'demo_baja'];

    public function apply(CrudListContext $ctx): void
    {
        $explicit = $ctx->query()['f_estado'] ?? null;
        if (is_string($explicit) && $explicit !== '' && in_array($explicit, self::HIDDEN_ESTADOS, true)) {
            return;
        }

        foreach (self::HIDDEN_ESTADOS as $estado) {
            $ctx->addCondition('estado', '!=', $estado);
        }
    }
}
