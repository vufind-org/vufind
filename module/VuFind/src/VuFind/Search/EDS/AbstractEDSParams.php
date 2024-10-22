<?php

/**
 * Common EDS & EPF API Params
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
 * Copyright (C) The National Library of Finland 2022
 * Copyright (C) Villanova University 2023
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
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\EDS;

use VuFindSearch\ParamBag;

use function in_array;

/**
 * Common EDS & EPF API Params
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AbstractEDSParams extends \VuFind\Search\Base\Params
{
    /**
     * Fields that the EDS API will always filter multiple values using OR, not AND.
     *
     * @var array
     */
    protected $forcedOrFields = [
    ];

    /**
     * Set up filters based on VuFind settings.
     *
     * @param ParamBag $params Parameter collection to update
     *
     * @return void
     */
    public function createBackendFilterParameters(ParamBag $params)
    {
        // Which filters should be applied to our query?
        $filterList = $this->getFilterList();
        $hiddenFilterList = $this->getHiddenFilters();
        if (!empty($filterList)) {
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $fq = $filt['field']
                        . ($this->filterRequiresFacetOperator($filt['field']) ?
                            ":{$this->getFacetOperator($filt['field'], $filt['operator'])}" : '')
                        . ":{$filt['value']}";
                    $params->add('filters', $fq);
                }
            }
        }
        if (!empty($hiddenFilterList)) {
            foreach ($hiddenFilterList as $field => $hiddenFilters) {
                foreach ($hiddenFilters as $value) {
                    $hfq = $field
                        . ($this->filterRequiresFacetOperator($field) ?
                            ":{$this->getFacetOperator($field)}" : '')
                        . ":{$value}";
                    $params->add('filters', $hfq);
                }
            }
        }
    }

    /**
     * Determines if the given filter field is a normal one, which should include the AND/OR operator,
     * or a special filter which should not.
     *
     * @param string $field Filter field name
     *
     * @return boolean
     */
    protected function filterRequiresFacetOperator($field)
    {
        if (
            str_starts_with($field, 'LIMIT') ||
            str_starts_with($field, 'EXPAND') ||
            str_starts_with($field, 'SEARCHMODE') ||
            str_starts_with($field, 'PublicationDate')
        ) {
            return false;
        }
        return true;
    }

    /**
     * Return the value for which search view we use
     *
     * @return string
     */
    public function getView()
    {
        $viewArr = explode('|', $this->view ?? '');
        return $viewArr[0];
    }

    /**
     * Get facet operator for the specified field
     *
     * @param string $field             Field name
     * @param string $specifiedOperator Operator specified on a config filter line
     *
     * @return string
     */
    public function getFacetOperator($field, $specifiedOperator = null)
    {
        if ($specifiedOperator && $specifiedOperator != 'AND') {
            return $specifiedOperator;
        }
        if (in_array($field, $this->forcedOrFields)) {
            return 'OR';
        }
        return parent::getFacetOperator($field);
    }
}
