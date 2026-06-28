<?php

declare(strict_types=1);

namespace App\Application\Validators\Auth;

use App\Domain\Exceptions\ValidationException;

final class LoginValidator
{
    public function validate(array $credenciales): void
    {
        $errors = [];

        if (empty($credenciales['email'])) {
            $errors['email'] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($credenciales['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El correo electrónico no tiene un formato válido.';
        }

        if (empty($credenciales['password'])) {
            $errors['password'] = 'La contraseña es obligatoria.';
        } elseif (strlen($credenciales['password']) < 6) {
            $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Los datos de inicio de sesión son inválidos.', $errors);
        }
    }
}
