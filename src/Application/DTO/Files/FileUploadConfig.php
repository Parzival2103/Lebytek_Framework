<?php

declare(strict_types=1);

namespace App\Application\DTO\Files;

/*
|--------------------------------------------------------------------------
| FileUploadConfig — Configuración de una subida de archivo
|--------------------------------------------------------------------------
| DTO inmutable que describe destino, límites y metadatos de ledger
| para FileUploadService::handle().
*/

final class FileUploadConfig
{
    public function __construct(
        public readonly string $entidadTipo,            // 'usuario' | 'crud:<key>' | ...
        public readonly string $directorio,             // relativo a public/ (p.ej. 'uploads/avatars')
        public readonly int $maxBytes,
        public readonly ?int $entidadId = null,
        public readonly string $coleccion = 'default',
        public readonly string $disco = 'public',       // 'public' | 'private'
        public readonly ?array $allowedExtensions = null,
        public readonly ?ImageOptions $imagen = null,
        public readonly bool $esActual = false,
        public readonly ?int $creadoPor = null
    ) {
    }
}
