<?php use App\Kernel\Helpers\ViewHelper; ?>
<footer class="app-footer px-4 py-2 mt-auto border-top">
    <span class="small text-muted">
        &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre ?? 'Sistema') ?>.
        Todos los derechos reservados.
    </span>
</footer>
