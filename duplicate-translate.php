<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/JudicaelPoumay/Duplicate-And-Translate
 * @since             1.0.2
 * @package           Duplicate-And-Translate
 *
 * @wordpress-plugin
 * Plugin Name:       Duplicate & Translate
 * Plugin URI:        https://github.com/JudicaelPoumay/Duplicate-And-Translate
 * Description:       Easily duplicate any post or page, then automatically translate it into your desired language while keeping its formatting using a configurable AI translation provider.
 * Version:           1.0.2
 * Author:            Judicael Poumay
 * Author URI:        https://github.com/JudicaelPoumay
 * License:           GPLv2
 * License URI:       https://github.com/JudicaelPoumay/Duplicate-And-Translate/blob/main/LICENSE
 * Requires at least: 6.2
 * Tested up to:      6.8
 * Text Domain:       duplicate-translate
 * Domain Path:       /languages
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- CONSTANTS ---
define( 'DUPLAMTR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DUPLAMTR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// --- INCLUDES ---
require DUPLAMTR_PLUGIN_DIR . 'includes/admin-menu.php';
require DUPLAMTR_PLUGIN_DIR . 'includes/post-buttons.php';
require DUPLAMTR_PLUGIN_DIR . 'includes/progress-page.php';
require DUPLAMTR_PLUGIN_DIR . 'includes/translation.php';