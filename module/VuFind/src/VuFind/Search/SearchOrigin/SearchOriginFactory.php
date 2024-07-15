<?php

/**
 * Search Origin Object
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
 * @package  TODO?
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\SearchOrigin;

use Exception;

/**
 * Factory for search origin objects
 *
 * @category VuFind
 * @package  TODO?
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchOriginFactory
{
    /**
     * From a request return an AbstractSearchOrigin object
     *
     * @param array $params URL GET parameters
     *
     * @return AbstractSearchOrigin|null
     */
    public static function createObject(array $params): ?AbstractSearchOrigin
    {
        $nameParam = AbstractSearchOrigin::PARAM_NAME;
        if (empty($params[$nameParam])) {
            return null;
        }
        try {
            return match ($params[$nameParam]) {
                AlphaBrowseSearchOrigin::getName() => new AlphaBrowseSearchOrigin(
                    $params[AlphaBrowseSearchOrigin::SEARCH_SOURCE_PARAM] ?? null,
                    $params[AlphaBrowseSearchOrigin::SEARCH_FROM_PARAM] ?? null,
                    $params[AlphaBrowseSearchOrigin::SEARCH_PAGE_PARAM] ?? null
                ),
                default => null,
            };
        } catch (Exception) {
            return null;
        }
    }
}
