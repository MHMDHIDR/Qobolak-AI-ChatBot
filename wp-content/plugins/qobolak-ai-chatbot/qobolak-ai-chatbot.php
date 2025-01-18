<?php
/*
Plugin Name: Qobolak AI ChatBot
Plugin URI: https://www.technodevlabs.com/
Description: A simple WordPress plugin example for learning purposes.
Version: 1.0
Author: Mohammed Ibrahim
Author URI: https://www.technodevlabs.com/
License: GPL2
*/

// Security check to prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue Tailwind CSS and custom JavaScript.
function qobolak_enqueue_assets()
{
    // Enqueue Tailwind CSS.
    wp_enqueue_style(
        'tailwindcss',
        plugins_url('src/styles.css', __FILE__),
        [],
        '1.0'
    );

    // Enqueue the JavaScript file.
    wp_enqueue_script(
        'qobolak-js',
        plugins_url('src/qobolak.js', __FILE__),
        [],
        '1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'qobolak_enqueue_assets');

/**
 * Add the button to the footer of the page.
 */
function qobolak_add_button(): void
{
    echo '<button id="qobolak-btn" class="fixed right-4 bottom-4 px-4 py-2 text-white bg-purple-500 rounded-full shadow-md hover:bg-purple-600">
            Ask Your Name
          </button>';
}
add_action('wp_footer', 'qobolak_add_button');
