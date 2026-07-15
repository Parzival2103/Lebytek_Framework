<?php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Session;

/** @var array<string, mixed> $paquete */
/** @var string $slug */
/** @var string $ciclo */
/** @var float|null $precio */
?>
<section class="py-5">
  <div class="container" style="max-width: 720px;">
    <h1 class="h3 mb-2">Comprar <?= ViewHelper::e((string) ($paquete['nombre'] ?? $slug)) ?></h1>
    <p class="text-muted mb-4">
      Periodo: <strong><?= $ciclo === 'annual' ? 'Anual' : 'Mensual' ?></strong>
      <?php if ($precio !== null): ?>
        — <strong>$<?= ViewHelper::e(number_format($precio, 2, '.', ',')) ?> MXN</strong>
      <?php endif; ?>
    </p>

    <?php if ($msg = Session::getFlash('error')): ?>
      <div class="alert alert-danger"><?= ViewHelper::e((string) $msg) ?></div>
    <?php endif; ?>

    <form method="post" action="/comprar/<?= ViewHelper::e($slug) ?>" class="card shadow-sm border-0">
      <div class="card-body p-4">
        <?= ViewHelper::csrfField() ?>
        <input type="hidden" name="ciclo" value="<?= ViewHelper::e($ciclo) ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="nombre">Nombre completo *</label>
            <input class="form-control" id="nombre" name="nombre" required maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="email">Correo *</label>
            <input class="form-control" id="email" name="email" type="email" required maxlength="190">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="telefono">Teléfono *</label>
            <input class="form-control" id="telefono" name="telefono" required maxlength="40">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="empresa">Empresa *</label>
            <input class="form-control" id="empresa" name="empresa" required maxlength="190">
          </div>
          <div class="col-12">
            <label class="form-label" for="direccion">Dirección fiscal *</label>
            <input class="form-control" id="direccion" name="direccion" required maxlength="255">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="rfc">RFC (opcional)</label>
            <input class="form-control" id="rfc" name="rfc" maxlength="20">
          </div>
        </div>

        <fieldset class="mt-4">
          <legend class="h6">Método de pago</legend>
          <div class="form-check">
            <input class="form-check-input" id="metodo_pago_stripe" name="metodo_pago" type="radio" value="stripe">
            <label class="form-check-label" for="metodo_pago_stripe">Tarjeta</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" id="metodo_pago_transfer" name="metodo_pago" type="radio" value="transfer" checked>
            <label class="form-check-label" for="metodo_pago_transfer">Transferencia bancaria</label>
          </div>
        </fieldset>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">Continuar al pago</button>
          <a class="btn btn-outline-secondary" href="/?compras=1#paquetes">Volver a paquetes</a>
        </div>
      </div>
    </form>
  </div>
</section>
