<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Kernel\Security\Hash;

/*
|--------------------------------------------------------------------------
| RestablecerPasswordUseCase — Consume el token y actualiza el hash
|--------------------------------------------------------------------------
| Valida primero el password (mismas reglas que CrearUsuarioValidator)
| para no consumir el token con datos inválidos.
*/

final class RestablecerPasswordUseCase
{
    private const MENSAJE_INVALIDO = 'El enlace de recuperación no es válido o ya expiró.';

    public function __construct(
        private readonly UsuarioRepositoryInterface   $usuarioRepo,
        private readonly AuthTokenRepositoryInterface $tokenRepo
    ) {
    }

    public function execute(string $tokenClaro, string $password, string $passwordConfirmacion): void
    {
        $errors = [];
        if (empty($password)) {
            $errors['password'] = 'La contraseña es obligatoria.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($password !== $passwordConfirmacion) {
            $errors['password_confirmacion'] = 'Las contraseñas no coinciden.';
        }
        if (!empty($errors)) {
            throw new ValidationException('Los datos son inválidos.', $errors);
        }

        $token = $this->tokenRepo->buscarVigentePorHash(
            hash('sha256', $tokenClaro),
            AuthToken::TIPO_RECUPERACION
        );

        if ($token === null || $token->id() === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $usuario = $this->usuarioRepo->findById($token->usuarioId());
        if ($usuario === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $this->tokenRepo->marcarUsado($token->id());
        $this->usuarioRepo->update($usuario->cambiarContrasena(Hash::make($password)));
    }
}
