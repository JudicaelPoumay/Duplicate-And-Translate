<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php _e('Translation Progress', 'duplicate-translate'); ?></title>
	<?php 
        wp_print_styles('dt-progress-page-style'); 
    ?>
</head>
<body>
	<h1><?php _e('Translation Progress', 'duplicate-translate'); ?> <span id="spinner" class="spinner" style="display:none;"></span></h1>
	
	<div id="translation-form" class="translation-form">
		<h2><?php _e('Translation Settings', 'duplicate-translate'); ?></h2>
		<div class="form-group">
			<label for="target-language"><?php _e('Target Language:', 'duplicate-translate'); ?></label>
			<select id="target-language" name="target-language">
				<?php
				$languages = ['Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Japanese', 'Chinese (Simplified)'];
				$current_language = get_option('target_language', 'French');
				foreach ($languages as $lang) {
					echo '<option value="' . esc_attr($lang) . '" ' . selected($current_language, $lang, false) . '>' . esc_html($lang) . '</option>';
				}
				?>
			</select>
		</div>
		<div class="form-group">
			<label for="translation-context"><?php _e('Translation Context (optional):', 'duplicate-translate'); ?></label>
			<textarea id="translation-context" name="translation-context" rows="4" placeholder="<?php esc_attr_e('Add any specific context or instructions for the translation...', 'duplicate-translate'); ?>"></textarea>
		</div>
		<button id="start-translation" class="button button-primary"><?php _e('Start Translation', 'duplicate-translate'); ?></button>
	</div>

	<div id="progress-container" style="display:none;">
		<div id="progress-log" class="progress-log"></div>
		<div id="block-progress-info" class="block-progress"></div>
		<div id="final-link"></div>
	</div>

	<?php wp_print_scripts('dt-progress-page-script'); ?>
</body>
</html>