<?php use App\Kernel\Helpers\ViewHelper; ?>

<div class="ct-page">
<div class="ct-page-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h1 class="ct-page-title">Permisos</h1>
        <p class="ct-page-subtitle">Permisos agrupados por módulo, asignables a roles.</p>
    </div>
    <div class="ct-actions">
        <a href="/admin/administracion/permisos/crear" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Nuevo permiso</span>
        </a>
    </div>
</div>

<?php if (empty($agrupados)): ?>
<div class="card border-0 shadow-sm ct-card">
    <div class="card-body p-4">
        <?= ViewHelper::partial('empty_state', [
            'icon' => 'bi-key',
            'title' => 'No hay permisos configurados',
            'hint' => 'Crea permisos para usarlos en roles y menú.',
        ]) ?>
    </div>
</div>
<?php else: ?>

<?php foreach ($agrupados as $modulo => $permisos): ?>
<div class="ct-table-card card border-0 shadow-sm ct-card mb-4">
    <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex align-items-center gap-2">
        <i class="bi bi-folder2 text-muted small"></i>
        <h6 class="mb-0 fw-semibold text-capitalize"><?= ViewHelper::e($modulo) ?></h6>
        <span class="badge bg-secondary-subtle text-secondary ms-auto"><?= count($permisos) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 js-dt-responsive">
                <thead class="table-light">
                    <tr>
                        <th class="px-3" data-priority="2">Nombre</th>
                        <th>Slug</th>
                        <th>Descripción</th>
                        <th class="text-end px-4" data-priority="1">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permisos as $permiso): ?>
                    <tr>
                        <td class="px-4 fw-medium"><?= ViewHelper::e($permiso['nombre']) ?></td>
                        <td><code class="small"><?= ViewHelper::e($permiso['slug']) ?></code></td>
                        <td class="text-muted small"><?= ViewHelper::e($permiso['descripcion']) ?: '—' ?></td>
                        <td class="text-end px-4">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="/admin/administracion/permisos/<?= (int) $permiso['id'] ?>/editar"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="/admin/administracion/permisos/<?= (int) $permiso['id'] ?>"
                                      class="delete-form">
                                    <?= ViewHelper::csrfField() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            data-confirm="¿Eliminar el permiso '<?= ViewHelper::e($permiso['nombre']) ?>'? Los roles que lo tengan asignado perderán este permiso.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?= ViewHelper::partial('datatables_responsive') ?>
</div>
