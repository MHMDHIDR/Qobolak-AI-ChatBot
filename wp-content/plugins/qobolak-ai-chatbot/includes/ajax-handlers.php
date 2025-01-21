<?php
if (!defined('ABSPATH')) {
  exit;
}

require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'class-qobolak-web-knowledge.php';

add_action('wp_ajax_qobolak_chat', 'handle_chat_request');
add_action('wp_ajax_nopriv_qobolak_chat', 'handle_chat_request');

function handle_chat_request()
{
  check_ajax_referer('qobolak_nonce', 'security');

  $message = sanitize_text_field($_POST['message']);
  if (empty($message)) {
    wp_send_json_error(['message' => 'Empty message']);
    return;
  }

  $settings = Qobolak_Admin_Settings::get_settings();
  if (empty($settings['api_key'])) {
    wp_send_json_error(['message' => 'API settings not configured']);
    return;
  }

  $knowledge = new Qobolak_Web_Knowledge();

  // Get relevant content from scraped website data
  $relevant_content = $knowledge->find_relevant_content($message);

  if (empty($relevant_content)) {
    wp_send_json_success([
      'response' => "I apologize, but I don't have specific information about that. Please contact Qobolak directly at their website for the most accurate information."
    ]);
    return;
  }

  // Build context from relevant content
  $context = "Information about Qobolak:\n\n";
  foreach ($relevant_content as $content) {
    $context .= "From {$content->section} section:\n{$content->content}\n\n";
  }

  try {
    $response = call_openai_api($message, $context, $settings);
    if ($response) {
      wp_send_json_success(['response' => $response]);
    } else {
      wp_send_json_error(['message' => 'Failed to get response']);
    }
  } catch (Exception $e) {
    wp_send_json_error(['message' => $e->getMessage()]);
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
          'Answer based only on the provided context. If you are not sure about something, ' .
          'kindly suggest contacting Qobolak directly for the most accurate information. ' .
          'Keep responses concise and professional.'
      ],
      [
        'role' => 'user',
        'content' => "Using this context:\n\n" . $context . "\n\nPlease answer this question: " . $message
      ]
    ],
    'max_tokens' => isset($settings['max_tokens']) ? (int) $settings['max_tokens'] : 150,
    'temperature' => isset($settings['temperature']) ? (float) $settings['temperature'] : 0.7,
    'top_p' => 1,
    'frequency_penalty' => 0,
    'presence_penalty' => 0
  ];

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http_code !== 200) {
    throw new Exception('API request failed');
  }

  $response_data = json_decode($response, true);
  return $response_data['choices'][0]['message']['content'] ?? '';
}