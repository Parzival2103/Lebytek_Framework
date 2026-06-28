/* Lebytek — Módulo Calendario (front).
 * Vanilla JS. Fase 1: vistas "month" y "table" (solo lectura).
 * Vistas "week"/"day" e interacciones de edición se añaden en fases posteriores.
 */
(function () {
    'use strict';

    var MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function ymdhms(d) { return ymd(d) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); }
    function startOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0); }
    function endOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59); }
    function addDays(d, n) { var c = new Date(d); c.setDate(c.getDate() + n); return c; }
    // 0 = lunes ... 6 = domingo
    function isoDow(d) { return (d.getDay() + 6) % 7; }
    function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Parsea "YYYY-MM-DD[ HH:MM:SS]" como fecha local (sin desfase de zona).
    function parseLocal(value) {
        if (!value) { return null; }
        var m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) { return null; }
        return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
    }

    function Calendar(el) {
        this.el = el;
        this.feed = el.getAttribute('data-feed') || '';
        this.crudBase = el.getAttribute('data-crud-base') || '';
        this.calendarUrl = el.getAttribute('data-calendar-url') || '';
        this.startField = el.getAttribute('data-start-field') || '';
        this.openDetail = el.getAttribute('data-open-detail') === '1';
        this.canCreate = el.getAttribute('data-can-create') === '1';
        this.canEdit = el.getAttribute('data-can-edit') === '1';
        this.canDelete = el.getAttribute('data-can-delete') === '1';
        this.csrf = el.getAttribute('data-csrf') || '';
        this.allDay = el.getAttribute('data-all-day') === '1';
        this.view = el.getAttribute('data-default-view') || 'month';
        this.anchor = new Date();
        this.events = [];
        this.periodLabel = document.querySelector('[data-cal-period]');
        this.emptyEl = document.querySelector('[data-cal-empty]');
        this.bind();
        this.refresh();
    }

    Calendar.prototype.bind = function () {
        var self = this;
        document.querySelectorAll('[data-cal-nav]').forEach(function (btn) {
            btn.addEventListener('click', function () { self.navigate(btn.getAttribute('data-cal-nav')); });
        });
        document.querySelectorAll('[data-cal-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                self.setView(btn.getAttribute('data-cal-view'));
                document.querySelectorAll('[data-cal-view]').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
        });
        document.querySelectorAll('[data-cal-filter]').forEach(function (sel) {
            sel.addEventListener('change', function () { self.refresh(); });
        });
    };

    // Construye "&f_campo=valor" para cada filtro activo de la toolbar.
    Calendar.prototype.filterQuery = function () {
        var q = '';
        document.querySelectorAll('[data-cal-filter]').forEach(function (sel) {
            var field = sel.getAttribute('data-cal-filter');
            var value = sel.value;
            if (field && value !== '' && value != null) {
                q += '&f_' + encodeURIComponent(field) + '=' + encodeURIComponent(value);
            }
        });
        return q;
    };

    Calendar.prototype.setView = function (view) {
        if (!view || view === this.view) { return; }
        this.view = view;
        this.refresh();
    };

    Calendar.prototype.navigate = function (dir) {
        if (dir === 'today') {
            this.anchor = new Date();
        } else {
            var step = (dir === 'prev') ? -1 : 1;
            if (this.view === 'day') {
                this.anchor = addDays(this.anchor, step);
            } else if (this.view === 'week') {
                this.anchor = addDays(this.anchor, step * 7);
            } else {
                this.anchor = new Date(this.anchor.getFullYear(), this.anchor.getMonth() + step, 1);
            }
        }
        this.refresh();
    };

    // Rango [from, to] a consultar según la vista (month/table cubren rejilla mensual).
    Calendar.prototype.range = function () {
        if (this.view === 'day') {
            return { from: startOfDay(this.anchor), to: endOfDay(this.anchor) };
        }
        if (this.view === 'week') {
            var monday = addDays(this.anchor, -isoDow(this.anchor));
            return { from: startOfDay(monday), to: endOfDay(addDays(monday, 6)) };
        }
        // month / table: rejilla que abarca semanas completas del mes.
        var first = new Date(this.anchor.getFullYear(), this.anchor.getMonth(), 1);
        var last = new Date(this.anchor.getFullYear(), this.anchor.getMonth() + 1, 0);
        var gridStart = addDays(first, -isoDow(first));
        var gridEnd = addDays(last, 6 - isoDow(last));
        return { from: startOfDay(gridStart), to: endOfDay(gridEnd) };
    };

    Calendar.prototype.updatePeriod = function () {
        if (!this.periodLabel) { return; }
        var label;
        if (this.view === 'day') {
            label = this.anchor.getDate() + ' de ' + MONTHS[this.anchor.getMonth()] + ' ' + this.anchor.getFullYear();
        } else if (this.view === 'week') {
            var monday = addDays(this.anchor, -isoDow(this.anchor));
            var sunday = addDays(monday, 6);
            label = ymd(monday) + ' — ' + ymd(sunday);
        } else {
            label = MONTHS[this.anchor.getMonth()].charAt(0).toUpperCase() +
                MONTHS[this.anchor.getMonth()].slice(1) + ' ' + this.anchor.getFullYear();
        }
        this.periodLabel.textContent = label;
    };

    Calendar.prototype.refresh = function () {
        var self = this;
        this.updatePeriod();
        var r = this.range();
        var url = this.feed + (this.feed.indexOf('?') >= 0 ? '&' : '?') +
            'desde=' + encodeURIComponent(ymd(r.from)) + '&hasta=' + encodeURIComponent(ymd(r.to)) +
            this.filterQuery();

        this.el.innerHTML = '<div class="lebytek-calendar-loading text-center text-muted py-5">' +
            '<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>Cargando eventos…</div>';

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) { throw new Error('feed ' + res.status); }
                return res.json();
            })
            .then(function (data) {
                self.events = (data && data.eventos) ? data.eventos : [];
                self.render();
            })
            .catch(function () {
                self.el.innerHTML = '<div class="alert alert-danger mb-0">No fue posible cargar los eventos.</div>';
            });
    };

    Calendar.prototype.render = function () {
        if (this.emptyEl) { this.emptyEl.classList.toggle('d-none', this.events.length > 0); }
        if (this.view === 'table') {
            this.renderTable();
        } else if (this.view === 'week') {
            this.renderWeek();
        } else if (this.view === 'day') {
            this.renderDay();
        } else {
            this.renderMonth();
        }
    };

    // Hora "HH:MM" del inicio del evento (vacío si es de día completo).
    Calendar.prototype.timeLabel = function (ev) {
        if (ev.allDay || this.allDay) { return ''; }
        var d = parseLocal(ev.start);
        if (!d) { return ''; }
        return pad(d.getHours()) + ':' + pad(d.getMinutes());
    };

    Calendar.prototype.dayColumn = function (date, events) {
        var self = this;
        var isToday = sameDay(date, new Date());
        var html = '<div class="lebytek-calendar-col' + (isToday ? ' is-today' : '') + '" data-cal-slot="' + ymd(date) + '">';
        html += '<div class="lebytek-calendar-col-head">' +
            WEEKDAYS[isoDow(date)] + ' ' + date.getDate() + '</div>';
        html += '<div class="lebytek-calendar-col-body">';
        if (events.length === 0) {
            html += '<div class="lebytek-calendar-col-empty">—</div>';
        } else {
            events.sort(function (a, b) { return String(a.start).localeCompare(String(b.start)); });
            events.forEach(function (ev) {
                var t = self.timeLabel(ev);
                html += '<button type="button" class="lebytek-calendar-pill bg-' + escapeHtml(ev.color || 'primary') +
                    '" data-event-id="' + escapeHtml(ev.id) + '" title="' + escapeHtml(ev.title) + '">' +
                    (t ? '<span class="lebytek-calendar-pill-time">' + t + '</span> ' : '') +
                    escapeHtml(ev.title) + '</button>';
            });
        }
        html += '</div></div>';
        return html;
    };

    Calendar.prototype.renderWeek = function () {
        var byDay = this.eventsByDay();
        var monday = addDays(this.anchor, -isoDow(this.anchor));
        var html = '<div class="lebytek-calendar-week">';
        for (var i = 0; i < 7; i++) {
            var d = addDays(monday, i);
            html += this.dayColumn(d, (byDay[ymd(d)] || []).slice());
        }
        html += '</div>';
        this.el.innerHTML = html;
        this.wireEventClicks();
    };

    Calendar.prototype.renderDay = function () {
        var byDay = this.eventsByDay();
        var html = '<div class="lebytek-calendar-week lebytek-calendar-week--single">';
        html += this.dayColumn(new Date(this.anchor), (byDay[ymd(this.anchor)] || []).slice());
        html += '</div>';
        this.el.innerHTML = html;
        this.wireEventClicks();
    };

    Calendar.prototype.eventsByDay = function () {
        var map = {};
        this.events.forEach(function (ev) {
            var d = parseLocal(ev.start);
            if (!d) { return; }
            var k = ymd(d);
            (map[k] = map[k] || []).push(ev);
        });
        return map;
    };

    Calendar.prototype.renderMonth = function () {
        var r = this.range();
        var byDay = this.eventsByDay();
        var today = new Date();
        var month = this.anchor.getMonth();

        var html = '<div class="lebytek-calendar-grid" role="grid">';
        WEEKDAYS.forEach(function (w) {
            html += '<div class="lebytek-calendar-weekday" role="columnheader">' + w + '</div>';
        });

        var cursor = new Date(r.from);
        var guard = 0;
        while (cursor <= r.to && guard < 60) {
            var inMonth = cursor.getMonth() === month;
            var isToday = sameDay(cursor, today);
            var dayEvents = byDay[ymd(cursor)] || [];
            html += '<div class="lebytek-calendar-day' + (inMonth ? '' : ' is-muted') + (isToday ? ' is-today' : '') +
                '" role="gridcell" data-cal-slot="' + ymd(cursor) + '">';
            html += '<div class="lebytek-calendar-daynum">' + cursor.getDate() + '</div>';
            html += '<div class="lebytek-calendar-events">';

            var shown = dayEvents.slice(0, 3);
            shown.forEach(function (ev) {
                html += '<button type="button" class="lebytek-calendar-pill bg-' + escapeHtml(ev.color || 'primary') +
                    '" data-event-id="' + escapeHtml(ev.id) + '" title="' + escapeHtml(ev.title) + '">' +
                    escapeHtml(ev.title) + '</button>';
            });
            if (dayEvents.length > shown.length) {
                html += '<span class="lebytek-calendar-more">+' + (dayEvents.length - shown.length) + ' más</span>';
            }
            html += '</div></div>';
            cursor = addDays(cursor, 1);
            guard++;
        }
        html += '</div>';
        this.el.innerHTML = html;
        this.wireEventClicks();
    };

    Calendar.prototype.renderTable = function () {
        var sorted = this.events.slice().sort(function (a, b) {
            return String(a.start).localeCompare(String(b.start));
        });
        if (sorted.length === 0) {
            this.el.innerHTML = '';
            return;
        }
        var html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0">' +
            '<thead><tr><th scope="col"></th><th scope="col">Evento</th><th scope="col">Inicio</th><th scope="col">Fin</th></tr></thead><tbody>';
        sorted.forEach(function (ev) {
            html += '<tr class="lebytek-calendar-row" data-event-id="' + escapeHtml(ev.id) + '" style="cursor:pointer">' +
                '<td><span class="badge bg-' + escapeHtml(ev.color || 'primary') + '">&nbsp;</span></td>' +
                '<td>' + escapeHtml(ev.title) + '</td>' +
                '<td class="text-muted small">' + escapeHtml(ev.start) + '</td>' +
                '<td class="text-muted small">' + escapeHtml(ev.end || '—') + '</td></tr>';
        });
        html += '</tbody></table></div>';
        this.el.innerHTML = html;
        this.wireEventClicks();
    };

    // Click en un evento abre el popover con acciones según capacidades RBAC.
    Calendar.prototype.wireEventClicks = function () {
        var self = this;
        this.el.querySelectorAll('[data-event-id]').forEach(function (node) {
            node.addEventListener('click', function (e) {
                e.stopPropagation();
                var ev = self.findEvent(node.getAttribute('data-event-id'));
                if (ev) { self.openPopover(ev, node); }
            });
        });
        // Crear desde slot vacío (día/columna sin evento).
        if (this.canCreate) {
            this.el.querySelectorAll('[data-cal-slot]').forEach(function (slot) {
                slot.classList.add('is-creatable');
                slot.addEventListener('click', function (e) {
                    if (e.target.closest('[data-event-id]')) { return; }
                    self.createOnSlot(slot.getAttribute('data-cal-slot'));
                });
            });
        }
    };

    Calendar.prototype.returnQuery = function () {
        if (!this.calendarUrl) { return ''; }
        return '&return_to=' + encodeURIComponent(this.calendarUrl);
    };

    Calendar.prototype.createOnSlot = function (date) {
        if (!this.canCreate || !this.crudBase || !date) { return; }
        var param = this.startField || 'fecha';
        window.location.href = this.crudBase + '/crear?' +
            encodeURIComponent(param) + '=' + encodeURIComponent(date) + this.returnQuery();
    };

    Calendar.prototype.closePopover = function () {
        if (this.popover) {
            this.popover.remove();
            this.popover = null;
        }
        if (this.popoverOutside) {
            document.removeEventListener('click', this.popoverOutside, true);
            this.popoverOutside = null;
        }
    };

    Calendar.prototype.openPopover = function (ev, node) {
        var self = this;
        this.closePopover();

        var pop = document.createElement('div');
        pop.className = 'lebytek-calendar-popover card shadow';
        var html = '<div class="lebytek-calendar-popover-head"><span class="badge bg-' +
            escapeHtml(ev.color || 'primary') + ' me-1">&nbsp;</span>' + escapeHtml(ev.title) + '</div>';
        html += '<div class="lebytek-calendar-popover-time text-muted small">' +
            escapeHtml(ev.start) + (ev.end ? ' — ' + escapeHtml(ev.end) : '') + '</div>';
        html += '<div class="lebytek-calendar-popover-actions">';
        if (this.openDetail && ev.url) {
            html += '<a class="btn btn-sm btn-outline-info" href="' + escapeHtml(ev.url) + '">' +
                '<i class="bi bi-eye"></i> Ver</a>';
        }
        if (this.canEdit && this.crudBase) {
            html += '<a class="btn btn-sm btn-outline-primary" href="' + escapeHtml(this.crudBase) +
                '/' + encodeURIComponent(ev.id) + '/editar' +
                (this.calendarUrl ? ('?return_to=' + encodeURIComponent(this.calendarUrl)) : '') +
                '"><i class="bi bi-pencil"></i> Editar</a>';
        }
        if (this.canDelete && this.crudBase) {
            html += '<form method="POST" class="d-inline" action="' + escapeHtml(this.crudBase) +
                '/' + encodeURIComponent(ev.id) + '/eliminar"' +
                ' data-confirm="¿Eliminar este registro? Esta acción no se puede deshacer."' +
                ' data-confirm-title="Eliminar" data-confirm-variant="danger" data-confirm-ok="Eliminar">' +
                '<input type="hidden" name="_csrf_token" value="' + escapeHtml(this.csrf) + '">' +
                (this.calendarUrl ? ('<input type="hidden" name="return_to" value="' + escapeHtml(this.calendarUrl) + '">') : '') +
                '<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button></form>';
        }
        html += '</div>';
        pop.innerHTML = html;
        document.body.appendChild(pop);

        var rect = node.getBoundingClientRect();
        var top = window.scrollY + rect.bottom + 6;
        var left = window.scrollX + rect.left;
        var maxLeft = window.scrollX + document.documentElement.clientWidth - pop.offsetWidth - 12;
        if (left > maxLeft) { left = Math.max(window.scrollX + 8, maxLeft); }
        pop.style.top = top + 'px';
        pop.style.left = left + 'px';

        this.popover = pop;
        this.popoverOutside = function (e) {
            if (!pop.contains(e.target)) { self.closePopover(); }
        };
        // Diferir para no capturar el click que abrió el popover.
        setTimeout(function () {
            document.addEventListener('click', self.popoverOutside, true);
        }, 0);
    };

    Calendar.prototype.findEvent = function (id) {
        for (var i = 0; i < this.events.length; i++) {
            if (String(this.events[i].id) === String(id)) { return this.events[i]; }
        }
        return null;
    };

    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('lebytek-calendar');
        if (el) { new Calendar(el); }
    });
})();
