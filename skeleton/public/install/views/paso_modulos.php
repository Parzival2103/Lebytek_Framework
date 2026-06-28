<?php /** @var array $manifests */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">3. Selección de módulos</h2>
<form method="post" action="?paso=admin">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <?php foreach ($manifests as $clave => $m): ?>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="modulos[]"
             value="<?= e($clave) ?>" id="mod_<?= e($clave) ?>"
             <?= $m->obligatorio ? 'checked disabled' : 'checked' ?>>
      <label class="form-check-label" for="mod_<?= e($clave) ?>">
        <span class="fw-semibold"><?= e($m->nombre) ?></span>
        <span class="badge bg-light text-dark ms-1">v<?= e($m->version) ?></span>
        <?php if ($m->obligatorio): ?><span class="badge bg-secondary ms-1">obligatorio</span><?php endif; ?>
        <div class="text-muted small"><?= e($m->descripcion) ?></div>
      </label>
    </div>
  <?php endforeach; ?>
  <?php // core obligatorio: campo oculto garantiza su envío aunque el checkbox esté disabled ?>
  <input type="hidden" name="modulos[]" value="core">
  <button type="submit" class="btn btn-primary mt-2">Continuar</button>
</form>
