<?php
/**
 * Plugin Name: Auto Rename Uploads to Match Post Slug with Updates
 * Description: Automatically renames uploaded images to match the post slug, adds product title to image alt text, renames attached images when a product is updated, and updates thumbnails accordingly.
 * Author: Dima Dodonov
 * Version: 1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Подключаем файлы с функциями
require_once plugin_dir_path( __FILE__ ) . 'includes/image-renamer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/content-updater.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/updater.php';

// Настройка задачи Cron
function setup_image_rename_cron() {
    if ( ! wp_next_scheduled( 'image_rename_cron_hook' ) ) {
        wp_schedule_event( time(), 'hourly', 'image_rename_cron_hook' ); // выполняется каждый час
    }
}
add_action( 'wp', 'setup_image_rename_cron' );

// Отмена задачи Cron при деактивации плагина
function remove_image_rename_cron() {
    $timestamp = wp_next_scheduled( 'image_rename_cron_hook' );
    wp_unschedule_event( $timestamp, 'image_rename_cron_hook' );
}
register_deactivation_hook( __FILE__, 'remove_image_rename_cron' );
