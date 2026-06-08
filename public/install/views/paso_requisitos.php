<?php /** @var array $checks */ /** @var bool $puedeSeguir */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">1. Requisitos del sistema</h2>
<ul class="list-group mb-3">
  <?php foreach ($checks as $c): ?>
    <li class="list-group-item d-flex align-items-center">
      <i class="bi <?= $c['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> me-2"></i>
      <span class="fw-semibold me-2"><?= e($c['clave']) ?></span>
      <span class="text-muted small"><?= e($c['detalle']) ?></span>
    </li>
  <?php endforeach; ?>
</ul>
<?php if ($puedeSeguir): ?>
  <a href="?paso=bd" class="btn btn-primary">Continuar</a>
<?php else: ?>
  <div class="alert alert-warning">Corrige los requisitos en rojo antes de continuar.</div>
  <a href="?paso=requisitos" class="btn btn-outline-secondary">Reintentar</a>
<?php endif; ?>
