<?php

/**
 * FacetCloud Recommendations Module
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Recommend;

use function is_callable;

/**
 * FacetCloud Recommendations Module
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FacetCloud extends ExpandFacets
{
    /**
     * Get the facet limit.
     *
     * @return int
     */
    public function getFacetLimit()
    {
        $params = $this->searchObject->getParams();
        $settings = is_callable([$params, 'getFacetSettings'])
            ? $params->getFacetSettings() : [];

        // Figure out the facet limit -- if available, we can use this to display
        // "..." when more facets are available than are currently being displayed,
        // although this comes at the cost of not being able to display the last
        // entry in the list -- otherwise we might show "..." when we've exactly
        // reached (but not exceeded) the facet limit. If we can't get a facet
        // limit, we will set an arbitrary high number so that all available values
        // will display and "..." will never display.
        return isset($settings['limit']) ? $settings['limit'] - 1 : 100000;
    }
}
