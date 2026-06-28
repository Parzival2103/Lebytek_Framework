<?php

declare(strict_types=1);

namespace App\Kernel\Http;

/**
 * Normaliza un destino de redirección (típicamente el header `Referer`) a una
 * ruta interna segura. Solo acepta rutas root-relative same-origin; cualquier
 * URL absoluta, protocol-relative, con esquema o con caracteres de control cae
 * al fallback. No cambia el resultado de redirecciones internas legítimas.
 */
final class SafeRedirect
{
    public static function toInternal(?string $candidate, string $fallback = '/'): string
    {
        $candidate = is_string($candidate) ? trim($candidate) : '';
        if ($candidate === '') {
            return $fallback;
        }

        // Rechazar caracteres de control / CRLF (header injection).
        if (preg_match('/[\x00-\x1f\x7f]/', $candidate) === 1) {
            return $fallback;
        }

        // Los navegadores tratan '\' como '/'. Normalizar para detectar
        // '//host' y '/\host' disfrazados antes de decidir.
        $normalized = str_replace('\\', '/', $candidate);

        // Debe ser root-relative ('/algo') y NO protocol-relative ('//host').
        if ($normalized[0] !== '/') {
            return $fallback;
        }
        if (isset($normalized[1]) && $normalized[1] === '/') {
            return $fallback;
        }

        return $candidate;
    }
}
