<?php

/**
 * LibGuides Profile Recommendations Module
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

use Laminas\Http\Client as HttpClient;
use VuFind\Connection\LibGuides;

/**
 * LibGuides Profile Recommendations Module
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class LibGuidesProfile implements
    RecommendInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Search results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Map of LibGuides account ID to data about that account
     *
     * @var array
     */
    protected $idToAccount = [];

    /**
     * Map of LibGuides subject name (lowercase) to ID of an account
     * (presumably a librarian) who wrote its subject guide
     *
     * @var array
     */
    protected $subjectToId = [];

    /**
     * LibGuides connector
     *
     * @var LibGuides
     */
    protected $libGuides;

    /**
     * Constructor
     *
     * @param array      $config Config object representing LibGuidesAPI.ini
     * @param HttpClient $client VuFind HTTP client
     */
    public function __construct($config, HttpClient $client)
    {
        $this->libGuides = new LibGuides(
            $client,
            $config->General->api_base_url,
            $config->General->client_id,
            $config->General->client_secret
        );
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
        // No action needed.
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
        $this->results = $results;
    }

    /**
     * Get terms related to the query.
     *
     * @return array
     */
    public function getResults()
    {
        // TODO: cache data, check it for staleness
        $this->refreshData();

        $query = $this->results->getParams()->getQuery();
        $account = $this->findBestMatch($query);
        return $account;
    }

    /**
     * Find the LibGuides account whose profile best matches the
     * given query.
     *
     * @param \VuFindSearch\Query\QueryInterface $query Current search query
     *
     * @return array LibGuides account
     */
    protected function findBestMatch(\VuFindSearch\Query\QueryInterface $query)
    {
        $queryString = $query->getAllTerms();
        if (!$queryString) {
            return false;
        }
        $queryString = strtolower($queryString);

        // Find the closest levenshtein match.
        $minDistance = PHP_INT_MAX;
        $subjects = array_keys($this->subjectToId);
        $id = null;
        foreach ($subjects as $subject) {
            $distance = levenshtein($subject, $queryString);
            if ($distance < $minDistance) {
                $id = $this->subjectToId[$subject];
                $minDistance = $distance;
            }
        }
        if ($id == null) {
            return false;
        }

        // // Find an exact match
        // if (!array_key_exists($queryString, $this->subjectToId)) {
        //     return false;
        // }
        // $id = $this->subjectToId[$queryString];
        // if (!$id) {
        //     return false;
        // }

        $account = $this->idToAccount[$id];
        if (!$account) {
            return false;
        }

        return $account;
    }

    /**
     * Load the list of LibGuides accounts from the LibGuides API.
     *
     * @return void
     */
    protected function refreshData()
    {
        $idToAccount = [];
        $subjectToId = [];
        $accounts = $this->libGuides->getAccounts();
        foreach ($accounts as $account) {
            $id = $account->id;
            $idToAccount[$id] = $account;

            foreach ($account->subjects ?? [] as $subject) {
                $subjectName = strtolower($subject->name);

                // Yes, this will override any previous account ID with the same subject.
                // Could be modified if someone has a library with more than one librarian
                // linked to the same Subject Guide if they have some way to decide who to display.
                $subjectToId[$subjectName] = $id;
            }
        }

        //TODO cache
        $this->idToAccount = $idToAccount;
        $this->subjectToId = $subjectToId;
    }

    /**
     * Return the list of facets configured to be collapsed
     *
     * @return array
     */
    public function isCollapsed()
    {
        return false;
    }
}
