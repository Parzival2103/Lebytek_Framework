<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Pdf\Templates;

use Lebytek\Framework\Domain\Pdf\PdfDataTable;
use Lebytek\Framework\Domain\Pdf\PdfDocument;
use Lebytek\Framework\Domain\Pdf\PdfHeader;
use Lebytek\Framework\Domain\Pdf\PdfPageSetup;
use Lebytek\Framework\Domain\Pdf\PdfSignatureBlock;
use Lebytek\Framework\Domain\Pdf\PdfSpacer;
use Lebytek\Framework\Domain\Pdf\PdfTemplateInterface;
use Lebytek\Framework\Domain\Pdf\PdfText;
use Lebytek\Framework\Domain\Pdf\PdfTotalsBlock;

/**
 * Documento de registro para un pedido: presupuesto con datos de cliente, conceptos y firma.
 */
final class PresupuestoTemplate implements PdfTemplateInterface
{
    private const IVA = 0.16;

    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $doc = PdfDocument::make(new PdfPageSetup('A4', 'portrait'));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $relations = is_array($payload['relations'] ?? null) ? $payload['relations'] : [];
        $cliente = (string) ($relations['cliente'] ?? 'Cliente');

        $doc->add(new PdfHeader('Presupuesto · ' . (string) ($record['folio'] ?? ''), (string) ($marca['empresa'] ?? '')));
        $doc->add(new PdfSpacer(6));
        $doc->add(new PdfText('Cliente: ' . $cliente, 'bold'));
        $doc->add(new PdfSpacer(6));

        $items = is_array($relations['items'] ?? null) ? $relations['items'] : [];
        $doc->add(new PdfDataTable(
            [
                ['name' => 'descripcion', 'label' => 'Concepto'],
                ['name' => 'cantidad', 'label' => 'Cantidad'],
                ['name' => 'precio_unitario', 'label' => 'P. Unitario', 'format' => 'money'],
                ['name' => 'subtotal', 'label' => 'Importe', 'format' => 'money'],
            ],
            array_values($items)
        ));

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['subtotal'] ?? 0);
        }
        $impuestos = round($subtotal * self::IVA, 2);
        $total = round($subtotal + $impuestos, 2);

        $doc->add(new PdfSpacer(8));
        $doc->add(new PdfTotalsBlock([
            ['label' => 'Subtotal', 'value' => number_format($subtotal, 2, '.', ''), 'format' => 'money'],
            ['label' => 'IVA (16%)', 'value' => number_format($impuestos, 2, '.', ''), 'format' => 'money'],
            ['label' => 'Total', 'value' => number_format($total, 2, '.', ''), 'format' => 'money'],
        ]));

        $doc->add(new PdfSpacer(16));
        $doc->add(new PdfSignatureBlock(['Cliente', 'Proveedor']));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'registro';
    }
}
