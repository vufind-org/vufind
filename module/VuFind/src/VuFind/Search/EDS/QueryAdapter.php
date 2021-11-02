<?php

/**
 * EDS API Query Adapter: search query parameters to AbstractQuery object
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\EDS;

use Laminas\Stdlib\Parameters;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * EDS API Query Adapter: search query parameters to AbstractQuery object
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class QueryAdapter extends \VuFind\Search\QueryAdapter
{
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
    protected static function displayAdvanced(
        AbstractQuery $query,
        $translate,
        $showName
    ) {
        $output = '';
        //There should only ever be 1 group with EDS queries.
        $all = [];
        foreach ($query->getQueries() as $search) {
            if ($search instanceof QueryGroup) {
                // Process each search group. There should only be 1 with EDS queries
                $groupQueries = $search->getQueries();
                for ($i = 0; $i < count($groupQueries); $i++) {
                    $group = $groupQueries[$i];
                    if ($group instanceof Query) {
                        // Build this group individually as a basic search
                        $queryOperator = $group->getOperator();
                        $op = (null != $queryOperator && 0 != $i) ?
                            call_user_func($translate, $queryOperator) . ' ' : '';
                        $all[] = $op
                            . call_user_func($showName, $group->getHandler()) . ':'
                            . $group->getString();
                    } else {
                        throw new \Exception('Unexpected ' . get_class($group));
                    }
                }
            } else {
                throw new \Exception('Unexpected ' . get_class($search));
            }
        }
        $output = '(' . join(' ', $all) . ')';

        return $output;
    }
}
