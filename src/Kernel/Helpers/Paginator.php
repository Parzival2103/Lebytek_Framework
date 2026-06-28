<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Helpers;

/*
|--------------------------------------------------------------------------
| Paginator — Paginación simple para listas
|--------------------------------------------------------------------------
*/

final class Paginator
{
    private int $total;
    private int $perPage;
    private int $currentPage;
    private string $baseUrl;

    public function __construct(int $total, int $perPage, int $currentPage, string $baseUrl)
    {
        $this->total       = $total;
        $this->perPage     = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->baseUrl     = rtrim($baseUrl, '?&');
    }

    public function totalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->totalPages() > 1;
    }

    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNext(): bool
    {
        return $this->currentPage < $this->totalPages();
    }

    public function previousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function nextPage(): int
    {
        return min($this->totalPages(), $this->currentPage + 1);
    }

    public function pageUrl(int $page): string
    {
        $separator = str_contains($this->baseUrl, '?') ? '&' : '?';
        return $this->baseUrl . $separator . 'pagina=' . $page;
    }

    public function render(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $html  = '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';

        // Anterior
        if ($this->hasPrevious()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->pageUrl($this->previousPage()) . '">‹</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">‹</span></li>';
        }

        // Páginas
        $start = max(1, $this->currentPage - 2);
        $end   = min($this->totalPages(), $this->currentPage + 2);

        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->pageUrl(1) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $this->currentPage ? ' active' : '';
            $html  .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"{$this->pageUrl($i)}\">{$i}</a></li>";
        }

        if ($end < $this->totalPages()) {
            if ($end < $this->totalPages() - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $tp    = $this->totalPages();
            $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$this->pageUrl($tp)}\">{$tp}</a></li>";
        }

        // Siguiente
        if ($this->hasNext()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->pageUrl($this->nextPage()) . '">›</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">›</span></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    // Accesors para la vista
    public function getTotal(): int       { return $this->total;       }
    public function getPerPage(): int     { return $this->perPage;     }
    public function getCurrentPage(): int { return $this->currentPage; }
}
