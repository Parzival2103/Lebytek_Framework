<?php
// app/Presentation/Views/publico/landing.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$bloques  = is_array($bloques ?? null) ? $bloques : [];
$paquetes = is_array($paquetes ?? null) ? $paquetes : [];

echo ViewHelper::render('publico/partials/_hero',        ['hero'        => $bloques['hero']        ?? []], '');
echo ViewHelper::render('publico/partials/_trust',       ['trust'       => $bloques['trust']       ?? []], '');
echo ViewHelper::render('publico/partials/_features',    ['features'    => $bloques['features']    ?? []], '');
echo ViewHelper::render('publico/partials/_pricing',     ['paquetes'    => $paquetes], '');
echo ViewHelper::render('publico/partials/_testimonios', ['testimonios' => $bloques['testimonios'] ?? []], '');
echo ViewHelper::render('publico/partials/_lead_form',   [], '');
