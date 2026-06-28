<?php use Lebytek\Framework\Kernel\Helpers\ViewHelper; ?>

<div class="ct-page">
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="ct-form-card card border-0 shadow-sm ct-card">
            <div class="card-header bg-transparent border-bottom px-4 py-3">
                <h5 class="mb-0 fw-semibold">Editar rol</h5>
                <p class="text-muted small mb-0 mt-1">Permisos cargados desde el catálogo actual (<code>auth_permisos</code>).</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/administracion/roles/<?= (int) $rol['id'] ?>" id="rolForm">
                    <?= ViewHelper::csrfField() ?>
                    <input type="hidden" name="_method" value="PUT">

                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label for="nombre" class="form-label fw-medium small">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre" name="nombre" class="form-control"
                                   value="<?= ViewHelper::old('nombre', $rol['nombre']) ?>"
                                   placeholder="Ej: Ventas" required>
                        </div>
                        <div class="col-md-4">
                            <label for="slug" class="form-label fw-medium small">Slug <span class="text-danger">*</span></label>
                            <input type="text" id="slug" name="slug" class="form-control font-monospace"
                                   value="<?= ViewHelper::old('slug', $rol['slug']) ?>"
                                   placeholder="ventas" required
                                   pattern="[a-z0-9_\-]+">
                            <div class="form-text">Solo letras minúsculas, números y guiones</div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch pb-2">
                                <input class="form-check-input" type="checkbox" name="activo" id="activo"
                                       value="1" <?= $rol['activo'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="activo">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-medium small">Descripción</label>
                            <textarea id="descripcion" name="descripcion" class="form-control" rows="2"
                                      placeholder="Descripción del rol..."><?= ViewHelper::old('descripcion', $rol['descripcion']) ?></textarea>
                        </div>
                    </div>

                    <?php if (!empty($permisosAgrupados)): ?>
                    <hr class="my-4">
                    <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch align-items-md-center justify-content-between mb-3">
                        <h6 class="fw-semibold mb-0">Permisos</h6>
                        <input type="search" id="filtroPermisos" class="form-control form-control-sm ct-table-toolbar-search"
                               placeholder="Filtrar por nombre o slug…" autocomplete="off" style="max-width: 20rem;">
                    </div>
                    <div class="row g-4" id="gruposPermisos">
                        <?php foreach ($permisosAgrupados as $grupo): ?>
                        <div class="col-12 col-lg-6 permiso-grupo-col" data-grupo-label="<?= ViewHelper::e(mb_strtolower($grupo['grupo_label'] . ' ' . $grupo['grupo_id'])) ?>">
                            <div class="card border shadow-sm h-100 ct-card permiso-grupo-card">
                                <div class="card-header bg-transparent py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <span class="fw-semibold small mb-0"><?= ViewHelper::e($grupo['grupo_label']) ?></span>
                                    <button type="button" class="btn btn-link btn-sm p-0 small"
                                            data-select-grupo="<?= ViewHelper::e((string) $grupo['grupo_id']) ?>">
                                        Seleccionar todo
                                    </button>
                                </div>
                                <div class="card-body py-2 px-3">
                                    <?php foreach ($grupo['permisos'] as $permiso): ?>
                                    <?php
                                        $pid = (int) $permiso['id'];
                                        $pslug = (string) ($permiso['slug'] ?? '');
                                        $pnombre = (string) ($permiso['nombre'] ?? '');
                                        $searchBlob = mb_strtolower($pnombre . ' ' . $pslug);
                                        $checked = in_array($pid, $permisoIdsAsignados, true) ? 'checked' : '';
                                    ?>
                                    <div class="form-check permiso-linea mb-2" data-search-text="<?= ViewHelper::e($searchBlob) ?>">
                                        <input class="form-check-input permiso-in-grupo-<?= ViewHelper::e((string) $grupo['grupo_id']) ?>"
                                               type="checkbox"
                                               name="permiso_ids[]"
                                               value="<?= $pid ?>"
                                               id="permiso_<?= $pid ?>"
                                               <?= $checked ?>>
                                        <label class="form-check-label small" for="permiso_<?= $pid ?>">
                                            <span class="fw-medium"><?= ViewHelper::e($pnombre) ?></span>
                                            <br><code class="small text-muted"><?= ViewHelper::e($pslug) ?></code>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mt-3 mb-0" id="permisosSinCoincidencia" style="display: none;">No hay permisos que coincidan con el filtro.</p>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2" aria-hidden="true"></i>Guardar cambios
                        </button>
                        <a href="/admin/administracion/roles" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script>
document.querySelectorAll('[data-select-grupo]').forEach(btn => {
    btn.addEventListener('click', () => {
        const gid = btn.dataset.selectGrupo;
        const checks = document.querySelectorAll('.permiso-in-grupo-' + CSS.escape(gid));
        const visible = [...checks].filter(c => {
            const linea = c.closest('.permiso-linea');
            return linea && linea.style.display !== 'none';
        });
        const target = visible.length ? visible : [...checks];
        const allChecked = target.length && target.every(c => c.checked);
        target.forEach(c => { c.checked = !allChecked; });
        btn.textContent = allChecked ? 'Seleccionar todo' : 'Quitar selección';
    });
});

const filtro = document.getElementById('filtroPermisos');
if (filtro) {
    filtro.addEventListener('input', () => {
        const q = filtro.value.trim().toLowerCase();
        document.querySelectorAll('.permiso-linea').forEach(linea => {
            const t = (linea.dataset.searchText || '');
            const show = q === '' || t.includes(q);
            linea.style.display = show ? '' : 'none';
        });
        document.querySelectorAll('.permiso-grupo-col').forEach(col => {
            const visibleLines = [...col.querySelectorAll('.permiso-linea')].some(l => l.style.display !== 'none');
            col.style.display = visibleLines ? '' : 'none';
        });
        const emptyMsg = document.getElementById('permisosSinCoincidencia');
        if (emptyMsg) {
            const anyCol = [...document.querySelectorAll('.permiso-grupo-col')].some(c => c.style.display !== 'none');
            emptyMsg.style.display = (q !== '' && !anyCol) ? 'block' : 'none';
        }
    });
}
</script>
