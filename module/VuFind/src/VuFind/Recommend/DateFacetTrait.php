<?php

/**
 * Trait for date facet processing.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Recommend
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Recommend;

use VuFind\Search\Base\Results;

/**
 * Trait for date facet processing.
 *
 * @category VuFind
 * @package  Recommend
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait DateFacetTrait
{
    /**
     * Extract details from applied filters.
     *
     * @param Results $results    Search result
     * @param array   $filters    Current filter list
     * @param array   $dateFacets Objects containing the date ranges
     *
     * @return array
     */
    protected function processDateFacets(Results $results, array $filters, array $dateFacets)
    {
        $result = [];
        foreach ($dateFacets as $current) {
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if (preg_match('/\[[\d\*]+ TO [\d\*]+\]/', $filter)) {
                        $range = explode(' TO ', trim($filter, '[]'));
                        $from = $range[0] == '*' ? '' : $range[0];
                        $to = $range[1] == '*' ? '' : $range[1];
                        $result[$current] = ['from' => $from, 'to' => $to];
                        break;
                    }
                }
            }
            $result[$current]['label'] = $results->getParams()->getFacetLabel($current);
        }
        return $result;
    }
}
