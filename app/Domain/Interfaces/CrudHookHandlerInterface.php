<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudWriteContext;

/**
 * Contrato para hooks de escritura del CRUD Engine.
 * Las implementaciones extienden AbstractCrudHookHandler y sobrescriben solo
 * lo necesario. Vocabulario canónico: Create/Update/Delete.
 *
 * Eventos extendidos opcionales (NO en la interfaz; el runner los invoca por
 * method_exists si el handler los define):
 *   beforeTransition/afterTransition(CrudTransitionContext)
 *   beforeRenderForm(CrudFormContext)
 *   beforeListQuery(CrudListContext)
 *   afterUpload(CrudWriteContext)
 */
interface CrudHookHandlerInterface
{
    public function beforeCreate(CrudWriteContext $ctx): void;

    public function afterCreate(CrudWriteContext $ctx): void;

    public function beforeUpdate(CrudWriteContext $ctx): void;

    public function afterUpdate(CrudWriteContext $ctx): void;

    public function beforeDelete(CrudWriteContext $ctx): void;

    public function afterDelete(CrudWriteContext $ctx): void;
}
