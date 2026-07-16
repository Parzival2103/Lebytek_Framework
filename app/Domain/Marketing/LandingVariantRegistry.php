<?php

declare(strict_types=1);

namespace App\Domain\Marketing;

final class LandingVariantRegistry
{
    /** @param array{catalog:list<string>,reveal_id_map:array<string,string>,variants:array<string,array<string,mixed>>} $config */
    public function __construct(private readonly array $config) {}

    // NO fromConfig() aquí — el container inyecta el array (Anti-deuda §A).

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->config['variants'];
    }

    /** @return array<string, mixed>|null */
    public function get(string $slug): ?array
    {
        return $this->config['variants'][$slug] ?? null;
    }

    /** @return list<string> */
    public function activeSlugs(): array
    {
        $out = [];
        foreach ($this->config['variants'] as $slug => $row) {
            if (($row['status'] ?? '') === 'active') {
                $out[] = (string) $slug;
            }
        }

        return $out;
    }

    public function revealId(string $sectionId): string
    {
        return (string) ($this->config['reveal_id_map'][$sectionId] ?? $sectionId);
    }

    /** @return list<string> */
    public function catalog(): array
    {
        return $this->config['catalog'];
    }

    /** weight_default del manifiesto para slugs nuevos no listados en seed map */
    public function weightDefault(string $slug): float
    {
        $row = $this->get($slug);

        return $row !== null ? (float) ($row['weight_default'] ?? 0.0) : 0.0;
    }
}
