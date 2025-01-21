<?php
// Security check to prevent direct access.
if (!defined('ABSPATH')) {
  exit;
}

function qobolak_add_button()
{
  echo '<button id="qobolak-btn" class="fixed right-4 bottom-4 px-4 py-2 text-white bg-purple-500 rounded-full shadow-md hover:bg-purple-600">
            Ask Qobolak
          </button>';
}
add_action('wp_footer', 'qobolak_add_button');
