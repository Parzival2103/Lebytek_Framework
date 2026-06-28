<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoLeadRepository implements LeadRepositoryInterface
{
    public function guardar(LeadDraft $draft): int
    {
        $pdo = Connection::getInstance();
        $utm = $draft->utm();
        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_leads (nombre, email, telefono, mensaje, estado, utm_source, utm_medium, utm_campaign)
             VALUES (:nombre, :email, :telefono, :mensaje, :estado, :s, :m, :c)'
        );
        $stmt->execute([
            'nombre'   => $draft->nombre(),
            'email'    => $draft->email(),
            'telefono' => $draft->telefono(),
            'mensaje'  => $draft->mensaje(),
            'estado'   => 'pendiente',
            's'        => $utm['utm_source']   ?? null,
            'm'        => $utm['utm_medium']   ?? null,
            'c'        => $utm['utm_campaign'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
