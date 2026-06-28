<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\DTO\Dashboard;

/**
 * Modelo único pasado a la vista admin/dashboard + parciales.
 *
 * sections: activityTitle, activityPlaceholder, statusTitle, badge, badgeTone, statusLines
 *
 * @param list<array{label:string,value:string,icon:string,color:string,url?:string,description?:string}> $kpis
 * @param list<array{icon:string,text:string,meta?:string}>                                               $activityItems
 * @param list<array{url:string,icon:string,label:string}>                                                $quickAccessItems
 * @param array{activityTitle:string,activityPlaceholder:string,statusTitle:string,badge:string,badgeTone:string,statusLines:list<array{text:string,tone?:string}>} $sections
 */
final class DashboardViewModel
{
    /**
     * @param list<array{label:string,value:string,icon:string,color:string,url?:string,description?:string}> $kpis
     * @param list<array{icon:string,text:string,meta?:string}>                                               $activityItems
     * @param list<array{url:string,icon:string,label:string}>                                                $quickAccessItems
     * @param array{activityTitle:string,activityPlaceholder:string,statusTitle:string,badge:string,badgeTone:string,statusLines:list<array{text:string,tone?:string}>} $sections
     */
    public function __construct(
        public readonly string $pageTitle,
        public readonly array $kpis,
        public readonly array $activityItems,
        public readonly array $quickAccessItems,
        public readonly array $sections,
        public readonly array $widgets = [],
    ) {}
}
