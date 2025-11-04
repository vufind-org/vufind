<?php

/**
 * Additional functionality for Finna SideFacets
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2015.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace Finna\Recommend;

use VuFind\Solr\Utils as SolrUtils;

/**
 * Additional functionality for Finna SideFacets
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait SideFacetsTrait
{
    /**
     * New items facet configuration
     *
     * @var array
     */
    protected $newItemsFacets = [];

    /**
     * Get new items facets (facet titles)
     *
     * @return array
     */
    public function getNewItemsFacets()
    {
        if (!isset($this->newItemsFacets)) {
            return [];
        }

        $filters = $this->results->getParams()->getRawFilters();
        $result = [];
        foreach ($this->newItemsFacets as $current) {
            $from = '';
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if ($range = SolrUtils::parseRange($filter)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        break;
                    }
                }
            }
            $result[$current] = [
                'raw' => $from,
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}/', $from)
                    ? substr($from, 0, 10) : str_replace('/DAY', '', $from),
            ];
        }
        return $result;
    }
}
