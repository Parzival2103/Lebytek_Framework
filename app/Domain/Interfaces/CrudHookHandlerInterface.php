<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

/**
 * Contrato para hooks del CRUD Engine.
 * Los métodos son no-op por defecto; las implementaciones sobrescriben solo lo necesario.
 */
interface CrudHookHandlerInterface
{
    public function beforeStore(array $payload): void;

    public function afterStore(array $payload): void;

    public function beforeUpdate(array $payload): void;

    public function afterUpdate(array $payload): void;

    public function beforeDelete(array $payload): void;

    public function afterDelete(array $payload): void;
}
