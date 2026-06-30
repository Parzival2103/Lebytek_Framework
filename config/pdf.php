<?php

declare(strict_types=1);

// Defaults del Kit de PDF: papel, orientación, márgenes y fuente. La "marca" del
// documento (logo, empresa, colores) NO se fija aquí: cada módulo que emite un PDF
// la pasa en el payload (clave 'marca'), típicamente leída de cfg_configuraciones
// vía ConfiguracionService. Así el kit no depende de la base de datos.
return [
    'paper'       => 'A4',
    'orientation' => 'portrait',
    'margins'     => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
    'font'        => 'DejaVu Sans',
];
