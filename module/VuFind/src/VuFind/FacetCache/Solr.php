<?php
/**
 * Solr FacetCache.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  FacetCache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\FacetCache;

use VuFind\Cache\Manager as CacheManager;
use VuFind\Search\Solr\Results;

/**
 * Solr FacetCache Factory.
 *
 * @category VuFind
 * @package  FacetCache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Solr
{
    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Currently selected language
     *
     * @var string
     */
    protected $language;

    /**
     * Search results object.
     *
     * @var Results
     */
    protected $results;

    /**
     * Constructor
     *
     * @param Results      $r        Search results object
     * @param CacheManager $cm       Cache manager
     * @param string       $language Active UI language
     */
    public function __construct(Results $r, CacheManager $cm, $language = 'en')
    {
        $this->results = $r;
        $this->cacheManager = $cm;
        $this->language = $language;
    }

    /**
     * Return a Search Results object containing requested facet information.  This
     * data may come from the cache.
     *
     * @param string $initMethod Name of params method to use to request facets
     *
     * @return array
     */
    protected function getFacetResults($initMethod)
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = $this->cacheManager->getCache('object', 'solr-facets');
        $params = $this->results->getParams();
        $hiddenFiltersHash = md5(json_encode($params->getHiddenFilters()));
        $cacheName = "{$initMethod}-{$hiddenFiltersHash}-{$this->language}";
        if (!($list = $cache->getItem($cacheName))) {
            $params->$initMethod();

            // Avoid a backend request if there are no facets configured by the given
            // init method.
            if (!empty($params->getFacetConfig())) {
                // We only care about facet lists, so don't get any results (this
                // helps prevent problems with serialized File_MARC objects in the
                // cache):
                $params->setLimit(0);
                $list = $this->results->getFacetList();
            } else {
                $list = [];
            }
            $cache->setItem($cacheName, $list);
        }

        return $list;
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache.
     *
     * @return array
     */
    public function getAdvancedList()
    {
        return $this->getFacetResults('initAdvancedFacets');
    }

    /**
     * Return a Search Results object containing homepage facet information.  This
     * data may come from the cache.
     *
     * @return array
     */
    public function getHomePageList()
    {
        return $this->getFacetResults('initHomePageFacets');
    }

    /**
     * Get results object used to retrieve facets.
     *
     * @return Results
     */
    public function getResults()
    {
        return $this->results;
    }
}