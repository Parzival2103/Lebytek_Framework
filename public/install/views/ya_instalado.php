<?php /** @var string $lockResumen */ ?>
<h2 class="h5 mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Sistema ya instalado</h2>
<p class="text-muted">Este despliegue ya fue instalado. El asistente está bloqueado por <code>storage/install.lock</code>.</p>
<pre class="bg-light p-3 small rounded"><?= htmlspecialchars($lockResumen ?? '') ?></pre>
<a href="/login" class="btn btn-primary">Ir al login</a>
