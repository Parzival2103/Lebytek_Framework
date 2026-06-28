<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

/**
 * Configuración de página para un documento PDF: tamaño de papel, orientación y
 * márgenes (en px, interpretados por dompdf vía CSS @page). VO inmutable.
 */
final class PdfPageSetup
{
    private const DEFAULT_MARGINS = ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36];

    private string $size;
    private string $orientation;
    /** @var array{top:int,right:int,bottom:int,left:int} */
    private array $margins;

    /** @param array<string,int>|null $margins */
    public function __construct(string $size = 'A4', string $orientation = 'portrait', ?array $margins = null)
    {
        $this->size = $size !== '' ? $size : 'A4';
        $this->orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';

        $m = $margins ?? self::DEFAULT_MARGINS;
        $this->margins = [
            'top'    => (int) ($m['top']    ?? self::DEFAULT_MARGINS['top']),
            'right'  => (int) ($m['right']  ?? self::DEFAULT_MARGINS['right']),
            'bottom' => (int) ($m['bottom'] ?? self::DEFAULT_MARGINS['bottom']),
            'left'   => (int) ($m['left']   ?? self::DEFAULT_MARGINS['left']),
        ];
    }

    /** @param array<string,mixed> $c */
    public static function fromArray(array $c): self
    {
        $margins = is_array($c['margins'] ?? null) ? $c['margins'] : null;
        return new self(
            (string) ($c['size'] ?? 'A4'),
            (string) ($c['orientation'] ?? 'portrait'),
            $margins,
        );
    }

    public function size(): string { return $this->size; }
    public function orientation(): string { return $this->orientation; }

    /** @return array{top:int,right:int,bottom:int,left:int} */
    public function margins(): array { return $this->margins; }

    /** Márgenes como shorthand CSS `top right bottom left` en px. */
    public function marginsCss(): string
    {
        return sprintf(
            '%dpx %dpx %dpx %dpx',
            $this->margins['top'],
            $this->margins['right'],
            $this->margins['bottom'],
            $this->margins['left'],
        );
    }
}
