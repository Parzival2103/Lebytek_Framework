<?php
// app/Presentation/Views/publico/layout_v2.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$empresaNombre = $empresaNombre ?? '';
$empresaLogo   = $empresaLogo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($pageTitle ?? 'API WhatsApp Business Lebytek | Automatiza Mensajes y Campañas') ?></title>
    <meta name="description" content="<?= ViewHelper::e($metaDescription ?? 'Automatiza WhatsApp para tu negocio con Lebytek. Envía campañas, notificaciones y respuestas automáticas en minutos. Demo inmediata.') ?>">
    <meta name="theme-color" content="#05070F">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="/assets/publico/landing_v2.css" rel="stylesheet">
</head>
<body>
    <header class="lb-nav" style="position:sticky; top:0; z-index:30; display:flex; align-items:center; gap:20px; padding:16px 28px; background:rgba(11,18,32,0.65); backdrop-filter:blur(10px); border-bottom:1px solid rgba(255,255,255,0.08); flex-wrap:wrap;">
      <a href="/" style="display:flex; align-items:center; gap:10px; margin-right:auto;">
        <?php if ($empresaLogo !== ''): ?>
          <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="" height="22">
        <?php else: ?>
          <span style="width:22px; height:22px; border-radius:6px; background:linear-gradient(135deg,#25D366,#00E6A0);"></span>
        <?php endif; ?>
        <span style="font-family:'Syne',sans-serif; font-weight:700; font-size:18px; color:#F5F7FA;"><?= ViewHelper::e($empresaNombre) ?></span>
      </a>
      <a href="#funciones" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">Funciones</a>
      <a href="#paquetes" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">Paquetes</a>
      <a href="#faq" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">FAQ</a>
      <a href="#demo" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">Demo</a>
      <a href="/login" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75); margin-right:6px;">Acceder</a>
      <a href="#demo" data-lb-cta="nav" style="background:linear-gradient(135deg,#25D366,#00E6A0); color:#05070F; font-weight:600; font-size:14px; padding:10px 18px; border-radius:8px;">Solicitar demo</a>
    </header>

    <main><?= $content ?? '' ?></main>

    <footer style="border-top:1px solid #25D366; padding:56px 28px;">
      <div style="max-width:1240px; margin:0 auto; display:flex; flex-wrap:wrap; gap:48px; justify-content:space-between;">
        <div style="max-width:32ch;">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
            <span style="width:16px; height:16px; border-radius:4px; background:linear-gradient(135deg,#25D366,#00E6A0);"></span>
            <span style="font-family:'Syne',sans-serif; font-weight:700; font-size:16px; color:#F5F7FA;"><?= ViewHelper::e($empresaNombre) ?></span>
          </div>
          <p style="font-size:13px; color:rgba(245,247,250,0.55);">Plataforma de mensajería WhatsApp Business para equipos en México.</p>
        </div>
        <div>
          <div style="font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:rgba(245,247,250,0.45); margin-bottom:10px;">Producto</div>
          <div style="display:flex; flex-direction:column; gap:8px; font-size:14px;">
            <a href="#paquetes">Paquetes</a><a href="#faq">FAQ</a><a href="#demo">Demo</a><a href="/login">Acceder</a>
          </div>
        </div>
        <div>
          <div style="font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:rgba(245,247,250,0.45); margin-bottom:10px;">Empresa</div>
          <div style="display:flex; flex-direction:column; gap:8px; font-size:14px;">
            <a href="#demo">Contacto</a><a href="mailto:soporte@lebytek.com">Soporte</a>
          </div>
        </div>
      </div>
      <div style="max-width:1240px; margin:32px auto 0; font-size:12px; color:rgba(245,247,250,0.4);">© <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?></div>
    </footer>

    <script src="/assets/publico/landing_v2.js" defer></script>
    <script>
window.__LB_METRICS__ = {
  variant: <?= json_encode($landingVariant ?? '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
  visitorId: <?= json_encode($visitorId ?? '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
  isPreview: <?= !empty($isPreview) ? 'true' : 'false' ?>,
  endpoint: '/marketing/collect'
};
    </script>
    <script src="/assets/publico/landing_metrics.js" defer></script>
</body>
</html>
