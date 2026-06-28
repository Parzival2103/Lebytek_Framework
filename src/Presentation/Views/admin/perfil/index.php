<?php use App\Kernel\Helpers\ViewHelper; ?>

<div class="row justify-content-center g-4">
    <div class="col-12 col-xl-8">

        <?= ViewHelper::partial('avatar_manager', [
            'avatarBaseUrl'   => '/admin/perfil/avatar',
            'avatarHistorial' => $historial ?? [],
        ]) ?>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3"><i class="bi bi-person-vcard me-2"></i>Datos personales</h2>

                <form method="POST" action="/admin/perfil" id="perfilForm">
                    <?= ViewHelper::csrfField() ?>
                    <input type="hidden" name="_method" value="PUT">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label fw-medium small">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre" name="nombre" class="form-control"
                                   value="<?= ViewHelper::old('nombre', $usuario['nombre']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="apellido" class="form-label fw-medium small">Apellido <span class="text-danger">*</span></label>
                            <input type="text" id="apellido" name="apellido" class="form-control"
                                   value="<?= ViewHelper::old('apellido', $usuario['apellido']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-medium small">Correo electrónico <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= ViewHelper::old('email', $usuario['email']) ?>" required>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Guardar cambios
                        </button>
                        <?php if (!empty($usuario['email'])): ?>
                            <a href="/recuperar" class="btn btn-outline-secondary">
                                <i class="bi bi-key me-2"></i>Cambiar contraseña
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script src="<?= ViewHelper::asset('js/avatar-manager.js') ?>" defer></script>
