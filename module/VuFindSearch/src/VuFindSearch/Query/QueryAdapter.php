<?php

/**
 * QueryAdapter class file.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category Search
 * @package  Query
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */

namespace VuFindSearch\Query;

/**
 * Adapter for legacy query representation.
 *
 * @category Search
 * @package  Query
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */

abstract class QueryAdapter
{

    /**
     * Return a Query or QueryGroup based on user search arguments.
     *
     * @param array $search Search arguments
     *
     * @return \VuFind\Search\Query|\VuFind\Search\QueryGroup
     */
    public static function create (array $search)
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
}