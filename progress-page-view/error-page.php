<?php
/**
 * Error Page for Duplicate & Translate Plugin.
 *
 * This file contains the HTML for the configuration error page.
 *
 * @package Duplicate-And-Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>


<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e('Configuration Error', 'duplicate-translate'); ?></title>
    <?php wp_head(); ?>
</head>
<body>
	<h1><?php esc_html_e('Duplicate and Translate: Please define the LLM provider and API key in the plugin settings.', 'duplicate-translate'); ?></h1>
    <?php wp_footer(); ?>
</body>
</html>