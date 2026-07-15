<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoLandingMetricsRepository implements LandingMetricsRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function findSessionByPublicId(string $publicId): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_landing_sessions WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array{public_id:string,visitor_id:string,variant_slug:string,is_preview:bool} $data */
    public function ensureSession(array $data): int
    {
        $pdo = Connection::getInstance();
        // `id = LAST_INSERT_ID(id)` fuerza a que lastInsertId() devuelva el id
        // existente incluso cuando la fila ya existía (rama UPDATE del upsert).
        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_landing_sessions (public_id, visitor_id, variant_slug, is_preview)
             VALUES (:public_id, :visitor_id, :variant_slug, :is_preview)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), last_seen_at = NOW()'
        );
        $stmt->execute([
            'public_id'    => $data['public_id'],
            'visitor_id'   => $data['visitor_id'],
            'variant_slug' => $data['variant_slug'],
            'is_preview'   => $data['is_preview'] ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function updateSessionMetrics(string $publicId, int $durationMs, int $maxScrollPct, ?string $exitSection): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_landing_sessions
             SET duration_ms = :duration_ms,
                 max_scroll_pct = :max_scroll_pct,
                 exit_section = :exit_section,
                 last_seen_at = NOW()
             WHERE public_id = :public_id'
        );
        $stmt->execute([
            'duration_ms'    => $durationMs,
            'max_scroll_pct' => $maxScrollPct,
            'exit_section'   => $exitSection,
            'public_id'      => $publicId,
        ]);
    }

    /**
     * @param array{
     *   session_id:?int,visitor_id:string,variant_slug:string,event_type:string,
     *   meta:?array<string, mixed>,is_preview:bool
     * } $data
     */
    public function insertEvent(array $data): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_landing_events (session_id, visitor_id, variant_slug, event_type, meta, is_preview)
             VALUES (:session_id, :visitor_id, :variant_slug, :event_type, :meta, :is_preview)'
        );
        $stmt->execute([
            'session_id'   => $data['session_id'],
            'visitor_id'   => $data['visitor_id'],
            'variant_slug' => $data['variant_slug'],
            'event_type'   => $data['event_type'],
            'meta'         => $data['meta'] !== null ? json_encode($data['meta'], JSON_THROW_ON_ERROR) : null,
            'is_preview'   => $data['is_preview'] ? 1 : 0,
        ]);
    }

    /** @see LandingMetricsRepositoryInterface::insertLeadSubmitEvent() */
    public function insertLeadSubmitEvent(string $visitorId, string $variantSlug): void
    {
        $this->insertEvent([
            'session_id'   => null,
            'visitor_id'   => $visitorId,
            'variant_slug' => $variantSlug,
            'event_type'   => 'lead_submit',
            'meta'         => null,
            'is_preview'   => false,
        ]);
    }

    /**
     * Agregación para el score híbrido. **Siempre** excluye `is_preview = 1`
     * (Anti-deuda §… / override Task 3 regla 4).
     *
     * @return list<array{
     *   variant_slug:string,sessions:int,avg_scroll:float,avg_duration_ms:float,
     *   leads:int,top_exit_section:?string,sections_seen_avg:float
     * }>
     */
    public function aggregateForScore(int $windowDays): array
    {
        $pdo = Connection::getInstance();
        $since = (new \DateTimeImmutable())->modify("-{$windowDays} days")->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT variant_slug,
                    COUNT(*) AS sessions,
                    AVG(max_scroll_pct) AS avg_scroll,
                    AVG(duration_ms) AS avg_duration_ms
             FROM dom_mkt_landing_sessions
             WHERE is_preview = 0 AND first_seen_at >= :since
             GROUP BY variant_slug'
        );
        $stmt->execute(['since' => $since]);
        $sessionRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach (is_array($sessionRows) ? $sessionRows : [] as $row) {
            $slug = (string) $row['variant_slug'];
            $out[$slug] = [
                'variant_slug'      => $slug,
                'sessions'          => (int) $row['sessions'],
                'avg_scroll'        => (float) $row['avg_scroll'],
                'avg_duration_ms'   => (float) $row['avg_duration_ms'],
                'leads'             => 0,
                'top_exit_section'  => null,
                'sections_seen_avg' => 0.0,
            ];
        }

        if ($out === []) {
            return [];
        }

        $leadStmt = $pdo->prepare(
            'SELECT landing_variant, COUNT(*) AS leads
             FROM dom_mkt_leads
             WHERE deleted = 0 AND landing_variant IS NOT NULL AND created_at >= :since
             GROUP BY landing_variant'
        );
        $leadStmt->execute(['since' => $since]);
        foreach ($leadStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $slug = (string) $row['landing_variant'];
            if (isset($out[$slug])) {
                $out[$slug]['leads'] = (int) $row['leads'];
            }
        }

        $exitStmt = $pdo->prepare(
            'SELECT variant_slug, exit_section, COUNT(*) AS n
             FROM dom_mkt_landing_sessions
             WHERE is_preview = 0 AND first_seen_at >= :since AND exit_section IS NOT NULL
             GROUP BY variant_slug, exit_section'
        );
        $exitStmt->execute(['since' => $since]);
        $exitTop = [];
        foreach ($exitStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $slug = (string) $row['variant_slug'];
            $n = (int) $row['n'];
            if (!isset($exitTop[$slug]) || $n > $exitTop[$slug]['n']) {
                $exitTop[$slug] = ['n' => $n, 'section' => (string) $row['exit_section']];
            }
        }
        foreach ($exitTop as $slug => $top) {
            if (isset($out[$slug])) {
                $out[$slug]['top_exit_section'] = $top['section'];
            }
        }

        $sectionsStmt = $pdo->prepare(
            "SELECT s.variant_slug AS variant_slug, e.session_id AS session_id,
                    COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(e.meta, '$.section'))) AS sections_seen
             FROM dom_mkt_landing_events e
             INNER JOIN dom_mkt_landing_sessions s ON s.id = e.session_id
             WHERE e.event_type = 'section_view' AND e.is_preview = 0 AND e.created_at >= :since
             GROUP BY s.variant_slug, e.session_id"
        );
        $sectionsStmt->execute(['since' => $since]);
        $sectionsBySlug = [];
        foreach ($sectionsStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $slug = (string) $row['variant_slug'];
            $sectionsBySlug[$slug][] = (int) $row['sections_seen'];
        }
        foreach ($sectionsBySlug as $slug => $counts) {
            if (isset($out[$slug]) && $counts !== []) {
                $out[$slug]['sections_seen_avg'] = array_sum($counts) / count($counts);
            }
        }

        return array_values($out);
    }

    /** @return array{sessions:int,events:int} */
    public function purgeOlderThan(\DateTimeImmutable $cutoff): array
    {
        $pdo = Connection::getInstance();
        $cutoffStr = $cutoff->format('Y-m-d H:i:s');

        $eventsStmt = $pdo->prepare('DELETE FROM dom_mkt_landing_events WHERE created_at < :cutoff');
        $eventsStmt->execute(['cutoff' => $cutoffStr]);
        $events = $eventsStmt->rowCount();

        $sessionsStmt = $pdo->prepare('DELETE FROM dom_mkt_landing_sessions WHERE last_seen_at < :cutoff');
        $sessionsStmt->execute(['cutoff' => $cutoffStr]);
        $sessions = $sessionsStmt->rowCount();

        return ['sessions' => $sessions, 'events' => $events];
    }
}
