<?php
/**
 * Admin: meta boxes, custom columns, list filters, settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_Admin {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_struijck_activiteit', array( __CLASS__, 'save_meta' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

        add_filter( 'manage_struijck_activiteit_posts_columns', array( __CLASS__, 'add_columns' ) );
        add_action( 'manage_struijck_activiteit_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-struijck_activiteit_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'sort_by_date' ) );

        add_action( 'restrict_manage_posts', array( __CLASS__, 'add_zaal_filter' ) );
        add_filter( 'parse_query', array( __CLASS__, 'filter_by_zaal' ) );

        add_action( 'admin_menu', array( __CLASS__, 'add_help_page' ) );
    }

    public static function enqueue_admin_assets( $hook ) {
        global $post;
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }
        if ( ! $post || 'struijck_activiteit' !== $post->post_type ) {
            return;
        }

        wp_enqueue_style(
            'struijck-agenda-admin',
            STRUIJCK_AGENDA_URL . 'admin/admin.css',
            array(),
            STRUIJCK_AGENDA_VERSION
        );
        wp_enqueue_script(
            'struijck-agenda-admin',
            STRUIJCK_AGENDA_URL . 'admin/admin.js',
            array( 'jquery' ),
            STRUIJCK_AGENDA_VERSION,
            true
        );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'struijck_agenda_datum',
            __( 'Datum & tijd', 'struijck-agenda' ),
            array( __CLASS__, 'render_datum_box' ),
            'struijck_activiteit',
            'normal',
            'high'
        );

        add_meta_box(
            'struijck_agenda_recurring',
            __( 'Terugkerende afspraak', 'struijck-agenda' ),
            array( __CLASS__, 'render_recurring_box' ),
            'struijck_activiteit',
            'normal',
            'high'
        );

        add_meta_box(
            'struijck_agenda_details',
            __( 'Extra details', 'struijck-agenda' ),
            array( __CLASS__, 'render_details_box' ),
            'struijck_activiteit',
            'side',
            'default'
        );
    }

    public static function render_datum_box( $post ) {
        wp_nonce_field( 'struijck_agenda_save', 'struijck_agenda_nonce' );
        $start_date = get_post_meta( $post->ID, '_struijck_start_date', true );
        $start_time = get_post_meta( $post->ID, '_struijck_start_time', true );
        $end_time   = get_post_meta( $post->ID, '_struijck_end_time', true );
        ?>
        <div class="struijck-meta-grid">
            <p>
                <label for="struijck_start_date"><strong><?php esc_html_e( 'Datum', 'struijck-agenda' ); ?></strong></label><br>
                <input type="date" id="struijck_start_date" name="struijck_start_date" value="<?php echo esc_attr( $start_date ); ?>" required>
            </p>
            <p>
                <label for="struijck_start_time"><strong><?php esc_html_e( 'Starttijd', 'struijck-agenda' ); ?></strong></label><br>
                <input type="time" id="struijck_start_time" name="struijck_start_time" value="<?php echo esc_attr( $start_time ); ?>" required>
            </p>
            <p>
                <label for="struijck_end_time"><strong><?php esc_html_e( 'Eindtijd', 'struijck-agenda' ); ?></strong></label><br>
                <input type="time" id="struijck_end_time" name="struijck_end_time" value="<?php echo esc_attr( $end_time ); ?>">
            </p>
        </div>
        <?php
    }

    public static function render_recurring_box( $post ) {
        $recurring  = get_post_meta( $post->ID, '_struijck_recurring', true );
        $frequency  = get_post_meta( $post->ID, '_struijck_recur_frequency', true ) ?: 'weekly';
        $interval   = get_post_meta( $post->ID, '_struijck_recur_interval', true ) ?: 1;
        $weekdays   = get_post_meta( $post->ID, '_struijck_recur_weekdays', true );
        $until      = get_post_meta( $post->ID, '_struijck_recur_until', true );
        $exceptions = get_post_meta( $post->ID, '_struijck_exceptions', true );
        $weekdays_arr = $weekdays ? array_map( 'intval', explode( ',', $weekdays ) ) : array();

        $weekday_labels = array(
            1 => __( 'Ma', 'struijck-agenda' ),
            2 => __( 'Di', 'struijck-agenda' ),
            3 => __( 'Wo', 'struijck-agenda' ),
            4 => __( 'Do', 'struijck-agenda' ),
            5 => __( 'Vr', 'struijck-agenda' ),
            6 => __( 'Za', 'struijck-agenda' ),
            0 => __( 'Zo', 'struijck-agenda' ),
        );
        ?>
        <p>
            <label>
                <input type="checkbox" name="struijck_recurring" value="yes" <?php checked( $recurring, 'yes' ); ?> id="struijck_recurring_toggle">
                <strong><?php esc_html_e( 'Deze activiteit komt terug', 'struijck-agenda' ); ?></strong>
            </label>
        </p>

        <div class="struijck-recurring-options" style="<?php echo 'yes' === $recurring ? '' : 'display:none;'; ?>">
            <p>
                <label><strong><?php esc_html_e( 'Frequentie', 'struijck-agenda' ); ?></strong></label><br>
                <select name="struijck_recur_frequency" id="struijck_recur_frequency">
                    <option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Dagelijks', 'struijck-agenda' ); ?></option>
                    <option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Wekelijks', 'struijck-agenda' ); ?></option>
                    <option value="monthly" <?php selected( $frequency, 'monthly' ); ?>><?php esc_html_e( 'Maandelijks', 'struijck-agenda' ); ?></option>
                </select>
                <?php esc_html_e( 'elke', 'struijck-agenda' ); ?>
                <input type="number" min="1" max="52" name="struijck_recur_interval" value="<?php echo esc_attr( $interval ); ?>" style="width: 60px;">
                <span class="struijck-interval-suffix"><?php esc_html_e( 'periode(s)', 'struijck-agenda' ); ?></span>
            </p>

            <div class="struijck-weekdays" style="<?php echo 'weekly' === $frequency ? '' : 'display:none;'; ?>">
                <p><strong><?php esc_html_e( 'Op welke dagen?', 'struijck-agenda' ); ?></strong></p>
                <p>
                    <?php foreach ( $weekday_labels as $val => $label ) : ?>
                        <label class="struijck-weekday-pill">
                            <input type="checkbox" name="struijck_recur_weekdays[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, $weekdays_arr, true ) ); ?>>
                            <span><?php echo esc_html( $label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </p>
            </div>

            <p>
                <label for="struijck_recur_until"><strong><?php esc_html_e( 'Herhalen tot en met', 'struijck-agenda' ); ?></strong></label><br>
                <input type="date" id="struijck_recur_until" name="struijck_recur_until" value="<?php echo esc_attr( $until ); ?>">
                <em><?php esc_html_e( '(leeg = blijft doorgaan)', 'struijck-agenda' ); ?></em>
            </p>

            <p>
                <label for="struijck_exceptions"><strong><?php esc_html_e( 'Uitzonderingen', 'struijck-agenda' ); ?></strong></label><br>
                <input type="text" id="struijck_exceptions" name="struijck_exceptions" value="<?php echo esc_attr( $exceptions ); ?>" placeholder="2026-12-25, 2026-12-26" style="width:100%;">
                <em><?php esc_html_e( 'Datums waarop deze activiteit NIET plaatsvindt (komma-gescheiden, formaat: JJJJ-MM-DD)', 'struijck-agenda' ); ?></em>
            </p>
        </div>
        <?php
    }

    public static function render_details_box( $post ) {
        $max     = get_post_meta( $post->ID, '_struijck_max_deelnemers', true );
        $contact = get_post_meta( $post->ID, '_struijck_contact', true );
        ?>
        <p>
            <label for="struijck_max_deelnemers"><strong><?php esc_html_e( 'Max. deelnemers', 'struijck-agenda' ); ?></strong></label><br>
            <input type="number" id="struijck_max_deelnemers" name="struijck_max_deelnemers" value="<?php echo esc_attr( $max ); ?>" min="0" style="width:100%;">
        </p>
        <p>
            <label for="struijck_contact"><strong><?php esc_html_e( 'Contactpersoon', 'struijck-agenda' ); ?></strong></label><br>
            <input type="text" id="struijck_contact" name="struijck_contact" value="<?php echo esc_attr( $contact ); ?>" style="width:100%;">
        </p>
        <?php
    }

    public static function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['struijck_agenda_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( wp_unslash( $_POST['struijck_agenda_nonce'] ), 'struijck_agenda_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = array(
            '_struijck_start_date'      => isset( $_POST['struijck_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['struijck_start_date'] ) ) : '',
            '_struijck_start_time'      => isset( $_POST['struijck_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['struijck_start_time'] ) ) : '',
            '_struijck_end_time'        => isset( $_POST['struijck_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['struijck_end_time'] ) ) : '',
            '_struijck_recurring'       => ! empty( $_POST['struijck_recurring'] ) ? 'yes' : 'no',
            '_struijck_recur_frequency' => isset( $_POST['struijck_recur_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['struijck_recur_frequency'] ) ) : '',
            '_struijck_recur_interval'  => isset( $_POST['struijck_recur_interval'] ) ? max( 1, (int) $_POST['struijck_recur_interval'] ) : 1,
            '_struijck_recur_until'     => isset( $_POST['struijck_recur_until'] ) ? sanitize_text_field( wp_unslash( $_POST['struijck_recur_until'] ) ) : '',
            '_struijck_max_deelnemers'  => isset( $_POST['struijck_max_deelnemers'] ) ? (int) $_POST['struijck_max_deelnemers'] : 0,
            '_struijck_contact'         => isset( $_POST['struijck_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['struijck_contact'] ) ) : '',
            '_struijck_exceptions'      => isset( $_POST['struijck_exceptions'] ) ? sanitize_text_field( wp_unslash( $_POST['struijck_exceptions'] ) ) : '',
        );

        // Weekdays array -> CSV string.
        if ( isset( $_POST['struijck_recur_weekdays'] ) && is_array( $_POST['struijck_recur_weekdays'] ) ) {
            $days = array_map( 'intval', wp_unslash( $_POST['struijck_recur_weekdays'] ) );
            $fields['_struijck_recur_weekdays'] = implode( ',', $days );
        } else {
            $fields['_struijck_recur_weekdays'] = '';
        }

        foreach ( $fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

    public static function add_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $val ) {
            if ( 'date' === $key ) {
                $new['struijck_datum']  = __( 'Wanneer', 'struijck-agenda' );
                $new['struijck_zaal']   = __( 'Zaal', 'struijck-agenda' );
                $new['struijck_recur']  = __( 'Herhaling', 'struijck-agenda' );
            }
            $new[ $key ] = $val;
        }
        return $new;
    }

    public static function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'struijck_datum':
                $date = get_post_meta( $post_id, '_struijck_start_date', true );
                $time = get_post_meta( $post_id, '_struijck_start_time', true );
                if ( $date ) {
                    echo esc_html( date_i18n( 'D j M Y', strtotime( $date ) ) );
                    if ( $time ) {
                        echo '<br><small>' . esc_html( $time ) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;
            case 'struijck_zaal':
                $terms = get_the_terms( $post_id, 'struijck_zaal' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $names = wp_list_pluck( $terms, 'name' );
                    echo esc_html( implode( ', ', $names ) );
                } else {
                    echo '—';
                }
                break;
            case 'struijck_recur':
                $recurring = get_post_meta( $post_id, '_struijck_recurring', true );
                if ( 'yes' === $recurring ) {
                    $freq = get_post_meta( $post_id, '_struijck_recur_frequency', true );
                    $map  = array( 'daily' => 'Dagelijks', 'weekly' => 'Wekelijks', 'monthly' => 'Maandelijks' );
                    echo '<span class="struijck-recur-badge">' . esc_html( $map[ $freq ] ?? 'Ja' ) . '</span>';
                } else {
                    echo '<span class="struijck-recur-none">—</span>';
                }
                break;
        }
    }

    public static function sortable_columns( $columns ) {
        $columns['struijck_datum'] = 'struijck_datum';
        return $columns;
    }

    public static function sort_by_date( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        if ( 'struijck_activiteit' !== $query->get( 'post_type' ) ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        if ( 'struijck_datum' === $orderby ) {
            $query->set( 'meta_key', '_struijck_start_date' );
            $query->set( 'orderby', 'meta_value' );
        } elseif ( ! $orderby ) {
            // Default: sort by date.
            $query->set( 'meta_key', '_struijck_start_date' );
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'order', 'ASC' );
        }
    }

    public static function add_zaal_filter() {
        global $typenow;
        if ( 'struijck_activiteit' !== $typenow ) {
            return;
        }
        $current = isset( $_GET['struijck_zaal'] ) ? sanitize_text_field( wp_unslash( $_GET['struijck_zaal'] ) ) : '';
        $terms   = get_terms( array( 'taxonomy' => 'struijck_zaal', 'hide_empty' => false ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return;
        }
        ?>
        <select name="struijck_zaal">
            <option value=""><?php esc_html_e( 'Alle zalen', 'struijck-agenda' ); ?></option>
            <?php foreach ( $terms as $term ) : ?>
                <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>>
                    <?php echo esc_html( $term->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public static function filter_by_zaal( $query ) {
        global $pagenow, $typenow;
        if ( 'edit.php' !== $pagenow || 'struijck_activiteit' !== $typenow ) {
            return;
        }
        if ( ! empty( $_GET['struijck_zaal'] ) ) {
            $query->query_vars['tax_query'] = array(
                array(
                    'taxonomy' => 'struijck_zaal',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( wp_unslash( $_GET['struijck_zaal'] ) ),
                ),
            );
        }
    }

    public static function add_help_page() {
        add_submenu_page(
            'edit.php?post_type=struijck_activiteit',
            __( 'Help & shortcodes', 'struijck-agenda' ),
            __( 'Help & shortcodes', 'struijck-agenda' ),
            'edit_posts',
            'struijck-agenda-help',
            array( __CLASS__, 'render_help_page' )
        );
    }

    public static function render_help_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Struijck Agenda — gebruik op de site', 'struijck-agenda' ); ?></h1>

            <h2><?php esc_html_e( 'Shortcode', 'struijck-agenda' ); ?></h2>
            <p><?php esc_html_e( 'Plaats deze shortcode in een pagina, post of Elementor Shortcode-widget:', 'struijck-agenda' ); ?></p>
            <p><code>[struijck_agenda]</code></p>

            <h3><?php esc_html_e( 'Opties', 'struijck-agenda' ); ?></h3>
            <ul style="list-style:disc; padding-left:20px;">
                <li><code>view="month"</code> — startweergave: <code>month</code>, <code>week</code> of <code>list</code></li>
                <li><code>zaal="zaal-slug"</code> — toon alleen activiteiten van een specifieke zaal</li>
                <li><code>filters="yes"</code> — toon zaal-filter knoppen (standaard: yes)</li>
            </ul>

            <h3><?php esc_html_e( 'Voorbeelden', 'struijck-agenda' ); ?></h3>
            <p><code>[struijck_agenda view="list"]</code></p>
            <p><code>[struijck_agenda view="week" zaal="grote-zaal" filters="no"]</code></p>

            <h2><?php esc_html_e( 'Elementor widget', 'struijck-agenda' ); ?></h2>
            <p><?php esc_html_e( 'Zoek in de Elementor sidebar naar "Struijck Agenda" en sleep het naar je pagina.', 'struijck-agenda' ); ?></p>

            <h2><?php esc_html_e( 'iCal-feed', 'struijck-agenda' ); ?></h2>
            <p><?php esc_html_e( 'Een volledige iCal-feed van alle aankomende activiteiten is beschikbaar op:', 'struijck-agenda' ); ?></p>
            <p><code><?php echo esc_html( home_url( '/?struijck_ical=feed' ) ); ?></code></p>

            <h2><?php esc_html_e( 'REST API', 'struijck-agenda' ); ?></h2>
            <p><?php esc_html_e( 'Voor eigen integraties:', 'struijck-agenda' ); ?></p>
            <p><code><?php echo esc_html( home_url( '/wp-json/struijck-agenda/v1/occurrences?start=2026-01-01&end=2026-12-31' ) ); ?></code></p>
        </div>
        <?php
    }
}
