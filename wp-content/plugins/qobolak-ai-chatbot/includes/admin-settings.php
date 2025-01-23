<?php

if (!defined('ABSPATH')) {
  exit;
}

class Qobolak_Admin_Settings
{
  private $option_name = 'qobolak_openai_settings';

  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_settings_page'));
    add_action('admin_init', array($this, 'register_settings'));
  }

  public function add_settings_page()
  {
    add_options_page(
      'Qobolak ChatBot Settings',
      'Qobolak ChatBot',
      'manage_options',
      'qobolak-settings',
      array($this, 'render_settings_page')
    );
  }

  public function register_settings()
  {
    register_setting(
      'qobolak_settings_group',
      $this->option_name,
      array($this, 'sanitize_settings')
    );
  }

  public function sanitize_settings($input)
  {
    return array(
      'api_key' => sanitize_text_field($input['api_key']),
      'max_tokens' => min((int) $input['max_tokens'], 150),
      'temperature' => min(max((float) $input['temperature'], 0), 1),
      'rate_limit' => min((int) $input['rate_limit'], 60),
      'cache_duration' => min((int) $input['cache_duration'], 86400),
      'training_mode' => isset($input['training_mode']) ? (bool) $input['training_mode'] : false,
      'last_scraped_at' => sanitize_text_field($input['last_scraped_at'] ?? '')
    );
  }

  public function render_settings_page()
  {
    $options = get_option($this->option_name, array(
      'api_key' => '',
      'max_tokens' => 100,
      'temperature' => 0.7,
      'rate_limit' => 30,
      'cache_duration' => 3600,
      'training_mode' => false,
      'last_scraped_at' => ''
    ));
    ?>
    <div class="wrap">
      <h1>Qobolak ChatBot Settings</h1>
      <form method="post" action="options.php">
        <?php settings_fields('qobolak_settings_group'); ?>
        <table class="form-table">
          <!-- Existing settings -->
          <tr>
            <th scope="row">OpenAI API Key</th>
            <td>
              <input type="password" name="<?php echo esc_attr($this->option_name); ?>[api_key]"
                value="<?php echo esc_attr($options['api_key']); ?>" class="regular-text" />
            </td>
          </tr>
          <tr>
            <th scope="row">Max Tokens per Response</th>
            <td>
              <input type="number" name="<?php echo esc_attr($this->option_name); ?>[max_tokens]"
                value="<?php echo esc_attr($options['max_tokens']); ?>" min="1" max="150" />
              <p class="description">Lower values reduce costs. Max 150 recommended.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Temperature</th>
            <td>
              <input type="number" step="0.1" name="<?php echo esc_attr($this->option_name); ?>[temperature]"
                value="<?php echo esc_attr($options['temperature']); ?>" min="0" max="1" />
              <p class="description">Lower values (0-0.3) give more focused, consistent responses.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Rate Limit (seconds)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($this->option_name); ?>[rate_limit]"
                value="<?php echo esc_attr($options['rate_limit']); ?>" min="10" max="60" />
              <p class="description">Minimum time between requests per user.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Cache Duration (seconds)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($this->option_name); ?>[cache_duration]"
                value="<?php echo esc_attr($options['cache_duration']); ?>" min="300" max="86400" />
              <p class="description">How long to cache similar responses.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Training Mode</th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[training_mode]" value="1" <?php checked($options['training_mode'], true); ?> />
                Enable Training Mode
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row">Last Scraped At</th>
            <td>
              <input type="text" name="<?php echo esc_attr($this->option_name); ?>[last_scraped_at]"
                value="<?php echo esc_attr($options['last_scraped_at']); ?>" class="regular-text" readonly />
              <p class="description">Timestamp of the last external knowledge scraping.</p>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  public static function get_settings()
  {
    $defaults = array(
      'api_key' => '',
      'max_tokens' => 100,
      'temperature' => 0.7,
      'rate_limit' => 30,
      'cache_duration' => 3600,
      'training_mode' => false,
      'last_scraped_at' => ''
    );

    $settings = get_option('qobolak_openai_settings', $defaults);

    return wp_parse_args($settings, $defaults);
  }

  // New function to update the last_scraped_at timestamp
  public function update_last_scraped_at()
  {
    $settings = Qobolak_Admin_Settings::get_settings();
    $settings['last_scraped_at'] = current_time('mysql');
    update_option('qobolak_openai_settings', $settings);
  }
}

new Qobolak_Admin_Settings();

?>