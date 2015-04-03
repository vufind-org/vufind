<?php

/**
 * SOLR QueryBuilder.
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
namespace VuFindSearch\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;

use VuFindSearch\ParamBag;

/**
 * SOLR QueryBuilder.
 *
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * Default dismax handler (if no DismaxHandler set in specs).
     *
     * @var string
     */
    protected $defaultDismaxHandler;

    /**
     * Search specs.
     *
     * @var array
     */
    protected $specs = [];

    /**
     * Search specs for exact searches.
     *
     * @var array
     */
    protected $exactSpecs = [];

    /**
     * Should we create the hl.q parameter when appropriate?
     *
     * @var bool
     */
    protected $createHighlightingQuery = false;

    /**
     * Should we create the spellcheck.q parameter when appropriate?
     *
     * @var bool
     */
    protected $createSpellingQuery = false;

    /**
     * Lucene syntax helper
     *
     * @var LuceneSyntaxHelper
     */
    protected $luceneHelper = null;

    /**
     * Constructor.
     *
     * @param array  $specs                Search handler specifications
     * @param string $defaultDismaxHandler Default dismax handler (if no
     * DismaxHandler set in specs).
     *
     * @return void
     */
    public function __construct(array $specs = [],
        $defaultDismaxHandler = 'dismax'
    ) {
        $this->defaultDismaxHandler = $defaultDismaxHandler;
        $this->setSpecs($specs);
    }

    /// Public API

    /**
     * Return SOLR search parameters based on a user query and params.
     *
     * @param AbstractQuery $query User query
     *
     * @return ParamBag
     */
    public function build(AbstractQuery $query)
    {
        $params = new ParamBag();

        // Add spelling query if applicable -- note that we mus set this up before
        // we process the main query in order to avoid unwanted extra syntax:
        if ($this->createSpellingQuery) {
            $params->set('spellcheck.q', $query->getAllTerms());
        }

        if ($query instanceof QueryGroup) {
            $query = $this->reduceQueryGroup($query);
        } else {
            $query->setString(
                $this->getLuceneHelper()->normalizeSearchString($query->getString())
            );
        }

        $string  = $query->getString() ?: '*:*';

        if ($handler = $this->getSearchHandler($query->getHandler(), $string)) {
            if (!$handler->hasExtendedDismax()
                && $this->getLuceneHelper()->containsAdvancedLuceneSyntax($string)
            ) {
                $string = $this->createAdvancedInnerSearchString($string, $handler);
                if ($handler->hasDismax()) {
                    $oldString = $string;
                    $string = $handler->createBoostQueryString($string);

                    // If a boost was added, we don't want to highlight based on
                    // the boost query, so we should use the non-boosted version:
                    if ($this->createHighlightingQuery && $oldString != $string) {
                        $params->set('hl.q', $oldString);
                    }
                }
            } else {
                if ($handler->hasDismax()) {
                    $params->set('qf', implode(' ', $handler->getDismaxFields()));
                    $params->set('qt', $handler->getDismaxHandler());
                    foreach ($handler->getDismaxParams() as $param) {
                        $params->add(reset($param), next($param));
                    }
                    if ($handler->hasFilterQuery()) {
                        $params->add('fq', $handler->getFilterQuery());
                    }
                } else {
                    $string = $handler->createSimpleQueryString($string);
                }
            }
        }
        $params->set('q', $string);

        return $params;
    }

    /**
     * Control whether or not the QueryBuilder should create an hl.q parameter
     * when the main query includes clauses that should not be factored into
     * highlighting. (Turned off by default).
     *
     * @param bool $enable Should highlighting query generation be enabled?
     *
     * @return void
     */
    public function setCreateHighlightingQuery($enable)
    {
        $this->createHighlightingQuery = $enable;
    }

    /**
     * Control whether or not the QueryBuilder should create a spellcheck.q
     * parameter. (Turned off by default).
     *
     * @param bool $enable Should spelling query generation be enabled?
     *
     * @return void
     */
    public function setCreateSpellingQuery($enable)
    {
        $this->createSpellingQuery = $enable;
    }

    /**
     * Set query builder search specs.
     *
     * @param array $specs Search specs
     *
     * @return void
     */
    public function setSpecs(array $specs)
    {
        foreach ($specs as $handler => $spec) {
            if (isset($spec['ExactSettings'])) {
                $this->exactSpecs[strtolower($handler)] = new SearchHandler(
                    $spec['ExactSettings'], $this->defaultDismaxHandler
                );
                unset($spec['ExactSettings']);
            }
            $this->specs[strtolower($handler)]
                = new SearchHandler($spec, $this->defaultDismaxHandler);
        }
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

    /// Internal API

    /**
     * Return named search handler.
     *
     * @param string $handler      Search handler name
     * @param string $searchString Search query
     *
     * @return SearchHandler|null
     */
    protected function getSearchHandler($handler, $searchString)
    {
        $handler = $handler ? strtolower($handler) : $handler;
        if ($handler) {
            // Since we will rarely have exactSpecs set, it is less expensive
            // to check for a handler first before doing multiple string
            // operations to determine eligibility for exact handling.
            if (isset($this->exactSpecs[$handler])) {
                $searchString = isset($searchString) ? trim($searchString) : '';
                if (strlen($searchString) > 1
                    && substr($searchString, 0, 1) == '"'
                    && substr($searchString, -1, 1) == '"'
                ) {
                    return $this->exactSpecs[$handler];
                }
            }
            if (isset($this->specs[$handler])) {
                return $this->specs[$handler];
            }
        }
        return null;
    }

    /**
     * Reduce query group a single query.
     *
     * @param QueryGroup $group Query group to reduce
     *
     * @return Query
     */
    protected function reduceQueryGroup(QueryGroup $group)
    {
        $searchString  = $this->reduceQueryGroupComponents($group);
        $searchHandler = $group->getReducedHandler();
        return new Query($searchString, $searchHandler);
    }

    /**
     * Reduce components of query group to a search string of a simple query.
     *
     * This function implements the recursive reduction of a query group.
     *
     * @param AbstractQuery $component Component
     *
     * @return string
     *
     * @see self::reduceQueryGroup()
     */
    protected function reduceQueryGroupComponents(AbstractQuery $component)
    {
        if ($component instanceof QueryGroup) {
            $reduced = array_map(
                [$this, 'reduceQueryGroupComponents'], $component->getQueries()
            );
            $searchString = $component->isNegated() ? 'NOT ' : '';
            $searchString .= sprintf(
                '(%s)', implode(" {$component->getOperator()} ", $reduced)
            );
        } else {
            $searchString  = $this->getLuceneHelper()
                ->normalizeSearchString($component->getString());
            $searchHandler = $this->getSearchHandler(
                $component->getHandler(),
                $searchString
            );
            if ($searchHandler) {
                $searchString
                    = $this->createSearchString($searchString, $searchHandler);
            }
        }
        return $searchString;
    }

    /**
     * Return search string based on input and handler.
     *
     * @param string        $string  Input search string
     * @param SearchHandler $handler Search handler
     *
     * @return string
     */
    protected function createSearchString($string, SearchHandler $handler = null)
    {
        $advanced = $this->getLuceneHelper()->containsAdvancedLuceneSyntax($string);

        if ($advanced && $handler) {
            return $handler->createAdvancedQueryString($string);
        } else if ($handler) {
            return $handler->createSimpleQueryString($string);
        } else {
            return $string;
        }
    }

    /**
     * Return advanced inner search string based on input and handler.
     *
     * @param string        $string  Input search string
     * @param SearchHandler $handler Search handler
     *
     * @return string
     */
    protected function createAdvancedInnerSearchString($string,
        SearchHandler $handler
    ) {
        // Special case -- if the user wants all records but the current handler
        // has a filter query, apply the filter query:
        if (trim($string) === '*:*' && $handler && $handler->hasFilterQuery()) {
            return $handler->getFilterQuery();
        }

        // If the query already includes field specifications, we can't easily
        // apply it to other fields through our defined handlers, so we'll leave
        // it as-is:
        if (strstr($string, ':')) {
            return $string;
        }

        // If the query ends in a non-escaped question mark, the user may not really
        // intend to use the question mark as a wildcard -- let's account for that
        // possibility
        if (substr($string, -1) == '?' && substr($string, -2) != '\?') {
            // Make sure all question marks are properly escaped (first unescape
            // any that are already escaped to prevent double-escapes, then escape
            // all of them):
            $strippedQuery
                = str_replace('?', '\?', str_replace('\?', '?', $string));
            $string = "({$string}) OR (" . $strippedQuery . ")";
        }

        return $handler
            ? $handler->createAdvancedQueryString($string, false) : $string;
    }
}