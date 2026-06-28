<?php
/** @var string $bodyHtml @var string $font */
$font = $font !== '' ? $font : 'DejaVu Sans';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }
  body { font-family: "<?= htmlspecialchars($font, ENT_QUOTES, 'UTF-8') ?>", sans-serif; font-size: 12px; color: #1a1a1a; }
  .pdf-h1 { font-size: 20px; margin: 0 0 2px; }
  .pdf-subtitle { color: #666; margin: 0 0 8px; }
  .pdf-text { margin: 4px 0; }
  .pdf-text-muted { color: #777; }
  .pdf-text-bold { font-weight: bold; }
  .pdf-logo img { display: block; }
  .pdf-spacer { display: block; }
  .pdf-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  .pdf-table th, .pdf-table td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
  .pdf-table th { background: #f2f2f2; }
  .pdf-empty { text-align: center; color: #999; }
  .pdf-indicator { display: inline-block; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; margin: 4px 6px 4px 0; }
  .pdf-indicator-value { font-size: 18px; font-weight: bold; }
  .pdf-indicator-label { font-size: 10px; color: #666; text-transform: uppercase; }
  .pdf-totals { margin: 8px 0; }
  .pdf-totals-label { padding: 2px 10px 2px 0; color: #555; }
  .pdf-totals-value { padding: 2px 0; font-weight: bold; text-align: right; }
  .pdf-signatures { margin-top: 40px; }
  .pdf-signature { display: inline-block; width: 45%; margin: 0 2% 16px; text-align: center; }
  .pdf-signature-line { border-top: 1px solid #333; margin-bottom: 4px; }
  .pdf-signature-label { font-size: 10px; color: #555; }
  .pdf-footer { margin-top: 16px; padding-top: 6px; border-top: 1px solid #ddd; font-size: 10px; color: #888; text-align: center; }
</style>
</head>
<body>
<?= $bodyHtml ?>
</body>
</html>
