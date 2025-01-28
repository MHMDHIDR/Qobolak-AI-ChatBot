<?php

if (!defined('ABSPATH')) {
  exit;
}

class Qobolak_Admin_Settings
{
  private $option_name = 'qobolak_openai_settings';

  public function __construct()
  {
    $this->option_name = 'qobolak_openai_settings';
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
    // Register OpenAI settings
    register_setting('qobolak_settings_group', $this->option_name, array($this, 'sanitize_settings'));

    // Register Cal.com API key
    register_setting('qobolak_settings_group', 'qobolak_calcom_api_key', array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field'
    ));

    // Register suggested questions with default values
    register_setting('qobolak_settings_group', 'qobolak_suggested_questions', array(
      'type' => 'array',
      'default' => array(
        'What are your business hours?',
        'How can I contact support?',
        'Do you offer online consultations?'
      ),
      'sanitize_callback' => array($this, 'sanitize_suggested_questions')
    ));

    add_settings_section(
      'qobolak_suggested_questions_section',
      'Suggested Questions',
      array($this, 'render_suggested_questions_section'),
      'qobolak-settings'
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

  public function sanitize_suggested_questions($questions)
  {
    if (!is_array($questions)) {
      return array();
    }
    return array_filter(array_map('sanitize_text_field', $questions));
  }

  public function render_api_section()
  {
    echo '<p>Configure your API keys for various integrations:</p>';
  }

  public function render_chatbot_section()
  {
    echo '<p>Configure your chatbot behavior settings:</p>';
  }

  public function render_suggested_questions_section()
  {
    echo '<p>Add suggested questions that will appear in the chatbot. Users can click these to quickly ask common questions.</p>';
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

    $calcom_api_key = get_option('qobolak_calcom_api_key', '');
    $suggested_questions = get_option('qobolak_suggested_questions', array());
    ?>
    <div class="wrap">
      <div class="p-6 max-w-4xl bg-white rounded-lg shadow-sm">
        <h1 class="mb-6 text-2xl font-bold text-gray-800">Qobolak ChatBot Configuration</h1>

        <form method="post" action="options.php">
          <?php
          settings_fields('qobolak_settings_group');
          do_settings_sections('qobolak-settings');
          ?>

          <!-- API Configuration Section -->
          <div class="p-6 mb-8 bg-blue-50 rounded-lg">
            <h2 class="mb-4 text-xl font-semibold text-blue-800">API Configuration</h2>
            <p class="mb-4 text-blue-600">Configure your API keys for various integrations</p>

            <table class="form-table">
              <tr>
                <th scope="row">OpenAI API Key</th>
                <td>
                  <div class="password-input-wrapper" style="position: relative;">
                    <input type="password" name="<?php echo esc_attr($this->option_name); ?>[api_key]"
                      value="<?php echo esc_attr($options['api_key']); ?>" class="regular-text" />
                    <button type="button" class="toggle-password" style="position: absolute; right: -2rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                      <span class="dashicons dashicons-visibility"></span>
                    </button>
                  </div>
                  <p class="description">Your OpenAI API key for ChatGPT functionality</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Cal.com API Key</th>
                <td>
                  <div class="password-input-wrapper" style="position: relative;">
                    <input type="password" name="qobolak_calcom_api_key" value="<?php echo esc_attr($calcom_api_key); ?>"
                      class="regular-text" />
                    <button type="button" class="toggle-password" style="position: absolute; right: -2rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                      <span class="dashicons dashicons-visibility"></span>
                    </button>
                  </div>
                  <p class="description">Required for appointment scheduling functionality</p>
                </td>
              </tr>
            </table>
          </div>

          <!-- ChatBot Behavior Section -->
          <div class="p-6 mb-8 bg-green-50 rounded-lg">
            <h2 class="mb-4 text-xl font-semibold text-green-800">ChatBot Behavior</h2>
            <p class="mb-4 text-green-600">Configure how your chatbot interacts with users</p>

            <table class="form-table">
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
            </table>
          </div>

          <!-- Training Mode Section -->
          <div class="p-6 mb-8 bg-purple-50 rounded-lg">
            <h2 class="mb-4 text-xl font-semibold text-purple-800">Training Mode</h2>
            <p class="mb-4 text-purple-600">Configure how your chatbot learns from interactions</p>

            <table class="form-table">
              <tr>
                <th scope="row">Training Mode</th>
                <td>
                  <label class="flex gap-2 items-center">
                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[training_mode]" value="1"
                      <?php checked($options['training_mode'], true); ?> />
                    <span>Enable Training Mode</span>
                  </label>
                  <p class="description">When enabled, the chatbot will ask users to teach it answers it doesn't know.</p>
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
          </div>

          <!-- Suggested Questions Section -->
          <div class="p-6 mb-8 bg-amber-50 rounded-lg">
            <h2 class="mb-4 text-xl font-semibold text-amber-800">Suggested Questions</h2>
            <p class="mb-4 text-amber-600">Configure quick questions that users can select in the chat</p>

            <div id="suggested-questions-container" class="mb-4">
              <?php if (!empty($suggested_questions)): ?>
                <?php foreach ($suggested_questions as $index => $question): ?>
                  <div class="question-row">
                    <input type="text" name="qobolak_suggested_questions[]" value="<?php echo esc_attr($question); ?>"
                      class="regular-text" />
                    <button type="button" class="button button-secondary remove-question">Remove</button>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
              <div class="question-row">
                <input type="text" name="qobolak_suggested_questions[]" value="" class="regular-text" />
                <button type="button" class="button button-secondary remove-question">Remove</button>
              </div>
            </div>
            <button type="button" class="button button-secondary" id="add-question">Add Question</button>
          </div>

          <style>
            .question-row {
              margin-bottom: 10px;
              display: flex;
              align-items: center;
              gap: 10px;
            }
            #suggested-questions-container {
              margin-bottom: 15px;
            }
            /* WordPress Admin Compatible Tailwind-like Classes */
            .bg-blue-50 { background-color: #eff6ff; }
            .bg-green-50 { background-color: #f0fdf4; }
            .bg-purple-50 { background-color: #faf5ff; }
            .bg-amber-50 { background-color: #fffbeb; }
            .text-blue-800 { color: #1e40af; }
            .text-green-800 { color: #166534; }
            .text-purple-800 { color: #6b21a8; }
            .text-amber-800 { color: #92400e; }
            .text-blue-600 { color: #2563eb; }
            .text-green-600 { color: #16a34a; }
            .text-purple-600 { color: #9333ea; }
            .text-amber-600 { color: #d97706; }
            .rounded-lg { border-radius: 0.5rem; }
            .p-6 { padding: 1.5rem; }
            .mb-4 { margin-bottom: 1rem; }
            .mb-6 { margin-bottom: 1.5rem; }
            .mb-8 { margin-bottom: 2rem; }
            .text-2xl { font-size: 1.5rem; line-height: 2rem; }
            .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
            .font-bold { font-weight: 700; }
            .font-semibold { font-weight: 600; }
            .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
          </style>

          <script>
            jQuery(document).ready(function ($) {
              $('#add-question').on('click', function () {
                var row = $('<div class="question-row">' +
                  '<input type="text" name="qobolak_suggested_questions[]" value="" class="regular-text" />' +
                  '<button type="button" class="button button-secondary remove-question">Remove</button>' +
                  '</div>');
                $('#suggested-questions-container').append(row);
              });

              $('#suggested-questions-container').on('click', '.remove-question', function () {
                if ($('.question-row').length > 1) {
                  $(this).closest('.question-row').remove();
                } else {
                  $(this).prev('input').val('');
                }
              });
            });
          </script>

          <script>
            document.addEventListener('DOMContentLoaded', function() {
              const toggleButtons = document.querySelectorAll('.toggle-password');

              toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                  const input = this.parentElement.querySelector('input');
                  const icon = this.querySelector('.dashicons');

                  if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('dashicons-visibility');
                    icon.classList.add('dashicons-hidden');
                  } else {
                    input.type = 'password';
                    icon.classList.remove('dashicons-hidden');
                    icon.classList.add('dashicons-visibility');
                  }
                });
              });
            });
          </script>

          <?php submit_button('Save Settings', 'primary', 'submit', false, array('class' => 'mt-4')); ?>
        </form>
      </div>
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

  public function update_last_scraped_at()
  {
    $settings = self::get_settings();
    $settings['last_scraped_at'] = current_time('mysql');
    update_option('qobolak_openai_settings', $settings);
  }
}

new Qobolak_Admin_Settings();

?>