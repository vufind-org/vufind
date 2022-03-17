<?php
/**
 * Primo Central Search Parameters
 *
 * PHP version 7
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\Primo;

use VuFindSearch\ParamBag;

/**
 * Primo Central Search Parameters
 *
 * @category VuFind
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
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
     * Format a single filter for use in getFilterList().
     *
     * @param string $field     Field name
     * @param string $value     Field value
     * @param string $operator  Operator (AND/OR/NOT)
     * @param bool   $translate Should we translate the label?
     *
     * @return array
     */
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        $result
            = parent::formatFilterListEntry($field, $value, $operator, $translate);
        if (!$translate) {
            $result['displayText']
                = $this->fixPrimoFacetValue($result['displayText']);
        }
        return $result;
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
        // Special case: odd spelling error in Primo results:
        if ($str == 'reference_entrys') {
            return 'Reference Entries';
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
            $result[$field] = [
                'facetOp' => $facetOp,
                'values' => $filter
            ];
        }
        return $result;
    }
}
