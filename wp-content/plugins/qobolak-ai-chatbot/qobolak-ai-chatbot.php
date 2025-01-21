<?php
/*
Plugin Name: Qobolak AI ChatBot
Plugin URI: https://www.technodevlabs.com
Description: An AI-powered chatbot that provides information about Qobolak's services using web scraping and OpenAI.
Version: 1.0
Author: Mohammed Ibrahim
Author URI: https://mohammedhaydar.com
License: GPL2
*/

// Security check to prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('QOBOLAK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QOBOLAK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QOBOLAK_VERSION', '1.0');

// Include plugin files
require_once QOBOLAK_PLUGIN_DIR . 'includes/class-qobolak-web-knowledge.php';
require_once QOBOLAK_PLUGIN_DIR . 'includes/admin-settings.php';
require_once QOBOLAK_PLUGIN_DIR . 'includes/enqueue-assets.php';
require_once QOBOLAK_PLUGIN_DIR . 'includes/render-button.php';
require_once QOBOLAK_PLUGIN_DIR . 'includes/ajax-handlers.php';

// Plugin activation hook
register_activation_hook(__FILE__, 'qobolak_activate_plugin');

function qobolak_activate_plugin()
{
    // Create necessary database tables
    $knowledge = new Qobolak_Web_Knowledge();

    // Set default options
    if (!get_option('qobolak_openai_settings')) {
        add_option('qobolak_openai_settings', array(
            'api_key' => '',
            'max_tokens' => 150,
            'temperature' => 0.7,
            'rate_limit' => 30,
            'cache_duration' => 3600
        ));
    }

    // Schedule initial website scraping
    if (!wp_next_scheduled('qobolak_initial_scrape')) {
        wp_schedule_single_event(time() + 60, 'qobolak_initial_scrape');
    }

    // Schedule regular updates
    if (!wp_next_scheduled('qobolak_update_knowledge')) {
        wp_schedule_event(time(), 'weekly', 'qobolak_update_knowledge');
    }

    // Create cache directory
    $upload_dir = wp_upload_dir();
    $cache_dir = $upload_dir['basedir'] . '/qobolak-cache';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'qobolak_deactivate_plugin');

function qobolak_deactivate_plugin()
{
    // Clear scheduled tasks
    wp_clear_scheduled_hook('qobolak_update_knowledge');
    wp_clear_scheduled_hook('qobolak_initial_scrape');
}

// Plugin uninstall hook
register_uninstall_hook(__FILE__, 'qobolak_uninstall_plugin');

function qobolak_uninstall_plugin()
{
    global $wpdb;

    // Remove plugin options
    delete_option('qobolak_openai_settings');

    // Remove database tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qobolak_external_knowledge");

    // Remove cache directory
    $upload_dir = wp_upload_dir();
    $cache_dir = $upload_dir['basedir'] . '/qobolak-cache';
    if (file_exists($cache_dir)) {
        array_map('unlink', glob("$cache_dir/*.*"));
        rmdir($cache_dir);
    }
}

// Initialize scheduled tasks
add_action('qobolak_initial_scrape', function () {
    $knowledge = new Qobolak_Web_Knowledge();
    $knowledge->scrape_website();
});

add_action('qobolak_update_knowledge', function () {
    $knowledge = new Qobolak_Web_Knowledge();
    $knowledge->scrape_website();
});

// Add admin notice for required setup
add_action('admin_notices', function () {
    $settings = get_option('qobolak_openai_settings');
    if (empty($settings['api_key'])) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php _e('Qobolak AI ChatBot requires an OpenAI API key to function. Please visit the <a href="' .
                    admin_url('options-general.php?page=qobolak-settings') .
                    '">settings page</a> to configure it.', 'qobolak-ai-chatbot'); ?>
            </p>
        </div>
        <?php
    }
});

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=qobolak-settings') . '">' .
        __('Settings', 'qobolak-ai-chatbot') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});