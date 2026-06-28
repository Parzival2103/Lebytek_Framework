<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfIndicatorCard;
use App\Domain\Pdf\PdfLogo;
use App\Domain\Pdf\PdfPageBreak;
use App\Domain\Pdf\PdfPageSetup;
use App\Domain\Pdf\PdfSignatureBlock;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfText;
use App\Domain\Pdf\PdfTotalsBlock;

/**
 * Datos de demostración del Kit de PDF: payload de demo_reporte y documento
 * compuesto que ejercita los diez bloques atómicos.
 */
final class PdfKitDemoData
{
    /** @return array<string,mixed> */
    public static function demoReportePayload(): array
    {
        return [
            'titulo'  => 'Pedidos del mes',
            'columns' => [
                ['name' => 'cliente', 'label' => 'Cliente'],
                ['name' => 'servicio', 'label' => 'Servicio'],
                ['name' => 'total', 'label' => 'Total', 'format' => 'money'],
                ['name' => 'fecha', 'label' => 'Fecha', 'format' => 'date'],
            ],
            'rows' => [
                ['cliente' => 'Ana García', 'servicio' => 'Consultoría', 'total' => 1200.5, 'fecha' => '2026-06-01'],
                ['cliente' => 'Beto López', 'servicio' => 'Soporte', 'total' => 980, 'fecha' => '2026-06-08'],
                ['cliente' => 'Carla Ruiz', 'servicio' => 'Implementación', 'total' => 3450.75, 'fecha' => '2026-06-14'],
            ],
        ];
    }

    public static function logoPath(): string
    {
        return ROOT_PATH . '/public/assets/images/logo.png';
    }

    public static function buildDocumentoCompleto(): PdfDocument
    {
        $payload = self::demoReportePayload();

        return PdfDocument::make(new PdfPageSetup('A4', 'portrait'))
            ->add(new PdfHeader(
                'Kit de PDF — Demostración completa',
                'Ejercicio de los diez componentes atómicos del módulo',
            ))
            ->add(new PdfLogo(self::logoPath(), 48))
            ->add(new PdfText('Documento generado desde las vistas demo del admin.', 'normal'))
            ->add(new PdfText('Texto secundario con estilo muted y valores formateados en tablas.', 'muted'))
            ->add(new PdfSpacer(12))
            ->add(new PdfIndicatorCard('Pedidos', '3', 'number'))
            ->add(new PdfIndicatorCard('Facturación', '5631.25', 'money'))
            ->add(new PdfSpacer(8))
            ->add(new PdfDataTable(
                is_array($payload['columns'] ?? null) ? $payload['columns'] : [],
                is_array($payload['rows'] ?? null) ? $payload['rows'] : [],
            ))
            ->add(new PdfTotalsBlock([
                ['label' => 'Subtotal', 'value' => 5631.25, 'format' => 'money'],
                ['label' => 'IVA (16%)', 'value' => 901, 'format' => 'money'],
                ['label' => 'Total', 'value' => 6532.25, 'format' => 'money'],
            ]))
            ->add(new PdfText('Resumen validado para pruebas manuales del renderer.', 'bold'))
            ->add(new PdfPageBreak())
            ->add(new PdfText('Segunda página tras un salto (PdfPageBreak).', 'normal'))
            ->add(new PdfSignatureBlock(['Responsable del área', 'Cliente / receptor']))
            ->add(new PdfFooter('Generado por el Kit de PDF — vista demo'));
    }

    /**
     * Bloques individuales para previsualización en el navegador.
     *
     * @return list<array{slug:string,type:string,label:string,descripcion:string,block:\App\Domain\Pdf\PdfBlock}>
     */
    public static function bloquesParaPrevisualizar(): array
    {
        $payload = self::demoReportePayload();

        return [
            [
                'slug' => 'header',
                'type' => 'header',
                'label' => 'Cabecera',
                'descripcion' => 'Título y subtítulo del documento (PdfHeader).',
                'block' => new PdfHeader('Pedidos del mes', 'Plantilla demo_reporte'),
            ],
            [
                'slug' => 'logo',
                'type' => 'logo',
                'label' => 'Logo',
                'descripcion' => 'Imagen local bajo chroot; sin URLs remotas (PdfLogo).',
                'block' => new PdfLogo(self::logoPath(), 40),
            ],
            [
                'slug' => 'text',
                'type' => 'text',
                'label' => 'Texto',
                'descripcion' => 'Párrafos con estilos normal, muted y bold (PdfText).',
                'block' => new PdfText('Línea de texto con formato money: $1,200.50', 'muted'),
            ],
            [
                'slug' => 'table',
                'type' => 'table',
                'label' => 'Tabla de datos',
                'descripcion' => 'Columnas con format money/date y filas del payload demo (PdfDataTable).',
                'block' => new PdfDataTable(
                    is_array($payload['columns'] ?? null) ? $payload['columns'] : [],
                    is_array($payload['rows'] ?? null) ? $payload['rows'] : [],
                ),
            ],
            [
                'slug' => 'indicator',
                'type' => 'indicator',
                'label' => 'Indicador KPI',
                'descripcion' => 'Tarjeta con etiqueta, valor y formato (PdfIndicatorCard).',
                'block' => new PdfIndicatorCard('Total facturado', '5631.25', 'money'),
            ],
            [
                'slug' => 'totals',
                'type' => 'totals',
                'label' => 'Totales',
                'descripcion' => 'Bloque de líneas label/valor (PdfTotalsBlock).',
                'block' => new PdfTotalsBlock([
                    ['label' => 'Subtotal', 'value' => 5631.25, 'format' => 'money'],
                    ['label' => 'Total', 'value' => 6532.25, 'format' => 'money'],
                ]),
            ],
            [
                'slug' => 'signature',
                'type' => 'signature',
                'label' => 'Firmas',
                'descripcion' => 'Líneas de firma con etiquetas (PdfSignatureBlock).',
                'block' => new PdfSignatureBlock(['Firma autorizada', 'Conformidad cliente']),
            ],
            [
                'slug' => 'footer',
                'type' => 'footer',
                'label' => 'Pie',
                'descripcion' => 'Texto de pie de documento (PdfFooter).',
                'block' => new PdfFooter('Generado por el Kit de PDF'),
            ],
            [
                'slug' => 'spacer',
                'type' => 'spacer',
                'label' => 'Espaciador',
                'descripcion' => 'Separación vertical en px (PdfSpacer).',
                'block' => new PdfSpacer(24),
            ],
            [
                'slug' => 'pagebreak',
                'type' => 'pagebreak',
                'label' => 'Salto de página',
                'descripcion' => 'Fuerza page-break-after en el PDF (PdfPageBreak).',
                'block' => new PdfPageBreak(),
            ],
        ];
    }
}
