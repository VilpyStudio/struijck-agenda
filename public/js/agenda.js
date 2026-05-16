/* ==========================================================================
   Struijck Agenda — Frontend (week / maand / lijst)
   ========================================================================== */
(function() {
    'use strict';

    function init() {
        document.querySelectorAll('.struijck-agenda').forEach(function(el) {
            if (el.dataset.initialized) return;
            el.dataset.initialized = 'true';
            try {
                var config = JSON.parse(el.dataset.config || '{}');
                new StruijckAgenda(el, config);
            } catch (e) {
                console.error('Struijck Agenda config error:', e);
            }
        });
    }

    function StruijckAgenda(root, config) {
        this.root = root;
        this.config = config;
        this.i18n = config.i18n || {};
        var iv = config.initialView;
        this.view = (iv === 'month' || iv === 'week' || iv === 'list') ? iv : 'week';
        this.anchor = new Date();
        this.activeDayOffset = this.getTodayOffset();
        this.currentZaal = config.lockedZaal || 'all';
        this.events = [];
        this.fetchAndRender();
    }

    var P = StruijckAgenda.prototype;

    P.getTodayOffset = function() {
        return (new Date().getDay() + 6) % 7;
    };

    P.weekStart = function(date) {
        var d = new Date(date);
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
        d.setHours(0, 0, 0, 0);
        return d;
    };
    P.addDays = function(date, n) {
        var d = new Date(date);
        d.setDate(d.getDate() + n);
        return d;
    };
    P.monthFirst = function(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1);
    };
    P.monthLast = function(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 0);
    };
    P.monthGridStart = function(date) {
        var f = this.monthFirst(date);
        return this.addDays(f, -((f.getDay() + 6) % 7));
    };
    P.monthGridEnd = function(date) {
        var l = this.monthLast(date);
        return this.addDays(l, 6 - ((l.getDay() + 6) % 7));
    };

    /* Date range to request from the REST API for the current view. */
    P.range = function() {
        if (this.view === 'week') {
            var ws = this.weekStart(this.anchor);
            return { start: ws, end: this.addDays(ws, 6) };
        }
        if (this.view === 'month') {
            return { start: this.monthGridStart(this.anchor), end: this.monthGridEnd(this.anchor) };
        }
        return { start: this.monthFirst(this.anchor), end: this.monthLast(this.anchor) };
    };

    P.fetchAndRender = function() {
        var self = this;
        // First load shows the placeholder; later loads keep the current
        // content (just dimmed) so the page doesn't collapse and jump.
        if (!this._loaded) {
            this.root.innerHTML = '<div class="struijck-agenda__loading">' + esc(this.i18n.loading) + '</div>';
        } else {
            this.root.classList.add('is-refetching');
        }
        var r = this.range();
        var url = this.config.restUrl + '?start=' + ymd(r.start) + '&end=' + ymd(r.end);
        if (this.currentZaal && this.currentZaal !== 'all') {
            url += '&zaal=' + encodeURIComponent(this.currentZaal);
        }
        var done = function(data) {
            self.events = Array.isArray(data) ? data : [];
            self._loaded = true;
            self.root.classList.remove('is-refetching');
            self.render();
        };
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(res) { return res.json(); })
            .then(done)
            .catch(function() { done([]); });
    };

    P.render = function() {
        var html = '';
        html += this.renderHead();
        if (this.config.canRequest) {
            html += '<div class="sa-actions"><button type="button" class="sa-request-btn" data-request>' +
                    esc('Datum/tijd aanvragen') + '</button></div>';
        }
        html += this.renderFilters();
        html += '<div class="sa-week-card">';
        if (this.view === 'week') html += this.renderWeek();
        else if (this.view === 'month') html += this.renderMonth();
        else html += this.renderList();
        html += '</div>';

        this.root.innerHTML = html;
        this.bindEvents();

        if (this.view === 'week') {
            var tabs = this.root.querySelector('.sa-tabs');
            var at = this.root.querySelector('.sa-tab--active');
            if (tabs && at && tabs.scrollWidth > tabs.clientWidth && at.scrollIntoView) {
                at.scrollIntoView({ block: 'nearest', inline: 'center' });
            }
        }
    };

    /* ---------- shared header ---------- */
    P.rangeLabel = function() {
        var m = this.i18n.months || [];
        if (this.view === 'week') {
            var ws = this.weekStart(this.anchor), we = this.addDays(ws, 6);
            return ws.getDate() + ' ' + m[ws.getMonth()] + ' – ' +
                   we.getDate() + ' ' + m[we.getMonth()] + ' ' + we.getFullYear();
        }
        return m[this.anchor.getMonth()] + ' ' + this.anchor.getFullYear();
    };

    P.eyebrow = function() {
        if (this.view === 'month') return this.i18n.month || 'Maand';
        if (this.view === 'list') return this.i18n.list || 'Lijst';
        var today = new Date(), ws = this.weekStart(this.anchor), we = this.addDays(ws, 6);
        if (today >= ws && today <= we) return 'Deze week';
        var tws = this.weekStart(today);
        var diff = Math.round((ws - tws) / (1000 * 60 * 60 * 24 * 7));
        if (diff === 1) return 'Volgende week';
        if (diff === -1) return 'Vorige week';
        if (diff > 0) return 'Over ' + diff + ' weken';
        return Math.abs(diff) + ' weken geleden';
    };

    P.renderHead = function() {
        var i = this.i18n, h = '';
        h += '<div class="sa-week-head">';
        h += '  <div class="sa-week-head__left">';
        h += '    <span class="sa-week-eyebrow">' + esc(this.eyebrow()) + '</span>';
        h += '    <span class="sa-week-range">' + esc(this.rangeLabel()) + '</span>';
        h += '  </div>';
        h += '  <div class="sa-head-right">';
        if (this.view === 'week' || this.view === 'month') {
            var to = this.view === 'week' ? 'month' : 'week';
            var toLabel = to === 'month' ? (i.month || 'Maand') : (i.week || 'Week');
            h += '    <button type="button" class="sa-viewtoggle" data-setview="' + to + '" ' +
                 'aria-label="' + esc('Wissel naar ' + toLabel.toLowerCase() + 'weergave') + '">' +
                 '<span class="sa-viewtoggle__ic" aria-hidden="true">⇆</span> ' + esc(toLabel) + '</button>';
        }
        h += '    <div class="sa-nav">';
        h += '      <button type="button" class="sa-nav-btn" data-action="prev" aria-label="' + esc(i.prev) + '">‹</button>';
        h += '      <button type="button" class="sa-nav-btn sa-nav-today" data-action="today">' + esc(i.today) + '</button>';
        h += '      <button type="button" class="sa-nav-btn" data-action="next" aria-label="' + esc(i.next) + '">›</button>';
        h += '    </div>';
        h += '  </div>';
        h += '</div>';
        return h;
    };

    P.renderFilters = function() {
        if (!this.config.showFilters || !this.config.zalen || !this.config.zalen.length) return '';
        var h = '<div class="sa-filters">';
        h += '<button type="button" class="sa-filter-pill' + (this.currentZaal === 'all' ? ' sa-filter-pill--active' : '') + '" data-zaal="all">' + esc(this.i18n.allZalen) + '</button>';
        this.config.zalen.forEach(function(z) {
            var a = this.currentZaal === z.slug ? ' sa-filter-pill--active' : '';
            h += '<button type="button" class="sa-filter-pill' + a + '" data-zaal="' + esc(z.slug) + '">' + esc(z.name) + '</button>';
        }, this);
        return h + '</div>';
    };

    P.eventsOn = function(dateStr) {
        return this.events
            .filter(function(e) { return e.date === dateStr; })
            .sort(function(a, b) { return (a.start_time || '').localeCompare(b.start_time || ''); });
    };

    P.rowHtml = function(e) {
        var h = '<div class="sa-day-row">';
        h += '<div class="sa-day-row__time">' + esc(e.start_time ? e.start_time.substring(0, 5) : '');
        if (e.end_time) h += '<span class="sa-day-row__time-end">tot ' + esc(e.end_time.substring(0, 5)) + '</span>';
        h += '</div><div><div class="sa-day-row__title">' + esc(e.title) + '</div>';
        if (e.zaal) h += '<div class="sa-day-row__zaal">' + esc(e.zaal) + '</div>';
        return h + '</div></div>';
    };

    /* ---------- week ---------- */
    P.renderWeek = function() {
        var ws = this.weekStart(this.anchor), todayStr = ymd(new Date()), m = this.i18n.months, h = '';
        h += '<div class="sa-tabs">';
        for (var i = 0; i < 7; i++) {
            var d = this.addDays(ws, i), dStr = ymd(d);
            var c = 'sa-tab';
            if (i === this.activeDayOffset) c += ' sa-tab--active';
            if (dStr === todayStr) c += ' sa-tab--today';
            h += '<button type="button" class="' + c + '" data-day-offset="' + i + '">' +
                 esc(this.i18n.weekdaysShort[i]) +
                 '<span class="sa-tab__date">' + d.getDate() + ' ' + esc(m[d.getMonth()].substring(0, 3)) + '</span></button>';
        }
        h += '</div>';

        // Desktop: alleen de actieve dag.
        h += '<div class="sa-day-content">';
        var ev = this.eventsOn(ymd(this.addDays(ws, this.activeDayOffset)));
        if (!ev.length) h += '<div class="sa-day-empty">' + esc(this.i18n.noEvents) + '</div>';
        else ev.forEach(function(e) { h += this.rowHtml(e); }, this);
        h += '</div>';

        // Mobiel: hele week verticaal (geen tabs, geen side-scroll).
        var wl = this.i18n.weekdaysLong;
        h += '<div class="sa-week-agenda">';
        for (var j = 0; j < 7; j++) {
            var wd = this.addDays(ws, j), wdStr = ymd(wd);
            var dayEv = this.eventsOn(wdStr);
            var dc = 'sa-wa-day' + (wdStr === todayStr ? ' sa-wa-day--today' : '');
            h += '<div class="' + dc + '">';
            h += '<div class="sa-wa-date">' + esc(wl[j]) + ' ' + wd.getDate() + ' ' + esc(m[wd.getMonth()]) + '</div>';
            if (!dayEv.length) {
                h += '<div class="sa-wa-empty">' + esc(this.i18n.noEvents) + '</div>';
            } else {
                dayEv.forEach(function(e) { h += this.rowHtml(e); }, this);
            }
            h += '</div>';
        }
        h += '</div>';

        return h;
    };

    /* ---------- month ---------- */
    P.renderMonth = function() {
        var gs = this.monthGridStart(this.anchor), ge = this.monthGridEnd(this.anchor);
        var todayStr = ymd(new Date()), curMonth = this.anchor.getMonth(), h = '';
        h += '<div class="sa-month__head">';
        this.i18n.weekdaysShort.forEach(function(w) { h += '<div class="sa-month__hcell">' + esc(w) + '</div>'; });
        h += '</div><div class="sa-month">';
        var it = new Date(gs);
        while (it <= ge) {
            var dStr = ymd(it);
            var cls = 'sa-mday';
            if (it.getMonth() !== curMonth) cls += ' sa-mday--other';
            if (dStr === todayStr) cls += ' sa-mday--today';
            var ev = this.eventsOn(dStr);
            if (ev.length) cls += ' sa-mday--has';
            h += '<div class="' + cls + '" data-date="' + dStr + '" role="button" tabindex="0">';
            h += '<span class="sa-mday__num">' + it.getDate() + '</span>';
            h += '<div class="sa-mday__events">';
            ev.slice(0, 3).forEach(function(e) {
                h += '<span class="sa-mevent">' +
                     (e.start_time ? '<b>' + esc(e.start_time.substring(0, 5)) + '</b> ' : '') +
                     esc(e.title) + '</span>';
            });
            if (ev.length > 3) h += '<span class="sa-mmore">+' + (ev.length - 3) + ' meer</span>';
            h += '</div>';
            h += '</div>';
            it = this.addDays(it, 1);
        }
        return h + '</div>';
    };

    /* ---------- list ---------- */
    P.renderList = function() {
        var m = this.i18n.months, wl = this.i18n.weekdaysLong, h = '';
        var sorted = this.events.slice().sort(function(a, b) {
            return (a.date + (a.start_time || '')).localeCompare(b.date + (b.start_time || ''));
        });
        if (!sorted.length) return '<div class="sa-day-content"><div class="sa-day-empty">' + esc(this.i18n.noEvents) + '</div></div>';
        h += '<div class="sa-list">';
        var lastDate = '';
        sorted.forEach(function(e) {
            if (e.date !== lastDate) {
                lastDate = e.date;
                var p = e.date.split('-');
                var d = new Date(+p[0], +p[1] - 1, +p[2]);
                h += '<div class="sa-list__date">' + esc(wl[(d.getDay() + 6) % 7]) + ' ' +
                     d.getDate() + ' ' + esc(m[d.getMonth()]) + '</div>';
            }
            h += this.rowHtml(e);
        }, this);
        return h + '</div>';
    };

    /* ---------- events ---------- */
    P.bindEvents = function() {
        var self = this;
        this.root.querySelectorAll('[data-action]').forEach(function(b) {
            b.addEventListener('click', function() {
                var a = b.dataset.action;
                if (a === 'today') {
                    self.anchor = new Date();
                    self.activeDayOffset = self.getTodayOffset();
                } else {
                    var step = (a === 'prev') ? -1 : 1;
                    if (self.view === 'week') self.anchor = self.addDays(self.anchor, step * 7);
                    else self.anchor = new Date(self.anchor.getFullYear(), self.anchor.getMonth() + step, 1);
                }
                self.fetchAndRender();
            });
        });
        this.root.querySelectorAll('[data-day-offset]').forEach(function(t) {
            t.addEventListener('click', function() {
                self.activeDayOffset = parseInt(t.dataset.dayOffset, 10);
                self.render();
            });
        });
        this.root.querySelectorAll('[data-zaal]').forEach(function(b) {
            b.addEventListener('click', function() {
                self.currentZaal = b.dataset.zaal;
                self.fetchAndRender();
            });
        });
        this.root.querySelectorAll('[data-setview]').forEach(function(b) {
            b.addEventListener('click', function() {
                if (self.view === b.dataset.setview) return;
                self.view = b.dataset.setview;
                self.fetchAndRender();
            });
        });
        this.root.querySelectorAll('.sa-mday[data-date]').forEach(function(c) {
            var open = function() { self.openDay(c.dataset.date); };
            c.addEventListener('click', open);
            c.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
            });
        });
        this.root.querySelectorAll('[data-request]').forEach(function(b) {
            b.addEventListener('click', function() { self.openRequest(''); });
        });
    };

    /* Day-detail overlay (month → all bookings of a day, incl. mobile). */
    P.openDay = function(dateStr) {
        var p = dateStr.split('-');
        var d = new Date(+p[0], +p[1] - 1, +p[2]);
        var wl = this.i18n.weekdaysLong, m = this.i18n.months;
        var label = esc(wl[(d.getDay() + 6) % 7]) + ' ' + d.getDate() + ' ' + esc(m[d.getMonth()]) + ' ' + d.getFullYear();
        var ev = this.eventsOn(dateStr);

        var body = '';
        if (!ev.length) {
            body = '<div class="sa-day-empty">' + esc(this.i18n.noEvents) + '</div>';
        } else {
            ev.forEach(function(e) { body += this.rowHtml(e); }, this);
        }

        var footer = '';
        if (this.config.canRequest) {
            footer = '<div class="sa-modal__foot"><button type="button" class="sa-request-btn" data-request-date="' +
                     esc(dateStr) + '">' + esc('Deze dag aanvragen') + '</button></div>';
        }

        var self = this;
        var wrap = document.createElement('div');
        wrap.className = 'sa-modal';
        wrap.innerHTML =
            '<div class="sa-modal__box" role="dialog" aria-modal="true">' +
            '<div class="sa-modal__head"><span class="sa-modal__title">' + label + '</span>' +
            '<button type="button" class="sa-modal__close" aria-label="' + esc(this.i18n.close || 'Sluiten') + '">×</button></div>' +
            '<div class="sa-modal__body">' + body + '</div>' + footer + '</div>';
        this.root.appendChild(wrap);

        var close = function() {
            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
            document.removeEventListener('keydown', onKey);
        };
        var onKey = function(e) { if (e.key === 'Escape') close(); };
        wrap.addEventListener('click', function(e) { if (e.target === wrap) close(); });
        wrap.querySelector('.sa-modal__close').addEventListener('click', close);
        var rb = wrap.querySelector('[data-request-date]');
        if (rb) {
            rb.addEventListener('click', function() {
                close();
                self.openRequest(rb.dataset.requestDate);
            });
        }
        document.addEventListener('keydown', onKey);
    };

    P.timeOpts = function(selected) {
        var out = '<option value="">—</option>';
        for (var mins = 6 * 60; mins <= 23 * 60 + 30; mins += 30) {
            var hh = Math.floor(mins / 60), mm = mins % 60;
            var v = (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
            out += '<option value="' + v + '"' + (v === selected ? ' selected' : '') + '>' + v + '</option>';
        }
        return out;
    };

    /* Public booking-request form. */
    P.openRequest = function(prefillDate) {
        var self = this;
        var zalenOpts = '<option value="">' + esc('Geen voorkeur') + '</option>';
        (this.config.zalen || []).forEach(function(z) {
            zalenOpts += '<option value="' + esc(z.slug) + '">' + esc(z.name) + '</option>';
        });

        var form =
            '<form class="sa-form" novalidate>' +
            '<label class="sa-field"><span>Naam / vereniging *</span>' +
            '<input type="text" name="naam" required></label>' +
            '<label class="sa-field"><span>E-mailadres *</span>' +
            '<input type="email" name="email" required></label>' +
            '<label class="sa-field"><span>Telefoon</span>' +
            '<input type="tel" name="telefoon"></label>' +
            '<label class="sa-field"><span>Zaal</span>' +
            '<select name="zaal">' + zalenOpts + '</select></label>' +
            '<label class="sa-field"><span>Datum *</span>' +
            '<input type="date" name="date" required value="' + esc(prefillDate || '') + '"></label>' +
            '<div class="sa-field-row">' +
            '<label class="sa-field"><span>Starttijd *</span>' +
            '<select name="start_time" required>' + this.timeOpts('') + '</select></label>' +
            '<label class="sa-field"><span>Eindtijd</span>' +
            '<select name="end_time">' + this.timeOpts('') + '</select></label>' +
            '</div>' +
            '<div class="sa-avail" hidden></div>' +
            '<label class="sa-field"><span>Opmerking</span>' +
            '<textarea name="opmerking" rows="3"></textarea></label>' +
            '<input type="text" name="website" class="sa-hp" tabindex="-1" autocomplete="off" aria-hidden="true">' +
            '<div class="sa-form__msg" hidden></div>' +
            '<div class="sa-modal__foot">' +
            '<button type="submit" class="sa-request-btn">' + esc('Aanvraag versturen') + '</button>' +
            '</div></form>';

        var wrap = document.createElement('div');
        wrap.className = 'sa-modal';
        wrap.innerHTML =
            '<div class="sa-modal__box" role="dialog" aria-modal="true">' +
            '<div class="sa-modal__head"><span class="sa-modal__title">' + esc('Datum/tijd aanvragen') + '</span>' +
            '<button type="button" class="sa-modal__close" aria-label="Sluiten">×</button></div>' +
            '<div class="sa-modal__body">' + form + '</div></div>';
        this.root.appendChild(wrap);

        var close = function() {
            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
            document.removeEventListener('keydown', onKey);
        };
        var onKey = function(e) { if (e.key === 'Escape') close(); };
        wrap.addEventListener('click', function(e) { if (e.target === wrap) close(); });
        wrap.querySelector('.sa-modal__close').addEventListener('click', close);
        document.addEventListener('keydown', onKey);

        var formEl = wrap.querySelector('.sa-form');
        var msg = wrap.querySelector('.sa-form__msg');

        /* Availability: disable time slots already booked for the chosen
           zaal/date (skipped when the zaal mag dubbel of geen zaal). */
        var avail = wrap.querySelector('.sa-avail');
        var zaalEl = formEl.querySelector('[name="zaal"]');
        var dateEl = formEl.querySelector('[name="date"]');
        var startEl = formEl.querySelector('[name="start_time"]');
        var endEl = formEl.querySelector('[name="end_time"]');
        var booked = [];

        var tmin = function(t) {
            var m = /^(\d{1,2}):(\d{2})/.exec(t || '');
            return m ? (+m[1]) * 60 + (+m[2]) : null;
        };
        var fmt = function(x) {
            var h = Math.floor(x / 60), mm = x % 60;
            return (h < 10 ? '0' : '') + h + ':' + (mm < 10 ? '0' : '') + mm;
        };
        var allowsDouble = function(slug) {
            var ad = false;
            (self.config.zalen || []).forEach(function(z) { if (z.slug === slug) ad = !!z.allowDouble; });
            return ad;
        };
        var applyDisable = function() {
            Array.prototype.forEach.call(startEl.options, function(o) {
                if (!o.value) { o.disabled = false; return; }
                var v = tmin(o.value);
                o.disabled = booked.some(function(b) { return v >= b.s && v < b.e; });
            });
            if (startEl.selectedOptions[0] && startEl.selectedOptions[0].disabled) startEl.value = '';
            var sMin = tmin(startEl.value);
            Array.prototype.forEach.call(endEl.options, function(o) {
                if (!o.value) { o.disabled = false; return; }
                var v = tmin(o.value);
                if (sMin == null) { o.disabled = false; return; }
                o.disabled = (v <= sMin) || booked.some(function(b) { return sMin < b.e && b.s < v; });
            });
            if (endEl.selectedOptions[0] && endEl.selectedOptions[0].disabled) endEl.value = '';
        };
        var refresh = function() {
            var slug = zaalEl.value, date = dateEl.value;
            booked = [];
            if (!slug || !date || allowsDouble(slug)) { avail.hidden = true; applyDisable(); return; }
            fetch(self.config.restUrl + '?start=' + encodeURIComponent(date) + '&end=' + encodeURIComponent(date) + '&zaal=' + encodeURIComponent(slug),
                  { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(list) {
                    booked = (Array.isArray(list) ? list : [])
                        .filter(function(e) { return e.date === date && e.start_time; })
                        .map(function(e) {
                            var s = tmin(e.start_time), en = tmin(e.end_time);
                            if (en == null || en <= s) en = s + 30;
                            return { s: s, e: en };
                        });
                    if (booked.length) {
                        avail.hidden = false;
                        avail.textContent = 'Al bezet: ' + booked.slice()
                            .sort(function(a, b) { return a.s - b.s; })
                            .map(function(b) { return fmt(b.s) + '–' + fmt(b.e); }).join(', ');
                    } else {
                        avail.hidden = true;
                    }
                    applyDisable();
                })
                .catch(function() { avail.hidden = true; applyDisable(); });
        };
        zaalEl.addEventListener('change', refresh);
        dateEl.addEventListener('change', refresh);
        startEl.addEventListener('change', applyDisable);
        refresh();

        formEl.addEventListener('submit', function(e) {
            e.preventDefault();
            var data = {};
            ['naam', 'email', 'telefoon', 'zaal', 'date', 'start_time', 'end_time', 'opmerking', 'website'].forEach(function(k) {
                var el = formEl.querySelector('[name="' + k + '"]');
                data[k] = el ? el.value : '';
            });
            if (!data.naam || !data.email || !data.date || !data.start_time) {
                msg.hidden = false;
                msg.className = 'sa-form__msg sa-form__msg--err';
                msg.textContent = 'Vul naam, e-mail, datum en starttijd in.';
                return;
            }
            var btn = formEl.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Versturen…';
            fetch(self.config.requestUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                .then(function(res) {
                    if (res.ok && res.j && res.j.success) {
                        if (self.config.redirect) {
                            window.location.href = self.config.redirect;
                            return;
                        }
                        wrap.querySelector('.sa-modal__body').innerHTML =
                            '<div class="sa-form__done">' + esc(res.j.message || 'Bedankt! Je aanvraag is verstuurd.') + '</div>';
                    } else {
                        msg.hidden = false;
                        msg.className = 'sa-form__msg sa-form__msg--err';
                        msg.textContent = (res.j && res.j.message) || 'Er ging iets mis. Probeer het later opnieuw.';
                        btn.disabled = false;
                        btn.textContent = 'Aanvraag versturen';
                    }
                })
                .catch(function() {
                    msg.hidden = false;
                    msg.className = 'sa-form__msg sa-form__msg--err';
                    msg.textContent = 'Er ging iets mis. Probeer het later opnieuw.';
                    btn.disabled = false;
                    btn.textContent = 'Aanvraag versturen';
                });
        });
    };

    /* Helpers */
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function ymd(d) {
        var m = d.getMonth() + 1, day = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
