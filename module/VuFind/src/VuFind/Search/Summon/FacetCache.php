<?php
/**
 * Summon FacetCache.
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
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Search\Summon;

/**
 * Summon FacetCache.
 *
 * @category VuFind
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FacetCache extends \VuFind\Search\Base\FacetCache
{
    /**
     * Perform the actual facet lookup.
     *
     * @return array
     */
    protected function getFacetResults()
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = $this->cacheManager->getCache('object', 'summon-facets');
        $cacheKey = $this->language;
        if (!($list = $cache->getItem($cacheKey))) {
            $limit = $this->config->Advanced_Facet_Settings->facet_limit ?? 100;
            $params = $this->results->getParams();
            $facetsToShow = $this->config->Advanced_Facets
                 ?? ['Language' => 'Language', 'ContentType' => 'Format'];
            $orFields = isset($this->config->Advanced_Facet_Settings->orFacets)
                ? array_map(
                    'trim',
                    explode(',', $this->config->Advanced_Facet_Settings->orFacets)
                ) : [];
            foreach ($facetsToShow as $facet => $label) {
                $useOr = (($orFields[0] ?? '') == '*') 
                    || in_array($facet, $orFields);
                $params->addFacet(
                    $facet . ',or,1,' . $limit, $label, $useOr
                );
            }

            // We only care about facet lists, so don't get any results:
            $params->setLimit(0);

            // force processing for cache
            $list = $this->results->getFacetList();

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
        return $this->getFacetResults();
    }
}
