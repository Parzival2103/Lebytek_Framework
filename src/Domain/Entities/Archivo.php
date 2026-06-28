<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/*
|--------------------------------------------------------------------------
| Archivo — Entidad de dominio
|--------------------------------------------------------------------------
| Representa un archivo subido registrado en el ledger central
| (core_archivos). Entidad pura e inmutable: no depende de SQL ni HTTP.
*/

final class Archivo
{
    public function __construct(
        private string  $entidadTipo,
        private ?int    $entidadId,
        private string  $coleccion,
        private string  $ruta,
        private ?string $thumbnailRuta = null,
        private ?string $nombreOriginal = null,
        private ?string $mime = null,
        private ?string $extension = null,
        private int     $tamanoBytes = 0,
        private string  $disco = 'public',
        private bool    $esActual = false,
        private ?int    $creadoPor = null,
        private ?string $createdAt = null,
        private ?string $deletedAt = null,
        private ?int    $id = null
    ) {
    }

    public static function desdeFila(array $row): self
    {
        return new self(
            entidadTipo:    (string) $row['entidad_tipo'],
            entidadId:      isset($row['entidad_id']) ? (int) $row['entidad_id'] : null,
            coleccion:      (string) ($row['coleccion'] ?? 'default'),
            ruta:           (string) $row['ruta'],
            thumbnailRuta:  isset($row['thumbnail_ruta']) ? (string) $row['thumbnail_ruta'] : null,
            nombreOriginal: isset($row['nombre_original']) ? (string) $row['nombre_original'] : null,
            mime:           isset($row['mime']) ? (string) $row['mime'] : null,
            extension:      isset($row['extension']) ? (string) $row['extension'] : null,
            tamanoBytes:    (int) ($row['tamano_bytes'] ?? 0),
            disco:          (string) ($row['disco'] ?? 'public'),
            esActual:       (bool) ($row['es_actual'] ?? false),
            creadoPor:      isset($row['creado_por']) ? (int) $row['creado_por'] : null,
            createdAt:      isset($row['created_at']) ? (string) $row['created_at'] : null,
            deletedAt:      isset($row['deleted_at']) ? (string) $row['deleted_at'] : null,
            id:             isset($row['id']) ? (int) $row['id'] : null
        );
    }

    // ── Comportamiento (clones inmutables) ────────────────────────────────────

    public function marcarComoActual(): self
    {
        $clone           = clone $this;
        $clone->esActual = true;
        return $clone;
    }

    public function marcarBorrado(?string $momento = null): self
    {
        $clone            = clone $this;
        $clone->deletedAt = $momento ?? date('Y-m-d H:i:s');
        $clone->esActual  = false;
        return $clone;
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function id(): ?int                 { return $this->id;             }
    public function entidadTipo(): string      { return $this->entidadTipo;    }
    public function entidadId(): ?int          { return $this->entidadId;      }
    public function coleccion(): string        { return $this->coleccion;      }
    public function ruta(): string             { return $this->ruta;           }
    public function thumbnailRuta(): ?string   { return $this->thumbnailRuta;  }
    public function nombreOriginal(): ?string  { return $this->nombreOriginal; }
    public function mime(): ?string            { return $this->mime;           }
    public function extension(): ?string       { return $this->extension;      }
    public function tamanoBytes(): int         { return $this->tamanoBytes;    }
    public function disco(): string            { return $this->disco;          }
    public function esActual(): bool           { return $this->esActual;       }
    public function creadoPor(): ?int          { return $this->creadoPor;      }
    public function createdAt(): ?string       { return $this->createdAt;      }
    public function deletedAt(): ?string       { return $this->deletedAt;      }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'entidadTipo'    => $this->entidadTipo,
            'entidadId'      => $this->entidadId,
            'coleccion'      => $this->coleccion,
            'ruta'           => $this->ruta,
            'thumbnailRuta'  => $this->thumbnailRuta,
            'nombreOriginal' => $this->nombreOriginal,
            'mime'           => $this->mime,
            'extension'      => $this->extension,
            'tamanoBytes'    => $this->tamanoBytes,
            'disco'          => $this->disco,
            'esActual'       => $this->esActual,
            'creadoPor'      => $this->creadoPor,
            'createdAt'      => $this->createdAt,
            'deletedAt'      => $this->deletedAt,
        ];
    }
}
