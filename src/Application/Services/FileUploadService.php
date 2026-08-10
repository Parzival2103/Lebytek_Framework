<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Application\DTO\Files\FileUploadConfig;
use Lebytek\Framework\Domain\Entities\Archivo;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\ArchivoRepositoryInterface;

/*
|--------------------------------------------------------------------------
| FileUploadService — Handler compartido de subidas de archivos
|--------------------------------------------------------------------------
| Punto único de entrada para uploads de cualquier módulo: valida
| (UploadValidator), mueve a disco con nombre seguro, post-procesa
| imágenes (ImageProcessor) y registra en el ledger core_archivos.
| Conserva los mensajes de error históricos del CRUD Engine.
*/

final class FileUploadService
{
    public function __construct(
        private readonly ImageProcessor $imageProcessor,
        private readonly ArchivoRepositoryInterface $archivos
    ) {
    }

    /**
     * @param array<string, mixed> $file estructura de $_FILES[campo]
     * @param string|null $label etiqueta para mensajes de validación (default: colección)
     */
    public function handle(array $file, FileUploadConfig $cfg, ?string $label = null): Archivo
    {
        $label ??= $cfg->coleccion;

        $original = (string) ($file['name'] ?? '');
        $tmpName  = (string) ($file['tmp_name'] ?? '');

        $detectedMime = null;
        if ($tmpName !== '' && is_readable($tmpName) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = finfo_file($finfo, $tmpName) ?: null;
                finfo_close($finfo);
            }
        }

        $validator = new UploadValidator($cfg->maxBytes);
        $extension = $validator->assertValid($file, $label, $cfg->allowedExtensions, $detectedMime);

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original, PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'upload';
        }
        $filename = $safeName . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        $publicRelative = trim(str_replace('\\', '/', $cfg->directorio), '/');
        $publicAbsolute = $this->resolvePublicUploadDirectory($cfg->directorio);

        $destination = $publicAbsolute . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            // Camino de tests/CLI: el origen no es un upload HTTP real.
            if (is_uploaded_file($tmpName) || !@rename($tmpName, $destination)) {
                throw new ValidationException('No fue posible guardar el archivo subido.');
            }
        }

        if ($cfg->imagen !== null) {
            $this->imageProcessor->redimensionar($destination, $cfg->imagen);
        }

        $archivo = Archivo::desdeFila([
            'entidad_tipo'    => $cfg->entidadTipo,
            'entidad_id'      => $cfg->entidadId,
            'coleccion'       => $cfg->coleccion,
            'ruta'            => '/' . $publicRelative . '/' . $filename,
            'nombre_original' => $original !== '' ? $original : null,
            'mime'            => $detectedMime,
            'extension'       => $extension !== '' ? $extension : null,
            'tamano_bytes'    => is_file($destination) ? (int) filesize($destination) : 0,
            'disco'           => $cfg->disco,
            'es_actual'       => $cfg->esActual && $cfg->entidadId !== null,
            'creado_por'      => $cfg->creadoPor,
        ]);

        $id = $this->archivos->guardar($archivo);

        if ($cfg->esActual && $cfg->entidadId !== null) {
            $this->archivos->marcarActual($id, $cfg->entidadTipo, $cfg->entidadId, $cfg->coleccion);
        }

        return $this->archivos->buscarPorId($id) ?? $archivo;
    }

    private function resolvePublicUploadDirectory(string $directorio): string
    {
        $root = realpath(PUBLIC_PATH);
        if ($root === false) {
            throw new ValidationException('No fue posible resolver el directorio público de uploads.');
        }

        $relative = trim(str_replace('\\', '/', $directorio), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new ValidationException('El directorio de uploads no es válido.');
        }

        if (!str_starts_with($relative, 'uploads/')) {
            throw new ValidationException('El directorio de uploads debe comenzar con uploads/.');
        }

        $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) {
            throw new ValidationException('No fue posible crear el directorio de uploads.');
        }

        $resolved = realpath($absolute);
        $jailRoot = realpath($root . DIRECTORY_SEPARATOR . 'uploads');
        if ($resolved === false || $jailRoot === false || !str_starts_with($resolved, $jailRoot . DIRECTORY_SEPARATOR)) {
            throw new ValidationException('El directorio de uploads está fuera del área permitida.');
        }

        return $resolved;
    }
}
