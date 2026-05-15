<?php

declare(strict_types=1);

namespace App\Domain\Policies;

/*
|--------------------------------------------------------------------------
| RbacPolicy — Motor de decisión de acceso RBAC
|--------------------------------------------------------------------------
| Determina si un usuario (dado su conjunto de permisos y roles) puede
| realizar una acción. No depende de DB, HTTP ni sesión.
*/

final class RbacPolicy
{
    private array $permisosUsuario;
    private array $rolesUsuario;

    /**
     * @param string[] $permisosUsuario  Slugs de permisos activos
     * @param string[] $rolesUsuario     Slugs de roles activos
     */
    public function __construct(array $permisosUsuario, array $rolesUsuario)
    {
        $this->permisosUsuario = $permisosUsuario;
        $this->rolesUsuario    = $rolesUsuario;
    }

    public function puede(string $permiso): bool
    {
        // El administrador tiene acceso total
        if ($this->esAdministrador()) {
            return true;
        }

        return in_array($permiso, $this->permisosUsuario, true);
    }

    public function puedeAlguno(array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if ($this->puede($permiso)) {
                return true;
            }
        }
        return false;
    }

    public function puedeTodos(array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if (!$this->puede($permiso)) {
                return false;
            }
        }
        return true;
    }

    public function tieneRol(string $rolSlug): bool
    {
        return in_array($rolSlug, $this->rolesUsuario, true);
    }

    public function esAdministrador(): bool
    {
        return $this->tieneRol('administrador');
    }

    public function permisosActivos(): array
    {
        return $this->permisosUsuario;
    }

    public function rolesActivos(): array
    {
        return $this->rolesUsuario;
    }
}
