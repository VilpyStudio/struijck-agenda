<?php
/**
 * Meta fields for activities: date, time, recurring options, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_Meta_Fields {

    const META_KEYS = array(
        '_struijck_start_date'      => 'string',
        '_struijck_start_time'      => 'string',
        '_struijck_end_time'        => 'string',
        '_struijck_recurring'       => 'string',
        '_struijck_recur_frequency' => 'string',
        '_struijck_recur_interval'  => 'integer',
        '_struijck_recur_weekdays'  => 'string',
        '_struijck_recur_until'     => 'string',
        '_struijck_max_deelnemers'  => 'integer',
        '_struijck_contact'         => 'string',
        '_struijck_exceptions'      => 'string',
    );

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_meta' ) );
    }

    public static function register_meta() {
        foreach ( self::META_KEYS as $key => $type ) {
            register_post_meta( 'struijck_activiteit', $key, array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => $type,
                'auth_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            ) );
        }
    }

    /**
     * Get all relevant meta for an activity in one go.
     */
    public static function get_activity_meta( $post_id ) {
        $meta = array();
        foreach ( array_keys( self::META_KEYS ) as $key ) {
            $clean_key       = substr( $key, strlen( '_struijck_' ) );
            $meta[ $clean_key ] = get_post_meta( $post_id, $key, true );
        }
        return $meta;
    }
}
