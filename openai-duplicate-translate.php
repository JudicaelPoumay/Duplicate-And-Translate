<?php
/**
 * Plugin Name: OpenAI Duplicate & Translate
 * Description: Adds a button to duplicate posts and translate their content using OpenAI API, showing progress.
 * Version: 1.0
 * Author: Your Name
 * License: GPLv2 or later
 * Text Domain: openai-duplicate-translate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'ODT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 1. Settings Page for API Key and Target Language
add_action( 'admin_menu', 'odt_add_admin_menu' );
add_action( 'admin_init', 'odt_settings_init' );

function odt_add_admin_menu() {
    add_options_page(
        'OpenAI Duplicate & Translate',
        'OpenAI D&T',
        'manage_options',
        'openai_duplicate_translate',
        'odt_options_page_html'
    );
}

function odt_settings_init() {
    register_setting( 'odt_options_group', 'odt_openai_api_key' );
    register_setting( 'odt_options_group', 'odt_target_language', ['default' => 'Spanish'] ); // Default to Spanish

    add_settings_section(
        'odt_settings_section',
        __( 'API Configuration', 'openai-duplicate-translate' ),
        null,
        'odt_options_group'
    );

    add_settings_field(
        'odt_openai_api_key_field',
        __( 'OpenAI API Key', 'openai-duplicate-translate' ),
        'odt_api_key_field_html',
        'odt_options_group',
        'odt_settings_section'
    );

    add_settings_field(
        'odt_target_language_field',
        __( 'Target Language', 'openai-duplicate-translate' ),
        'odt_target_language_field_html',
        'odt_options_group',
        'odt_settings_section'
    );
}

function odt_api_key_field_html() {
    $api_key = get_option( 'odt_openai_api_key' );
    echo '<input type="text" name="odt_openai_api_key" value="' . esc_attr( $api_key ) . '" size="50" />';
    echo '<p class="description">' . __('Enter your OpenAI API key.', 'openai-duplicate-translate') . '</p>';
}

function odt_target_language_field_html() {
    $target_language = get_option( 'odt_target_language', 'Spanish' );
    // You can expand this list or make it a text input for any language
    $languages = ['Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Japanese', 'Chinese (Simplified)'];
    echo '<select name="odt_target_language">';
    foreach ($languages as $lang) {
        echo '<option value="' . esc_attr($lang) . '" ' . selected($target_language, $lang, false) . '>' . esc_html($lang) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . __('Select the language to translate content into.', 'openai-duplicate-translate') . '</p>';

}

function odt_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'odt_options_group' );
            do_settings_sections( 'odt_options_group' );
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}

// 2. Add "Duplicate & Translate" Button to Post Edit Screen
add_action( 'post_submitbox_misc_actions', 'odt_add_duplicate_translate_button' );
function odt_add_duplicate_translate_button( $post ) {
    // Only show for published posts of type 'post' (articles)
    if ( $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
        return;
    }

    $api_key = get_option( 'odt_openai_api_key' );
    $target_language = get_option( 'odt_target_language' );

    if ( empty( $api_key ) || empty( $target_language ) ) {
        echo '<div id="duplicate-translate-action" class="misc-pub-section">';
        echo '<p style="color:red;">' . __('Please configure OpenAI API Key and Target Language in settings.', 'openai-duplicate-translate') . '</p>';
        echo '</div>';
        return;
    }

    // Nonce for security
    $nonce = wp_create_nonce( 'odt_duplicate_translate_nonce_' . $post->ID );
    $url = admin_url( 'admin.php?action=odt_start_translation_process&post_id=' . $post->ID . '&_wpnonce=' . $nonce );

    ?>
    <div id="duplicate-translate-action" class="misc-pub-section">
        <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-primary">
            <?php esc_html_e( 'Duplicate & Translate', 'openai-duplicate-translate' ); ?>
        </a>
        <p class="description">
            <?php printf(
                esc_html__('Translates to %s. Opens in a new tab.', 'openai-duplicate-translate'),
                '<strong>' . esc_html($target_language) . '</strong>'
            ); ?>
        </p>
    </div>
    <?php
}

// 3. Handle the Translation Process (Progress Page)
add_action( 'admin_action_odt_start_translation_process', 'odt_handle_translation_process' );

function odt_handle_translation_process() {
    // Check nonce and user capabilities
    if ( ! isset( $_GET['post_id'], $_GET['_wpnonce'] ) ||
         ! wp_verify_nonce( $_GET['_wpnonce'], 'odt_duplicate_translate_nonce_' . $_GET['post_id'] ) ||
         ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Security check failed or insufficient permissions.' );
    }

    $original_post_id = intval( $_GET['post_id'] );
    $original_post = get_post( $original_post_id );

    if ( ! $original_post || $original_post->post_type !== 'post' ) {
        wp_die( 'Invalid post ID or post type.' );
    }

    $api_key = get_option( 'odt_openai_api_key' );
    $target_language = get_option( 'odt_target_language', 'Spanish' );

    if ( empty( $api_key ) || empty( $target_language ) ) {
        wp_die( 'OpenAI API Key or Target Language not set in plugin settings.' );
    }

    // --- Start Progress Page Output ---
    // Set headers for streaming
    header('Content-Type: text/html; charset=utf-8');
    header('X-Accel-Buffering: no'); // For Nginx, to disable fastcgi_buffering
    if (function_exists('apache_setenv')) { // For Apache
        @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
    }
    @ini_set('implicit_flush', 1); // Enable implicit flush
    ob_implicit_flush(true);

    // Ensure no WordPress or other plugin output buffering interferes
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php _e('Translation Progress', 'openai-duplicate-translate'); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; background-color: #f9f9f9; color: #333; }
            .progress-log { background-color: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px; max-height: 500px; overflow-y: auto; }
            .progress-log p { margin: 0 0 10px; padding-bottom: 5px; border-bottom: 1px dotted #eee; }
            .progress-log p:last-child { border-bottom: none; }
            .progress-log .success { color: green; }
            .progress_log .error { color: red; font-weight: bold; }
            h1 { color: #555; }
            .done a { display: inline-block; padding: 10px 15px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 3px; margin-top: 15px; }
            .done a:hover { background-color: #005177; }
        </style>
    </head>
    <body>
        <h1><?php _e('Translation Progress', 'openai-duplicate-translate'); ?></h1>
        <div id="progress-log" class="progress-log">
            <?php odt_echo_progress( __( 'Starting translation process...', 'openai-duplicate-translate' ) ); ?>
        </div>
        <div id="final-link"></div>

        <script>
            function scrollToBottom() {
                var logDiv = document.getElementById('progress-log');
                logDiv.scrollTop = logDiv.scrollHeight;
            }
        </script>
    <?php
    odt_flush_buffers(); // Initial flush for the page structure

    // 1. Duplicate the post
    odt_echo_progress( __( 'Duplicating post...', 'openai-duplicate-translate' ) );
    $new_post_id = odt_duplicate_post( $original_post );
    if ( is_wp_error( $new_post_id ) ) {
        odt_echo_progress( __( 'Error duplicating post: ', 'openai-duplicate-translate' ) . $new_post_id->get_error_message(), 'error' );
        odt_echo_progress( __( 'Process aborted.', 'openai-duplicate-translate' ), 'error' );
        echo '</body></html>';
        exit;
    }
    odt_echo_progress( sprintf(__( 'Post duplicated successfully. New Post ID: %d', 'openai-duplicate-translate' ), $new_post_id), 'success' );
    odt_flush_buffers();

    // 2. Translate Title
    odt_echo_progress( sprintf(__( 'Translating title to %s...', 'openai-duplicate-translate' ), $target_language) );
    $translated_title = odt_translate_text_with_openai( $original_post->post_title, $target_language, $api_key );

    if ( is_wp_error( $translated_title ) ) {
        odt_echo_progress( __( 'Error translating title: ', 'openai-duplicate-translate' ) . $translated_title->get_error_message(), 'error' );
        // Continue with original title if translation fails
        $translated_title = $original_post->post_title . ' (' . $target_language . ')';
    } else {
        odt_echo_progress( __( 'Title translated: ', 'openai-duplicate-translate' ) . esc_html( $translated_title ), 'success' );
    }

    // Update new post with (potentially) translated title and mark as draft
    wp_update_post( array(
        'ID'         => $new_post_id,
        'post_title' => $translated_title,
        'post_name'  => sanitize_title( $translated_title ), // Slug
        'post_status'=> 'draft', // Keep it as draft initially
    ) );
    odt_echo_progress( __( 'New post title updated and status set to draft.', 'openai-duplicate-translate' ) );
    odt_flush_buffers();

    // 3. Translate Content (Gutenberg Blocks)
    odt_echo_progress( __( 'Parsing content blocks...', 'openai-duplicate-translate' ) );
    $blocks = parse_blocks( $original_post->post_content );
    $translated_blocks = [];

    if ( empty( $blocks ) ) {
        // Handle classic editor content (basic translation)
        odt_echo_progress( __( 'No blocks found. Assuming classic editor content. Translating entire content...', 'openai-duplicate-translate' ) );
        $translated_content_full = odt_translate_text_with_openai( $original_post->post_content, $target_language, $api_key );
        if ( is_wp_error( $translated_content_full ) ) {
            odt_echo_progress( __( 'Error translating classic content: ', 'openai-duplicate-translate' ) . $translated_content_full->get_error_message(), 'error' );
            $final_content = $original_post->post_content; // Use original if error
        } else {
            odt_echo_progress( __( 'Classic content translated.', 'openai-duplicate-translate' ), 'success' );
            $final_content = $translated_content_full;
        }
    } else {
        // Process Gutenberg blocks
        $block_count = count($blocks);
        odt_echo_progress( sprintf(__( 'Found %d blocks. Processing...', 'openai-duplicate-translate' ), $block_count) );
        odt_flush_buffers();

        foreach ( $blocks as $index => $block ) {
            odt_echo_progress( sprintf(__( 'Processing block %d of %d: %s', 'openai-duplicate-translate' ), $index + 1, $block_count, $block['blockName']) );
            $translated_block = odt_translate_block_recursive( $block, $target_language, $api_key );
            $translated_blocks[] = $translated_block;
            odt_flush_buffers(); // Flush after each block potentially
        }
        $final_content = serialize_blocks( $translated_blocks );
        odt_echo_progress( __( 'All blocks processed and translated.', 'openai-duplicate-translate' ), 'success' );
    }

    // Update post content
    wp_update_post( array(
        'ID'           => $new_post_id,
        'post_content' => $final_content,
    ) );
    odt_echo_progress( __( 'New post content updated.', 'openai-duplicate-translate' ), 'success' );
    odt_flush_buffers();

    $edit_link = get_edit_post_link( $new_post_id, 'raw' );
    odt_echo_progress( sprintf(
        __( 'Translation process complete! <span class="done"><a href="%s" target="_blank">Edit Translated Post (ID: %d)</a></span>', 'openai-duplicate-translate' ),
        esc_url($edit_link),
        $new_post_id
    ), 'success' );
    odt_echo_progress( __( 'You can now close this tab.', 'openai-duplicate-translate' ) );

    echo '</body></html>';
    exit; // Important to stop further WordPress execution
}

// Helper function to echo progress and scroll
function odt_echo_progress( $message, $type = 'info' ) {
    echo '<p class="' . esc_attr($type) . '">' . esc_html( date( '[H:i:s] ' ) ) . esc_html( $message ) . "</p>\n";
    echo '<script>scrollToBottom();</script>'; // Ensure script is there to scroll
    odt_flush_buffers();
}

// Helper to ensure buffers are flushed
function odt_flush_buffers(){
    if (ob_get_level() > 0) {
        ob_flush(); // Flush PHP's output buffer
    }
    flush(); // Flush system's output buffer
    // usleep(50000); // Small delay can sometimes help ensure output is sent
}


// 4. Post Duplication Function
function odt_duplicate_post( $post_to_duplicate ) {
    if ( ! $post_to_duplicate || ! is_object( $post_to_duplicate ) ) {
        return new WP_Error( 'invalid_post', 'Invalid post object provided for duplication.' );
    }

    $current_user = wp_get_current_user();
    $new_post_author = $current_user->ID;

    $args = array(
        'comment_status' => $post_to_duplicate->comment_status,
        'ping_status'    => $post_to_duplicate->ping_status,
        'post_author'    => $new_post_author,
        'post_content'   => $post_to_duplicate->post_content, // Will be replaced
        'post_excerpt'   => $post_to_duplicate->post_excerpt, // Consider translating this too
        'post_name'      => $post_to_duplicate->post_name . '-translation', // Temporary slug
        'post_parent'    => $post_to_duplicate->post_parent,
        'post_password'  => $post_to_duplicate->post_password,
        'post_status'    => 'draft', // Start as draft
        'post_title'     => $post_to_duplicate->post_title . ' (Translation)', // Temporary title
        'post_type'      => $post_to_duplicate->post_type,
        'to_ping'        => $post_to_duplicate->to_ping,
        'menu_order'     => $post_to_duplicate->menu_order
    );

    $new_post_id = wp_insert_post( $args, true ); // Pass true for WP_Error on failure

    if ( is_wp_error( $new_post_id ) ) {
        return $new_post_id;
    }

    // Copy post taxonomies (categories, tags)
    $taxonomies = get_object_taxonomies( $post_to_duplicate->post_type );
    foreach ( $taxonomies as $taxonomy ) {
        $post_terms = wp_get_object_terms( $post_to_duplicate->ID, $taxonomy, array( 'fields' => 'slugs' ) );
        wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
    }

    // Copy post meta (custom fields)
    $post_meta_keys = get_post_custom_keys( $post_to_duplicate->ID );
    if ( ! empty( $post_meta_keys ) ) {
        foreach ( $post_meta_keys as $meta_key ) {
            $meta_values = get_post_custom_values( $meta_key, $post_to_duplicate->ID );
            foreach ( $meta_values as $meta_value ) {
                // Consider if some meta keys should not be copied or should be translated
                // For now, we copy all, except things like edit locks.
                if ( $meta_key == '_edit_lock' || $meta_key == '_edit_last' ) continue;
                add_post_meta( $new_post_id, $meta_key, maybe_unserialize($meta_value) );
            }
        }
    }
    return $new_post_id;
}

// 5. OpenAI API Call Function
function odt_translate_text_with_openai( $text_to_translate, $target_language, $api_key, $context = "general text" ) {
    if ( empty( trim( $text_to_translate ) ) ) {
        return ''; // Don't call API for empty strings
    }

    $prompt = "You are a helpful translation assistant.
    Translate the following {$context} into {$target_language}.
    Preserve HTML tags if present in the original text.
    Only provide the translated text, without any additional explanations or introductions.
    Original text:
    \"{$text_to_translate}\"
    Translated text:";

    $api_url = 'https://api.openai.com/v1/chat/completions';
    $body = array(
        'model'    => 'gpt-3.5-turbo', // Or 'gpt-4' if you have access and prefer it
        'messages' => [
            ['role' => 'system', 'content' => "You are a professional translator. Translate accurately to {$target_language} and maintain original HTML formatting if any."],
            ['role' => 'user', 'content' => $text_to_translate]
        ],
        'temperature' => 0.3, // Lower for more factual, higher for more creative
        'max_tokens'  => 2000, // Adjust based on expected length
    );

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    );

    $response = wp_remote_post( $api_url, array(
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => json_encode( $body ),
        'timeout' => 60, // Increase timeout for potentially long translations
    ) );

    if ( is_wp_error( $response ) ) {
        return $response; // Return WP_Error object
    }

    $response_body = wp_remote_retrieve_body( $response );
    $response_data = json_decode( $response_body, true );

    if ( isset( $response_data['choices'][0]['message']['content'] ) ) {
        $translated_text = trim( $response_data['choices'][0]['message']['content'] );
        // Basic cleanup: OpenAI might sometimes wrap in quotes
        if ( (substr($translated_text, 0, 1) == '"' && substr($translated_text, -1) == '"') ||
             (substr($translated_text, 0, 1) == "'" && substr($translated_text, -1) == "'") ) {
            $translated_text = substr($translated_text, 1, -1);
        }
        return $translated_text;
    } elseif ( isset( $response_data['error'] ) ) {
        return new WP_Error( 'openai_api_error', 'OpenAI API Error: ' . $response_data['error']['message'] );
    } else {
        return new WP_Error( 'openai_unknown_error', 'Unknown error from OpenAI API. Response: ' . $response_body );
    }
}


// 6. Recursive Block Translation
function odt_translate_block_recursive( $block, $target_language, $api_key ) {
    $block_name = $block['blockName'];
    $translated_block = $block; // Start with a copy

    // Translate inner blocks first
    if ( ! empty( $block['innerBlocks'] ) ) {
        $translated_inner_blocks = [];
        foreach ( $block['innerBlocks'] as $inner_block ) {
            odt_echo_progress( sprintf(__( '  Translating inner block: %s', 'openai-duplicate-translate' ), $inner_block['blockName']) );
            $translated_inner_blocks[] = odt_translate_block_recursive( $inner_block, $target_language, $api_key );
        }
        $translated_block['innerBlocks'] = $translated_inner_blocks;
    }

    // Translate attributes and content for specific blocks
    // This list needs to be expanded for comprehensive coverage
    $text_attributes_to_translate = [
        'core/heading'   => ['content'],
        'core/paragraph' => ['content'],
        'core/list'      => ['values'], // This is innerHTML of <li> items
        'core/quote'     => ['value', 'citation'],
        'core/button'    => ['text', 'url'], // URL might need special handling if internal
        'core/cover'     => ['title'], // Has innerBlocks too for paragraph text
        // Add more blocks and their text attributes here
    ];
     $html_content_attributes = ['content', 'values', 'value', 'caption']; // Attributes containing HTML

    if ( array_key_exists( $block_name, $text_attributes_to_translate ) ) {
        foreach ( $text_attributes_to_translate[$block_name] as $attr_key ) {
            if ( ! empty( $translated_block['attrs'][ $attr_key ] ) ) {
                $original_text = $translated_block['attrs'][ $attr_key ];
                odt_echo_progress( sprintf(__( '    Translating attribute "%s" for %s: "%s..."', 'openai-duplicate-translate' ), $attr_key, $block_name, substr(strip_tags($original_text), 0, 30)) );
                $translated_text = odt_translate_text_with_openai( $original_text, $target_language, $api_key, "block attribute: {$attr_key}" );
                if ( ! is_wp_error( $translated_text ) ) {
                    $translated_block['attrs'][ $attr_key ] = $translated_text;
                } else {
                    odt_echo_progress( sprintf(__( '    Error translating attribute "%s": %s', 'openai-duplicate-translate' ), $attr_key, $translated_text->get_error_message()), 'error' );
                }
            }
        }
    }

    // Handle innerHTML for blocks that store content directly there (e.g. paragraph, heading after conversion)
    // Or blocks like core/html
    if ( ! empty( $block['innerHTML'] ) ) {
        $original_html = $block['innerHTML'];
        // Heuristic: if it's a block like paragraph or heading, its content is in innerHTML
        $blocks_with_direct_innerHTML = ['core/paragraph', 'core/heading', 'core/list-item', 'core/html', 'core/quote']; // Add more as needed
        if(in_array($block_name, $blocks_with_direct_innerHTML) || strpos($block_name, 'core/') === 0 ) { // Be a bit more generous for core blocks
            odt_echo_progress( sprintf(__( '    Translating innerHTML for %s: "%s..."', 'openai-duplicate-translate' ), $block_name, substr(strip_tags($original_html), 0, 30)) );
            $translated_html = odt_translate_text_with_openai( $original_html, $target_language, $api_key, "HTML content block" );
            if ( ! is_wp_error( $translated_html ) ) {
                $translated_block['innerHTML'] = $translated_html;
            } else {
                odt_echo_progress( sprintf(__( '    Error translating innerHTML: %s', 'openai-duplicate-translate' ), $translated_html->get_error_message()), 'error' );
            }
        }
    }


    // Special handling for core/image alt text and caption
    if ( $block_name === 'core/image' ) {
        if ( ! empty( $translated_block['attrs']['alt'] ) ) {
            odt_echo_progress( sprintf(__( '    Translating alt text for image: "%s..."', 'openai-duplicate-translate' ), substr($translated_block['attrs']['alt'], 0, 30)) );
            $translated_alt = odt_translate_text_with_openai( $translated_block['attrs']['alt'], $target_language, $api_key, 'image alt text' );
            if ( ! is_wp_error( $translated_alt ) ) {
                $translated_block['attrs']['alt'] = $translated_alt;
            } else {
                 odt_echo_progress( sprintf(__( '    Error translating alt text: %s', 'openai-duplicate-translate' ), $translated_alt->get_error_message()), 'error' );
            }
        }
        // Image captions are often in innerHTML of an inner figcaption block, or in `innerHTML` of the image block itself if simple
        // The general innerHTML handling above might catch simple captions.
        // If caption is an innerBlock (e.g. core/paragraph within core/image), recursive call handles it.
    }

    return $translated_block;
}

?>