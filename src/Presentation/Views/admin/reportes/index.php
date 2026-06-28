<?php
/** @var array $reportes */
use Lebytek\Framework\Kernel\Security\Csrf;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Reportes</h1>
  <a href="/admin/reportes/crear" class="btn btn-primary">
    <i class="bi bi-plus-lg"></i> Nuevo reporte
  </a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Nombre</th><th>Fuente</th><th>Plantilla</th><th>Modo</th>
          <th>Compartido</th><th>Actualizado</th><th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($reportes)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay reportes. Crea el primero.</td></tr>
      <?php else: foreach ($reportes as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['fuente_key'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['template_key'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars((string) $r['modo'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= ((int) $r['compartido'] === 1) ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?></td>
          <td class="text-muted small"><?= htmlspecialchars((string) ($r['updated_at'] ?? $r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-end">
            <form method="post" action="/admin/reportes/<?= (int) $r['id'] ?>/generar" class="d-inline">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-outline-primary" title="Generar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
            </form>
            <a href="/admin/reportes/<?= (int) $r['id'] ?>/editar" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
            <form method="post" action="/admin/reportes/<?= (int) $r['id'] ?>/eliminar" class="d-inline"
                  data-confirm="¿Eliminar este reporte?">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
