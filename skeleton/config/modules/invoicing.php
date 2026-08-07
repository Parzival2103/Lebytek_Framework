<?php
declare(strict_types=1);

return [
    'clave'         => 'invoicing',
    'nombre'        => 'Facturación',
    'descripcion'   => 'Puerto de proveedores de facturación electrónica (Facturapi v1).',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/invoicing.sql',
    'cruds'         => [],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
