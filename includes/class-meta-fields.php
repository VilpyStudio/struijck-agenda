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
        '_struijck_all_day'         => 'string',
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

    /**
     * Minutes-since-midnight range an occurrence occupies. A whole-day
     * activity has no times and blocks the zaal from 00:00 to 24:00.
     *
     * @return int[] array( start, end )
     */
    public static function minute_range( $start_time, $end_time, $all_day ) {
        if ( $all_day ) {
            return array( 0, 24 * 60 );
        }
        $start = self::to_min( $start_time );
        $end   = self::to_min( $end_time ? $end_time : $start_time );
        return array( $start, $end );
    }

    /** "HH:MM" (or "HH:MM:SS") -> minutes since midnight. */
    public static function to_min( $time ) {
        if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', (string) $time, $m ) ) {
            return 0;
        }
        return ( (int) $m[1] ) * 60 + (int) $m[2];
    }
}
