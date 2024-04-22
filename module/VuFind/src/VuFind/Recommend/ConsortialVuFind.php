<?php

/**
 * ConsortialVuFind Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use Laminas\Config\Config;
use VuFind\Connection\ExternalVuFind as Connection;

use function intval;
use function is_callable;

/**
 * ConsortialVuFind Recommendations Module
 *
 * This class searches a separate instance of VuFind via its public API and links to
 * the record and results pages hosted within that instance. This is intended to
 * search and link to a consortial catalog, such as ReShare, which uses its own VuFind
 * instance to display consortium holdings and facilitate borrowing between institutions.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class ConsortialVuFind implements RecommendInterface, \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Request parameter for the search query
     *
     * @var string
     */
    protected $requestParam = 'lookfor';

    /**
     * Number of results to show
     *
     * @var int
     */
    protected $limit = 5;

    /**
     * Connection to consortial VuFind API
     *
     * @var Connection
     */
    protected $connection;

    /**
     * ConsortialVuFind.ini configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Base URL of a search results page
     *
     * @var string
     */
    protected $resultsBaseUrl = null;

    /**
     * Base URL of a record page
     *
     * @var string
     */
    protected $recordBaseUrl = null;

    /**
     * Any filters used in the search
     *
     * @var array
     */
    protected $searchFilters = [];

    /**
     * Boolean indicating if at least the minimal required configuration is present
     *
     * @var bool
     */
    protected $hasMinimumConfig = false;

    /**
     * Query string from the original search results
     *
     * @var ?string
     */
    protected $queryString = null;

    /**
     * Constructor
     *
     * @param Config     $config     ConsortialVuFind.ini configuration
     * @param Connection $connection Connection to consortial VuFind API
     */
    public function __construct(
        Config $config,
        Connection $connection
    ) {
        $this->config = $config;
        $this->connection = $connection;
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
        $settings = explode(':', $settings);
        $this->requestParam = !empty($settings[0]) ? $settings[0] : $this->requestParam;
        $limitSetting = intval($settings[1] ?? 0);
        $this->limit = $limitSetting > 0 ? $limitSetting : $this->limit;
        $configSectionName = $settings[2] ?? 'ReShare';

        // Read config file
        $configSection = $this->config->get($configSectionName);
        if ($configSection) {
            $this->resultsBaseUrl = $configSection->results_base_url;
            $this->recordBaseUrl = $configSection->record_base_url;
            $this->searchFilters = $configSection->filters?->toArray() ?? [];

            // Configure connection
            $this->connection->setBaseUrl($configSection->api_base_url);

            // Confirm that required configuration is present
            $this->hasMinimumConfig = $this->resultsBaseUrl
                && $this->recordBaseUrl
                && $configSection->api_base_url;
            if (!$this->hasMinimumConfig) {
                $this->logError("Required configuration missing in '$configSectionName'
                    section of ConsortialVuFind.ini.");
            }
        } else {
            $this->logError("'$configSectionName' section not found in ConsortialVuFind.ini.");
        }
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        // No action needed.
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
        $query = $results->getParams()->getQuery();
        if (is_callable([$query, 'getString'])) {
            $this->queryString = $query->getString();
        }
    }

    /**
     * Get the consortial VuFind instance's search results.
     *
     * @return array
     */
    public function getResults()
    {
        if (!$this->hasMinimumConfig || !$this->queryString) {
            return [];
        }

        $results = $this->connection->search(
            $this->queryString,
            $this->requestParam,
            $this->limit,
            $this->searchFilters
        );
        foreach (($results['records'] ?? []) as $i => $record) {
            $results['records'][$i]['url'] =
                $this->recordBaseUrl . '/' . urlencode($record['id']);
        }
        return $results;
    }

    /**
     * Get a URL to the full search results page in the consortial VuFind instance.
     *
     * @return string The url
     */
    public function getMoreResultsUrl()
    {
        $url = $this->resultsBaseUrl
            . '?' . $this->requestParam . '=' . urlencode($this->queryString);
        foreach ($this->searchFilters as $filter) {
            $url .= '&filter[]=' . urlencode($filter);
        }
        return $url;
    }
}
