<?php
// app/Presentation/Views/publico/landing_v2.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$bloques  = is_array($bloques ?? null) ? $bloques : [];
$paquetes = is_array($paquetes ?? null) ? $paquetes : [];

echo ViewHelper::render('publico/partials/v2/_hero',        ['hero'        => $bloques['hero']        ?? []], '');
echo ViewHelper::render('publico/partials/v2/_trust',       ['trust'       => $bloques['trust']       ?? []], '');
echo ViewHelper::render('publico/partials/v2/_features',    ['features'    => $bloques['features']    ?? []], '');
echo ViewHelper::render('publico/partials/v2/_pricing',     ['paquetes' => $paquetes, 'comprasHabilitadas' => ! empty($comprasHabilitadas)], '');
echo ViewHelper::render('publico/partials/v2/_testimonios', ['testimonios' => $bloques['testimonios'] ?? []], '');
echo ViewHelper::render('publico/partials/v2/_faq',         ['faq'         => $bloques['faq']         ?? []], '');
echo ViewHelper::render('publico/partials/v2/_lead_form',   [], '');
