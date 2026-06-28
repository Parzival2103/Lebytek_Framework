<?php

declare(strict_types=1);

// Manifiesto del módulo CRUD Engine (demo/showcase). Opcional: un deploy que
// no lo quiera queda sin tablas demo. Bootstrap en schema/modules/crud-engine.sql.
return [
    'clave'         => 'crud-engine',
    'nombre'        => 'CRUD Engine (demo)',
    'descripcion'   => 'Motor CRUD genérico dirigido por JSON + showcase con relaciones, estados y validaciones.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [
        '20260609120000_crud_demo_permisos_modulo_por_recurso.sql',
    ],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/crud-engine.sql',
    'cruds'         => ['demo_clientes', 'demo_productos', 'demo_categorias', 'demo_pedidos'],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
