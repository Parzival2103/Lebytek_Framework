<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$demoPayload = is_array($demoPayload ?? null) ? $demoPayload : [];
$componentes = is_array($componentes ?? null) ? $componentes : [];
$columns = is_array($demoPayload['columns'] ?? null) ? $demoPayload['columns'] : [];
$rows = is_array($demoPayload['rows'] ?? null) ? $demoPayload['rows'] : [];
?>
<link rel="stylesheet" href="<?= ViewHelper::asset('css/lebytek-ui.css') ?>">

<style>
  .pdf-demo-preview {
    font-family: "DejaVu Sans", system-ui, sans-serif;
    font-size: 12px;
    color: #1a1a1a;
    background: #fff;
    border: 1px dashed #dee2e6;
    border-radius: 8px;
    padding: 1rem;
  }
  .pdf-demo-preview .pdf-h1 { font-size: 20px; margin: 0 0 2px; }
  .pdf-demo-preview .pdf-subtitle { color: #666; margin: 0 0 8px; }
  .pdf-demo-preview .pdf-text { margin: 4px 0; }
  .pdf-demo-preview .pdf-text-muted { color: #777; }
  .pdf-demo-preview .pdf-text-bold { font-weight: bold; }
  .pdf-demo-preview .pdf-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  .pdf-demo-preview .pdf-table th,
  .pdf-demo-preview .pdf-table td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
  .pdf-demo-preview .pdf-table th { background: #f2f2f2; }
  .pdf-demo-preview .pdf-indicator {
    display: inline-block; border: 1px solid #ddd; border-radius: 6px;
    padding: 8px 12px; margin: 4px 6px 4px 0;
  }
  .pdf-demo-preview .pdf-indicator-value { font-size: 18px; font-weight: bold; }
  .pdf-demo-preview .pdf-indicator-label { font-size: 10px; color: #666; text-transform: uppercase; }
  .pdf-demo-preview .pdf-totals { margin: 8px 0; }
  .pdf-demo-preview .pdf-totals-label { padding: 2px 10px 2px 0; color: #555; }
  .pdf-demo-preview .pdf-totals-value { padding: 2px 0; font-weight: bold; text-align: right; }
  .pdf-demo-preview .pdf-signatures { margin-top: 16px; }
  .pdf-demo-preview .pdf-signature {
    display: inline-block; width: 45%; margin: 0 2% 16px; text-align: center;
  }
  .pdf-demo-preview .pdf-signature-line { border-top: 1px solid #333; margin-bottom: 4px; }
  .pdf-demo-preview .pdf-signature-label { font-size: 10px; color: #555; }
  .pdf-demo-preview .pdf-footer {
    margin-top: 16px; padding-top: 6px; border-top: 1px solid #ddd;
    font-size: 10px; color: #888; text-align: center;
  }
  .pdf-demo-preview .pdf-pagebreak {
    border-top: 2px dotted #adb5bd; margin: 12px 0; padding-top: 8px;
    color: #6c757d; font-size: 11px;
  }
  .pdf-demo-preview .pdf-pagebreak::before { content: "— Salto de página —"; }
</style>

<div class="container-fluid px-3 px-lg-4 py-3 py-lg-4 ct-page">
    <header class="ct-page-header card border-0 shadow-sm ct-card mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center gap-3">
                <div>
                    <h1 class="ct-page-title h4 mb-1 d-inline-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                        <?= ViewHelper::e((string) ($titulo ?? 'Kit de PDF')) ?>
                    </h1>
                    <p class="text-muted mb-0 small"><?= ViewHelper::e((string) ($subtitulo ?? '')) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= ViewHelper::e((string) ($urlDescargaReporte ?? '#')) ?>"
                       class="btn btn-primary d-inline-flex align-items-center gap-2">
                        <i class="bi bi-download" aria-hidden="true"></i>
                        <span>PDF demo_reporte</span>
                    </a>
                    <a href="<?= ViewHelper::e((string) ($urlDescargaCompleto ?? '#')) ?>"
                       class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                        <i class="bi bi-collection" aria-hidden="true"></i>
                        <span>PDF todos los componentes</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <section class="card border-0 shadow-sm ct-card mb-4">
        <div class="card-header bg-transparent border-bottom">
            <h2 class="h6 mb-0">Plantilla <code><?= ViewHelper::e((string) ($plantillaClave ?? 'demo_reporte')) ?></code></h2>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">
                Payload de datos que consume <code>PdfRenderingService::renderTemplate()</code>.
                La estructura la define el programador; el kit no recibe HTML de usuario.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?= ViewHelper::e((string) ($col['label'] ?? '')) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="<?= max(1, count($columns)) ?>" class="text-muted">Sin filas demo.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $col): ?>
                                        <?php
                                        $name = (string) ($col['name'] ?? '');
                                        $val = $row[$name] ?? '';
                                        ?>
                                        <td><?= ViewHelper::e((string) $val) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm ct-card">
        <div class="card-header bg-transparent border-bottom">
            <h2 class="h6 mb-0">Biblioteca de componentes atómicos</h2>
        </div>
        <div class="card-body p-0">
            <div class="accordion accordion-flush" id="pdfDemoAccordion">
                <?php foreach ($componentes as $i => $comp): ?>
                    <?php
                    $slug = (string) ($comp['slug'] ?? ('c' . $i));
                    $collapseId = 'pdf-demo-' . $slug;
                    ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button<?= $i > 0 ? ' collapsed' : '' ?>"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#<?= ViewHelper::e($collapseId) ?>"
                                    aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"
                                    aria-controls="<?= ViewHelper::e($collapseId) ?>">
                                <span class="badge text-bg-secondary me-2"><?= ViewHelper::e((string) ($comp['type'] ?? '')) ?></span>
                                <?= ViewHelper::e((string) ($comp['label'] ?? '')) ?>
                            </button>
                        </h3>
                        <div id="<?= ViewHelper::e($collapseId) ?>"
                             class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>"
                             data-bs-parent="#pdfDemoAccordion">
                            <div class="accordion-body">
                                <p class="small text-muted"><?= ViewHelper::e((string) ($comp['descripcion'] ?? '')) ?></p>
                                <?php if (($comp['type'] ?? '') === 'logo'): ?>
                                    <p class="small mb-2">
                                        En el PDF el logo usa ruta local (<code>chroot</code>). Vista web equivalente:
                                    </p>
                                    <img src="<?= ViewHelper::asset('images/logo.png') ?>" height="40" alt="Logo demo" class="mb-2">
                                <?php endif; ?>
                                <div class="pdf-demo-preview" aria-label="Previsualización HTML del componente">
                                    <?= $comp['html'] ?? '' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
