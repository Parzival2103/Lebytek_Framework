<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Constants\UiConfirmConstants;
?>

<header class="topbar ct-topbar d-flex align-items-center px-3 gap-2">

    <!-- Toggle móvil (abre el sidebar) -->
    <button class="btn btn-ghost topbar-toggle p-1 me-1 d-lg-none"
            id="sidebarToggleMobile" aria-label="Menú">
        <i class="bi bi-list fs-5"></i>
    </button>

    <!-- Breadcrumb: texto plano, sin links azules subrayados -->
    <nav aria-label="breadcrumb" class="d-none d-sm-flex align-items-center gap-2 flex-shrink-0">
        <a href="/admin/dashboard" class="topbar-crumb-home">Inicio</a>
        <?php if (!empty($titulo)): ?>
            <span class="topbar-crumb-sep">/</span>
            <span class="topbar-crumb-current"><?= ViewHelper::e($titulo) ?></span>
        <?php endif; ?>
    </nav>

    <!-- Spacer -->
    <div class="flex-grow-1"></div>

    <!-- Acciones derecha -->
    <div class="d-flex align-items-center gap-1">

        <!-- Notificaciones -->
        <button class="btn btn-ghost topbar-btn topbar-notif-btn position-relative"
                title="Notificaciones">
            <i class="bi bi-bell"></i>
        </button>

        <!-- Tema -->
        <button class="btn btn-ghost topbar-btn" id="themeToggle" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>

        <!-- Panel de estilos -->
        <button class="btn btn-ghost topbar-btn" id="stylePanelBtn" title="Personalizar interfaz">
            <i class="bi bi-palette"></i>
        </button>

        <!-- Menú usuario -->
        <div class="dropdown">
            <button class="btn btn-ghost topbar-btn d-flex align-items-center gap-2"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <?= ViewHelper::partial('avatar_thumb', [
                    'thumbClase'  => 'topbar-avatar',
                    'thumbRuta'   => $usuario['avatar'] ?? null,
                    'thumbNombre' => $usuario['nombre'] ?? 'U',
                ]) ?>
                <span class="d-none d-md-inline small fw-medium">
                    <?= ViewHelper::e($usuario['nombre'] ?? '') ?>
                </span>
                <i class="bi bi-chevron-down small opacity-75"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><h6 class="dropdown-header"><?= ViewHelper::e($usuario['nombreCompleto'] ?? '') ?></h6></li>
                <li><a class="dropdown-item" href="/admin/perfil">
                    <i class="bi bi-person me-2"></i>Mi perfil</a></li>
                <li><a class="dropdown-item" href="/admin/ajustes">
                    <i class="bi bi-gear me-2"></i>Ajustes</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <?php $logoutConfirmAttrs = ViewHelper::confirmAttrs([
                        'body'    => UiConfirmConstants::LOGOUT_BODY,
                        'title'   => UiConfirmConstants::LOGOUT_TITLE,
                        'ok'      => UiConfirmConstants::LOGOUT_OK,
                        'variant' => 'danger',
                        'icon'    => UiConfirmConstants::LOGOUT_ICON,
                    ]); ?>
                    <form method="POST" action="/logout" class="m-0" <?= $logoutConfirmAttrs ?>>
                        <?= ViewHelper::csrfField() ?>
                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center w-100 border-0 bg-transparent text-start py-2 px-3">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>

    </div>

</header>
