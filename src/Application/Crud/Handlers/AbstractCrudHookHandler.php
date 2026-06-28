<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Handlers;

use Lebytek\Framework\Application\Crud\Context\CrudFormContext;
use Lebytek\Framework\Application\Crud\Context\CrudListContext;
use Lebytek\Framework\Application\Crud\Context\CrudTransitionContext;
use Lebytek\Framework\Application\Crud\Context\CrudWriteContext;
use Lebytek\Framework\Domain\Interfaces\CrudHookHandlerInterface;

/**
 * Base no-op para handlers de hooks. Provee también los eventos extendidos
 * opcionales como no-op para que las subclases puedan sobrescribirlos.
 */
abstract class AbstractCrudHookHandler implements CrudHookHandlerInterface
{
    public function beforeCreate(CrudWriteContext $ctx): void {}

    public function afterCreate(CrudWriteContext $ctx): void {}

    public function beforeUpdate(CrudWriteContext $ctx): void {}

    public function afterUpdate(CrudWriteContext $ctx): void {}

    public function beforeDelete(CrudWriteContext $ctx): void {}

    public function afterDelete(CrudWriteContext $ctx): void {}

    // --- Eventos extendidos opcionales (no forman parte de la interfaz) ---

    public function beforeTransition(CrudTransitionContext $ctx): void {}

    public function afterTransition(CrudTransitionContext $ctx): void {}

    public function beforeRenderForm(CrudFormContext $ctx): void {}

    public function beforeListQuery(CrudListContext $ctx): void {}

    public function afterUpload(CrudWriteContext $ctx): void {}
}
