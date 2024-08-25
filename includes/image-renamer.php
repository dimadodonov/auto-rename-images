<?php
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

// Переименование изображений и миниатюр
function auto_rename_and_set_alt_for_image( $image_id ) {
    $file = get_attached_file( $image_id );
    $info = pathinfo( $file );
    $ext  = isset( $info['extension'] ) ? '.' . $info['extension'] : '';

    // Получаем объект поста-родителя (товара)
    $parent_post_id = wp_get_post_parent_id( $image_id );
    if ( !$parent_post_id ) {
        return;
    }

    $postObj = get_post( $parent_post_id );
    $postSlug = sanitize_title( $postObj->post_name );
    $postTitle = $postObj->post_title;

    if ( empty( $file ) || empty( $postSlug ) ) {
        return;
    }

    // Проверка, если изображение уже переименовано
    if ( strpos( $info['filename'], $postSlug ) !== false ) {
        return;
    }

    // Проверяем и устанавливаем alt-текст
    $current_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
    if ( strpos( $current_alt, $postTitle ) === false ) {
        update_post_meta( $image_id, '_wp_attachment_image_alt', $postTitle );
    }

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

        // Переименование миниатюр
        auto_rename_image_thumbnails( $image_id, $info['filename'], $new_file_name );
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
            }
        }
    }
}
