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
    'migraciones'   => [
        '20260630120000_mkt_leads_api_columns.sql',
        '20260630180000_mkt_landing_whatsapp_content.sql',
        '20260701160000_mkt_leads_api_instance_public_id.sql',
        '20260701170000_mkt_leads_api_lifecycle_status.sql',
        '20260706120000_mkt_leads_churn_columns.sql',
        '20260706120100_mkt_paquetes_limits.sql',
        '20260713120000_mkt_leads_email_verify.sql',
        '20260714200000_mkt_membership_orders.sql',
        '20260714210000_mkt_landing_copy_seo.sql',
        '20260715100000_mkt_ordenes_permission_slug.sql',
        '20260715120000_mkt_ordenes_stripe.sql',
        '20260715120000_mkt_landing_experiments.sql',
    ],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/marketing.sql',
    'cruds'         => ['mkt_leads', 'mkt_paquetes', 'mkt_bloques', 'mkt_plantillas', 'mkt_secuencias', 'mkt_ordenes'],
    'permisos'      => [
        'marketing.ver', 'marketing.crear', 'marketing.editar', 'marketing.eliminar',
        'marketing.gestionar', 'marketing.leads', 'marketing.publicar', 'marketing.ordenes',
        'marketing.experimentos',
    ],
    'menu'          => [],
    'providers'     => [],
];
