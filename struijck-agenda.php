<?php
/**
 * Plugin Name: Struijck Agenda
 * Plugin URI:  https://github.com/VilpyStudio/struijck-agenda
 * Description: Agenda- en activiteitenbeheer voor sporthal De Struijck. Beheer activiteiten per zaal, met ondersteuning voor terugkerende afspraken, en toon de agenda op de site via shortcode of Elementor widget.
 * Version:     1.5.0
 * Author:      Studio Vilpy
 * Author URI:  https://github.com/VilpyStudio
 * License:     GPL-2.0+
 * Text Domain: struijck-agenda
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STRUIJCK_AGENDA_VERSION', '1.5.0' );
define( 'STRUIJCK_AGENDA_FILE', __FILE__ );
define( 'STRUIJCK_AGENDA_DIR', plugin_dir_path( __FILE__ ) );
define( 'STRUIJCK_AGENDA_URL', plugin_dir_url( __FILE__ ) );

// owner/repo of the GitHub repository used by the lightweight self-updater.
define( 'STRUIJCK_AGENDA_GITHUB_REPO', 'VilpyStudio/struijck-agenda' );

require_once STRUIJCK_AGENDA_DIR . 'includes/class-post-types.php';
require_once STRUIJCK_AGENDA_DIR . 'includes/class-meta-fields.php';
require_once STRUIJCK_AGENDA_DIR . 'includes/class-recurring.php';
require_once STRUIJCK_AGENDA_DIR . 'includes/class-rest-api.php';
require_once STRUIJCK_AGENDA_DIR . 'includes/class-ical.php';
require_once STRUIJCK_AGENDA_DIR . 'admin/class-admin.php';
require_once STRUIJCK_AGENDA_DIR . 'admin/class-admin-calendar.php';
require_once STRUIJCK_AGENDA_DIR . 'public/class-shortcode.php';
require_once STRUIJCK_AGENDA_DIR . 'includes/class-github-updater.php';

/**
 * Bootstrap the plugin.
 */
function struijck_agenda_init() {
    Struijck_Agenda_Post_Types::init();
    Struijck_Agenda_Meta_Fields::init();
    Struijck_Agenda_Admin::init();
    Struijck_Agenda_Admin_Calendar::init();
    Struijck_Agenda_Shortcode::init();
    Struijck_Agenda_REST_API::init();
    Struijck_Agenda_ICal::init();
    Struijck_Agenda_GitHub_Updater::init();

    load_plugin_textdomain( 'struijck-agenda', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'struijck_agenda_init' );

/**
 * Register Elementor widget if Elementor is available.
 */
function struijck_agenda_register_elementor_widget() {
    if ( ! did_action( 'elementor/loaded' ) ) {
        return;
    }
    require_once STRUIJCK_AGENDA_DIR . 'public/class-elementor-widget.php';

    add_action( 'elementor/widgets/register', function( $widgets_manager ) {
        $widgets_manager->register( new \Struijck_Agenda_Elementor_Widget() );
    } );
}
add_action( 'init', 'struijck_agenda_register_elementor_widget', 20 );

/**
 * Activation: register post types, then flush rewrite rules.
 */
register_activation_hook( __FILE__, function() {
    require_once STRUIJCK_AGENDA_DIR . 'includes/class-post-types.php';
    Struijck_Agenda_Post_Types::register_post_type();
    Struijck_Agenda_Post_Types::register_taxonomy();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );
