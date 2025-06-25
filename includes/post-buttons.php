<?php
/**
 * Post Buttons for Duplicate & Translate Plugin.
 *
 * This file contains the code for adding the "Duplicate & Translate"
 * button to the post row actions.
 *
 * @package Duplicate-And-Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- HOOKS ---
add_filter( 'post_row_actions', 'duplamtr_add_duplicate_translate_row_action', 10, 2 );

/**
 * Add the "Duplicate & Translate" row action to the post list.
 *
 * @param array   $actions The existing row actions.
 * @param WP_Post $post    The current post object.
 * @return array The modified row actions.
 */
function duplamtr_add_duplicate_translate_row_action( $actions, $post ) {
    if ( $post->post_type === 'post') {
        // --- CREATE URL ---
        $url = admin_url( 'admin.php?action=duplamtr_render_progress_page&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'duplamtr_render_progress_page_nonce_' . $post->ID ) );
        
        // --- ADD ACTION ---
        $actions['duplicate_translate'] = sprintf(
            '<a href="%s" target="_blank" aria-label="%s">%s</a>',
            esc_url( $url ),
            // translators: %s: post title
            esc_attr( sprintf( __( 'Duplicate & Translate "%s"', 'duplicate-translate' ), get_the_title( $post->ID ) ) ),
            esc_html__( 'Duplicate & Translate', 'duplicate-translate' )
        );
    }
    return $actions;
}