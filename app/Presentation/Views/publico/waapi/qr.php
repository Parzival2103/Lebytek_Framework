<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$phase = (string) ($phase ?? 'error');
$error = $error ?? null;
$qr = $qr ?? null;
$docsUrl = (string) ($docsUrl ?? 'https://docs.lebytek.com');
$statusUrl = $statusUrl ?? null;
?>
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <h1 class="h3 mb-4">Conectar WhatsApp</h1>

                <div id="wa-phase-scan" class="<?= $phase === 'awaiting_scan' ? '' : 'd-none' ?>">
                    <p class="text-muted mb-3">Escanea este código QR con WhatsApp en tu teléfono.</p>
                    <?php if (is_string($qr) && $qr !== ''): ?>
                        <img id="wa-qr" src="data:image/png;base64,<?= ViewHelper::e($qr) ?>" alt="Código QR de WhatsApp" class="img-fluid border rounded mb-3">
                    <?php endif; ?>
                </div>

                <div id="wa-phase-sync" class="<?= $phase === 'syncing' ? '' : 'd-none' ?>">
                    <div class="alert alert-info">Sincronizando con WhatsApp…</div>
                </div>

                <div id="wa-phase-ready" class="<?= $phase === 'ready' ? '' : 'd-none' ?>">
                    <div class="alert alert-success">WhatsApp conectado. Ya puedes usar la API.</div>
                </div>

                <div id="wa-phase-error" class="<?= $phase === 'error' ? '' : 'd-none' ?>">
                    <div class="alert alert-danger"><?= ViewHelper::e((string) ($error ?? 'No se pudo obtener el código QR.')) ?></div>
                </div>

                <a href="/portal/dashboard" class="btn btn-outline-secondary btn-sm me-2">Volver al panel</a>
                <a href="<?= ViewHelper::e($docsUrl) ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">Documentación</a>
            </div>
        </div>
    </div>
</section>

<?php if (is_string($statusUrl) && $statusUrl !== '' && $phase === 'awaiting_scan'): ?>
<script>
(function () {
    var statusUrl = <?= json_encode($statusUrl, JSON_UNESCAPED_SLASHES) ?>;
    function poll() {
        fetch(statusUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.phase === 'ready') {
                    document.getElementById('wa-phase-scan').classList.add('d-none');
                    document.getElementById('wa-phase-ready').classList.remove('d-none');
                    return;
                }
                setTimeout(poll, 5000);
            })
            .catch(function () { setTimeout(poll, 8000); });
    }
    setTimeout(poll, 5000);
})();
</script>
<?php endif; ?>
