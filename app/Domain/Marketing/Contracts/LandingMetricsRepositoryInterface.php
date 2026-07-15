<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface LandingMetricsRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function findSessionByPublicId(string $publicId): ?array;

    /**
     * Inserta la sesión si no existe (por `public_id`) o actualiza `last_seen_at` si ya existe.
     * Devuelve el `id` (nuevo o existente).
     *
     * @param array{public_id:string,visitor_id:string,variant_slug:string,is_preview:bool} $data
     */
    public function ensureSession(array $data): int;

    public function updateSessionMetrics(string $publicId, int $durationMs, int $maxScrollPct, ?string $exitSection): void;

    /**
     * @param array{
     *   session_id:?int,visitor_id:string,variant_slug:string,event_type:string,
     *   meta:?array<string, mixed>,is_preview:bool
     * } $data
     */
    public function insertEvent(array $data): void;

    /**
     * Inserta un evento `lead_submit` de confianza (Anti-deuda §N) — llamado
     * **solo** desde `LeadController` tras una captura de lead exitosa y
     * no-preview. `event_type` se hardcodea a `lead_submit` aquí; el
     * colector público (`CollectLandingMetricsUseCase`) sigue rechazando
     * `lead_submit` si llega en el body de `POST /marketing/collect`.
     * `is_preview` siempre `false` — llamadas preview nunca llegan aquí.
     */
    public function insertLeadSubmitEvent(string $visitorId, string $variantSlug): void;

    /**
     * Agregación para el score híbrido. **Siempre** excluye `is_preview = 1`.
     *
     * @return list<array{
     *   variant_slug:string,sessions:int,avg_scroll:float,avg_duration_ms:float,
     *   leads:int,top_exit_section:?string,sections_seen_avg:float
     * }>
     */
    public function aggregateForScore(int $windowDays): array;

    /**
     * Purga de retención: elimina sesiones/eventos anteriores a `$cutoff`.
     *
     * @return array{sessions:int,events:int} filas eliminadas
     */
    public function purgeOlderThan(\DateTimeImmutable $cutoff): array;
}
