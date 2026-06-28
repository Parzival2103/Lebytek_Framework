<?php

declare(strict_types=1);

// Manifiesto del módulo Kit de PDF. Envoltorio endurecido de dompdf + biblioteca
// de componentes atómicos de documento. No conoce el CRUD Engine ni Reportes:
// cualquier módulo resuelve PdfRenderingService del contenedor para emitir un PDF.
// Sin tablas (no bootstrap_sql), sin rutas, sin providers de dashboard.
return [
    'clave'         => 'pdf-kit',
    'nombre'        => 'Kit de PDF',
    'descripcion'   => 'Envoltorio endurecido de dompdf + componentes atómicos de documento + servicio de render.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => ['20260614120000_pdf_kit_demo_menu.sql'],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/pdf-kit.sql',
    'cruds'         => [],
    'permisos'      => ['pdf_kit.ver'],
    'menu'          => [],
    'providers'     => [],
];
