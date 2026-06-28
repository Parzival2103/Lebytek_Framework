<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Entities;

/*
|--------------------------------------------------------------------------
| AuthToken — Entidad de dominio
|--------------------------------------------------------------------------
| Token de un solo uso para verificación de correo y recuperación de
| contraseña (auth_tokens). Solo persiste el hash sha256 del token.
| Entidad pura e inmutable: no depende de SQL ni HTTP.
*/

final class AuthToken
{
    public const TIPO_RECUPERACION = 'recuperacion';
    public const TIPO_VERIFICACION = 'verificacion';

    public function __construct(
        private int     $usuarioId,
        private string  $tipo,
        private string  $tokenHash,
        private string  $expiraEn,
        private ?string $usadoEn = null,
        private ?string $createdAt = null,
        private ?int    $id = null
    ) {
    }

    public static function desdeFila(array $row): self
    {
        return new self(
            usuarioId: (int) $row['usuario_id'],
            tipo:      (string) $row['tipo'],
            tokenHash: (string) $row['token_hash'],
            expiraEn:  (string) $row['expira_en'],
            usadoEn:   isset($row['usado_en']) ? (string) $row['usado_en'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            id:        isset($row['id']) ? (int) $row['id'] : null
        );
    }

    public function estaVigente(?string $ahora = null): bool
    {
        $ahora ??= date('Y-m-d H:i:s');
        return $this->usadoEn === null && $this->expiraEn > $ahora;
    }

    public function marcarUsado(?string $momento = null): self
    {
        $clone          = clone $this;
        $clone->usadoEn = $momento ?? date('Y-m-d H:i:s');
        return $clone;
    }

    public function id(): ?int           { return $this->id;        }
    public function usuarioId(): int     { return $this->usuarioId; }
    public function tipo(): string       { return $this->tipo;      }
    public function tokenHash(): string  { return $this->tokenHash; }
    public function expiraEn(): string   { return $this->expiraEn;  }
    public function usadoEn(): ?string   { return $this->usadoEn;   }
    public function createdAt(): ?string { return $this->createdAt; }
}
