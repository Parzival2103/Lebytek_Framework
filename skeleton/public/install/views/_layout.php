<?php /** @var string $contenido */ /** @var string $tituloPaso */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalador — <?= htmlspecialchars($tituloPaso ?? '') ?></title>
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/lebytek-ui.css">
</head>
<body class="bg-light">
  <div class="container" style="max-width: 720px;">
    <div class="py-4 text-center">
      <h1 class="h4">Instalador Lebytek</h1>
    </div>
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <?= $contenido ?>
      </div>
    </div>
    <p class="text-center text-muted small mt-3">Lebytek Framework</p>
  </div>
</body>
</html>
