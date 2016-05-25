<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\URL;

class GoogleCrawler extends Controller
{
  private $url;
  private $resultsContainerId = 'res';

  /**
   * GoogleCrawler constructor.
   * @param string $url
   */
  public function __construct($url = 'https://www.google.com') {
    $this->url = $url;
  }

  /**
   * Returns Google search results for a specific query.
   *
   * @param string $query
   *  The searched text.
   * @param int $limit
   *  The number of results returned.
   * @param bool $advanced
   *  If set to TRUE, the crawler will create a new request for every result to
   * get the full page content.
   * @return array
   *  An array of arrays containing:
   *    - url: Url to results webpage
   *    - title: Title of webpage
   *    - summary: Summary of webpage content
   *    - content: Parsed content of webpage if the $advanced parameter is set to
   * TRUE, NULL otherwise
   * @throws \Exception
   */
  public function search($query, $limit = 10, $advanced = FALSE) {
    if (!is_string($query)) {
      throw new \Exception('The query string is not valid.');
    }
    $url = $this->url . '/search?q=' . urlencode($query);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $contents = curl_exec($ch);
    curl_close($ch);
    $redirect_url = NULL;
    if(preg_match('#Location: (.*)#', $contents, $r)) {
      $redirect_url = trim($r[1]);
    }
    $url = $redirect_url ?: $url;
    $contents = file_get_contents($url);
    $doc = new \DOMDocument();

    // Just because that: http://php.net/manual/ro/domdocument.loadhtml.php#95463
    libxml_use_internal_errors(true);

    $doc->loadHTML($contents);
    $container = $doc->getElementById($this->resultsContainerId);
    foreach ($container->getElementsByTagName('div') as $div) {
      /** @var \DOMElement $div */
      if ($div->getAttribute('class') != 'g') {
        continue;
      }
      $item = [];
      /** @var \DOMElement $h3 */
      $h3 = $div->getElementsByTagName('h3')->item(0);
      $a = $h3->firstChild;
      $item['url'] = $item['title'] = $item['summary'] = $item['content'] = NULL;
      $item['url'] = $this->url . $a->getAttribute('href');
      $item['title'] = strip_tags($a->textContent);
      foreach ($div->getElementsByTagName('span') as $span) {
        /** @var \DOMElement $span */
        if ($span->getAttribute('class') != 'st') {
          continue;
        }
        $item['summary'] = strip_tags($span->textContent);
        break;
      }
      if (!empty($item['url'])) {
        $items[] = $item;
      }
    }
    return $items;
  }
}