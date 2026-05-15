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

        // Get zalen for the UI.
        $zalen_terms = get_terms( array( 'taxonomy' => 'struijck_zaal', 'hide_empty' => false ) );
        $zalen       = array();
        if ( ! is_wp_error( $zalen_terms ) ) {
            foreach ( $zalen_terms as $t ) {
                $zalen[] = array( 'id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name );
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
            <h1 class="struijck-cal-pagetitle">
                📅 <?php esc_html_e( 'Agenda planner', 'struijck-agenda' ); ?>
            </h1>
            <p class="struijck-cal-intro">
                <?php esc_html_e( 'Klik op een dag om een boeking toe te voegen of te bewerken.', 'struijck-agenda' ); ?>
            </p>

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
}
