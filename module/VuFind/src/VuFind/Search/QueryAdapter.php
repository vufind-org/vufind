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
use Zend\StdLib\Parameters;

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
            return new Query(
                $search['l'], $handler, isset($search['o']) ? $search['o'] : null
            );
        } elseif (isset($search['g'])) {
            $operator = $search['g'][0]['b'];
            return new QueryGroup(
                $operator, array_map(['self', 'deminify'], $search['g'])
            );
        } else {
            // Special case: The outer-most group-of-groups.
            if (isset($search[0]['j'])) {
                $operator = $search[0]['j'];
                return new QueryGroup(
                    $operator, array_map(['self', 'deminify'], $search)
                );
            } else {
                // Simple query
                return new Query($search[0]['l'], $search[0]['i']);
            }
        }
    }

    /**
     * Convert a Query or QueryGroup into a human-readable display query.
     *
     * @param AbstractQuery $query     Query to convert
     * @param callable      $translate Callback to translate strings
     * @param callable      $showName  Callback to translate field names
     *
     * @return string
     */
    public static function display(AbstractQuery $query, $translate, $showName)
    {
        // Simple case -- basic query:
        if ($query instanceof Query) {
            return $query->getString();
        }

        // Complex case -- advanced query:
        return self::displayAdvanced($query, $translate, $showName);
    }

    /**
     * Support method for display() -- process advanced queries.
     *
     * @param AbstractQuery $query     Query to convert
     * @param callable      $translate Callback to translate strings
     * @param callable      $showName  Callback to translate field names
     *
     * @return string
     */
    protected static function displayAdvanced(AbstractQuery $query, $translate,
        $showName
    ) {
        // Groups and exclusions.
        $groups = $excludes = [];

        foreach ($query->getQueries() as $search) {
            if ($search instanceof QueryGroup) {
                $thisGroup = [];
                // Process each search group
                foreach ($search->getQueries() as $group) {
                    if ($group instanceof Query) {
                        // Build this group individually as a basic search
                        $thisGroup[]
                            = call_user_func($showName, $group->getHandler()) . ':'
                            . $group->getString();
                    } else {
                        throw new \Exception('Unexpected ' . get_class($group));
                    }
                }
                // Is this an exclusion (NOT) group or a normal group?
                $str = join(
                    ' ' . call_user_func($translate, $search->getOperator())
                    . ' ', $thisGroup
                );
                if ($search->isNegated()) {
                    $excludes[] = $str;
                } else {
                    $groups[] = $str;
                }
            } else {
                throw new \Exception('Unexpected ' . get_class($search));
            }
        }

        // Base 'advanced' query
        $operator = call_user_func($translate, $query->getOperator());
        $output = '(' . join(') ' . $operator . ' (', $groups) . ')';

        // Concatenate exclusion after that
        if (count($excludes) > 0) {
            $output .= ' ' . call_user_func($translate, 'NOT') . ' (('
                . join(') ' . call_user_func($translate, 'OR') . ' (', $excludes)
                . '))';
        }

        return $output;
    }

    /**
     * Convert user request parameters into a query (currently for advanced searches
     * only).
     *
     * @param Parameters $request        User-submitted parameters
     * @param string     $defaultHandler Default search handler
     *
     * @return Query|QueryGroup
     */
    public static function fromRequest(Parameters $request, $defaultHandler)
    {
        $groupCount = 0;
        $groups = [];

        // Loop through each search group
        while (!is_null($lookfor = $request->get("lookfor{$groupCount}"))) {
            $group = [];
            $lastBool = null;

            // Loop through each term inside the group
            for ($i = 0; $i < count($lookfor); $i++) {
                // Ignore advanced search fields with no lookup
                if ($lookfor[$i] != '') {
                    // Use default fields if not set
                    $typeArr = $request->get('type' . $groupCount);
                    $handler = (isset($typeArr[$i]) && !empty($typeArr[$i]))
                        ? $typeArr[$i] : $defaultHandler;

                    $opArr = $request->get('op' . $groupCount);
                    $operator = (isset($opArr[$i]) && !empty($opArr[$i]))
                        ? $opArr[$i] : null;

                    // Add term to this group
                    $boolArr = $request->get('bool' . $groupCount);
                    $lastBool = isset($boolArr[0]) ? $boolArr[0] : null;
                    $group[] = new Query($lookfor[$i], $handler, $operator);
                }
            }

            // Make sure we aren't adding groups that had no terms
            if (count($group) > 0) {
                // Add the completed group to the list
                $groups[] = new QueryGroup($lastBool, $group);
            }

            // Increment
            $groupCount++;
        }

        return (count($groups) > 0)
            ? new QueryGroup($request->get('join'), $groups)
            : new Query();
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
            return [
                [
                    'l' => $query->getString(),
                    'i' => $query->getHandler()
                ]
            ];
        }

        // Advanced query:
        $retVal = [];
        $operator = $query->isNegated() ? 'NOT' : $query->getOperator();
        foreach ($query->getQueries() as $current) {
            if ($topLevel) {
                $retVal[] = [
                    'g' => self::minify($current, false),
                    'j' => $operator
                ];
            } elseif ($current instanceof QueryGroup) {
                throw new \Exception('Not sure how to minify this query!');
            } else {
                $currentArr = [
                    'f' => $current->getHandler(),
                    'l' => $current->getString(),
                    'b' => $operator
                ];
                if (null !== ($op = $current->getOperator())) {
                    $currentArr['o'] = $op;
                }
                $retVal[] = $currentArr;
            }
        }
        return $retVal;
    }
}