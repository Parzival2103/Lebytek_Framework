<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Policies\RbacPolicy;
use Lebytek\Framework\Domain\Exceptions\AccesoException;
use Lebytek\Framework\Kernel\Security\Session;

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
