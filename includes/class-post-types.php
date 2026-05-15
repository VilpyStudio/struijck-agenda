<?php
/**
 * Registers the Activiteit custom post type and Zaal taxonomy.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_Post_Types {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );

        // "Mag dubbel verhuurd worden" toggle on zaal terms.
        add_action( 'struijck_zaal_add_form_fields', array( __CLASS__, 'zaal_add_field' ) );
        add_action( 'struijck_zaal_edit_form_fields', array( __CLASS__, 'zaal_edit_field' ) );
        add_action( 'created_struijck_zaal', array( __CLASS__, 'save_zaal_field' ) );
        add_action( 'edited_struijck_zaal', array( __CLASS__, 'save_zaal_field' ) );
    }

    const ALLOW_DOUBLE_META = '_struijck_allow_double';

    public static function zaal_add_field() {
        ?>
        <div class="form-field">
            <label for="struijck_allow_double">
                <input type="checkbox" name="struijck_allow_double" id="struijck_allow_double" value="1" />
                <?php esc_html_e( 'Mag dubbel verhuurd worden (bijv. de kantine)', 'struijck-agenda' ); ?>
            </label>
            <p><?php esc_html_e( 'Aan = meerdere boekingen tegelijk toegestaan. Uit = de zaal kan maar één keer per tijdstip verhuurd worden.', 'struijck-agenda' ); ?></p>
        </div>
        <?php
    }

    public static function zaal_edit_field( $term ) {
        $checked = '1' === get_term_meta( $term->term_id, self::ALLOW_DOUBLE_META, true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="struijck_allow_double"><?php esc_html_e( 'Dubbele boekingen', 'struijck-agenda' ); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" name="struijck_allow_double" id="struijck_allow_double" value="1" <?php checked( $checked ); ?> />
                    <?php esc_html_e( 'Mag dubbel verhuurd worden (bijv. de kantine)', 'struijck-agenda' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'Uit = de zaal kan maar één keer per tijdstip verhuurd worden.', 'struijck-agenda' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_zaal_field( $term_id ) {
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        $value = ! empty( $_POST['struijck_allow_double'] ) ? '1' : '0';
        update_term_meta( $term_id, self::ALLOW_DOUBLE_META, $value );
    }

    public static function register_post_type() {
        $labels = array(
            'name'                  => __( 'Activiteiten', 'struijck-agenda' ),
            'singular_name'         => __( 'Activiteit', 'struijck-agenda' ),
            'menu_name'             => __( 'Agenda', 'struijck-agenda' ),
            'name_admin_bar'        => __( 'Activiteit', 'struijck-agenda' ),
            'add_new'               => __( 'Nieuwe activiteit', 'struijck-agenda' ),
            'add_new_item'          => __( 'Nieuwe activiteit toevoegen', 'struijck-agenda' ),
            'new_item'              => __( 'Nieuwe activiteit', 'struijck-agenda' ),
            'edit_item'             => __( 'Activiteit bewerken', 'struijck-agenda' ),
            'view_item'             => __( 'Activiteit bekijken', 'struijck-agenda' ),
            'all_items'             => __( 'Alle activiteiten', 'struijck-agenda' ),
            'search_items'          => __( 'Zoek activiteiten', 'struijck-agenda' ),
            'not_found'             => __( 'Geen activiteiten gevonden.', 'struijck-agenda' ),
            'not_found_in_trash'    => __( 'Geen activiteiten in prullenbak.', 'struijck-agenda' ),
            'featured_image'        => __( 'Activiteit afbeelding', 'struijck-agenda' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'rest_base'          => 'activiteiten',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'activiteit' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
        );

        register_post_type( 'struijck_activiteit', $args );
    }

    public static function register_taxonomy() {
        $labels = array(
            'name'              => __( 'Zalen', 'struijck-agenda' ),
            'singular_name'     => __( 'Zaal', 'struijck-agenda' ),
            'search_items'      => __( 'Zoek zalen', 'struijck-agenda' ),
            'all_items'         => __( 'Alle zalen', 'struijck-agenda' ),
            'edit_item'         => __( 'Zaal bewerken', 'struijck-agenda' ),
            'update_item'       => __( 'Zaal bijwerken', 'struijck-agenda' ),
            'add_new_item'      => __( 'Nieuwe zaal toevoegen', 'struijck-agenda' ),
            'new_item_name'     => __( 'Naam nieuwe zaal', 'struijck-agenda' ),
            'menu_name'         => __( 'Zalen', 'struijck-agenda' ),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'zaal' ),
        );

        register_taxonomy( 'struijck_zaal', array( 'struijck_activiteit' ), $args );

        $huurder_labels = array(
            'name'          => __( 'Huurders', 'struijck-agenda' ),
            'singular_name' => __( 'Huurder', 'struijck-agenda' ),
            'search_items'  => __( 'Zoek huurders', 'struijck-agenda' ),
            'all_items'     => __( 'Alle huurders', 'struijck-agenda' ),
            'edit_item'     => __( 'Huurder bewerken', 'struijck-agenda' ),
            'update_item'   => __( 'Huurder bijwerken', 'struijck-agenda' ),
            'add_new_item'  => __( 'Nieuwe huurder toevoegen', 'struijck-agenda' ),
            'new_item_name' => __( 'Naam nieuwe huurder', 'struijck-agenda' ),
            'menu_name'     => __( 'Huurders', 'struijck-agenda' ),
        );

        register_taxonomy( 'struijck_huurder', array( 'struijck_activiteit' ), array(
            'hierarchical'      => false,
            'labels'            => $huurder_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'huurder' ),
        ) );
    }
}
