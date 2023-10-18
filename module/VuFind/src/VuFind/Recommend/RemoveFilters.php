<?php

/**
 * RemoveFilters Recommendations Module
 * Recommends to remove filters
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Recommendations
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use function count;

/**
 * RemoveFilters Recommendations Module
 * Recommends to remove filters
 *
 * This class recommends to remove filters from a query to extend the result.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class RemoveFilters implements RecommendInterface
{
    /**
     * Search handler to try
     *
     * @var string
     */
    protected $activeFacetsCount = 0;

    /**
     * Search results object.
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $filters = $results->getParams()->getFilterList(false);
        foreach ($filters as $filter) {
            $this->activeFacetsCount += count($filter);
        }
        $this->results = $results;
    }

    /**
     * Determines if filters are applied or not.
     *
     * @return bool
     */
    public function hasFilters()
    {
        return $this->activeFacetsCount > 0;
    }

    /**
     * Get the URL for this query without filters.
     *
     * @return string
     */
    public function getFilterlessUrl()
    {
        return $this->results->getUrlQuery()->removeAllFilters()->getParams();
    }

    /**
     * Get the new search handler, or false if it does not apply.
     *
     * @return string
     */
    public function getActiveFacetsCount()
    {
        return $this->activeFacetsCount;
    }
}
