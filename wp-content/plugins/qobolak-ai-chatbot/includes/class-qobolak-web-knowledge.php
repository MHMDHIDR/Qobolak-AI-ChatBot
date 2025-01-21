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
    'https://www.qobolak.com/ar/contact-us-ar',
    'https://www.qobolak.com/services',
    'https://www.qobolak.com/ar/services-ar',
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

  public function __construct()
  {
    global $wpdb;
    $this->db = $wpdb;
    $this->table_name = $wpdb->prefix . 'qobolak_external_knowledge';
    $this->init_table();
  }

  private function init_table()
  {
    $charset_collate = $this->db->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            section varchar(50) NOT NULL,
            last_scraped datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY url (url)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }

  public function scrape_website()
  {
    if (!class_exists('DOMDocument')) {
      throw new Exception('DOMDocument class is required for web scraping');
    }

    foreach ($this->urls_to_scrape as $url) {
      $this->scrape_page($url);
      // Be nice to the server
      sleep(2);
    }
  }

  private function scrape_page($url)
  {
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
      return false;
    }

    $html = wp_remote_retrieve_body($response);

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Extract main title
    $title = $this->extract_text($xpath, '//h1');

    // Extract navigation links
    $nav_links = $this->extract_text($xpath, '//ul[@id="top-menu"]//a');

    // Extract hero section
    $hero_titles = $this->extract_text($xpath, '//div[contains(@class, "et_pb_slide")]//h2');
    $hero_descriptions = $this->extract_text($xpath, '//div[contains(@class, "et_pb_slide")]//p');

    // Extract services
    $services = $this->extract_text($xpath, '//div[contains(@class, "et_pb_blurb")]//h4');

    // Extract testimonials
    $testimonials = $this->extract_text($xpath, '//div[contains(@class, "et_pb_testimonial")]//p');
    $authors = $this->extract_text($xpath, '//div[contains(@class, "et_pb_testimonial")]//span[contains(@class, "et_pb_testimonial_author")]');

    // Extract "Message From Qabolak"
    $message_title = $this->extract_text($xpath, '//div[contains(@class, "et_pb_text_2")]//h2');
    $message_content = $this->extract_text($xpath, '//div[contains(@class, "et_pb_text_3")]//p');

    // Extract statistics
    $years_in_industry = $this->extract_text($xpath, '//div[contains(@class, "et_pb_number_counter_0")]//span[@class="percent-value"]');
    $years_label = $this->extract_text($xpath, '//div[contains(@class, "et_pb_number_counter_0")]//h3');

    $students_served = $this->extract_text($xpath, '//div[contains(@class, "et_pb_number_counter_1")]//span[@class="percent-value"]');
    $students_label = $this->extract_text($xpath, '//div[contains(@class, "et_pb_number_counter_1")]//h3');

    $partners_worldwide = $this->extract_text($xpath, '//div[contains(@class, "et_pb_number_counter_2")]//span[@class="percent-value"]');
    $partners_label = $this->extract_text($xpath, '//div[contains(@class, "et_pb_number_counter_2")]//h3');

    // Extract "Why Us?"
    $why_us_title = $this->extract_text($xpath, '//div[contains(@class, "et_pb_text_8")]//h2');
    $why_us_descriptions = [
      $this->extract_text($xpath, '//div[contains(@class, "et_pb_blurb_4")]//p'),
      $this->extract_text($xpath, '//div[contains(@class, "et_pb_blurb_5")]//p'),
      $this->extract_text($xpath, '//div[contains(@class, "et_pb_blurb_6")]//p'),
      $this->extract_text($xpath, '//div[contains(@class, "et_pb_blurb_7")]//p')
    ];
    $know_more_link = $this->extract_text($xpath, '//div[contains(@class, "et_pb_button_1_wrapper")]//a/@href');

    // Combine content for storage
    $content = "Navigation Links: $nav_links\nHero Titles: $hero_titles\nHero Descriptions: $hero_descriptions\nServices: $services\nTestimonials: $testimonials\nAuthors: $authors\nMessage Title: $message_title\nMessage Content: $message_content\nStatistics:\n - $years_in_industry $years_label\n - $students_served $students_label\n - $partners_worldwide $partners_label\nWhy Us?\n - Title: $why_us_title\n - Descriptions: " . implode("\n - ", $why_us_descriptions) . "\n - Know More: $know_more_link";

    // Determine section based on URL
    $section = $this->determine_section($url);

    // Store in database
    $this->store_content($url, $title, $content, $section);
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

  private function store_content($url, $title, $content, $section)
  {
    return $this->db->replace(
      $this->table_name,
      array(
        'url' => $url,
        'title' => $title,
        'content' => $content,
        'section' => $section,
        'last_scraped' => current_time('mysql')
      ),
      array('%s', '%s', '%s', '%s', '%s')
    );
  }

  public function find_relevant_content($query)
  {
    // First try exact section matching
    $section = $this->guess_section($query);
    if ($section) {
      $results = $this->db->get_results($this->db->prepare(
        "SELECT * FROM $this->table_name WHERE section = %s",
        $section
      ));
      if (!empty($results)) {
        return $results;
      }
    }

    // Fallback to full-text search
    $words = explode(' ', $query);
    $search_terms = array_filter($words, function ($word) {
      return strlen($word) > 3;
    });

    if (empty($search_terms)) {
      return array();
    }

    $where_clauses = array();
    foreach ($search_terms as $term) {
      $where_clauses[] = $this->db->prepare(
        "(content LIKE %s OR title LIKE %s)",
        '%' . $this->db->esc_like($term) . '%',
        '%' . $this->db->esc_like($term) . '%'
      );
    }

    $sql = "SELECT * FROM $this->table_name WHERE " . implode(' OR ', $where_clauses);
    return $this->db->get_results($sql);
  }

  private function guess_section($query)
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