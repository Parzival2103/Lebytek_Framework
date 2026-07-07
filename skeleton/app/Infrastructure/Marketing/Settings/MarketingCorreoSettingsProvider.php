<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use Lebytek\Framework\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingCorreoSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_correo'; }
    public function titulo(): string { return 'Correo y automatizaciones'; }
    public function icono(): string { return 'bi-envelope'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_mail_host', 'label' => 'SMTP host (override)', 'type' => 'text', 'help' => 'Vacío ⇒ usa el SMTP global del sistema.'],
            ['name' => 'mkt_mail_from', 'label' => 'Remitente del módulo', 'type' => 'text'],
            ['name' => 'mkt_mail_secuencias', 'label' => 'Activar secuencias', 'type' => 'toggle', 'default' => '0'],
        ];
    }

    public function vista(): ?string
    {
        return null;
    }
}
