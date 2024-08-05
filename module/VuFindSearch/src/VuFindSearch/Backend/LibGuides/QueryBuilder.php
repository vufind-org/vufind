<?php

/**
 * LibGuides QueryBuilder.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\LibGuides;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * LibGuides QueryBuilder.
 *
 * @category VuFind
 * @package  Search
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilder
{
    /**
     * LibGuides widget type
     *
     * 1 = Research Guides
     * 2 = Database A-Z List
     *
     * @var string
     */
    protected $widgetType = '1';

    /// Public API

    /**
     * Return LibGuides search parameters based on a user query and params.
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
        $newParams = new ParamBag();

        // Convert the query to an array, then flatten that to a string
        // (right now, we're ignoring a lot of data -- we may want to
        // revisit this and see if more detail can be utilized).
        $array = $this->abstractQueryToArray($query);
        if (isset($array[0]['lookfor'])) {
            $newParams->set('search', $array[0]['lookfor']);
        }
        $newParams->set('widget_type', $this->widgetType);

        return $newParams;
    }

    /**
     * Set the widget type for this QueryBuilder instance.  See $widgetType.
     *
     * @param string $type Widget type
     *
     * @return void
     */
    public function setDefaultWidgetType($type)
    {
        $this->widgetType = $type;
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
            return $this->queryToArray($query);
        } else {
            return $this->queryGroupToArray($query);
        }
    }

    /**
     * Convert a QueryGroup object to a query string.
     *
     * @param QueryGroup $query QueryGroup to convert
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function queryGroupToArray(QueryGroup $query)
    {
        throw new \Exception('Advanced search not supported.');
    }

    /**
     * Convert a single Query object to a query string.
     *
     * @param Query $query Query to convert
     *
     * @return array
     */
    protected function queryToArray(Query $query)
    {
        // Clean and validate input:
        $index = $query->getHandler();
        $lookfor = $query->getString();
        return [compact('index', 'lookfor')];
    }
}
