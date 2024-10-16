<?php

/**
 * Search query adapter
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search;

use Laminas\Stdlib\Parameters;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\WorkKeysQuery;

use function array_key_exists;
use function call_user_func;
use function count;

/**
 * Search query adapter
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class QueryAdapter implements QueryAdapterInterface
{
    /**
     * Return a Query or QueryGroup based on minified search arguments.
     *
     * @param array $search Minified search arguments
     *
     * @return Query|QueryGroup|WorkKeysQuery
     */
    public function deminify(array $search)
    {
        $type = $search['s'] ?? null;
        if ('w' === $type) {
            // WorkKeysQuery
            return new WorkKeysQuery($search['l'], $search['i'], $search['k'] ?? []);
        }
        // Use array_key_exists since null is also valid
        if ('b' === $type || array_key_exists('l', $search)) {
            // Basic search
            $handler = $search['i'] ?? $search['f'];
            return new Query(
                $search['l'] ?? '',
                $handler,
                $search['o'] ?? null
            );
        } elseif (isset($search['g'])) {
            $operator = $search['g'][0]['b'];
            return new QueryGroup(
                $operator,
                array_map([$this, 'deminify'], $search['g'])
            );
        } else {
            // Special case: The outer-most group-of-groups.
            if (isset($search[0]['j'])) {
                $operator = $search[0]['j'];
                return new QueryGroup(
                    $operator,
                    array_map([$this, 'deminify'], $search)
                );
            } else {
                // Simple query
                return new Query($search[0]['l'] ?? '', $search[0]['i']);
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
    public function display(AbstractQuery $query, $translate, $showName)
    {
        // Simple case -- basic query:
        if ($query instanceof Query) {
            return $query->getString();
        }

        // Work keys query:
        if ($query instanceof WorkKeysQuery) {
            return $translate('Versions') . ' - ' . ($query->getId() ?? '');
        }

        // Complex case -- advanced query:
        return $this->displayAdvanced($query, $translate, $showName);
    }

    /**
     * Support method for display() -- process advanced queries.
     *
     * @param QueryGroup $query     Query to convert
     * @param callable   $translate Callback to translate strings
     * @param callable   $showName  Callback to translate field names
     *
     * @return string
     */
    protected function displayAdvanced(
        QueryGroup $query,
        callable $translate,
        callable $showName
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
                        throw new \Exception('Unexpected ' . $group::class);
                    }
                }
                // Is this an exclusion (NOT) group or a normal group?
                $str = implode(
                    ' ' . call_user_func($translate, $search->getOperator())
                    . ' ',
                    $thisGroup
                );
                if ($search->isNegated()) {
                    $excludes[] = $str;
                } else {
                    $groups[] = $str;
                }
            } else {
                throw new \Exception('Unexpected ' . $search::class);
            }
        }

        // Base 'advanced' query
        $operator = call_user_func($translate, $query->getOperator());
        $output = '(' . implode(') ' . $operator . ' (', $groups) . ')';

        // Concatenate exclusion after that
        if (count($excludes) > 0) {
            $output .= ' ' . call_user_func($translate, 'NOT') . ' (('
                . implode(') ' . call_user_func($translate, 'OR') . ' (', $excludes)
                . '))';
        }

        return $output;
    }

    /**
     * Convert user request parameters into a query (currently for advanced searches
     * and work keys searches only).
     *
     * @param Parameters $request        User-submitted parameters
     * @param string     $defaultHandler Default search handler
     *
     * @return Query|QueryGroup|WorkKeysQuery
     */
    public function fromRequest(Parameters $request, $defaultHandler)
    {
        // Check for a work keys query first (id and keys included for back-compatibility):
        if (
            $request->get('search') === 'versions'
            || ($request->offsetExists('id') && $request->offsetExists('keys'))
        ) {
            if (null !== ($id = $request->offsetGet('id'))) {
                return new WorkKeysQuery($id, true);
            }
        }

        $groups = [];
        // Loop through all parameters and look for 'lookforX'
        foreach ($request as $key => $value) {
            if (!preg_match('/^lookfor(\d+)$/', $key, $matches)) {
                continue;
            }
            $groupId = $matches[1];
            $group = [];
            $lastBool = null;
            $value = (array)$value;

            // Loop through each term inside the group
            for ($i = 0; $i < count($value); $i++) {
                // Ignore advanced search fields with no lookup
                if ($value[$i] != '') {
                    // Use default fields if not set
                    $typeArr = (array)$request->get("type$groupId");
                    $handler = !empty($typeArr[$i]) ? $typeArr[$i] : $defaultHandler;

                    $opArr = (array)$request->get("op$groupId");
                    $operator = !empty($opArr[$i]) ? $opArr[$i] : null;

                    // Add term to this group
                    $boolArr = (array)$request->get("bool$groupId");
                    $lastBool = $boolArr[0] ?? 'AND';
                    $group[] = new Query($value[$i], $handler, $operator);
                }
            }

            // Make sure we aren't adding groups that had no terms
            if (count($group) > 0) {
                // Add the completed group to the list
                $groups[] = new QueryGroup($lastBool, $group);
            }
        }

        return (count($groups) > 0)
            ? new QueryGroup($request->get('join', 'AND'), $groups)
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
    public function minify(AbstractQuery $query, $topLevel = true)
    {
        // Simple query:
        if ($query instanceof Query) {
            return [
                [
                    'l' => $query->getString(),
                    'i' => $query->getHandler(),
                    's' => 'b',
                ],
            ];
        }

        // WorkKeys query:
        if ($query instanceof WorkKeysQuery) {
            return [
                'l' => $query->getId(),
                'i' => $query->getIncludeSelf(),
                'k' => $query->getWorkKeys(),
                's' => 'w',
            ];
        }

        // Advanced query:
        $retVal = [];
        $operator = $query->isNegated() ? 'NOT' : $query->getOperator();
        foreach ($query->getQueries() as $current) {
            if ($topLevel) {
                $retVal[] = [
                    'g' => $this->minify($current, false),
                    'j' => $operator,
                    's' => 'a',
                ];
            } elseif ($current instanceof QueryGroup) {
                throw new \Exception('Not sure how to minify this query!');
            } else {
                $currentArr = [
                    'f' => $current->getHandler(),
                    'l' => $current->getString(),
                    'b' => $operator,
                ];
                if (null !== ($op = $current->getOperator())) {
                    // Some search forms omit the operator for the first element;
                    // if we have an operator in a subsequent element, we should
                    // backfill a blank here for consistency; otherwise, VuFind
                    // may not construct correct search URLs.
                    if (isset($retVal[0]['f']) && !isset($retVal[0]['o'])) {
                        $retVal[0]['o'] = '';
                    }
                    $currentArr['o'] = $op;
                }
                $retVal[] = $currentArr;
            }
        }
        return $retVal;
    }
}
