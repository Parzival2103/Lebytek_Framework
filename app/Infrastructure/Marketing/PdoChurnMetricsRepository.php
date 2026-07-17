<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoChurnMetricsRepository implements ChurnMetricsRepositoryInterface
{
    public function countActiveDemos(): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM dom_mkt_leads
             WHERE deleted = 0
               AND estado = 'demo_enviada'
               AND api_tenant_public_id IS NOT NULL
               AND converted_at IS NULL"
        );

        return (int) $stmt->fetchColumn();
    }

    public function countDemosExpiringWithinDays(int $days): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM dom_mkt_leads
             WHERE deleted = 0
               AND estado = 'demo_enviada'
               AND demo_expires_at IS NOT NULL
               AND demo_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
               AND converted_at IS NULL"
        );
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countOpenRiskSignals(): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM rep_risk_signals WHERE resolved_at IS NULL'
        );

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function getLatestChurnSnapshot(): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->query(
            'SELECT * FROM rep_churn_monthly ORDER BY period_year DESC, period_month DESC LIMIT 1'
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function findOpenRiskSignals(int $limit = 5): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT rs.*, l.nombre AS lead_nombre, l.email AS lead_email
             FROM rep_risk_signals rs
             LEFT JOIN dom_mkt_leads l ON l.id = rs.lead_id
             WHERE rs.resolved_at IS NULL
             ORDER BY rs.detected_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function upsertRiskSignal(
        ?int $leadId,
        ?string $tenantPublicId,
        string $signalType,
        string $severity = 'medium',
        ?array $payload = null,
    ): void {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT id FROM rep_risk_signals
             WHERE resolved_at IS NULL
               AND signal_type = :type
               AND ((lead_id IS NOT NULL AND lead_id = :lead_id) OR (tenant_public_id IS NOT NULL AND tenant_public_id = :tenant_id))
               AND DATE(detected_at) = CURDATE()
             LIMIT 1'
        );
        $stmt->execute([
            'type'      => $signalType,
            'lead_id'   => $leadId,
            'tenant_id' => $tenantPublicId,
        ]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO rep_risk_signals (lead_id, tenant_public_id, signal_type, severity, payload_json)
             VALUES (:lead_id, :tenant_id, :type, :severity, :payload)'
        );
        $insert->execute([
            'lead_id'   => $leadId,
            'tenant_id' => $tenantPublicId,
            'type'      => $signalType,
            'severity'  => $severity,
            'payload'   => $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function resolveOpenRiskSignal(?int $leadId, ?string $tenantPublicId, string $signalType): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE rep_risk_signals
             SET resolved_at = NOW()
             WHERE resolved_at IS NULL
               AND signal_type = :type
               AND (
                 (:lead_id IS NOT NULL AND lead_id = :lead_id)
                 OR (:tenant_id IS NOT NULL AND tenant_public_id = :tenant_id)
               )'
        );
        $stmt->execute([
            'type' => $signalType,
            'lead_id' => $leadId,
            'tenant_id' => $tenantPublicId,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function saveChurnSnapshot(array $data): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO rep_churn_monthly (
                period_year, period_month, clients_start, clients_lost, churn_rate_pct,
                demos_started, demos_converted, demo_conversion_pct,
                active_by_usage, at_risk_count, net_new_clients
             ) VALUES (
                :year, :month, :start, :lost, :churn,
                :demos_started, :demos_converted, :demo_conv,
                :active_usage, :at_risk, :net_new
             )
             ON DUPLICATE KEY UPDATE
                clients_start = VALUES(clients_start),
                clients_lost = VALUES(clients_lost),
                churn_rate_pct = VALUES(churn_rate_pct),
                demos_started = VALUES(demos_started),
                demos_converted = VALUES(demos_converted),
                demo_conversion_pct = VALUES(demo_conversion_pct),
                active_by_usage = VALUES(active_by_usage),
                at_risk_count = VALUES(at_risk_count),
                net_new_clients = VALUES(net_new_clients),
                calculated_at = NOW()'
        );
        $stmt->execute([
            'year'          => $data['period_year'],
            'month'         => $data['period_month'],
            'start'         => $data['clients_start'],
            'lost'          => $data['clients_lost'],
            'churn'         => $data['churn_rate_pct'],
            'demos_started' => $data['demos_started'],
            'demos_converted' => $data['demos_converted'],
            'demo_conv'     => $data['demo_conversion_pct'],
            'active_usage'  => $data['active_by_usage'],
            'at_risk'       => $data['at_risk_count'],
            'net_new'       => $data['net_new_clients'],
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function findRecentlyProvisioned(int $hours = 24): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "SELECT id, nombre, email, api_provisioned_at, plan_slug
             FROM dom_mkt_leads
             WHERE deleted = 0
               AND api_provisioned_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
             ORDER BY api_provisioned_at DESC
             LIMIT 10"
        );
        $stmt->bindValue('hours', $hours, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function countDemosStarted(int $year, int $month): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dom_mkt_leads
             WHERE deleted = 0
               AND COALESCE(demo_started_at, api_provisioned_at) IS NOT NULL
               AND YEAR(COALESCE(demo_started_at, api_provisioned_at)) = :year
               AND MONTH(COALESCE(demo_started_at, api_provisioned_at)) = :month'
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (int) $stmt->fetchColumn();
    }

    public function countDemosConverted(int $year, int $month): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dom_mkt_leads
             WHERE deleted = 0
               AND converted_at IS NOT NULL
               AND YEAR(converted_at) = :year
               AND MONTH(converted_at) = :month'
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (int) $stmt->fetchColumn();
    }

    public function countClientsStart(int $year, int $month): int
    {
        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dom_mkt_leads
             WHERE deleted = 0
               AND converted_at IS NOT NULL
               AND converted_at < :period_start
               AND (cancelled_at IS NULL OR cancelled_at >= :period_start)'
        );
        $stmt->execute(['period_start' => $periodStart]);

        return (int) $stmt->fetchColumn();
    }

    public function countClientsLost(int $year, int $month): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dom_mkt_leads
             WHERE deleted = 0
               AND converted_at IS NOT NULL
               AND cancelled_at IS NOT NULL
               AND YEAR(cancelled_at) = :year
               AND MONTH(cancelled_at) = :month'
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (int) $stmt->fetchColumn();
    }

    public function countActiveByUsage(int $year, int $month): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dom_mkt_leads
             WHERE deleted = 0
               AND converted_at IS NOT NULL
               AND (cancelled_at IS NULL OR YEAR(cancelled_at) > :year OR (YEAR(cancelled_at) = :year AND MONTH(cancelled_at) > :month))
               AND (
                 (last_activity_at IS NOT NULL AND YEAR(last_activity_at) = :year AND MONTH(last_activity_at) = :month)
                 OR (first_message_sent_at IS NOT NULL AND YEAR(first_message_sent_at) = :year AND MONTH(first_message_sent_at) = :month)
               )'
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (int) $stmt->fetchColumn();
    }
}
