# Módulo Kit de PDF (`pdf-kit`)

Capa opcional y desacoplada para emitir PDFs. No conoce el CRUD Engine ni Reportes:
cualquier módulo resuelve `PdfRenderingService` del contenedor y emite un documento.

## Modelo

- Un **documento** (`PdfDocument`) es una `PdfPageSetup` + una lista ordenada de
  **bloques** atómicos (`PdfHeader`, `PdfDataTable`, `PdfIndicatorCard`, `PdfTotalsBlock`,
  `PdfSignatureBlock`, `PdfText`, `PdfLogo`, `PdfFooter`, `PdfSpacer`, `PdfPageBreak`).
- Una **plantilla** (`PdfTemplateInterface`) compone un `PdfDocument` a partir de un
  payload de datos. La estructura/diseño es 100% del programador; el kit nunca recibe
  HTML de usuario.
- El **renderer** (`PdfComponentRenderer`) convierte bloques a HTML escapado vía
  partials en `app/Presentation/Views/partials/pdf/components/`.
- El **motor** (`DompdfRenderer`) está endurecido: `isRemoteEnabled=false`,
  `isPhpEnabled=false`, `chroot` al repo, fuentes locales.

## Uso desde otro módulo

```php
$pdf = $pdfRenderingService->renderTemplate('demo_reporte', [
    'titulo'  => 'Pedidos del mes',
    'columns' => [['name' => 'cliente', 'label' => 'Cliente'],
                  ['name' => 'total', 'label' => 'Total', 'format' => 'money']],
    'rows'    => [['cliente' => 'Ana', 'total' => 1200.5]],
]);
return $response->download($pdf, 'reporte.pdf');
```

## Registrar una plantilla

1. Implementa `PdfTemplateInterface` (`compose()` + `supports()`).
2. Añade `clave => Clase::class` en `config/pdf_templates.php` (whitelist; nunca un
   FQCN desde datos de usuario).

## Configuración

- `config/pdf.php` — papel, orientación, márgenes, fuente por defecto.
- `config/modules/pdf-kit.php` — manifiesto (`requiere: [core]`, sin tablas/rutas).
- Toggle: `modules.pdf_kit` en `config/vertical.php`.

## Pruebas

`php tests/run.php Pdf` — VOs, renderer (escape + formato), registry (whitelist),
motor dompdf (`%PDF`), servicio (esqueleto + plantilla demo end-to-end).

## Vista demo (admin)

Con el módulo activo (`modules.pdf_kit` en `config/vertical.php`) y el permiso
`pdf_kit.ver`, el menú **Kit de PDF → Demostración PDF** abre `/admin/pdf-kit/demo`:

- Tabla con el payload de `demo_reporte` (mismos datos que la plantilla).
- Acordeón con previsualización HTML de cada componente atómico.
- Descarga **PDF demo_reporte** (`renderTemplate`) y **PDF completo** (todos los bloques vía `renderDocument`).
