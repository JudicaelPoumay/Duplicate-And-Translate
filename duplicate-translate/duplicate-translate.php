<?php
/**
 * Plugin Name: Duplicate & Translate
 * Description: ...
 * Version: 0.2
 * Author: Judicael Poumay
 * License: GPLv2 or later
 * Text Domain: duplicate-translate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require PLUGIN_DIR . 'includes/admin-menu.php';
require PLUGIN_DIR . 'includes/post-buttons.php';
require PLUGIN_DIR . 'includes/progress-page.php';
require PLUGIN_DIR . 'includes/translation.php';

?>