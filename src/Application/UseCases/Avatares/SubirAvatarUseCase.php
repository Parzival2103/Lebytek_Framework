<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Avatares;

use Lebytek\Framework\Application\DTO\Files\FileUploadConfig;
use Lebytek\Framework\Application\Services\FileUploadService;
use Lebytek\Framework\Domain\Entities\Archivo;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;
use Lebytek\Framework\Kernel\Config\Config;

/*
|--------------------------------------------------------------------------
| SubirAvatarUseCase — Sube un avatar y lo deja como actual
|--------------------------------------------------------------------------
| La autorización la aplican los controllers (AvatarPolicy); aquí solo
| se sube al ledger con la config de avatar y se refresca el cache
| denormalizado auth_usuarios.avatar.
*/

final class SubirAvatarUseCase
{
    public function __construct(
        private readonly FileUploadService $uploads,
        private readonly UsuarioRepositoryInterface $usuarioRepo
    ) {
    }

    /** @param array<string, mixed> $file estructura $_FILES[campo] */
    public function execute(int $usuarioId, array $file, int $actorId): Archivo
    {
        $archivo = $this->uploads->handle($file, new FileUploadConfig(
            entidadTipo: AvatarDefaults::ENTIDAD_TIPO,
            directorio: AvatarDefaults::DIRECTORIO,
            maxBytes: ((int) Config::get('security.max_upload_mb', 10)) * 1024 * 1024,
            entidadId: $usuarioId,
            coleccion: AvatarDefaults::COLECCION,
            allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
            imagen: AvatarDefaults::imageOptions(),
            esActual: true,
            creadoPor: $actorId
        ), 'Avatar');

        $this->usuarioRepo->actualizarAvatar($usuarioId, $archivo->ruta());

        return $archivo;
    }
}
