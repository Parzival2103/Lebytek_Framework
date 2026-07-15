<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\LandingVariantRegistry;

/**
 * Merges the runtime content blocks (`RenderLandingUseCase::ejecutar()`) with a
 * variant manifest's `copy_overrides`, enabled `sections` (manifesto order) and
 * `seo`. Pure Application service — no Presentation/Infrastructure concerns.
 */
final class MergeLandingVariantUseCase
{
    public function __construct(private readonly LandingVariantRegistry $registry)
    {
    }

    /**
     * @param array<string,array<string,mixed>> $bloques
     * @return array{
     *     slug: string,
     *     shell: string,
     *     sections: list<string>,
     *     bloques: array<string,array<string,mixed>>,
     *     seo: array{title: string, description: string}
     * }
     */
    public function merge(string $slug, array $bloques): array
    {
        $variant = $this->registry->get($slug) ?? [];
        $shell = (string) ($variant['shell'] ?? $slug);

        $overrides = is_array($variant['copy_overrides'] ?? null) ? $variant['copy_overrides'] : [];
        foreach ($overrides as $key => $patch) {
            $key = (string) $key;
            $existing = is_array($bloques[$key] ?? null) ? $bloques[$key] : [];
            $bloques[$key] = array_replace_recursive($existing, is_array($patch) ? $patch : []);
        }

        $sections = [];
        foreach (is_array($variant['sections'] ?? null) ? $variant['sections'] : [] as $section) {
            if (!empty($section['enabled'])) {
                $sections[] = (string) ($section['id'] ?? '');
            }
        }

        $seo = is_array($variant['seo'] ?? null) ? $variant['seo'] : [];

        return [
            'slug' => $slug,
            'shell' => $shell,
            'sections' => $sections,
            'bloques' => $bloques,
            'seo' => [
                'title' => (string) ($seo['title'] ?? ''),
                'description' => (string) ($seo['description'] ?? ''),
            ],
        ];
    }
}
