<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Interfaces\CrudTransitionGuardInterface;

if (!class_exists('RecordingTransitionGuard')) {
    /** Registra el último contexto recibido para aserciones; nunca bloquea. */
    class RecordingTransitionGuard implements CrudTransitionGuardInterface
    {
        public static ?CrudTransitionContext $last = null;

        public function authorize(CrudTransitionContext $ctx): void
        {
            self::$last = $ctx;
        }
    }

    /** Siempre bloquea lanzando, para verificar que la transición se aborta. */
    class BlockingTransitionGuard implements CrudTransitionGuardInterface
    {
        public function authorize(CrudTransitionContext $ctx): void
        {
            throw new \RuntimeException('transición bloqueada por guard');
        }
    }
}
