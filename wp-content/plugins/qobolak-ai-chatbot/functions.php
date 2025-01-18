<?php

function enqueue_tailwindcss() {
    wp_enqueue_style('tailwindcss', get_template_directory_uri() . '/src/styles.css');
}

add_action('wp_scripts', 'enqueue_tailwindcss');