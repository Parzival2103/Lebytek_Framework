<?php

declare(strict_types=1);

// Manifiesto del módulo CRUD Engine (demo/showcase). Opcional: un deploy que
// no lo quiera queda sin estas tablas demo. Los recursos siguen cargándose
// desde config/cruds/*.json; aquí solo se referencian para inventario.
return [
    'clave'       => 'crud-engine',
    'nombre'      => 'CRUD Engine (demo)',
    'descripcion' => 'Motor CRUD genérico dirigido por JSON + showcase con relaciones, estados y validaciones.',
    'version'     => '1.0.0',
    'obligatorio' => false,
    'requiere'    => ['core'],
    'migraciones' => [
        '20260428132500_crud_engine_demo_resources.sql',
        '20260607120000_crud_engine_demo_showcase.sql',
    ],
    'seeds'     => [],
    'cruds'     => ['demo_clientes', 'demo_productos', 'demo_categorias', 'demo_pedidos'],
    'permisos'  => [],
    'menu'      => [],
    'providers' => [],
];
