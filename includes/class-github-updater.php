<?php
/**
 * Lightweight self-updater that pulls releases from the public GitHub repo
 * and surfaces them on the WordPress plugins screen. No configuration or
 * token required.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_GitHub_Updater {

    const TRANSIENT   = 'struijck_agenda_gh_release';
    const ASSET_NAME  = 'struijck-agenda.zip';
    const CACHE_HOURS = 6;

    public static function init() {
        if ( ! self::repo() ) {
            return;
        }

        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_cache' ), 10, 2 );
    }

    /** owner/repo, e.g. "VilpyStudio/struijck-agenda". */
    private static function repo() {
        return defined( 'STRUIJCK_AGENDA_GITHUB_REPO' ) ? STRUIJCK_AGENDA_GITHUB_REPO : '';
    }

    private static function plugin_basename() {
        return plugin_basename( STRUIJCK_AGENDA_FILE );
    }

    private static function plugin_slug() {
        return dirname( self::plugin_basename() );
    }

    /**
     * Fetch (and cache) the latest GitHub release for the configured repo.
     *
     * @return array|null Parsed release data or null on failure.
     */
    private static function get_release() {
        $cached = get_site_transient( self::TRANSIENT );
        if ( false !== $cached ) {
            return is_array( $cached ) ? $cached : null;
        }

        $url      = 'https://api.github.com/repos/' . self::repo() . '/releases/latest';
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ),
        ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            set_site_transient( self::TRANSIENT, 'none', HOUR_IN_SECONDS );
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            set_site_transient( self::TRANSIENT, 'none', HOUR_IN_SECONDS );
            return null;
        }

        $package = '';
        if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( isset( $asset['name'] ) && self::ASSET_NAME === $asset['name'] ) {
                    $package = $asset['browser_download_url'];
                    break;
                }
            }
        }
        if ( ! $package && ! empty( $body['zipball_url'] ) ) {
            $package = $body['zipball_url'];
        }

        $release = array(
            'version'     => ltrim( $body['tag_name'], 'vV' ),
            'package'     => $package,
            'html_url'    => isset( $body['html_url'] ) ? $body['html_url'] : '',
            'body'        => isset( $body['body'] ) ? (string) $body['body'] : '',
            'published'   => isset( $body['published_at'] ) ? $body['published_at'] : '',
        );

        set_site_transient( self::TRANSIENT, $release, self::CACHE_HOURS * HOUR_IN_SECONDS );
        return $release;
    }

    /**
     * Inject an available update into the plugin update transient.
     */
    public static function check_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $release = self::get_release();
        if ( ! $release || empty( $release['package'] ) ) {
            return $transient;
        }

        if ( ! version_compare( $release['version'], STRUIJCK_AGENDA_VERSION, '>' ) ) {
            return $transient;
        }

        $basename = self::plugin_basename();
        $update   = (object) array(
            'slug'        => self::plugin_slug(),
            'plugin'      => $basename,
            'new_version' => $release['version'],
            'url'         => $release['html_url'],
            'package'     => $release['package'],
        );

        if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
            $transient->response = array();
        }
        $transient->response[ $basename ] = $update;

        return $transient;
    }

    /**
     * Populate the "View details" modal for the plugin.
     */
    public static function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || self::plugin_slug() !== $args->slug ) {
            return $result;
        }

        $release = self::get_release();
        if ( ! $release ) {
            return $result;
        }

        return (object) array(
            'name'          => 'Struijck Agenda',
            'slug'          => self::plugin_slug(),
            'version'       => $release['version'],
            'author'        => 'Studio Vilpy',
            'homepage'      => $release['html_url'],
            'download_link' => $release['package'],
            'sections'      => array(
                'changelog' => $release['body'] ? wpautop( esc_html( $release['body'] ) ) : __( 'Zie GitHub releases.', 'struijck-agenda' ),
            ),
        );
    }

    /**
     * The downloaded archive extracts to a folder named after the asset/zipball
     * rather than the plugin slug. Rename it so WordPress installs in place.
     */
    public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        if ( empty( $hook_extra['plugin'] ) || self::plugin_basename() !== $hook_extra['plugin'] ) {
            return $source;
        }

        $slug = self::plugin_slug();
        if ( basename( untrailingslashit( $source ) ) === $slug ) {
            return $source;
        }

        global $wp_filesystem;
        $desired = trailingslashit( $remote_source ) . $slug . '/';

        if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
            return $desired;
        }

        return $source;
    }

    public static function flush_cache( $upgrader, $data ) {
        if ( isset( $data['type'] ) && 'plugin' === $data['type'] ) {
            delete_site_transient( self::TRANSIENT );
        }
    }
}
