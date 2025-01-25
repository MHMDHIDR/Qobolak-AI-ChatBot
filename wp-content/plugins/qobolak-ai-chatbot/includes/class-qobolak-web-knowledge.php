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

    // Insert new question-answer pair
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

  public function get_training_response($question)
  {
    if (empty($question)) {
      return null;
    }

    // 1. Try exact match first (fastest)
    $exact_match = $this->db->get_var(
      $this->db->prepare(
        "SELECT answer FROM $this->training_table WHERE LOWER(question) = LOWER(%s) LIMIT 1",
        $question
      )
    );

    if ($exact_match) {
      return $this->format_response($exact_match);
    }

    // 2. Try fuzzy match using LIKE and MATCH (still fast)
    $normalized_question = $this->normalize_text($question);
    $keywords = preg_split('/\s+/', $normalized_question);
    $like_conditions = [];
    $values = [];

    foreach ($keywords as $keyword) {
      if (mb_strlen($keyword) >= 3) {
        $like_conditions[] = "LOWER(question) LIKE %s";
        $values[] = '%' . mb_strtolower($keyword) . '%';
      }
    }

    if (!empty($like_conditions)) {
      $sql = "SELECT answer, question, (
        CASE
          WHEN LOWER(question) = LOWER(%s) THEN 100
          WHEN LOWER(question) LIKE %s THEN 80
          ELSE (
            " . count($like_conditions) . " - (
              SELECT COUNT(*) FROM (
                SELECT UNNEST(REGEXP_SPLIT_TO_ARRAY(LOWER(question), E'\\\\s+')) AS word
              ) AS words
              WHERE word NOT IN ('" . implode("','", array_map('mb_strtolower', $keywords)) . "')
            )
          ) * 10
        END
      ) as relevance
      FROM $this->training_table
      WHERE " . implode(" OR ", $like_conditions) . "
      ORDER BY relevance DESC
      LIMIT 1";

      array_unshift($values, '%' . mb_strtolower($normalized_question) . '%');
      array_unshift($values, $normalized_question);

      $fuzzy_match = $this->db->get_row($this->db->prepare($sql, $values));

      if ($fuzzy_match && $fuzzy_match->relevance >= 60) {
        return $this->format_response($fuzzy_match->answer);
      }
    }

    // 3. Only use OpenAI embeddings as last resort for complex semantic matching
    $settings = get_option('qobolak_openai_settings');
    if (empty($settings['api_key'])) {
      return null;
    }

    // Cache the embeddings to avoid recalculating
    $cache_key = 'qobolak_embedding_' . md5($question);
    $question_embedding = get_transient($cache_key);

    if ($question_embedding === false) {
      $question_embedding = $this->get_openai_embedding($question, $settings['api_key']);
      if ($question_embedding) {
        set_transient($cache_key, $question_embedding, HOUR_IN_SECONDS);
      }
    }

    if (!$question_embedding) {
      return null;
    }

    // Get potential matches from previous fuzzy search
    $potential_matches = $this->db->get_results(
      $this->db->prepare(
        "SELECT question, answer FROM $this->training_table WHERE " . implode(" OR ", $like_conditions),
        $values
      ),
      ARRAY_A
    );

    if (empty($potential_matches)) {
      return null;
    }

    $best_match = null;
    $highest_similarity = 0;

    foreach ($potential_matches as $match) {
      $cache_key = 'qobolak_embedding_' . md5($match['question']);
      $stored_embedding = get_transient($cache_key);

      if ($stored_embedding === false) {
        $stored_embedding = $this->get_openai_embedding($match['question'], $settings['api_key']);
        if ($stored_embedding) {
          set_transient($cache_key, $stored_embedding, HOUR_IN_SECONDS);
        }
      }

      if (!$stored_embedding) {
        continue;
      }

      $similarity = $this->calculate_cosine_similarity($question_embedding, $stored_embedding);
      if ($similarity > $highest_similarity) {
        $highest_similarity = $similarity;
        $best_match = $match;
      }
    }

    if ($best_match && $highest_similarity > 0.8) {
      return $this->format_response($best_match['answer']);
    }

    return null;
  }

  private function format_response($text)
  {
    // Replace multiple spaces with single space
    $text = preg_replace('/\s+/', ' ', $text);

    // Add line breaks after colons that are followed by lists
    $text = preg_replace('/:\s*-\s*/', ":\n- ", $text);

    // Add line breaks before new sections (identified by capital letters followed by colon)
    $text = preg_replace('/([A-Z][^:]+):/', "\n$1:", $text);

    // Add line breaks before list items
    $text = preg_replace('/\s+-\s*/', "\n- ", $text);

    // Clean up multiple consecutive line breaks
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    // Trim extra whitespace
    $text = trim($text);

    return $text;
  }

  private function normalize_text($text)
  {
    // Remove extra spaces and normalize Arabic/Persian characters
    $text = preg_replace('/\s+/', ' ', trim($text));
    $text = str_replace(['ي', 'ئ'], 'ی', $text);
    $text = str_replace('ة', 'ه', $text);
    $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
    return $text;
  }

  public function get_training_response_openai($question, $exact_match = false)
  {
    $settings = get_option('qobolak_openai_settings');
    if (empty($settings['api_key'])) {
      error_log('OpenAI API key not configured');
      return null;
    }

    // Normalize input question
    $question = $this->normalize_text($question);

    if ($exact_match) {
      // Try exact match first
      $exact_match = $this->db->get_row(
        $this->db->prepare(
          "SELECT answer FROM $this->training_table WHERE LOWER(question) = %s",
          strtolower($question)
        )
      );

      if ($exact_match) {
        return $this->format_response($exact_match->answer);
      }
    }

    // Get all training data
    $all_qa_pairs = $this->db->get_results(
      "SELECT question, answer FROM $this->training_table ORDER BY created_at DESC",
      ARRAY_A
    );

    if (empty($all_qa_pairs)) {
      return null;
    }

    // Format context for OpenAI
    $context = "Here are some question-answer pairs that might be relevant. Use these to find the most appropriate answer for the user's question:\n\n";
    foreach ($all_qa_pairs as $pair) {
      $context .= "Q: {$pair['question']}\nA: {$pair['answer']}\n\n";
    }

    // Get OpenAI's response
    $answer = $this->ask_openai($question, $context, $settings['api_key']);

    if ($answer && !preg_match('/cannot|don\'t have|no specific|not find/i', $answer)) {
      return $this->format_response($answer);
    }

    // If OpenAI couldn't find a good match, try keyword-based search as fallback
    $keywords = $this->get_keywords($question);
    if (!empty($keywords)) {
      $conditions = [];
      $values = [];
      foreach ($keywords as $keyword) {
        if (strlen($keyword) >= 3) {
          $conditions[] = "LOWER(question) LIKE %s OR LOWER(answer) LIKE %s";
          $values[] = '%' . strtolower($keyword) . '%';
          $values[] = '%' . strtolower($keyword) . '%';
        }
      }

      if (!empty($conditions)) {
        $query = $this->db->prepare(
          "SELECT answer FROM $this->training_table WHERE " . implode(' OR ', $conditions) . " ORDER BY created_at DESC LIMIT 1",
          $values
        );

        $result = $this->db->get_row($query);
        if ($result) {
          return $this->format_response($result->answer);
        }
      }
    }

    return null;
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

  private function calculate_cosine_similarity($vec1, $vec2)
  {
    $dot_product = 0;
    $norm1 = 0;
    $norm2 = 0;

    foreach ($vec1 as $i => $val1) {
      $dot_product += $val1 * $vec2[$i];
      $norm1 += $val1 * $val1;
      $norm2 += $vec2[$i] * $vec2[$i];
    }

    if ($norm1 == 0 || $norm2 == 0) {
      return 0;
    }

    return $dot_product / (sqrt($norm1) * sqrt($norm2));
  }

  private function get_openai_embedding($text, $api_key)
  {
    $headers = [
      "Authorization: Bearer {$api_key}",
      'Content-Type: application/json'
    ];

    $data = [
      'model' => 'text-embedding-ada-002',
      'input' => $text
    ];

    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
      return null;
    }

    $response_data = json_decode($response, true);
    if (!isset($response_data['data'][0]['embedding'])) {
      return null;
    }

    return $response_data['data'][0]['embedding'];
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

  public function find_relevant_content($query)
  {
    if (empty($query)) {
      return null;
    }

    // Get OpenAI settings
    $settings = get_option('qobolak_openai_settings');
    if (empty($settings['api_key'])) {
      return null;
    }

    // Detect query language
    $query_language = $this->detect_language($query);

    // Get all training data
    $training_data = $this->db->get_results(
      "SELECT question, answer FROM $this->training_table",
      ARRAY_A
    );

    if (empty($training_data)) {
      return null;
    }

    // First try direct keyword matching
    $keywords = preg_split('/\s+/', trim($query));
    foreach ($keywords as $keyword) {
      if (mb_strlen($keyword) >= 2) {
        $matches = $this->db->get_results(
          $this->db->prepare(
            "SELECT question, answer FROM $this->training_table
             WHERE LOWER(question) LIKE %s
             OR LOWER(answer) LIKE %s",
            '%' . $this->db->esc_like(mb_strtolower($keyword)) . '%',
            '%' . $this->db->esc_like(mb_strtolower($keyword)) . '%'
          ),
          ARRAY_A
        );

        if (!empty($matches)) {
          // Use OpenAI to find the most relevant match
          $context = $query_language === 'arabic'
            ? "فيما يلي النتائج المحتملة. اختر الإجابة الأكثر صلة بالسؤال:\n\n"
            : "Here are the potential matches. Select the most relevant answer:\n\n";

          foreach ($matches as $match) {
            $context .= "Q: {$match['question']}\nA: {$match['answer']}\n\n";
          }

          $system_message = $query_language === 'arabic'
            ? 'أنت مساعد ذكي يختار الإجابة الأكثر صلة من مجموعة من الإجابات المحتملة. إذا كان السؤال كلمة واحدة مثل "فيسبوك" أو "قطر"، ابحث عن المعلومات ذات الصلة مثل روابط وسائل التواصل الاجتماعي أو معلومات الاتصال. يجب أن تكون إجاباتك دائماً باللغة العربية.'
            : 'You are a smart assistant that selects the most relevant answer from a set of potential matches. If the question is a single word like "facebook" or "qatar", look for related information like social media links or contact information. Always respond in English.';

          $prompt = $query_language === 'arabic'
            ? "السؤال: {$query}\n\n{$context}\nاختر الإجابة الأكثر صلة وأعد صياغتها بشكل مناسب. إذا لم تجد إجابة مناسبة، أجب بـ 'NO_RELEVANT_CONTENT'."
            : "Question: {$query}\n\n{$context}\nSelect the most relevant answer and rephrase it appropriately. If no suitable answer is found, respond with 'NO_RELEVANT_CONTENT'.";

          $answer = $this->ask_openai_chat($prompt, $settings['api_key'], $system_message);
          if ($answer && $answer !== 'NO_RELEVANT_CONTENT') {
            return $this->format_response($answer);
          }
        }
      }
    }

    // If no keyword matches found, try semantic search with all data
    $context = $query_language === 'arabic'
      ? "فيما يلي جميع الأسئلة والأجوبة المتوفرة. حاول إيجاد المعلومات ذات الصلة:\n\n"
      : "Here is all available QA data. Try to find relevant information:\n\n";

    foreach ($training_data as $data) {
      $context .= "Q: {$data['question']}\nA: {$data['answer']}\n\n";
    }

    $system_message = $query_language === 'arabic'
      ? 'أنت مساعد ذكي يبحث عن المعلومات ذات الصلة في مجموعة بيانات كبيرة. حاول فهم سياق السؤال وإيجاد المعلومات المناسبة. يجب أن تكون إجاباتك دائماً باللغة العربية.'
      : 'You are a smart assistant that searches for relevant information in a large dataset. Try to understand the context of the question and find appropriate information. Always respond in English.';

    $prompt = $query_language === 'arabic'
      ? "السؤال: {$query}\n\n{$context}\nابحث عن المعلومات ذات الصلة وقدم إجابة مناسبة. إذا لم تجد معلومات ذات صلة، أجب بـ 'NO_RELEVANT_CONTENT'."
      : "Question: {$query}\n\n{$context}\nSearch for relevant information and provide an appropriate answer. If no relevant information is found, respond with 'NO_RELEVANT_CONTENT'.";

    $answer = $this->ask_openai_chat($prompt, $settings['api_key'], $system_message);
    if ($answer && $answer !== 'NO_RELEVANT_CONTENT') {
      return $this->format_response($answer);
    }

    // If no answer found and not in training mode, return a generic response
    return $query_language === 'arabic'
      ? 'عذراً، لا يمكنني العثور على معلومات متعلقة بسؤالك.'
      : 'Sorry, I could not find any information related to your question.';
  }

  private function detect_language($text)
  {
    // Check if text contains Arabic characters
    return preg_match('/\p{Arabic}/u', $text) ? 'arabic' : 'english';
  }

  private function filter_by_language($data, $language)
  {
    return array_filter($data, function ($item) use ($language) {
      $question_lang = $this->detect_language($item['question']);
      $answer_lang = $this->detect_language($item['answer']);
      return $question_lang === $language || $answer_lang === $language;
    });
  }

  private function ask_openai_chat($prompt, $api_key, $system_message)
  {
    $headers = [
      "Authorization: Bearer {$api_key}",
      'Content-Type: application/json'
    ];

    $data = [
      'model' => 'gpt-3.5-turbo',
      'messages' => [
        [
          'role' => 'system',
          'content' => $system_message
        ],
        [
          'role' => 'user',
          'content' => $prompt
        ]
      ],
      'temperature' => 0.3,
      'max_tokens' => 500
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
      return null;
    }

    $response_data = json_decode($response, true);
    return $response_data['choices'][0]['message']['content'] ?? null;
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
}