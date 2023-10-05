<?php

/**
 * EDS Databases Recommendations Module
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

use Laminas\Cache\Storage\Adapter\AbstractAdapter as CacheAdapter;
use Laminas\Config\Config;
use VuFind\Connection\LibGuides;

use function intval;

/**
 * EDSDatabases Recommendations Module
 *
 * This class displays a list of external links to the research databases represented
 * by EDS results.  (Unlike the EDS ContentProvider facet that would narrow down the
 * results within VuFind.)
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class EDSDatabases implements RecommendInterface
{
    use \VuFind\Cache\CacheTrait;

    /**
     * Results object
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
        $this->cacheLifetime = intval($config->GetAZ->cache_lifetime ?? 600);
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
        $records = $this->results->getResults();
        $nameToDatabase = $this->getLibGuidesDatabases();
        $databases = [];
        foreach ($records as $record) {
            $databaseInfo = $nameToDatabase[$record->getDbLabel()] ?? null;
            if ($databaseInfo) {
                $databases[$record->getDbLabel()] = $databaseInfo;
            }
        }
        return $databases;
    }

    /**
     * Load or retrieve from the cache the list of LibGuides A-Z databases.
     *
     * @return array An array mapping a database name to the full object
     * from the LibGuides /az API.
     */
    protected function getLibGuidesDatabases()
    {
        $nameToDatabase = $this->getCachedData('libGuidesAZ-nameToDatabase');
        if (empty($nameToDatabase)) {
            $databases = $this->libGuides->getAZ();

            $nameToDatabase = [];
            foreach ($databases as $database) {
                $nameToDatabase[$database->name] = $database;
            }

            $this->putCachedData('libGuidesAZ-nameToDatabase', $nameToDatabase);
        }
        return $nameToDatabase;
    }
}
