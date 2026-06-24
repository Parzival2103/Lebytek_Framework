<?php

use App\Kernel\Constants\AppConstants;
use App\Kernel\Helpers\ViewHelper;

$nombreFooter = AppConstants::resolveEmpresaNombre($empresaNombre ?? null);
$mostrarPoweredBy = AppConstants::footerMuestraPoweredBy($empresaNombre ?? null);
?>
<footer class="app-footer px-4 py-2 mt-auto border-top">
    <span class="small text-muted">
        &copy; <?= date('Y') ?> <?= ViewHelper::e($nombreFooter) ?>.
        Todos los derechos reservados.
        <?php if ($mostrarPoweredBy): ?>
            <span class="ms-1">&middot; <?= ViewHelper::e(AppConstants::FOOTER_POWERED_BY) ?></span>
        <?php endif; ?>
    </span>
</footer>
