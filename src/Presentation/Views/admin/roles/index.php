<?php use Lebytek\Framework\Kernel\Helpers\ViewHelper; ?>

<div class="ct-page">
<div class="ct-page-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h1 class="ct-page-title">Roles</h1>
        <p class="ct-page-subtitle">Define los roles y sus permisos asociados.</p>
    </div>
    <div class="ct-actions">
        <a href="/admin/administracion/roles/crear" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Nuevo rol</span>
        </a>
    </div>
</div>

<div class="ct-table-card card border-0 shadow-sm ct-card">
    <div class="card-header bg-transparent border-bottom px-4 py-3">
        <h6 class="mb-0 fw-semibold">Roles del sistema</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 js-dt-responsive">
                <thead class="table-light">
                    <tr>
                        <th class="px-3" data-priority="2">Nombre</th>
                        <th>Slug</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th class="text-end px-4" data-priority="1">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($roles)): ?>
                    <tr>
                        <td colspan="5" class="p-0">
                            <?= ViewHelper::partial('empty_state', [
                                'icon' => 'bi-shield-lock',
                                'title' => 'No hay roles configurados',
                                'hint' => 'Crea un rol para asignar permisos.',
                                'extraClass' => 'py-5',
                            ]) ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($roles as $rol): ?>
                    <tr>
                        <td class="px-4 fw-medium"><?= ViewHelper::e($rol['nombre']) ?></td>
                        <td><code class="small"><?= ViewHelper::e($rol['slug']) ?></code></td>
                        <td class="text-muted small"><?= ViewHelper::e($rol['descripcion']) ?: '—' ?></td>
                        <td>
                            <?php if ($rol['activo']): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-2">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end px-4">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="/admin/administracion/roles/<?= (int) $rol['id'] ?>/editar"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="/admin/administracion/roles/<?= (int) $rol['id'] ?>"
                                      class="delete-form">
                                    <?= ViewHelper::csrfField() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            data-confirm="¿Eliminar este rol? Esta acción no se puede deshacer.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?= ViewHelper::partial('datatables_responsive') ?>
