<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Email;
use App\Domain\Exceptions\ValidationException;

/*
|--------------------------------------------------------------------------
| Usuario — Entidad de dominio
|--------------------------------------------------------------------------
| Representa un usuario del sistema con su identidad y estado.
| No depende de frameworks, HTTP, SQL ni de la capa de presentación.
*/

final class Usuario
{
    private ?int   $id;
    private string $nombre;
    private string $apellido;
    private Email  $email;
    private string $passwordHash;
    private bool   $activo;
    private ?string $avatar;
    private ?\DateTimeImmutable $ultimoAcceso;
    private ?\DateTimeImmutable $emailVerificadoEn;
    private \DateTimeImmutable $creadoEn;

    public function __construct(
        string  $nombre,
        string  $apellido,
        Email   $email,
        string  $passwordHash,
        bool    $activo          = true,
        ?string $avatar          = null,
        ?\DateTimeImmutable $ultimoAcceso = null,
        ?\DateTimeImmutable $creadoEn     = null,
        ?\DateTimeImmutable $emailVerificadoEn = null,
        ?int    $id              = null
    ) {
        self::assertNombreValido($nombre);
        self::assertNombreValido($apellido);

        $this->id           = $id;
        $this->nombre       = trim($nombre);
        $this->apellido     = trim($apellido);
        $this->email        = $email;
        $this->passwordHash = $passwordHash;
        $this->activo       = $activo;
        $this->avatar       = $avatar;
        $this->ultimoAcceso = $ultimoAcceso;
        $this->emailVerificadoEn = $emailVerificadoEn;
        $this->creadoEn     = $creadoEn ?? new \DateTimeImmutable();
    }

    // ── Reglas de negocio ─────────────────────────────────────────────────────

    private static function assertNombreValido(string $nombre): void
    {
        if (trim($nombre) === '') {
            throw new ValidationException('El nombre no puede estar vacío.');
        }
        if (strlen($nombre) > 100) {
            throw new ValidationException('El nombre no puede superar 100 caracteres.');
        }
    }

    public function puedeIniciarSesion(): bool
    {
        return $this->activo;
    }

    public function registrarAcceso(\DateTimeImmutable $momento): self
    {
        $clone               = clone $this;
        $clone->ultimoAcceso = $momento;
        return $clone;
    }

    public function cambiarContrasena(string $nuevoHash): self
    {
        $clone               = clone $this;
        $clone->passwordHash = $nuevoHash;
        return $clone;
    }

    public function desactivar(): self
    {
        $clone         = clone $this;
        $clone->activo = false;
        return $clone;
    }

    public function activar(): self
    {
        $clone         = clone $this;
        $clone->activo = true;
        return $clone;
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function id(): ?int                         { return $this->id;           }
    public function nombre(): string                   { return $this->nombre;       }
    public function apellido(): string                 { return $this->apellido;     }
    public function nombreCompleto(): string           { return "{$this->nombre} {$this->apellido}"; }
    public function email(): Email                     { return $this->email;        }
    public function passwordHash(): string             { return $this->passwordHash; }
    public function activo(): bool                     { return $this->activo;       }
    public function avatar(): ?string                  { return $this->avatar;       }
    public function ultimoAcceso(): ?\DateTimeImmutable { return $this->ultimoAcceso; }
    public function emailVerificadoEn(): ?\DateTimeImmutable { return $this->emailVerificadoEn; }
    public function creadoEn(): \DateTimeImmutable     { return $this->creadoEn;    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'nombre'        => $this->nombre,
            'apellido'      => $this->apellido,
            'nombreCompleto' => $this->nombreCompleto(),
            'email'         => (string) $this->email,
            'activo'        => $this->activo,
            'avatar'        => $this->avatar,
            'ultimo_acceso' => $this->ultimoAcceso?->format('Y-m-d H:i:s'),
            'email_verificado_en' => $this->emailVerificadoEn?->format('Y-m-d H:i:s'),
            'created_at'    => $this->creadoEn->format('Y-m-d H:i:s'),
        ];
    }
}
