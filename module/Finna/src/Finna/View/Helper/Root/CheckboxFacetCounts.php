<?php
/**
 * Get record counts for checkbox filters.
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
 * Get record counts for checkbox filters.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class CheckboxFacetCounts extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Return the count of records when checkbox filter is activated.
     *
     * @param array                       $checkboxFilter Checkbox filter
     * @param \VuFind\Search\Base\Results $results        Search result set
     *
     * @return int Record count
     */
    public function __invoke($checkboxFilter, $results)
    {
        $ret = 0;

        list($field, $value) = $results->getParams()
            ->parseFilter($checkboxFilter['filter']);
        $facets = $results->getFacetList([$field => $value]);

        if (isset($facets[$field])) {
            foreach ($facets[$field]['list'] as $item) {
                if ($item['value'] == $value
                    || (substr($value, -1) == '*'
                    && preg_match('/^' . $value . '/', $item['value']))
                    || ($item['value'] == 'true' && $value == '1')
                    || ($item['value'] == 'false' && $value == '0')
                ) {
                    $ret += $item['count'];
                }
            }
        }

        return $ret;
    }

}
