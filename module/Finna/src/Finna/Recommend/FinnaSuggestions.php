<?php
/**
 * FinnaSuggestions Recommendations Module
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Recommend;

use Laminas\Http\Client;

/**
 * FinnaSuggestions Recommendations Module
 *
 * This class provides recommendations via VuFind REST API (deferred).
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FinnaSuggestions implements
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \VuFind\Recommend\RecommendInterface,
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * API url
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Search URL
     *
     * @var string
     */
    protected $searchUrl;

    /**
     * Settings from searches.ini
     *
     * @var string
     */
    protected $settings;

    /**
     * Search term
     *
     * @var string
     */
    protected $lookfor;

    /**
     * Search handler
     *
     * @var string
     */
    protected $searchHandler;

    /**
     * Search type
     *
     * @var string
     */
    protected $searchType;

    /**
     * Result count
     *
     * @var int
     */
    protected $resultCount;

    /**
     * HTTP client.
     *
     * @var \Laminas\Http\Client
     */
    protected $client;

    /**
     * URL helper.
     *
     * @var \VuFind\View\Helper\Root\Url
     */
    protected $urlHelper;

    /**
     * Search handlers that are supported in Finna.fi
     *
     * @var array
     */
    protected $supportedSearchHandlers = ['AllFields', 'Title', 'Author', 'Subject'];

    /**
     * FinnaSuggestions constructor.
     *
     * @param Client $client HTTP client
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client;
        $this->resetSearch();
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        $lookfor = $request->get('lookfor');
        $searchHandler = $params->getSearchHandler()
            ?: $request->get('searchHandler');
        $searchType
            = $params->getSearchType() ?: $request->get('searchType');

        // Output suggestions only for basic search with
        // AllFields handler and no filters.
        if (!empty($lookfor)
            && !$params->getRawFilters()
            && in_array($searchHandler, $this->supportedSearchHandlers)
            && $searchType === 'basic'
        ) {
            $this->lookfor = $lookfor;
            $this->searchHandler = $searchHandler;
            $this->searchType = $searchType;
        } else {
            $this->resetSearch();
        }
    }

    /**
     * Get recommendations (for use in the view).
     *
     * @return array
     */
    public function getRecommendations()
    {
        return [
            'lookfor' => $this->lookfor,
            'resultCount' => $this->resultCount,
            'searchLink' => $this->getSearchLink()
        ];
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
        $this->settings = $settings;
        $settings = explode(':', $settings);
        $this->apiUrl = !empty($settings[0]) ? ('https://' . $settings[0]) : null;
        $this->searchUrl = !empty($settings[1]) ? ('https://' . $settings[1]) : null;
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        if (!$this->client || !$this->lookfor || !$this->apiUrl) {
            return;
        }

        $url = str_replace(
            ['%%lookfor%%', '%%handler%%'],
            [urlencode($this->lookfor), urlencode($this->searchHandler)],
            $this->apiUrl
        );
        $client = $this->client->setUri($url);
        $client->setOptions(
            [
                'timeout' => 30,
                'useragent' => 'VuFind'
            ]
        );
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept', 'application/json'
        );
        $client->setParameterGet(['limit' => 0]);
        $response = $client->setMethod('GET')->send();

        if (!$response->isSuccess()) {
            return;
        }

        $result = $response->getBody();
        $result = json_decode($result, true);

        if ($result['status'] === 'OK') {
            if ($resultCount = $result['resultCount'] ?? null) {
                $this->resultCount = $resultCount;
            }
        }
    }

    /**
     * Reset search parameters
     *
     * @return void
     */
    protected function resetSearch()
    {
        $this->lookfor = '';
        $this->searchHandler = 'AllFields';
        $this->searchType = 'basic';
    }

    /**
     * Get search link to Finna
     *
     * @return string
     */
    protected function getSearchLink()
    {
        return str_replace(
            ['%%lookfor%%', '%%handler%%', '%%lng%%'],
            [
                urlencode($this->lookfor),
                urlencode($this->searchHandler),
                urlencode($this->getTranslatorLocale())
            ],
            $this->searchUrl
        );
    }
}
