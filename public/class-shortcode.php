<?php
/**
 * Frontend shortcode: [struijck_agenda]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_Shortcode {

    public static function init() {
        add_shortcode( 'struijck_agenda', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
    }

    public static function register_assets() {
        wp_register_style(
            'struijck-agenda',
            STRUIJCK_AGENDA_URL . 'public/css/agenda.css',
            array(),
            STRUIJCK_AGENDA_VERSION
        );
        wp_register_script(
            'struijck-agenda',
            STRUIJCK_AGENDA_URL . 'public/js/agenda.js',
            array(),
            STRUIJCK_AGENDA_VERSION,
            true
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'view'    => 'month',
            'zaal'    => '',
            'filters' => 'yes',
        ), $atts, 'struijck_agenda' );

        wp_enqueue_style( 'struijck-agenda' );
        wp_enqueue_script( 'struijck-agenda' );

        $instance_id = 'struijck-agenda-' . wp_unique_id();

        // Get zalen for filter.
        $zalen = get_terms( array( 'taxonomy' => 'struijck_zaal', 'hide_empty' => false ) );
        if ( is_wp_error( $zalen ) ) {
            $zalen = array();
        }

        $config = array(
            'restUrl'    => esc_url_raw( rest_url( 'struijck-agenda/v1/occurrences' ) ),
            'initialView' => in_array( $atts['view'], array( 'month', 'week', 'list' ), true ) ? $atts['view'] : 'month',
            'lockedZaal' => $atts['zaal'],
            'showFilters' => 'yes' === $atts['filters'] && empty( $atts['zaal'] ),
            'zalen'      => array_map( function( $t ) {
                return array( 'slug' => $t->slug, 'name' => $t->name );
            }, $zalen ),
            'i18n'       => array(
                'months'     => array(
                    __( 'januari', 'struijck-agenda' ),
                    __( 'februari', 'struijck-agenda' ),
                    __( 'maart', 'struijck-agenda' ),
                    __( 'april', 'struijck-agenda' ),
                    __( 'mei', 'struijck-agenda' ),
                    __( 'juni', 'struijck-agenda' ),
                    __( 'juli', 'struijck-agenda' ),
                    __( 'augustus', 'struijck-agenda' ),
                    __( 'september', 'struijck-agenda' ),
                    __( 'oktober', 'struijck-agenda' ),
                    __( 'november', 'struijck-agenda' ),
                    __( 'december', 'struijck-agenda' ),
                ),
                'weekdaysShort' => array(
                    __( 'ma', 'struijck-agenda' ),
                    __( 'di', 'struijck-agenda' ),
                    __( 'wo', 'struijck-agenda' ),
                    __( 'do', 'struijck-agenda' ),
                    __( 'vr', 'struijck-agenda' ),
                    __( 'za', 'struijck-agenda' ),
                    __( 'zo', 'struijck-agenda' ),
                ),
                'weekdaysLong' => array(
                    __( 'maandag', 'struijck-agenda' ),
                    __( 'dinsdag', 'struijck-agenda' ),
                    __( 'woensdag', 'struijck-agenda' ),
                    __( 'donderdag', 'struijck-agenda' ),
                    __( 'vrijdag', 'struijck-agenda' ),
                    __( 'zaterdag', 'struijck-agenda' ),
                    __( 'zondag', 'struijck-agenda' ),
                ),
                'today'      => __( 'Vandaag', 'struijck-agenda' ),
                'prev'       => __( 'Vorige', 'struijck-agenda' ),
                'next'       => __( 'Volgende', 'struijck-agenda' ),
                'month'      => __( 'Maand', 'struijck-agenda' ),
                'week'       => __( 'Week', 'struijck-agenda' ),
                'list'       => __( 'Lijst', 'struijck-agenda' ),
                'allZalen'   => __( 'Alle zalen', 'struijck-agenda' ),
                'noEvents'   => __( 'Geen activiteiten in deze periode.', 'struijck-agenda' ),
                'loading'    => __( 'Bezig met laden…', 'struijck-agenda' ),
                'close'      => __( 'Sluiten', 'struijck-agenda' ),
                'recurring'  => __( 'Terugkerende activiteit', 'struijck-agenda' ),
                'addToCal'   => __( 'Toevoegen aan kalender', 'struijck-agenda' ),
            ),
        );

        ob_start();
        ?>
        <div class="struijck-agenda" id="<?php echo esc_attr( $instance_id ); ?>"
             data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
            <div class="struijck-agenda__loading"><?php esc_html_e( 'Bezig met laden…', 'struijck-agenda' ); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
