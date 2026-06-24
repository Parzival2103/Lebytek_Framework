<?php

declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$includeNavChrome = $includeNavChrome ?? true;
$lebytekCssVariables = is_array($lebytekCssVariables ?? null) ? $lebytekCssVariables : [];

?>
<style id="app-theme-vars">
    :root {
        <?php foreach ($lebytekCssVariables as $cssKey => $cssVal): ?>
        <?= ViewHelper::e((string) $cssKey) ?>: <?= ViewHelper::e((string) $cssVal) ?>;
        <?php endforeach; ?>
        --app-primary:        <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --app-primary-hover:  <?= ViewHelper::e($primaryHover ?? '#0b5ed7') ?>;
        --app-primary-active: <?= ViewHelper::e($primaryActive ?? '#0a58ca') ?>;
        --app-primary-subtle: <?= ViewHelper::e($primarySubtle ?? '#e8f0fe') ?>;
        --app-navbar-bg:      <?= ViewHelper::e($navbarColor ?? '#1a1d2e') ?>;
        --app-navbar-text:    <?= ViewHelper::e($navbarText ?? 'rgba(255,255,255,0.88)') ?>;
        --app-navbar-muted:   <?= ViewHelper::e($navbarTextMuted ?? 'rgba(255,255,255,0.45)') ?>;
        --app-navbar-sep:     <?= ViewHelper::e($navbarSeparator ?? 'rgba(255,255,255,0.1)') ?>;
        --app-body-bg:        <?= ViewHelper::e(($bodyBg ?? '') !== '' ? (string) $bodyBg : ($bodyColor ?? '#f0f2f5')) ?>;
    }

    :root {
        --bs-primary:          <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --bs-primary-rgb:      <?= ViewHelper::e($primaryRgb ?? '13, 110, 253') ?>;
        --bs-link-color:       <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --bs-link-color-rgb:   <?= ViewHelper::e($primaryRgb ?? '13, 110, 253') ?>;
        --bs-link-hover-color: <?= ViewHelper::e($primaryHover ?? '#0b5ed7') ?>;
    }

    .btn-primary {
        --bs-btn-bg:             <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --bs-btn-border-color:   <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --bs-btn-hover-bg:       <?= ViewHelper::e($primaryHover ?? '#0b5ed7') ?>;
        --bs-btn-hover-border-color: <?= ViewHelper::e($primaryHover ?? '#0b5ed7') ?>;
        --bs-btn-active-bg:      <?= ViewHelper::e($primaryActive ?? '#0a58ca') ?>;
        --bs-btn-active-border-color: <?= ViewHelper::e($primaryActive ?? '#0a58ca') ?>;
        --bs-btn-disabled-opacity: 1;
        --bs-btn-disabled-color: <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --bs-btn-disabled-bg:    <?= ViewHelper::e($primarySubtle ?? '#e8f0fe') ?>;
        --bs-btn-disabled-border-color: <?= ViewHelper::e($primarySubtle ?? '#e8f0fe') ?>;
    }
    .btn-outline-primary {
        --bs-btn-color:               <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --bs-btn-border-color:        <?= ViewHelper::e($primaryActive ?? '#0a58ca') ?>;
        --bs-btn-hover-color:         #fff;
        --bs-btn-hover-bg:            <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        --bs-btn-hover-border-color:  <?= ViewHelper::e($primaryActive ?? '#0a58ca') ?>;
        --bs-btn-active-bg:           <?= ViewHelper::e($primaryActive ?? '#0a58ca') ?>;
        --bs-btn-active-border-color: <?= ViewHelper::e($primaryActive ?? '#0a58ca') ?>;
        --bs-btn-focus-shadow-rgb:    <?= ViewHelper::e($primaryRgb ?? '13, 110, 253') ?>;
    }
    .form-control:focus,
    .form-select:focus {
        border-color: <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        box-shadow: 0 0 0 0.2rem rgba(<?= ViewHelper::e($primaryRgb ?? '13, 110, 253') ?>, 0.2);
    }
    .form-check-input:checked {
        background-color: <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        border-color:     <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
    }
    .pagination .page-item.active .page-link {
        background-color: <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
        border-color:     <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>;
    }
    .badge.bg-primary { background-color: <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?> !important; }
    .text-primary     { color: <?= ViewHelper::e($primaryColor ?? '#0d6efd') ?> !important; }

    <?php if (!($darkMode ?? false) && !empty($bodyBg)): ?>
    body { background-color: <?= ViewHelper::e((string) $bodyBg) ?> !important; }
    <?php endif; ?>

    <?php if ($includeNavChrome): ?>
    .sidebar, .topnav, .topbar, .bottomnav {
        background: <?= ViewHelper::e($navbarColor ?? '#1a1d2e') ?> !important;
    }
    .topbar,
    .topbar .btn-ghost,
    .topbar .topbar-toggle,
    .topbar .topbar-crumb-current {
        color: <?= ViewHelper::e($navbarText ?? 'rgba(255,255,255,0.88)') ?>;
    }
    .topbar .btn-ghost:hover {
        background: <?= ViewHelper::e($navbarSeparator ?? 'rgba(255,255,255,0.1)') ?> !important;
        color: <?= ViewHelper::e($navbarText ?? 'rgba(255,255,255,0.88)') ?>;
    }
    .topbar { border-bottom-color: <?= ViewHelper::e($navbarSeparator ?? 'rgba(255,255,255,0.1)') ?> !important; }
    <?php endif; ?>
</style>
