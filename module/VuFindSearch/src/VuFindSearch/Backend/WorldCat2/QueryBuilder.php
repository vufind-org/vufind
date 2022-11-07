<?php

/**
 * WorldCat Search API v2 query builder.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
namespace VuFindSearch\Backend\WorldCat;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use VuFindSearch\Query\QueryGroup;

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
    /**
     * OCLC code to exclude from results
     *
     * @var string
     */
    protected $oclcCodeToExclude;

    /// Public API

    /**
     * Constructor
     *
     * @param string $exclude OCLC code to exclude from results
     */
    public function __construct($exclude = null)
    {
        $this->oclcCodeToExclude = $exclude;
    }

    /**
     * Return WorldCat search parameters based on a user query and params.
     *
     * @param AbstractQuery $query User query
     *
     * @return ParamBag
     */
    public function build(AbstractQuery $query)
    {
        // Build base query
        $queryStr = $this->abstractQueryToString($query);

        // Exclude current library from results (if applicable)
        if (null !== $this->oclcCodeToExclude) {
            $queryStr .= ' not srw.li all "' . $this->oclcCodeToExclude . '"';
        }

        // Send back results
        $params = new ParamBag();
        $params->set('query', $queryStr);
        return $params;
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
        if ($query instanceof Query) {
            return $this->queryToString($query);
        } else {
            return $this->queryGroupToString($query);
        }
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
                    $excludes[] = join(" OR ", $thisGroup);
                } else {
                    $groups[]
                        = join(" " . $params->getOperator() . " ", $thisGroup);
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
                .= "(" . join(") " . $query->getOperator() . " (", $groups) . ")";
        }
        // and concatenate exclusion after that
        if (count($excludes) > 0) {
            $queryStr .= " NOT ((" . join(") OR (", $excludes) . "))";
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
        $index = $query->getHandler();
        if (empty($index)) {
            // No handler?  Just accept query string as-is; no modifications needed.
            return $query->getString();
        }
        $lookfor = str_replace('"', '', $query->getString());

        // The index may contain multiple parts -- we want to search all listed index
        // fields:
        $index = explode(':', $index);
        $clauses = [];
        foreach ($index as $currentIndex) {
            $clauses[] = "{$currentIndex} all \"{$lookfor}\"";
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }
}
