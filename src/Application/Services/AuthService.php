<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Entities\Usuario;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\PermisoRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\Policies\RbacPolicy;
use Lebytek\Framework\Domain\Exceptions\AuthException;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Kernel\Security\Hash;

/*
|--------------------------------------------------------------------------
| AuthService — Orquesta la autenticación y la sesión activa
|--------------------------------------------------------------------------
*/

final class AuthService
{
    public function __construct(
        private readonly UsuarioRepositoryInterface  $usuarioRepo,
        private readonly PermisoRepositoryInterface $permisoRepo,
        private readonly RolRepositoryInterface     $rolRepo
    ) {}

    public function autenticar(string $email, string $password): Usuario
    {
        $emailVO  = new \Lebytek\Framework\Domain\ValueObjects\Email($email);
        $usuario  = $this->usuarioRepo->findByEmail($emailVO);

        if ($usuario === null || !Hash::verify($password, $usuario->passwordHash())) {
            throw new AuthException('Credenciales incorrectas.');
        }

        if (!$usuario->puedeIniciarSesion()) {
            throw new AuthException('Tu cuenta está desactivada. Contacta al administrador.');
        }

        // Actualizar último acceso
        $usuarioActualizado = $usuario->registrarAcceso(new \DateTimeImmutable());
        $this->usuarioRepo->update($usuarioActualizado);

        return $usuarioActualizado;
    }

    public function iniciarSesion(Usuario $usuario): void
    {
        Session::regenerate();

        $permisos = $this->permisoRepo->slugsPorUsuarioId($usuario->id());
        $roles    = array_map(
            fn($rol) => (string) $rol->slug(),
            $this->rolRepo->buscarPorUsuarioId($usuario->id())
        );

        Session::set('auth_user', [
            'id'              => $usuario->id(),
            'nombre'          => $usuario->nombre(),
            'apellido'        => $usuario->apellido(),
            'nombreCompleto'  => $usuario->nombreCompleto(),
            'email'           => (string) $usuario->email(),
            'avatar'          => $usuario->avatar(),
        ]);
        Session::set('auth_permisos', $permisos);
        Session::set('auth_roles',    $roles);
    }

    public function cerrarSesion(): void
    {
        Session::forget('auth_user');
        Session::forget('auth_permisos');
        Session::forget('auth_roles');
        Session::regenerate();
    }

    public function estaAutenticado(): bool
    {
        return Session::has('auth_user');
    }

    public function usuarioActual(): ?array
    {
        return Session::get('auth_user');
    }

    public function politica(): RbacPolicy
    {
        return new RbacPolicy(
            Session::get('auth_permisos', []),
            Session::get('auth_roles',    [])
        );
    }

    public function recargarPermisos(int $usuarioId): void
    {
        $permisos = $this->permisoRepo->slugsPorUsuarioId($usuarioId);
        $roles    = array_map(
            fn($rol) => (string) $rol->slug(),
            $this->rolRepo->buscarPorUsuarioId($usuarioId)
        );

        Session::set('auth_permisos', $permisos);
        Session::set('auth_roles',    $roles);
    }
}
