<?php
/**
 * Progress Page for Duplicate & Translate Plugin.
 *
 * This file contains the code for rendering the progress page,
 * handling the translation process via AJAX, and updating the post.
 *
 * @package Duplicate-And-Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- HOOKS ---
add_action( 'admin_action_duplamtr_render_progress_page', 'duplamtr_render_progress_page_callback' );
add_action( 'wp_ajax_duplamtr_initiate_job', 'duplamtr_initiate_job_callback' );
add_action( 'wp_ajax_duplamtr_process_block_translation', 'duplamtr_process_block_translation_callback' );
add_action( 'wp_ajax_duplamtr_finalize_job', 'duplamtr_finalize_job_callback' );

/**
 * Enqueue scripts and styles for the progress page.
 */
function duplamtr_progress_page_assets() {
    // Enqueue Style
    wp_enqueue_style(
        'duplamtr-progress-page-style',
        DUPLAMTR_PLUGIN_URL . 'progress-page-view/progress-page.css',
        [],
        '1.0.0'
    );
    wp_enqueue_style(
        'duplamtr-donation-button-style',
        DUPLAMTR_PLUGIN_URL . 'assets/donation-button.css',
        [],
        '1.0.0'
    );

    // Enqueue Script
    wp_enqueue_script(
        'duplamtr-progress-page-script',
        DUPLAMTR_PLUGIN_URL . 'progress-page-view/progress-page.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // Localize Script for JS
    $translation_array = [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'ajaxnonce' => wp_create_nonce('duplamtr_ajax_nonce'),
        'originalPostId' => isset($_GET['post_id']) ? intval($_GET['post_id']) : 0,
        'parallelBatchSize' => 6,
        'i18n' => [
            'selectLanguage' => esc_html__('Please select a target language.', 'duplicate-translate'),
            'initiatingJob' => esc_html__('Initiating translation job...', 'duplicate-translate'),
            'noBlocks' => esc_html__('No content blocks found to translate. Finalizing...', 'duplicate-translate'),
            'startingBlockTranslations' => esc_html__('Starting block translations...', 'duplicate-translate'),
            'blocks' => esc_html__('blocks', 'duplicate-translate'),
            'finalizingPost' => esc_html__('All blocks processed. Finalizing post...', 'duplicate-translate'),
            'complete' => esc_html__('Translation process complete!', 'duplicate-translate'),
            'editPost' => esc_html__('See Translated Post', 'duplicate-translate'),
            'canClose' => esc_html__('You can now close this tab.', 'duplicate-translate'),
            'blocksTranslated' => esc_html__('Translated %1$d of %2$d blocks.', 'duplicate-translate'),
            'activeAPI' => esc_html__('Active API calls: %d', 'duplicate-translate'),
            'blockTranslated' => esc_html__('Block %d translated.', 'duplicate-translate'),
            'errorTranslatingBlock' => esc_html__('Error translating block %d: ', 'duplicate-translate'),
            'ajaxErrorTranslatingBlock' => esc_html__('AJAX Error translating block %d: ', 'duplicate-translate'),
            'missingBlocksWarning' => esc_html__('Warning: Some blocks might be missing due to critical errors. Proceeding with available blocks.', 'duplicate-translate')
        ]
    ];
    wp_localize_script('duplamtr-progress-page-script', 'progressPageData', $translation_array);
}

/**
 * Render the initial progress page.
 */
function duplamtr_render_progress_page_callback() {
    // --- SECURITY CHECK ---
    if ( ! isset( $_GET['post_id'], $_GET['_wpnonce'] ) ||
         ! wp_verify_nonce( $_GET['_wpnonce'], 'duplamtr_render_progress_page_nonce_' . $_GET['post_id'] ) ||
         ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'Security check failed or insufficient permissions. Are you logged in? Try reloading the admin page.', 'duplicate-translate' ) );
    }

    // --- ENQUEUE ASSETS ---
    duplamtr_progress_page_assets();

    // --- CHECK CONFIGURATION ---
    $provider = get_option('duplamtr_llm_provider', 'openai');
    $api_key = get_option("duplamtr_{$provider}_api_key");
	$target_language = get_option( 'duplamtr_target_language' );
	if ( empty( $api_key ) || empty( $target_language ) )
	{
		require DUPLAMTR_PLUGIN_DIR . 'progress-page-view/error-page.php';
		exit;
	}

    // --- RENDER HTML ---
	require DUPLAMTR_PLUGIN_DIR . 'progress-page-view/html.php';
    exit;
}

/**
 * AJAX callback to initiate the translation job.
 * This includes duplicating the post, translating the title, and parsing the blocks.
 */
function duplamtr_initiate_job_callback() {
    // --- SECURITY & PERMISSION CHECK ---
    check_ajax_referer( 'duplamtr_ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => esc_html__('Permissions error.', 'duplicate-translate')] );

    // --- VALIDATE INPUT ---
    $original_post_id = isset( $_POST['original_post_id'] ) ? intval( $_POST['original_post_id'] ) : 0;
    if ( ! $original_post_id ) wp_send_json_error( ['message' => esc_html__('Original Post ID missing.', 'duplicate-translate')] );

    $original_post = get_post( $original_post_id );
    if ( ! $original_post || $original_post->post_type !== 'post' || $original_post->post_status !== 'publish' ) {
        wp_send_json_error( ['message' => esc_html__('Invalid original post.', 'duplicate-translate')] );
    }

    $provider = get_option('duplamtr_llm_provider', 'openai');
    $api_key = get_option("duplamtr_{$provider}_api_key");
    $target_language = isset( $_POST['target_language'] ) ? sanitize_text_field( $_POST['target_language'] ) : get_option( 'duplamtr_target_language', 'French' );
    $translation_context = isset( $_POST['translation_context'] ) ? sanitize_textarea_field( $_POST['translation_context'] ) : '';

    if ( empty( $api_key ) || empty( $target_language ) ) {
        wp_send_json_error( ['message' => esc_html__('API Key or Target Language not set.', 'duplicate-translate')] );
    }

    try {
        // --- DUPLICATE POST ---
        $new_post_id = duplamtr_duplicate_post( $original_post );
        if ( is_wp_error( $new_post_id ) ) throw new Exception( esc_html__('Error duplicating post: ', 'duplicate-translate') . $new_post_id->get_error_message() );

        // --- TRANSLATE TITLE ---
        $translated_title_text = $original_post->post_title . ' (' . $target_language . ')'; // Fallback
        $translated_title = duplamtr_translate_text( $original_post->post_title, $target_language, 'post title', $translation_context );
        if ( ! is_wp_error( $translated_title ) && !empty($translated_title) ) {
            $translated_title_text = $translated_title;
        } else if (is_wp_error($translated_title)) {
            error_log('Duplicate & Translate : Title Translation Error: ' . $translated_title->get_error_message());
            // Use fallback title
        }
        wp_update_post( ['ID' => $new_post_id, 'post_title' => $translated_title_text, 'post_name' => sanitize_title( $translated_title_text ), 'post_status' => 'draft'] );

        // --- PARSE BLOCKS ---
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

        // --- STORE JOB DATA IN TRANSIENT ---
        $job_id = 'duplamtr_job_' . $original_post_id . '_' . time();
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

        // --- PREPARE CLIENT RESPONSE ---
        // Client only needs meta for iteration, not full raw_block for each item if server fetches block from transient.
        // For simplicity now, client isn't directly using the raw_block from blocks_meta, server will.
        $client_blocks_meta = array_map(function($bm) {
            return ['index' => $bm['original_index'], 'blockName' => $bm['block_name'], 'status' => 'pending'];
        }, $blocks_meta);

        wp_send_json_success([
            'message'     => sprintf(esc_html__('Job initiated. New post ID: %d. Title translated. %d blocks parsed.', 'duplicate-translate'), $new_post_id, count($blocks_meta)),
            'job_id'      => $job_id,
            'new_post_id' => $new_post_id,
            'blocks_meta' => $client_blocks_meta // Meta for client to iterate
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
    wp_die();
}

/**
 * AJAX callback to process the translation of a single block.
 */
function duplamtr_process_block_translation_callback() {
    // --- SECURITY & PERMISSION CHECK ---
    check_ajax_referer( 'duplamtr_ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => esc_html__('Permissions error.', 'duplicate-translate')] );

    // --- VALIDATE INPUT ---
    $job_id = isset( $_POST['job_id'] ) ? sanitize_key( $_POST['job_id'] ) : null;
    $block_meta_index = isset( $_POST['block_meta_index'] ) ? intval( $_POST['block_meta_index'] ) : -1;

    if ( ! $job_id || $block_meta_index < 0 ) {
        wp_send_json_error( ['message' => esc_html__('Job ID or Block Index missing.', 'duplicate-translate')] );
    }

    // --- GET JOB DATA ---
    $job_data = get_transient( $job_id );
    if ( false === $job_data || !isset($job_data['blocks_meta_full'][$block_meta_index]) ) {
        wp_send_json_error( ['message' => esc_html__('Job data or specific block not found or expired.', 'duplicate-translate')] );
    }

    // --- TRANSLATE BLOCK ---
    $block_to_translate_raw = $job_data['blocks_meta_full'][$block_meta_index]['raw_block'];
    $target_language = $job_data['target_language'];
    $api_key = $job_data['api_key'];
    $translation_context = isset($job_data['translation_context']) ? $job_data['translation_context'] : '';

    // Use the recursive translator
    $translated_block_array = duplamtr_translate_block_recursive_for_ajax( $block_to_translate_raw, $target_language, 0, $translation_context );

    if ( is_wp_error( $translated_block_array ) ) {
        // --- HANDLE TRANSLATION ERROR ---
        error_log('Duplicate & Translate : Block Translation Error (Job: '.$job_id.', Block Index: '.$block_meta_index.'): ' . $translated_block_array->get_error_message());
        wp_send_json_error( [
            'message' => $translated_block_array->get_error_message(),
            'block_name' => $block_to_translate_raw['blockName'] ?: 'unknown',
            'original_block_content' => serialize_block( $block_to_translate_raw ) // Send original back
        ] );
    } else {
        // --- SEND SUCCESS RESPONSE ---
        wp_send_json_success( [
            'message' => 'Block translated', // Client already adds more context
            'block_name' => $translated_block_array['blockName'] ?: 'unknown',
            'translated_block_content' => serialize_block( $translated_block_array )
        ] );
    }
    wp_die();
}

/**
 * AJAX callback to finalize the job and update the post with translated blocks.
 */
function duplamtr_finalize_job_callback() {
    // --- SECURITY & PERMISSION CHECK ---
    check_ajax_referer( 'duplamtr_ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => esc_html__('Permissions error.', 'duplicate-translate')] );

    // --- VALIDATE INPUT ---
    $job_id = isset( $_POST['job_id'] ) ? sanitize_key( $_POST['job_id'] ) : null;
    $translated_blocks_serialized = isset( $_POST['translated_blocks_serialized'] ) && is_array($_POST['translated_blocks_serialized']) 
                                    ? wp_unslash( $_POST['translated_blocks_serialized'] ) 
                                    : [];

    if ( ! $job_id || ! is_array( $translated_blocks_serialized ) ) {
        wp_send_json_error( ['message' => esc_html__('Job ID or Translated Blocks missing/invalid.', 'duplicate-translate')] );
    }

    // --- GET JOB DATA ---
    $job_data = get_transient( $job_id );
    if ( false === $job_data ) {
        wp_send_json_error( ['message' => esc_html__('Job data not found or expired.', 'duplicate-translate')] );
    }

    // --- UPDATE POST ---
    $new_post_id = $job_data['new_post_id'];
    $final_content = implode( "\n\n", $translated_blocks_serialized );
    
    $updated = wp_update_post( [
        'ID'           => $new_post_id,
        'post_content' => $final_content,
    ], true );

    if ( is_wp_error( $updated ) ) {
        wp_send_json_error( ['message' => __('Error updating post content: ', 'duplicate-translate') . $updated->get_error_message()] );
    }

    // --- CLEANUP ---
    delete_transient( $job_id );

    // --- SEND RESPONSE ---
    wp_send_json_success( [
        'message' => sprintf(
            esc_html__( 'Post updated successfully. Translated %1$d of %2$d blocks.', 'duplicate-translate' ),
            count( $translated_blocks_serialized ),
            count( $job_data['blocks_meta_full'] )
        ),
        'edit_url' => get_edit_post_link( $new_post_id, 'raw' )
    ] );
    wp_die();
}
