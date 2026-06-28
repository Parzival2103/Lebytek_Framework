<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Pdf\Templates;

use Lebytek\Framework\Domain\Pdf\PdfDataTable;
use Lebytek\Framework\Domain\Pdf\PdfDocument;
use Lebytek\Framework\Domain\Pdf\PdfFooter;
use Lebytek\Framework\Domain\Pdf\PdfHeader;
use Lebytek\Framework\Domain\Pdf\PdfLogo;
use Lebytek\Framework\Domain\Pdf\PdfPageSetup;
use Lebytek\Framework\Domain\Pdf\PdfSpacer;
use Lebytek\Framework\Domain\Pdf\PdfTemplateInterface;
use Lebytek\Framework\Domain\Pdf\PdfTotalsBlock;

/**
 * Documento de registro (modo registro) para un pedido: ticket de compra compacto.
 */
final class TicketCompraTemplate implements PdfTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $doc = PdfDocument::make(new PdfPageSetup('A4', 'portrait'));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $logo = (string) ($marca['logo'] ?? '');
        if ($logo !== '') {
            $doc->add(new PdfLogo($logo, 40));
        }

        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $relations = is_array($payload['relations'] ?? null) ? $payload['relations'] : [];
        $cliente = (string) ($relations['cliente'] ?? '');
        $folio = (string) ($record['folio'] ?? '');

        $doc->add(new PdfHeader('Ticket de compra · ' . $folio, trim((string) ($marca['empresa'] ?? '') . ' · ' . $cliente, ' ·')));
        $doc->add(new PdfSpacer(8));

        $items = is_array($relations['items'] ?? null) ? $relations['items'] : [];
        $doc->add(new PdfDataTable(
            [
                ['name' => 'descripcion', 'label' => 'Descripción'],
                ['name' => 'cantidad', 'label' => 'Cantidad'],
                ['name' => 'precio_unitario', 'label' => 'P. Unitario', 'format' => 'money'],
                ['name' => 'subtotal', 'label' => 'Subtotal', 'format' => 'money'],
            ],
            array_values($items)
        ));

        $doc->add(new PdfSpacer(8));
        $doc->add(new PdfTotalsBlock([
            ['label' => 'Total', 'value' => (string) ($record['total'] ?? '0'), 'format' => 'money'],
        ]));

        $doc->add(new PdfFooter('Generado por Lebytek · ' . date('Y-m-d H:i')));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'registro';
    }
}
