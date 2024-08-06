<?php

/**
 * A group of single/simples queries, joined by boolean operator.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Query;

use VuFindSearch\Exception\InvalidArgumentException;

use function in_array;

/**
 * A group of single/simples queries, joined by boolean operator.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryGroup extends AbstractQuery
{
    /**
     * Valid boolean operators.
     *
     * @var array
     */
    protected static $operators = ['AND', 'OR', 'NOT'];

    /**
     * Name of the handler to be used if the query group is reduced.
     *
     * @see \VuFindSearch\Backend\Solr\QueryBuilder::reduceQueryGroup()
     *
     * @var string
     *
     * @todo Check if we actually use/need this feature
     */
    protected $reducedHandler;

    /**
     * Boolean operator.
     *
     * @var string
     */
    protected $operator;

    /**
     * Is the query group negated?
     *
     * @var bool
     */
    protected $negation;

    /**
     * Queries.
     *
     * @var array
     */
    protected $queries;

    /**
     * Constructor.
     *
     * @param string $operator       Boolean operator
     * @param array  $queries        Queries
     * @param string $reducedHandler Handler to be uses if reduced
     *
     * @return void
     */
    public function __construct(
        $operator,
        array $queries = [],
        $reducedHandler = null
    ) {
        $this->setOperator($operator);
        $this->setQueries($queries);
        $this->setReducedHandler($reducedHandler);
    }

    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        $new = [];
        foreach ($this->queries as $q) {
            $new[] = clone $q;
        }
        $this->queries = $new;
    }

    /**
     * Return name of reduced handler.
     *
     * @return string|null
     */
    public function getReducedHandler()
    {
        return $this->reducedHandler;
    }

    /**
     * Set name of reduced handler.
     *
     * @param string $handler Reduced handler
     *
     * @return void
     */
    public function setReducedHandler($handler)
    {
        $this->reducedHandler = $handler;
    }

    /**
     * Unset reduced handler.
     *
     * @return void
     */
    public function unsetReducedHandler()
    {
        $this->reducedHandler = null;
    }

    /**
     * Add a query to the group.
     *
     * @param AbstractQuery $query Query to add
     *
     * @return void
     */
    public function addQuery(AbstractQuery $query)
    {
        $this->queries[] = $query;
    }

    /**
     * Return group queries.
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * Set group queries.
     *
     * @param array $queries Group queries
     *
     * @return void
     */
    public function setQueries(array $queries)
    {
        $this->queries = [];
        $this->addQueries($queries);
    }

    /**
     * Add group queries.
     *
     * @param array $queries Group queries
     *
     * @return void
     */
    public function addQueries(array $queries)
    {
        foreach ($queries as $query) {
            $this->addQuery($query);
        }
    }

    /**
     * Set boolean operator.
     *
     * @param string $operator Boolean operator
     *
     * @return void
     *
     * @throws \InvalidArgumentException Unknown or invalid boolean operator
     */
    public function setOperator($operator)
    {
        if (!in_array($operator, self::$operators)) {
            throw new InvalidArgumentException(
                "Unknown or invalid boolean operator: {$operator}"
            );
        }
        if ($operator == 'NOT') {
            $this->operator = 'OR';
            $this->negation = true;
        } else {
            $this->operator = $operator;
        }
    }

    /**
     * Return boolean operator.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Return true if group is an exclusion group.
     *
     * @return bool
     */
    public function isNegated()
    {
        return $this->negation;
    }

    /**
     * Does the query contain the specified term? An optional normalizer can be
     * provided to allow for fuzzier matching.
     *
     * @param string   $needle     Term to check
     * @param callable $normalizer Function to normalize text strings (null for
     * no normalization)
     *
     * @return bool
     */
    public function containsTerm($needle, $normalizer = null)
    {
        foreach ($this->getQueries() as $q) {
            if ($q->containsTerm($needle, $normalizer)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a concatenated list of all query strings within the object.
     *
     * @return string
     */
    public function getAllTerms()
    {
        $parts = [];
        foreach ($this->getQueries() as $q) {
            $parts[] = $q->getAllTerms();
        }
        return implode(' ', $parts);
    }

    /**
     * Replace a term.
     *
     * @param string   $from       Search term to find
     * @param string   $to         Search term to insert
     * @param callable $normalizer Function to normalize text strings (null for
     * no normalization)
     *
     * @return void
     */
    public function replaceTerm($from, $to, $normalizer = null)
    {
        foreach ($this->getQueries() as $q) {
            $q->replaceTerm($from, $to, $normalizer);
        }
    }
}
