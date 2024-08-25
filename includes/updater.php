<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Код для проверки обновлений плагина с использованием JSON
class Auto_Rename_Updater {
    private $update_url = 'https://mitroliti.com/path/to/auto_rename_updates.json';

    public function __construct() {
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'site_transient_update_plugins', array( $this, 'push_update' ) );
        add_action( 'upgrader_process_complete', array( $this, 'after_update' ), 10, 2 );
    }

    public function plugin_info( $res, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $res;
        }

        if ( $args->slug !== 'auto-rename-images' ) {
            return $res;
        }

        $remote = wp_remote_get( $this->update_url, array( 'timeout' => 10 ) );

        if ( ! is_wp_error( $remote ) && wp_remote_retrieve_response_code( $remote ) === 200 ) {
            $remote = json_decode( wp_remote_retrieve_body( $remote ) );
            $res = (object) array(
                'name'          => 'Auto Rename Uploads to Match Post Slug with Updates',
                'slug'          => 'auto-rename-images',
                'version'       => $remote->version,
                'tested'        => $remote->tested,
                'requires'      => $remote->requires,
                'download_link' => $remote->download_url,
                'trunk'         => $remote->download_url,
                'last_updated'  => current_time( 'mysql' ),
                'sections'      => array(
                    'description' => $remote->changelog,
                ),
            );
        }

        return $res;
    }

    public function push_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = wp_remote_get( $this->update_url, array( 'timeout' => 10 ) );

        if ( ! is_wp_error( $remote ) && wp_remote_retrieve_response_code( $remote ) === 200 ) {
            $remote = json_decode( wp_remote_retrieve_body( $remote ) );

            if ( version_compare( $transient->checked['auto-rename-images/auto-rename-images.php'], $remote->version, '<' ) ) {
                $res = array(
                    'slug'        => 'auto-rename-images',
                    'plugin'      => 'auto-rename-images/auto-rename-images.php',
                    'new_version' => $remote->version,
                    'package'     => $remote->download_url,
                    'url'         => '',
                );

                $transient->response[ $res['plugin'] ] = (object) $res;
            }
        }

        return $transient;
    }

    public function after_update( $upgrader_object, $options ) {
        if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            // Дополнительные действия после обновления, если необходимо
        }
    }
}

new Auto_Rename_Updater();
