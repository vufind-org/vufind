<?php

/**
 * EuropeanaResults Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use Laminas\Feed\Reader\Reader as FeedReader;

use function count;
use function intval;
use function is_object;

/**
 * EuropeanaResults Recommendations Module
 *
 * This class provides recommendations by using the Europeana API.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class EuropeanaResults implements
    RecommendInterface,
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Request parameter for searching
     *
     * @var string
     */
    protected $requestParam;

    /**
     * Result limit
     *
     * @var int
     */
    protected $limit;

    /**
     * Europeana base URL
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Fully constructed API URL
     *
     * @var string
     */
    protected $targetUrl;

    /**
     * Providers to exclude
     *
     * @var array
     */
    protected $excludeProviders;

    /**
     * Site to search
     *
     * @var string
     */
    protected $searchSite;

    /**
     * Link for more results
     *
     * @var string
     */
    protected $sitePath;

    /**
     * API key
     *
     * @var string
     */
    protected $key;

    /**
     * Search string
     *
     * @var string
     */
    protected $lookfor;

    /**
     * Search results
     *
     * @var array
     */
    protected $results;

    /**
     * Constructor
     *
     * @param string $key API key
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // Parse out parameters:
        $params = explode(':', $settings);
        $this->baseUrl = (isset($params[0]) && !empty($params[0]))
            ? $params[0] : 'api.europeana.eu/api/v2/opensearch.rss';
        $this->requestParam = (isset($params[1]) && !empty($params[1]))
            ? $params[1] : 'searchTerms';
        $this->limit = isset($params[2]) && is_numeric($params[2])
                        && $params[2] > 0 ? intval($params[2]) : 5;
        $this->excludeProviders = (isset($params[3]) && !empty($params[3]))
            ? $params[3] : [];
        //make array
        if (!empty($this->excludeProviders)) {
            $this->excludeProviders = explode(',', $this->excludeProviders);
        }
        $this->searchSite = 'Europeana.eu';
    }

    /**
     * Build the url which will be send to retrieve the RSS results
     *
     * @param string $targetUrl        Base URL
     * @param string $requestParam     Parameter name to add
     * @param array  $excludeProviders An array of providers to exclude when
     * getting results.
     *
     * @return string The url to be sent
     */
    protected function getURL($targetUrl, $requestParam, $excludeProviders)
    {
        // build url
        $url = $targetUrl . '?' . $requestParam . '=' . $this->lookfor;
        // add providers to ignore
        foreach ($excludeProviders as $provider) {
            $provider = trim($provider);
            if (!empty($provider)) {
                $url .= urlencode(' NOT europeana_dataProvider:"' . $provider . '"');
            }
        }
        $url .= '&wskey=' . urlencode($this->key);

        // return complete url
        return $url;
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // Collect the best possible search term(s):
        $this->lookfor = $request->get('lookfor', '');
        if (empty($this->lookfor) && is_object($params)) {
            $this->lookfor = $params->getQuery()->getAllTerms();
        }
        $this->lookfor = urlencode(trim($this->lookfor));
        $this->sitePath = 'http://www.europeana.eu/portal/search.html?query=' .
            $this->lookfor;
        $this->targetUrl = $this->getURL(
            'http://' . $this->baseUrl,
            $this->requestParam,
            $this->excludeProviders
        );
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->debug('Pulling feed from ' . $this->targetUrl);
        if (null !== $this->httpService) {
            FeedReader::setHttpClient(
                $this->httpService->createClient($this->targetUrl)
            );
        }
        $parsedFeed = FeedReader::import($this->targetUrl);
        $resultsProcessed = [];
        foreach ($parsedFeed as $value) {
            $link = $value->getLink();
            if (!empty($link)) {
                $resultsProcessed[] = [
                    'title' => $value->getTitle(),
                    'link' => $link,
                    'enclosure' => $value->getEnclosure()['url'] ?? null,
                ];
            }
            if (count($resultsProcessed) == $this->limit) {
                break;
            }
        }

        if (!empty($resultsProcessed)) {
            $this->results = [
                'worksArray' => $resultsProcessed,
                'feedTitle' => $this->searchSite,
                'sourceLink' => $this->sitePath,
            ];
        } else {
            $this->results = false;
        }
    }

    /**
     * Get the results of the query (false if none).
     *
     * @return array|bool
     */
    public function getResults()
    {
        return $this->results;
    }
}
