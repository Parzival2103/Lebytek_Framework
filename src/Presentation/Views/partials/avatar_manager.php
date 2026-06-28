<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/*
|--------------------------------------------------------------------------
| Partial: avatar_manager
|--------------------------------------------------------------------------
| Variables que define el include:
|   $avatarBaseUrl   string  p.ej. '/admin/perfil/avatar' o
|                            '/admin/administracion/usuarios/5/avatar'
|   $avatarHistorial array   historial vigente (Archivo::toArray():
|                            id, ruta, esActual), más reciente primero
|
| El estado inicial se renderiza server-side; avatar-manager.js solo
| repinta con las respuestas JSON de los endpoints.
*/

$avatarHistorial = is_array($avatarHistorial ?? null) ? $avatarHistorial : [];
$avatarActual    = null;
foreach ($avatarHistorial as $avatarItem) {
    if (!empty($avatarItem['esActual'])) {
        $avatarActual = $avatarItem;
        break;
    }
}
?>
<div class="card border-0 shadow-sm"
     data-avatar-manager
     data-base-url="<?= ViewHelper::e($avatarBaseUrl) ?>"
     data-csrf="<?= ViewHelper::e(ViewHelper::csrfToken()) ?>">
    <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3"><i class="bi bi-person-circle me-2"></i>Foto de perfil</h2>

        <div class="alert alert-danger d-none py-2 small" data-avatar-error role="alert"></div>

        <div class="row g-3 align-items-start">
            <div class="col-12 col-sm-auto">
                <div class="position-relative d-inline-block" data-avatar-main>
                    <?php if ($avatarActual !== null): ?>
                        <img src="<?= ViewHelper::e($avatarActual['ruta']) ?>" alt="Avatar actual"
                             class="rounded-3 object-fit-cover border" width="140" height="140">
                        <button type="button"
                                class="btn btn-danger btn-sm rounded-circle position-absolute top-0 start-100 translate-middle p-0 d-flex align-items-center justify-content-center"
                                style="width: 1.6rem; height: 1.6rem;"
                                data-avatar-delete="<?= (int) $avatarActual['id'] ?>"
                                aria-label="Eliminar foto de perfil">
                            <i class="bi bi-x-lg small"></i>
                        </button>
                    <?php else: ?>
                        <div class="rounded-3 border border-2 border-dashed d-flex flex-column align-items-center justify-content-center text-secondary bg-body-tertiary"
                             style="width: 140px; height: 140px;">
                            <i class="bi bi-person fs-1"></i>
                            <span class="small">Sin foto de perfil</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col">
                <label class="form-label fw-medium small" for="avatarInput">Cambiar o agregar foto</label>
                <input type="file" class="form-control" id="avatarInput"
                       accept=".jpg,.jpeg,.png,.webp" data-avatar-input>
                <div class="form-text">JPG, PNG o WEBP. La imagen se ajusta automáticamente.</div>
                <div class="spinner-border spinner-border-sm text-secondary d-none mt-2" data-avatar-spinner role="status">
                    <span class="visually-hidden">Subiendo…</span>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h3 class="small text-secondary fw-semibold text-uppercase mb-2">Fotos anteriores</h3>
            <div class="d-flex flex-wrap gap-2" data-avatar-gallery>
                <?php foreach ($avatarHistorial as $avatarItem): ?>
                    <button type="button"
                            class="btn p-0 border rounded-2 overflow-hidden <?= !empty($avatarItem['esActual']) ? 'border-primary border-2' : '' ?>"
                            data-avatar-pick="<?= (int) $avatarItem['id'] ?>"
                            <?= !empty($avatarItem['esActual']) ? 'data-avatar-current' : '' ?>
                            aria-label="Usar esta foto">
                        <img src="<?= ViewHelper::e($avatarItem['ruta']) ?>" alt="" width="56" height="56"
                             class="object-fit-cover d-block">
                    </button>
                <?php endforeach; ?>
                <?php if ($avatarHistorial === []): ?>
                    <span class="text-secondary small" data-avatar-empty>Sin fotos anteriores.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
