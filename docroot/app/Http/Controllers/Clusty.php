<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class Clusty extends Controller
{
  /**
   * @param array $results
   * @return array
   */
  public static function groupByWebsite(array $results)
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
  private static function isValidWord($word) {
    if (strlen($word) <= 3) {
      return FALSE;
    }
    if (preg_match('/\\d/', $word) > 0) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @param string $text
   *  Text to be classified.
   * @param array $omit
   *  Array of words to omit.
   * @return string
   *  Text category.
   */
  public static function classifyText($text, array $omit = [])
  {
    $text = trim(preg_replace("/[^0-9a-z]+/i", " ", $text));
    $words = explode(' ', $text);
    $categories = [
      'other' => 0,
    ];
    foreach ($words as $word) {
      $word = strtolower((string) $word);
      if (self::isValidWord($word) && !in_array($text, $omit)) {
        if (empty($categories[$word])) {
          $categories[$word] = 0;
        }
        $categories[$word] ++;
      }
    }
    asort($categories, SORT_NUMERIC);
    $categories = array_reverse($categories);
    return key($categories);
  }

  public static function groupByCategory(array $results) {
    $clusters = [];
    foreach ($results as $result) {
      $text = !empty($result['content']) ? $result['content'] : $result['summary'];
      $category = self::classifyText($text);
      if (empty($clusters[$category])) {
        $clusters[$category] = [
          'title' => $category,
          'children' => [],
          'weight' => 0,
        ];
      }
      $clusters[$category]['children'][] = $result;
      $clusters[$category]['weight']++;
    }
    $clusters = array_values($clusters);
    $other_cluster = [];
    foreach ($clusters as $key => $cluster) {
      if (count($cluster['children']) == 1) {
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
    if (!empty($other_cluster)) {
      $clusters[] = $other_cluster;
    }

    return array_values($clusters);
  }
}
