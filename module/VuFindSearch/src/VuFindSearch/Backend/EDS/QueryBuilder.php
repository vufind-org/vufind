<?php

/**
 * EDS API Querybuilder
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\EDS;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * EDS API Querybuilder
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilder
{
    /**
     * Default query (used when query string is empty). This should retrieve all
     * records in the index, facilitating high-level facet-based browsing.
     *
     * @var string
     */
    protected $defaultQuery = '(FT yes) OR (FT no)';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Construct EdsApi search parameters based on a user query and params.
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
        $queries = $this->abstractQueryToArray($query);

        // Send back results
        return new ParamBag(['query' => $queries]);
    }

    /**
     * Convert a single Query object to an eds api query array
     *
     * @param Query  $query    Query to convert
     * @param string $operator Operator to apply
     *
     * @return array
     */
    protected function queryToEdsQuery(Query $query, $operator = 'AND')
    {
        $expression = $query->getString();
        $fieldCode = ($query->getHandler() == 'AllFields')
            ? '' : $query->getHandler();  //fieldcode
        // Special case: default search
        if (empty($fieldCode) && empty($expression)) {
            $expression = $this->defaultQuery;
        }
        return json_encode(
            ['term' => $expression, 'field' => $fieldCode, 'bool' => $operator]
        );
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
            return ['1' => $this->queryToEdsQuery($query)];
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
        $groups = [];
        foreach ($query->getQueries() as $params) {
            // Advanced Search
            if ($params instanceof QueryGroup) {
                // Process each search group
                foreach ($params->getQueries() as $q) {
                    // Build this group individually as a basic search
                    $op = $q->getOperator();
                    if ($params->isNegated()) {
                        $op = 'NOT';
                    }
                    $grp  = $this->queryToEdsQuery($q, $op);
                    $groups[] = $grp;
                }
            } else {
                // Basic Search
                $groups[] = $this->queryToEdsQuery($params);
            }
        }
        return $groups;
    }
}
