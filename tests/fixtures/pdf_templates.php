<?php
declare(strict_types=1);

use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfTemplateInterface;

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
