<?php /** @var string $version */ /** @var array $seleccion */ ?>
<h2 class="h5 mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Instalación completada</h2>
<p>Plataforma <strong>v<?= e($version) ?></strong> instalada.</p>
<p class="text-muted small">Módulos activos: <?= e(implode(', ', $seleccion)) ?></p>
<p class="text-muted small">El asistente quedó bloqueado por <code>storage/install.lock</code>.</p>
<a href="/login" class="btn btn-primary">Ir al login</a>
