<?php

declare(strict_types=1);

// Manifiesto del módulo Marketing y Contenido Público.
// Cimientos desacoplables: CMS público + captación de leads + paquetes + settings.
// Bootstrap (tablas dom_mkt_*, permisos, menú, demo) en schema/modules/marketing.sql.
return [
    'clave'         => 'marketing',
    'nombre'        => 'Marketing y Contenido Público',
    'descripcion'   => 'CMS público, captación de leads, paquetes y automatizaciones de correo.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core', 'crud-engine'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/marketing.sql',
    'cruds'         => ['mkt_leads', 'mkt_paquetes', 'mkt_bloques', 'mkt_plantillas', 'mkt_secuencias'],
    'permisos'      => [
        'marketing.ver', 'marketing.crear', 'marketing.editar', 'marketing.eliminar',
        'marketing.gestionar', 'marketing.leads', 'marketing.publicar',
    ],
    'menu'          => [],
    'providers'     => [],
];
