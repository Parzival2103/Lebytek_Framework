<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Perfil del deploy (vertical / instancia)
|--------------------------------------------------------------------------
| Solo módulos presentes como entradas de menú (tabla core_menu_items / slug padre). Añadir claves nuevas junto con el dominio — ver docs/modules/modulo-menu.md
| correspondiente — ver docs/modules/uso-de-modulo-dominio.md
*/

return [
    'modules' => [
        'dashboard'      => true,
        'administracion' => true,
    ],

    'labels' => [
        'menu' => [],
    ],
];
