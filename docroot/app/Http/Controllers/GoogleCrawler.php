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
  public function __construct($url = 'https://www.google.com/search') {
    $this->url = $url;
  }

  /**
   * Returns Google search results for a specific query.
   *
   * @param string $query
   * @return array
   * @throws \Exception
   */
  public function search($query) {
    if (!is_string($query)) {
      throw new \Exception('The query string is not valid.');
    }
    $url = $this->url . '?q=' . urlencode($query);
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
      $h3 = $div->getElementsByTagName('h3')->item(0);
    }
    return [];
  }
}
