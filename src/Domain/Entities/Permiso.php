<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Entities;

use Lebytek\Framework\Domain\ValueObjects\Slug;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

/*
|--------------------------------------------------------------------------
| Permiso — Entidad de dominio para permisos RBAC
|--------------------------------------------------------------------------
| Ejemplo de slug: "usuarios.gestionar", "dashboard.ver", "administracion.ver"
*/

final class Permiso
{
    private ?int   $id;
    private string $nombre;
    private Slug   $slug;
    private string $descripcion;
    private string $modulo;

    public function __construct(
        string $nombre,
        Slug   $slug,
        string $modulo      = '',
        string $descripcion = '',
        ?int   $id          = null
    ) {
        if (trim($nombre) === '') {
            throw new ValidationException('El nombre del permiso no puede estar vacío.');
        }

        $this->id          = $id;
        $this->nombre      = trim($nombre);
        $this->slug        = $slug;
        $this->modulo      = strtolower(trim($modulo));
        $this->descripcion = trim($descripcion);
    }

    public function id(): ?int           { return $this->id;          }
    public function nombre(): string     { return $this->nombre;      }
    public function slug(): Slug         { return $this->slug;        }
    public function modulo(): string     { return $this->modulo;      }
    public function descripcion(): string { return $this->descripcion; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'nombre'      => $this->nombre,
            'slug'        => (string) $this->slug,
            'modulo'      => $this->modulo,
            'descripcion' => $this->descripcion,
        ];
    }
}
