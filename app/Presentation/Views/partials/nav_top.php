<?php
use App\Kernel\Helpers\ViewHelper;

$uri = $currentUri ?? (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$menuItems = $menuFiltrado ?? [];
?>

<nav class="navbar navbar-expand-lg topnav ct-topbar shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/admin/dashboard">
            <?php if (!empty($empresaLogo)): ?>
                <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="Logo" height="32">
            <?php else: ?>
                <i class="bi bi-grid-3x3-gap-fill"></i>
            <?php endif; ?>
            <span class="fw-bold"><?= ViewHelper::e($empresaNombre) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNavMenu">
            <ul class="navbar-nav me-auto gap-1">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (!empty($item['submenu'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= str_starts_with($uri, $item['match'] ?? '') ? 'active' : '' ?>"
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                            <?= ViewHelper::e($item['label']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($item['submenu'] as $sub): ?>
                                <li><a class="dropdown-item <?= str_starts_with($uri, $sub['url']) ? 'active' : '' ?>"
                                       href="<?= ViewHelper::e($sub['url']) ?>">
                                    <i class="bi <?= ViewHelper::e($sub['icon'] ?? 'bi-dash') ?> me-2"></i>
                                    <?= ViewHelper::e($sub['label']) ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <?php $topLeafUrl = (string) ($item['url'] ?? ''); ?>
                        <a class="nav-link <?= str_starts_with($uri, $item['match'] ?? $item['url'] ?? '') ? 'active' : '' ?>"
                           href="<?= ViewHelper::e($topLeafUrl !== '' ? $topLeafUrl : '#') ?>">
                            <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                            <?= ViewHelper::e($item['label']) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <button class="btn btn-ghost topbar-btn" id="themeToggle" title="Tema">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <button class="btn btn-ghost topbar-btn" id="stylePanelBtn" title="Personalizar interfaz">
                    <i class="bi bi-palette"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-ghost topbar-btn d-flex align-items-center gap-2"
                            data-bs-toggle="dropdown">
                        <div class="topbar-avatar"><?= strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1)) ?></div>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header"><?= ViewHelper::e($usuario['nombreCompleto'] ?? '') ?></h6></li>
                        <li><a class="dropdown-item" href="/admin/ajustes"><i class="bi bi-gear me-2"></i>Ajustes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="/logout" class="m-0" onsubmit="return confirm('¿Cerrar sesión?');">
                                <?= ViewHelper::csrfField() ?>
                                <button type="submit" class="dropdown-item text-danger d-flex align-items-center w-100 border-0 bg-transparent text-start py-2 px-3">
                                    <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
