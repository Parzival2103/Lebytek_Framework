<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingTrackingSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_tracking'; }
    public function titulo(): string { return 'Marketing y tracking'; }
    public function icono(): string { return 'bi-graph-up'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_analytics_id', 'label' => 'ID de Analytics', 'type' => 'text'],
            ['name' => 'mkt_pixel_id', 'label' => 'ID de píxel', 'type' => 'text'],
            ['name' => 'mkt_captacion_activa', 'label' => 'Captación de leads activa', 'type' => 'toggle', 'default' => '1'],
        ];
    }
}
