<?php use Lebytek\Framework\Kernel\Helpers\ViewHelper; ?>

<div class="ct-page">
<div class="ct-page-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h1 class="ct-page-title">Usuarios</h1>
        <p class="ct-page-subtitle">Gestiona los usuarios del sistema y sus accesos.</p>
    </div>
    <div class="ct-actions">
        <a href="/admin/administracion/usuarios/crear" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Nuevo usuario</span>
        </a>
    </div>
</div>

<div class="ct-table-card card border-0 shadow-sm ct-card">
    <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex flex-column flex-md-row align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h2 class="ct-card-title h6 mb-0 fw-semibold">Usuarios del sistema</h2>
            <span class="badge bg-secondary"><?= (int) ($total ?? 0) ?></span>
        </div>
        <div class="ms-md-auto w-100 w-md-auto">
            <label class="visually-hidden" for="tableSearch">Buscar en tabla</label>
            <input type="search" class="form-control form-control-sm ct-table-toolbar-search" id="tableSearch"
                   placeholder="Buscar…" autocomplete="off">
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 js-dt-responsive" id="usuariosTable">
                <thead class="table-light">
                    <tr>
                        <th class="px-3 sortable" data-col="0" data-priority="2">Nombre</th>
                        <th class="sortable" data-col="1">Correo</th>
                        <th class="sortable" data-col="2">Estado</th>
                        <th class="sortable" data-col="3">Último acceso</th>
                        <th class="text-end px-3" data-priority="1">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="5" class="p-0">
                            <?= ViewHelper::partial('empty_state', [
                                'icon' => 'bi-people',
                                'title' => 'No hay usuarios registrados',
                                'hint' => 'Crea un usuario desde «Nuevo usuario».',
                                'extraClass' => 'py-5',
                            ]) ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                    <tr data-id="<?= (int) $u['id'] ?>">
                        <td class="px-3">
                            <div class="d-flex align-items-center gap-3">
                                <?= ViewHelper::partial('avatar_thumb', [
                                    'thumbClase'  => 'table-avatar flex-shrink-0',
                                    'thumbRuta'   => $u['avatar'] ?? null,
                                    'thumbNombre' => $u['nombre'],
                                ]) ?>
                                <div>
                                    <div class="fw-medium"><?= ViewHelper::e($u['nombreCompleto']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted small"><?= ViewHelper::e($u['email']) ?></td>
                        <td>
                            <?php if ($u['activo']): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-2">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= $u['ultimo_acceso']
                                ? date('d/m/Y H:i', strtotime($u['ultimo_acceso']))
                                : 'Nunca' ?>
                        </td>
                        <td class="text-end px-3">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="/admin/administracion/usuarios/<?= (int) $u['id'] ?>/editar"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="/admin/administracion/usuarios/<?= (int) $u['id'] ?>"
                                      class="delete-form">
                                    <?= ViewHelper::csrfField() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Desactivar"
                                            data-confirm="¿Desactivar este usuario?">
                                        <i class="bi bi-person-x"></i>
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

    <?php if (!empty($paginator) && $paginator->hasPages()): ?>
    <div class="card-footer bg-transparent border-top px-4 py-3">
        <div class="d-flex align-items-center justify-content-between">
            <span class="small text-muted">
                <?= (int) ($total ?? 0) ?> registros en total
            </span>
            <?= $paginator->render() ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<?= ViewHelper::partial('datatables_responsive') ?>
