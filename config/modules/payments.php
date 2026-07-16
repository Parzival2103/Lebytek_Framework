<?php
declare(strict_types=1);

return [
    'clave'         => 'payments',
    'nombre'        => 'Pagos',
    'descripcion'   => 'Puerto de pasarelas de pago (Stripe v1).',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/payments.sql',
    'cruds'         => [],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
