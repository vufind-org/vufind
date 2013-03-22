<?php

/**
 * Legacy adapter: search query parameters to AbstractQuery object
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

namespace VuFind\Search;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;

/**
 * Legacy adapter: search query parameters to AbstractQuery object
 *
 * The class is a intermediate solution to translate the (possibly modified)
 * search query parameters in an object required by the new search system.
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
abstract class QueryAdapter
{
    /**
     * Return a Query or QueryGroup based on user search arguments.
     *
     * @param array $search Search arguments
     *
     * @return Query|QueryGroup
     */
    public static function create(array $search)
    {
        if (isset($search['lookfor'])) {
            $handler = isset($search['index']) ? $search['index'] : $search['field'];
            return new Query($search['lookfor'], $handler);
        } elseif (isset($search['group'])) {
            $operator = $search['group'][0]['bool'];
            return new QueryGroup($operator, array_map(array('self', 'create'), $search['group']));
        } else {
            // Special case: The outer-most group-of-groups.
            if (isset($search[0]['join'])) {
                $operator = $search[0]['join'];
                return new QueryGroup($operator, array_map(array('self', 'create'), $search));
            } else {
                // Simple query
                return new Query($search[0]['lookfor'], $search[0]['index']);
            }
        }
    }

    /**
     * Return a Query or QueryGroup based on minified search arguments.
     *
     * @param array $search Minified search arguments
     *
     * @return Query|QueryGroup
     */
    public static function deminify(array $search)
    {
        if (isset($search['l'])) {
            $handler = isset($search['i']) ? $search['i'] : $search['f'];
            return new Query($search['l'], $handler);
        } elseif (isset($search['g'])) {
            $operator = $search['g'][0]['b'];
            return new QueryGroup($operator, array_map(array('self', 'deminify'), $search['g']));
        } else {
            // Special case: The outer-most group-of-groups.
            if (isset($search[0]['j'])) {
                $operator = $search[0]['j'];
                return new QueryGroup($operator, array_map(array('self', 'deminify'), $search));
            } else {
                // Simple query
                return new Query($search[0]['l'], $search[0]['i']);
            }
        }
    }

    /**
     * Convert a Query or QueryGroup into minified search arguments.
     *
     * @param AbstractQuery $query    Query to minify
     * @param bool          $topLevel Is this a top-level query? (Used for recursion)
     *
     * @return array
     */
    public static function minify(AbstractQuery $query, $topLevel = true)
    {
        // Simple query:
        if ($query instanceof Query) {
            return array(
                array(
                    'l' => $query->getString(),
                    'i' => $query->getHandler()
                )
            );
        }

        // Advanced query:
        $retVal = array();
        $operator = $query->isNegated() ? 'NOT' : $query->getOperator();
        foreach ($query->getQueries() as $current) {
            if ($topLevel) {
                $retVal[] = array(
                    'g' => self::minify($current, false),
                    'j' => $operator
                );
            } elseif ($current instanceof QueryGroup) {
                throw new \Exception('Not sure how to minify this query!');
            } else {
                $retVal[] = array(
                    'f' => $current->getHandler(),
                    'l' => $current->getString(),
                    'b' => $operator
                );
            }
        }
        return $retVal;
    }
}