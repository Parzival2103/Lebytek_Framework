<?php

declare(strict_types=1);

// Manifiesto del módulo Integraciones y Conectores (Fase 1).
// Capa desacoplada para enviar mensajes y consumir APIs externas.
// Bootstrap (tabla int_logs + permisos) en schema/modules/integrations.sql.
return [
    'clave'         => 'integrations',
    'nombre'        => 'Integraciones y Conectores',
    'descripcion'   => 'Capa desacoplada para enviar mensajes, consumir APIs y (F2) recibir webhooks.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/integrations.sql',
    'cruds'         => [],
    'permisos'      => ['integrations.ver', 'integrations.enviar', 'integrations.configurar'],
    'menu'          => [],
    'providers'     => [],
];
