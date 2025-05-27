<?php
/*
* Plugin Name: Smart WP Export
* Description: Export any WordPress database table with filtering and pagination.
* Version: 1.0.0
* Contributors: smartartsbt
* Author: Szucs Janos
* Author URI: http://smartart.hu
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: smart-wp-export
* Domain Path: /languages
* Tags: export, database, shortcode, custom, backup, csv
* Requires at least: 5.0
* Tested up to: 6.8
* Stable tag: 1.0.0
*/

defined('ABSPATH') || exit;

define('SWE_PATH', plugin_dir_path(__FILE__));
define('SWE_URL', plugin_dir_url(__FILE__));
define('SWE_FILE', __FILE__); 

require_once SWE_PATH . 'includes/helpers.php';
require_once SWE_PATH . 'includes/class-swe-core.php';
require_once SWE_PATH . 'includes/class-swe-shortcode.php';

SWE_Shortcode_Manager::init();

add_action('admin_enqueue_scripts', function () {
   $pages = ['smart-wp-export', 'swe-shortcode-configs']; 
   if (isset($_GET['page']) && in_array($_GET['page'], $pages)) {
        wp_enqueue_style('swe-style', SWE_URL . 'assets/style.css', [], '1.0');
        wp_register_script('swe-htmx', SWE_URL . 'assets/htmx.min.js', [], '1.9.5', true);
        wp_enqueue_script('swe-htmx');
    }
});

add_action('wp_enqueue_scripts', function () {
    if (is_singular()) {
        global $post;
        if (has_shortcode($post->post_content, 'smart_export_viewer')) {
            wp_enqueue_style('swe-style', SWE_URL . 'assets/style.css', [], '1.0');
        }
    }
});

function swe_load_textdomain() {
    load_plugin_textdomain('smart-wp-export', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'swe_load_textdomain');



// Admin menu setup
add_action('admin_menu', function () {
    add_menu_page(
        __('Smart WP Export', 'smart-wp-export'),
        __('Database Export', 'smart-wp-export'),
        'manage_options',
        'smart-wp-export',
        ['SWE_Core', 'render_admin_page'],
        'dashicons-database-export'
    );
});



// REST API setup
add_action('rest_api_init', function () {
    register_rest_route('smart-export/v1', '/table', [
        'methods'  => ['GET', 'POST'],
        'callback' => ['SWE_Core', 'handle_request'],
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);
});

// Initialize the plugin
SWE_Core::init();
