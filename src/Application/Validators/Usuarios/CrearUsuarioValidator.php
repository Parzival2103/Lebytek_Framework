<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Validators\Usuarios;

use Lebytek\Framework\Domain\Exceptions\ValidationException;

final class CrearUsuarioValidator
{
    public function validate(array $datosUsuario): void
    {
        $errors = [];

        if (empty(trim($datosUsuario['nombre'] ?? ''))) {
            $errors['nombre'] = 'El nombre es obligatorio.';
        }

        if (empty(trim($datosUsuario['apellido'] ?? ''))) {
            $errors['apellido'] = 'El apellido es obligatorio.';
        }

        if (empty($datosUsuario['email'])) {
            $errors['email'] = 'El correo es obligatorio.';
        } elseif (!filter_var($datosUsuario['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El formato del correo no es válido.';
        }

        if (empty($datosUsuario['password'])) {
            $errors['password'] = 'La contraseña es obligatoria.';
        } elseif (strlen($datosUsuario['password']) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Los datos del usuario son inválidos.', $errors);
        }
    }

    public function validateUpdate(array $datosUsuario): void
    {
        $errors = [];

        if (empty(trim($datosUsuario['nombre'] ?? ''))) {
            $errors['nombre'] = 'El nombre es obligatorio.';
        }

        if (empty(trim($datosUsuario['apellido'] ?? ''))) {
            $errors['apellido'] = 'El apellido es obligatorio.';
        }

        if (empty($datosUsuario['email'])) {
            $errors['email'] = 'El correo es obligatorio.';
        } elseif (!filter_var($datosUsuario['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El formato del correo no es válido.';
        }

        if (!empty($datosUsuario['password']) && strlen($datosUsuario['password']) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Los datos del usuario son inválidos.', $errors);
        }
    }
}
