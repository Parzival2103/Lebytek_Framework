<?php
/**
 * Partial: DataTables Responsive (modo solo-responsive) para tablas core.
 *
 * Replica la metodología del CRUD Engine (admin/crud/index.php): la extensión
 * Responsive colapsa columnas por ancho y TODA la fila expande/colapsa el
 * detalle (sin icono dtr-control). NO toma búsqueda/orden/paginación: eso lo
 * resuelve el servidor o el JS cliente existente (TableSearch/TableSort).
 *
 * Inicializa todas las tablas con la clase `js-dt-responsive`. Incluir UNA sola
 * vez por vista (aunque haya varias tablas, p. ej. permisos por módulo).
 *
 * Marcado esperado en cada <table>:
 *   - clase `js-dt-responsive`
 *   - <th data-priority="2"> en la 1ª columna de datos y data-priority="1" en Acciones
 */
?>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.3/css/responsive.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/responsive.bootstrap5.min.js"></script>
<script>
(function () {
  function initCoreResponsive() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) return;
    var tables = jQuery('table.js-dt-responsive');
    if (!tables.length) return;

    tables.each(function () {
      var $t = jQuery(this);
      if (jQuery.fn.DataTable.isDataTable(this)) return;

      var dt = $t.DataTable({
        // type 'column' + target 'tr' → toda la fila expande/colapsa el detalle,
        // sin dibujar columna/icono de control (igual que el CRUD Engine).
        responsive: { details: { type: 'column', target: 'tr' } },
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        lengthChange: false,
        autoWidth: false,
        columnDefs: [{ orderable: false, targets: '_all' }]
      });

      // Los controles interactivos de la fila no deben disparar el toggle del
      // detalle: cortamos la propagación antes de que el click burbujee al
      // handler de fila de Responsive.
      $t.on('click', 'input, a, button, label, select', function (e) {
        e.stopPropagation();
      });

      // El orden cliente (TableSort en app.js) reordena el <tbody> por DOM.
      // Tras reordenar, recalculamos el responsive para no desincronizar el
      // detalle expandible. Microtask para correr después del reorden.
      $t.on('click', 'th.sortable', function () {
        setTimeout(function () {
          dt.columns.adjust().responsive.recalc();
        }, 0);
      });
    });
  }

  if (document.readyState !== 'loading') {
    initCoreResponsive();
  } else {
    document.addEventListener('DOMContentLoaded', initCoreResponsive);
  }
})();
</script>
