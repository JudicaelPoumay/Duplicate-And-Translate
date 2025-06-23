<?php
/**
 * Admin Menu for Duplicate & Translate Plugin.
 *
 * This file contains the code for the admin menu, settings page,
 * and all related functionality for the Duplicate & Translate plugin.
 *
 * @package Duplicate-And-Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- HOOKS ---
add_action( 'admin_menu', 'duplamtr_add_admin_menu' );
add_action( 'admin_init', 'duplamtr_settings_init' );

/**
 * Add the options page to the admin menu.
 */
function duplamtr_add_admin_menu() {
    $hook = add_options_page('Duplicate & Translate', 'Duplicate & Translate', 'manage_options', 'duplicate_translate', 'duplamtr_options_page_html');
    add_action( "admin_print_scripts-{$hook}", 'duplamtr_admin_enqueue_scripts' );
}

/**
 * Enqueue scripts for the admin page.
 */
function duplamtr_admin_enqueue_scripts() {
    wp_enqueue_style( 'duplamtr-donation-button', DUPLAMTR_PLUGIN_URL . 'assets/donation-button.css', array(), '1.0.0' );
    wp_enqueue_script( 'duplamtr-admin-js', DUPLAMTR_PLUGIN_URL . 'assets/admin-settings.js', array(), '1.0.0', true );
    $translation_array = array(
        'chosen_model' => esc_html__( 'You have chosen model:', 'duplicate-translate' )
    );
    wp_localize_script( 'duplamtr-admin-js', 'duplamtr_vars', $translation_array );
}

/**
 * Initialize the settings.
 */
function duplamtr_settings_init() {
    // --- REGISTER SETTINGS ---
    register_setting( 'duplamtr_options_group', 'duplamtr_openai_api_key', 'sanitize_text_field' );
    register_setting( 'duplamtr_options_group', 'duplamtr_gemini_api_key', 'sanitize_text_field' );
    register_setting( 'duplamtr_options_group', 'duplamtr_claude_api_key', 'sanitize_text_field' );
    register_setting( 'duplamtr_options_group', 'duplamtr_deepseek_api_key', 'sanitize_text_field' );
    register_setting( 'duplamtr_options_group', 'duplamtr_llm_provider',
        array(
            'default' => 'openai',
            'sanitize_callback' => 'duplamtr_sanitize_llm_provider',
        )
    );
    register_setting( 'duplamtr_options_group', 'duplamtr_openai_model',
        array(
            'default' => 'gpt-4o',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    register_setting( 'duplamtr_options_group', 'duplamtr_gemini_model',
        array(
            'default' => 'gemini-1.5-pro-latest',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    register_setting( 'duplamtr_options_group', 'duplamtr_claude_model',
        array(
            'default' => 'claude-opus-4-20250514',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    register_setting( 'duplamtr_options_group', 'duplamtr_deepseek_model',
        array(
            'default' => 'deepseek-chat',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    register_setting( 'duplamtr_options_group', 'duplamtr_custom_model', 'sanitize_text_field' );
    register_setting( 'duplamtr_options_group', 'duplamtr_target_language',
        array(
            'default' => 'French',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    register_setting( 'duplamtr_options_group', 'duplamtr_dt_debug_mode', 'duplamtr_sanitize_debug_mode' );

    // --- ADD SETTINGS SECTIONS ---
    add_settings_section('duplamtr_settings_section', __('API Configuration', 'duplicate-translate'), null, 'duplamtr_options_group');
    
    // --- ADD SETTINGS FIELDS ---
    add_settings_field('duplamtr_provider_selection_field', __('Select LLM Provider', 'duplicate-translate'), 'duplamtr_provider_selection_field_html', 'duplamtr_options_group', 'duplamtr_settings_section');
    add_settings_field('duplamtr_api_key_fields', __('API Keys', 'duplicate-translate'), 'duplamtr_api_key_fields_html', 'duplamtr_options_group', 'duplamtr_settings_section');
    add_settings_field('duplamtr_model_selection_field', __('Select Model', 'duplicate-translate'), 'duplamtr_model_selection_field_html', 'duplamtr_options_group', 'duplamtr_settings_section');
    add_settings_field('duplamtr_target_language_field', __('Target Language', 'duplicate-translate'), 'duplamtr_target_language_field_html', 'duplamtr_options_group', 'duplamtr_settings_section');
    add_settings_field('duplamtr_debug_mode_field', __('Developer Mode', 'duplicate-translate'), 'duplamtr_debug_mode_field_html', 'duplamtr_options_group', 'duplamtr_settings_section');
}

/**
 * Sanitize the LLM provider input.
 *
 * @param string $input The input to sanitize.
 * @return string The sanitized input.
 */
function duplamtr_sanitize_llm_provider( $input ) {
    $allowed_providers = array( 'openai', 'gemini', 'claude', 'deepseek' );
    if ( in_array( $input, $allowed_providers, true ) ) {
        return $input;
    }
    return 'openai'; // Default value
}

/**
 * Sanitize the debug mode input.
 *
 * @param string $input The input to sanitize.
 * @return string The sanitized input.
 */
function duplamtr_sanitize_debug_mode( $input ) {
    return 'on' === $input ? 'on' : 'off';
}

/**
 * HTML for the API key fields.
 */
function duplamtr_api_key_fields_html() {
    ?>
    <div id="openai-key-div">
        <label for="duplamtr_openai_api_key"><?php esc_html_e('OpenAI API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="duplamtr_openai_api_key" name="duplamtr_openai_api_key" value="<?php echo esc_attr( get_option( 'duplamtr_openai_api_key' ) ); ?>" size="50" />
    </div>
    <div id="gemini-key-div" style="display:none;">
        <label for="duplamtr_gemini_api_key"><?php esc_html_e('Gemini API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="duplamtr_gemini_api_key" name="duplamtr_gemini_api_key" value="<?php echo esc_attr( get_option( 'duplamtr_gemini_api_key' ) ); ?>" size="50" />
    </div>
    <div id="claude-key-div" style="display:none;">
        <label for="duplamtr_claude_api_key"><?php esc_html_e('Claude API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="duplamtr_claude_api_key" name="duplamtr_claude_api_key" value="<?php echo esc_attr( get_option( 'duplamtr_claude_api_key' ) ); ?>" size="50" />
    </div>
    <div id="deepseek-key-div" style="display:none;">
        <label for="duplamtr_deepseek_api_key"><?php esc_html_e('DeepSeek API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="duplamtr_deepseek_api_key" name="duplamtr_deepseek_api_key" value="<?php echo esc_attr( get_option( 'duplamtr_deepseek_api_key' ) ); ?>" size="50" />
    </div>
    <?php
}

/**
 * HTML for the provider selection field.
 */
function duplamtr_provider_selection_field_html() {
    $provider = get_option('duplamtr_llm_provider', 'openai');
    ?>
    <label><input type="radio" name="duplamtr_llm_provider" value="openai" <?php checked($provider, 'openai'); ?>> OpenAI</label><br>
    <label><input type="radio" name="duplamtr_llm_provider" value="gemini" <?php checked($provider, 'gemini'); ?>> Gemini</label><br>
    <label><input type="radio" name="duplamtr_llm_provider" value="claude" <?php checked($provider, 'claude'); ?>> Claude</label><br>
    <label><input type="radio" name="duplamtr_llm_provider" value="deepseek" <?php checked($provider, 'deepseek'); ?>> DeepSeek</label><br>
    <?php
}

/**
 * HTML for the model selection field.
 */
function duplamtr_model_selection_field_html() {
    $openai_model = get_option('duplamtr_openai_model', 'gpt-4o');
    $gemini_model = get_option('duplamtr_gemini_model', 'gemini-1.5-pro-latest');
    $claude_model = get_option('duplamtr_claude_model', 'claude-opus-4-20250514');
    $deepseek_model = get_option('duplamtr_deepseek_model', 'deepseek-chat');
    $custom_model = get_option('duplamtr_custom_model', '');

    ?>
    <div id="openai-models">
        <label><input type="radio" name="duplamtr_openai_model" value="gpt-4o" <?php checked($openai_model, 'gpt-4o'); ?>> gpt-4o</label><br>
        <label><input type="radio" name="duplamtr_openai_model" value="gpt-4-turbo" <?php checked($openai_model, 'gpt-4-turbo'); ?>> gpt-4-turbo</label><br>
        <label><input type="radio" name="duplamtr_openai_model" value="gpt-3.5-turbo" <?php checked($openai_model, 'gpt-3.5-turbo'); ?>> gpt-3.5-turbo</label><br>
    </div>
    <div id="gemini-models" style="display:none;">
        <label><input type="radio" name="duplamtr_gemini_model" value="gemini-1.5-pro-latest" <?php checked($gemini_model, 'gemini-1.5-pro-latest'); ?>> gemini-1.5-pro-latest</label><br>
        <label><input type="radio" name="duplamtr_gemini_model" value="gemini-pro" <?php checked($gemini_model, 'gemini-pro'); ?>> gemini-pro</label><br>
    </div>
    <div id="claude-models" style="display:none;">
        <label><input type="radio" name="duplamtr_claude_model" value="claude-opus-4-20250514" <?php checked($claude_model, 'claude-opus-4-20250514'); ?>> claude-opus-4-20250514</label><br>
        <label><input type="radio" name="duplamtr_claude_model" value="claude-3-7-sonnet-latest" <?php checked($claude_model, 'claude-3-7-sonnet-latest'); ?>> claude-3-7-sonnet-latest</label><br>
		<label><input type="radio" name="duplamtr_claude_model" value="claude-3-5-haiku-latest" <?php checked($claude_model, 'claude-3-5-haiku-latest'); ?>> claude-3-5-haiku-latest</label><br>
    </div>
    <div id="deepseek-models" style="display:none;">
        <label><input type="radio" name="duplamtr_deepseek_model" value="deepseek-chat" <?php checked($deepseek_model, 'deepseek-chat'); ?>> deepseek-chat</label><br>
        <label><input type="radio" name="duplamtr_deepseek_model" value="deepseek-reasoner" <?php checked($deepseek_model, 'deepseek-reasoner'); ?>> deepseek-reasoner</label><br>
    </div>
    <div id="custom-model-div" style="margin-top: 10px;">
        <label for="duplamtr_custom_model"><?php esc_html_e('Custom Model', 'duplicate-translate'); ?></label><br>
        <input type="text" id="duplamtr_custom_model" name="duplamtr_custom_model" value="<?php echo esc_attr($custom_model); ?>" size="40" placeholder="<?php esc_attr_e('Specify a custom model name', 'duplicate-translate'); ?>" />
        <p class="description"><?php esc_html_e('If you fill this, it will override the selection above for the chosen provider.', 'duplicate-translate'); ?></p>
        <p class="description"><?php esc_html_e('Beware : some models maybe slower than others, we do not recommend thinking models for translation tasks.', 'duplicate-translate'); ?></p>
    </div>
    <div id="chosen-model-container" style="margin-top: 1em;">
        <b><span id="chosen-model-text-prefix"></span> <span id="chosen-model-text"></span></b>
    </div>
    <?php
}

/**
 * HTML for the target language field.
 */
function duplamtr_target_language_field_html() {
    $target_language = get_option( 'duplamtr_target_language', 'French' );
    echo '<input type="text" name="duplamtr_target_language" value="' . esc_attr( $target_language ) . '" />';
    echo '<p class="description">' . esc_html__('Enter the language to translate content into.', 'duplicate-translate') . '</p>';
}

/**
 * HTML for the debug mode field.
 */
function duplamtr_debug_mode_field_html() {
    $debug_mode = get_option( 'duplamtr_dt_debug_mode', 'off' );
    ?>
    <label>
        <input type="checkbox" name="duplamtr_dt_debug_mode" value="on" <?php checked( $debug_mode, 'on' ); ?> />
        <?php esc_html_e( 'Enable Developer Mode', 'duplicate-translate' ); ?>
    </label>
    <p class="description">
        <?php esc_html_e( 'This is for developer mode only. Some errors may appear but may not be indicative of bugs.', 'duplicate-translate' ); ?>
    </p>
    <?php
}

/**
 * HTML for the options page.
 */
function duplamtr_options_page_html() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('duplamtr_options_group'); do_settings_sections('duplamtr_options_group'); submit_button(__('Save Settings', 'duplicate-translate')); ?>
        </form>
		<div id="donation-button">
			<?php require DUPLAMTR_PLUGIN_DIR . 'progress-page-view/donation-button.php'; ?>
		</div>
		<div style="margin-top: 1em;">
			<h3><?php esc_html_e( 'Contact :', 'duplicate-translate' ); ?></h3>
			<a href="https://www.linkedin.com/in/judicael-poumay/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('My LinkedIn', 'duplicate-translate'); ?></a> |
			<a href="https://thethoughtprocess.xyz/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('My Website', 'duplicate-translate'); ?></a> |
			<a href="mailto:pro.judicael.poumay@gmail.com"><?php esc_html_e('My Email', 'duplicate-translate'); ?></a>
		</div>
    </div>
    <?php
}

