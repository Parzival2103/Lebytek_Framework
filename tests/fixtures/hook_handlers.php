<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Context\CrudWriteContext;
use App\Application\Crud\Handlers\AbstractCrudHookHandler;
use App\Domain\Interfaces\CrudActionHandlerInterface;

if (!class_exists('MutatingHookHandler')) {
    /** Hook handler que muta data en beforeCreate (prueba del read-back). */
    class MutatingHookHandler extends AbstractCrudHookHandler
    {
        public function beforeCreate(CrudWriteContext $ctx): void
        {
            $ctx->set('slug', 'from-hook');
        }
    }

    /** Hook handler cuyo beforeUpdate lanza (prueba de abort). */
    class ThrowingHookHandler extends AbstractCrudHookHandler
    {
        public function beforeUpdate(CrudWriteContext $ctx): void
        {
            throw new \RuntimeException('abort from hook');
        }
    }

    /** Handler legacy: implementa la interfaz nueva (vía abstract) pero además
     *  define el método legacy beforeStore para probar el dispatch de alias. */
    class LegacyAliasHookHandler extends AbstractCrudHookHandler
    {
        public function beforeStore(CrudWriteContext $ctx): void
        {
            $ctx->set('legacy', 'yes');
        }
    }

    /** Solo implementa la interfaz de acción, NO la de hooks. */
    class ActionOnlyHandler implements CrudActionHandlerInterface
    {
        public function handle(CrudActionContext $ctx): void {}
    }
}
