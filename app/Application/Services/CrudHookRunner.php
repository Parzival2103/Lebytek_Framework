<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudResourceDefinition;
use App\Kernel\Logging\AppLogger;

final class CrudHookRunner
{
    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry
    ) {}

    public function run(CrudResourceDefinition $definition, string $hookMethod, array $payload = []): void
    {
        $handlerKey = $definition->hookHandler();
        if ($handlerKey === null || $handlerKey === '') {
            return;
        }

        try {
            $handler = $this->handlerRegistry->resolve($handlerKey);
        } catch (\Throwable $e) {
            AppLogger::error('CRUD hook: error al resolver handler', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($handler === null) {
            AppLogger::warning('CRUD hook: handler no registrado en whitelist', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
            ]);
            return;
        }

        if (!method_exists($handler, $hookMethod)) {
            AppLogger::warning('CRUD hook: método no implementado en handler', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
            ]);
            return;
        }

        try {
            $handler->{$hookMethod}($payload);
        } catch (\Throwable $e) {
            AppLogger::error('CRUD hook: excepción en ejecución', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
