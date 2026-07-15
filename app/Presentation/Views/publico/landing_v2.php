<?php
// app/Presentation/Views/publico/landing_v2.php
declare(strict_types=1);

use App\Presentation\Marketing\LandingSectionRenderer;

// Anti-deuda §P: mismo patrón que landing.php (v1) — happy path usa
// $sectionsHtml del controller; fallback BC construye vía el mapa único
// (Anti-deuda §G) para no romper ViewHelper::render('publico/landing_v2', ...)
// directo (ver LandingV2ViewTest).
$sectionsHtml = is_string($sectionsHtml ?? null) ? $sectionsHtml : '';
$sections     = is_array($sections ?? null) && $sections !== []
    ? $sections
    : ['hero', 'trust', 'features', 'pricing', 'testimonios', 'faq', 'lead_form'];

if ($sectionsHtml === '') {
    $sectionsHtml = (new LandingSectionRenderer())->render('v2', $sections, [
        'bloques' => $bloques ?? [],
        'paquetes' => $paquetes ?? [],
        'comprasHabilitadas' => $comprasHabilitadas ?? false,
        'landingVariant' => $landingVariant ?? '',
        'visitorId' => $visitorId ?? '',
    ]);
}

echo $sectionsHtml;
