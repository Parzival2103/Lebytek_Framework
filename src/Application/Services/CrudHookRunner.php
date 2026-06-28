<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Interfaces\CrudHookHandlerInterface;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class CrudHookRunner
{
    /**
     * Mapeo de eventos canónicos a sus alias legacy. El alias solo se dispara
     * si el handler lo define explícitamente (las clases que extienden
     * AbstractCrudHookHandler no lo definen, así que no hay doble ejecución).
     */
    private const LEGACY_ALIASES = [
        'beforeCreate' => 'beforeStore',
        'afterCreate'  => 'afterStore',
    ];

    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry
    ) {}

    /**
     * Ejecuta el hook pasando el contexto por objeto (referencia de handle).
     * Las mutaciones del handler sobre el contexto son visibles al volver.
     */
    public function run(CrudResourceDefinition $definition, string $hookMethod, object $context): void
    {
        $handlerKey = $definition->hookHandler();
        if ($handlerKey === null || $handlerKey === '') {
            return;
        }

        try {
            $handler = $this->handlerRegistry->resolve($handlerKey, CrudHookHandlerInterface::class);
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

        $methods = [$hookMethod];
        if (isset(self::LEGACY_ALIASES[$hookMethod])) {
            $methods[] = self::LEGACY_ALIASES[$hookMethod];
        }

        $invoked = false;
        foreach (array_unique($methods) as $method) {
            if (!method_exists($handler, $method)) {
                continue;
            }
            $invoked = true;
            try {
                $handler->{$method}($context);
            } catch (\Throwable $e) {
                AppLogger::error('CRUD hook: excepción en ejecución', [
                    'resource' => $definition->key(),
                    'handlerKey' => $handlerKey,
                    'hook' => $method,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        if (!$invoked) {
            AppLogger::warning('CRUD hook: método no implementado en handler', [
                'resource' => $definition->key(),
                'handlerKey' => $handlerKey,
                'hook' => $hookMethod,
            ]);
        }
    }
}
