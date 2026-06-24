<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingContenidoSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_contenido'; }
    public function titulo(): string { return 'Contenido público'; }
    public function icono(): string { return 'bi-layout-text-window'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_pagina_inicio', 'label' => 'Página de inicio activa', 'type' => 'text', 'default' => 'home'],
            ['name' => 'mkt_slug_base', 'label' => 'Slug base público', 'type' => 'text', 'default' => ''],
            ['name' => 'mkt_mostrar_testimonios', 'label' => 'Mostrar testimonios', 'type' => 'toggle', 'default' => '1'],
        ];
    }

    public function vista(): ?string
    {
        return null;
    }
}
