<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\ValidationException;

/**
 * Validación pura de un archivo subido: error PHP, tamaño máximo, lista blanca
 * de extensiones y coherencia MIME ↔ extensión. No mueve archivos ni toca disco
 * (el MIME detectado se inyecta), por lo que es unit-testable. Conserva los
 * mensajes de error previos del CRUD Engine para no alterar el flujo existente.
 */
final class UploadValidator
{
    /**
     * MIME esperados por extensión conocida. Si una extensión NO está aquí, la
     * verificación MIME se omite (no se bloquea), preservando la capacidad de
     * subir tipos legítimos no catalogados.
     *
     * @var array<string, list<string>>
     */
    private const MIME_BY_EXT = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml', 'text/plain', 'text/xml'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    public function __construct(private readonly int $maxBytes = 10485760) {}

    /**
     * @param array<string, mixed> $file estructura de $_FILES[campo]
     * @param list<string>|null    $allowedExtensions lista blanca declarada en el campo
     * @param string|null          $detectedMime MIME real (finfo) o null para omitir el chequeo
     * @return string extensión validada en minúsculas, sin punto
     */
    public function assertValid(array $file, string $label, ?array $allowedExtensions, ?string $detectedMime): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error al subir archivo para ' . $label . '.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($this->maxBytes > 0 && $size > $this->maxBytes) {
            throw new ValidationException('El archivo para ' . $label . ' supera el tamaño máximo permitido.');
        }

        $original = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (is_array($allowedExtensions) && $allowedExtensions !== []) {
            $allowedLower = array_map(static fn($x): string => strtolower((string) $x), $allowedExtensions);
            if ($extension === '' || !in_array($extension, $allowedLower, true)) {
                throw new ValidationException('Extensión de archivo no permitida para ' . $label . '.');
            }
        }

        if ($detectedMime !== null && $extension !== '' && isset(self::MIME_BY_EXT[$extension])) {
            if (!in_array($detectedMime, self::MIME_BY_EXT[$extension], true)) {
                throw new ValidationException('El contenido del archivo para ' . $label . ' no coincide con su extensión.');
            }
        }

        return $extension;
    }
}
