<?php /** @var string $csrf */ /** @var array $errores */ /** @var string $email */ ?>
<h2 class="h5 mb-3">4. Cuenta de administrador</h2>
<?php foreach (($errores ?? []) as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>
<form method="post" action="?paso=revision">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" required value="<?= e($email ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Contraseña (mín. 8 caracteres)</label>
    <input type="password" name="password" class="form-control" required minlength="8">
  </div>
  <button type="submit" class="btn btn-primary">Continuar</button>
</form>
