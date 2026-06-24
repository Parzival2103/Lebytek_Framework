<?php

declare(strict_types=1);

use App\Application\Pdf\Templates\ContratoTemplate;
use App\Application\Pdf\Templates\DemoReporteTemplate;
use App\Application\Pdf\Templates\PresupuestoTemplate;
use App\Application\Pdf\Templates\TablaEstadisticaTemplate;
use App\Application\Pdf\Templates\TicketCompraTemplate;

// Whitelist de plantillas PDF: clave estable => clase PdfTemplateInterface.
// NUNCA se acepta un FQCN proveniente de datos de usuario; solo estas claves.
// Reportes y otros módulos añaden sus plantillas aquí.
return [
    'demo_reporte'      => DemoReporteTemplate::class,
    'tabla_estadistica' => TablaEstadisticaTemplate::class,
    'ticket_compra'     => TicketCompraTemplate::class,
    'presupuesto'       => PresupuestoTemplate::class,
    'contrato'          => ContratoTemplate::class,
];
