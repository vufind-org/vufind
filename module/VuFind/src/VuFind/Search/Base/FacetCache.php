<?php

/**
 * Abstract Base FacetCache.
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Search\Base;

use VuFind\Cache\Manager as CacheManager;

/**
 * Solr FacetCache Factory.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class FacetCache
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
     * Get the namespace to use for caching facets.
     *
     * @return string
     */
    abstract protected function getCacheNamespace();

    /**
     * Get the cache key for the provided method.
     *
     * @return string
     */
    protected function getCacheKey()
    {
        $params = $this->results->getParams();
        $facetConfig = $params->getFacetConfig();
        $settings = [
            $facetConfig,
            $params->getHiddenFilters(),
            // Factor operator settings into cache key:
            array_map([$params, 'getFacetOperator'], array_keys($facetConfig)),
        ];
        return $this->language . md5(print_r($settings, true));
    }

    /**
     * Perform the actual facet lookup.
     *
     * @param string $initMethod Name of params method to use to request facets
     *
     * @return array
     */
    protected function getFacetResults($initMethod)
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = $this->cacheManager->getCache('object', $this->getCacheNamespace());
        $params = $this->results->getParams();

        // Note that we need to initialize the parameters BEFORE generating the
        // cache key to ensure that the key is based on the proper settings.
        $params->$initMethod();
        $cacheKey = $this->getCacheKey();
        if (!($list = $cache->getItem($cacheKey))) {
            // Avoid a backend request if there are no facets configured by the given
            // init method.
            if (!empty($params->getFacetConfig())) {
                // We only care about facet lists, so don't get any results (this
                // improves performance):
                $params->setLimit(0);
                $list = $this->results->getFacetList();
            } else {
                $list = [];
            }
            $cache->setItem($cacheKey, $list);
        }

        return $list;
    }

    /**
     * Return facet information. This data may come from the cache.
     *
     * @param string $context Context of list to retrieve ('Advanced' or 'HomePage')
     *
     * @return array
     */
    public function getList($context = 'Advanced')
    {
        if (!in_array($context, ['Advanced', 'HomePage'])) {
            throw new \Exception('Invalid context: ' . $context);
        }
        // For now, all contexts are handled the same way.
        return $this->getFacetResults('init' . $context . 'Facets');
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
