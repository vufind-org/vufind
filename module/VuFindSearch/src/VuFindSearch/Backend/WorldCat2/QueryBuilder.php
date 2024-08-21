<?php

/**
 * WorldCat Search API v2 query builder.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\WorldCat2;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

use function count;

/**
 * WorldCat Search API v2 query builder.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilder
{
    /// Public API

    /**
     * Constructor
     *
     * @param ?string $oclcCodeToExclude OCLC code to exclude from results
     */
    public function __construct(protected $oclcCodeToExclude = null)
    {
    }

    /**
     * Return WorldCat search parameters based on a user query and params.
     *
     * @param AbstractQuery $query  User query
     * @param ?ParamBag     $params Search backend parameters
     *
     * @return ParamBag
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function build(AbstractQuery $query, ?ParamBag $params = null)
    {
        // Build base query
        $queryStr = $this->abstractQueryToString($query);
        if ($this->oclcCodeToExclude) {
            $queryStr = "($queryStr) NOT li:{$this->oclcCodeToExclude}";
        }

        // Send back results
        $newParams = new ParamBag();
        $newParams->set('q', $queryStr);
        return $newParams;
    }

    /// Internal API

    /**
     * Convert an AbstractQuery object to a query string.
     *
     * @param AbstractQuery $query Query to convert
     *
     * @return string
     */
    protected function abstractQueryToString(AbstractQuery $query)
    {
        return $query instanceof Query
            ? $this->queryToString($query)
            : $this->queryGroupToString($query);
    }

    /**
     * Convert a QueryGroup object to a query string.
     *
     * @param QueryGroup $query QueryGroup to convert
     *
     * @return string
     */
    protected function queryGroupToString(QueryGroup $query)
    {
        $groups = $excludes = [];

        foreach ($query->getQueries() as $params) {
            // Advanced Search
            if ($params instanceof QueryGroup) {
                $thisGroup = [];
                // Process each search group
                foreach ($params->getQueries() as $group) {
                    // Build this group individually as a basic search
                    $thisGroup[] = $this->abstractQueryToString($group);
                }
                // Is this an exclusion (NOT) group or a normal group?
                if ($params->isNegated()) {
                    $excludes[] = implode(' OR ', $thisGroup);
                } else {
                    $groups[]
                        = implode(' ' . $params->getOperator() . ' ', $thisGroup);
                }
            } else {
                // Basic Search
                $groups[] = $this->queryToString($params);
            }
        }

        // Put our advanced search together
        $queryStr = '';
        if (count($groups) > 0) {
            $queryStr
                .= '(' . implode(') ' . $query->getOperator() . ' (', $groups) . ')';
        }
        // and concatenate exclusion after that
        if (count($excludes) > 0) {
            $queryStr .= ' NOT ((' . implode(') OR (', $excludes) . '))';
        }

        return $queryStr;
    }

    /**
     * Convert a single Query object to a query string.
     *
     * @param Query $query Query to convert
     *
     * @return string
     */
    protected function queryToString(Query $query)
    {
        // Clean and validate input:
        $indexParts = explode(':', $query->getHandler() ?? 'kw');
        $lookfor = $query->getString();

        // Prepend the index name:
        $parts = [];
        foreach ($indexParts as $index) {
            $parts[] = "{$index}:($lookfor)";
        }

        return count($parts) > 1 ? '(' . implode(' OR ', $parts) . ')' : $parts[0];
    }
}
