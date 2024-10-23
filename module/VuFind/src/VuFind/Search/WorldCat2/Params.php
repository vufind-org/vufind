<?php

/**
 * WorldCat v2 Search Parameters
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Search_WorldCat2
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\WorldCat2;

use VuFindSearch\ParamBag;

/**
 * WorldCat v2 Search Parameters
 *
 * @category VuFind
 * @package  Search_WorldCat2
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    /**
     * Some facet fields are named differently than the corresponding filter parameters;
     * this map lists those exceptions.
     *
     * @var array
     */
    protected $facetToFilterFieldMap = [
        'language' => 'inLanguage',
    ];

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $backendParams = new ParamBag();

        // Sort
        $sort = $this->getSort();
        // Translate internal default/relevance sort to WorldCat equivalent:
        if (empty($sort) || $sort === 'relevance') {
            $sort = 'bestMatch';
        }
        $backendParams->set('orderBy', $sort);
        $backendParams->set('facets', array_keys($this->getFacetConfig()));
        $this->createBackendFilterParameters($backendParams);

        return $backendParams;
    }

    /**
     * Given a facet field name, return the filter parameter to use to apply the filter.
     *
     * @param string $field Name of facet field.
     *
     * @return string
     */
    protected function mapFacetFieldToFilterParam(string $field): string
    {
        return $this->facetToFilterFieldMap[$field] ?? $field;
    }

    /**
     * Set up filters based on VuFind settings.
     *
     * @param ParamBag $params Parameter collection to update
     *
     * @return void
     */
    protected function createBackendFilterParameters(ParamBag $params): void
    {
        // Which filters should be applied to our query?
        $filterList = $this->getFilterList();
        $hiddenFilterList = $this->getHiddenFilters();
        if (!empty($filterList)) {
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $params->add($this->mapFacetFieldToFilterParam($filt['field']), $filt['value']);
                }
            }
        }
        if (!empty($hiddenFilterList)) {
            foreach ($hiddenFilterList as $field => $hiddenFilters) {
                foreach ($hiddenFilters as $value) {
                    $params->add($this->mapFacetFieldToFilterParam($field), $value);
                }
            }
        }
    }
}
