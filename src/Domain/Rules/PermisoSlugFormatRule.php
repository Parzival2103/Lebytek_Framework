<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Rules;

use Lebytek\Framework\Domain\Exceptions\ValidationException;

/**
 * Formato canónico de slug de permiso RBAC: modulo.accion (minúsculas, números, guion bajo).
 */
final class PermisoSlugFormatRule
{
    public const PATTERN = '/^[a-z0-9_]+\.[a-z0-9_]+$/';

    public static function isValid(string $slug): bool
    {
        $slug = trim($slug);

        return $slug !== '' && preg_match(self::PATTERN, $slug) === 1;
    }

    /**
     * @throws ValidationException
     */
    public static function assertValid(string $slug, string $fieldKey = 'slug'): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new ValidationException(
                'El slug del permiso es obligatorio.',
                [$fieldKey => 'Indica un slug válido (modulo.accion).']
            );
        }
        if (!self::isValid($slug)) {
            throw new ValidationException(
                "El slug '{$slug}' no cumple el formato modulo.accion (solo minúsculas, números y guión bajo; exactamente un punto).",
                [$fieldKey => 'Use el patrón modulo.accion, por ejemplo usuarios.gestionar o demo_clientes.ver.']
            );
        }
    }
}
