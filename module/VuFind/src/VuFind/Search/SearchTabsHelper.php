<?php
/**
 * "Search tabs" helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Search;
use VuFind\Search\Results\PluginManager, Zend\View\Helper\Url, Zend\Http\Request;

/**
 * "Search tabs" helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchTabsHelper extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Search manager
     *
     * @var PluginManager
     */
    protected $results;

    /**
     * Tab configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Tab filter configuration
     *
     * @var array
     */
    protected $filters;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param PluginManager $results Search results plugin manager
     * @param array         $config  Tab configuration
     * @param array         $filters Tab filter configuration
     * @param Request       $request Request
     */
    public function __construct(PluginManager $results, array $config,
        array $filters, Request $request
    ) {
        $this->results = $results;
        $this->config = $config;
        $this->filters = $filters;
        $this->request = $request;
    }

    /**
     * Get an array of currently active hidden filters
     *
     * @param string $searchClassId Active search class
     *
     * @return array
     */
    public function getCurrentHiddenFilters($searchClassId)
    {
        $filters = $this->request->getQuery('hiddenFilters');
        return null === $filters
            ? [] : $this->parseFilters($searchClassId, $filters);
    }

    /**
     * Get an array of hidden filters for the default tab of the given search class
     *
     * @param string $searchClassId Search class
     *
     * @return array
     */
    public function getDefaultTabHiddenFilters($searchClassId)
    {
        if (empty($this->config)) {
            return [];
        }

        $firstTab = null;
        foreach ($this->config as $key => $label) {
            $class = $this->extractClassName($key);
            if ($class == $searchClassId) {
                if (null === $firstTab) {
                    $firstTab = $key;
                }
                if (empty($this->filters[$key])) {
                    return [];
                }
            }
        }
        if (null === $firstTab || empty($this->filters[$firstTab])) {
            return [];
        }

        return $this->parseFilters($searchClassId, (array)$this->filters[$firstTab]);
    }

    /**
     * Extract search class name from a tab id
     *
     * @param string $tabId Tab id as defined in config.ini
     *
     * @return string
     */
    public function extractClassName($tabId)
    {
        list($class) = explode(':', $tabId, 2);
        return $class;
    }

    /**
     * Check if given hidden filters match with the hidden filters from configuration
     *
     * @param string $class         Search class ID
     * @param string $hiddenFilters Hidden filters
     * @param string $configFilters Filters from filter configuration
     *
     * @return boolean
     */
    public function filtersMatch($class, $hiddenFilters, $configFilters)
    {
        $compare = $this->parseFilters($class, $configFilters);
        return $hiddenFilters == $this->parseFilters($class, $configFilters);
    }

    /**
     * Parse a simple filter array to a keyed array
     *
     * @param string $class   Search class ID
     * @param array  $filters Filters to parse
     *
     * @return array
     */
    protected function parseFilters($class, $filters)
    {
        $results = $this->results->get($class);
        $params = $results->getParams();
        $result = [];
        foreach ($filters as $filter) {
            list($field, $value) = $params->parseFilter($filter);
            $result[$field][] = $value;
        }
        return $result;
    }
}
