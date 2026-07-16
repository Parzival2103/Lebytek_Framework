<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

final class MarketingMailRenderer
{
    /** @var array<string, string> clave => vista PHP relativa (migración) */
    private const FALLBACK_VIEWS = [
        'lead_welcome' => 'emails/lead_welcome',
        'lead_api_credentials' => 'emails/lead_api_credentials',
        'membership_activated' => 'emails/membership_activated',
    ];

    public function __construct(
        private readonly PlantillaRepositoryInterface $plantillas,
        private readonly MailerInterface $mailer,
    ) {}

    /** @param array<string, scalar|null> $vars */
    public function send(string $clave, string $toEmail, string $toName, array $vars): void
    {
        $row = $this->plantillas->findActiveByClave($clave);
        $safe = [];
        foreach ($vars as $k => $v) {
            $safe[$k] = htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($row === null) {
            if (! isset(self::FALLBACK_VIEWS[$clave])) {
                throw new \RuntimeException('Plantilla de correo no encontrada: '.$clave);
            }
            error_log('[MarketingMailRenderer] fallback PHP view for clave='.$clave);
            $html = ViewHelper::render(self::FALLBACK_VIEWS[$clave], $this->viewDataForFallback($clave, $vars), '');
            $asunto = $this->fallbackSubject($clave);
            $this->mailer->enviar(new MensajeCorreo($toEmail, $toName, $asunto, $html));

            return;
        }

        $asunto = $this->replaceVars((string) $row['asunto'], $safe);
        $cuerpo = $this->replaceVars((string) $row['cuerpo'], $safe);
        $this->mailer->enviar(new MensajeCorreo($toEmail, $toName, $asunto, $cuerpo));
    }

    /** @param array<string, string> $safe */
    private function replaceVars(string $template, array $safe): string
    {
        $out = $template;
        foreach ($safe as $key => $value) {
            $out = str_replace('{{'.$key.'}}', $value, $out);
        }

        return $out;
    }

    /** @param array<string, scalar|null> $vars */
    private function viewDataForFallback(string $clave, array $vars): array
    {
        if ($clave === 'membership_activated') {
            return [
                'nombre' => (string) ($vars['nombre'] ?? ''),
                'planNombre' => (string) ($vars['plan'] ?? $vars['planNombre'] ?? ''),
                'ciclo' => (string) ($vars['ciclo'] ?? ''),
                'cuota' => (string) ($vars['cuota'] ?? ''),
                'apiBaseUrl' => (string) ($vars['api_base_url'] ?? $vars['apiBaseUrl'] ?? ''),
                'token' => (string) ($vars['token'] ?? ''),
            ];
        }
        if ($clave === 'lead_api_credentials') {
            $docsUrl = (string) ($vars['docs_url'] ?? $vars['docsUrl'] ?? '');
            $dashboardUrl = (string) ($vars['dashboard_url'] ?? $vars['dashboardUrl'] ?? '');
            $packagesUrl = (string) ($vars['packages_url'] ?? $vars['packagesUrl'] ?? '');

            return [
                'nombre' => (string) ($vars['nombre'] ?? ''),
                'token' => (string) ($vars['token'] ?? ''),
                'apiBaseUrl' => (string) ($vars['api_base_url'] ?? $vars['apiBaseUrl'] ?? ''),
                'docsUrl' => $docsUrl,
                'showDocsCta' => $docsUrl !== '',
                'dashboardUrl' => $dashboardUrl,
                'showDashboardCta' => $dashboardUrl !== '',
                'packagesUrl' => $packagesUrl,
                'showPackagesCta' => $packagesUrl !== '',
            ];
        }

        return [
            'nombre' => (string) ($vars['nombre'] ?? ''),
            'landingUrl' => (string) ($vars['landing_url'] ?? $vars['landingUrl'] ?? ''),
            'empresaNombre' => $vars['empresa_nombre'] ?? $vars['empresaNombre'] ?? null,
            'codigo' => (string) ($vars['codigo'] ?? ''),
            'verifyUrl' => (string) ($vars['verify_url'] ?? $vars['verifyUrl'] ?? ''),
        ];
    }

    private function fallbackSubject(string $clave): string
    {
        return match ($clave) {
            'lead_welcome' => 'Recibimos tu solicitud — Lebytek',
            'lead_api_credentials' => 'Tus credenciales demo — Lebytek',
            'membership_activated' => 'Tu membresía Lebytek está activa',
            default => 'Lebytek',
        };
    }
}
