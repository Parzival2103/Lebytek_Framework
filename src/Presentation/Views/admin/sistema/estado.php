<?php /** @var array $estado */ ?>
<div class="container-fluid py-3">
  <h1 class="h4 mb-4"><i class="bi bi-hdd-stack me-2"></i>Estado del sistema</h1>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Versión de plataforma</div>
          <div class="h3 mb-0">v<?= htmlspecialchars((string) $estado['plataformaVersion']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Módulos</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle js-dt-responsive">
        <thead><tr><th data-priority="2">Clave</th><th>Declarada</th><th>Instalada</th><th data-priority="1">Estado</th></tr></thead>
        <tbody>
        <?php foreach ($estado['modulos'] as $clave => $m): ?>
          <tr>
            <td><code><?= htmlspecialchars((string) $clave) ?></code></td>
            <td><?= htmlspecialchars((string) $m['declarada']) ?></td>
            <td><?= $m['instalada'] === null ? '<span class="text-muted">no instalado</span>' : htmlspecialchars((string) $m['instalada']) ?></td>
            <td>
              <?php if ($m['actualizacionDisponible']): ?>
                <span class="badge bg-warning text-dark">Actualización disponible</span>
              <?php elseif (!$m['activo']): ?>
                <span class="badge bg-secondary">Inactivo</span>
              <?php else: ?>
                <span class="badge bg-success">Al día</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">Migraciones pendientes (<?= count($estado['migracionesPendientes']) ?>)</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($estado['migracionesPendientes'] as $p): ?>
            <li class="list-group-item small"><span class="text-muted">[<?= htmlspecialchars((string) $p['modulo']) ?>]</span> <?= htmlspecialchars((string) $p['archivo']) ?></li>
          <?php endforeach; ?>
          <?php if ($estado['migracionesPendientes'] === []): ?>
            <li class="list-group-item small text-muted">Ninguna.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">Checksums modificados (<?= count($estado['checksumsModificados']) ?>)</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($estado['checksumsModificados'] as $c): ?>
            <li class="list-group-item small text-danger"><span class="text-muted">[<?= htmlspecialchars((string) $c['modulo']) ?>]</span> <?= htmlspecialchars((string) $c['archivo']) ?></li>
          <?php endforeach; ?>
          <?php if ($estado['checksumsModificados'] === []): ?>
            <li class="list-group-item small text-muted">Ninguno.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">Health checks</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($estado['healthChecks'] as $h): ?>
        <li class="list-group-item small d-flex align-items-center">
          <?php if ($h['ok']): ?>
            <i class="bi bi-check-circle-fill text-success me-2"></i>
          <?php else: ?>
            <i class="bi bi-x-circle-fill text-danger me-2"></i>
          <?php endif; ?>
          <span class="fw-semibold me-2"><?= htmlspecialchars((string) $h['clave']) ?></span>
          <span class="text-muted"><?= htmlspecialchars((string) $h['detalle']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?= \Lebytek\Framework\Kernel\Helpers\ViewHelper::partial('datatables_responsive') ?>
</div>
