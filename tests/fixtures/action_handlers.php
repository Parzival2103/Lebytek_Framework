<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudActionContext;
use Lebytek\Framework\Domain\Interfaces\CrudActionHandlerInterface;

if (!class_exists('RecordingActionHandler')) {
    /** Registra la última llamada en una propiedad estática para aserciones. */
    class RecordingActionHandler implements CrudActionHandlerInterface
    {
        public static ?CrudActionContext $last = null;
        public function handle(CrudActionContext $ctx): void
        {
            self::$last = $ctx;
        }
    }

    /** Lanza para simular un fallo de acción. */
    class FailingActionHandler implements CrudActionHandlerInterface
    {
        public function handle(CrudActionContext $ctx): void
        {
            throw new \RuntimeException('boom en acción');
        }
    }
}
