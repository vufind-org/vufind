<?php

/**
 * Primo Central QueryBuilder.
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
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Primo;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;

use VuFindSearch\ParamBag;

/**
 * Primo Central QueryBuilder.
 *
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class QueryBuilder
{
    /// Public API

    /**
     * Return Primo search parameters based on a user query and params.
     *
     * @param AbstractQuery $query User query
     *
     * @return ParamBag
     */
    public function build(AbstractQuery $query)
    {
        // Send back results
        $params = new ParamBag();
        $params->set('query', $this->abstractQueryToArray($query));
        return $params;
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
     */
    protected function queryGroupToArray(QueryGroup $query)
    {
        $nextLevel = $query->getQueries();
        $parts = [];
        foreach ($nextLevel[0]->getQueries() as $q) {
            $index = $q->getHandler();
            $op = $q->getOperator();
            $lookfor = $q->getString();
            $parts[] = compact('index', 'op', 'lookfor');
        }
        return $parts;
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
