<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface MembershipOrderRepositoryInterface
{
    /**
     * @param array{
     *   public_id:string,paquete_id:int,paquete_slug:string,ciclo:string,
     *   precio_snapshot:float|string,mensajes_mes_limite_snapshot:?int,
     *   nombre:string,email:string,telefono:string,empresa:string,direccion:string,
     *   rfc:?string,lead_id:?int,api_tenant_public_id:?string,status:string
     * } $data
     */
    public function create(array $data): int;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByPublicId(string $publicId): ?array;

    public function markTransferNotified(int $orderId): void;

    public function setApiActivationError(int $orderId, string $error): void;

    public function markPaid(int $orderId, int $authorizedBy): void;

    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void;

    /** @param array{metodo_pago:string,payment_provider:?string,payment_ref:?string,status:string} $patch */
    public function markPaymentPending(int $orderId, array $patch): void;

    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void;

    /** @return array<string, mixed>|null */
    public function findByPaymentRef(string $provider, string $paymentRef): ?array;
}
