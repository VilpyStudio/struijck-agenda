<?php
/**
 * Recurring activity engine.
 *
 * Generates concrete date occurrences from a recurring activity definition
 * within a given date range. Supports daily, weekly, monthly recurrence
 * with an optional weekday filter and exception dates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_Recurring {

    /**
     * Get all activity occurrences within a date range.
     *
     * @param string $range_start Y-m-d
     * @param string $range_end   Y-m-d
     * @param array  $args        Additional WP_Query args (e.g. taxonomy filter).
     * @return array List of occurrences sorted by date+time.
     */
    public static function get_occurrences( $range_start, $range_end, $args = array() ) {
        $defaults = array(
            'post_type'      => 'struijck_activiteit',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        );
        $query_args = wp_parse_args( $args, $defaults );

        $query       = new WP_Query( $query_args );
        $occurrences = array();

        $range_start_ts = strtotime( $range_start );
        $range_end_ts   = strtotime( $range_end );

        if ( false === $range_start_ts || false === $range_end_ts ) {
            return array();
        }

        foreach ( $query->posts as $post ) {
            $meta = Struijck_Agenda_Meta_Fields::get_activity_meta( $post->ID );

            $start_date = isset( $meta['start_date'] ) ? $meta['start_date'] : '';
            if ( ! $start_date ) {
                continue;
            }

            $start_ts = strtotime( $start_date );
            if ( false === $start_ts ) {
                continue;
            }

            $is_recurring = ! empty( $meta['recurring'] ) && 'yes' === $meta['recurring'];

            if ( ! $is_recurring ) {
                // Single occurrence.
                if ( $start_ts >= $range_start_ts && $start_ts <= $range_end_ts ) {
                    $occurrences[] = self::build_occurrence( $post, $meta, $start_date );
                }
                continue;
            }

            // Recurring: generate occurrences.
            $generated = self::generate_recurring_dates(
                $start_date,
                $meta,
                $range_start,
                $range_end
            );

            $exceptions = array();
            if ( ! empty( $meta['exceptions'] ) ) {
                $exceptions = array_map( 'trim', explode( ',', $meta['exceptions'] ) );
            }

            foreach ( $generated as $date ) {
                if ( in_array( $date, $exceptions, true ) ) {
                    continue;
                }
                $occurrences[] = self::build_occurrence( $post, $meta, $date );
            }
        }

        // Sort by date+time.
        usort( $occurrences, function( $a, $b ) {
            $cmp = strcmp( $a['date'], $b['date'] );
            if ( 0 !== $cmp ) {
                return $cmp;
            }
            return strcmp( $a['start_time'], $b['start_time'] );
        } );

        return $occurrences;
    }

    /**
     * Generate concrete dates for a recurring activity.
     */
    protected static function generate_recurring_dates( $start_date, $meta, $range_start, $range_end ) {
        $frequency = ! empty( $meta['recur_frequency'] ) ? $meta['recur_frequency'] : 'weekly';
        $interval  = ! empty( $meta['recur_interval'] ) ? max( 1, (int) $meta['recur_interval'] ) : 1;
        $until     = ! empty( $meta['recur_until'] ) ? $meta['recur_until'] : '';
        $weekdays  = array();

        if ( ! empty( $meta['recur_weekdays'] ) ) {
            $weekdays = array_map( 'intval', explode( ',', $meta['recur_weekdays'] ) );
        }

        $range_end_ts = strtotime( $range_end );
        $until_ts     = $until ? strtotime( $until ) : null;

        // The hard cap: whichever comes first.
        $hard_end_ts = $range_end_ts;
        if ( $until_ts && $until_ts < $hard_end_ts ) {
            $hard_end_ts = $until_ts;
        }

        $dates = array();
        $current = strtotime( $start_date );

        // Safety cap to prevent infinite loops.
        $max_iterations = 5000;
        $i = 0;

        while ( $current <= $hard_end_ts && $i < $max_iterations ) {
            $i++;

            $matches = true;

            if ( 'weekly' === $frequency && ! empty( $weekdays ) ) {
                // Day-of-week: 0=Sunday, 1=Monday, ... 6=Saturday.
                $dow     = (int) gmdate( 'w', $current );
                $matches = in_array( $dow, $weekdays, true );
            }

            if ( $matches && $current >= strtotime( $range_start ) ) {
                $dates[] = gmdate( 'Y-m-d', $current );
            }

            switch ( $frequency ) {
                case 'daily':
                    $current = strtotime( '+' . $interval . ' day', $current );
                    break;
                case 'weekly':
                    if ( ! empty( $weekdays ) ) {
                        // Advance day by day; the weekday filter handles the rest.
                        $current = strtotime( '+1 day', $current );
                    } else {
                        $current = strtotime( '+' . $interval . ' week', $current );
                    }
                    break;
                case 'monthly':
                    $current = strtotime( '+' . $interval . ' month', $current );
                    break;
                default:
                    $current = strtotime( '+1 week', $current );
                    break;
            }
        }

        return $dates;
    }

    /**
     * Build a single occurrence array.
     */
    protected static function build_occurrence( $post, $meta, $date ) {
        $terms = wp_get_post_terms( $post->ID, 'struijck_zaal', array( 'fields' => 'names' ) );
        $zaal  = ! is_wp_error( $terms ) && ! empty( $terms ) ? implode( ', ', $terms ) : '';

        return array(
            'id'             => $post->ID,
            'title'          => get_the_title( $post ),
            'date'           => $date,
            'start_time'     => isset( $meta['start_time'] ) ? $meta['start_time'] : '',
            'end_time'       => isset( $meta['end_time'] ) ? $meta['end_time'] : '',
            'zaal'           => $zaal,
            'description'    => wp_strip_all_tags( $post->post_content ),
            'permalink'      => get_permalink( $post ),
            'max_deelnemers' => isset( $meta['max_deelnemers'] ) ? (int) $meta['max_deelnemers'] : 0,
            'contact'        => isset( $meta['contact'] ) ? $meta['contact'] : '',
            'is_recurring'   => ! empty( $meta['recurring'] ) && 'yes' === $meta['recurring'],
        );
    }
}
