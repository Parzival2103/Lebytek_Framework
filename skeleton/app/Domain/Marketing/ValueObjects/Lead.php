<?php

declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class Lead
{
    public function __construct(
        private readonly int $id,
        private readonly string $nombre,
        private readonly string $email,
        private readonly string $estado,
    ) {}

    public function id(): int { return $this->id; }
    public function nombre(): string { return $this->nombre; }
    public function email(): string { return $this->email; }
    public function estado(): string { return $this->estado; }
}
