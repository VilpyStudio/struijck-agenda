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
    }
}
