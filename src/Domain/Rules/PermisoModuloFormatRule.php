<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Rules;

use Lebytek\Framework\Domain\Exceptions\ValidationException;

/**
 * Normaliza y valida la etiqueta de módulo de un permiso (agrupación).
 */
final class PermisoModuloFormatRule
{
    public const PATTERN = '/^[a-z0-9_]+$/';

    public static function normalize(string $modulo): string
    {
        return strtolower(trim($modulo));
    }

    /**
     * @throws ValidationException
     */
    public static function assertValid(string $modulo, string $fieldKey = 'modulo'): string
    {
        $m = self::normalize($modulo);
        if ($m === '') {
            throw new ValidationException(
                'El módulo del permiso es obligatorio.',
                [$fieldKey => 'Indica un módulo (solo minúsculas, números y guión bajo).']
            );
        }
        if (preg_match(self::PATTERN, $m) !== 1) {
            throw new ValidationException(
                'El módulo solo puede contener letras minúsculas, números y guión bajo.',
                [$fieldKey => 'Ejemplo: administracion, demo_clientes, clientes.']
            );
        }

        return $m;
    }
}
