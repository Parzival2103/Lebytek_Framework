<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Reporte;

/**
 * Reporte guardado por un usuario: su selección (columnas, tratamientos, filtros,
 * periodo, opciones) sobre una fuente reportable. Hidrata desde una fila de
 * rep_reportes decodificando las columnas JSON.
 */
final class ReporteGuardado
{
    /**
     * @param list<array<string,mixed>> $columnas
     * @param array<string,mixed> $tratamientos
     * @param array<string,mixed> $filtros
     * @param array<string,mixed> $periodo
     * @param array<string,mixed> $opciones
     */
    public function __construct(
        private readonly int $id,
        private readonly string $nombre,
        private readonly string $fuenteKey,
        private readonly string $modo,
        private readonly array $columnas,
        private readonly array $tratamientos,
        private readonly array $filtros,
        private readonly array $periodo,
        private readonly array $opciones,
        private readonly string $templateKey,
        private readonly bool $compartido,
        private readonly ?int $createdBy,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['nombre'] ?? ''),
            (string) ($row['fuente_key'] ?? ''),
            (string) ($row['modo'] ?? 'coleccion'),
            self::decodeList($row['columnas'] ?? null),
            self::decodeMap($row['tratamientos'] ?? null),
            self::decodeMap($row['filtros'] ?? null),
            self::decodeMap($row['periodo'] ?? null),
            self::decodeMap($row['opciones'] ?? null),
            (string) ($row['template_key'] ?? ''),
            (bool) ($row['compartido'] ?? false),
            isset($row['created_by']) ? (int) $row['created_by'] : null,
        );
    }

    /** @return list<array<string,mixed>> */
    private static function decodeList(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @return array<string,mixed> */
    private static function decodeMap(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        return is_array($decoded) ? $decoded : [];
    }

    public function id(): int { return $this->id; }
    public function nombre(): string { return $this->nombre; }
    public function fuenteKey(): string { return $this->fuenteKey; }
    public function modo(): string { return $this->modo; }
    /** @return list<array<string,mixed>> */
    public function columnas(): array { return $this->columnas; }
    /** @return array<string,mixed> */
    public function tratamientos(): array { return $this->tratamientos; }
    /** @return array<string,mixed> */
    public function filtros(): array { return $this->filtros; }
    /** @return array<string,mixed> */
    public function periodo(): array { return $this->periodo; }
    /** @return array<string,mixed> */
    public function opciones(): array { return $this->opciones; }
    public function templateKey(): string { return $this->templateKey; }
    public function compartido(): bool { return $this->compartido; }
    public function createdBy(): ?int { return $this->createdBy; }
}
