<?php
// app/Presentation/Views/publico/landing.php
declare(strict_types=1);

use App\Presentation\Marketing\LandingSectionRenderer;

// Anti-deuda §P: happy path recibe $sectionsHtml ya renderizado por el
// controller (inyecta LandingSectionRenderer). Fallback BC: si falta o viene
// vacío (p.ej. tests que llaman ViewHelper::render('publico/landing', ...)
// directo con solo $bloques/$paquetes), construir aquí con el mismo mapa
// único (Anti-deuda §G) — nunca duplicar la lista de partials.
$sectionsHtml = is_string($sectionsHtml ?? null) ? $sectionsHtml : '';
$sections     = is_array($sections ?? null) && $sections !== []
    ? $sections
    : ['hero', 'trust', 'features', 'pricing', 'testimonios', 'faq', 'lead_form'];

if ($sectionsHtml === '') {
    $sectionsHtml = (new LandingSectionRenderer())->render('v1', $sections, [
        'bloques' => $bloques ?? [],
        'paquetes' => $paquetes ?? [],
        'comprasHabilitadas' => $comprasHabilitadas ?? false,
        'landingVariant' => $landingVariant ?? '',
        'visitorId' => $visitorId ?? '',
    ]);
}

echo $sectionsHtml;
