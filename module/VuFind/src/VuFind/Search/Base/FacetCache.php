<?php

/**
 * Abstract Base FacetCache.
 *
 * PHP version 8
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
use VuFind\Config\PluginManager as ConfigManager;
use VuFind\Search\Solr\HierarchicalFacetHelper;

use function in_array;

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
    use \VuFind\Log\VarDumperTrait;

    /**
     * Constructor
     *
     * @param Results                  $results                 Search results object
     * @param CacheManager             $cacheManager            Cache manager
     * @param string                   $language                Active UI language
     * @param ?HierarchicalFacetHelper $hierarchicalFacetHelper Hierarchical facet helper
     * @param ?ConfigManager           $configManager           Configuration manager
     */
    public function __construct(
        protected Results $results,
        protected CacheManager $cacheManager,
        protected $language = 'en',
        protected ?HierarchicalFacetHelper $hierarchicalFacetHelper = null,
        protected ?ConfigManager $configManager = null
    ) {
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
        return $this->language . md5($this->varDump($settings));
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
        if (!in_array($context, ['Advanced', 'HomePage', 'NewItems'])) {
            throw new \Exception('Invalid context: ' . $context);
        }
        // For now, all contexts are handled the same way.
        $facetList = $this->getFacetResults('init' . $context . 'Facets');

        // Temporary context-specific sort fix for Advanced and HomePage:
        if (in_array($context, ['Advanced', 'HomePage']) && $this->hierarchicalFacetHelper && $this->configManager) {
            $options = $this->results->getOptions();
            $facetConfig = $this->configManager->get($this->results->getOptions()->getFacetsIni())->toArray();
            $sortOptions = array_merge(
                $options->getHierarchicalFacetSortSettings(),
                $facetConfig[$context . '_Settings']['hierarchicalFacetSortOptions'] ?? []
            );
            $defaultSort = 'HomePage' === $context ? 'all' : 'top';
            foreach ($options->getHierarchicalFacets() as $facet) {
                if (!empty($facetList[$facet]['list'])) {
                    $this->hierarchicalFacetHelper->sortFacetList(
                        $facetList[$facet]['list'],
                        $sortOptions[$facet] ?? $sortOptions['*'] ?? $defaultSort,
                    );
                }
            }
        }
        return $facetList;
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
