<?php
/**
 * Plugin Name: OpenAI Duplicate & Translate (Batch AJAX)
 * Description: Adds a button to duplicate posts and translate their content using OpenAI API, with batched AJAX progress.
 * Version: 1.3
 * Author: Your Name
 * License: GPLv2 or later
 * Text Domain: openai-duplicate-translate
 */

// ... (DEFINES, SETTINGS FUNCTIONS, BUTTON/LINK FUNCTIONS - remain largely the same as v1.2) ...
// Make sure button/link functions now point to `odt_render_progress_page`
// e.g., in odt_add_duplicate_translate_button_on_edit_screen and odt_add_duplicate_translate_row_action:
// $url = admin_url( 'admin.php?action=odt_render_progress_page&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'odt_render_progress_page_nonce_' . $post->ID ) );

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'ODT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// --- SETTINGS (Unchanged from previous version) ---
add_action( 'admin_menu', 'odt_add_admin_menu' );
add_action( 'admin_init', 'odt_settings_init' );

function odt_add_admin_menu() {
    add_options_page('OpenAI Duplicate & Translate', 'OpenAI D&T', 'manage_options', 'openai_duplicate_translate', 'odt_options_page_html');
}
function odt_settings_init() {
    register_setting( 'odt_options_group', 'odt_openai_api_key' );
    register_setting( 'odt_options_group', 'odt_target_language', ['default' => 'Spanish'] );
    add_settings_section('odt_settings_section', __('API Configuration', 'openai-duplicate-translate'), null, 'odt_options_group');
    add_settings_field('odt_openai_api_key_field', __('OpenAI API Key', 'openai-duplicate-translate'), 'odt_api_key_field_html', 'odt_options_group', 'odt_settings_section');
    add_settings_field('odt_target_language_field', __('Target Language', 'openai-duplicate-translate'), 'odt_target_language_field_html', 'odt_options_group', 'odt_settings_section');
}
function odt_api_key_field_html() {
    $api_key = get_option( 'odt_openai_api_key' );
    echo '<input type="text" name="odt_openai_api_key" value="' . esc_attr( $api_key ) . '" size="50" />';
    echo '<p class="description">' . __('Enter your OpenAI API key.', 'openai-duplicate-translate') . '</p>';
}
function odt_target_language_field_html() {
    $target_language = get_option( 'odt_target_language', 'Spanish' );
    $languages = ['Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Japanese', 'Chinese (Simplified)'];
    echo '<select name="odt_target_language">';
    foreach ($languages as $lang) {
        echo '<option value="' . esc_attr($lang) . '" ' . selected($target_language, $lang, false) . '>' . esc_html($lang) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . __('Select the language to translate content into.', 'openai-duplicate-translate') . '</p>';
}
function odt_options_page_html() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('odt_options_group'); do_settings_sections('odt_options_group'); submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

// --- BUTTONS / LINKS ---
add_action( 'post_submitbox_misc_actions', 'odt_add_duplicate_translate_button_on_edit_screen' );
add_filter( 'post_row_actions', 'odt_add_duplicate_translate_row_action', 10, 2 );

function odt_add_duplicate_translate_button_on_edit_screen( $post ) {
    if ( $post->post_type !== 'post' || $post->post_status !== 'publish' ) return;
    $api_key = get_option( 'odt_openai_api_key' );
    $target_language = get_option( 'odt_target_language' );
    if ( empty( $api_key ) || empty( $target_language ) ) {
        echo '<div id="duplicate-translate-action" class="misc-pub-section"><p style="color:red;">' . __('Please configure OpenAI API Key and Target Language in settings.', 'openai-duplicate-translate') . '</p></div>';
        return;
    }
    $url = admin_url( 'admin.php?action=odt_render_progress_page&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'odt_render_progress_page_nonce_' . $post->ID ) );
    ?>
    <div id="duplicate-translate-action" class="misc-pub-section">
        <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Duplicate & Translate', 'openai-duplicate-translate' ); ?></a>
        <p class="description"><?php printf(esc_html__('Translates to %s. Opens in a new tab.', 'openai-duplicate-translate'), '<strong>' . esc_html($target_language) . '</strong>'); ?></p>
    </div>
    <?php
}

function odt_add_duplicate_translate_row_action( $actions, $post ) {
    if ( $post->post_type === 'post' && $post->post_status === 'publish' ) {
        $api_key = get_option( 'odt_openai_api_key' );
        $target_language = get_option( 'odt_target_language' );
        if ( empty( $api_key ) || empty( $target_language ) ) return $actions;
        $url = admin_url( 'admin.php?action=odt_render_progress_page&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'odt_render_progress_page_nonce_' . $post->ID ) );
        $actions['duplicate_translate'] = sprintf(
            '<a href="%s" target="_blank" aria-label="%s">%s</a>',
            esc_url( $url ),
            esc_attr( sprintf( __( 'Duplicate & Translate "%s"', 'openai-duplicate-translate' ), get_the_title( $post->ID ) ) ),
            __( 'Duplicate & Translate', 'openai-duplicate-translate' )
        );
    }
    return $actions;
}


// 1. Action to Render the Initial Progress Page (with JavaScript)
add_action( 'admin_action_odt_render_progress_page', 'odt_render_progress_page_callback' );
function odt_render_progress_page_callback() {
    if ( ! isset( $_GET['post_id'], $_GET['_wpnonce'] ) ||
         ! wp_verify_nonce( $_GET['_wpnonce'], 'odt_render_progress_page_nonce_' . $_GET['post_id'] ) ||
         ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Security check failed or insufficient permissions.' );
    }
    $original_post_id = intval( $_GET['post_id'] );
    // Further checks on $original_post_id can be added here

    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php _e('Translation Progress', 'openai-duplicate-translate'); ?></title>
        <style>
            /* ... (same styles as before) ... */
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; background-color: #f9f9f9; color: #333; }
            .progress-log { background-color: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px; min-height:100px; max-height: 500px; overflow-y: auto; }
            .progress-log p { margin: 0 0 10px; padding-bottom: 5px; border-bottom: 1px dotted #eee; }
            .progress-log p:last-child { border-bottom: none; }
            .progress-log .success { color: green; }
            .progress-log .error { color: red; font-weight: bold; }
            h1 { color: #555; }
            .done a { display: inline-block; padding: 10px 15px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 3px; margin-top: 15px; }
            .done a:hover { background-color: #005177; }
            .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; display: inline-block; margin-left: 10px; vertical-align: middle;}
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .block-progress { margin-top: 10px; font-size: 0.9em; }
        </style>
        <?php wp_print_scripts('jquery'); ?>
    </head>
    <body>
        <h1><?php _e('Translation Progress', 'openai-duplicate-translate'); ?> <span id="spinner" class="spinner" style="display:none;"></span></h1>
        <div id="progress-log" class="progress-log"></div>
        <div id="block-progress-info" class="block-progress"></div>
        <div id="final-link"></div>

        <script type="text/javascript">
            var ajaxurl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
            var odtNonce = '<?php echo esc_js( wp_create_nonce('odt_ajax_nonce') ); ?>'; // General nonce for our AJAX actions
            var originalPostId = <?php echo intval( $original_post_id ); ?>;
            var parallelBatchSize = 5; // How many blocks to translate concurrently

            jQuery(document).ready(function($) {
                var progressLog = $('#progress-log');
                var finalLink = $('#final-link');
                var spinner = $('#spinner');
                var blockProgressInfo = $('#block-progress-info');

                var jobId = null;
                var newPostId = null;
                var blocksToTranslateMeta = []; // Array of { index: i, blockName: 'core/para...', status: 'pending' }
                var translatedBlocksData = []; // Array to store translated block structures in order
                var activeRequests = 0;
                var totalBlocks = 0;
                var processedBlockCount = 0;

                function addProgress(message, type = 'info') {
                    var date = new Date();
                    var timeString = '[' + ('0' + date.getHours()).slice(-2) + ':' + ('0' + date.getMinutes()).slice(-2) + ':' + ('0' + date.getSeconds()).slice(-2) + '] ';
                    var pClass = type === 'error' ? ' class="error"' : (type === 'success' ? ' class="success"' : '');
                    progressLog.append('<p' + pClass + '>' + timeString + message + '</p>');
                    progressLog.scrollTop(progressLog[0].scrollHeight);
                }

                function updateBlockProgress() {
                    blockProgressInfo.text('Translated ' + processedBlockCount + ' of ' + totalBlocks + ' blocks. Active API calls: ' + activeRequests);
                }

                // 1. Initiate Job
                function initiateTranslationJob() {
                    spinner.show();
                    addProgress('<?php _e("Initiating translation job...", "openai-duplicate-translate"); ?>');
                    $.ajax({
                        url: ajaxurl, type: 'POST', dataType: 'json',
                        data: {
                            action: 'odt_initiate_job',
                            original_post_id: originalPostId,
                            _ajax_nonce: odtNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                jobId = response.data.job_id;
                                newPostId = response.data.new_post_id;
                                blocksToTranslateMeta = response.data.blocks_meta;
                                totalBlocks = blocksToTranslateMeta.length;
                                translatedBlocksData = new Array(totalBlocks); // Initialize array for ordered results

                                addProgress(response.data.message, 'success');
                                if (totalBlocks === 0) {
                                    addProgress('<?php _e("No content blocks found to translate. Finalizing...", "openai-duplicate-translate"); ?>');
                                    finalizeJob();
                                } else {
                                    addProgress('<?php _e("Starting block translations...", "openai-duplicate-translate"); ?> (' + totalBlocks + ' blocks)');
                                    processBlockQueue();
                                }
                            } else {
                                spinner.hide();
                                addProgress('Error initiating job: ' + (response.data.message || 'Unknown error'), 'error');
                            }
                        },
                        error: function(jqXHR, ts, et) {
                            spinner.hide();
                            addProgress('AJAX Error initiating job: ' + ts + ' - ' + et, 'error');
                        }
                    });
                }

                // 2. Process Block Queue (Manages concurrent requests)
                function processBlockQueue() {
                    updateBlockProgress();
                    if (processedBlockCount === totalBlocks && activeRequests === 0) {
                        finalizeJob();
                        return;
                    }

                    for (var i = 0; i < blocksToTranslateMeta.length; i++) {
                        if (activeRequests >= parallelBatchSize) break; // Limit concurrent requests

                        if (blocksToTranslateMeta[i].status === 'pending') {
                            blocksToTranslateMeta[i].status = 'in_progress';
                            activeRequests++;
                            translateSingleBlock(i, blocksToTranslateMeta[i]); // Pass index and block meta
                            updateBlockProgress();
                        }
                    }
                     // If all blocks are processed or in_progress but no active requests (e.g. initial run didn't fill batch), and not all done, this might indicate an issue.
                    // However, the main check is `processedBlockCount === totalBlocks`.
                }

                // 3. Translate Single Block
                function translateSingleBlock(blockMetaIndex, blockMeta) {
                    // The blockMeta here could contain the actual block data or just an identifier if server fetches it
                    // For this example, let's assume blockMeta.raw_block is passed from `odt_initiate_job`
                    $.ajax({
                        url: ajaxurl, type: 'POST', dataType: 'json',
                        data: {
                            action: 'odt_process_block_translation',
                            job_id: jobId,
                            block_meta_index: blockMetaIndex, // Server uses this to fetch the specific block from transient
                             // raw_block_data: blockMeta.raw_block, // Send raw block for translation
                            _ajax_nonce: odtNonce
                        },
                        success: function(response) {
                            activeRequests--;
                            processedBlockCount++;
                            if (response.success) {
                                addProgress('Block ' + (blockMetaIndex + 1) + ' (' + response.data.block_name + ') translated.', 'success');
                                translatedBlocksData[blockMetaIndex] = response.data.translated_block_content; // Store serialized block
                                blocksToTranslateMeta[blockMetaIndex].status = 'done';
                            } else {
                                addProgress('Error translating block ' + (blockMetaIndex + 1) + ': ' + (response.data.message || 'Unknown error'), 'error');
                                // Store original block content on error to prevent data loss
                                translatedBlocksData[blockMetaIndex] = response.data.original_block_content; // Server should send this
                                blocksToTranslateMeta[blockMetaIndex].status = 'failed';
                            }
                            processBlockQueue(); // Check if more blocks can be processed
                        },
                        error: function(jqXHR, ts, et) {
                            activeRequests--;
                            processedBlockCount++; // Count as processed even on error to move on
                            addProgress('AJAX Error translating block ' + (blockMetaIndex + 1) + ': ' + ts + ' - ' + et, 'error');
                            blocksToTranslateMeta[blockMetaIndex].status = 'failed_ajax';
                            processBlockQueue();
                        }
                    });
                }

                // 4. Finalize Job
                function finalizeJob() {
                    spinner.show();
                    updateBlockProgress(); // Final update
                    addProgress('<?php _e("All blocks processed. Finalizing post...", "openai-duplicate-translate"); ?>');

                    // Filter out any undefined slots if some blocks failed catastrophically (should ideally not happen if server sends original on error)
                    var finalBlockArray = translatedBlocksData.filter(function (el) { return el != null; });
                    if (finalBlockArray.length !== totalBlocks && totalBlocks > 0) {
                         addProgress('Warning: Some blocks might be missing due to critical errors. Proceeding with available blocks.', 'error');
                    }


                    $.ajax({
                        url: ajaxurl, type: 'POST', dataType: 'json',
                        data: {
                            action: 'odt_finalize_job',
                            job_id: jobId,
                            new_post_id: newPostId,
                            translated_blocks_serialized: finalBlockArray, // Array of serialized block strings
                            _ajax_nonce: odtNonce
                        },
                        success: function(response) {
                            spinner.hide();
                            if (response.success) {
                                addProgress('<?php _e("Translation process complete!", "openai-duplicate-translate"); ?>', 'success');
                                if(response.data.edit_link) {
                                    finalLink.html('<p class="done"><a href="' + response.data.edit_link + '" target="_blank"><?php _e("Edit Translated Post", "openai-duplicate-translate"); ?> (ID: ' + newPostId + ')</a></p>');
                                }
                                addProgress('<?php _e("You can now close this tab.", "openai-duplicate-translate"); ?>');
                            } else {
                                addProgress('Error finalizing job: ' + (response.data.message || 'Unknown error'), 'error');
                            }
                        },
                        error: function(jqXHR, ts, et) {
                            spinner.hide();
                            addProgress('AJAX Error finalizing job: ' + ts + ' - ' + et, 'error');
                        }
                    });
                }

                // Start the whole process
                initiateTranslationJob();
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// 2. AJAX: Initiate Job, Duplicate, Translate Title, Parse Blocks
add_action( 'wp_ajax_odt_initiate_job', 'odt_initiate_job_callback' );
function odt_initiate_job_callback() {
    check_ajax_referer( 'odt_ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => __('Permissions error.', 'openai-duplicate-translate')] );

    $original_post_id = isset( $_POST['original_post_id'] ) ? intval( $_POST['original_post_id'] ) : 0;
    if ( ! $original_post_id ) wp_send_json_error( ['message' => __('Original Post ID missing.', 'openai-duplicate-translate')] );

    $original_post = get_post( $original_post_id );
    if ( ! $original_post || $original_post->post_type !== 'post' || $original_post->post_status !== 'publish' ) {
        wp_send_json_error( ['message' => __('Invalid original post.', 'openai-duplicate-translate')] );
    }

    $api_key = get_option( 'odt_openai_api_key' );
    $target_language = get_option( 'odt_target_language', 'Spanish' );
    if ( empty( $api_key ) || empty( $target_language ) ) {
        wp_send_json_error( ['message' => __('API Key or Target Language not set.', 'openai-duplicate-translate')] );
    }

    try {
        // a. Duplicate Post
        $new_post_id = odt_duplicate_post( $original_post );
        if ( is_wp_error( $new_post_id ) ) throw new Exception( __('Error duplicating post: ', 'openai-duplicate-translate') . $new_post_id->get_error_message() );

        // b. Translate Title
        $translated_title_text = $original_post->post_title . ' (' . $target_language . ')'; // Fallback
        $translated_title = odt_translate_text_with_openai( $original_post->post_title, $target_language, $api_key, 'post title' );
        if ( ! is_wp_error( $translated_title ) && !empty($translated_title) ) {
            $translated_title_text = $translated_title;
        } else if (is_wp_error($translated_title)) {
            error_log('ODT Title Translation Error: ' . $translated_title->get_error_message());
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
                    'raw_block'      => $block_item, // Client doesn't need this if server fetches, but useful for `odt_process_block_translation`
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
        $job_id = 'odt_job_' . $original_post_id . '_' . time();
        $job_data = [
            'original_post_id' => $original_post_id,
            'new_post_id'      => $new_post_id,
            'target_language'  => $target_language,
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
            'message'     => sprintf(__('Job initiated. New post ID: %d. Title translated. %d blocks parsed.', 'openai-duplicate-translate'), $new_post_id, count($blocks_meta)),
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
add_action( 'wp_ajax_odt_process_block_translation', 'odt_process_block_translation_callback' );
function odt_process_block_translation_callback() {
    check_ajax_referer( 'odt_ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => __('Permissions error.', 'openai-duplicate-translate')] );

    $job_id = isset( $_POST['job_id'] ) ? sanitize_key( $_POST['job_id'] ) : null;
    $block_meta_index = isset( $_POST['block_meta_index'] ) ? intval( $_POST['block_meta_index'] ) : -1;

    if ( ! $job_id || $block_meta_index < 0 ) {
        wp_send_json_error( ['message' => __('Job ID or Block Index missing.', 'openai-duplicate-translate')] );
    }

    $job_data = get_transient( $job_id );
    if ( false === $job_data || !isset($job_data['blocks_meta_full'][$block_meta_index]) ) {
        wp_send_json_error( ['message' => __('Job data or specific block not found or expired.', 'openai-duplicate-translate')] );
    }

    $block_to_translate_raw = $job_data['blocks_meta_full'][$block_meta_index]['raw_block'];
    $target_language = $job_data['target_language'];
    $api_key = $job_data['api_key'];

    // Use the recursive translator (modified to not echo, just return block or WP_Error)
    $translated_block_array = odt_translate_block_recursive_for_ajax( $block_to_translate_raw, $target_language, $api_key );

    if ( is_wp_error( $translated_block_array ) ) {
        error_log('ODT Block Translation Error (Job: '.$job_id.', Block Index: '.$block_meta_index.'): ' . $translated_block_array->get_error_message());
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
add_action( 'wp_ajax_odt_finalize_job', 'odt_finalize_job_callback' );
function odt_finalize_job_callback() {
    check_ajax_referer( 'odt_ajax_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( ['message' => __('Permissions error.', 'openai-duplicate-translate')] );

    $job_id = isset( $_POST['job_id'] ) ? sanitize_key( $_POST['job_id'] ) : null;
    $new_post_id = isset( $_POST['new_post_id'] ) ? intval( $_POST['new_post_id'] ) : 0;
    $translated_blocks_serialized = isset( $_POST['translated_blocks_serialized'] ) && is_array($_POST['translated_blocks_serialized'])
                                    ? $_POST['translated_blocks_serialized']
                                    : [];

    if ( ! $job_id || ! $new_post_id ) {
        wp_send_json_error( ['message' => __('Job ID or New Post ID missing.', 'openai-duplicate-translate')] );
    }

    // Basic validation of serialized blocks
    $final_content_parts = [];
    foreach ($translated_blocks_serialized as $serialized_block_string) {
        if (is_string($serialized_block_string) && strpos(trim($serialized_block_string), '<!-- wp:') === 0) {
            $final_content_parts[] = $serialized_block_string;
        } else {
            // Log problematic block string
            error_log("ODT Finalize: Invalid serialized block string received for job $job_id: " . substr($serialized_block_string,0,100));
        }
    }
    $final_content = implode( "\n\n", $final_content_parts );


    $updated = wp_update_post( [
        'ID'           => $new_post_id,
        'post_content' => $final_content,
        // 'post_status' => 'draft' // Already set to draft during title translation
    ], true ); // true for WP_Error on failure

    if ( is_wp_error( $updated ) ) {
        wp_send_json_error( ['message' => __('Error updating post content: ', 'openai-duplicate-translate') . $updated->get_error_message()] );
    } else {
        delete_transient( $job_id ); // Clean up
        wp_send_json_success( [
            'message'   => __('Translated post content updated successfully.', 'openai-duplicate-translate'),
            'edit_link' => get_edit_post_link( $new_post_id, 'raw' )
        ] );
    }
    wp_die();
}


// --- HELPER FUNCTIONS ---
// odt_duplicate_post (no change)
// odt_translate_text_with_openai (no change)
// odt_translate_block_recursive_for_ajax (no change from v1.2 - ensure it doesn't echo)
function odt_duplicate_post( $post_to_duplicate ) {
    if ( ! $post_to_duplicate || ! is_object( $post_to_duplicate ) ) return new WP_Error( 'invalid_post', 'Invalid post object.' );
    $current_user = wp_get_current_user();
    $new_post_author = $current_user->ID;
    $args = [
        'comment_status' => $post_to_duplicate->comment_status, 'ping_status' => $post_to_duplicate->ping_status,
        'post_author' => $new_post_author, 'post_content' => $post_to_duplicate->post_content,
        'post_excerpt' => $post_to_duplicate->post_excerpt, 'post_name' => $post_to_duplicate->post_name . '-translation',
        'post_parent' => $post_to_duplicate->post_parent, 'post_password' => $post_to_duplicate->post_password,
        'post_status' => 'draft', 'post_title' => $post_to_duplicate->post_title . ' (Translation)',
        'post_type' => $post_to_duplicate->post_type, 'to_ping' => $post_to_duplicate->to_ping,
        'menu_order' => $post_to_duplicate->menu_order
    ];
    $new_post_id = wp_insert_post( $args, true );
    if ( is_wp_error( $new_post_id ) ) return $new_post_id;
    $taxonomies = get_object_taxonomies( $post_to_duplicate->post_type );
    foreach ( $taxonomies as $taxonomy ) {
        $post_terms = wp_get_object_terms( $post_to_duplicate->ID, $taxonomy, ['fields' => 'slugs'] );
        wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
    }
    $post_meta_keys = get_post_custom_keys( $post_to_duplicate->ID );
    if ( ! empty( $post_meta_keys ) ) {
        foreach ( $post_meta_keys as $meta_key ) {
            if ( $meta_key == '_edit_lock' || $meta_key == '_edit_last' ) continue;
            $meta_values = get_post_custom_values( $meta_key, $post_to_duplicate->ID );
            foreach ( $meta_values as $meta_value ) add_post_meta( $new_post_id, $meta_key, maybe_unserialize($meta_value) );
        }
    }
    return $new_post_id;
}

function odt_translate_text_with_openai( $text_to_translate, $target_language, $api_key, $context = "general text" ) {
    if ( empty( trim( $text_to_translate ) ) ) return '';
    $api_url = 'https://api.openai.com/v1/chat/completions';
    $body = [
        'model'    => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => "You are a professional translator. Translate accurately to {$target_language} and maintain original HTML formatting if any. Only return the translated text."],
            ['role' => 'user', 'content' => $text_to_translate]
        ],
        'temperature' => 0.3, 'max_tokens'  => 2000,
    ];
    $headers = ['Authorization' => 'Bearer ' . $api_key, 'Content-Type'  => 'application/json'];
    $response = wp_remote_post( $api_url, ['method'  => 'POST', 'headers' => $headers, 'body' => json_encode( $body ), 'timeout' => 60] );
    if ( is_wp_error( $response ) ) return $response;
    $response_body = wp_remote_retrieve_body( $response );
    $response_data = json_decode( $response_body, true );
    if ( isset( $response_data['choices'][0]['message']['content'] ) ) {
        $translated_text = trim( $response_data['choices'][0]['message']['content'] );
        if ( (substr($translated_text, 0, 1) == '"' && substr($translated_text, -1) == '"') || (substr($translated_text, 0, 1) == "'" && substr($translated_text, -1) == "'") ) {
            $translated_text = substr($translated_text, 1, -1);
        }
        return $translated_text;
    } elseif ( isset( $response_data['error'] ) ) {
        return new WP_Error( 'openai_api_error', 'OpenAI API Error: ' . $response_data['error']['message'] . ' (Context: '.esc_html($context).', Original: '. substr(esc_html($text_to_translate),0,50).'...)');
    }
    return new WP_Error( 'openai_unknown_error', 'Unknown error from OpenAI API. Response: ' . esc_html($response_body) );
}

function odt_translate_block_recursive_for_ajax( $block, $target_language, $api_key, $depth = 0 ) {
    $translated_block = $block;
    if ( ! empty( $block['innerBlocks'] ) ) {
        $translated_inner_blocks = [];
        foreach ( $block['innerBlocks'] as $inner_block ) {
            $translated_inner_block_result = odt_translate_block_recursive_for_ajax( $inner_block, $target_language, $api_key, $depth + 1 );
            if (is_wp_error($translated_inner_block_result)) return $translated_inner_block_result; // Propagate error
            $translated_inner_blocks[] = $translated_inner_block_result;
        }
        $translated_block['innerBlocks'] = $translated_inner_blocks;
    }
    $text_attributes_to_translate = [
        'core/heading'   => ['content'], 'core/paragraph' => ['content'], 'core/list' => ['values'],
        'core/quote'     => ['value', 'citation'], 'core/button' => ['text'],
    ];
    if ( isset($block['blockName']) && array_key_exists( $block['blockName'], $text_attributes_to_translate ) ) {
        foreach ( $text_attributes_to_translate[$block['blockName']] as $attr_key ) {
            if ( ! empty( $translated_block['attrs'][ $attr_key ] ) ) {
                $original_text = $translated_block['attrs'][ $attr_key ];
                $translated_text = odt_translate_text_with_openai( $original_text, $target_language, $api_key, "block attribute: {$block['blockName']}/{$attr_key}" );
                if ( is_wp_error( $translated_text ) ) return $translated_text;
                $translated_block['attrs'][ $attr_key ] = $translated_text;
            }
        }
    }
    if ( isset($block['blockName']) && ! empty( $block['innerHTML'] ) ) {
        $blocks_with_direct_innerHTML = ['core/paragraph', 'core/heading', 'core/list-item', 'core/html', 'core/quote']; // Classic editor content also uses innerHTML for 'core/html'
        if(in_array($block['blockName'], $blocks_with_direct_innerHTML) || ($block['blockName'] === 'core/html' && $depth === 0) || strpos($block['blockName'], 'core/') === 0 ) { // Be more generous for core blocks or top-level HTML
            $original_html = $block['innerHTML'];
            $translated_html = odt_translate_text_with_openai( $original_html, $target_language, $api_key, "HTML content for block: {$block['blockName']}" );
            if ( is_wp_error( $translated_html ) ) return $translated_html;
            $translated_block['innerHTML'] = $translated_html;
        }
    }
    if ( isset($block['blockName']) && $block['blockName'] === 'core/image' ) {
        if ( ! empty( $translated_block['attrs']['alt'] ) ) {
            $translated_alt = odt_translate_text_with_openai( $translated_block['attrs']['alt'], $target_language, $api_key, 'image alt text' );
            if ( is_wp_error( $translated_alt ) ) return $translated_alt;
            $translated_block['attrs']['alt'] = $translated_alt;
        }
    }
    return $translated_block;
}

?>