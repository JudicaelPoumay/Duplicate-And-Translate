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
	<style>
		<?php require PLUGIN_DIR . 'progress-page-view/css.php'; ?>
	</style>
	<?php wp_print_scripts('jquery'); ?>
</head>
<body>
	<h1><?php _e('Translation Progress', 'duplicate-translate'); ?> <span id="spinner" class="spinner" style="display:none;"></span></h1>
	<div id="progress-log" class="progress-log"></div>
	<div id="block-progress-info" class="block-progress"></div>
	<div id="final-link"></div>

	<?php require PLUGIN_DIR . 'progress-page-view/js.php'; ?>
</body>
</html>