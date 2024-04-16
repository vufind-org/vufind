<?php

/**
 * Summon QueryBuilder.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Summon;

use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

use function count;

/**
 * Summon QueryBuilder.
 *
 * @category VuFind
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilder
{
    /**
     * Lucene syntax helper
     *
     * @var LuceneSyntaxHelper
     */
    protected $luceneHelper = null;

    /// Public API

    /**
     * Return Summon search parameters based on a user query and params.
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
        $queryStr = $this->abstractQueryToString($query);

        // Send back results
        $newParams = new ParamBag();
        $newParams->set('query', $queryStr);
        return $newParams;
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
                    $excludes[] = implode(' OR ', $thisGroup);
                } else {
                    $groups[]
                        = implode(' ' . $params->getOperator() . ' ', $thisGroup);
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
                .= '(' . implode(') ' . $query->getOperator() . ' (', $groups) . ')';
        }
        // and concatenate exclusion after that
        if (count($excludes) > 0) {
            $queryStr .= ' NOT ((' . implode(') OR (', $excludes) . '))';
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
        $lookfor = $query->getString();

        // Force boolean operators to uppercase if we are in a
        // case-insensitive mode:
        $lookfor = $this->getLuceneHelper()
            ->capitalizeCaseInsensitiveBooleans($lookfor);

        // Prepend the index name, unless it's the special "AllFields"
        // index:
        return ($index != 'AllFields') ? "{$index}:($lookfor)" : $lookfor;
    }

    /**
     * Get Lucene syntax helper
     *
     * @return LuceneSyntaxHelper
     */
    public function getLuceneHelper()
    {
        if (null === $this->luceneHelper) {
            $this->luceneHelper = new LuceneSyntaxHelper();
        }
        return $this->luceneHelper;
    }

    /**
     * Set Lucene syntax helper
     *
     * @param LuceneSyntaxHelper $helper Lucene syntax helper
     *
     * @return void
     */
    public function setLuceneHelper(LuceneSyntaxHelper $helper)
    {
        $this->luceneHelper = $helper;
    }
}
