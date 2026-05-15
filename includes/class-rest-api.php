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
