<?php
/**
 * Plugin Name: WooCommerce Transient Cleaner
 * Plugin URI: https://github.com/justnasasira/woo-transient-cleaner/
 * Description: Automatically cleans WooCommerce transients to prevent database overload and site crashes.
 * Version: 1.0.0
 * Author: Nasasira Justus
 * Author URI: https://techease.ug
 * Text Domain: woo-transient-cleaner
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WTC_VERSION', '1.0.0');
define('WTC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WTC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WTC_PLUGIN_DIR . 'includes/class-woo-transient-cleaner.php';
require_once WTC_PLUGIN_DIR . 'includes/class-woo-transient-cleaner-admin.php';

// Initialize the plugin
function run_woo_transient_cleaner() {
    $plugin = new Woo_Transient_Cleaner();
    $plugin->run();
}
run_woo_transient_cleaner(); 