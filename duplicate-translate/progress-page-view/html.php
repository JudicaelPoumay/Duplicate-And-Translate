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
	<p><?php _e('Do not close this tab until the translation is complete.', 'duplicate-translate'); ?></p>
	<div id="translation-form" class="translation-form">
		<h2><?php _e('Translation Settings', 'duplicate-translate'); ?></h2>
		<div class="form-group">
			<label for="target-language"><?php _e('Target Language:', 'duplicate-translate'); ?></label>
			<input type="text" id="target-language" name="target-language" value="<?php echo esc_attr(get_option('target_language', 'French')); ?>" />
		</div>
		<div class="form-group">
			<label for="translation-context"><?php _e('Translation Context (optional):', 'duplicate-translate'); ?></label>
			<textarea id="translation-context" name="translation-context" rows="4" placeholder="<?php esc_attr_e('Add any specific context or instructions for the translation...', 'duplicate-translate'); ?>"></textarea>
		</div>
		<button id="start-translation" class="button button-primary"><?php _e('Start Translation', 'duplicate-translate'); ?></button>
	</div>

	<div id="progress-container" style="display:none;">
        <?php 
        $debug_mode = get_option( 'dt_debug_mode' );
        $style = ( $debug_mode === 'on' ) ? '' : 'style="display:none;"';
        ?>
		<div id="progress-log" class="progress-log" <?php echo $style; ?>></div>
		<progress id="block-progress-bar" value="0" max="1" style="width: 100%;"></progress>
		<div id="block-progress-info" class="block-progress"></div>
		<div id="final-link"></div>
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

	<?php wp_print_scripts('dt-progress-page-script'); ?>
</body>
</html>