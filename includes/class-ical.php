<?php
/**
 * iCal export for activities.
 *
 * Endpoint: /?struijck_ical=1&id=POST_ID  -> single activity (with recurrence rule)
 *           /?struijck_ical=feed          -> all activities feed
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_ICal {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_ical' ) );
    }

    public static function add_rewrite() {
        add_rewrite_endpoint( 'struijck_ical', EP_ROOT );
    }

    public static function maybe_serve_ical() {
        if ( ! isset( $_GET['struijck_ical'] ) ) {
            return;
        }

        $mode = sanitize_text_field( wp_unslash( $_GET['struijck_ical'] ) );

        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="struijck-agenda.ics"' );

        $lines = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Sporthal De Struijck//Agenda//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        );

        if ( 'feed' === $mode ) {
            $range_start = gmdate( 'Y-m-d' );
            $range_end   = gmdate( 'Y-m-d', strtotime( '+1 year' ) );
            $occurrences = Struijck_Agenda_Recurring::get_occurrences( $range_start, $range_end );

            foreach ( $occurrences as $occ ) {
                $lines = array_merge( $lines, self::occurrence_to_vevent( $occ ) );
            }
        } else {
            $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
            if ( $id ) {
                $post = get_post( $id );
                if ( $post && 'struijck_activiteit' === $post->post_type ) {
                    $meta = Struijck_Agenda_Meta_Fields::get_activity_meta( $id );
                    $lines = array_merge( $lines, self::activity_to_vevent( $post, $meta ) );
                }
            }
        }

        $lines[] = 'END:VCALENDAR';

        echo implode( "\r\n", $lines );
        exit;
    }

    protected static function occurrence_to_vevent( $occ ) {
        $start_dt = self::format_dt( $occ['date'], $occ['start_time'] );
        $end_dt   = self::format_dt( $occ['date'], $occ['end_time'] ?: $occ['start_time'] );

        return array(
            'BEGIN:VEVENT',
            'UID:' . $occ['id'] . '-' . $occ['date'] . '@' . self::uid_domain(),
            'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
            'DTSTART:' . $start_dt,
            'DTEND:' . $end_dt,
            'SUMMARY:' . self::escape_text( $occ['title'] ),
            'LOCATION:' . self::escape_text( $occ['zaal'] ),
            'DESCRIPTION:' . self::escape_text( $occ['description'] ),
            'END:VEVENT',
        );
    }

    protected static function activity_to_vevent( $post, $meta ) {
        $start = self::format_dt( $meta['start_date'], $meta['start_time'] );
        $end   = self::format_dt( $meta['start_date'], $meta['end_time'] ?: $meta['start_time'] );

        $terms = wp_get_post_terms( $post->ID, 'struijck_zaal', array( 'fields' => 'names' ) );
        $zaal  = ! is_wp_error( $terms ) && ! empty( $terms ) ? implode( ', ', $terms ) : '';

        $event = array(
            'BEGIN:VEVENT',
            'UID:' . $post->ID . '@' . self::uid_domain(),
            'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
            'DTSTART:' . $start,
            'DTEND:' . $end,
            'SUMMARY:' . self::escape_text( get_the_title( $post ) ),
            'LOCATION:' . self::escape_text( $zaal ),
            'DESCRIPTION:' . self::escape_text( wp_strip_all_tags( $post->post_content ) ),
        );

        if ( ! empty( $meta['recurring'] ) && 'yes' === $meta['recurring'] ) {
            $rrule = self::build_rrule( $meta );
            if ( $rrule ) {
                $event[] = 'RRULE:' . $rrule;
            }
        }

        $event[] = 'END:VEVENT';
        return $event;
    }

    protected static function build_rrule( $meta ) {
        $freq_map = array(
            'daily'   => 'DAILY',
            'weekly'  => 'WEEKLY',
            'monthly' => 'MONTHLY',
        );
        $frequency = ! empty( $meta['recur_frequency'] ) ? $meta['recur_frequency'] : 'weekly';
        $freq      = isset( $freq_map[ $frequency ] ) ? $freq_map[ $frequency ] : 'WEEKLY';

        $parts = array( 'FREQ=' . $freq );

        $interval = ! empty( $meta['recur_interval'] ) ? max( 1, (int) $meta['recur_interval'] ) : 1;
        if ( $interval > 1 ) {
            $parts[] = 'INTERVAL=' . $interval;
        }

        if ( 'weekly' === $frequency && ! empty( $meta['recur_weekdays'] ) ) {
            $days_map = array( 0 => 'SU', 1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA' );
            $days     = array();
            foreach ( array_map( 'intval', explode( ',', $meta['recur_weekdays'] ) ) as $d ) {
                if ( isset( $days_map[ $d ] ) ) {
                    $days[] = $days_map[ $d ];
                }
            }
            if ( $days ) {
                $parts[] = 'BYDAY=' . implode( ',', $days );
            }
        }

        if ( ! empty( $meta['recur_until'] ) ) {
            $parts[] = 'UNTIL=' . gmdate( 'Ymd\T235959\Z', strtotime( $meta['recur_until'] ) );
        }

        return implode( ';', $parts );
    }

    protected static function format_dt( $date, $time ) {
        if ( ! $time ) {
            $time = '00:00';
        }
        $ts = strtotime( $date . ' ' . $time );
        return gmdate( 'Ymd\THis', $ts );
    }

    /**
     * Domain used for iCal UIDs. Derived from the site's own URL so UIDs are
     * stable per install and never hardcode a specific domain.
     */
    protected static function uid_domain() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return $host ? $host : 'struijck-agenda.local';
    }

    protected static function escape_text( $text ) {
        $text = str_replace( array( "\\", ";", ",", "\n", "\r" ), array( "\\\\", "\\;", "\\,", "\\n", "" ), $text );
        return $text;
    }
}
