<?php
/**
 * Related Records: Solr-based work expressions
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2009.
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
namespace Finna\Related;

/**
 * Related Records: Solr-based work expressions
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class WorkExpressions implements \VuFind\Related\RelatedInterface
{
    /**
     * Work expressions
     *
     * @var array
     */
    protected $results;

    /**
     * Total count
     *
     * @var int
     */
    protected $resultCount;

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Search configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $searchConfig;

    /**
     * Record ID
     *
     * @var string
     */
    protected $recordId;

    /**
     * Work keys
     *
     * @var array
     */
    protected $workKeys;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service  $search       Search service
     * @param \Laminas\Config\Config $searchConfig Search configuration
     */
    public function __construct(\VuFindSearch\Service $search,
        \Laminas\Config\Config $searchConfig
    ) {
        $this->searchService = $search;
        $this->searchConfig = $searchConfig;
    }

    /**
     * Establishes base settings for making recommendations.
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        $this->recordId = $driver->getUniqueID();
        if (($this->workKeys = $driver->tryMethod('getWorkKeys'))
            && $driver->getSourceIdentifier() === 'Solr'
        ) {
            $params = new \VuFindSearch\ParamBag();
            $params->add('rows', $this->getResultMoreLimit());
            $results = $this->searchService->workExpressions(
                $driver->getSourceIdentifier(),
                $driver->getUniqueID(),
                $this->workKeys,
                $params
            );
            $this->results = $results->getRecords();
            $this->resultCount = $results->getTotal();
        } else {
            $this->results = [];
            $this->resultCount = 0;
        }
    }

    /**
     * Get an array of Record Driver objects representing the other expressions of
     * the record passed to the constructor.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get the total number of results.
     *
     * @return int
     */
    public function getResultCount()
    {
        return $this->resultCount;
    }

    /**
     * Get the number of results to be displayed by default
     *
     * @return int
     */
    public function getResultLimit()
    {
        return $this->searchConfig->WorkExpressions->count ?? 5;
    }

    /**
     * Get the number of results to be displayed with the more link
     *
     * @return int
     */
    public function getResultMoreLimit()
    {
        return $this->searchConfig->WorkExpressions->showMoreCount ?? 30;
    }

    /**
     * Get parameters for a search URL that display all work expressions
     *
     * @return string
     */
    public function getSearchUrlParams()
    {
        $mapFunc = function ($val) {
            return addcslashes($val, '"');
        };
        $imploded = implode('" OR "', array_map($mapFunc, $this->workKeys));
        $query = [
            'join' => 'AND',
            'lookfor0[]' => "\"$imploded\"",
            'type0[]' => 'WorkKeys',
            'bool0[]' => 'AND'
        ];
        return http_build_query($query);
    }
}
