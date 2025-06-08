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
	<title><?php _e('Configuration Error', 'duplicate-translate'); ?></title>
    <?php 
        wp_print_styles('dt-progress-page-style');
    ?>
</head>
<body>
	<h1>Duplicate and Translate : Please define the LLM and API key in the plugin settings</h1>
</body>
</html>