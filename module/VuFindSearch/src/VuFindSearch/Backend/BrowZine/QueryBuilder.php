<?php

/**
 * BrowZine QueryBuilder.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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

namespace VuFindSearch\Backend\BrowZine;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

/**
 * BrowZine QueryBuilder.
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
     * Return BrowZine search parameters based on a user query and params.
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
        // Send back results
        $q = $this->abstractQueryToArray($query);
        return new ParamBag(['query' => empty($q) ? '*' : $q]);
    }

    /// Internal API

    /**
     * Convert an AbstractQuery object to a query string.
     *
     * @param AbstractQuery $query Query to convert
     *
     * @return array
     */
    protected function abstractQueryToArray(AbstractQuery $query)
    {
        if ($query instanceof Query) {
            return $query->getString();
        } else {
            throw new \Exception('Query groups not supported');
        }
    }
}
