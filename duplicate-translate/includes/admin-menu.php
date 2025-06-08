<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- SETTINGS (Unchanged from previous version) ---
add_action( 'admin_menu', 'add_admin_menu' );
add_action( 'admin_init', 'settings_init' );

function add_admin_menu() {
    add_options_page('Duplicate & Translate', 'Duplicate & Translate', 'manage_options', 'duplicate_translate', 'options_page_html');
}

function settings_init() {
    register_setting( 'options_group', 'openai_api_key' );
    register_setting( 'options_group', 'target_language', ['default' => 'French'] );
    add_settings_section('settings_section', __('API Configuration', 'duplicate-translate'), null, 'options_group');
    add_settings_field('openai_api_key_field', __('OpenAI API Key', 'duplicate-translate'), 'api_key_field_html', 'options_group', 'settings_section');
    add_settings_field('target_language_field', __('Target Language', 'duplicate-translate'), 'target_language_field_html', 'options_group', 'settings_section');
}

function api_key_field_html() {
    $api_key = get_option( 'openai_api_key' );
    echo '<input type="text" name="openai_api_key" value="' . esc_attr( $api_key ) . '" size="50" />';
    echo '<p class="description">' . __('Enter your OpenAI API key.', 'duplicate-translate') . '</p>';
}

function target_language_field_html() {
    $target_language = get_option( 'target_language', 'French' );
    $languages = ['Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Japanese', 'Chinese (Simplified)'];
    echo '<select name="target_language">';
    foreach ($languages as $lang) {
        echo '<option value="' . esc_attr($lang) . '" ' . selected($target_language, $lang, false) . '>' . esc_html($lang) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . __('Select the language to translate content into.', 'duplicate-translate') . '</p>';
}

function options_page_html() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('options_group'); do_settings_sections('options_group'); submit_button('Save Settings'); ?>
        </form>
		<div id="donation-button">
			<?php require PLUGIN_DIR . 'progress-page-view/donation-button.php'; ?>
		</div>
		<div style="margin-top: 1em;">
			<h3>Contact :</h3>
			<a href="https://www.linkedin.com/in/judicael-poumay/" target="_blank" rel="noopener noreferrer">My LinkedIn</a> |
			<a href="https://thethoughtprocess.xyz/" target="_blank" rel="noopener noreferrer">My Website</a> |
			<a href="mailto:pro.judicael.poumay@gmail.com">My Email</a>
		</div>
    </div>
    <?php
}

