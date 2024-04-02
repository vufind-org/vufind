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
class Databases implements RecommendInterface, \Laminas\Log\LoggerAwareInterface
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
     * Configuration manager
     *
     * @var ConfigManager
     */
    protected $configManager;

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
     * @var bool
     */
    protected $useQueryMinLength = 3;

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
     */
    protected $linkToAllDatabases = false;

    /**
     * Callable for LibGuides connector
     *
     * @var callable
     */
    protected $libGuidesGetter;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configManager   Config PluginManager
     * @param callable                     $libGuidesGetter Getter for LibGuides API connection
     * @param CacheAdapter                 $cache           Object cache
     */
    public function __construct(
        \VuFind\Config\PluginManager $configManager,
        callable $libGuidesGetter,
        CacheAdapter $cache
    ) {
        $this->configManager = $configManager;
        $this->libGuidesGetter = $libGuidesGetter;
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

        $databasesConfig = $this->configManager->get($databasesConfigFile)->Databases;
        if (!$databasesConfig) {
            throw new \Exception("Databases config file $databasesConfigFile must have section 'Databases'.");
        }
        $this->configFileDatabases = $databasesConfig->url?->toArray()
            ?? $this->configFileDatabases;
        array_walk($this->configFileDatabases, function (&$value, $name) {
            $value = [
                'name' => $name,
                'url' => $value,
            ];
        });

        $this->resultFacet = $databasesConfig->resultFacet?->toArray() ?? $this->resultFacet;
        $this->resultFacetNameKey = $databasesConfig->resultFacetNameKey
            ?? $this->resultFacetNameKey;

        $this->useQuery = $databasesConfig->useQuery ?? $this->useQuery;
        $this->useQueryMinLength = $databasesConfig->useQueryMinLength
            ?? $this->useQueryMinLength;

        $this->useLibGuides = $databasesConfig->useLibGuides ?? $this->useLibGuides;
        if ($this->useLibGuides) {
            // Cache the data related to profiles for up to 10 minutes:
            $libGuidesApiConfig = $this->configManager->get('LibGuidesAPI');
            $this->cacheLifetime = intval($libGuidesApiConfig->GetAZ->cache_lifetime ?? 600);

            $this->useLibGuidesAlternateNames = $databasesConfig->useLibGuidesAlternateNames
                ?? $this->useLibGuidesAlternateNames;

            $this->linkToAllDatabases = $databasesConfig->linkToAllDatabases
                ?? $this->linkToAllDatabases;
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
        $this->results = $results;
    }

    /**
     * Get terms related to the query.
     *
     * @return array
     */
    public function getResults()
    {
        if (count($this->resultFacet) < 1) {
            $this->logError('At least one facet key is required.');
            return [];
        }

        $resultDatabasesTopFacet = array_shift($this->resultFacet);
        try {
            $resultDatabases =
                $this->results->getFacetList([$resultDatabasesTopFacet => null])[$resultDatabasesTopFacet];
            while (count($this->resultFacet) && $resultDatabases) {
                $resultDatabases = $resultDatabases[array_shift($this->resultFacet)];
            }
        } catch (\Exception $ex) {
            $this->logError('Error using configured facets to find list of result databases.');
            return [];
        }
        $nameToDatabase = $this->getDatabases();

        // Array of url => [name, url].  Key by URL so that the same database (under alternate
        // names) is not duplicated.
        $databases = [];

        // Add databases from search query
        if ($this->useQuery) {
            $queryObject = $this->results->getParams()->getQuery();
            $query = is_callable([$queryObject, 'getString'])
                ? strtolower($queryObject->getString())
                : '';
            if (strlen($query) >= $this->useQueryMinLength) {
                foreach ($nameToDatabase as $name => $databaseInfo) {
                    if (str_contains(strtolower($name), $query)) {
                        $databases[$databaseInfo['url']] = $databaseInfo;
                    }
                    if (count($databases) >= $this->limit) {
                        return $databases;
                    }
                }
            }
        }

        // Add databases from result facets
        foreach ($resultDatabases as $resultDatabase) {
            try {
                $name = $resultDatabase[$this->resultFacetNameKey];
            } catch (\Exception $ex) {
                $this->logError("Name key '$this->resultFacetNameKey' not found for database.");
                continue;
            }
            $databaseInfo = $nameToDatabase[$name] ?? null;
            if ($databaseInfo) {
                $databases[$databaseInfo['url']] = $databaseInfo;
            }
            if (count($databases) >= $this->limit) {
                return $databases;
            }
        }

        return $databases;
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
            $databases = $libGuides->getAZ();

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
