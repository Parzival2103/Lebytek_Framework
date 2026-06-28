<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

/**
 * Resuelve la URL de retorno tras mutaciones CRUD cuando el recurso tiene
 * calendario vinculado en config/calendars/{key}.json.
 */
final class CrudReturnUrlResolver
{
    public function __construct(
        private readonly CalendarConfigLoader $calendarLoader,
    ) {}

    public function resolve(string $resource, ?string $candidate = null): string
    {
        $resource = trim($resource);
        if ($resource === '') {
            return '/admin/dashboard';
        }

        $validated = $this->validateCalendarReturn($resource, $candidate);
        if ($validated !== null) {
            return $validated;
        }

        $calendarKey = $this->calendarLoader->findKeyForResource($resource);
        if ($calendarKey !== null) {
            return '/admin/calendario/' . $calendarKey;
        }

        return '/admin/crud/' . $resource;
    }

    private function validateCalendarReturn(string $resource, ?string $candidate): ?string
    {
        if ($candidate === null || $candidate === '') {
            return null;
        }

        if (!preg_match('#^/admin/calendario/([a-z0-9_]+)$#', $candidate, $matches)) {
            return null;
        }

        try {
            $definition = $this->calendarLoader->load($matches[1]);
        } catch (\Throwable) {
            return null;
        }

        return $definition->resource() === $resource ? $candidate : null;
    }
}
