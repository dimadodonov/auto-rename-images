<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Обновление контента товаров
function auto_rename_and_update_images_on_post_save( $post_id ) {
    if ( get_post_type( $post_id ) !== 'product' ) {
        return;
    }

    $attachments = get_posts( array(
        'post_parent'    => $post_id,
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'numberposts'    => -1, // Получаем все изображения, прикрепленные к товару
    ));

    if ( empty( $attachments ) ) {
        return;
    }

    foreach ( $attachments as $attachment ) {
        auto_rename_and_set_alt_for_image( $attachment->ID );
    }

    // Обновление ссылок на изображения в контенте товара
    auto_update_image_urls_in_content( $post_id );
}
add_action( 'save_post', 'auto_rename_and_update_images_on_post_save' );

// Обновление ссылок на изображения в контенте товара
function auto_update_image_urls_in_content( $post_id ) {
    $post_content = get_post_field( 'post_content', $post_id );
    $attachments = get_posts( array(
        'post_parent'    => $post_id,
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'numberposts'    => -1,
    ));

    foreach ( $attachments as $attachment ) {
        $old_url = wp_get_attachment_url( $attachment->ID );
        $new_url = wp_get_attachment_url( $attachment->ID );

        // Обновляем все вхождения старого URL на новый в контенте товара
        $post_content = str_replace( $old_url, $new_url, $post_content );
    }

    // Сохраняем обновленный контент товара
    wp_update_post( array(
        'ID'           => $post_id,
        'post_content' => $post_content,
    ));
}
