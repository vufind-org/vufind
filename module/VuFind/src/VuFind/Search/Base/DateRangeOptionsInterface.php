<?php

/**
 * Interface for date range options
 *
 * Copyright (C) The National Library of Finland 2025.
 *
 * PHP version 8
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
 * @package  Search_Base
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Base;

/**
 * Interface for date range options
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface DateRangeOptionsInterface
{
    /**
     * Get date range facets.
     *
     * @return array
     */
    public function getDateRangeFacets(): array;

    /**
     * Get full (day/month/year, rather than just year) date range facets.
     *
     * @return array
     */
    public function getFullDateRangeFacets(): array;

    /**
     * Get date range field types in the search index.
     *
     * @return array
     */
    public function getDateRangeFieldTypes(): array;
}
