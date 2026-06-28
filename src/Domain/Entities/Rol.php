<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Slug;
use App\Domain\Exceptions\ValidationException;

/*
|--------------------------------------------------------------------------
| Rol — Entidad de dominio para roles del sistema RBAC
|--------------------------------------------------------------------------
*/

final class Rol
{
    public const SLUG_ADMIN      = 'administrador';
    public const SLUG_VENTAS     = 'ventas';
    public const SLUG_PRODUCCION = 'produccion';
    public const SLUG_DIRECCION  = 'direccion';

    private ?int   $id;
    private string $nombre;
    private Slug   $slug;
    private string $descripcion;
    private bool   $activo;
    private array  $permisos = [];

    public function __construct(
        string $nombre,
        Slug   $slug,
        string $descripcion = '',
        bool   $activo      = true,
        ?int   $id          = null
    ) {
        if (trim($nombre) === '') {
            throw new ValidationException('El nombre del rol no puede estar vacío.');
        }

        $this->id          = $id;
        $this->nombre      = trim($nombre);
        $this->slug        = $slug;
        $this->descripcion = trim($descripcion);
        $this->activo      = $activo;
    }

    public function asignarPermiso(Permiso $permiso): void
    {
        $this->permisos[$permiso->slug()->value()] = $permiso;
    }

    public function revocarPermiso(string $slugPermiso): void
    {
        unset($this->permisos[$slugPermiso]);
    }

    public function tienePermiso(string $slugPermiso): bool
    {
        return isset($this->permisos[$slugPermiso]);
    }

    public function id(): ?int           { return $this->id;          }
    public function nombre(): string     { return $this->nombre;      }
    public function slug(): Slug         { return $this->slug;        }
    public function descripcion(): string { return $this->descripcion; }
    public function activo(): bool       { return $this->activo;      }
    public function permisos(): array    { return $this->permisos;    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'nombre'      => $this->nombre,
            'slug'        => (string) $this->slug,
            'descripcion' => $this->descripcion,
            'activo'      => $this->activo,
        ];
    }
}
