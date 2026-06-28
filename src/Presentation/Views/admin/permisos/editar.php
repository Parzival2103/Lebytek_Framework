<?php use Lebytek\Framework\Kernel\Helpers\ViewHelper; ?>

<div class="ct-page">
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="ct-form-card card border-0 shadow-sm ct-card">
            <div class="card-body p-4">
                <form method="POST" action="/admin/administracion/permisos/<?= (int) $permiso['id'] ?>">
                    <?= ViewHelper::csrfField() ?>
                    <input type="hidden" name="_method" value="PUT">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label fw-medium small">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre" name="nombre" class="form-control"
                                   value="<?= ViewHelper::old('nombre', $permiso['nombre']) ?>"
                                   placeholder="Ej: Ver usuarios" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modulo" class="form-label fw-medium small">Módulo <span class="text-danger">*</span></label>
                            <input type="text" id="modulo" name="modulo" class="form-control"
                                   value="<?= ViewHelper::old('modulo', $permiso['modulo']) ?>"
                                   placeholder="Ej: usuarios" required>
                        </div>
                        <div class="col-12">
                            <label for="slug" class="form-label fw-medium small">Slug <span class="text-danger">*</span></label>
                            <input type="text" id="slug" name="slug" class="form-control font-monospace"
                                   value="<?= ViewHelper::old('slug', $permiso['slug']) ?>"
                                   placeholder="modulo.accion" required
                                   pattern="[a-z0-9_]+\.[a-z0-9_]+"
                                   maxlength="100">
                            <div class="form-text">
                                Formato obligatorio <code>módulo.acción</code> (validación en servidor según reglas en documentación).
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-medium small">Descripción</label>
                            <textarea id="descripcion" name="descripcion" class="form-control" rows="2"
                                      placeholder="Describe qué permite hacer este permiso..."><?= ViewHelper::old('descripcion', $permiso['descripcion']) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Guardar cambios
                        </button>
                        <a href="/admin/administracion/permisos" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3 border-warning ct-card">
            <div class="card-body p-3 d-flex gap-3 align-items-start">
                <i class="bi bi-exclamation-triangle text-warning mt-1"></i>
                <div class="small text-muted">
                    Modificar el <strong>slug</strong> de un permiso puede romper políticas RBAC si hay código
                    que lo referencie directamente. Asegúrate de actualizar <code>core_menu_items.permiso_slug</code> y filas relacionadas (ver documentación en el repositorio: <code>docs/modules/modulo-menu.md</code>).
                </div>
            </div>
        </div>
    </div>
</div>
</div>