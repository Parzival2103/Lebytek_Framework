<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Domain\Interfaces\CrudHookHandlerInterface;

abstract class AbstractCrudHookHandler implements CrudHookHandlerInterface
{
    public function beforeStore(array $payload): void {}

    public function afterStore(array $payload): void {}

    public function beforeUpdate(array $payload): void {}

    public function afterUpdate(array $payload): void {}

    public function beforeDelete(array $payload): void {}

    public function afterDelete(array $payload): void {}
}
