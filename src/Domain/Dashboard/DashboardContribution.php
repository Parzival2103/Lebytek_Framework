<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

/**
 * Fragmento aportado por un proveedor. Arrays planos según docs/modules/modulo-dashboard.md.
 */
final class DashboardContribution
{
    /**
     * @param list<array{label:string,value:string,icon:string,color:string,url?:string,description?:string}> $kpis
     * @param list<array{icon:string,text:string,meta?:string}>                                               $activityItems
     * @param list<array{url:string,icon:string,label:string}>                                                $quickAccess
     * @param array{badge:string,badgeTone?:string,lines?:array<int, array{text:string,tone?:string}>}|null $statusBlock Estado del sistema (cabecera con badge opcional + líneas)
     * @param list<array{partial:string,data:array<string,mixed>}> $widgets Widgets de vista (parciales whitelisteados)
     */
    public function __construct(
        public readonly array $kpis,
        public readonly array $activityItems,
        public readonly array $quickAccess,
        public readonly ?array $statusBlock = null,
        public readonly array $widgets = [],
    ) {}

    /** Contribución vacía (solo para proveedores opcionales). */
    public static function vacia(): self
    {
        return new self([], [], [], null, []);
    }
}
