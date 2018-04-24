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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Search\Solr;

/**
 * Solr FacetCache.
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FacetCache extends \VuFind\Search\Base\FacetCache
{
    /**
     * Get the namespace to use for caching facets.
     *
     * @return string
     */
    protected function getCacheNamespace()
    {
        return 'solr-facets';
    }

    /**
     * Get the cache key for the provided method.
     *
     * @param string $initMethod Name of params method to use to request facets
     *
     * @return string
     */
    protected function getCacheKey($initMethod)
    {
        $params = $this->results->getParams();
        $hiddenFiltersHash = md5(json_encode($params->getHiddenFilters()));
        return $hiddenFiltersHash . '-' . parent::getCacheKey($initMethod);
    }
}
