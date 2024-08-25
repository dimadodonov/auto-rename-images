<?php
/**
 * Plugin Name: Auto Rename Uploads to Match Post Slug with Updates
 * Description: Automatically renames uploaded images to match the post slug and also renames attached images when a product is updated.
 * Author: Your Name
 * Version: 0.5
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
            $postSlug = $postObj->post_name;
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

// Функция переименования изображений, связанных с обновляемым товаром
function auto_rename_attached_images_on_update( $post_id ) {
    if ( get_post_type( $post_id ) !== 'product' ) {
        return;
    }

    $attachments = get_children( array(
        'post_parent'    => $post_id,
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
    ));

    foreach ( $attachments as $attachment_id => $attachment ) {
        $file = get_attached_file( $attachment_id );
        $info = pathinfo( $file );
        $ext  = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
        $current_name = basename( $file, $ext );
        $postSlug = get_post( $post_id )->post_name;

        // Проверяем, совпадает ли имя файла с пост-слагом
        if ( $current_name === $postSlug ) {
            continue; // Если совпадает, пропускаем это изображение
        }

        // Переименовываем изображение
        $upload_dir = wp_upload_dir();
        $new_name = $postSlug;
        $i = 1;
        $base_name = $new_name;
        while ( file_exists( $upload_dir['path'] . '/' . $new_name . $ext ) ) {
            $new_name = $base_name . '-' . $i++;
        }

        $new_file_path = $upload_dir['path'] . '/' . $new_name . $ext;
        if ( rename( $file, $new_file_path ) ) {
            update_attached_file( $attachment_id, $new_file_path );

            // Обновляем ссылку в контенте
            $post_content = get_post( $post_id )->post_content;
            $post_content = str_replace( wp_get_attachment_url( $attachment_id ), $upload_dir['url'] . '/' . $new_name . $ext, $post_content );
            wp_update_post( array(
                'ID'           => $post_id,
                'post_content' => $post_content,
            ));
        }
    }
}

add_action( 'save_post', 'auto_rename_attached_images_on_update' );
