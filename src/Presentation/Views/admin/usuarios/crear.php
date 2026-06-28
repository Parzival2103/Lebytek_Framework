<?php use Lebytek\Framework\Kernel\Helpers\ViewHelper; ?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="/admin/administracion/usuarios" id="usuarioForm">
                    <?= ViewHelper::csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label fw-medium small">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre" name="nombre" class="form-control"
                                   value="<?= ViewHelper::old('nombre') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="apellido" class="form-label fw-medium small">Apellido <span class="text-danger">*</span></label>
                            <input type="text" id="apellido" name="apellido" class="form-control"
                                   value="<?= ViewHelper::old('apellido') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-medium small">Correo electrónico <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= ViewHelper::old('email') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-medium small">Contraseña <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control"
                                       placeholder="Mínimo 8 caracteres" required minlength="8">
                                <button class="btn btn-outline-secondary" type="button" data-toggle-password="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($roles)): ?>
                        <div class="col-12">
                            <label class="form-label fw-medium small">Roles</label>
                            <div class="row g-2">
                                <?php foreach ($roles as $rol): ?>
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="rol_ids[]"
                                               value="<?= (int) $rol['id'] ?>"
                                               id="rol_<?= (int) $rol['id'] ?>">
                                        <label class="form-check-label small" for="rol_<?= (int) $rol['id'] ?>">
                                            <?= ViewHelper::e($rol['nombre']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo" id="activo"
                                       value="1" checked>
                                <label class="form-check-label small" for="activo">Usuario activo</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Guardar usuario
                        </button>
                        <a href="/admin/administracion/usuarios" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
