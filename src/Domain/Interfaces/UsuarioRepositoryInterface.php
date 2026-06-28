<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Domain\Entities\Usuario;
use Lebytek\Framework\Domain\ValueObjects\Email;

/*
|--------------------------------------------------------------------------
| UsuarioRepositoryInterface — Contrato de persistencia de Usuario
|--------------------------------------------------------------------------
| El dominio define qué necesita; la infraestructura implementa cómo.
*/

interface UsuarioRepositoryInterface
{
    public function findById(int $id): ?Usuario;

    public function findByEmail(Email $email): ?Usuario;

    /** @return Usuario[] */
    public function findAll(int $limit = 50, int $offset = 0): array;

    public function countAll(): int;

    public function save(Usuario $usuario): int;

    public function update(Usuario $usuario): void;

    /** Actualiza solo la columna avatar (cache denormalizado del avatar actual). */
    public function actualizarAvatar(int $usuarioId, ?string $ruta): void;

    /** Activa la cuenta y registra el momento de verificación del correo. */
    public function marcarEmailVerificado(int $usuarioId): void;

    public function delete(int $id): void;

    public function emailExists(Email $email, ?int $excludeId = null): bool;
}
