<?php

/**
 * A single/simple query.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Query;

/**
 * A single/simple query.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Query extends AbstractQuery
{
    /**
     * Name of query handler, if any.
     *
     * @var string
     */
    protected $queryHandler;

    /**
     * Query string
     *
     * @var string
     */
    protected $queryString;

    /**
     * Operator to apply to query string (null if not applicable)
     *
     * @var string
     */
    protected $operator;

    /**
     * Constructor.
     *
     * @param string $string   Search string
     * @param string $handler  Name of search handler
     * @param string $operator Operator to apply to query string (null if n/a)
     */
    public function __construct($string = null, $handler = null, $operator = null)
    {
        $this->queryHandler = $handler ? $handler : null;
        $this->queryString  = $string;
        $this->operator = $operator;
    }

    /**
     * Return search string.
     *
     * @return string
     */
    public function getString()
    {
        return $this->queryString;
    }

    /**
     * Set the search string.
     *
     * @param string $string New search string
     *
     * @return void
     */
    public function setString($string)
    {
        $this->queryString = $string;
    }

    /**
     * Return name of search handler.
     *
     * @return string
     */
    public function getHandler()
    {
        return $this->queryHandler;
    }

    /**
     * Set name of search handler.
     *
     * @param string $handler Name of handler
     *
     * @return string
     */
    public function setHandler($handler)
    {
        $this->queryHandler = $handler;
    }

    /**
     * Return operator (null if n/a).
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Set operator (null if n/a).
     *
     * @param string $operator Operator
     *
     * @return string
     */
    public function setOperator($operator)
    {
        $this->operator = $operator;
    }

    /**
     * Does the query contain the specified term?
     *
     * @param string $needle Term to check
     *
     * @return bool
     */
    public function containsTerm($needle)
    {
        // Escape characters with special meaning in regular expressions to avoid
        // errors:
        $needle = preg_quote($needle, '/');

        return (bool)preg_match("/\b$needle\b/u", $this->getString());
    }

    /**
     * Get a concatenated list of all query strings within the object.
     *
     * @return string
     */
    public function getAllTerms()
    {
        return $this->getString();
    }

    /**
     * Replace a term.
     *
     * @param string $from Search term to find
     * @param string $to   Search term to insert
     *
     * @return void
     */
    public function replaceTerm($from, $to)
    {
        // Escape $from so it is regular expression safe (just in case it
        // includes any weird punctuation -- unlikely but possible):
        $from = preg_quote($from, '/');

        // If our "from" pattern contains non-word characters, we can't use word
        // boundaries for matching.  We want to try to use word boundaries when
        // possible, however, to avoid the replacement from affecting unexpected
        // parts of the search query.
        if (!preg_match('/.*[^\w].*/', $from)) {
            $pattern = "/\b$from\b/i";
        } else {
            $pattern = "/$from/i";
        }

        // Perform the replacement:
        $this->queryString = preg_replace($pattern, $to, $this->queryString);
    }
}
