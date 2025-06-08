<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function duplicate_post( $post_to_duplicate ) {
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

function translate_text( $text_to_translate, $target_language, $context = "general text", $translation_context = '' ) {
    if ( empty( trim( $text_to_translate ) ) ) return '';

    $provider = get_option('llm_provider', 'openai');
    $custom_model = get_option('custom_model');
    
    $model = '';
    $api_url = '';
    $headers = [];
    $body = [];

    switch ($provider) {
        case 'gemini':
            $model = !empty($custom_model) ? $custom_model : get_option('gemini_model', 'gemini-1.5-pro-latest');
            $api_key = get_option('gemini_api_key');
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
            $headers = ['Content-Type'  => 'application/json'];
            $body = [
                'contents' => [
                    ['parts' => [
                        ['text' => "You are a professional translator. Translate accurately to {$target_language} and maintain original HTML formatting if any. Only return the translated text." . (!empty($translation_context) ? "\n\nAdditional context for translation:\n" . $translation_context : "") . "\n\nTranslate the following text:\n" . $text_to_translate]
                    ]]
                ]
            ];
            break;
        case 'claude':
            $model = !empty($custom_model) ? $custom_model : get_option('claude_model', 'claude-3-opus-20240229');
            $api_key = get_option('claude_api_key');
            $api_url = 'https://api.anthropic.com/v1/messages';
            $headers = [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ];
            $body = [
                'model' => $model,
                'max_tokens' => 2000,
                'temperature' => 0.3,
                'system' => "You are a professional translator. Translate accurately to {$target_language} and maintain original HTML formatting if any. Only return the translated text." . (!empty($translation_context) ? "\n\nAdditional context for translation:\n" . $translation_context : ""),
                'messages' => [
                    ['role' => 'user', 'content' => $text_to_translate]
                ]
            ];
            break;
        case 'deepseek':
            $model = !empty($custom_model) ? $custom_model : get_option('deepseek_model', 'deepseek-chat');
            $api_key = get_option('deepseek_api_key');
            $api_url = 'https://api.deepseek.com/v1/chat/completions';
            $headers = ['Authorization' => 'Bearer ' . $api_key, 'Content-Type'  => 'application/json'];
            $body = [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => "You are a professional translator. Translate accurately to {$target_language} and maintain original HTML formatting if any. Only return the translated text." . (!empty($translation_context) ? "\n\nAdditional context for translation:\n" . $translation_context : "")],
                    ['role' => 'user', 'content' => $text_to_translate]
                ],
                'temperature' => 0.3, 'max_tokens'  => 2000,
            ];
            break;
        case 'openai':
        default:
            $model = !empty($custom_model) ? $custom_model : get_option('openai_model', 'gpt-4o');
            $api_key = get_option('openai_api_key');
            $api_url = 'https://api.openai.com/v1/chat/completions';
            $headers = ['Authorization' => 'Bearer ' . $api_key, 'Content-Type'  => 'application/json'];
            $system_prompt = "You are a professional translator. Translate accurately to {$target_language} and maintain original HTML formatting if any. Only return the translated text.";
            if (!empty($translation_context)) {
                $system_prompt .= "\n\nAdditional context for translation:\n" . $translation_context;
            }
            $body = [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $text_to_translate]
                ],
                'temperature' => 0.3, 'max_tokens'  => 2000,
            ];
            break;
    }

    $max_attempts = 4;
    $delay = 1;
    $last_error = null;
    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $response = wp_remote_post( $api_url, ['method'  => 'POST', 'headers' => $headers, 'body' => json_encode( $body ), 'timeout' => 60] );
        if ( is_wp_error( $response ) ) {
            $last_error = $response;
        } else {
            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body, true );

            $translated_text = '';
            if ($provider === 'gemini') {
                if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
                    $translated_text = $response_data['candidates'][0]['content']['parts'][0]['text'];
                }
            } elseif ($provider === 'claude') {
                if (isset($response_data['content'][0]['text'])) {
                    $translated_text = $response_data['content'][0]['text'];
                }
            } else { // openai, deepseek
                if (isset( $response_data['choices'][0]['message']['content'])) {
                    $translated_text = $response_data['choices'][0]['message']['content'];
                }
            }
            
            if (!empty($translated_text)) {
                $translated_text = trim( $translated_text );
                if ( (substr($translated_text, 0, 1) == '"' && substr($translated_text, -1) == '"') || (substr($translated_text, 0, 1) == "'" && substr($translated_text, -1) == "'") ) {
                    $translated_text = substr($translated_text, 1, -1);
                }
                return $translated_text;
            } elseif ( isset( $response_data['error'] ) ) {
                $last_error = new WP_Error( 'api_error', 'API Error (' . $provider . '): ' . $response_data['error']['message'] . ' (Context: '.esc_html($context).', Original: '. substr(esc_html($text_to_translate),0,50).'...)');
            } else {
                $last_error = new WP_Error( 'unknown_api_error', 'Unknown error from ' . $provider . ' API. Response: ' . esc_html($response_body) );
            }
        }
        if ($attempt < $max_attempts) {
            sleep($delay);
            $delay *= 2;
        }
    }
    return $last_error;
}

function translate_block_recursive_for_ajax( $block, $target_language, $depth = 0, $translation_context = '' ) {
	if(empty( $block['innerHTML'] ))
		return $block;
	
    $translated_block = $block;
    if ( ! empty( $block['innerBlocks'] ) ) {
        $translated_inner_blocks = [];
        foreach ( $block['innerBlocks'] as $inner_block ) {
            $translated_inner_block_result = translate_block_recursive_for_ajax( $inner_block, $target_language, $depth + 1, $translation_context );
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
                $translated_text = translate_text( $original_text, $target_language, "block attribute: {$block['blockName']}/{$attr_key}", $translation_context );
                if ( is_wp_error( $translated_text ) ) return $translated_text;
                $translated_block['attrs'][ $attr_key ] = $translated_text;
            }
        }
    }
    if ( isset($block['blockName']) && ! empty( $block['innerContent'][0] ) ) {
        $blocks_with_direct_content = ['core/paragraph', 'core/heading', 'core/list-item', 'core/html', 'core/quote']; // Classic editor content also uses innerContent for 'core/html'
        if(in_array($block['blockName'], $blocks_with_direct_content) || ($block['blockName'] === 'core/html' && $depth === 0) || strpos($block['blockName'], 'core/') === 0 ) { // Be more generous for core blocks or top-level HTML
            $original_content = $block['innerContent'][0];
            $translated_content = translate_text( $original_content, $target_language, "content for block: {$block['blockName']}", $translation_context );
            if ( is_wp_error( $translated_content ) ) return $translated_content;
            $translated_block['innerContent'][0] = $translated_content;
        }
    }
    if ( isset($block['blockName']) && $block['blockName'] === 'core/image' ) {
        if ( ! empty( $translated_block['attrs']['alt'] ) ) {
            $translated_alt = translate_text( $translated_block['attrs']['alt'], $target_language, 'image alt text', $translation_context );
            if ( is_wp_error( $translated_alt ) ) return $translated_alt;
            $translated_block['attrs']['alt'] = $translated_alt;
        }
    }


    error_log(print_r($translated_block, true));
    return $translated_block;
}
