/* ==========================================================================
   Struijck Agenda - Admin Kalender Planner
   Vanilla JS, geen jQuery dependency
   ========================================================================== */
(function() {
    'use strict';

    var cfg, i18n, app;
    var state = {
        viewDate: new Date(),
        events: [],
        loading: false
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        if (typeof StruijckCalendar === 'undefined') return;
        cfg = StruijckCalendar;
        i18n = cfg.i18n;
        app = document.getElementById('struijck-calendar-app');
        if (!app) return;

        render();
        fetchMonth();
    }

    /* ============== RENDER ============== */
    function render() {
        var d = state.viewDate;
        var monthName = i18n.months[d.getMonth()];
        var year = d.getFullYear();

        var html = '';

        // Toolbar
        html += '<div class="sc-toolbar">';
        html += '  <div class="sc-toolbar__nav">';
        html += '    <button class="sc-btn sc-btn--icon" data-action="prev" aria-label="Vorige maand">‹</button>';
        html += '    <button class="sc-btn" data-action="today">' + esc(i18n.today) + '</button>';
        html += '    <button class="sc-btn sc-btn--icon" data-action="next" aria-label="Volgende maand">›</button>';
        html += '    <span class="sc-toolbar__title">' + esc(monthName) + ' ' + year + '</span>';
        html += '  </div>';
        html += '  <div class="sc-toolbar__help">Klik op een dag om te plannen</div>';
        html += '</div>';

        // Legenda
        html += '<div class="sc-legend">';
        (cfg.zalen || []).forEach(function(z) {
            html += '<span class="sc-legend__item"><span class="sc-legend__swatch" style="background:' + esc(z.color) + '"></span>' + esc(z.name) + (z.allowDouble ? ' <em>(dubbel mogelijk)</em>' : '') + '</span>';
        });
        html += '<span class="sc-legend__item"><span class="sc-legend__swatch" style="background:#9ca3af"></span>Geen zaal</span>';
        html += '<span class="sc-legend__item sc-legend__item--note">↻ = wekelijks terugkerend</span>';
        html += '</div>';

        // Grid headers
        html += '<div class="sc-grid">';
        i18n.weekdaysShort.forEach(function(w) {
            html += '<div class="sc-grid__header">' + esc(w) + '</div>';
        });

        // Calculate grid dates (Monday-first weeks).
        var first = new Date(d.getFullYear(), d.getMonth(), 1);
        var last  = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        var startOffset = (first.getDay() + 6) % 7; // Mon=0
        var endOffset   = 6 - ((last.getDay() + 6) % 7);

        var gridStart = new Date(first);
        gridStart.setDate(first.getDate() - startOffset);
        var gridEnd = new Date(last);
        gridEnd.setDate(last.getDate() + endOffset);

        var todayStr = ymd(new Date());
        var currentMonth = d.getMonth();
        var eventsByDate = groupBy(state.events, 'date');

        var iter = new Date(gridStart);
        while (iter <= gridEnd) {
            var dStr = ymd(iter);
            var isOther = iter.getMonth() !== currentMonth;
            var isToday = dStr === todayStr;
            var events = eventsByDate[dStr] || [];

            var classes = 'sc-day';
            if (isOther) classes += ' sc-day--other-month';
            if (isToday) classes += ' sc-day--today';

            html += '<div class="' + classes + '" data-date="' + dStr + '">';
            html += '  <div class="sc-day__number">' + iter.getDate() + '</div>';
            html += '  <div class="sc-day__events">';

            events.slice(0, 4).forEach(function(e) {
                var label = e.title + (e.zaal ? ' — ' + e.zaal : '');
                html += '<button class="sc-event" style="background:' + eventColor(e) + ';" data-event-id="' + esc(e.id) + '" data-event-date="' + esc(e.date) + '" title="' + esc(label) + '">';
                if (e.start_time) html += '<span class="sc-event__time">' + esc(e.start_time.substring(0, 5)) + '</span>';
                if (e.is_recurring) html += '<span class="sc-event__repeat">↻</span> ';
                html += esc(e.title);
                html += '</button>';
            });
            if (events.length > 4) {
                html += '<button class="sc-event" style="background:#6b7280;">+' + (events.length - 4) + ' meer</button>';
            }

            html += '  </div>';
            html += '  <button class="sc-day__add" data-add="' + dStr + '" title="Voeg boeking toe op ' + dStr + '" aria-label="Voeg boeking toe">+</button>';
            html += '</div>';

            iter.setDate(iter.getDate() + 1);
        }
        html += '</div>';

        app.innerHTML = html;
        bindToolbar();
        bindDays();
    }

    function bindToolbar() {
        app.querySelectorAll('[data-action]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var a = btn.dataset.action;
                var d = new Date(state.viewDate);
                if (a === 'prev') d.setMonth(d.getMonth() - 1);
                else if (a === 'next') d.setMonth(d.getMonth() + 1);
                else if (a === 'today') d = new Date();
                state.viewDate = d;
                render();
                fetchMonth();
            });
        });
    }

    function bindDays() {
        // Click on a day -> open day modal (list of events for that day)
        app.querySelectorAll('.sc-day').forEach(function(day) {
            day.addEventListener('click', function(e) {
                // Skip if clicking an event pill or the add button (handled separately).
                if (e.target.closest('.sc-event') || e.target.closest('.sc-day__add')) return;
                openDayModal(day.dataset.date);
            });
        });
        app.querySelectorAll('[data-add]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                openBookingModal({ date: btn.dataset.add });
            });
        });
        app.querySelectorAll('[data-event-id]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var ev = findEvent(btn.dataset.eventId, btn.dataset.eventDate);
                if (ev) openBookingModal(ev);
            });
        });
    }

    /* ============== FETCH ============== */
    function fetchMonth() {
        var d = state.viewDate;
        var first = new Date(d.getFullYear(), d.getMonth(), 1);
        var last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        var startOffset = (first.getDay() + 6) % 7;
        var endOffset = 6 - ((last.getDay() + 6) % 7);
        var start = new Date(first); start.setDate(first.getDate() - startOffset);
        var end = new Date(last); end.setDate(last.getDate() + endOffset);

        var url = cfg.ajaxUrl + '?action=struijck_get_month' +
                  '&nonce=' + encodeURIComponent(cfg.nonce) +
                  '&start=' + ymd(start) + '&end=' + ymd(end);

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (j && j.success) {
                    state.events = j.data || [];
                    render();
                }
            })
            .catch(function(err) {
                console.error('Struijck fetch error:', err);
            });
    }

    /* ============== DAY MODAL (lijst per dag) ============== */
    function openDayModal(dateStr) {
        var d = parseYmd(dateStr);
        var dow = (d.getDay() + 6) % 7;
        var dayLabel = i18n.weekdaysLong[dow] + ' ' + d.getDate() + ' ' + i18n.months[d.getMonth()] + ' ' + d.getFullYear();
        var events = state.events.filter(function(e) { return e.date === dateStr; });
        events.sort(function(a, b) { return (a.start_time || '').localeCompare(b.start_time || ''); });

        var html = '<div class="sc-modal-content"><div class="sc-modal__header">';
        html += '<h3 class="sc-modal__title">Boekingen voor ' + esc(dayLabel) + '</h3>';
        html += '<button class="sc-modal__close" data-close>×</button>';
        html += '</div><div class="sc-modal__body">';

        if (events.length === 0) {
            html += '<div class="sc-day-empty">' + esc(i18n.noEvents) + '</div>';
        } else {
            html += '<ul class="sc-day-list">';
            events.forEach(function(e) {
                html += '<li class="sc-day-list__item" data-edit-id="' + esc(e.id) + '" data-edit-date="' + esc(e.date) + '">';
                html += '<div class="sc-day-list__time">';
                if (e.start_time) html += esc(e.start_time.substring(0, 5));
                if (e.end_time) html += ' – ' + esc(e.end_time.substring(0, 5));
                html += '</div>';
                html += '<div><div class="sc-day-list__title">' + esc(e.title) + '</div>';
                if (e.zaal) html += '<div class="sc-day-list__zaal">' + esc(e.zaal) + '</div>';
                html += '</div>';
                html += '<div>';
                if (e.is_recurring) html += '<span class="sc-day-list__badge">↻ wekelijks</span>';
                html += '</div>';
                html += '</li>';
            });
            html += '</ul>';
        }

        html += '<button class="sc-btn sc-btn--primary" data-new-on-day="' + esc(dateStr) + '" style="width:100%;justify-content:center;">' + esc(i18n.addAnother) + '</button>';
        html += '</div></div>';

        showModal(html, function(modal) {
            modal.querySelectorAll('[data-edit-id]').forEach(function(item) {
                item.addEventListener('click', function() {
                    var ev = findEvent(item.dataset.editId, item.dataset.editDate);
                    closeModal(modal);
                    if (ev) openBookingModal(ev);
                });
            });
            var addBtn = modal.querySelector('[data-new-on-day]');
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    var date = addBtn.dataset.newOnDay;
                    closeModal(modal);
                    openBookingModal({ date: date });
                });
            }
        });
    }

    /* ============== BOOKING MODAL (form) ============== */
    function openBookingModal(data) {
        var isEdit = !!data.id;
        var d = parseYmd(data.date);
        var dow = (d.getDay() + 6) % 7;
        var dayLabel = i18n.weekdaysLong[dow] + ' ' + d.getDate() + ' ' + i18n.months[d.getMonth()] + ' ' + d.getFullYear();

        // Determine zaal_id from name (events have zaal name).
        var zaalId = '';
        if (isEdit && data.zaal) {
            cfg.zalen.forEach(function(z) {
                if (z.name === data.zaal) zaalId = z.id;
            });
        }

        var html = '<div class="sc-modal-content"><div class="sc-modal__header">';
        html += '<h3 class="sc-modal__title">' + esc(isEdit ? i18n.editBooking : i18n.newBooking) + '</h3>';
        html += '<div class="sc-modal__date">' + esc(dayLabel) + '</div>';
        html += '<button class="sc-modal__close" data-close>×</button>';
        html += '</div><div class="sc-modal__body">';

        if (isEdit && data.is_recurring) {
            html += '<div class="sc-form__notice">⚠️ ' + esc(i18n.recurringNotice) + '</div>';
        }

        html += '<form id="sc-form" autocomplete="off">';
        html += '<input type="hidden" name="id" value="' + esc(data.id || '') + '">';
        html += '<input type="hidden" name="date" value="' + esc(data.date) + '">';

        html += '<div class="sc-form__row">';
        html += '  <label class="sc-form__label">' + esc(i18n.title) + ' *</label>';
        html += '  <div class="sc-combo" data-combo>';
        html += '    <input type="text" class="sc-form__input sc-combo__input" name="title" value="' + esc(data.title || '') + '" required autofocus placeholder="Kies of typ een huurder…" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list">';
        html += '    <button type="button" class="sc-combo__toggle" tabindex="-1" aria-label="Toon huurders"><span class="sc-combo__toggle-icon">▾</span></button>';
        html += '    <ul class="sc-combo__list" role="listbox" hidden></ul>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="sc-form__row">';
        html += '  <label class="sc-form__label">' + esc(i18n.zaal) + '</label>';
        if (cfg.zalen.length === 0) {
            html += '  <div class="sc-form__no-zalen">' + esc(i18n.noZalen) + ' <a href="' + esc(cfg.newZaalUrl) + '" target="_blank">Zaal aanmaken</a></div>';
        } else {
            html += '  <select class="sc-form__select" name="zaal_id">';
            html += '    <option value="">' + esc(i18n.pickZaal) + '</option>';
            cfg.zalen.forEach(function(z) {
                var sel = (String(z.id) === String(zaalId)) ? ' selected' : '';
                html += '<option value="' + esc(z.id) + '"' + sel + '>' + esc(z.name) + '</option>';
            });
            html += '  </select>';
        }
        html += '</div>';

        html += '<div class="sc-form__row sc-form__row--split">';
        html += '  <div><label class="sc-form__label">' + esc(i18n.startTime) + ' *</label>';
        html += '    <select class="sc-form__select" name="start_time" required>' + timeOptions(data.start_time) + '</select></div>';
        html += '  <div><label class="sc-form__label">' + esc(i18n.endTime) + '</label>';
        html += '    <select class="sc-form__select" name="end_time">' + timeOptions(data.end_time) + '</select></div>';
        html += '</div>';

        html += '<div class="sc-form__row">';
        html += '  <label class="sc-form__check">';
        html += '    <input type="checkbox" name="recurring" id="sc-recurring-cb"' + (data.is_recurring ? ' checked' : '') + '>';
        html += '    <span>↻ ' + esc(i18n.recurring) + '</span>';
        html += '  </label>';
        html += '  <div class="sc-form__recur-options" id="sc-recur-options" style="' + (data.is_recurring ? '' : 'display:none;') + '">';
        html += '    <label class="sc-form__label">' + esc(i18n.recurUntil) + '</label>';
        html += '    <input type="date" class="sc-form__input" name="recur_until" value="' + esc(data.recur_until || '') + '">';
        html += '    <small style="color:#6b7280;">Leeg = blijft doorgaan</small>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="sc-form__row">';
        html += '  <label class="sc-form__label">' + esc(i18n.notes) + '</label>';
        html += '  <textarea class="sc-form__textarea" name="notes" placeholder="Optioneel - alleen zichtbaar in admin">' + esc(data.description || '') + '</textarea>';
        html += '</div>';

        html += '</form>';
        html += '</div><div class="sc-modal__footer">';
        html += '<div>';
        if (isEdit) html += '<button class="sc-btn sc-btn--danger" data-delete>' + esc(i18n.delete) + '</button>';
        html += '</div>';
        html += '<div class="sc-modal__footer-right">';
        html += '<button class="sc-btn" data-close>' + esc(i18n.cancel) + '</button>';
        html += '<button class="sc-btn sc-btn--primary" data-save>' + esc(i18n.save) + '</button>';
        html += '</div></div></div>';

        showModal(html, function(modal) {
            setupCombo(modal);

            var recurCb = modal.querySelector('#sc-recurring-cb');
            var recurOpts = modal.querySelector('#sc-recur-options');
            if (recurCb && recurOpts) {
                recurCb.addEventListener('change', function() {
                    recurOpts.style.display = recurCb.checked ? '' : 'none';
                });
            }

            var saveBtn = modal.querySelector('[data-save]');
            saveBtn.addEventListener('click', function() {
                saveBooking(modal, saveBtn);
            });

            // Enter in form -> save
            var form = modal.querySelector('#sc-form');
            form.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    saveBooking(modal, saveBtn);
                }
            });

            var deleteBtn = modal.querySelector('[data-delete]');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    if (confirm(i18n.confirmDelete)) deleteBooking(data.id, modal);
                });
            }
        });
    }

    function saveBooking(modal, saveBtn) {
        var form = modal.querySelector('#sc-form');
        var fd = new FormData(form);
        fd.append('action', 'struijck_save_booking');
        fd.append('nonce', cfg.nonce);

        saveBtn.disabled = true;
        saveBtn.textContent = i18n.saving;

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (j && j.success) {
                    closeModal(modal);
                    fetchMonth();
                } else {
                    alert((j && j.data) || i18n.errorSaving);
                    saveBtn.disabled = false;
                    saveBtn.textContent = i18n.save;
                }
            })
            .catch(function() {
                alert(i18n.errorSaving);
                saveBtn.disabled = false;
                saveBtn.textContent = i18n.save;
            });
    }

    function deleteBooking(id, modal) {
        var fd = new FormData();
        fd.append('action', 'struijck_delete_booking');
        fd.append('nonce', cfg.nonce);
        fd.append('id', id);

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (j && j.success) {
                    closeModal(modal);
                    fetchMonth();
                } else {
                    alert((j && j.data) || 'Verwijderen mislukt.');
                }
            });
    }

    /* ============== MODAL HELPERS ============== */
    function showModal(innerHtml, onMount) {
        var wrap = document.createElement('div');
        wrap.className = 'sc-modal';
        wrap.innerHTML = '<div class="sc-modal__box">' + innerHtml + '</div>';
        document.body.appendChild(wrap);

        wrap.addEventListener('click', function(e) {
            if (e.target === wrap) closeModal(wrap);
        });
        wrap.querySelectorAll('[data-close]').forEach(function(b) {
            b.addEventListener('click', function() { closeModal(wrap); });
        });
        var escListener = function(e) {
            if (e.key === 'Escape') {
                closeModal(wrap);
                document.removeEventListener('keydown', escListener);
            }
        };
        document.addEventListener('keydown', escListener);

        if (typeof onMount === 'function') onMount(wrap);
    }

    function closeModal(modal) {
        if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
    }

    /* ============== UTILS ============== */
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    // Build <option>s in 30-min steps from 06:00 to 23:30. Any pre-existing
    // value outside the grid (e.g. a legacy 20:47) stays selectable so editing
    // an old booking never silently loses its time.
    var TIME_START_MIN = 6 * 60;
    var TIME_END_MIN = 23 * 60 + 30;
    function timeOptions(selected) {
        var sel = (selected || '').substring(0, 5);
        var out = '<option value="">—</option>';
        var found = false;
        for (var mins = TIME_START_MIN; mins <= TIME_END_MIN; mins += 30) {
            var h = Math.floor(mins / 60), m = mins % 60;
            var v = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
            var isSel = v === sel;
            if (isSel) found = true;
            out += '<option value="' + v + '"' + (isSel ? ' selected' : '') + '>' + v + '</option>';
        }
        if (sel && !found) {
            out += '<option value="' + esc(sel) + '" selected>' + esc(sel) + ' (overig)</option>';
        }
        return out;
    }
    function eventColor(ev) {
        if (!ev.zaal) return '#9ca3af';
        var name = String(ev.zaal).split(',')[0].trim();
        var color = '#9ca3af';
        (cfg.zalen || []).forEach(function(z) {
            if (z.name === name) color = z.color;
        });
        return color;
    }

    // Custom combobox for the huurder field: filter-as-you-type, click,
    // keyboard nav, free entry. Replaces the native <datalist>.
    function setupCombo(scope) {
        var combo = scope.querySelector('[data-combo]');
        if (!combo) return;
        var input = combo.querySelector('.sc-combo__input');
        var toggle = combo.querySelector('.sc-combo__toggle');
        var list = combo.querySelector('.sc-combo__list');
        var all = (cfg.huurders || []).slice();
        var items = [];
        var activeIdx = -1;

        function build(filter) {
            var f = (filter || '').toLowerCase().trim();
            var matches = all.filter(function(h) {
                return !f || h.toLowerCase().indexOf(f) !== -1;
            });
            if (matches.length === 0) {
                list.innerHTML = '<li class="sc-combo__empty">Geen bestaande huurders — typ een nieuwe naam</li>';
                items = [];
            } else {
                list.innerHTML = matches.map(function(h) {
                    return '<li class="sc-combo__opt" role="option">' + esc(h) + '</li>';
                }).join('');
                items = Array.prototype.slice.call(list.querySelectorAll('.sc-combo__opt'));
            }
            activeIdx = -1;
        }
        function open() {
            if (all.length === 0) return;
            build(input.value);
            list.hidden = false;
            combo.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
        }
        function close() {
            list.hidden = true;
            combo.classList.remove('is-open');
            input.setAttribute('aria-expanded', 'false');
            activeIdx = -1;
        }
        function setActive(i) {
            activeIdx = i;
            items.forEach(function(el, idx) {
                el.classList.toggle('is-active', idx === i);
            });
            if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
        }
        function pick(text) {
            input.value = text;
            close();
            input.focus();
        }

        list.addEventListener('mousedown', function(e) {
            var opt = e.target.closest('.sc-combo__opt');
            if (opt) { e.preventDefault(); pick(opt.textContent); }
        });
        toggle.addEventListener('click', function() {
            if (list.hidden) { open(); input.focus(); } else { close(); }
        });
        input.addEventListener('input', function() { open(); });
        input.addEventListener('click', function() { if (list.hidden) { open(); } });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (list.hidden) { open(); }
                if (items.length) { setActive(activeIdx + 1 >= items.length ? items.length - 1 : activeIdx + 1); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (items.length) { setActive(activeIdx <= 0 ? 0 : activeIdx - 1); }
            } else if (e.key === 'Enter') {
                if (!list.hidden) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (activeIdx >= 0 && items[activeIdx]) { pick(items[activeIdx].textContent); }
                    else { close(); }
                }
            } else if (e.key === 'Escape') {
                if (!list.hidden) { e.stopPropagation(); close(); }
            }
        });
        scope.addEventListener('mousedown', function(e) {
            if (!e.target.closest('[data-combo]')) { close(); }
        });
    }

    function ymd(d) {
        var m = d.getMonth() + 1, day = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }
    function parseYmd(s) {
        var p = s.split('-');
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    }
    function groupBy(arr, key) {
        return arr.reduce(function(acc, item) {
            (acc[item[key]] = acc[item[key]] || []).push(item);
            return acc;
        }, {});
    }
    function findEvent(id, date) {
        return state.events.find(function(e) {
            return String(e.id) === String(id) && e.date === date;
        });
    }
})();
