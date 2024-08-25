<?php
/**
 * Plugin Name: Auto Rename Uploads to Match Post Slug with Updates
 * Description: Automatically renames uploaded images to match the post slug, adds product title to image alt text, renames attached images when a product is updated, and updates thumbnails accordingly.
 * Author: Dima Dodonov
 * Version: 0.9
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

// Функция для переименования изображений, миниатюр и добавления alt-текста
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

        // Переименование миниатюр
        auto_rename_image_thumbnails( $image_id, $info['filename'], $new_file_name );
    } else {
        error_log("Auto Rename: Ошибка переименования файла $file в $new_file_path.");
    }

    // Освобождаем память
    unset($file);
    unset($info);
    wp_cache_flush();
}

// Переименование миниатюр
function auto_rename_image_thumbnails( $image_id, $original_file_name, $new_file_name ) {
    $upload_dir = wp_upload_dir();
    $sizes = array('woocommerce_thumbnail', 'woocommerce_single', 'woocommerce_gallery_thumbnail');

    foreach ($sizes as $size) {
        $thumbnail = wp_get_attachment_image_src( $image_id, $size );
        if ( $thumbnail && strpos($thumbnail[0], $original_file_name) !== false ) {
            $thumb_path = str_replace($original_file_name, $new_file_name, $thumbnail[0]);
            $thumb_file = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $thumb_path);

            if ( file_exists($thumb_file) ) {
                rename($thumbnail[0], $thumb_file);
                error_log("Auto Rename: Миниатюра для $size успешно переименована в $thumb_file.");
            } else {
                error_log("Auto Rename: Миниатюра для $size не найдена: $thumb_file.");
            }
        }
    }
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

    // Обновление ссылок на изображения в контенте товара
    auto_update_image_urls_in_content( $post_id );
}
add_action( 'save_post', 'auto_rename_and_update_images_on_post_save' );

// Функция для обновления ссылок на изображения в контенте товара
function auto_update_image_urls_in_content( $post_id ) {
    $post_content = get_post_field( 'post_content', $post_id );
    $attachments = get_children( array(
        'post_parent'    => $post_id,
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
    ));

    foreach ( $attachments as $attachment_id => $attachment ) {
        $old_url = wp_get_attachment_url( $attachment_id );
        $new_url = wp_get_attachment_url( $attachment_id );

        // Обновляем все вхождения старого URL на новый в контенте товара
        $post_content = str_replace( $old_url, $new_url, $post_content );
    }

    // Сохраняем обновленный контент товара
    wp_update_post( array(
        'ID'           => $post_id,
        'post_content' => $post_content,
    ));
}

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
