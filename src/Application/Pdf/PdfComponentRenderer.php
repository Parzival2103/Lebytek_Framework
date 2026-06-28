<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Pdf\PdfBlock;
use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfIndicatorCard;
use App\Domain\Pdf\PdfLogo;
use App\Domain\Pdf\PdfPageBreak;
use App\Domain\Pdf\PdfSignatureBlock;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfText;
use App\Domain\Pdf\PdfTotalsBlock;

/**
 * Convierte bloques de documento (VOs puros) en HTML pensado para dompdf. Toda
 * presentación vive en partials bajo Views/partials/pdf/components; aquí solo se
 * preparan los datos (incluido el formateo de valores) y se delega el escape de
 * texto al partial. Sin recursos remotos, sin HTML de usuario.
 */
final class PdfComponentRenderer
{
    private const COMPONENTS_DIR = ROOT_PATH . '/app/Presentation/Views/partials/pdf/components/';

    /** @param list<PdfBlock> $blocks */
    public function renderBlocks(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }
        return $html;
    }

    public function renderBlock(PdfBlock $block): string
    {
        return match (true) {
            $block instanceof PdfHeader        => $this->partial('header', ['title' => $block->title(), 'subtitle' => $block->subtitle()]),
            $block instanceof PdfLogo          => $this->partial('logo', ['src' => $block->src(), 'height' => $block->height()]),
            $block instanceof PdfText          => $this->partial('text', ['text' => $block->text(), 'style' => $block->style()]),
            $block instanceof PdfDataTable     => $this->renderTable($block),
            $block instanceof PdfIndicatorCard => $this->partial('indicator', ['label' => $block->label(), 'value' => $this->formatValue($block->value(), $block->format())]),
            $block instanceof PdfTotalsBlock   => $this->renderTotals($block),
            $block instanceof PdfSignatureBlock => $this->partial('signature', ['labels' => $block->labels()]),
            $block instanceof PdfFooter        => $this->partial('footer', ['text' => $block->text()]),
            $block instanceof PdfSpacer        => $this->partial('spacer', ['height' => $block->height()]),
            $block instanceof PdfPageBreak     => $this->partial('pagebreak', []),
            default                            => '',
        };
    }

    private function renderTable(PdfDataTable $table): string
    {
        $headers = array_map(static fn(array $c): string => (string) ($c['label'] ?? ''), $table->columns());

        $matrix = [];
        foreach ($table->rows() as $row) {
            $cells = [];
            foreach ($table->columns() as $col) {
                $name = (string) ($col['name'] ?? '');
                $format = (string) ($col['format'] ?? 'raw');
                $cells[] = $this->formatValue($row[$name] ?? '', $format);
            }
            $matrix[] = $cells;
        }

        return $this->partial('data_table', ['headers' => $headers, 'matrix' => $matrix]);
    }

    private function renderTotals(PdfTotalsBlock $totals): string
    {
        $rows = [];
        foreach ($totals->totals() as $t) {
            $rows[] = [
                'label' => (string) ($t['label'] ?? ''),
                'value' => $this->formatValue($t['value'] ?? '', (string) ($t['format'] ?? 'raw')),
            ];
        }
        return $this->partial('totals', ['rows' => $rows]);
    }

    /** Formatea un valor escalar según el formato declarado. Devuelve siempre string. */
    public function formatValue(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($format) {
            'money'    => '$' . number_format((float) $value, 2),
            'number'   => number_format((float) $value),
            'date'     => $this->formatDate((string) $value, 'Y-m-d'),
            'datetime' => $this->formatDate((string) $value, 'Y-m-d H:i'),
            default    => (string) $value,
        };
    }

    private function formatDate(string $value, string $fmt): string
    {
        $ts = strtotime($value);
        return $ts === false ? $value : date($fmt, $ts);
    }

    /** @param array<string,mixed> $vars */
    private function partial(string $name, array $vars): string
    {
        $file = self::COMPONENTS_DIR . $name . '.php';
        if (!is_readable($file)) {
            return '';
        }
        extract($vars, EXTR_OVERWRITE);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
