<?php declare(strict_types=1);
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var string $titulo */
/** @var int $windowDays */
/** @var int $minSessions */
/** @var list<array<string, mixed>> $aggregate */
/** @var array<string, float> $liveWeights */
/** @var list<array<string, mixed>> $pending */
?>
<div class="container py-4">
  <h1 class="h3 mb-1"><?= ViewHelper::e($titulo) ?></h1>
  <p class="text-muted mb-4">Ventana de score: últimos <?= (int) $windowDays ?> días. Muestra mínima por variante: <?= (int) $minSessions ?> sesiones.</p>

  <div class="alert alert-warning">
    <strong>Antes de aceptar:</strong> no aceptes con sample &lt; <?= (int) $minSessions ?> sesiones (<code>min_sessions</code>) o si sospechas tráfico anómalo (bots). Revisa el aggregate abajo antes de resolver una propuesta.
  </div>

  <div class="card mb-4">
    <div class="card-header">Aggregate en vivo por variante</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>Variante</th>
            <th>Peso vigente</th>
            <th>Sesiones</th>
            <th>Leads</th>
            <th>Scroll prom.</th>
            <th>Duración prom. (s)</th>
            <th>Secciones vistas</th>
            <th>Top salida</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($aggregate === []): ?>
            <tr><td colspan="8" class="text-muted">Sin datos en la ventana configurada.</td></tr>
          <?php endif; ?>
          <?php foreach ($aggregate as $row): ?>
            <?php $slug = (string) ($row['variant_slug'] ?? ''); ?>
            <tr>
              <td><code><?= ViewHelper::e($slug) ?></code></td>
              <td><?= ViewHelper::e(number_format((float) ($liveWeights[$slug] ?? 0.0), 4)) ?></td>
              <td>
                <?= (int) ($row['sessions'] ?? 0) ?>
                <?php if ((int) ($row['sessions'] ?? 0) < $minSessions): ?>
                  <span class="badge bg-warning text-dark ms-1" title="Bajo el mínimo de muestra">bajo muestra</span>
                <?php endif; ?>
              </td>
              <td><?= (int) ($row['leads'] ?? 0) ?></td>
              <td><?= ViewHelper::e(number_format((float) ($row['avg_scroll'] ?? 0.0), 1)) ?>%</td>
              <td><?= ViewHelper::e(number_format(((float) ($row['avg_duration_ms'] ?? 0.0)) / 1000, 1)) ?></td>
              <td><?= ViewHelper::e(number_format((float) ($row['sections_seen_avg'] ?? 0.0), 1)) ?></td>
              <td><?= ViewHelper::e((string) ($row['top_exit_section'] ?? '—')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Propuestas pendientes de reponderación</div>
    <div class="card-body">
      <?php if ($pending === []): ?>
        <p class="text-muted mb-0">No hay propuestas pendientes.</p>
      <?php endif; ?>

      <?php foreach ($pending as $p): ?>
        <div class="border rounded p-3 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <strong>Propuesta #<?= (int) $p['id'] ?></strong>
              <span class="text-muted">— <?= ViewHelper::e((string) $p['created_at']) ?></span>
              <?php if ((string) $p['reason'] !== ''): ?>
                <span class="badge bg-secondary ms-2"><?= ViewHelper::e((string) $p['reason']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($p['is_stale']): ?>
              <span class="badge bg-danger">Stale: los pesos vigentes cambiaron desde que se calculó esta propuesta</span>
            <?php endif; ?>
          </div>

          <table class="table table-sm">
            <thead>
              <tr>
                <th>Variante</th>
                <th>Peso vigente (snapshot)</th>
                <th>Peso vigente (ahora)</th>
                <th>Peso sugerido</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ((array) $p['suggested_weights'] as $slug => $suggestedWeight): ?>
                <?php $slug = (string) $slug; ?>
                <tr>
                  <td><code><?= ViewHelper::e($slug) ?></code></td>
                  <td><?= ViewHelper::e(number_format((float) ($p['current_weights'][$slug] ?? 0.0), 4)) ?></td>
                  <td><?= ViewHelper::e(number_format((float) ($liveWeights[$slug] ?? 0.0), 4)) ?></td>
                  <td><?= ViewHelper::e(number_format((float) $suggestedWeight, 4)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <?php if ($p['is_stale']): ?>
            <p class="text-danger small mb-2">No se puede aceptar: recalcula (espera al próximo cron de scoring) o rechaza esta propuesta.</p>
          <?php endif; ?>

          <form method="post" action="/admin/marketing/experimentos/accept" class="d-inline">
            <?= ViewHelper::csrfField() ?>
            <input type="hidden" name="proposal_id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="btn btn-primary btn-sm" <?= $p['is_stale'] ? 'disabled' : '' ?>>Aceptar</button>
          </form>
          <form method="post" action="/admin/marketing/experimentos/reject" class="d-inline ms-2">
            <?= ViewHelper::csrfField() ?>
            <input type="hidden" name="proposal_id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Rechazar</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
