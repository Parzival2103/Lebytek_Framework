<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Policies\RbacPolicy;
use App\Domain\Exceptions\AccesoException;
use App\Kernel\Security\Session;

final class RbacService
{
    public function politicaDeUsuarioActual(): RbacPolicy
    {
        return new RbacPolicy(
            Session::get('auth_permisos', []),
            Session::get('auth_roles',    [])
        );
    }

    public function verificar(string $permiso): void
    {
        if (!$this->politicaDeUsuarioActual()->puede($permiso)) {
            throw new AccesoException("No tienes permiso para realizar esta acción: {$permiso}");
        }
    }

    public function puede(string $permiso): bool
    {
        return $this->politicaDeUsuarioActual()->puede($permiso);
    }

    public function esAdministrador(): bool
    {
        return $this->politicaDeUsuarioActual()->esAdministrador();
    }
}
