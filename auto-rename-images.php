<?php
/**
 * Plugin Name: Auto Rename Uploads to Match Post Slug with Updates
 * Description: Automatically renames uploaded images to match the post slug, adds product title to image alt text, and renames attached images when a product is updated.
 * Author: Dima Dodonov
 * Version: 0.7
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

// Функция для переименования изображений и добавления alt-текста
function auto_rename_and_set_alt_for_image( $image_id ) {
    $file = get_attached_file( $image_id );

    // Получаем объект поста-родителя (товара)
    $parent_post_id = wp_get_post_parent_id( $image_id );
    if ( !$parent_post_id ) {
        error_log("Auto Rename: У изображения с ID $image_id нет родительского поста.");
        return;
    }

    $postObj = get_post( $parent_post_id );
    $postSlug = sanitize_title( $postObj->post_name );
    $postTitle = $postObj->post_title;

    if ( empty( $file ) || empty( $postSlug ) ) {
        error_log("Auto Rename: Путь к файлу или слаг пустые для изображения с ID $image_id.");
        return;
    }

    // Проверяем и устанавливаем alt-текст
    $current_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
    if ( strpos( $current_alt, $postTitle ) === false ) {
        update_post_meta( $image_id, '_wp_attachment_image_alt', $postTitle );
        error_log("Auto Rename: Alt-текст для изображения с ID $image_id установлен как '$postTitle'.");
    } else {
        error_log("Auto Rename: Alt-текст для изображения с ID $image_id уже содержит название товара.");
    }

    error_log("Auto Rename: Переименование изображения с ID $image_id. Текущий путь: $file, Новый слаг: $postSlug.");

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
        error_log("Auto Rename: Ошибка переименования файла $file в $new_file_path.");
    }

    // Освобождаем память
    unset($file);
    unset($info);
    wp_cache_flush();
}

// Функция для обработки всех изображений при обновлении товара
function auto_rename_and_update_images_on_post_save( $post_id ) {
    if ( get_post_type( $post_id ) !== 'product' ) {
        return;
    }

    error_log("Auto Rename: Обновление товара с ID $post_id начато.");

    $attachments = get_children( array(
        'post_parent'    => $post_id,
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
    ));

    if ( empty( $attachments ) ) {
        error_log("Auto Rename: Нет прикрепленных изображений к товару с ID $post_id.");
        return;
    }

    foreach ( $attachments as $attachment_id => $attachment ) {
        auto_rename_and_set_alt_for_image( $attachment_id );
    }
}
add_action( 'save_post', 'auto_rename_and_update_images_on_post_save' );

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

// Функция для пакетного переименования изображений с использованием Cron
function image_rename_cron_function() {
    global $wpdb;

    $images = $wpdb->get_results( "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_status = 'publish' LIMIT 5" );

    foreach ( $images as $image ) {
        auto_rename_and_set_alt_for_image( $image->ID );
    }
}
add_action( 'image_rename_cron_hook', 'image_rename_cron_function' );
