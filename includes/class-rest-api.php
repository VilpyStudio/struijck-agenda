<?php
/**
 * REST API endpoint for fetching activity occurrences.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_REST_API {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( 'struijck-agenda/v1', '/occurrences', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_occurrences' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'start' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( __CLASS__, 'validate_date' ),
                ),
                'end'   => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( __CLASS__, 'validate_date' ),
                ),
                'zaal'  => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( 'struijck-agenda/v1', '/zalen', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_zalen' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'struijck-agenda/v1', '/request', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'submit_request' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Public booking request → stored as a 'pending' activity for the
     * beheerder to approve in WordPress, plus an e-mail notification.
     */
    public static function submit_request( WP_REST_Request $request ) {
        $p = $request->get_json_params();
        if ( ! is_array( $p ) ) {
            $p = $request->get_params();
        }

        // Spam: honeypot must stay empty.
        if ( ! empty( $p['website'] ) ) {
            return new WP_REST_Response( array( 'success' => true ), 200 );
        }

        // Nonce.
        $nonce = isset( $p['nonce'] ) ? $p['nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'struijck_request' ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Beveiligingscontrole mislukt. Herlaad de pagina en probeer opnieuw.' ), 403 );
        }

        // Light throttle: max 5 aanvragen per uur per IP.
        $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
        $key = 'struijck_req_' . md5( $ip );
        $cnt = (int) get_transient( $key );
        if ( $cnt >= 5 ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Te veel aanvragen. Probeer het later opnieuw.' ), 429 );
        }

        $naam      = isset( $p['naam'] ) ? sanitize_text_field( $p['naam'] ) : '';
        $email     = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
        $telefoon  = isset( $p['telefoon'] ) ? sanitize_text_field( $p['telefoon'] ) : '';
        $zaal_slug = isset( $p['zaal'] ) ? sanitize_title( $p['zaal'] ) : '';
        $date      = isset( $p['date'] ) ? sanitize_text_field( $p['date'] ) : '';
        $start     = isset( $p['start_time'] ) ? sanitize_text_field( $p['start_time'] ) : '';
        $end       = isset( $p['end_time'] ) ? sanitize_text_field( $p['end_time'] ) : '';
        $opmerking = isset( $p['opmerking'] ) ? sanitize_textarea_field( $p['opmerking'] ) : '';

        if ( ! $naam || ! is_email( $email ) || ! self::validate_date( $date ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $start ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Vul naam, geldig e-mailadres, datum en starttijd in.' ), 400 );
        }
        if ( $end && preg_match( '/^\d{1,2}:\d{2}$/', $end ) && self::to_min( $end ) <= self::to_min( $start ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'De eindtijd moet na de starttijd liggen.' ), 400 );
        }

        // Conflictcontrole tegen bestaande boekingen (tenzij de zaal dubbel mag).
        $zaal_term = $zaal_slug ? get_term_by( 'slug', $zaal_slug, 'struijck_zaal' ) : null;
        if ( $zaal_term && ! is_wp_error( $zaal_term ) ) {
            $allow_double = '1' === get_term_meta( $zaal_term->term_id, Struijck_Agenda_Post_Types::ALLOW_DOUBLE_META, true );
            if ( ! $allow_double ) {
                $occ = Struijck_Agenda_Recurring::get_occurrences( $date, $date, array(
                    'tax_query' => array( array(
                        'taxonomy' => 'struijck_zaal',
                        'field'    => 'term_id',
                        'terms'    => $zaal_term->term_id,
                    ) ),
                ) );
                $ns = self::to_min( $start );
                $ne = self::to_min( $end ? $end : $start );
                foreach ( $occ as $o ) {
                    $os = self::to_min( $o['start_time'] );
                    $oe = self::to_min( ! empty( $o['end_time'] ) ? $o['end_time'] : $o['start_time'] );
                    $a_e = $ne <= $ns ? $ns : $ne;
                    $b_e = $oe <= $os ? $os : $oe;
                    if ( $ns === $os || ( $ns < $b_e && $os < $a_e ) ) {
                        return new WP_REST_Response( array(
                            'success' => false,
                            'message' => sprintf( 'Helaas, %s is op %s rond die tijd al bezet. Kies een ander tijdslot.', $zaal_term->name, $date ),
                        ), 409 );
                    }
                }
            }
        }

        $post_id = wp_insert_post( array(
            'post_type'    => 'struijck_activiteit',
            'post_status'  => 'pending',
            'post_title'   => $naam,
            'post_content' => $opmerking,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Er ging iets mis. Probeer het later opnieuw.' ), 500 );
        }

        update_post_meta( $post_id, '_struijck_start_date', $date );
        update_post_meta( $post_id, '_struijck_start_time', $start );
        update_post_meta( $post_id, '_struijck_end_time', $end );
        update_post_meta( $post_id, '_struijck_recurring', 'no' );
        update_post_meta( $post_id, '_struijck_contact', $email . ( $telefoon ? ' / ' . $telefoon : '' ) );
        update_post_meta( $post_id, '_struijck_is_request', '1' );
        if ( $zaal_term && ! is_wp_error( $zaal_term ) ) {
            wp_set_object_terms( $post_id, array( (int) $zaal_term->term_id ), 'struijck_zaal' );
        }

        set_transient( $key, $cnt + 1, HOUR_IN_SECONDS );

        $edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        $zaal_name = ( $zaal_term && ! is_wp_error( $zaal_term ) ) ? $zaal_term->name : '—';
        $body  = "Nieuwe aanvraag via de website:\n\n";
        $body .= "Naam/huurder: {$naam}\n";
        $body .= "E-mail: {$email}\n";
        $body .= "Telefoon: " . ( $telefoon ? $telefoon : '—' ) . "\n";
        $body .= "Zaal: {$zaal_name}\n";
        $body .= "Datum: {$date}\n";
        $body .= "Tijd: {$start}" . ( $end ? " - {$end}" : '' ) . "\n";
        $body .= "Opmerking: " . ( $opmerking ? $opmerking : '—' ) . "\n\n";
        $body .= "Beoordeel en keur goed:\n{$edit_link}\n";
        wp_mail(
            get_option( 'admin_email' ),
            'Nieuwe agenda-aanvraag: ' . $naam . ' (' . $date . ')',
            $body
        );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Bedankt! Je aanvraag is verstuurd. De beheerder neemt contact op zodra deze is beoordeeld.',
        ), 200 );
    }

    private static function to_min( $time ) {
        if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', (string) $time, $m ) ) {
            return 0;
        }
        return ( (int) $m[1] ) * 60 + (int) $m[2];
    }

    public static function validate_date( $value ) {
        return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
    }

    public static function get_occurrences( WP_REST_Request $request ) {
        $start = $request->get_param( 'start' );
        $end   = $request->get_param( 'end' );
        $zaal  = $request->get_param( 'zaal' );

        $args = array();
        if ( $zaal && 'all' !== $zaal ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'struijck_zaal',
                    'field'    => 'slug',
                    'terms'    => $zaal,
                ),
            );
        }

        $occurrences = Struijck_Agenda_Recurring::get_occurrences( $start, $end, $args );

        return rest_ensure_response( $occurrences );
    }

    public static function get_zalen() {
        $terms = get_terms( array(
            'taxonomy'   => 'struijck_zaal',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) ) {
            return rest_ensure_response( array() );
        }

        $result = array();
        foreach ( $terms as $term ) {
            $result[] = array(
                'id'    => $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'count' => $term->count,
            );
        }
        return rest_ensure_response( $result );
    }
}
