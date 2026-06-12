<?php

use App\Kernel\Helpers\ViewHelper;

$c = $configuracion ?? [];
$uiLayoutWidth = $c['ui_layout_width'] ?? 'fluid';
$uiContentDensity = $c['ui_content_density'] ?? 'comfortable';
$uiCardStyle = $c['ui_card_style'] ?? 'soft';
$uiTableDensity = $c['ui_table_density'] ?? 'normal';
$uiEnableAnimations = !isset($c['ui_enable_animations']) || $c['ui_enable_animations'] !== '0';
$themeBorderRadius = $c['theme_border_radius'] ?? 'md';
$themeShadowLevel = isset($c['theme_shadow_level']) ? (string) $c['theme_shadow_level'] : '1';

?>
<div class="ct-page">
<div class="row g-4">
    <!-- Formulario principal -->
    <div class="col-12 col-xl-8">
        <form method="POST" action="/admin/ajustes" id="ajustesForm">
            <?= ViewHelper::csrfField() ?>

            <div class="accordion ct-ajustes-accordion" id="ajustesAccordion">

                <?php ob_start(); ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="empresa_nombre" class="form-label fw-medium small">Nombre de la empresa</label>
                            <input type="text" id="empresa_nombre" name="empresa_nombre"
                                   class="form-control"
                                   value="<?= ViewHelper::e($configuracion['empresa_nombre'] ?? '') ?>"
                                   placeholder="Mi Empresa S.A. de C.V.">
                        </div>
                        <div class="col-12">
                            <label for="empresa_logo" class="form-label fw-medium small">URL del logotipo</label>
                            <input type="url" id="empresa_logo" name="empresa_logo"
                                   class="form-control"
                                   value="<?= ViewHelper::e($configuracion['empresa_logo'] ?? '') ?>"
                                   placeholder="https://...">
                            <div class="form-text">URL pública de la imagen del logo (PNG, SVG recomendado). También se usa como icono al instalar la app (PWA) y en la pantalla de inicio de sesión.</div>
                        </div>
                    </div>
                <?php $bodyEmpresa = ob_get_clean(); ?>
                <?= ViewHelper::partial('admin/ajustes_accordion_item', [
                    'collapseId'       => 'ajustesCollapseEmpresa',
                    'headingId'        => 'ajustesHeadingEmpresa',
                    'title'            => 'Información de la empresa',
                    'iconClass'        => 'bi-building',
                    'bodyHtml'         => $bodyEmpresa,
                ]) ?>

                <?php ob_start(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="menu_layout" class="form-label fw-medium small">Posición del menú</label>
                            <select id="menu_layout" name="menu_layout" class="form-select">
                                <option value="side"   <?= ($configuracion['menu_layout'] ?? '') === 'side'   ? 'selected' : '' ?>>Lateral (sidebar)</option>
                                <option value="top"    <?= ($configuracion['menu_layout'] ?? '') === 'top'    ? 'selected' : '' ?>>Superior (topbar)</option>
                                <option value="bottom" <?= ($configuracion['menu_layout'] ?? '') === 'bottom' ? 'selected' : '' ?>>Inferior (bottombar)</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" name="dark_mode" id="dark_mode"
                                       value="1" <?= !empty($configuracion['dark_mode']) && $configuracion['dark_mode'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-medium small" for="dark_mode">
                                    Modo oscuro
                                </label>
                            </div>
                        </div>
                    </div>
                <?php $bodyLayout = ob_get_clean(); ?>
                <?= ViewHelper::partial('admin/ajustes_accordion_item', [
                    'collapseId' => 'ajustesCollapseLayout',
                    'headingId'  => 'ajustesHeadingLayout',
                    'title'      => 'Layout y tema',
                    'iconClass'  => 'bi-layout-sidebar',
                    'bodyHtml'   => $bodyLayout,
                ]) ?>

                <?php ob_start(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="ui_layout_width" class="form-label fw-medium small">Ancho del contenido</label>
                            <select id="ui_layout_width" name="ui_layout_width" class="form-select">
                                <option value="fluid" <?= $uiLayoutWidth === 'fluid' ? 'selected' : '' ?>>Fluido (ancho completo)</option>
                                <option value="boxed" <?= $uiLayoutWidth === 'boxed' ? 'selected' : '' ?>>Caja (máx. ~1320px centrado)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="ui_content_density" class="form-label fw-medium small">Densidad del contenido</label>
                            <select id="ui_content_density" name="ui_content_density" class="form-select">
                                <option value="comfortable" <?= $uiContentDensity === 'comfortable' ? 'selected' : '' ?>>Cómoda</option>
                                <option value="compact" <?= $uiContentDensity === 'compact' ? 'selected' : '' ?>>Compacta</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="ui_card_style" class="form-label fw-medium small">Estilo de tarjetas</label>
                            <select id="ui_card_style" name="ui_card_style" class="form-select">
                                <option value="soft" <?= $uiCardStyle === 'soft' ? 'selected' : '' ?>>Suave (sombra ligera)</option>
                                <option value="bordered" <?= $uiCardStyle === 'bordered' ? 'selected' : '' ?>>Con borde</option>
                                <option value="flat" <?= $uiCardStyle === 'flat' ? 'selected' : '' ?>>Plana</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="ui_table_density" class="form-label fw-medium small">Densidad de tablas (CRUD)</label>
                            <select id="ui_table_density" name="ui_table_density" class="form-select">
                                <option value="normal" <?= $uiTableDensity === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="compact" <?= $uiTableDensity === 'compact' ? 'selected' : '' ?>>Compacta (<code class="small">table-sm</code> por defecto)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="theme_border_radius" class="form-label fw-medium small">Redondeo de bordes</label>
                            <select id="theme_border_radius" name="theme_border_radius" class="form-select">
                                <option value="sm" <?= $themeBorderRadius === 'sm' ? 'selected' : '' ?>>Pequeño</option>
                                <option value="md" <?= $themeBorderRadius === 'md' ? 'selected' : '' ?>>Medio (predeterminado)</option>
                                <option value="lg" <?= $themeBorderRadius === 'lg' ? 'selected' : '' ?>>Grande</option>
                                <option value="xl" <?= $themeBorderRadius === 'xl' ? 'selected' : '' ?>>Extra</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="theme_shadow_level" class="form-label fw-medium small">Sombra en tarjetas</label>
                            <select id="theme_shadow_level" name="theme_shadow_level" class="form-select">
                                <option value="0" <?= $themeShadowLevel === '0' ? 'selected' : '' ?>>Ninguna</option>
                                <option value="1" <?= $themeShadowLevel === '1' ? 'selected' : '' ?>>Sutil</option>
                                <option value="2" <?= $themeShadowLevel === '2' ? 'selected' : '' ?>>Media</option>
                                <option value="3" <?= $themeShadowLevel === '3' ? 'selected' : '' ?>>Marcada</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ui_enable_animations" id="ui_enable_animations"
                                       value="1" <?= $uiEnableAnimations ? 'checked' : '' ?>>
                                <label class="form-check-label fw-medium small" for="ui_enable_animations">
                                    Animaciones y transiciones (hover, focus, cards)
                                </label>
                            </div>
                        </div>
                    </div>
                <?php $bodyLebytek = ob_get_clean(); ?>
                <?= ViewHelper::partial('admin/ajustes_accordion_item', [
                    'collapseId'    => 'ajustesCollapseLebytekUi',
                    'headingId'     => 'ajustesHeadingLebytekUi',
                    'title'         => 'Interfaz',
                    'iconClass'     => 'bi-sliders',
                    'subtitleHtml'  => '',
                    'bodyHtml'      => $bodyLebytek,
                ]) ?>

                <?php ob_start(); ?>
                    <div class="row g-4">

                        <!-- Color primario -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium small d-block mb-2">
                                Color primario
                                <span class="text-muted">(botones, links, badges)</span>
                            </label>
                            <div class="color-picker-group">
                                <div class="color-preview-swatch mb-2" id="preview_primary"
                                     style="background:<?= ViewHelper::e($configuracion['primary_color'] ?? '#0d6efd') ?>"></div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="color" id="primary_color" name="primary_color"
                                           class="form-control form-control-color flex-shrink-0"
                                           value="<?= ViewHelper::e($configuracion['primary_color'] ?? '#0d6efd') ?>"
                                           data-css-var="--app-primary"
                                           data-preview="preview_primary">
                                    <input type="text" id="primary_color_hex" class="form-control form-control-sm font-monospace"
                                           value="<?= ViewHelper::e($configuracion['primary_color'] ?? '#0d6efd') ?>"
                                           maxlength="7" pattern="#[0-9a-fA-F]{6}"
                                           data-sync="primary_color">
                                </div>
                                <div class="mt-2 d-flex gap-1 flex-wrap" id="primary_presets"></div>
                            </div>
                        </div>

                        <!-- Color del navbar -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium small d-block mb-2">
                                Color del navbar
                                <span class="text-muted">(sidebar, topbar)</span>
                            </label>
                            <div class="color-picker-group">
                                <div class="color-preview-swatch mb-2" id="preview_navbar"
                                     style="background:<?= ViewHelper::e($configuracion['navbar_color'] ?? '#1a1d2e') ?>"></div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="color" id="navbar_color" name="navbar_color"
                                           class="form-control form-control-color flex-shrink-0"
                                           value="<?= ViewHelper::e($configuracion['navbar_color'] ?? '#1a1d2e') ?>"
                                           data-css-var="--app-navbar-bg"
                                           data-preview="preview_navbar">
                                    <input type="text" id="navbar_color_hex" class="form-control form-control-sm font-monospace"
                                           value="<?= ViewHelper::e($configuracion['navbar_color'] ?? '#1a1d2e') ?>"
                                           maxlength="7" pattern="#[0-9a-fA-F]{6}"
                                           data-sync="navbar_color">
                                </div>
                                <div class="mt-2 d-flex gap-1 flex-wrap" id="navbar_presets"></div>
                            </div>
                        </div>

                        <!-- Color del fondo body -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium small d-block mb-2">
                                Color del fondo
                                <span class="text-muted">(área de contenido)</span>
                            </label>
                            <div class="color-picker-group">
                                <div class="color-preview-swatch mb-2" id="preview_body"
                                     style="background:<?= ViewHelper::e($c['body_color'] ?: '#f0f2f5') ?>"></div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="color" id="body_color" name="body_color"
                                           class="form-control form-control-color flex-shrink-0"
                                           value="<?= ViewHelper::e($c['body_color'] ?: '#f0f2f5') ?>"
                                           data-css-var="--app-body-bg"
                                           data-preview="preview_body">
                                    <input type="text" id="body_color_hex" class="form-control form-control-sm font-monospace"
                                           value="<?= ViewHelper::e($c['body_color'] ?: '#f0f2f5') ?>"
                                           maxlength="7" pattern="#[0-9a-fA-F]{6}"
                                           data-sync="body_color">
                                </div>
                                <div class="mt-2 d-flex gap-1 flex-wrap" id="body_presets"></div>
                            </div>
                        </div>

                    </div>
                <?php $bodyColores = ob_get_clean(); ?>
                <?= ViewHelper::partial('admin/ajustes_accordion_item', [
                    'collapseId'     => 'ajustesCollapseColores',
                    'headingId'      => 'ajustesHeadingColores',
                    'title'          => 'Colores del sistema',
                    'iconClass'      => 'bi-palette',
                    'titleExtraHtml' => '',
                    'bodyHtml'       => $bodyColores,
                ]) ?>

                <?php ob_start(); ?>
                    <p class="small text-muted mb-0">Contenido pendiente. Aquí se podrán añadir preferencias del panel principal (KPIs, enlaces rápidos, etc.) sin duplicar el marcado del acordeón.</p>
                <?php $bodyDashboard = ob_get_clean(); ?>
                <?= ViewHelper::partial('admin/ajustes_accordion_item', [
                    'collapseId' => 'ajustesCollapseDashboard',
                    'headingId'  => 'ajustesHeadingDashboard',
                    'title'      => 'Dashboard',
                    'subtitle'   => '',
                    'iconClass'  => 'bi-speedometer2',
                    'bodyHtml'   => $bodyDashboard,
                ]) ?>

                <?php ob_start(); ?>
                    <p class="small text-muted mb-0">Contenido pendiente. Aquí se podrán añadir opciones visuales de la pantalla de inicio de sesión sin duplicar el marcado del acordeón.</p>
                <?php $bodyLogin = ob_get_clean(); ?>
                <?= ViewHelper::partial('admin/ajustes_accordion_item', [
                    'collapseId' => 'ajustesCollapseLogin',
                    'headingId'  => 'ajustesHeadingLogin',
                    'title'      => 'Login',
                    'subtitle'   => '',
                    'iconClass'  => 'bi-box-arrow-in-right',
                    'bodyHtml'   => $bodyLogin,
                ]) ?>

            </div>

            <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-2"></i>Guardar ajustes
                </button>
                <button type="button" class="btn btn-outline-secondary" id="resetColores">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restablecer colores
                </button>
            </div>
        </form>
    </div>

    <!-- Panel lateral informativo -->
    <div class="col-12 col-xl-4">
        <!-- Preview del navbar -->
        <div class="card ct-card mb-4 overflow-hidden">
            <div class="ct-card-header d-flex align-items-center gap-2">
                <i class="bi bi-eye text-primary" aria-hidden="true"></i>
                <span class="ct-card-title">Vista previa del navbar</span>
            </div>
            <div class="card-body p-0">
                <div id="navbarPreviewBox" class="ct-navbar-preview-box p-3 d-flex align-items-center gap-3 rounded-bottom">
                    <div class="ct-navbar-preview-icon">
                        <i class="bi bi-grid-3x3-gap-fill text-white" aria-hidden="true"></i>
                    </div>
                    <span class="ct-navbar-preview-title">Sistema</span>
                    <div class="ms-auto d-flex gap-2">
                        <div class="ct-navbar-preview-dot ct-navbar-preview-dot--muted" aria-hidden="true"></div>
                        <div class="ct-navbar-preview-dot ct-navbar-preview-dot--accent" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info -->
        <div class="card ct-card bg-body-tertiary">
            <div class="card-body p-3 p-md-4">
                <h6 class="fw-semibold mb-3">
                    <i class="bi bi-info-circle me-2 text-primary" aria-hidden="true"></i>Acerca de los ajustes
                </h6>
                <p class="small text-muted mb-3">
                    Los colores se pueden previsualizar en tiempo real. Al guardar se aplican de forma permanente para todos los usuarios.
                </p>
                <hr>
                <div class="small text-muted">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Versión del sistema</span>
                        <strong>1.0.0</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>PHP</span>
                        <strong><?= phpversion() ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
