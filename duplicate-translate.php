<?php
/**
 * Plugin Name: Duplicate & Translate
 * Description: Easily duplicate any post or page, then automatically translate it into your desired language while keeping its formatting using a configurable AI translation provider.
 * Version: 0.2
 * Author: Judicael Poumay
 * License: GPLv2 or later
 * Text Domain: duplicate-translate
 *
 * @package Duplicate-And-Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- CONSTANTS ---
define( 'PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// --- INCLUDES ---
require PLUGIN_DIR . 'includes/admin-menu.php';
require PLUGIN_DIR . 'includes/post-buttons.php';
require PLUGIN_DIR . 'includes/progress-page.php';
require PLUGIN_DIR . 'includes/translation.php';

// --- HOOKS ---
add_action( 'plugins_loaded', 'duplicate_translate_load_textdomain' );

/**
 * Load the plugin text domain for translation.
 */
function duplicate_translate_load_textdomain() {
    load_plugin_textdomain( 'duplicate-translate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

?>