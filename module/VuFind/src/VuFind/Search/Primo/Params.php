<?php

/**
 * Primo Central Search Parameters
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Primo;

use VuFindSearch\ParamBag;

use function in_array;

/**
 * Primo Central Search Parameters
 *
 * @category VuFind
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    /**
     * Config sections to search for facet labels if no override configuration
     * is set.
     *
     * @var array
     */
    protected $defaultFacetLabelSections
        = ['Advanced_Facets', 'FacetsTop', 'Facets'];

    /**
     * Config sections to search for checkbox facet labels if no override
     * configuration is set.
     *
     * @var array
     */
    protected $defaultFacetLabelCheckboxSections = ['CheckboxFacets'];

    /**
     * Mappings of specific Primo facet values (spelling errors and other special
     * cases present at least in CDI)
     *
     * @var array
     */
    protected $facetValueMappings = [
        'reference_entrys' => 'Reference Entries',
        'newsletterarticle' => 'Newsletter Articles',
        'archival_material_manuscripts' => 'Archival Materials / Manuscripts',
        'magazinearticle' => 'Magazine Articles',
    ];

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $backendParams = new ParamBag();

        // The "relevance" sort option is a VuFind reserved word; we need to make
        // this null in order to achieve the desired effect with Primo:
        $sort = $this->getSort();
        $finalSort = ($sort == 'relevance') ? null : $sort;
        $backendParams->set('sort', $finalSort);
        $backendParams->set('filterList', $this->getFilterSettings());
        if ($this->getOptions()->highlightEnabled()) {
            $backendParams->set('highlight', true);
            $backendParams->set('highlightStart', '{{{{START_HILITE}}}}');
            $backendParams->set('highlightEnd', '{{{{END_HILITE}}}}');
        }

        return $backendParams;
    }

    /**
     * Get a display text for a facet field.
     *
     * @param string $field Facet field
     * @param string $value Facet value
     *
     * @return string
     */
    public function getFacetValueRawDisplayText(string $field, string $value): string
    {
        return $this->fixPrimoFacetValue(
            parent::getFacetValueRawDisplayText($field, $value)
        );
    }

    /**
     * Normalize a Primo facet value.
     *
     * @param string $str String to normalize
     *
     * @return string
     */
    public function fixPrimoFacetValue($str)
    {
        if ($replacement = $this->facetValueMappings[$str] ?? '') {
            return $replacement;
        }
        return mb_convert_case(
            preg_replace('/_/u', ' ', $str),
            MB_CASE_TITLE,
            'UTF-8'
        );
    }

    /**
     * Return the current filters as an array
     *
     * @return array
     */
    public function getFilterSettings()
    {
        $result = [];
        $filterList = array_merge_recursive(
            $this->getHiddenFilters(),
            $this->filterList
        );
        foreach ($filterList as $field => $filter) {
            $facetOp = 'AND';
            $prefix = substr($field, 0, 1);
            if ('~' === $prefix || '-' === $prefix) {
                $facetOp = '~' === $prefix ? 'OR' : 'NOT';
                $field = substr($field, 1);
            }
            $result[] = [
                'field' => $field,
                'facetOp' => $facetOp,
                'values' => $filter,
            ];
        }
        return $result;
    }

    /**
     * Return an array structure containing information about all current filters.
     *
     * @param bool $excludeCheckboxFilters Should we exclude checkbox filters from
     * the list (to be used as a complement to getCheckboxFacets()).
     *
     * @return array                       Field, values and translation status
     */
    public function getFilterList($excludeCheckboxFilters = false)
    {
        $result = parent::getFilterList($excludeCheckboxFilters);
        if (isset($result['citing'])) {
            unset($result['citing']);
        }
        if (isset($result['citedby'])) {
            unset($result['citedby']);
        }
        return $result;
    }

    /**
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field   Facet field name.
     * @param string $value   Facet value.
     * @param string $default Default field name (null for default behavior).
     *
     * @return string         Human-readable description of field.
     */
    public function getFacetLabel($field, $value = null, $default = null)
    {
        if (in_array($field, ['citing', 'citedby'])) {
            return $field;
        }
        return parent::getFacetLabel($field, $value, $default);
    }
}
