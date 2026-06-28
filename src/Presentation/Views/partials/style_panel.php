<?php use App\Kernel\Helpers\ViewHelper; ?>

<!-- Overlay del panel de estilos -->
<div id="stylePanelOverlay"></div>

<!-- Panel de ajuste de estilos (drawer flotante) -->
<aside id="stylePanel" role="dialog" aria-modal="true" aria-label="Personalización de interfaz">

    <div class="style-panel-header">
        <span><i class="bi bi-palette me-2"></i>Personalizar</span>
        <button id="stylePanelClose" class="btn btn-ghost p-1" aria-label="Cerrar panel">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="style-panel-body">

        <!-- Modo oscuro -->
        <div>
            <p class="style-panel-section-title">Apariencia</p>
            <div class="d-flex align-items-center justify-content-between">
                <span class="small fw-medium">Modo oscuro</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="panelDarkToggle" role="switch">
                </div>
            </div>
        </div>

        <!-- Color primario -->
        <div>
            <p class="style-panel-section-title mb-2">Color primario</p>
            <div class="style-panel-presets" id="panelPrimaryPresets"></div>
        </div>

        <!-- Color del navbar -->
        <div>
            <p class="style-panel-section-title mb-2">Color del navbar</p>
            <div class="style-panel-presets" id="panelNavbarPresets"></div>
        </div>

        <!-- Layout del menú -->
        <div>
            <p class="style-panel-section-title mb-2">Posición del menú</p>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="layout-option" data-layout="side">
                    <i class="bi bi-layout-sidebar"></i>
                    Lateral
                </button>
                <button type="button" class="layout-option" data-layout="top">
                    <i class="bi bi-layout-text-window-reverse"></i>
                    Superior
                </button>
                <button type="button" class="layout-option" data-layout="bottom">
                    <i class="bi bi-layout-sidebar-reverse"></i>
                    Inferior
                </button>
            </div>
            <p class="small text-muted mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Para guardar el layout permanentemente, usa los
                <a href="/admin/ajustes" class="text-decoration-none">Ajustes del sistema</a>.
            </p>
        </div>

    </div>

    <div class="style-panel-footer">
        <a href="/admin/ajustes" class="btn btn-primary w-100 btn-sm">
            <i class="bi bi-gear me-2"></i>Ajustes completos
        </a>
    </div>

</aside>
