<?php /** @var \Lebytek\Framework\Application\Install\InstallPlan $plan */ /** @var array $seleccion */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">5. Revisión</h2>
<p>Modo: <strong><?= $plan->nueva ? 'Instalación nueva' : 'Actualización' ?></strong></p>

<h3 class="h6 mt-3">Módulos a registrar</h3>
<p><?= e(implode(', ', $seleccion)) ?></p>

<h3 class="h6 mt-3">Migraciones pendientes (<?= count($plan->migracionesPendientes) ?>)</h3>
<ul class="small">
  <?php foreach ($plan->migracionesPendientes as $m): ?>
    <li>[<?= e($m['modulo']) ?>] <?= e($m['archivo']) ?></li>
  <?php endforeach; ?>
</ul>

<h3 class="h6 mt-3">Seeds pendientes (<?= count($plan->seedsPendientes) ?>)</h3>
<ul class="small">
  <?php foreach ($plan->seedsPendientes as $s): ?>
    <li>[<?= e($s['modulo']) ?>] <?= e($s['archivo']) ?></li>
  <?php endforeach; ?>
</ul>

<form method="post" action="?paso=ejecutar" onsubmit="this.querySelector('button').disabled=true;">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <button type="submit" class="btn btn-success">Instalar ahora</button>
</form>
