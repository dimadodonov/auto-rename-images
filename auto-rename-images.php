<?php
/**
 * Plugin Name: Auto Rename Uploads to Match Post Slug with Updates
 * Description: Automatically renames uploaded images to match the post slug and also renames attached images when a product is updated.
 * Author: Dima Dodonov
 * Version: 0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Переименование новых изображений при загрузке
function auto_rename_uploads_to_post_slug( $filename ) {
    $info = pathinfo( $filename );
    $ext  = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
    $name = basename( $filename, $ext );

    if ( isset( $_REQUEST['post_id'] ) && is_numeric( $_REQUEST['post_id'] ) ) {
        $postObj = get_post( $_REQUEST['post_id'] );
        if ( $postObj ) {
            $postSlug = sanitize_title( $postObj->post_name );
        }
    }

    if ( isset( $postSlug ) && ! empty( $postSlug ) && $postSlug != 'auto-draft' ) {
        $finalFileName = $postSlug;
    } else {
        $finalFileName = sanitize_title( $name );
    }

    $upload_dir = wp_upload_dir();
    $i = 1;
    $base_name = $finalFileName;
    while ( file_exists( $upload_dir['path'] . '/' . $finalFileName . $ext ) ) {
        $finalFileName = $base_name . '-' . $i++;
    }

    return $finalFileName . $ext;
}

add_filter( 'sanitize_file_name', 'auto_rename_uploads_to_post_slug', 100 );

// Функция для настройки задачи Cron
function setup_image_rename_cron() {
    if ( ! wp_next_scheduled( 'image_rename_cron_hook' ) ) {
        wp_schedule_event( time(), 'hourly', 'image_rename_cron_hook' ); // выполняется каждый час
    }
}
add_action( 'wp', 'setup_image_rename_cron' );

// Функция для отмены задачи Cron при деактивации плагина
function remove_image_rename_cron() {
    $timestamp = wp_next_scheduled( 'image_rename_cron_hook' );
    wp_unschedule_event( $timestamp, 'image_rename_cron_hook' );
}
register_deactivation_hook( __FILE__, 'remove_image_rename_cron' );

// Функция для переименования одного изображения
function auto_rename_single_image( $image_id ) {
    // Получаем полный путь к файлу
    $file = get_attached_file( $image_id );
    // Получаем информацию о посте
    $postObj = get_post( $image_id );
    // Генерируем слаг из названия поста
    $postSlug = sanitize_title( $postObj->post_name );

    // Проверяем, если путь к файлу или слаг пустые, выходим
    if ( empty( $file ) || empty( $postSlug ) ) {
        error_log("Auto Rename: Путь к файлу или слаг пустые.");
        return;
    }

    // Получаем информацию о файле
    $info = pathinfo( $file );
    $ext  = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
    $upload_dir = wp_upload_dir();
    $new_file_name = $postSlug;
    $i = 1;

    // Проверяем наличие файла, чтобы избежать конфликта имен
    while ( file_exists( $upload_dir['path'] . '/' . $new_file_name . $ext ) ) {
        $new_file_name = $postSlug . '-' . $i++;
    }

    $new_file_path = $upload_dir['path'] . '/' . $new_file_name . $ext;

    // Переименование файла
    if ( rename( $file, $new_file_path ) ) {
        update_attached_file( $image_id, $new_file_path );
        error_log("Auto Rename: Файл успешно переименован в $new_file_path.");
    } else {
        error_log("Auto Rename: Ошибка переименования файла.");
    }

    // Освобождаем память
    unset($file);
    unset($info);
    wp_cache_flush();
}



// Функция для пакетного переименования изображений с использованием Cron
function image_rename_cron_function() {
    global $wpdb;

    $images = $wpdb->get_results( "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_status = 'publish' LIMIT 5" );

    foreach ( $images as $image ) {
        auto_rename_single_image( $image->ID );
    }
}
add_action( 'image_rename_cron_hook', 'image_rename_cron_function' );
