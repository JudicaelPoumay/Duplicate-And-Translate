<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


// 1. Action to Render the Initial Progress Page (with JavaScript)
add_action( 'admin_action_render_progress_page', 'render_progress_page_callback' );
function render_progress_page_callback() {
    if ( ! isset( $_GET['post_id'], $_GET['_wpnonce'] ) ||
         ! wp_verify_nonce( $_GET['_wpnonce'], 'render_progress_page_nonce_' . $_GET['post_id'] ) ||
         ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Security check failed or insufficient permissions.' );
    }

	$api_key = get_option( 'openai_api_key' );
	$target_language = get_option( 'target_language' );
	if ( empty( $api_key ) || empty( $target_language ) ) 
	{
		require PLUGIN_DIR . 'progress-page-view/error-page.php';
		exit;
	}

	require PLUGIN_DIR . 'progress-page-view/html.php';
    exit;
}

// 2. AJAX: Initiate Job, Duplicate, Translate Title, Parse Blocks
add_action( 'wp_ajax_initiate_job', 'initiate_job_callback' );
function initiate_job_callback() {
    check_ajax_referer( 'ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => __('Permissions error.', 'duplicate-translate')] );

    $original_post_id = isset( $_POST['original_post_id'] ) ? intval( $_POST['original_post_id'] ) : 0;
    if ( ! $original_post_id ) wp_send_json_error( ['message' => __('Original Post ID missing.', 'duplicate-translate')] );

    $original_post = get_post( $original_post_id );
    if ( ! $original_post || $original_post->post_type !== 'post' || $original_post->post_status !== 'publish' ) {
        wp_send_json_error( ['message' => __('Invalid original post.', 'duplicate-translate')] );
    }

    $api_key = get_option( 'openai_api_key' );
    $target_language = isset( $_POST['target_language'] ) ? sanitize_text_field( $_POST['target_language'] ) : get_option( 'target_language', 'French' );
    $translation_context = isset( $_POST['translation_context'] ) ? sanitize_textarea_field( $_POST['translation_context'] ) : '';

    if ( empty( $api_key ) || empty( $target_language ) ) {
        wp_send_json_error( ['message' => __('API Key or Target Language not set.', 'duplicate-translate')] );
    }

    try {
        // a. Duplicate Post
        $new_post_id = duplicate_post( $original_post );
        if ( is_wp_error( $new_post_id ) ) throw new Exception( __('Error duplicating post: ', 'duplicate-translate') . $new_post_id->get_error_message() );

        // b. Translate Title
        $translated_title_text = $original_post->post_title . ' (' . $target_language . ')'; // Fallback
        $translated_title = translate_text( $original_post->post_title, $target_language, $api_key, 'post title', $translation_context );
        if ( ! is_wp_error( $translated_title ) && !empty($translated_title) ) {
            $translated_title_text = $translated_title;
        } else if (is_wp_error($translated_title)) {
            error_log('Duplicate & Translate : Title Translation Error: ' . $translated_title->get_error_message());
            // Use fallback title
        }
        wp_update_post( ['ID' => $new_post_id, 'post_title' => $translated_title_text, 'post_name' => sanitize_title( $translated_title_text ), 'post_status' => 'draft'] );

        // c. Parse Blocks from Original Post
        $blocks_raw = parse_blocks( $original_post->post_content );
        $blocks_meta = [];
        if ( !empty($blocks_raw) ) {
            foreach( $blocks_raw as $index => $block_item ) {
                $blocks_meta[] = [
                    'original_index' => $index,
                    'block_name'     => $block_item['blockName'] ? $block_item['blockName'] : 'unknown',
                    'raw_block'      => $block_item, // Client doesn't need this if server fetches, but useful for `process_block_translation`
                    'status'         => 'pending' // Client-side status
                ];
            }
        } else if (!empty(trim($original_post->post_content))) { // Handle classic editor
             $blocks_meta[] = [
                'original_index' => 0,
                'block_name'     => 'core/html', // Treat classic as one HTML block
                'raw_block'      => ['blockName' => 'core/html', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => $original_post->post_content],
                'status'         => 'pending'
            ];
        }

        // d. Store Job Data in Transient
        $job_id = 'job_' . $original_post_id . '_' . time();
        $job_data = [
            'original_post_id' => $original_post_id,
            'new_post_id'      => $new_post_id,
            'target_language'  => $target_language,
            'translation_context' => $translation_context,
            'api_key'          => $api_key, // Store for block translation step
            'blocks_meta_full' => $blocks_meta, // Store full block data for server-side processing
            'status'           => 'blocks_ready'
        ];
        set_transient( $job_id, $job_data, HOUR_IN_SECONDS * 3 );

        // Client only needs meta for iteration, not full raw_block for each item if server fetches block from transient.
        // For simplicity now, client isn't directly using the raw_block from blocks_meta, server will.
        $client_blocks_meta = array_map(function($bm) {
            return ['index' => $bm['original_index'], 'blockName' => $bm['block_name'], 'status' => 'pending'];
        }, $blocks_meta);

        wp_send_json_success([
            'message'     => sprintf(__('Job initiated. New post ID: %d. Title translated. %d blocks parsed.', 'duplicate-translate'), $new_post_id, count($blocks_meta)),
            'job_id'      => $job_id,
            'new_post_id' => $new_post_id,
            'blocks_meta' => $client_blocks_meta // Meta for client to iterate
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
    wp_die();
}


// 3. AJAX: Process a Single Block's Translation
add_action( 'wp_ajax_process_block_translation', 'process_block_translation_callback' );
function process_block_translation_callback() {
    check_ajax_referer( 'ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => __('Permissions error.', 'duplicate-translate')] );

    $job_id = isset( $_POST['job_id'] ) ? sanitize_key( $_POST['job_id'] ) : null;
    $block_meta_index = isset( $_POST['block_meta_index'] ) ? intval( $_POST['block_meta_index'] ) : -1;

    if ( ! $job_id || $block_meta_index < 0 ) {
        wp_send_json_error( ['message' => __('Job ID or Block Index missing.', 'duplicate-translate')] );
    }

    $job_data = get_transient( $job_id );
    if ( false === $job_data || !isset($job_data['blocks_meta_full'][$block_meta_index]) ) {
        wp_send_json_error( ['message' => __('Job data or specific block not found or expired.', 'duplicate-translate')] );
    }

    $block_to_translate_raw = $job_data['blocks_meta_full'][$block_meta_index]['raw_block'];
    $target_language = $job_data['target_language'];
    $api_key = $job_data['api_key'];
    $translation_context = isset($job_data['translation_context']) ? $job_data['translation_context'] : '';

    // Use the recursive translator (modified to not echo, just return block or WP_Error)
    $translated_block_array = translate_block_recursive_for_ajax( $block_to_translate_raw, $target_language, $api_key, 0, $translation_context );

    if ( is_wp_error( $translated_block_array ) ) {
        error_log('Duplicate & Translate : Block Translation Error (Job: '.$job_id.', Block Index: '.$block_meta_index.'): ' . $translated_block_array->get_error_message());
        wp_send_json_error( [
            'message' => $translated_block_array->get_error_message(),
            'block_name' => $block_to_translate_raw['blockName'] ?: 'unknown',
            'original_block_content' => serialize_block( $block_to_translate_raw ) // Send original back
        ] );
    } else {
        wp_send_json_success( [
            'message' => 'Block translated', // Client already adds more context
            'block_name' => $translated_block_array['blockName'] ?: 'unknown',
            'translated_block_content' => serialize_block( $translated_block_array )
        ] );
    }
    wp_die();
}


// 4. AJAX: Finalize Job, Update Post with all translated blocks
add_action( 'wp_ajax_finalize_job', 'finalize_job_callback' );
function finalize_job_callback() {
    check_ajax_referer( 'ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => __('Permissions error.', 'duplicate-translate')] );

    $job_id = isset( $_POST['job_id'] ) ? sanitize_key( $_POST['job_id'] ) : null;
    $new_post_id = isset( $_POST['new_post_id'] ) ? intval( $_POST['new_post_id'] ) : 0;
    $translated_blocks_serialized = isset( $_POST['translated_blocks_serialized'] ) && is_array($_POST['translated_blocks_serialized'])
                                    ? $_POST['translated_blocks_serialized']
                                    : [];

    if ( ! $job_id || ! $new_post_id ) {
        wp_send_json_error( ['message' => __('Job ID or New Post ID missing.', 'duplicate-translate')] );
    }

    // Basic validation of serialized blocks
    $final_content_parts = [];
    foreach ($translated_blocks_serialized as $serialized_block_string) {
        if (is_string($serialized_block_string) && strpos(trim($serialized_block_string), '<!-- wp:') === 0) {
            $final_content_parts[] = $serialized_block_string;
        } else {
            // Log problematic block string
            error_log("Duplicate & Translate : Finalize: Invalid serialized block string received for job $job_id: " . substr($serialized_block_string,0,100));
        }
    }
    $final_content = implode( "\n\n", $final_content_parts );


    $updated = wp_update_post( [
        'ID'           => $new_post_id,
        'post_content' => $final_content,
        // 'post_status' => 'draft' // Already set to draft during title translation
    ], true ); // true for WP_Error on failure

    if ( is_wp_error( $updated ) ) {
        wp_send_json_error( ['message' => __('Error updating post content: ', 'duplicate-translate') . $updated->get_error_message()] );
    } else {
        delete_transient( $job_id ); // Clean up
        wp_send_json_success( [
            'message'   => __('Translated post content updated successfully.', 'duplicate-translate'),
            'edit_link' => get_edit_post_link( $new_post_id, 'raw' )
        ] );
    }
    wp_die();
}
