<?php

/**
 * ProQuest Federated Search Gateway Search Parameters
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search_ProQuestFSG
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\ProQuestFSG;

use VuFindSearch\ParamBag;

/**
 * ProQuest Federated Search Gateway Search Parameters
 *
 * @category VuFind
 * @package  Search_ProQuestFSG
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
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
        $backendParams->set('sortKey', empty($sort) ? 'relevance' : $sort);

        // Facets
        $backendParams->set('x-navigators', 'database');

        $filterList = $this->getFilterList();
        if (!empty($filterList)) {
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $value = explode('|', $filt['value'])[0];
                    $backendParams->add('filters', $filt['field'] . ':' . $value);
                }
            }
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
        $parts = explode('|', $value);
        return end($parts);
    }
}
