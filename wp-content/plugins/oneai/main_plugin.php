<?php
/*
Plugin Name: OneAI Agent Plugin
Description: Add your OneAI Agent to your WordPress site.
Version: 1.5.0
Author: OneAI
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

namespace OneAI;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! function_exists( 'oneai_add_settings_page' ) ) {
    function oneai_add_settings_page() {
        add_menu_page(
            'OneAI Settings',           // Page title
            'OneAI',                    // Menu title
            'manage_options',           // Capability
            'oneai-settings',           // Menu slug
            __NAMESPACE__ . '\\oneai_settings_page', // Callback function
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iOTUxIiBoZWlnaHQ9Ijc1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNTkyIDMyMSAzMDkgNzUwaDU2N0w1OTIgMzIxWk05NTAgMEg3NjN2NDMwaDE4N1YwWk0yMjEgNTQyYTIyMSAyMjEgMCAxIDAgMC00NDIgMjIxIDIyMSAwIDAgMCAwIDQ0MloiIGZpbGw9IkN1cnJlbnRDb2xvciIvPjwvc3ZnPg=='
        );
    }
}

if ( ! function_exists( 'oneai_settings_page' ) ) {
    function oneai_settings_page() {
        global $default_agent_url;
        ?>
        <div class="wrap">
            <h1>OneAI Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('oneai-options-group'); 
                do_settings_sections('oneai-options-group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Agent Name:</th>
                        <td>
                        <input pattern="[^@#$%^&]+" onchange="setCustomValidity('')" oninvalid="this.setCustomValidity('Please Insert a valid agent name.')" required  placeholder="~example-agent-name" type="text" name="agent_url" value="<?php echo esc_attr(get_option('agent_url', $default_agent_url)); ?>" />
                        </td>
                    </tr>
                </table><p>Open the <a href='https://studio.oneai.com'>Agent Studio</a> to customize agent settings, knowledge and behaviors</p>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

if ( ! function_exists( 'oneai_register_settings' ) ) {
    function oneai_register_settings() {
        register_setting('oneai-options-group', 'agent_url');
    }
}

if ( ! function_exists( 'oneai_embed_agent' ) ) {
    function oneai_embed_agent() {
        global $default_agent_url;
    
        $param = get_option('agent_url', $default_agent_url);
        $agent_name = oneai_extract_public_name($param);
        
        if (!empty($agent_name)) {
            $escaped_agent_name = esc_attr($agent_name);
            $extracted_domain = oneai_extract_domain($param);
            $url = esc_url($extracted_domain.'/~widget?id=' . $escaped_agent_name);
            wp_enqueue_script('oneai-snippet', $url, array(), null, true);
        }
    }
    
}

if ( ! function_exists( 'oneai_add_actions' ) ) {
    function oneai_add_actions() {
        add_action('admin_menu', __NAMESPACE__ . '\\oneai_add_settings_page');
        add_action('admin_init', __NAMESPACE__ . '\\oneai_register_settings');
        add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\oneai_embed_agent');
    }
}

if ( ! function_exists( 'oneai_extract_domain' ) ) {
    function oneai_extract_domain($string) {
        $parsed_url = parse_url($string);
        if (isset($parsed_url['host'])) {
            $domain = $parsed_url['scheme'] . '://' . $parsed_url['host'];
            $domain = htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
            if (substr($parsed_url['host'], -10) === '.oneai.com') {
                return $domain;
            }
        }
        $default_domain = 'https://oneai.com';
        return $default_domain;
    }
}

if ( ! function_exists( 'oneai_extract_public_name' ) ) {
    function oneai_extract_public_name($string) {
        $patterns = [
        '/(?:\?id=)([a-zA-Z0-9_-]+)/',  
        '/^https?:\/\/[^\/]+\/~([^?#]+)(?:\?.*)?$/',  
        '/^~([^?#]+)(?:\?.*)?$/',   
        '/^([^?#]+)(?:\?.*)?$/',   
        ];
    
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $string, $matches)) {
                return $matches[1];  
            }
        }
        return null; 
    }
}


oneai_add_actions();
?>
