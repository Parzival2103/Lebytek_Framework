<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Integrations\Settings;

use Lebytek\Framework\Domain\Interfaces\SettingsSectionProviderInterface;

final class IntegrationsWhatsappSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string
    {
        return 'integrations_whatsapp';
    }

    public function titulo(): string
    {
        return 'Integraciones / WhatsApp';
    }

    public function icono(): string
    {
        return 'bi-whatsapp';
    }

    public function permiso(): string
    {
        return 'integrations.configurar';
    }

    public function campos(): array
    {
        return [];
    }

    public function vista(): ?string
    {
        return 'admin/integraciones/_ajustes_card';
    }
}
