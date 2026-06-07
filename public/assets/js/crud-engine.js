(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Generic confirm for single + bulk POST action forms.
        document.querySelectorAll('form.js-crud-action, form.js-crud-bulk-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var msg = form.getAttribute('data-confirm');
                if (msg && !window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        });

        var bar = document.querySelector('[data-crud-bulk-bar]');
        if (!bar) { return; }

        var selectAll = document.querySelector('[data-crud-select-all]');
        var checkboxes = function () {
            return Array.prototype.slice.call(document.querySelectorAll('[data-crud-row-check]'));
        };
        var countEl = bar.querySelector('[data-crud-bulk-count]');

        function selectedIds() {
            return checkboxes().filter(function (c) { return c.checked; })
                .map(function (c) { return c.value; });
        }

        function refresh() {
            var ids = selectedIds();
            if (countEl) { countEl.textContent = String(ids.length); }
            bar.classList.toggle('d-none', ids.length === 0);
            // Inject hidden ids[] into every bulk form.
            document.querySelectorAll('[data-crud-bulk-ids]').forEach(function (slot) {
                slot.innerHTML = '';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    slot.appendChild(input);
                });
            });
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes().forEach(function (c) { c.checked = selectAll.checked; });
                refresh();
            });
        }
        document.addEventListener('change', function (e) {
            if (e.target && e.target.matches('[data-crud-row-check]')) { refresh(); }
        });
        refresh();
    });
})();
