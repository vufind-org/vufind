<?php

/**
 * Trait for sorting results from the resource table.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service\Feature;

use function in_array;

/**
 * Trait for sorting results from the resource table.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
trait ResourceSortTrait
{
    /**
     * Apply a sort parameter to a query on the resource table. Returns an
     * array with two keys: 'orderByClause' (the actual ORDER BY) and
     * 'extraSelect' (extra values to add to SELECT, if necessary)
     *
     * @param string $sort  Field to use for sorting (may include
     *                      'desc' qualifier)
     * @param string $alias Alias to the resource table (defaults to 'r')
     *
     * @return array
     */
    protected function getResourceOrderByClause(string $sort, string $alias = 'r'): array
    {
        // Apply sorting, if necessary:
        $legalSorts = [
            'title', 'title desc', 'author', 'author desc', 'year', 'year desc', 'last_saved', 'last_saved desc',
        ];
        $orderByClause = $extraSelect = '';
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            // Strip off 'desc' to obtain the raw field name -- we'll need it
            // to sort null values to the bottom:
            $parts = explode(' ', $sort);
            $rawField = trim($parts[0]);

            // Start building the list of sort fields:
            $order = [];

            // Only include the table alias on non-virtual fields:
            $fieldPrefix = (strtolower($rawField) === 'last_saved') ? '' : "$alias.";

            // The title field can't be null, so don't bother with the extra
            // isnull() sort in that case.
            if (strtolower($rawField) === 'title') {
                // Do nothing
            } elseif (strtolower($rawField) === 'last_saved') {
                $extraSelect = 'ur.saved AS HIDDEN last_saved, '
                    . 'CASE WHEN ur.saved IS NULL THEN 1 ELSE 0 END AS HIDDEN last_savedsort';
                $order[] = 'last_savedsort';
            } else {
                $extraSelect = 'CASE WHEN ' . $fieldPrefix . $rawField . ' IS NULL THEN 1 ELSE 0 END AS HIDDEN '
                    . $rawField . 'sort';
                $order[] = "{$rawField}sort";
            }

            // Apply the user-specified sort:
            $order[] = $fieldPrefix . $sort;
            // Inject the sort preferences into the query object:
            $orderByClause = ' ORDER BY ' . implode(', ', $order);
        }
        return compact('orderByClause', 'extraSelect');
    }
}
