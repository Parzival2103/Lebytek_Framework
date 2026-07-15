<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface VariantWeightRepositoryInterface
{
    /** @return array<string, float> */
    public function all(): array;

    public function get(string $slug): ?float;

    public function upsert(string $slug, float $weight): void;

    /**
     * Inserta únicamente los slugs sin fila existente en `dom_mkt_variant_weights`.
     * **Nunca** sobrescribe pesos ya editados por ops (Anti-deuda §W).
     *
     * @param array<string, float> $defaults
     */
    public function seedMissing(array $defaults): void;
}
