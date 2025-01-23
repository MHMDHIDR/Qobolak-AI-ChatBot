<?php
class Qobolak_Web_Knowledge
{
  private $db;
  private $base_url = 'https://www.qobolak.com';
  private $urls_to_scrape = [
    'https://www.qobolak.com',
    'https://www.qobolak.com/ar/home',
    'https://www.qobolak.com/about-us',
    'https://www.qobolak.com/ar/about-us-ar',
    'https://www.qobolak.com/contact-us',
    'https://www.qobolak.com/contact-us-ar',
    'https://www.qobolak.com/services',
    'https://www.qobolak.com/services-ar',
    'https://www.qobolak.com/student-services',
    'https://www.qobolak.com/ar/student-services-ar',
    'https://www.qobolak.com/scholarship-and-training-abroad-management-services',
    'https://www.qobolak.com/ar/scholarship-and-training-abroad-management-services-ar',
    'https://www.qobolak.com/university-and-education-institution-services',
    'https://www.qobolak.com/ar/university-and-education-institution-services-ar',
    'https://www.qobolak.com/employment-services',
    'https://www.qobolak.com/ar/employment-services-ar',
  ];

  private $table_name;
  private $training_table;

  public function __construct()
  {
    global $wpdb;
    $this->db = $wpdb;
    $this->table_name = $wpdb->prefix . 'qobolak_external_knowledge';
    $this->training_table = $wpdb->prefix . 'qobolak_training_data';
    $this->init_tables();
  }

  private function init_tables()
  {
    $charset_collate = $this->db->get_charset_collate();

    $main_table_sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      url varchar(255) NOT NULL,
      title text NOT NULL,
      content longtext NOT NULL,
      section varchar(50) NOT NULL,
      last_scraped datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      UNIQUE KEY url (url)
  ) $charset_collate;";

    // Training data table
    $training_table_sql = "CREATE TABLE IF NOT EXISTS $this->training_table (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      question text NOT NULL,
      answer longtext NOT NULL,
      created_at datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id)
  ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($main_table_sql);
    dbDelta($training_table_sql);
  }

  public function store_training_data($question, $answer)
  {
    if (empty($question) || empty($answer)) {
      return false;
    }

    // Normalize line endings and preserve them
    $answer = str_replace(["\r\n", "\r"], "\n", $answer);

    return $this->db->insert(
      $this->training_table,
      [
        'question' => $question,
        'answer' => $answer,
        'created_at' => current_time('mysql')
      ],
      ['%s', '%s', '%s']
    );
  }

  private function get_openai_embedding($text, $api_key)
  {
    $url = 'https://api.openai.com/v1/embeddings';
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $api_key
    ];

    $data = [
      'input' => $text,
      'model' => 'text-embedding-ada-002'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
      error_log('OpenAI Embedding Error: ' . $err);
      return null;
    }

    $result = json_decode($response, true);
    return $result['data'][0]['embedding'] ?? null;
  }

  private function ask_openai($question, $context, $api_key)
  {
    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $api_key
    ];

    $messages = [
      [
        'role' => 'system',
        'content' => "You are a helpful assistant for Qobolak. Your task is to find the most relevant answer from the provided context. If the context contains a relevant answer, provide it. If not, indicate that you cannot find a specific answer. Context:\n\n" . $context
      ],
      [
        'role' => 'user',
        'content' => $question
      ]
    ];

    $data = [
      'model' => 'gpt-3.5-turbo',
      'messages' => $messages,
      'temperature' => 0.3,
      'max_tokens' => 500
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
      error_log('OpenAI Chat Error: ' . $err);
      return null;
    }

    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? null;
  }

  public function get_training_response($question)
  {
    $settings = get_option('qobolak_openai_settings');
    if (empty($settings['api_key'])) {
      error_log('OpenAI API key not configured');
      return null;
    }

    // Get all training data
    $all_qa_pairs = $this->db->get_results(
      "SELECT question, answer FROM $this->training_table",
      ARRAY_A
    );

    if (empty($all_qa_pairs)) {
      return null;
    }

    // Format context for OpenAI
    $context = "Here are some question-answer pairs that might be relevant:\n\n";
    foreach ($all_qa_pairs as $pair) {
      $context .= "Q: {$pair['question']}\nA: {$pair['answer']}\n\n";
    }

    // Get OpenAI's response
    $answer = $this->ask_openai($question, $context, $settings['api_key']);

    if ($answer && !preg_match('/cannot|don\'t have|no specific|not find/i', $answer)) {
      return $answer;
    }

    // If OpenAI couldn't find a good match, try keyword-based search as fallback
    $keywords = $this->get_keywords($question);
    if (!empty($keywords)) {
      $conditions = [];
      $values = [];
      foreach ($keywords as $keyword) {
        if (strlen($keyword) >= 3) {
          $conditions[] = "LOWER(question) LIKE %s OR LOWER(answer) LIKE %s";
          $values[] = '%' . $this->db->esc_like($keyword) . '%';
          $values[] = '%' . $this->db->esc_like($keyword) . '%';
        }
      }

      if (!empty($conditions)) {
        $sql = $this->db->prepare(
          "SELECT answer FROM $this->training_table WHERE " . implode(' OR ', $conditions) . " LIMIT 1",
          $values
        );
        $fallback_answer = $this->db->get_var($sql);
        if ($fallback_answer) {
          return str_replace(["\r\n", "\r"], "\n", $fallback_answer);
        }
      }
    }

    return null;
  }

  private function normalize_text($text)
  {
    // Convert to lowercase
    $text = strtolower($text);

    // Remove extra spaces
    $text = preg_replace('/\s+/', ' ', trim($text));

    // Remove common punctuation
    $text = str_replace(['?', '.', ',', '!', ';', ':', '"', "'"], '', $text);

    // Remove common words that don't add meaning
    $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
    $words = explode(' ', $text);
    $words = array_diff($words, $stopwords);

    return implode(' ', $words);
  }

  private function get_keywords($text)
  {
    $normalized = $this->normalize_text($text);
    $words = explode(' ', $normalized);

    // Keep words that are 3 or more characters
    return array_filter($words, function ($word) {
      return mb_strlen($word) >= 3;
    });
  }

  public function scrape_website()
  {
    if (!class_exists('DOMDocument')) {
      error_log('Qobolak AI ChatBot Error: DOMDocument class is required for web scraping');
      return false;
    }

    // Clear old data before new scrape
    $this->db->query("TRUNCATE TABLE $this->table_name");

    $success_count = 0;
    $error_count = 0;

    foreach ($this->urls_to_scrape as $url) {
      try {
        if ($this->scrape_page($url)) {
          $success_count++;
        } else {
          $error_count++;
        }
        // Be nice to the server
        sleep(2);
      } catch (Exception $e) {
        error_log("Qobolak AI ChatBot Error scraping $url: " . $e->getMessage());
        $error_count++;
      }
    }

    error_log("Qobolak AI ChatBot Scraping Complete - Success: $success_count, Errors: $error_count");
    return $success_count > 0;
  }

  private function scrape_page($url)
  {
    $response = wp_remote_get($url, [
      'timeout' => 30,
      'user-agent' => 'QobolakBot/1.0 (+https://www.qobolak.com)'
    ]);

    if (is_wp_error($response)) {
      error_log('Qobolak AI ChatBot Error: ' . $response->get_error_message());
      return false;
    }

    $html = wp_remote_retrieve_body($response);
    if (empty($html)) {
      return false;
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Extract content sections
    $sections = [
      'title' => $this->extract_text($xpath, '//h1'),
      'description' => $this->extract_text($xpath, '//meta[@name="description"]/@content'),
      'main_content' => $this->extract_text($xpath, '//main//p | //article//p | //div[contains(@class, "content")]//p'),
      'headings' => $this->extract_text($xpath, '//h2 | //h3 | //h4'),
    ];

    // Store in database
    return $this->db->insert(
      $this->table_name,
      [
        'url' => $url,
        'title' => $sections['title'],
        'content' => json_encode($sections),
        'section' => $this->determine_section($url),
        'last_scraped' => current_time('mysql')
      ],
      ['%s', '%s', '%s', '%s', '%s']
    );
  }

  public function find_relevant_content($query)
  {
    $query = $this->db->esc_like($query);

    // First try exact matches
    $results = $this->db->get_results($this->db->prepare(
      "SELECT * FROM $this->table_name
       WHERE MATCH(title, content) AGAINST(%s IN BOOLEAN MODE)
       OR title LIKE %s
       OR content LIKE %s
       LIMIT 5",
      $query,
      '%' . $query . '%',
      '%' . $query . '%'
    ));

    if (empty($results)) {
      // Try fuzzy matching if no exact matches
      $words = explode(' ', $query);
      $like_conditions = [];
      foreach ($words as $word) {
        if (strlen($word) > 3) {
          $like_conditions[] = $this->db->prepare(
            "title LIKE %s OR content LIKE %s",
            '%' . $word . '%',
            '%' . $word . '%'
          );
        }
      }

      if (!empty($like_conditions)) {
        $results = $this->db->get_results(
          "SELECT * FROM $this->table_name
           WHERE " . implode(' OR ', $like_conditions) . "
           LIMIT 5"
        );
      }
    }

    return $results;
  }

  private function extract_text($xpath, $query)
  {
    $nodes = $xpath->query($query);
    if (!$nodes || $nodes->length === 0) {
      return '';
    }

    $text = '';
    foreach ($nodes as $node) {
      $text .= ' ' . trim($node->textContent);
    }
    return preg_replace('/\s+/', ' ', $text);
  }

  private function determine_section($url)
  {
    if (strpos($url, 'about-us') !== false)
      return 'about';
    if (strpos($url, 'services') !== false)
      return 'services';
    if (strpos($url, 'contact') !== false)
      return 'contact';
    if (strpos($url, 'university') !== false)
      return 'education';
    if (strpos($url, 'employment') !== false)
      return 'employment';
    return 'general';
  }

  public function guess_section($query)
  {
    $query = strtolower($query);
    if (strpos($query, 'study') !== false || strpos($query, 'university') !== false)
      return 'education';
    if (strpos($query, 'job') !== false || strpos($query, 'work') !== false)
      return 'employment';
    if (strpos($query, 'contact') !== false || strpos($query, 'location') !== false)
      return 'contact';
    if (strpos($query, 'about') !== false || strpos($query, 'company') !== false)
      return 'about';
    if (strpos($query, 'service') !== false)
      return 'services';
    return null;
  }
}