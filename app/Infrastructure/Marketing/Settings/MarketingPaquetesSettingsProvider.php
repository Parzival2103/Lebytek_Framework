<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingPaquetesSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_paquetes'; }
    public function titulo(): string { return 'Paquetes comerciales'; }
    public function icono(): string { return 'bi-box-seam'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_paquetes_moneda', 'label' => 'Moneda', 'type' => 'text', 'default' => 'MXN'],
            ['name' => 'mkt_paquetes_ciclo', 'label' => 'Ciclo por defecto', 'type' => 'select',
             'options' => ['mensual' => 'Mensual', 'anual' => 'Anual'], 'default' => 'mensual'],
        ];
    }
}
