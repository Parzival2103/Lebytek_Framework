(function () {
  'use strict';

  var form = document.getElementById('reporte-form');
  if (!form) return;

  var stepsNav = document.getElementById('wizard-steps');
  var panes = document.querySelectorAll('#wizard-panes [data-pane]');
  var btnAtras = document.getElementById('btn-atras');
  var btnSiguiente = document.getElementById('btn-siguiente');
  var btnGuardar = document.getElementById('btn-guardar');

  var plantilla = document.getElementById('campo-plantilla');
  var fuente = document.getElementById('campo-fuente');
  var listaColumnas = document.getElementById('lista-columnas');
  var listaFiltros = document.getElementById('lista-filtros');
  var groupBy = document.getElementById('campo-group-by');
  var campoCount = document.getElementById('campo-count');
  var campoPeriodo = document.getElementById('campo-periodo');
  var campoNombre = document.getElementById('campo-nombre');
  var campoTitulo = document.getElementById('campo-titulo');
  var campoOrientacion = document.getElementById('campo-orientacion');
  var campoCompartido = document.getElementById('campo-compartido');

  var precarga = {};
  try {
    precarga = JSON.parse(document.getElementById('reporte-precarga').textContent || '{}');
  } catch (e) { precarga = {}; }

  var fuentesMeta = {};
  try {
    fuentesMeta = JSON.parse(document.getElementById('fuentes-meta').textContent || '{}');
  } catch (e) { fuentesMeta = {}; }

  var stepOrder = [1, 2, 3, 4, 5, 6, 7];
  var currentIdx = 0;
  var selectedColumns = [];

  function plantillaOpts() {
    var opt = plantilla.selectedOptions[0];
    return {
      requierePeriodo: opt && opt.dataset.requierePeriodo === '1',
      permiteTratamientos: opt && opt.dataset.permiteTratamientos === '1'
    };
  }

  function visibleSteps() {
    var o = plantillaOpts();
    return stepOrder.filter(function (s) {
      if (s === 4 && !o.permiteTratamientos) return false;
      if (s === 6 && !o.requierePeriodo) return false;
      return true;
    });
  }

  function updateTabVisibility() {
    var o = plantillaOpts();
    stepsNav.querySelectorAll('[data-step-tab="tratamientos"]').forEach(function (el) {
      el.hidden = !o.permiteTratamientos;
    });
    stepsNav.querySelectorAll('[data-step-tab="periodo"]').forEach(function (el) {
      el.hidden = !o.requierePeriodo;
    });
  }

  function showStep(idx) {
    var vis = visibleSteps();
    currentIdx = Math.max(0, Math.min(idx, vis.length - 1));
    var step = vis[currentIdx];

    panes.forEach(function (pane) {
      pane.hidden = parseInt(pane.dataset.pane, 10) !== step;
    });

    stepsNav.querySelectorAll('[data-step]').forEach(function (el) {
      var n = parseInt(el.dataset.step, 10);
      el.classList.toggle('active', n === step);
    });

    btnAtras.hidden = currentIdx === 0;
    btnSiguiente.hidden = currentIdx >= vis.length - 1;
    btnGuardar.hidden = currentIdx < vis.length - 1;
  }

  function renderColumnas(meta) {
    if (!meta || !meta.columns || !meta.columns.length) {
      listaColumnas.innerHTML = '<span class="text-muted">Sin columnas disponibles.</span>';
      return;
    }
    var html = '';
    meta.columns.forEach(function (c) {
      var checked = selectedColumns.indexOf(c.name) >= 0 ? ' checked' : '';
      html += '<div class="form-check"><input class="form-check-input col-check" type="checkbox" value="' +
        c.name + '" id="col-' + c.name + '"' + checked + '>' +
        '<label class="form-check-label" for="col-' + c.name + '">' + (c.label || c.name) + '</label></div>';
    });
    listaColumnas.innerHTML = html;
    listaColumnas.querySelectorAll('.col-check').forEach(function (cb) {
      cb.addEventListener('change', syncSelectedColumns);
    });
  }

  function syncSelectedColumns() {
    selectedColumns = [];
    listaColumnas.querySelectorAll('.col-check:checked').forEach(function (cb) {
      selectedColumns.push(cb.value);
    });
  }

  function renderGroupBy(meta) {
    groupBy.innerHTML = '';
    if (!meta || !meta.groupBy) return;
    meta.groupBy.forEach(function (g) {
      var opt = document.createElement('option');
      opt.value = g;
      opt.textContent = g;
      groupBy.appendChild(opt);
    });
  }

  function renderFiltros(meta) {
    if (!meta || !meta.filters || !Object.keys(meta.filters).length) {
      listaFiltros.innerHTML = '<span class="text-muted">Esta fuente no declara filtros.</span>';
      return;
    }
    var html = '';
    Object.keys(meta.filters).forEach(function (field) {
      html += '<div class="mb-2"><label class="form-label">' + meta.filters[field] +
        '</label><input type="text" class="form-control filtro-field" data-field="' +
        field + '"></div>';
    });
    listaFiltros.innerHTML = html;
  }

  function onFuenteChange() {
    var key = fuente.value;
    var meta = fuentesMeta[key];
    if (!meta) {
      listaColumnas.innerHTML = '<span class="text-muted">Selecciona una fuente.</span>';
      return;
    }
    if (!selectedColumns.length && meta.columns.length) {
      selectedColumns = meta.columns.map(function (c) { return c.name; });
    }
    renderColumnas(meta);
    renderGroupBy(meta);
    renderFiltros(meta);

    if (meta.periodPresets && meta.periodPresets.length) {
      Array.from(campoPeriodo.options).forEach(function (opt) {
        opt.hidden = meta.periodPresets.indexOf(opt.value) < 0;
      });
    }
  }

  function collectFiltros() {
    var out = {};
    listaFiltros.querySelectorAll('.filtro-field').forEach(function (input) {
      if (input.value.trim() !== '') {
        out[input.dataset.field] = input.value.trim();
      }
    });
    return out;
  }

  function collectTratamientos() {
    var groups = [];
    Array.from(groupBy.selectedOptions).forEach(function (opt) {
      groups.push(opt.value);
    });
    var aggs = [];
    if (campoCount.checked) {
      aggs.push({ op: 'count', column: '' });
    }
    var orderBy = groups[0] || '';
    return {
      group_by: groups,
      aggregations: aggs,
      order: orderBy ? { by: orderBy, dir: 'asc' } : null
    };
  }

  function serializeHidden() {
    document.getElementById('hidden-fuente').value = fuente.value;
    document.getElementById('hidden-template').value = plantilla.value;
    document.getElementById('hidden-nombre').value = campoNombre.value.trim();
    document.getElementById('hidden-columnas').value = JSON.stringify(
      selectedColumns.map(function (n) { return { name: n }; })
    );
    document.getElementById('hidden-tratamientos').value = JSON.stringify(collectTratamientos());
    document.getElementById('hidden-filtros').value = JSON.stringify(collectFiltros());
    document.getElementById('hidden-periodo').value = JSON.stringify({ preset: campoPeriodo.value });
    document.getElementById('hidden-opciones').value = JSON.stringify({
      titulo: campoTitulo.value.trim() || campoNombre.value.trim(),
      orientacion: campoOrientacion.value
    });
    document.getElementById('hidden-compartido').value = campoCompartido.checked ? '1' : '0';
  }

  function applyPrecarga() {
    if (precarga.template_key) plantilla.value = precarga.template_key;
    if (precarga.fuente_key) fuente.value = precarga.fuente_key;
    if (precarga.columnas && precarga.columnas.length) {
      selectedColumns = precarga.columnas.map(function (c) { return c.name; });
    }
    if (precarga.periodo && precarga.periodo.preset) campoPeriodo.value = precarga.periodo.preset;
    if (precarga.opciones) {
      if (precarga.opciones.titulo) campoTitulo.value = precarga.opciones.titulo;
      if (precarga.opciones.orientacion) campoOrientacion.value = precarga.opciones.orientacion;
    }
    if (precarga.compartido) campoCompartido.checked = true;
    updateTabVisibility();
    onFuenteChange();
    if (precarga.tratamientos) {
      if (precarga.tratamientos.group_by) {
        Array.from(groupBy.options).forEach(function (opt) {
          opt.selected = precarga.tratamientos.group_by.indexOf(opt.value) >= 0;
        });
      }
      if (precarga.tratamientos.aggregations) {
        campoCount.checked = precarga.tratamientos.aggregations.some(function (a) {
          return a.op === 'count';
        });
      }
    }
    if (precarga.filtros) {
      Object.keys(precarga.filtros).forEach(function (field) {
        var input = listaFiltros.querySelector('.filtro-field[data-field="' + field + '"]');
        if (input) input.value = precarga.filtros[field];
      });
    }
  }

  plantilla.addEventListener('change', function () {
    updateTabVisibility();
    showStep(0);
  });

  fuente.addEventListener('change', function () {
    selectedColumns = [];
    onFuenteChange();
  });

  btnAtras.addEventListener('click', function () { showStep(currentIdx - 1); });
  btnSiguiente.addEventListener('click', function () { showStep(currentIdx + 1); });

  form.addEventListener('submit', function () {
    syncSelectedColumns();
    serializeHidden();
  });

  updateTabVisibility();
  applyPrecarga();
  showStep(0);
})();
