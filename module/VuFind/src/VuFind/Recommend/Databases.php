<?php

/**
 * Databases Recommendations Module
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use Closure;
use Laminas\Cache\Storage\StorageInterface as CacheAdapter;

use function count;
use function intval;
use function is_callable;
use function strlen;

/**
 * Databases Recommendations Module
 *
 * This class displays a list of external links to the research databases represented
 * by EDS or similar results.  (Unlike the EDS ContentProvider facet that would narrow
 * down the results within VuFind.)
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class Databases implements RecommendInterface, \Psr\Log\LoggerAwareInterface
{
    use \VuFind\Cache\CacheTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Number of results to show
     *
     * @var int
     */
    protected $limit = 5;

    /**
     * The result facet with the list of databases.  Each value in the
     * array is a level of the facet hierarchy.
     *
     * @var array
     */
    protected $resultFacet = [];

    /**
     * For each database facet, the key to the database name.
     *
     * @var string
     */
    protected $resultFacetNameKey = 'value';

    /**
     * Databases listed in configuration file
     *
     * @var array
     */
    protected $configFileDatabases = [];

    /**
     * Configuration of whether to use the query string as a match point
     *
     * @var bool
     */
    protected $useQuery = true;

    /**
     * Minimum string length of a query to use as a match point
     *
     * @var int
     */
    protected $useQueryMinLength = 3;

    /**
     * When using the query string as a match point, the query string and
     * database names will first be normalized by removing the characters
     * in this regular expression. If empty, no normalization will occur.
     *
     * @var string
     */
    protected $useQueryReplacePattern = '/[-\/\.,:]/';

    /**
     * Maximum Levenshtein distance to match a query with the start
     * of a database name
     *
     * @var int
     */
    protected $useQueryMaxDifference = 2;

    /**
     * Configuration of whether to use LibGuides as a data source
     *
     * @var bool
     */
    protected $useLibGuides = false;

    /**
     * Configuration of whether to match on the alt_names field in LibGuides
     * in addition to the primary name
     *
     * @var bool
     */
    protected $useLibGuidesAlternateNames = true;

    /**
     * URL to a list of all available databases, for display in the results list,
     * or false to omit.
     *
     * @var bool|string
     */
    protected $linkToAllDatabases = false;

    /**
     * Group results into separate arrays for template use: results based
     * on the search query, or the results, or specific result types
     * (below).
     */
    protected $useGroupedResults = false;

    /**
     * Database types to highlight in separate groupings.
     */
    protected $typeGroups = [];

    /**
     * Constructor
     *
     * @param \VuFind\Config\ConfigManagerInterface $configManager   Config Manager
     * @param Closure                               $libGuidesGetter Getter for LibGuides API connection
     * @param CacheAdapter                          $cache           Object cache
     */
    public function __construct(
        protected \VuFind\Config\ConfigManagerInterface $configManager,
        protected Closure $libGuidesGetter,
        CacheAdapter $cache
    ) {
        $this->setCacheStorage($cache);
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
        // Only change settings from current values if they are defined in $settings or .ini

        $settings = explode(':', $settings);
        $this->limit
            = (isset($settings[0]) && is_numeric($settings[0]) && $settings[0] > 0)
            ? intval($settings[0]) : $this->limit;
        $databasesConfigFile = $settings[1] ?? 'EDS';

        $databasesConfig = $this->configManager->getConfigArray($databasesConfigFile)['Databases'] ?? [];
        if (empty($databasesConfig)) {
            throw new \Exception("Databases config file $databasesConfigFile must have section 'Databases'.");
        }
        $this->configFileDatabases = $databasesConfig['url']
            ?? $this->configFileDatabases;
        array_walk($this->configFileDatabases, function (&$value, $name): void {
            $value = [
                'name' => $name,
                'url' => $value,
            ];
        });

        $this->resultFacet = $databasesConfig['resultFacet']
            ?? $this->resultFacet;
        $this->resultFacetNameKey = $databasesConfig['resultFacetNameKey']
            ?? $this->resultFacetNameKey;

        $this->useQuery = $databasesConfig['useQuery']
            ?? $this->useQuery;
        $this->useQueryMinLength = $databasesConfig['useQueryMinLength']
            ?? $this->useQueryMinLength;
        $queryReplaceConfig = $databasesConfig['useQueryReplacePattern'] ?? $this->useQueryReplacePattern;
        $this->useQueryReplacePattern = $queryReplaceConfig ?: '';
        $this->useQueryMaxDifference = $databasesConfig['useQueryMaxDifference']
            ?? $this->useQueryMaxDifference;

        $this->useLibGuides = $databasesConfig['useLibGuides']
            ?? $this->useLibGuides;
        if ($this->useLibGuides) {
            // Cache the data related to profiles for up to 10 minutes:
            $libGuidesApiConfig = $this->configManager->getConfigArray('LibGuidesAPI');
            $this->cacheLifetime = intval($libGuidesApiConfig['GetAZ']['cache_lifetime'] ?? 600);

            $this->useLibGuidesAlternateNames = $databasesConfig['useLibGuidesAlternateNames']
                ?? $this->useLibGuidesAlternateNames;

            $this->linkToAllDatabases = $databasesConfig['linkToAllDatabases']
                ?? $this->linkToAllDatabases;
        }

        $this->useGroupedResults = $databasesConfig['useGroupedResults'] ?? $this->useGroupedResults;
        $this->typeGroups = $databasesConfig['typeGroups'] ?? $this->typeGroups;
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
     * Get an array of groups of results.  There is a group of results related to
     * the query, and a group related to the search results.  There may be additional
     * groups of search result databases within configured database types.
     *
     * @return array
     */
    public function getGroupedResults()
    {
        if (!$this->useGroupedResults) {
            return null;
        }
        $groupedResults = [];

        if ($resultsFromSearchQuery = $this->getResultsFromSearchQuery()) {
            $groupedResults['query'] = $resultsFromSearchQuery;
        }
        foreach ($this->typeGroups as $typeGroup) {
            if ($resultsFromType = $this->getResultsFromSearchResultFacets($typeGroup)) {
                $groupedResults['type_' . $typeGroup] = $resultsFromType;
            }
        }
        if ($resultsFromSearchResult = $this->getResultsFromSearchResultFacets()) {
            $groupedResults['results'] = $resultsFromSearchResult;
        }

        return $groupedResults;
    }

    /**
     * Get a single list of databases related to both the query and the search results.
     *
     * @return array
     */
    public function getResults()
    {
        // Array of url => [name, url].  Key by URL so that the same database (under alternate
        // names) is not duplicated.
        $databases = [];

        // Add databases from search query
        if ($this->useQuery) {
            $databases = $this->getResultsFromSearchQuery();
            if (count($databases) >= $this->limit) {
                return $databases;
            }
        }

        // Add databases from result facets
        $databases = array_merge(
            $databases,
            $this->getResultsFromSearchResultFacets()
        );

        return $databases;
    }

    /**
     * Get a list of databases related to the query.
     *
     * @return array
     */
    public function getResultsFromSearchQuery()
    {
        $nameToDatabase = $this->getDatabases();
        $databases = [];
        if ($this->useQuery) {
            $queryObject = $this->results->getParams()->getQuery();
            $query = is_callable([$queryObject, 'getString'])
                ? $this->normalizeQueryString($queryObject->getString())
                : '';
            if (strlen($query) >= $this->useQueryMinLength) {
                foreach ($nameToDatabase as $name => $databaseInfo) {
                    $normalizedName = $this->normalizeQueryString($name);
                    $nameContainsQuery = str_contains($normalizedName, $query);
                    $nameResemblesQuery = $this->useQueryMaxDifference &&
                        (levenshtein(substr($normalizedName, 0, strlen($query)), $query)
                            <= $this->useQueryMaxDifference);
                    if ($nameContainsQuery || $nameResemblesQuery) {
                        $databases[$databaseInfo['url']] = $databaseInfo;
                    }
                    if (count($databases) >= $this->limit) {
                        return $databases;
                    }
                }
            }
        }
        return $databases;
    }

    /**
     * Get a list of databases related to the search results.
     *
     * @param ?string $databaseType When provided, return only databases that match
     * the given type.
     *
     * @return array
     */
    public function getResultsFromSearchResultFacets($databaseType = null)
    {
        $resultFacet = $this->resultFacet;
        if (count($resultFacet) < 1) {
            $this->logError('At least one facet key is required.');
            return [];
        }

        $resultDatabasesTopFacet = array_shift($resultFacet);
        try {
            $resultDatabases =
                $this->results->getFacetList([$resultDatabasesTopFacet => null])[$resultDatabasesTopFacet];
            while (count($resultFacet) && $resultDatabases) {
                $resultDatabases = $resultDatabases[array_shift($resultFacet)];
            }
        } catch (\Exception $ex) {
            $this->logError('Error using configured facets to find list of result databases.');
            return [];
        }

        $nameToDatabase = $this->getDatabases();
        $databases = [];
        foreach ($resultDatabases as $resultDatabase) {
            try {
                $name = $resultDatabase[$this->resultFacetNameKey];
            } catch (\Exception $ex) {
                $this->logError("Name key '$this->resultFacetNameKey' not found for database.");
                continue;
            }
            $databaseInfo = $nameToDatabase[$name] ?? null;
            if ($databaseInfo) {
                if ($databaseType && !$this->databaseMatchesType($databaseInfo, $databaseType)) {
                    continue;
                }
                $databases[$databaseInfo['url']] = $databaseInfo;
            }
            if (count($databases) >= $this->limit) {
                return $databases;
            }
        }
        return $databases;
    }

    /**
     * Normalize a query string or database name for comparison with each other.
     * Force to lower case, and remove any characters specified by a regex.
     *
     * @param string $str The query string or database name
     *
     * @return string The normalized string
     */
    protected function normalizeQueryString(string $str): string
    {
        $str = strtolower($str);
        if ($this->useQueryReplacePattern) {
            $str = preg_replace($this->useQueryReplacePattern, '', $str);
        }
        return $str;
    }

    /**
     * Return true if the given database info indicates that the database
     * belongs to the given type. Or if the type begins with '-', then
     * returns true if the given database info does *not* include that type.
     *
     * @param array  $databaseInfo The database info from LibGuides and/or config
     * @param string $type         The database type
     *
     * @return bool
     */
    protected function databaseMatchesType($databaseInfo, $type)
    {
        $resultOnMatch = true;
        if (str_starts_with($type, '-')) {
            $type = substr($type, 1);
            $resultOnMatch = false;
        }
        foreach (($databaseInfo['az_types'] ?? []) as $databaseType) {
            $normalizedDatabaseTypeName = preg_replace('/[\s\&]/', '_', $databaseType->name);
            if ($type == $normalizedDatabaseTypeName) {
                return $resultOnMatch;
            }
        }
        return !$resultOnMatch;
    }

    /**
     * Generate a combined list of databases from all enabled sources.
     *
     * @return An array mapping a database name to a sub-array with
     * the url.
     */
    protected function getDatabases()
    {
        $databases = [];
        if ($this->useLibGuides) {
            $databases = $this->getLibGuidesDatabases();
        }
        $databases = array_merge($databases, $this->configFileDatabases);
        return $databases;
    }

    /**
     * Load or retrieve from the cache the list of LibGuides A-Z databases.
     *
     * @return array An array mapping a database name to an array
     * representing the full object retrieved from the LibGuides /az API.
     */
    protected function getLibGuidesDatabases()
    {
        $nameToDatabase = $this->getCachedData('libGuidesAZ-nameToDatabase');
        if (empty($nameToDatabase)) {
            $libGuides = ($this->libGuidesGetter)();
            $includeTypes = !empty($this->typeGroups);
            $databases = $libGuides->getAZ($includeTypes);

            $nameToDatabase = [];
            foreach ($databases as $database) {
                $nameToDatabase[$database->name] = (array)$database;
                // The alt_names field is single-valued free text
                if ($this->useLibGuidesAlternateNames && ($database->alt_names ?? false)) {
                    $nameToDatabase[$database->alt_names] = (array)$database;
                }
            }

            $this->putCachedData('libGuidesAZ-nameToDatabase', $nameToDatabase);
        }
        return $nameToDatabase;
    }

    /**
     * Get a URL to a list of all available databases, if configured.
     *
     * @return string The URL, or null.
     */
    public function getLinkToAllDatabases()
    {
        return $this->linkToAllDatabases;
    }
}
