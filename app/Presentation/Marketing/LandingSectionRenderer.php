<?php

declare(strict_types=1);

namespace App\Presentation\Marketing;

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/**
 * Single map from section catalog id → v1/v2 partial (Anti-deuda §G). Both
 * `landing.php` and `landing_v2.php` must render through this class instead
 * of duplicating the `echo ViewHelper::render(...)` list per shell.
 */
final class LandingSectionRenderer
{
    /**
     * @param list<string> $sections
     * @param array<string,mixed> $ctx keys: bloques, paquetes, comprasHabilitadas, landingVariant, visitorId
     */
    public function render(string $shell, array $sections, array $ctx): string
    {
        $prefix = $shell === 'v2' ? 'publico/partials/v2/' : 'publico/partials/';
        $bloques = is_array($ctx['bloques'] ?? null) ? $ctx['bloques'] : [];

        $map = [
            'hero' => ['_hero', ['hero' => $bloques['hero'] ?? []]],
            'trust' => ['_trust', ['trust' => $bloques['trust'] ?? []]],
            'features' => ['_features', ['features' => $bloques['features'] ?? []]],
            'pricing' => ['_pricing', [
                'paquetes' => $ctx['paquetes'] ?? [],
                'comprasHabilitadas' => !empty($ctx['comprasHabilitadas']),
            ]],
            'testimonios' => ['_testimonios', ['testimonios' => $bloques['testimonios'] ?? []]],
            'faq' => ['_faq', ['faq' => $bloques['faq'] ?? []]],
            'lead_form' => ['_lead_form', [
                'landingVariant' => (string) ($ctx['landingVariant'] ?? ''),
                'visitorId' => (string) ($ctx['visitorId'] ?? ''),
            ]],
        ];

        $html = '';
        foreach ($sections as $id) {
            if (!isset($map[$id])) {
                continue;
            }
            [$file, $data] = $map[$id];
            $html .= ViewHelper::render($prefix . $file, $data, '');
        }

        return $html;
    }
}
