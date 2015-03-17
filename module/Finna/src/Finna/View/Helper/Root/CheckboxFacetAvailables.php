<?php
/**
 * Get available records when checkbox filter is active.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Get available records when checkbox filter is active.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class CheckboxFacetAvailables extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Return the count of records when checkbox filter is activated.
     * @param type   $checkboxFilter current checkbox filter
     * @param Params $searchClassId  search class id
     * @param type   $facetSet       facet get from query
     * @return int record count
     */
    public function getAvailableWithCbFacet($checkboxFilter, $searchClassId,
        $facetSet
    ) {
        if ($searchClassId == 'Primo') {
            foreach ($facetSet['tlevel']['list'] as $item) {
                if ($item['value'] == $checkboxFilter['desc']) {
                    return $item['count'];
                }
            }
        } else if ($searchClassId == 'Solr') {
            $filter = explode(':', $checkboxFilter['filter']);
            if (isset($facetSet[$filter[0]])) {
                return $facetSet[$filter[0]]['list'][0]['count'];
            }
        }

        return -1;
    }

}
