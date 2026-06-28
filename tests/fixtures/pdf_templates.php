<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Pdf\PdfDocument;
use Lebytek\Framework\Domain\Pdf\PdfHeader;
use Lebytek\Framework\Domain\Pdf\PdfTemplateInterface;

final class FixtureOkTemplate implements PdfTemplateInterface
{
    public function compose(array $payload): PdfDocument
    {
        return PdfDocument::make()->add(new PdfHeader((string) ($payload['title'] ?? 'Demo')));
    }

    public function supports(string $mode): bool
    {
        return $mode === 'coleccion';
    }
}

final class FixtureNotATemplate
{
}
