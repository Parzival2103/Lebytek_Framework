<?php

declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class LeadDraft
{
    /** @param array<string,string> $utm */
    public function __construct(
        private readonly string $nombre,
        private readonly string $email,
        private readonly ?string $telefono = null,
        private readonly ?string $mensaje = null,
        private readonly array $utm = [],
    ) {}

    public function nombre(): string { return $this->nombre; }
    public function email(): string { return $this->email; }
    public function telefono(): ?string { return $this->telefono; }
    public function mensaje(): ?string { return $this->mensaje; }
    /** @return array<string,string> */
    public function utm(): array { return $this->utm; }
}
