<?php
if (!defined('ABSPATH')) {
  exit;
}

require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'class-qobolak-web-knowledge.php';

add_action('wp_ajax_qobolak_chat', 'qobolak_chat_handler');
add_action('wp_ajax_nopriv_qobolak_chat', 'qobolak_chat_handler');

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

  if (empty($message)) {
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
    // If we received a training answer
    if ($is_training && !empty($previous_question) && !empty($training_answer)) {
      try {
        $knowledge->store_training_data($previous_question, $training_answer);
        wp_send_json_success([
          'response' => "Thank you for teaching me! I've learned this answer and will use it to help others.",
          'is_training' => false
        ]);
        return;
      } catch (Exception $e) {
        error_log('Qobolak AI ChatBot Training Error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Failed to save training data.']);
        return;
      }
    }

    // Ask for training input
    wp_send_json_success([
      'response' => "I'm in training mode and learning! Could you please teach me the correct answer to this question?",
      'is_training' => true,
      'previous_question' => $message
    ]);
    return;
  }

  // Normal mode: First check training data
  $training_response = $knowledge->get_training_response($message);
  if ($training_response) {
    wp_send_json_success(['response' => $training_response]);
    return;
  }

  // Then check external knowledge
  $relevant_content = $knowledge->find_relevant_content($message);
  if (empty($relevant_content)) {
    wp_send_json_success([
      'response' => "I don't have specific information about that in my knowledge base. Would you like to switch to training mode to teach me?",
      'suggest_training' => true
    ]);
    return;
  }

  // Call OpenAI API
  try {
    $response = call_openai_api($message, $relevant_content, $settings);
    if ($response) {
      wp_send_json_success(['response' => $response]);
    } else {
      wp_send_json_error(['message' => 'Failed to generate a response.']);
    }
  } catch (Exception $e) {
    error_log('Qobolak AI ChatBot Error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'An error occurred while processing your request.']);
  }
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

function call_openai_api($message, $context, $settings)
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
        'content' => 'You are a helpful assistant for Qobolak, a company that provides study abroad and educational services. ' .
          'Answer based only on the provided context. If unsure, suggest contacting Qobolak directly for accurate information. Keep responses concise and professional.'
      ],
      [
        'role' => 'user',
        'content' => "Using this context:\n\n" . $context . "\n\nPlease answer this question: " . $message
      ]
    ],
    'max_tokens' => (int) ($settings['max_tokens'] ?? 150),
    'temperature' => (float) ($settings['temperature'] ?? 0.7),
    'top_p' => 1,
    'frequency_penalty' => 0,
    'presence_penalty' => 0
  ];

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    throw new Exception('cURL error: ' . $curl_error);
  }

  if ($http_code !== 200) {
    throw new Exception('API request failed with HTTP code: ' . $http_code);
  }

  $response_data = json_decode($response, true);
  if (!isset($response_data['choices'][0]['message']['content'])) {
    throw new Exception('Invalid API response format.');
  }

  return $response_data['choices'][0]['message']['content'];
}
