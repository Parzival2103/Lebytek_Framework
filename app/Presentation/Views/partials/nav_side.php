<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Constants\AppConstants;

$uri = $currentUri ?? '/';
$mostrarEmpresaNombre = AppConstants::empresaMostrarNombre($mostrarEmpresaNombre ?? null);

$isActive = static function (?string $path) use ($uri): string {
    if ($path === null || $path === '') return '';
    return str_starts_with($uri, $path) ? 'active' : '';
};

$menuItems = $menuFiltrado ?? [];
?>

<nav id="sidebar" class="sidebar ct-sidebar d-flex flex-column flex-shrink-0">
    <!-- Marca -->
    <div class="sidebar-brand d-flex align-items-center px-3 py-3">
        <?php if (!empty($empresaLogo)): ?>
            <img src="<?= ViewHelper::e($empresaLogo) ?>"
                 alt="<?= ViewHelper::e($empresaNombre) ?>"
                 class="sidebar-logo-full"
                 title="<?= ViewHelper::e($empresaNombre) ?>">
        <?php else: ?>
            <div class="sidebar-icon-brand me-2">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </div>
            <?php if ($mostrarEmpresaNombre): ?>
            <span class="sidebar-brand-text fw-bold text-truncate"><?= ViewHelper::e($empresaNombre) ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <button class="btn btn-link <?= !empty($empresaLogo) ? 'ms-auto' : '' ?> sidebar-toggle p-0" id="sidebarToggle" aria-label="Colapsar menú">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <!-- Navegación -->
    <div class="sidebar-nav flex-grow-1 overflow-y-auto">
        <ul class="nav flex-column px-2 gap-1">
            <?php foreach ($menuItems as $item): ?>
                <?php
                $tieneSubmenus = !empty($item['submenu']);
                $itemId = 'menu-' . ($item['id'] ?? md5($item['label']));
                $isParentActive = $isActive($item['match'] ?? null);
                ?>
                <?php if ($tieneSubmenus): ?>
                <li class="nav-item">
                    <a class="nav-link sidebar-link with-submenu <?= $isParentActive ?>"
                       data-bs-toggle="collapse"
                       href="#<?= $itemId ?>"
                       aria-expanded="<?= $isParentActive ? 'true' : 'false' ?>">
                        <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?> nav-icon"></i>
                        <span class="nav-label"><?= ViewHelper::e($item['label']) ?></span>
                        <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
                    </a>
                    <div id="<?= $itemId ?>" class="collapse <?= $isParentActive ? 'show' : '' ?>">
                        <ul class="nav flex-column ps-3 gap-1 mt-1">
                            <?php foreach ($item['submenu'] as $sub): ?>
                                <?php $subActive = $isActive($sub['url'] ?? null); ?>
                                <li class="nav-item">
                                    <a href="<?= ViewHelper::e($sub['url']) ?>"
                                       class="nav-link sidebar-link sidebar-sublink <?= $subActive ?>">
                                        <i class="bi <?= ViewHelper::e($sub['icon'] ?? 'bi-dash') ?> nav-icon"></i>
                                        <span class="nav-label"><?= ViewHelper::e($sub['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <?php $leafUrl = (string) ($item['url'] ?? ''); ?>
                    <a href="<?= ViewHelper::e($leafUrl !== '' ? $leafUrl : '#') ?>"
                       class="nav-link sidebar-link <?= $isActive($item['match'] ?? $item['url'] ?? null) ?>">
                        <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?> nav-icon"></i>
                        <span class="nav-label"><?= ViewHelper::e($item['label']) ?></span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Perfil en pie del sidebar -->
    <div class="sidebar-footer px-3 py-3 border-top">
        <div class="d-flex align-items-center gap-2">
            <?= ViewHelper::partial('avatar_thumb', [
                'thumbClase'  => 'sidebar-avatar',
                'thumbRuta'   => $usuario['avatar'] ?? null,
                'thumbNombre' => $usuario['nombre'] ?? 'U',
            ]) ?>
            <div class="sidebar-user-info nav-label overflow-hidden">
                <div class="fw-medium text-truncate small"><?= ViewHelper::e($usuario['nombreCompleto'] ?? $usuario['nombre'] ?? '') ?></div>
                <div class="ct-sidebar-user-email"><?= ViewHelper::e($usuario['email'] ?? '') ?></div>
            </div>
            <form method="POST" action="/logout" class="ms-auto nav-label m-0" onsubmit="return confirm('¿Cerrar sesión?');">
                <?= ViewHelper::csrfField() ?>
                <button type="submit" class="btn btn-link p-0"
                        title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</nav>
