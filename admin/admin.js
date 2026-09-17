(function($) {
    'use strict';

    $(document).ready(function() {

        // Hele dag: geen tijden nodig.
        $('#struijck_all_day').on('change', function() {
            $('.struijck-time-field').toggle(!this.checked);
            $('#struijck_start_time').prop('required', !this.checked);
        }).trigger('change');

        // Toggle recurring options.
        $('#struijck_recurring_toggle').on('change', function() {
            $('.struijck-recurring-options').toggle(this.checked);
        });

        // Show/hide weekday picker based on frequency.
        $('#struijck_recur_frequency').on('change', function() {
            var freq = $(this).val();
            $('.struijck-weekdays').toggle(freq === 'weekly');

            var suffix = {
                'daily': 'dag(en)',
                'weekly': 'week (weken)',
                'monthly': 'maand(en)'
            };
            $('.struijck-interval-suffix').text(suffix[freq] || 'periode(s)');
        }).trigger('change');

    });

})(jQuery);
