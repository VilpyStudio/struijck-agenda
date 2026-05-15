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
        this.root.innerHTML = '<div class="struijck-agenda__loading">' + esc(this.i18n.loading) + '</div>';
        var self = this;
        var r = this.range();
        var url = this.config.restUrl + '?start=' + ymd(r.start) + '&end=' + ymd(r.end);
        if (this.currentZaal && this.currentZaal !== 'all') {
            url += '&zaal=' + encodeURIComponent(this.currentZaal);
        }
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(res) { return res.json(); })
            .then(function(data) { self.events = Array.isArray(data) ? data : []; self.render(); })
            .catch(function() { self.events = []; self.render(); });
    };

    P.render = function() {
        var html = '';
        html += this.renderHead();
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
        h += '  <div class="sa-nav">';
        h += '    <button type="button" class="sa-nav-btn" data-action="prev" aria-label="' + esc(i.prev) + '">‹</button>';
        h += '    <button type="button" class="sa-nav-btn sa-nav-today" data-action="today">' + esc(i.today) + '</button>';
        h += '    <button type="button" class="sa-nav-btn" data-action="next" aria-label="' + esc(i.next) + '">›</button>';
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
            h += '<div class="' + cls + '">';
            h += '<span class="sa-mday__num">' + it.getDate() + '</span>';
            var ev = this.eventsOn(dStr);
            ev.slice(0, 3).forEach(function(e) {
                h += '<span class="sa-mevent">' +
                     (e.start_time ? '<b>' + esc(e.start_time.substring(0, 5)) + '</b> ' : '') +
                     esc(e.title) + '</span>';
            });
            if (ev.length > 3) h += '<span class="sa-mmore">+' + (ev.length - 3) + '</span>';
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
