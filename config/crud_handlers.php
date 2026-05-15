<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CRUD Engine — registro de handlers (whitelist)
|--------------------------------------------------------------------------
| Las claves son strings simples referenciadas en config/cruds/*.json:
|
|   "hooks": { "handler": "clientes" }
|
| NUNCA uses FQCN directamente en JSON.
|--------------------------------------------------------------------------
*/

return [
    // Ejemplo (descomenta y crea la clase al implementar lógica real):
    // 'clientes' => \App\Application\Crud\Handlers\ClientesHandler::class,
];
