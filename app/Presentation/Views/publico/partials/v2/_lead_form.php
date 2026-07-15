<?php
// app/Presentation/Views/publico/partials/v2/_lead_form.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Session;

$flash = $flashAll ?? Session::flashAll();
$flash = is_array($flash) ? $flash : [];

$inputStyle = 'width:100%; box-sizing:border-box; padding:11px 14px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#F5F7FA; font:inherit; font-size:14px;';
$labelStyle = 'display:block; font-size:12px; color:rgba(245,247,250,0.6); margin-bottom:6px;';
?>
<section id="demo" data-reveal-id="cta" class="lb-reveal" style="background:rgba(255,255,255,0.02); border-top:1px solid #25D366;">
  <div style="max-width:640px; margin:0 auto; padding:80px 28px;">
    <div style="border:1px solid rgba(255,255,255,0.75); box-shadow:0 0 32px rgba(255,255,255,0.18), 0 0 60px rgba(255,255,255,0.08); border-radius:16px; padding:36px; background:rgba(255,255,255,0.04);">
      <h2 style="font-family:'Syne',sans-serif; font-size:clamp(24px,2.6vw,30px); font-weight:700; margin:0 0 8px;">Solicita una demo</h2>
      <p style="color:rgba(245,247,250,0.65); margin:0 0 24px;">Cuéntanos sobre tu proyecto y te contactamos pronto.</p>

      <?php foreach ($flash as $tipo => $msg): ?>
        <?php if (in_array($tipo, ['success', 'error'], true)): ?>
          <div style="padding:12px 16px; margin-bottom:16px; border-radius:8px; font-size:14px; border:1px solid <?= $tipo === 'success' ? '#00E6A0' : '#ff6b6b' ?>; color:<?= $tipo === 'success' ? '#00E6A0' : '#ff9b9b' ?>;">
            <?= ViewHelper::e(is_array($msg) ? implode(' ', $msg) : (string) $msg) ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <form method="POST" action="/lead" data-lead-form style="display:flex; flex-direction:column; gap:14px;">
        <?= ViewHelper::csrfField() ?>
        <input type="hidden" name="landing_variant" value="<?= ViewHelper::e((string)($landingVariant ?? '')) ?>">
        <input type="hidden" name="visitor_id" value="<?= ViewHelper::e((string)($visitorId ?? '')) ?>">
        <div><label style="<?= $labelStyle ?>">Nombre</label><input type="text" name="nombre" placeholder="Tu nombre" required style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">Correo</label><input type="email" name="email" placeholder="tu@correo.com" required style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">Empresa (opcional)</label><input type="text" data-empresa-merge placeholder="Nombre de tu negocio" style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">WhatsApp / Teléfono</label><input type="tel" name="telefono" placeholder="55 0000 0000" style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">Mensaje</label><textarea name="mensaje" placeholder="Cuéntanos qué necesitas automatizar" style="<?= $inputStyle ?> min-height:90px; resize:vertical;"></textarea></div>
        <button type="submit" style="background:linear-gradient(135deg,#25D366,#00E6A0); color:#05070F; font-weight:600; font-size:15px; padding:13px; border:none; border-radius:8px; cursor:pointer;">Enviar</button>
      </form>
    </div>
  </div>
</section>
