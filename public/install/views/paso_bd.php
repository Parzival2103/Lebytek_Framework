<?php /** @var array $bd */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">2. Conexión a la base de datos</h2>
<div class="alert <?= $bd['ok'] ? 'alert-success' : 'alert-danger' ?>"><?= e($bd['detalle']) ?></div>
<?php if ($bd['ok']): ?>
  <a href="?paso=modulos" class="btn btn-primary">Continuar</a>
<?php else: ?>
  <p class="text-muted small">Edita <code>.env</code> con credenciales válidas y reintenta. El instalador no modifica <code>.env</code>.</p>
  <a href="?paso=bd" class="btn btn-outline-secondary">Reintentar</a>
<?php endif; ?>
