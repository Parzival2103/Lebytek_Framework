<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Domain\Marketing\Contracts\PurchaseTeamAlertNotifierInterface;
use App\Domain\Marketing\Ulid;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class CrearOrdenMembresiaUseCase
{
    private const PURCHASABLE_SLUGS = ['starter', 'business'];

    public function __construct(
        private readonly MarketingContentRepositoryInterface $content,
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly LeadRepositoryInterface $leads,
        private readonly PurchaseTeamAlertNotifierInterface $notifier,
    ) {}

    /**
     * @param array{nombre:string,email:string,telefono:string,empresa:string,direccion:string,rfc?:?string,ciclo:string} $input
     * @return array{order: array<string, mixed>, alert_sent: bool}
     */
    public function ejecutar(string $paqueteSlug, array $input): array
    {
        $nombre = trim($input['nombre'] ?? '');
        $email = trim($input['email'] ?? '');
        $telefono = trim($input['telefono'] ?? '');
        $empresa = trim($input['empresa'] ?? '');
        $direccion = trim($input['direccion'] ?? '');
        $rfc = trim((string) ($input['rfc'] ?? ''));
        $ciclo = trim($input['ciclo'] ?? '');

        if ($nombre === '' || $email === '' || $telefono === '' || $empresa === '' || $direccion === '') {
            throw new \InvalidArgumentException('Completa todos los campos obligatorios.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Correo electrónico inválido.');
        }
        if (! in_array($ciclo, ['monthly', 'annual'], true)) {
            throw new \InvalidArgumentException('Periodo de facturación inválido.');
        }

        $paquete = $this->content->findPaqueteBySlug($paqueteSlug);
        if ($paquete === null) {
            throw new \InvalidArgumentException('Paquete no disponible.');
        }
        if (! in_array($paqueteSlug, self::PURCHASABLE_SLUGS, true) || $paqueteSlug === 'empresa') {
            throw new \InvalidArgumentException('Este paquete no admite compra en línea.');
        }

        $priceColumn = $ciclo === 'annual' ? 'precio_anual' : 'precio_mensual';
        $precioRaw = $paquete[$priceColumn] ?? null;
        if (! $this->isPositiveNumericPrice($precioRaw)) {
            throw new \InvalidArgumentException('Precio no disponible para este paquete.');
        }

        $leadId = null;
        $tenantPublicId = null;
        $lead = $this->leads->findLatestByEmail($email);
        if ($lead !== null) {
            $leadId = (int) ($lead['id'] ?? 0) ?: null;
            $tenant = trim((string) ($lead['api_tenant_public_id'] ?? ''));
            $tenantPublicId = $tenant !== '' ? $tenant : null;
        }

        $publicId = Ulid::generate();
        $mensajesLimite = isset($paquete['mensajes_mes_limite']) && $paquete['mensajes_mes_limite'] !== null
            ? (int) $paquete['mensajes_mes_limite']
            : null;

        $orderId = $this->orders->create([
            'public_id' => $publicId,
            'paquete_id' => (int) $paquete['id'],
            'paquete_slug' => $paqueteSlug,
            'ciclo' => $ciclo,
            'precio_snapshot' => (float) $precioRaw,
            'mensajes_mes_limite_snapshot' => $mensajesLimite,
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'empresa' => $empresa,
            'direccion' => $direccion,
            'rfc' => $rfc !== '' ? $rfc : null,
            'lead_id' => $leadId,
            'api_tenant_public_id' => $tenantPublicId,
            'status' => 'pending_transfer',
        ]);

        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new \RuntimeException('No se pudo recuperar la orden creada.');
        }

        $alertSent = false;
        try {
            $alertSent = $this->notifier->notifyTransferPending($order);
            if ($alertSent) {
                $this->orders->markTransferNotified($orderId);
                $order = $this->orders->findById($orderId) ?? $order;
            } else {
                AppLogger::error('[CrearOrdenMembresia] WhatsApp alert not sent (disabled or no recipients)', [
                    'order_id' => $orderId,
                ]);
            }
        } catch (\Throwable $e) {
            AppLogger::error('[CrearOrdenMembresia] WhatsApp alert failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['order' => $order, 'alert_sent' => $alertSent];
    }

    private function isPositiveNumericPrice(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return is_numeric($value) && (float) $value > 0;
    }
}
