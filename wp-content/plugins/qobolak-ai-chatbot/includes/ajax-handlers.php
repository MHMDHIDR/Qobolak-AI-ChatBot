<?php
if (!defined('ABSPATH')) {
  exit;
}

require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'class-qobolak-web-knowledge.php';

add_action('wp_ajax_qobolak_chat', 'qobolak_chat_handler');
add_action('wp_ajax_nopriv_qobolak_chat', 'qobolak_chat_handler');

// Add AJAX handler for suggested questions
add_action('wp_ajax_qobolak_get_suggested_questions', 'qobolak_get_suggested_questions_handler');
add_action('wp_ajax_nopriv_qobolak_get_suggested_questions', 'qobolak_get_suggested_questions_handler');

// Add this new action at the top with other add_action calls
add_action('wp_ajax_qobolak_get_calendar_settings', 'qobolak_get_calendar_settings_handler');
add_action('wp_ajax_nopriv_qobolak_get_calendar_settings', 'qobolak_get_calendar_settings_handler');

function qobolak_chat_handler()
{
  // Security check
  if (!check_ajax_referer('qobolak_nonce', 'security', false)) {
    wp_send_json_error(['message' => 'Invalid nonce.']);
    exit;
  }

  // Input validation
  $message = sanitize_text_field($_POST['message'] ?? '');
  $is_training = filter_var($_POST['is_training'] ?? false, FILTER_VALIDATE_BOOLEAN);
  $previous_question = sanitize_text_field($_POST['previous_question'] ?? '');
  $training_answer = sanitize_text_field($_POST['training_answer'] ?? '');
  $chat_history = isset($_POST['chatHistory']) ? json_decode(stripslashes($_POST['chatHistory']), true) : [];

  // Only require message if not submitting a training answer
  if (empty($message) && !$is_training) {
    wp_send_json_error(['message' => 'Message cannot be empty.']);
    return;
  }

  // Retrieve settings
  $settings = Qobolak_Admin_Settings::get_settings();
  if (empty($settings['api_key'])) {
    wp_send_json_error(['message' => 'API settings are not configured.']);
    return;
  }

  // Initialize knowledge class
  $knowledge = new Qobolak_Web_Knowledge();

  // Handle training mode
  if ($settings['training_mode']) {
    // ... existing training mode code ...
    return;
  }

  // Get conversation context from chat history
  $recent_context = array_slice($chat_history, -3);
  $conversation_context = '';
  foreach ($recent_context as $msg) {
    $role = $msg['sender'] === 'user' ? 'User' : 'Assistant';
    $conversation_context .= "{$role}: {$msg['text']}\n";
  }

  // Normal mode: First check training data with context
  $training_response = $knowledge->get_training_response($message, $conversation_context);
  if ($training_response) {
    wp_send_json_success(['response' => $training_response]);
    return;
  }

  // Then try semantic search with OpenAI
  $relevant_content = $knowledge->find_relevant_content($message, $conversation_context);
  if ($relevant_content) {
    wp_send_json_success(['response' => $relevant_content]);
    return;
  }

  // Rest of the existing code...
}

function qobolak_get_suggested_questions_handler()
{
  // Security check
  if (!check_ajax_referer('qobolak_nonce', 'security', false)) {
    wp_send_json_error(['message' => 'Invalid nonce.']);
    exit;
  }

  // Get suggested questions from options
  $questions = get_option('qobolak_suggested_questions', []);

  wp_send_json_success(['questions' => $questions]);
  exit;
}

function qobolak_handle_chatbot_request($user_input)
{
  global $wpdb;
  $settings = Qobolak_Admin_Settings::get_settings();
  $is_training_mode = $settings['training_mode'];

  if ($is_training_mode) {
    // Save question and provided answer into the training data table
    $answer = 'What should I respond to this?'; // Prompt user for training
    $wpdb->insert(
      'qo_qobolak_training_data',
      array(
        'question' => $user_input,
        'answer' => $answer,
        'created_at' => current_time('mysql')
      ),
      array('%s', '%s', '%s')
    );
    return $answer;
  } else {
    // Answer mode: Look for answers in training data first
    $answer = $wpdb->get_var($wpdb->prepare(
      "SELECT answer FROM qo_qobolak_training_data WHERE question = %s LIMIT 1",
      $user_input
    ));

    if (!$answer) {
      $answer = $wpdb->get_var("SELECT answer FROM qo_qobolak_external_knowledge WHERE question = %s LIMIT 1");
    }

    if (!$answer) {
      $answer = "I couldn't find relevant answers, please train me.";
    }

    return $answer;
  }
}

function call_openai_api_with_context($message, $conversation_context, $knowledge_context, $system_prompt, $settings)
{
  $api_key = $settings['api_key'];
  $headers = [
    "Authorization: Bearer {$api_key}",
    'Content-Type: application/json'
  ];

  $data = [
    'model' => 'gpt-3.5-turbo',
    'messages' => [
      [
        'role' => 'system',
        'content' => $system_prompt
      ],
      [
        'role' => 'user',
        'content' => "Previous conversation:\n{$conversation_context}\n\nAvailable knowledge:\n{$knowledge_context}\n\nBased on this context and knowledge, provide a direct and concise answer to the latest question."
      ]
    ],
    'max_tokens' => (int) ($settings['max_tokens'] ?? 150),
    'temperature' => 0.3, // Lower temperature for more focused answers
  ];

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http_code !== 200) {
    throw new Exception('API request failed with HTTP code: ' . $http_code);
  }

  $response_data = json_decode($response, true);
  return $response_data['choices'][0]['message']['content'] ?? null;
}

function qobolak_get_calendar_settings_handler()
{
  check_ajax_referer('qobolak_nonce', 'security');

  $calendar_settings = get_option('qobolak_calendar_settings', array());
  wp_send_json_success(array('settings' => $calendar_settings));
}
