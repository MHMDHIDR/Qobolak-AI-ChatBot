<?php
// Security check to prevent direct access.
if (!defined('ABSPATH')) {
  exit;
}

function qobolak_enqueue_assets()
{
  // Enqueue Tailwind CSS.
  wp_enqueue_style(
    'tailwindcss',
    plugins_url('qobolak-ai-chatbot/src/styles.css', dirname(__FILE__, 2)),
    [],
    '1.0'
  );

  // Enqueue the JavaScript file.
  wp_enqueue_script(
    'qobolak-js',
    plugins_url('qobolak-ai-chatbot/src/qobolak.js', dirname(__FILE__, 2)),
    ['jquery'],
    '1.0',
    true
  );

  // Localize the JavaScript file.
  wp_localize_script('qobolak-js', 'qobolakAjax', [
    'url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('qobolak_nonce'),
  ]);
}
add_action('wp_enqueue_scripts', 'qobolak_enqueue_assets');
