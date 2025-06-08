<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_filter( 'post_row_actions', 'add_duplicate_translate_row_action', 10, 2 );
function add_duplicate_translate_row_action( $actions, $post ) {
    if ( $post->post_type === 'post') {
        $url = admin_url( 'admin.php?action=render_progress_page&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'render_progress_page_nonce_' . $post->ID ) );
        $actions['duplicate_translate'] = sprintf(
            '<a href="%s" target="_blank" aria-label="%s">%s</a>',
            esc_url( $url ),
            esc_attr( sprintf( __( 'Duplicate & Translate "%s"', 'duplicate-translate' ), get_the_title( $post->ID ) ) ),
            __( 'Duplicate & Translate', 'duplicate-translate' )
        );
    }
    return $actions;
}