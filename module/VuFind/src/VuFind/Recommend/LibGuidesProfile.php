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

use Laminas\Cache\Storage\StorageInterface as CacheAdapter;
use Laminas\Config\Config;
use VuFind\Connection\LibGuides;

use function intval;
use function is_string;
use function strlen;

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
    use \VuFind\Cache\CacheTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Search results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * LibGuides connector
     *
     * @var LibGuides
     */
    protected $libGuides;

    /**
     * List of strategies enabled to find a matching LibGuides profile
     *
     * @var int
     */
    protected $strategies = [];

    /**
     * Map of call number pattern to config alias
     *
     * @var array
     */
    protected $callNumberToAlias;

    /**
     * Map of config alias to LibGuides account ID
     *
     * @var array
     */
    protected $aliasToAccountId;

    /**
     * Facet field name containing the call numbers to match against
     *
     * @var string
     */
    protected $callNumberField;

    /**
     * Length of the substring at the start of a call number to match against
     *
     * @var int
     */
    protected $callNumberLength;

    /**
     * Constructor
     *
     * @param LibGuides    $libGuides LibGuides API connection
     * @param Config       $config    LibGuides API configuration object
     * @param CacheAdapter $cache     Object cache
     */
    public function __construct(
        LibGuides $libGuides,
        Config $config,
        CacheAdapter $cache
    ) {
        $this->libGuides = $libGuides;
        $this->setCacheStorage($cache);

        // Cache the data related to profiles for up to 10 minutes:
        $this->cacheLifetime = intval($config->GetAccounts->cache_lifetime ?? 600);

        if ($profile = $config->Profile) {
            $strategies = $profile->get('strategies', []);
            $this->strategies = is_string($strategies) ? [$strategies] : $strategies;

            $this->callNumberToAlias = $profile->call_numbers ? $profile->call_numbers->toArray() : [];
            $this->aliasToAccountId = $profile->profile_aliases ? $profile->profile_aliases->toArray() : [];
            $this->callNumberField = $profile->get('call_number_field', 'callnumber-first');
            $this->callNumberLength = $profile->get('call_number_length', 3);
        }
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
        $this->results = $results;
    }

    /**
     * Get terms related to the query.
     *
     * @return array
     */
    public function getResults()
    {
        if (empty($this->strategies)) {
            throw new \Exception('LibGuidesAPI.ini must define at least one strategy if LibGuidesProfile is used.');
        }

        // Consider strategies in the order listed in the config file.
        foreach ($this->strategies as $strategy) {
            // Sanitize the strategy name.
            $strategy = preg_replace('/[^\w]/', '', $strategy);

            $method = 'findBestMatchBy' . $strategy;
            if (
                method_exists($this, $method) &&
                $account = $this->$method($this->results)
            ) {
                return $account;
            }
        }
        return false;
    }

    /**
     * Find the LibGuides account whose profile best matches the
     * call number facets in the given search results.
     *
     * Adapted from Demian Katz: https://gist.github.com/demiankatz/4600bdfb9af9882ad491f74c406a8a8a#file-guide-php-L308
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return array LibGuides account
     */
    protected function findBestMatchByCallNumber($results)
    {
        // Skip if no call number mapping was provided by config.
        if (empty($this->callNumberToAlias)) {
            return false;
        }

        // Get the Call Number facet list.
        $filter = [
            $this->callNumberField => 'Call Number',
        ];
        $facets = $results->getFacetList($filter);

        // For each call number facet.
        $profiles = [];
        foreach ($facets[$this->callNumberField]['list'] ?? [] as $current) {
            $callNumber = trim(substr($current['value'], 0, $this->callNumberLength));

            // Find an alias for this call number, or a broader call number if none is found.
            while (strlen($callNumber > 0) && !isset($this->callNumberToAlias[$callNumber])) {
                $callNumber = substr($callNumber, 0, strlen($callNumber) - 1);
            }

            // Add "match value" to that alias based on the result count with that call number.
            if (isset($this->callNumberToAlias[$callNumber])) {
                $alias = $this->callNumberToAlias[$callNumber];
                if (!isset($profiles[$alias])) {
                    $profiles[$alias] = 0;
                }
                $profiles[$alias] += $current['count'];
            }
        }

        // Identify the alias with the highest match value
        arsort($profiles);
        if (empty($profiles)) {
            return false;
        }
        $alias = array_key_first($profiles);

        // Return the profile for that alias.
        $accountId = $this->aliasToAccountId[$alias] ?? null;
        if (!$accountId) {
            return false;
        }
        $idToAccount = $this->getLibGuidesData()['idToAccount'];
        $account = $idToAccount[$accountId];
        return $account;
    }

    /**
     * Find the LibGuides account whose subject expertise in their
     * profile best matches the given query.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return array LibGuides account
     */
    protected function findBestMatchBySubject($results)
    {
        $data = $this->getLibGuidesData();
        $subjectToId = $data['subjectToId'];
        $idToAccount = $data['idToAccount'];

        $query = $results->getParams()->getQuery();
        $queryString = $query->getAllTerms();
        if (!$queryString) {
            return false;
        }
        $queryString = strtolower($queryString);

        // Find the closest levenshtein match.
        $minDistance = PHP_INT_MAX;
        $subjects = array_keys($subjectToId);
        $id = null;
        foreach ($subjects as $subject) {
            $distance = levenshtein($subject, $queryString);
            if ($distance < $minDistance) {
                $id = $subjectToId[$subject];
                $minDistance = $distance;
            }
        }
        if ($id == null) {
            return false;
        }

        $account = $idToAccount[$id];
        if (!$account) {
            return false;
        }

        return $account;
    }

    /**
     * Load or retrieve from the cache the list of LibGuides accounts
     * from the LibGuides API.
     *
     * @return array An array containing the idToAccount and subjectToId maps
     */
    protected function getLibGuidesData()
    {
        $idToAccount = $this->getCachedData('libGuidesProfile-idToAccount');
        $subjectToId = $this->getCachedData('libGuidesProfile-subjectToId');
        if (!empty($idToAccount) && !empty($subjectToId)) {
            return [
                'idToAccount' => $idToAccount,
                'subjectToId' => $subjectToId,
            ];
        }

        return $this->populateLibGuidesCache();
    }

    /**
     * Load the list of LibGuides accounts from the LibGuides API.
     *
     * @return array An array containing the idToAccount and subjectToId maps
     */
    protected function populateLibGuidesCache()
    {
        $idToAccount = [];
        $subjectToId = [];
        $accounts = $this->libGuides->getAccounts();
        foreach ($accounts ?? [] as $account) {
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

        $this->putCachedData('libGuidesProfile-idToAccount', $idToAccount);
        $this->putCachedData('libGuidesProfile-subjectToId', $subjectToId);
        return [
            'idToAccount' => $idToAccount,
            'subjectToId' => $subjectToId,
        ];
    }
}
