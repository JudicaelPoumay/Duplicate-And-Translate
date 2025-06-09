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
    register_setting( 'options_group', 'gemini_api_key' );
    register_setting( 'options_group', 'claude_api_key' );
    register_setting( 'options_group', 'deepseek_api_key' );
    register_setting( 'options_group', 'llm_provider', ['default' => 'openai'] );
    register_setting( 'options_group', 'openai_model', ['default' => 'gpt-4o'] );
    register_setting( 'options_group', 'gemini_model', ['default' => 'gemini-1.5-pro-latest'] );
    register_setting( 'options_group', 'claude_model', ['default' => 'claude-opus-4-20250514'] );
    register_setting( 'options_group', 'deepseek_model', ['default' => 'deepseek-chat'] );
    register_setting( 'options_group', 'custom_model' );
    register_setting( 'options_group', 'target_language', ['default' => 'French'] );
    register_setting( 'options_group', 'dt_debug_mode' );

    add_settings_section('settings_section', __('API Configuration', 'duplicate-translate'), null, 'options_group');
    
    add_settings_field('provider_selection_field', __('Select LLM Provider', 'duplicate-translate'), 'provider_selection_field_html', 'options_group', 'settings_section');
    add_settings_field('api_key_fields', __('API Keys', 'duplicate-translate'), 'api_key_fields_html', 'options_group', 'settings_section');
    add_settings_field('model_selection_field', __('Select Model', 'duplicate-translate'), 'model_selection_field_html', 'options_group', 'settings_section');
    add_settings_field('target_language_field', __('Target Language', 'duplicate-translate'), 'target_language_field_html', 'options_group', 'settings_section');
    add_settings_field('debug_mode_field', __('Developer Mode', 'duplicate-translate'), 'debug_mode_field_html', 'options_group', 'settings_section');
}

function api_key_fields_html() {
    ?>
    <div id="openai-key-div">
        <label for="openai_api_key"><?php _e('OpenAI API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr( get_option( 'openai_api_key' ) ); ?>" size="50" />
    </div>
    <div id="gemini-key-div" style="display:none;">
        <label for="gemini_api_key"><?php _e('Gemini API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="gemini_api_key" name="gemini_api_key" value="<?php echo esc_attr( get_option( 'gemini_api_key' ) ); ?>" size="50" />
    </div>
    <div id="claude-key-div" style="display:none;">
        <label for="claude_api_key"><?php _e('Claude API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="claude_api_key" name="claude_api_key" value="<?php echo esc_attr( get_option( 'claude_api_key' ) ); ?>" size="50" />
    </div>
    <div id="deepseek-key-div" style="display:none;">
        <label for="deepseek_api_key"><?php _e('DeepSeek API Key', 'duplicate-translate'); ?></label><br>
        <input type="text" id="deepseek_api_key" name="deepseek_api_key" value="<?php echo esc_attr( get_option( 'deepseek_api_key' ) ); ?>" size="50" />
    </div>
    <?php
}

function provider_selection_field_html() {
    $provider = get_option('llm_provider', 'openai');
    ?>
    <label><input type="radio" name="llm_provider" value="openai" <?php checked($provider, 'openai'); ?>> OpenAI</label><br>
    <label><input type="radio" name="llm_provider" value="gemini" <?php checked($provider, 'gemini'); ?>> Gemini</label><br>
    <label><input type="radio" name="llm_provider" value="claude" <?php checked($provider, 'claude'); ?>> Claude</label><br>
    <label><input type="radio" name="llm_provider" value="deepseek" <?php checked($provider, 'deepseek'); ?>> DeepSeek</label><br>
    <?php
}

function model_selection_field_html() {
    $openai_model = get_option('openai_model', 'gpt-4o');
    $gemini_model = get_option('gemini_model', 'gemini-2.0-flash');
    $claude_model = get_option('claude_model', 'claude-opus-4-20250514');
    $deepseek_model = get_option('deepseek_model', 'deepseek-chat');
    $custom_model = get_option('custom_model', '');

    ?>
    <div id="openai-models">
        <label><input type="radio" name="openai_model" value="gpt-4o" <?php checked($openai_model, 'gpt-4o'); ?>> gpt-4o</label><br>
        <label><input type="radio" name="openai_model" value="gpt-4-turbo" <?php checked($openai_model, 'gpt-4-turbo'); ?>> gpt-4-turbo</label><br>
        <label><input type="radio" name="openai_model" value="gpt-3.5-turbo" <?php checked($openai_model, 'gpt-3.5-turbo'); ?>> gpt-3.5-turbo</label><br>
    </div>
    <div id="gemini-models" style="display:none;">
        <label><input type="radio" name="gemini_model" value="gemini-2.0-flash" <?php checked($gemini_model, 'gemini-2.0-flash'); ?>> gemini-2.0-flash</label><br>
        <label><input type="radio" name="gemini_model" value="gemini-1.5-pro-latest" <?php checked($gemini_model, 'gemini-1.5-pro-latest'); ?>> gemini-1.5-pro-latest</label><br>
        <label><input type="radio" name="gemini_model" value="gemini-pro" <?php checked($gemini_model, 'gemini-pro'); ?>> gemini-pro</label><br>
    </div>
    <div id="claude-models" style="display:none;">
        <label><input type="radio" name="claude_model" value="claude-opus-4-20250514" <?php checked($claude_model, 'claude-opus-4-20250514'); ?>> claude-opus-4-20250514</label><br>
        <label><input type="radio" name="claude_model" value="claude-3-7-sonnet-latest" <?php checked($claude_model, 'claude-3-7-sonnet-latest'); ?>> claude-3-7-sonnet-latest</label><br>
		<label><input type="radio" name="claude_model" value="claude-3-5-haiku-latest" <?php checked($claude_model, 'claude-3-5-haiku-latest'); ?>> claude-3-5-haiku-latest</label><br>
    </div>
    <div id="deepseek-models" style="display:none;">
        <label><input type="radio" name="deepseek_model" value="deepseek-chat" <?php checked($deepseek_model, 'deepseek-chat'); ?>> deepseek-chat</label><br>
        <label><input type="radio" name="deepseek_model" value="deepseek-reasoner" <?php checked($deepseek_model, 'deepseek-reasoner'); ?>> deepseek-reasoner</label><br>
    </div>
    <div id="custom-model-div" style="margin-top: 10px;">
        <label for="custom_model"><?php _e('Custom Model', 'duplicate-translate'); ?></label><br>
        <input type="text" id="custom_model" name="custom_model" value="<?php echo esc_attr($custom_model); ?>" size="40" placeholder="<?php _e('Specify a custom model name', 'duplicate-translate'); ?>" />
        <p class="description"><?php _e('If you fill this, it will override the selection above for the chosen provider.', 'duplicate-translate'); ?></p>
        <p class="description"><?php _e('Beware : some models maybe slower than others, we do not recommend thinking models for translation tasks.', 'duplicate-translate'); ?></p>
    </div>
    <div id="chosen-model-container" style="margin-top: 1em;">
        <b><?php _e('You have chosen model:', 'duplicate-translate'); ?> <span id="chosen-model-text"></span></b>
    </div>
    <?php
}

function target_language_field_html() {
    $target_language = get_option( 'target_language', 'French' );
    echo '<input type="text" name="target_language" value="' . esc_attr( $target_language ) . '" />';
    echo '<p class="description">' . __('Enter the language to translate content into.', 'duplicate-translate') . '</p>';
}

function debug_mode_field_html() {
    $debug_mode = get_option( 'dt_debug_mode', 'off' );
    ?>
    <label>
        <input type="checkbox" name="dt_debug_mode" value="on" <?php checked( $debug_mode, 'on' ); ?> />
        <?php _e( 'Enable Developer Mode', 'duplicate-translate' ); ?>
    </label>
    <p class="description">
        <?php _e( 'This is for developer mode only. Some errors may appear but may not be indicative of bugs.', 'duplicate-translate' ); ?>
    </p>
    <?php
}

function options_page_html() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('options_group'); do_settings_sections('options_group'); submit_button(__('Save Settings', 'duplicate-translate')); ?>
        </form>
		<div id="donation-button">
			<?php require PLUGIN_DIR . 'progress-page-view/donation-button.php'; ?>
		</div>
		<div style="margin-top: 1em;">
			<h3>Contact :</h3>
			<a href="https://www.linkedin.com/in/judicael-poumay/" target="_blank" rel="noopener noreferrer"><?php _e('My LinkedIn', 'duplicate-translate'); ?></a> |
			<a href="https://thethoughtprocess.xyz/" target="_blank" rel="noopener noreferrer"><?php _e('My Website', 'duplicate-translate'); ?></a> |
			<a href="mailto:pro.judicael.poumay@gmail.com"><?php _e('My Email', 'duplicate-translate'); ?></a>
		</div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const providerRadios = document.querySelectorAll('input[name="llm_provider"]');
        const modelGroups = {
            openai: document.getElementById('openai-models'),
            gemini: document.getElementById('gemini-models'),
            claude: document.getElementById('claude-models'),
            deepseek: document.getElementById('deepseek-models')
        };
        const keyDivs = {
            openai: document.getElementById('openai-key-div'),
            gemini: document.getElementById('gemini-key-div'),
            claude: document.getElementById('claude-key-div'),
            deepseek: document.getElementById('deepseek-key-div')
        };
        const chosenModelText = document.getElementById('chosen-model-text');
        const customModelInput = document.getElementById('custom_model');

        function updateChosenModelDisplay() {
            let selectedProvider = document.querySelector('input[name="llm_provider"]:checked').value;
            let chosenModel = '';

            const customModelValue = customModelInput.value.trim();
            if (customModelValue) {
                chosenModel = customModelValue;
            } else {
                const modelRadio = document.querySelector('input[name="' + selectedProvider + '_model"]:checked');
                if (modelRadio) {
                    chosenModel = modelRadio.value;
                }
            }
            chosenModelText.textContent = chosenModel;
        }

        function toggleVisibility() {
            let selectedProvider = document.querySelector('input[name="llm_provider"]:checked').value;

            for (const provider in modelGroups) {
                if (modelGroups[provider]) {
                    modelGroups[provider].style.display = provider === selectedProvider ? 'block' : 'none';
                }
            }
            for (const provider in keyDivs) {
                if (keyDivs[provider]) {
                    keyDivs[provider].style.display = provider === selectedProvider ? 'block' : 'none';
                }
            }
            updateChosenModelDisplay();
        }

        providerRadios.forEach(radio => radio.addEventListener('change', toggleVisibility));
        
        const modelRadios = document.querySelectorAll('input[name$="_model"]');
        modelRadios.forEach(radio => radio.addEventListener('change', updateChosenModelDisplay));
        customModelInput.addEventListener('input', updateChosenModelDisplay);

        toggleVisibility(); // Initial check
    });
    </script>
    <?php
}

