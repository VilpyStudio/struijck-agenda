/* ==========================================================================
   Struijck Agenda — Frontend (weekoverzicht)
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
        this.i18n = config.i18n;
        this.currentDate = new Date();         // ankerpunt: bepaalt welke week wordt getoond
        this.activeDayOffset = this.getTodayOffset();
        this.currentZaal = config.lockedZaal || 'all';
        this.events = [];

        this.fetchAndRender();
    }

    /* offset (0=ma..6=zo) van vandaag binnen de getoonde week */
    StruijckAgenda.prototype.getTodayOffset = function() {
        var t = new Date();
        return (t.getDay() + 6) % 7;
    };

    StruijckAgenda.prototype.getWeekStart = function() {
        var d = new Date(this.currentDate);
        var dow = (d.getDay() + 6) % 7; // Ma=0
        d.setDate(d.getDate() - dow);
        d.setHours(0, 0, 0, 0);
        return d;
    };

    StruijckAgenda.prototype.getWeekEnd = function() {
        var s = this.getWeekStart();
        s.setDate(s.getDate() + 6);
        return s;
    };

    StruijckAgenda.prototype.fetchAndRender = function() {
        this.root.innerHTML = '<div class="struijck-agenda__loading">' + esc(this.i18n.loading) + '</div>';

        var self = this;
        var start = ymd(this.getWeekStart());
        var end   = ymd(this.getWeekEnd());

        var url = this.config.restUrl + '?start=' + start + '&end=' + end;
        if (this.currentZaal && this.currentZaal !== 'all') {
            url += '&zaal=' + encodeURIComponent(this.currentZaal);
        }

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                self.events = Array.isArray(data) ? data : [];
                self.render();
            })
            .catch(function() {
                self.events = [];
                self.render();
            });
    };

    StruijckAgenda.prototype.render = function() {
        var html = '';
        var weekStart = this.getWeekStart();
        var weekEnd   = this.getWeekEnd();
        var todayStr  = ymd(new Date());
        var months    = this.i18n.months;
        var weekRange = weekStart.getDate() + ' ' + months[weekStart.getMonth()] + ' – ' +
                        weekEnd.getDate() + ' ' + months[weekEnd.getMonth()] + ' ' + weekEnd.getFullYear();

        // Header
        html += '<div class="sa-week-head">';
        html += '  <div class="sa-week-head__left">';
        html += '    <span class="sa-week-eyebrow">' + esc(this.getWeekLabel()) + '</span>';
        html += '    <span class="sa-week-range">' + esc(weekRange) + '</span>';
        html += '  </div>';
        html += '  <div class="sa-nav">';
        html += '    <button class="sa-nav-btn" data-action="prev" aria-label="' + esc(this.i18n.prev) + '">‹</button>';
        html += '    <button class="sa-nav-btn sa-nav-today" data-action="today">' + esc(this.i18n.today) + '</button>';
        html += '    <button class="sa-nav-btn" data-action="next" aria-label="' + esc(this.i18n.next) + '">›</button>';
        html += '  </div>';
        html += '</div>';

        // Filters
        if (this.config.showFilters && this.config.zalen && this.config.zalen.length > 0) {
            html += '<div class="sa-filters">';
            html += '  <button class="sa-filter-pill' + (this.currentZaal === 'all' ? ' sa-filter-pill--active' : '') + '" data-zaal="all">' + esc(this.i18n.allZalen) + '</button>';
            this.config.zalen.forEach(function(z) {
                var active = this.currentZaal === z.slug ? ' sa-filter-pill--active' : '';
                html += '  <button class="sa-filter-pill' + active + '" data-zaal="' + esc(z.slug) + '">' + esc(z.name) + '</button>';
            }, this);
            html += '</div>';
        }

        // Week card
        html += '<div class="sa-week-card">';

        // Tabs (7 dagen)
        html += '<div class="sa-tabs">';
        for (var i = 0; i < 7; i++) {
            var d = new Date(weekStart);
            d.setDate(weekStart.getDate() + i);
            var dStr = ymd(d);
            var isToday = dStr === todayStr;
            var isActive = i === this.activeDayOffset;
            var classes = 'sa-tab';
            if (isActive) classes += ' sa-tab--active';
            if (isToday) classes += ' sa-tab--today';

            html += '<button class="' + classes + '" data-day-offset="' + i + '">';
            html += '  ' + esc(this.i18n.weekdaysShort[i]);
            html += '  <span class="sa-tab__date">' + d.getDate() + ' ' + esc(months[d.getMonth()].substring(0, 3)) + '</span>';
            html += '</button>';
        }
        html += '</div>';

        // Day content
        var activeDate = new Date(weekStart);
        activeDate.setDate(weekStart.getDate() + this.activeDayOffset);
        var activeDateStr = ymd(activeDate);
        var dayEvents = this.events
            .filter(function(e) { return e.date === activeDateStr; })
            .sort(function(a, b) { return (a.start_time || '').localeCompare(b.start_time || ''); });

        html += '<div class="sa-day-content">';
        if (dayEvents.length === 0) {
            html += '<div class="sa-day-empty">' + esc(this.i18n.noEvents) + '</div>';
        } else {
            dayEvents.forEach(function(e) {
                html += '<div class="sa-day-row">';
                html += '  <div class="sa-day-row__time">';
                html += '    ' + esc(e.start_time ? e.start_time.substring(0, 5) : '');
                if (e.end_time) html += '<span class="sa-day-row__time-end">tot ' + esc(e.end_time.substring(0, 5)) + '</span>';
                html += '  </div>';
                html += '  <div>';
                html += '    <div class="sa-day-row__title">' + esc(e.title) + '</div>';
                if (e.zaal) html += '<div class="sa-day-row__zaal">' + esc(e.zaal) + '</div>';
                html += '  </div>';
                html += '</div>';
            });
        }
        html += '</div>';

        html += '</div>'; // .sa-week-card

        this.root.innerHTML = html;
        this.bindEvents();
    };

    StruijckAgenda.prototype.getWeekLabel = function() {
        var today = new Date();
        var weekStart = this.getWeekStart();
        var weekEnd = this.getWeekEnd();
        if (today >= weekStart && today <= weekEnd) return 'Deze week';
        var thisWeekStart = new Date(today);
        thisWeekStart.setDate(today.getDate() - ((today.getDay() + 6) % 7));
        thisWeekStart.setHours(0, 0, 0, 0);
        var diff = Math.round((weekStart - thisWeekStart) / (1000 * 60 * 60 * 24 * 7));
        if (diff === 1) return 'Volgende week';
        if (diff === -1) return 'Vorige week';
        if (diff > 0) return 'Over ' + diff + ' weken';
        return diff + ' weken geleden';
    };

    StruijckAgenda.prototype.bindEvents = function() {
        var self = this;
        this.root.querySelectorAll('[data-action]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var a = btn.dataset.action;
                var d = new Date(self.currentDate);
                if (a === 'prev') d.setDate(d.getDate() - 7);
                else if (a === 'next') d.setDate(d.getDate() + 7);
                else if (a === 'today') {
                    d = new Date();
                    self.activeDayOffset = self.getTodayOffset();
                }
                self.currentDate = d;
                self.fetchAndRender();
            });
        });

        this.root.querySelectorAll('[data-day-offset]').forEach(function(tab) {
            tab.addEventListener('click', function() {
                self.activeDayOffset = parseInt(tab.dataset.dayOffset, 10);
                self.render();
            });
        });

        this.root.querySelectorAll('[data-zaal]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                self.currentZaal = btn.dataset.zaal;
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
