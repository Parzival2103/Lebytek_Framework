<?php

declare(strict_types=1);

// Manifiesto del módulo Reportes. Capa opcional sobre el CRUD Engine + pdf-kit:
// el programador declara fuentes reportables (config/reportes/*.json) y el usuario
// final arma/guarda reportes (rep_reportes) que se regeneran como PDF.
// Bootstrap (tabla rep_reportes, permisos, menú, reporte demo) en
// database/schema/modules/reportes.sql.
return [
    'clave'         => 'reportes',
    'nombre'        => 'Reportes',
    'descripcion'   => 'Builder de reportes sobre recursos CRUD; genera PDFs con el pdf-kit.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core', 'crud-engine', 'pdf-kit'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/reportes.sql',
    'cruds'         => [],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [],
];
