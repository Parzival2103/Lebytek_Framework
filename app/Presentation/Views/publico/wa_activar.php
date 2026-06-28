<?php

use App\Kernel\Helpers\ViewHelper;

$phase = (string) ($phase ?? 'error');
$message = $message ?? null;
$qr = $qr ?? null;
$qrUrl = $qr_url ?? null;
$state = $state ?? null;
$apiDocsUrl = (string) ($api_docs_url ?? '/docs/integraciones/whatsapp-api');
$statusUrl = $status_url ?? null;
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <h1 class="h3 mb-4">Activar WhatsApp</h1>

            <div id="wa-phase-scan" class="<?= $phase === 'awaiting_scan' ? '' : 'd-none' ?>">
                <p class="text-muted mb-3">Escanea este código QR con WhatsApp en tu teléfono.</p>
                <p class="small text-muted mb-3">El código se actualiza cada 20 segundos; la página lo refrescará automáticamente.</p>
                <?php if (is_string($qr) && $qr !== ''): ?>
                    <img id="wa-qr" src="data:image/png;base64,<?= ViewHelper::e($qr) ?>" alt="Código QR de WhatsApp" class="img-fluid border rounded">
                <?php elseif (is_string($qrUrl) && $qrUrl !== ''): ?>
                    <img id="wa-qr" src="<?= ViewHelper::e($qrUrl) ?>" alt="Código QR de WhatsApp" class="img-fluid border rounded">
                <?php endif; ?>
            </div>

            <div id="wa-phase-sync" class="<?= $phase === 'syncing' ? '' : 'd-none' ?>">
                <div class="alert alert-info text-start">
                    <div class="d-flex align-items-start gap-3">
                        <div class="spinner-border text-info flex-shrink-0 mt-1" role="status" aria-hidden="true"></div>
                        <div>
                            <p class="fw-semibold mb-2">Código escaneado correctamente</p>
                            <p id="wa-sync-message" class="mb-2"><?= ViewHelper::e((string) ($message ?? 'Tu instancia se está sincronizando con WhatsApp; esto puede tardar unos segundos.')) ?></p>
                            <p class="mb-0 small text-muted">No cierres esta página hasta que la conexión esté lista.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="wa-phase-ready" class="<?= $phase === 'ready' ? '' : 'd-none' ?>">
                <div class="alert alert-success text-start">
                    <p class="fw-semibold mb-2"><i class="bi bi-check-circle-fill me-1"></i> WhatsApp conectado</p>
                    <p id="wa-ready-message" class="mb-2"><?= ViewHelper::e((string) ($message ?? 'Tu instancia ya está autorizada y lista para usar la API.')) ?></p>
                    <p class="mb-0"><a id="wa-docs-link-ready" href="<?= ViewHelper::e($apiDocsUrl) ?>" class="alert-link">Consulta la documentación de la API</a> para integrar tu sistema.</p>
                </div>
            </div>

            <div id="wa-phase-error" class="<?= $phase === 'error' ? '' : 'd-none' ?>">
                <div class="alert alert-danger"><?= ViewHelper::e((string) ($message ?? 'No se pudo obtener el código QR. Intenta más tarde.')) ?></div>
            </div>

            <div id="wa-docs-sync" class="mt-3 <?= in_array($phase, ['syncing', 'ready'], true) ? '' : 'd-none' ?>">
                <a id="wa-docs-link-sync" href="<?= ViewHelper::e($apiDocsUrl) ?>" class="btn btn-outline-primary btn-sm">Documentación de la API</a>
            </div>
        </div>
    </div>
</div>

<?php if (is_string($statusUrl) && $statusUrl !== ''): ?>
<script>
(function () {
    var statusUrl = <?= json_encode($statusUrl, JSON_UNESCAPED_SLASHES) ?>;
    var phases = {
        scan: document.getElementById('wa-phase-scan'),
        sync: document.getElementById('wa-phase-sync'),
        ready: document.getElementById('wa-phase-ready'),
        error: document.getElementById('wa-phase-error'),
        docsSync: document.getElementById('wa-docs-sync')
    };
    var qrImg = document.getElementById('wa-qr');
    var qrBase = <?= json_encode(is_string($qrUrl) ? $qrUrl : '', JSON_UNESCAPED_SLASHES) ?>;

    function showPhase(phase) {
        if (phases.scan) phases.scan.classList.toggle('d-none', phase !== 'awaiting_scan');
        if (phases.sync) phases.sync.classList.toggle('d-none', phase !== 'syncing');
        if (phases.ready) phases.ready.classList.toggle('d-none', phase !== 'ready');
        if (phases.error) phases.error.classList.toggle('d-none', phase !== 'error');
        if (phases.docsSync) phases.docsSync.classList.toggle('d-none', phase !== 'syncing' && phase !== 'ready');
    }

    function applyPayload(data) {
        if (!data || typeof data.phase !== 'string') return;
        showPhase(data.phase);
        if (data.phase === 'syncing') {
            var syncMsg = document.getElementById('wa-sync-message');
            if (syncMsg && data.message) syncMsg.textContent = data.message;
        }
        if (data.phase === 'ready') {
            var readyMsg = document.getElementById('wa-ready-message');
            if (readyMsg && data.message) readyMsg.textContent = data.message;
        }
        if (data.docs_url) {
            ['wa-docs-link-sync', 'wa-docs-link-ready'].forEach(function (id) {
                var link = document.getElementById(id);
                if (link) link.href = data.docs_url;
            });
        }
        if (data.phase === 'awaiting_scan' && qrImg && data.qr_base64) {
            qrImg.src = 'data:image/png;base64,' + data.qr_base64;
        }
    }

    function pollStatus() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(applyPayload)
            .catch(function () {});
    }

    if (qrImg && qrBase) {
        setInterval(function () {
            if (phases.sync && !phases.sync.classList.contains('d-none')) return;
            qrImg.src = qrBase + (qrBase.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
        }, 20000);
        qrImg.addEventListener('error', pollStatus);
    }

    setInterval(pollStatus, 4000);
})();
</script>
<?php endif; ?>
