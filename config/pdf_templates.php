<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Pdf\Templates\ContratoTemplate;
use Lebytek\Framework\Application\Pdf\Templates\DemoReporteTemplate;
use Lebytek\Framework\Application\Pdf\Templates\PresupuestoTemplate;
use Lebytek\Framework\Application\Pdf\Templates\TablaEstadisticaTemplate;
use Lebytek\Framework\Application\Pdf\Templates\TicketCompraTemplate;

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
