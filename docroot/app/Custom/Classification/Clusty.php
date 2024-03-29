<?php

namespace App\Custom\Classification;

class Clusty
{
  private $htmlElementsWeight = [
    'div' => 1,
    'span' => 1,
    'h1' => 6,
    'h2' => 5,
    'h3' => 4,
    'h4' => 3,
    'h5' => 2,
  ];

  private $htmlSkipTags = [
    'script', 'img', 'br',
  ];

  /**
   * @param array $results
   * @return array
   */
  public function groupByWebsite(array $results)
  {
    $groups = [];
    foreach ($results as $result) {
      $url_components = parse_url($result['url']);
      $host_components = explode('.', $url_components['host']);
      $host = array_pop($host_components);
      $host = array_pop($host_components) . '.' . $host;
      if (empty($groups[$host])) {
        $groups[$host] = [
          'title' => $host,
          'children' => [],
          'weight' => 0,
        ];
      }
      $groups[$host]['children'][] = $result;
      $groups[$host]['weight']++;
    }
    return array_values($groups);
  }

  /**
   * @param string $word
   * @return bool
   */
  private function isValidWord($word, $language = 'en') {
    $conditions = [
      strlen($word) <= 3,
      (preg_match('/\\d/', $word) > 0),
    ];
    foreach ($conditions as $condition) {
      if ($condition == TRUE) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function classifyHtmlElement(array &$categories, $element, $weight = 1) {
    if (in_array($element->nodeName, $this->htmlSkipTags)) {
      return;
    }
    if ($element->hasChildNodes() && $element->nodeType === 1) {
      foreach ($element->childNodes as $child) {
        $htmlElementWeight = !empty($this->htmlElementsWeight[$element->nodeName]) ? $this->htmlElementsWeight[$element->nodeName] : 1;
        $this->classifyHtmlElement($categories, $child, $weight * $htmlElementWeight);
      }
    }
    else {
      $text = trim(preg_replace("/[^0-9a-z]+/i", " ", $element->textContent));
      $words = explode(' ', $text);
      foreach ($words as $word) {
        $word = strtolower((string) $word);
        if ($this->isValidWord($word)) {
          if (empty($categories[$word])) {
            $categories[$word] = 0;
          }
          $categories[$word] += $weight;
        }
      }
    }
  }

  /**
   * @param string $text
   *  Text to be classified.
   * @param array $omit
   *  Array of words to omit.
   * @return string
   *  Text category.
   */
  public function classifyText($text, $language = 'en', array $omit = [])
  {
    $stopwordsArr = array_map('str_getcsv', file(__DIR__ . "/stopwords/{$language}.csv"));
    $stopwords = [];
    foreach ($stopwordsArr as $row) {
      foreach ($row as $col => $val) {
        $stopwords[] = $val;
      }
    }
    $categories = [
      'other' => 0,
    ];

    try {
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      $d = new \DOMDocument;
      $d->loadHTML($text);
      $body = $d->getElementsByTagName('body')->item(0);
      foreach ($body->childNodes as $child) {
        if (!empty($child->textContent)) {
          $this->classifyHtmlElement($categories, $child);
        }
      }
    }
    catch (\Exception $e) {
    }
    foreach ($categories as $word => $num) {
      if (in_array($word, $stopwords) || in_array($word, $omit)) {
        unset($categories[$word]);
      }
    }
    asort($categories, SORT_NUMERIC);
    $categories = array_reverse($categories);
    return key($categories);
  }

  public function groupByCategory(array $results) {
    $clusters = [];
    $omit = explode(' ', $_GET['q']);
    foreach ($omit as $o) {
      $omit[] = $o . 's';
    }
    foreach ($results as $result) {
      $text = !empty($result['content']) ? $result['content'] : $result['summary'];
      $category = $this->classifyText($text, 'en', $omit);
      if (empty($clusters[$category])) {
        $clusters[$category] = [
          'title' => $category,
          'children' => [],
          'weight' => 0,
        ];
      }
      unset ($result['content']);
      unset ($result['summary']);
      $clusters[$category]['children'][] = $result;
      $clusters[$category]['weight']++;
    }
    $clusters = array_values($clusters);
    $other_cluster = [];
    $min_weight = 1000;
    $max_weight = 0;
    foreach ($clusters as $key => $cluster) {
      if ($cluster['weight'] < $min_weight) {
        $min_weight = $cluster['weight'];
      }
      if ($cluster['weight'] > $max_weight) {
        $max_weight = $cluster['weight'];
      }
    }
    if (count($clusters) > 10) {
      foreach ($clusters as $key => $cluster) {
        if (count($cluster['children']) == $min_weight) {
          if (empty($other_cluster)) {
            $other_cluster = [
              'title' => 'other',
              'children' => [],
              'weight' => 0,
            ];
          }
          $other_cluster['children'][] = $cluster;
          $other_cluster['weight']++;
          unset($clusters[$key]);
        }
      }
    }
    if (!empty($other_cluster)) {
      $other_cluster['weight'] = $min_weight;
      $clusters[] = $other_cluster;
    }

    return array_values($clusters);
  }
}
