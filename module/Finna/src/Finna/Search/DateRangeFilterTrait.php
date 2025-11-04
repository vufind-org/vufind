<?php

/**
 * Additional functionality for date range facet
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Search
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Search;

use function in_array;
use function is_array;
use function strlen;

/**
 * Additional functionality for date range facet.
 *
 * @category VuFind
 * @package  Search
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait DateRangeFilterTrait
{
    /**
     *  Date range index field (VuFind1)
     *
     * @var string
     */
    public string $spatialDateRangeFieldVF1 = 'search_sdaterange_mv';

    /**
     * Date range index field type (VuFind1)
     *
     * @var string
     */
    public string $spatialDateRangeFieldTypeVF1 = 'search_sdaterange_mvtype';

    /**
     * Default date range type value
     *
     * @var string
     */
    public string $spatialDateRangeDefaultType = 'overlap';

    /**
     * Does the object already contain the specified filter?
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addFilter($filter): void
    {
        // Extract field and value from URL string:
        [$field, ] = $this->parseFilter($filter);

        if (
            $field == $this->getDateRangeSearchField()
            || $field == $this->spatialDateRangeFieldVF1
        ) {
            // Date range filters are processed
            // separately (see initSpatialDateRangeFilter)
            return;
        }
        parent::addFilter($filter);
    }

    /**
     * Return current date range filter.
     *
     * @return mixed false|array Filter
     */
    public function getDateRangeFilter(): mixed
    {
        $filterList = $this->getFilterList();
        foreach ($filterList as $filters) {
            foreach ($filters as $filter) {
                if ($this->isDateRangeFilter($filter['field'])) {
                    return $filter;
                }
            }
        }
        return false;
    }

    /**
     * Return the current filters as an array of strings ['field:filter']
     *
     * @return array $filterQuery
     */
    public function getFilterSettings(): array
    {
        $result = parent::getFilterSettings();

        // Special processing for date range filters
        $dateRangeField = $this->getDateRangeSearchField();
        if (!$dateRangeField) {
            return $result;
        }
        foreach ($result as &$filter) {
            $dateRange = strncmp(
                $filter,
                "$dateRangeField:",
                strlen($dateRangeField) + 1
            ) == 0;
            if ($dateRange) {
                [, $value] = $this->parseFilter($filter);
                [$op, $range] = explode('|', $value);
                $op = $op == 'within' ? 'Within' : 'Intersects';
                $filter = "{!field f=$dateRangeField op=$op}$range";
            }
        }
        return $result;
    }

    /**
     * Initialize date range filter (search_daterange_mv)
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initSpatialDateRangeFilter($request): void
    {
        $dateRangeField = $this->getDateRangeSearchField();
        if (!$dateRangeField) {
            return;
        }
        $type = $request->get("{$dateRangeField}_type");
        if (!$type) {
            // VuFind 1
            $type = $request->get($this->spatialDateRangeFieldTypeVF1);
        }
        if (!$type) {
            $type = $this->spatialDateRangeDefaultType;
        }

        $from = $to = null;
        $found = false;
        // Date range filter
        if (($reqFilters = $request->get('filter')) && is_array($reqFilters)) {
            foreach ($reqFilters as $f) {
                [$field, ] = $this->parseFilter($f);
                if (
                    in_array($field, [$dateRangeField, $this->spatialDateRangeFieldVF1])
                    && $range = $this->parseDateRangeFilter($f)
                ) {
                    $from = $range['from'];
                    $to = $range['to'];
                    if (
                        isset($range['type'])
                        && $range['type'] !== $this->spatialDateRangeDefaultType
                    ) {
                        $type = $range['type'];
                    }
                    $found = true;
                    break;
                }
            }
        }

        // Uninitialized VuFind1 date range query
        if (!$found && $request->get('sdaterange')) {
            // Search for VuFind1 search_sdaterange_mvfrom, search_sdaterange_mvto
            $from = $request->get('search_sdaterange_mvfrom');
            $to = $request->get('search_sdaterange_mvto');
            if ($from || $to) {
                if (!$from) {
                    $from = -9999;
                }
                if (!$to) {
                    $to = 9999;
                }
                $found = true;
            }
        }

        if (!$found) {
            return;
        }

        // Add filter. The final Solr filter is constructed in getFilterSettings.
        $filter = "$dateRangeField:$type|[$from TO $to]";
        parent::addFilter($filter);
    }
}
