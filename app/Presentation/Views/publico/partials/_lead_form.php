<?php
// app/Presentation/Views/publico/partials/_lead_form.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Session;

$flash = $flashAll ?? Session::flashAll();
$flash = is_array($flash) ? $flash : [];
?>
<section class="ct-demo" id="demo">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="ct-section__title">Solicita una demo</h2>
      <p class="ct-section__lead">Cuéntanos sobre tu proyecto y te contactamos pronto.</p>
    </div>
    <div class="ct-demo__card mx-auto">
      <?php foreach ($flash as $tipo => $msg): ?>
        <?php if (in_array($tipo, ['success', 'error'], true)): ?>
          <div class="alert alert-<?= $tipo === 'success' ? 'success' : 'danger' ?>">
            <?= ViewHelper::e(is_array($msg) ? implode(' ', $msg) : (string) $msg) ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <form method="POST" action="/lead">
        <?= ViewHelper::csrfField() ?>
        <div class="mb-3"><input type="text" name="nombre" class="form-control form-control-lg" placeholder="Nombre" required></div>
        <div class="mb-3"><input type="email" name="email" class="form-control form-control-lg" placeholder="Correo" required></div>
        <div class="mb-3"><input type="text" name="telefono" class="form-control form-control-lg" placeholder="Teléfono (opcional)"></div>
        <div class="mb-3"><textarea name="mensaje" class="form-control" rows="3" placeholder="¿En qué te ayudamos?"></textarea></div>
        <button type="submit" class="btn btn-primary btn-lg w-100">Enviar</button>
      </form>
    </div>
  </div>
</section>
