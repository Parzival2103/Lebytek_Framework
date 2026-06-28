<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Pdf\Templates;

use Lebytek\Framework\Domain\Pdf\PdfDocument;
use Lebytek\Framework\Domain\Pdf\PdfHeader;
use Lebytek\Framework\Domain\Pdf\PdfPageSetup;
use Lebytek\Framework\Domain\Pdf\PdfSignatureBlock;
use Lebytek\Framework\Domain\Pdf\PdfSpacer;
use Lebytek\Framework\Domain\Pdf\PdfTemplateInterface;
use Lebytek\Framework\Domain\Pdf\PdfText;

/**
 * Documento de registro para un cliente: contrato con texto largo y firma.
 */
final class ContratoTemplate implements PdfTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $doc = PdfDocument::make(new PdfPageSetup('A4', 'portrait'));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $empresa = (string) ($marca['empresa'] ?? 'La Empresa');
        $nombre = (string) ($record['nombre'] ?? '');
        $email = (string) ($record['email'] ?? '');
        $telefono = (string) ($record['telefono'] ?? '');

        $doc->add(new PdfHeader('Contrato de servicio', $empresa));
        $doc->add(new PdfSpacer(10));

        $cuerpo = sprintf(
            'Por el presente documento, %s (en adelante "El Proveedor") y %s, con correo %s y teléfono %s '
            . '(en adelante "El Cliente"), acuerdan los términos y condiciones del servicio contratado. '
            . 'El Cliente declara que los datos proporcionados son correctos y autoriza su tratamiento conforme '
            . 'a la política de privacidad vigente. Este contrato entra en vigor en la fecha de su firma.',
            $empresa,
            $nombre !== '' ? $nombre : 'El Cliente',
            $email !== '' ? $email : 's/d',
            $telefono !== '' ? $telefono : 's/d'
        );

        $doc->add(new PdfText($cuerpo, 'normal'));
        $doc->add(new PdfSpacer(8));
        $doc->add(new PdfText('Estado actual del cliente: ' . (string) ($record['status'] ?? 's/d'), 'muted'));
        $doc->add(new PdfSpacer(24));
        $doc->add(new PdfSignatureBlock(['El Cliente', 'El Proveedor']));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'registro';
    }
}
