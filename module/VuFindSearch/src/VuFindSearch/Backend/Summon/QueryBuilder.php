<?php

/**
 * Summon QueryBuilder.
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
namespace VuFindSearch\Backend\Summon;

use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;

use VuFindSearch\ParamBag;

/**
 * Summon QueryBuilder.
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
     * @param AbstractQuery $query User query
     *
     * @return ParamBag
     */
    public function build(AbstractQuery $query)
    {
        // Build base query
        $queryStr = $this->abstractQueryToString($query);

        // Send back results
        $params = new ParamBag();
        $params->set('query', $queryStr);
        return $params;
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
                    $excludes[] = join(" OR ", $thisGroup);
                } else {
                    $groups[]
                        = join(" " . $params->getOperator() . " ", $thisGroup);
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
                .= "(" . join(") " . $query->getOperator() . " (", $groups) . ")";
        }
        // and concatenate exclusion after that
        if (count($excludes) > 0) {
            $queryStr .= " NOT ((" . join(") OR (", $excludes) . "))";
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