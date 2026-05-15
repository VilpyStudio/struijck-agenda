<?php
/**
 * Admin Kalender pagina - visuele maandplanner.
 *
 * Een aparte pagina waar de beheerder visueel boekingen kan maken
 * door op dagen te klikken in een maandoverzicht.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_Admin_Calendar {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 5 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // AJAX endpoints voor de kalender UI.
        add_action( 'wp_ajax_struijck_get_month', array( __CLASS__, 'ajax_get_month' ) );
        add_action( 'wp_ajax_struijck_save_booking', array( __CLASS__, 'ajax_save_booking' ) );
        add_action( 'wp_ajax_struijck_delete_booking', array( __CLASS__, 'ajax_delete_booking' ) );
    }

    public static function add_menu() {
        // Verberg het standaard "Nieuwe activiteit" submenu, want we hebben nu de kalender.
        add_submenu_page(
            'edit.php?post_type=struijck_activiteit',
            __( 'Kalender', 'struijck-agenda' ),
            __( '📅 Kalender (planner)', 'struijck-agenda' ),
            'edit_posts',
            'struijck-agenda-calendar',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'struijck-agenda-calendar' ) === false ) {
            return;
        }
        wp_enqueue_style(
            'struijck-admin-calendar',
            STRUIJCK_AGENDA_URL . 'admin/calendar.css',
            array(),
            STRUIJCK_AGENDA_VERSION
        );
        wp_enqueue_script(
            'struijck-admin-calendar',
            STRUIJCK_AGENDA_URL . 'admin/calendar.js',
            array(),
            STRUIJCK_AGENDA_VERSION,
            true
        );

        // Get zalen for the UI, each with a stable color + double-booking flag.
        $palette     = array( '#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#dc2626', '#0d9488', '#9333ea' );
        $zalen_terms = get_terms( array( 'taxonomy' => 'struijck_zaal', 'hide_empty' => false ) );
        $zalen       = array();
        if ( ! is_wp_error( $zalen_terms ) ) {
            $idx = 0;
            foreach ( $zalen_terms as $t ) {
                $zalen[] = array(
                    'id'          => $t->term_id,
                    'slug'        => $t->slug,
                    'name'        => $t->name,
                    'color'       => $palette[ $idx % count( $palette ) ],
                    'allowDouble' => '1' === get_term_meta( $t->term_id, Struijck_Agenda_Post_Types::ALLOW_DOUBLE_META, true ),
                );
                $idx++;
            }
        }

        // Get huurders (renters) for the autocomplete list.
        $huurder_terms = get_terms( array( 'taxonomy' => 'struijck_huurder', 'hide_empty' => false ) );
        $huurders      = array();
        if ( ! is_wp_error( $huurder_terms ) ) {
            foreach ( $huurder_terms as $t ) {
                $huurders[] = $t->name;
            }
            sort( $huurders );
        }

        wp_localize_script( 'struijck-admin-calendar', 'StruijckCalendar', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'struijck_calendar' ),
            'zalen'    => $zalen,
            'huurders' => $huurders,
            'newZaalUrl' => admin_url( 'edit-tags.php?taxonomy=struijck_zaal&post_type=struijck_activiteit' ),
            'i18n'     => array(
                'months'        => array( 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' ),
                'weekdaysShort' => array( 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo' ),
                'weekdaysLong'  => array( 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag' ),
                'today'         => 'Vandaag',
                'newBooking'    => 'Nieuwe boeking',
                'editBooking'   => 'Boeking bewerken',
                'title'         => 'Titel / huurder',
                'zaal'          => 'Zaal',
                'pickZaal'      => 'Kies een zaal…',
                'noZalen'       => 'Nog geen zalen. Voeg er eerst eentje toe →',
                'startTime'     => 'Starttijd',
                'endTime'       => 'Eindtijd',
                'recurring'     => 'Komt elke week terug',
                'recurUntil'    => 'Herhalen tot en met',
                'notes'         => 'Notities (alleen intern)',
                'save'          => 'Opslaan',
                'delete'        => 'Verwijderen',
                'cancel'        => 'Annuleren',
                'close'         => 'Sluiten',
                'confirmDelete' => 'Weet je zeker dat je deze boeking wilt verwijderen?',
                'noEvents'      => 'Geen boekingen op deze dag.',
                'addAnother'    => '+ Nog een boeking toevoegen',
                'recurringNotice' => 'Dit is een terugkerende boeking. Wijzigingen gelden voor alle herhalingen.',
                'errorSaving'   => 'Er ging iets mis bij het opslaan.',
                'saving'        => 'Bezig met opslaan…',
            ),
        ) );
    }

    public static function render_page() {
        ?>
        <div class="wrap struijck-cal-wrap">
            <div class="sc-pagehead">
                <span class="sc-eyebrow"><?php esc_html_e( 'Beheer', 'struijck-agenda' ); ?></span>
                <h1 class="sc-pagetitle"><?php esc_html_e( 'De planner.', 'struijck-agenda' ); ?></h1>
                <p class="sc-pageintro">
                    <?php esc_html_e( 'Klik op een dag om een boeking toe te voegen of te bewerken.', 'struijck-agenda' ); ?>
                </p>
            </div>

            <div id="struijck-calendar-app">
                <div class="struijck-cal-loading"><?php esc_html_e( 'Bezig met laden…', 'struijck-agenda' ); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: ophalen van alle occurrences in een maand-bereik.
     */
    public static function ajax_get_month() {
        check_ajax_referer( 'struijck_calendar', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Geen toegang', 403 );
        }

        $start = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
        $end   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
            wp_send_json_error( 'Ongeldig datumbereik' );
        }

        $occurrences = Struijck_Agenda_Recurring::get_occurrences( $start, $end );
        wp_send_json_success( $occurrences );
    }

    /**
     * AJAX: een boeking aanmaken of bijwerken.
     */
    public static function ajax_save_booking() {
        check_ajax_referer( 'struijck_calendar', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Geen toegang', 403 );
        }

        $id          = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $start_time  = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '';
        $end_time    = isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '';
        $zaal_id     = isset( $_POST['zaal_id'] ) ? (int) $_POST['zaal_id'] : 0;
        $recurring   = ! empty( $_POST['recurring'] );
        $recur_until = isset( $_POST['recur_until'] ) ? sanitize_text_field( wp_unslash( $_POST['recur_until'] ) ) : '';
        $notes       = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

        if ( ! $title || ! $date || ! $start_time ) {
            wp_send_json_error( 'Vul minimaal titel, datum en starttijd in' );
        }

        // Conflictcontrole: een zaal die niet dubbel verhuurd mag worden,
        // kan niet twee overlappende boekingen op hetzelfde tijdstip hebben.
        if ( $zaal_id ) {
            $allow_double = '1' === get_term_meta( $zaal_id, Struijck_Agenda_Post_Types::ALLOW_DOUBLE_META, true );
            if ( ! $allow_double ) {
                $conflict = self::find_conflict( $id, $zaal_id, $date, $start_time, $end_time, $recurring, $recur_until );
                if ( $conflict ) {
                    wp_send_json_error( $conflict );
                }
            }
        }

        $post_data = array(
            'post_type'    => 'struijck_activiteit',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $notes,
        );

        if ( $id ) {
            $post_data['ID'] = $id;
            $post_id         = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( $post_id->get_error_message() );
        }

        // Save meta.
        update_post_meta( $post_id, '_struijck_start_date', $date );
        update_post_meta( $post_id, '_struijck_start_time', $start_time );
        update_post_meta( $post_id, '_struijck_end_time', $end_time );

        if ( $recurring ) {
            update_post_meta( $post_id, '_struijck_recurring', 'yes' );
            update_post_meta( $post_id, '_struijck_recur_frequency', 'weekly' );
            update_post_meta( $post_id, '_struijck_recur_interval', 1 );
            // Bepaal weekdag uit datum.
            $weekday_php = (int) gmdate( 'w', strtotime( $date ) );
            update_post_meta( $post_id, '_struijck_recur_weekdays', (string) $weekday_php );
            update_post_meta( $post_id, '_struijck_recur_until', $recur_until );
        } else {
            update_post_meta( $post_id, '_struijck_recurring', 'no' );
        }

        // Zaal toewijzen.
        if ( $zaal_id ) {
            wp_set_object_terms( $post_id, array( $zaal_id ), 'struijck_zaal' );
        } else {
            wp_set_object_terms( $post_id, array(), 'struijck_zaal' );
        }

        // Huurder vastleggen als term, zodat de naam in de keuzelijst komt
        // en niet telkens opnieuw getypt hoeft te worden.
        wp_set_object_terms( $post_id, $title, 'struijck_huurder', false );

        wp_send_json_success( array( 'id' => $post_id ) );
    }

    /**
     * AJAX: een boeking verwijderen.
     */
    public static function ajax_delete_booking() {
        check_ajax_referer( 'struijck_calendar', 'nonce' );
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( 'Geen toegang', 403 );
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( ! $id ) {
            wp_send_json_error( 'Geen ID' );
        }

        $result = wp_delete_post( $id, true );
        if ( ! $result ) {
            wp_send_json_error( 'Verwijderen mislukt' );
        }

        wp_send_json_success();
    }

    /**
     * Check whether a (possibly recurring) booking overlaps an existing one
     * in the same zaal. Returns a human-readable message on conflict, or ''.
     */
    protected static function find_conflict( $current_id, $zaal_id, $date, $start_time, $end_time, $recurring, $recur_until ) {
        $dates = self::booking_dates( $date, $recurring, $recur_until );
        if ( empty( $dates ) ) {
            return '';
        }
        $date_set = array_flip( $dates );
        $min      = min( $dates );
        $max      = max( $dates );

        $occurrences = Struijck_Agenda_Recurring::get_occurrences( $min, $max, array(
            'tax_query' => array(
                array(
                    'taxonomy' => 'struijck_zaal',
                    'field'    => 'term_id',
                    'terms'    => $zaal_id,
                ),
            ),
        ) );

        $new_start = self::to_min( $start_time );
        $new_end   = self::to_min( $end_time ? $end_time : $start_time );
        $zaal_term = get_term( $zaal_id, 'struijck_zaal' );
        $zaal_name = ( $zaal_term && ! is_wp_error( $zaal_term ) ) ? $zaal_term->name : 'deze zaal';

        foreach ( $occurrences as $o ) {
            if ( (int) $o['id'] === (int) $current_id ) {
                continue;
            }
            if ( ! isset( $date_set[ $o['date'] ] ) ) {
                continue;
            }
            $o_start = self::to_min( $o['start_time'] );
            $o_end   = self::to_min( ! empty( $o['end_time'] ) ? $o['end_time'] : $o['start_time'] );

            if ( self::times_overlap( $new_start, $new_end, $o_start, $o_end ) ) {
                $range = substr( (string) $o['start_time'], 0, 5 );
                if ( ! empty( $o['end_time'] ) ) {
                    $range .= '–' . substr( (string) $o['end_time'], 0, 5 );
                }
                return sprintf(
                    'Dubbele boeking: "%1$s" staat op %2$s al om %3$s in %4$s. De %4$s kan maar één keer per tijdstip verhuurd worden.',
                    $o['title'],
                    $o['date'],
                    $range,
                    $zaal_name
                );
            }
        }

        return '';
    }

    /**
     * The concrete dates a booking occupies (single, or weekly until end).
     */
    protected static function booking_dates( $date, $recurring, $recur_until ) {
        $dates = array( $date );
        if ( ! $recurring ) {
            return $dates;
        }
        $start = strtotime( $date );
        $end   = $recur_until ? strtotime( $recur_until ) : strtotime( '+1 year', $start );
        $cur   = strtotime( '+7 day', $start );
        $i     = 0;
        while ( $cur <= $end && $i < 400 ) {
            $dates[] = gmdate( 'Y-m-d', $cur );
            $cur     = strtotime( '+7 day', $cur );
            $i++;
        }
        return $dates;
    }

    /** "HH:MM" (or "HH:MM:SS") -> minutes since midnight. */
    protected static function to_min( $time ) {
        if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', (string) $time, $m ) ) {
            return 0;
        }
        return ( (int) $m[1] ) * 60 + (int) $m[2];
    }

    protected static function times_overlap( $a_start, $a_end, $b_start, $b_end ) {
        if ( $a_end <= $a_start ) {
            $a_end = $a_start;
        }
        if ( $b_end <= $b_start ) {
            $b_end = $b_start;
        }
        if ( $a_start === $b_start ) {
            return true;
        }
        return $a_start < $b_end && $b_start < $a_end;
    }
}
