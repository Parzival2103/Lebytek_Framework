<?php
/** @var array $fuentes @var array $fuentesMeta @var \App\Domain\Reporte\ReporteGuardado|null $reporte */
use App\Kernel\Security\Csrf;

$editId = $reporte?->id();
$action = $editId ? '/admin/reportes/' . $editId : '/admin/reportes';
?>
<h1 class="h4 mb-3"><?= $editId ? 'Editar reporte' : 'Nuevo reporte' ?></h1>

<form id="reporte-form" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
  <?= Csrf::field() ?>

  <ol class="nav nav-pills gap-2 mb-4" id="wizard-steps">
    <li class="nav-item"><span class="nav-link active" data-step="1">1 · Plantilla</span></li>
    <li class="nav-item"><span class="nav-link" data-step="2">2 · Fuente</span></li>
    <li class="nav-item"><span class="nav-link" data-step="3">3 · Columnas</span></li>
    <li class="nav-item" data-step-tab="tratamientos"><span class="nav-link" data-step="4">4 · Tratamientos</span></li>
    <li class="nav-item"><span class="nav-link" data-step="5">5 · Filtros</span></li>
    <li class="nav-item" data-step-tab="periodo"><span class="nav-link" data-step="6">6 · Periodo</span></li>
    <li class="nav-item"><span class="nav-link" data-step="7">7 · Guardar</span></li>
  </ol>

  <div class="card"><div class="card-body" id="wizard-panes">
    <section data-pane="1">
      <h2 class="h6">Elige una plantilla</h2>
      <select class="form-select" id="campo-plantilla">
        <option value="tabla_estadistica"
          data-requiere-periodo="1" data-permite-tratamientos="1">Tabla estadística (colección)</option>
      </select>
      <p class="text-muted small mt-2">La plantilla decide qué pasos verás a continuación.</p>
    </section>

    <section data-pane="2" hidden>
      <h2 class="h6">Elige una fuente</h2>
      <select class="form-select" id="campo-fuente">
        <option value="">— Selecciona —</option>
        <?php foreach ($fuentes as $key => $title): ?>
          <option value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </section>

    <section data-pane="3" hidden>
      <h2 class="h6">Columnas</h2>
      <div id="lista-columnas" class="text-muted small">Selecciona una fuente para ver sus columnas.</div>
    </section>

    <section data-pane="4" hidden>
      <h2 class="h6">Tratamientos</h2>
      <div class="mb-2">
        <label class="form-label">Agrupar por</label>
        <select class="form-select" id="campo-group-by" multiple size="3"></select>
      </div>
      <div class="form-check">
        <input type="checkbox" class="form-check-input" id="campo-count" checked>
        <label class="form-check-label" for="campo-count">Contar filas por grupo</label>
      </div>
    </section>

    <section data-pane="5" hidden>
      <h2 class="h6">Filtros</h2>
      <div id="lista-filtros" class="text-muted small">Filtros declarados por la fuente.</div>
    </section>

    <section data-pane="6" hidden>
      <h2 class="h6">Periodo</h2>
      <select class="form-select" id="campo-periodo">
        <option value="todo">Todo</option>
        <option value="hoy">Hoy</option>
        <option value="semana">Esta semana</option>
        <option value="mes">Este mes</option>
        <option value="anio">Este año</option>
        <option value="ayer">Ayer</option>
        <option value="mes_pasado">Mes pasado</option>
        <option value="anio_pasado">Año pasado</option>
      </select>
    </section>

    <section data-pane="7" hidden>
      <h2 class="h6">Detalles</h2>
      <div class="mb-2"><label class="form-label">Nombre</label>
        <input type="text" class="form-control" id="campo-nombre" value="<?= htmlspecialchars((string) ($reporte?->nombre() ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="mb-2"><label class="form-label">Título del documento</label>
        <input type="text" class="form-control" id="campo-titulo"></div>
      <div class="mb-2"><label class="form-label">Orientación</label>
        <select class="form-select" id="campo-orientacion"><option value="portrait">Vertical</option><option value="landscape">Horizontal</option></select></div>
      <div class="form-check"><input type="checkbox" class="form-check-input" id="campo-compartido"><label class="form-check-label">Compartir con otros usuarios</label></div>
    </section>
  </div></div>

  <input type="hidden" name="fuente_key" id="hidden-fuente">
  <input type="hidden" name="template_key" id="hidden-template">
  <input type="hidden" name="nombre" id="hidden-nombre">
  <input type="hidden" name="columnas" id="hidden-columnas">
  <input type="hidden" name="tratamientos" id="hidden-tratamientos">
  <input type="hidden" name="filtros" id="hidden-filtros">
  <input type="hidden" name="periodo" id="hidden-periodo">
  <input type="hidden" name="opciones" id="hidden-opciones">
  <input type="hidden" name="compartido" id="hidden-compartido" value="0">

  <div class="d-flex justify-content-between mt-3">
    <button type="button" class="btn btn-outline-secondary" id="btn-atras">← Atrás</button>
    <button type="button" class="btn btn-primary" id="btn-siguiente">Siguiente →</button>
    <button type="submit" class="btn btn-success" id="btn-guardar" hidden>Guardar reporte</button>
  </div>
</form>

<script type="application/json" id="reporte-precarga">
<?= json_encode([
    'fuente_key'   => $reporte?->fuenteKey(),
    'template_key' => $reporte?->templateKey(),
    'columnas'     => $reporte?->columnas() ?? [],
    'tratamientos' => $reporte?->tratamientos() ?? new stdClass(),
    'filtros'      => $reporte?->filtros() ?? new stdClass(),
    'periodo'      => $reporte?->periodo() ?? ['preset' => 'todo'],
    'opciones'     => $reporte?->opciones() ?? new stdClass(),
    'compartido'   => $reporte?->compartido() ?? false,
], JSON_UNESCAPED_UNICODE) ?>
</script>

<script type="application/json" id="fuentes-meta">
<?= json_encode($fuentesMeta ?? [], JSON_UNESCAPED_UNICODE) ?>
</script>

<script src="/assets/js/reportes-builder.js" defer></script>
