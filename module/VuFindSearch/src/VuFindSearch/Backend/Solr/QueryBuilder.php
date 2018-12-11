<?php

/**
 * SOLR QueryBuilder.
 *
 * PHP version 7
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
namespace VuFindSearch\Backend\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use VuFindSearch\Query\QueryGroup;

/**
 * SOLR QueryBuilder.
 *
 * @category VuFind
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
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
     * Solr fields to highlight. Also serves as a flag for whether to perform
     * highlight-specific behavior; if the field list is empty, highlighting is
     * skipped.
     *
     * @var string
     */
    protected $fieldsToHighlight = '';

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

        // Add spelling query if applicable -- note that we must set this up before
        // we process the main query in order to avoid unwanted extra syntax:
        if ($this->createSpellingQuery) {
            $params->set(
                'spellcheck.q',
                $this->getLuceneHelper()->extractSearchTerms($query->getAllTerms())
            );
        }

        if ($query instanceof QueryGroup) {
            $finalQuery = $this->reduceQueryGroup($query);
        } else {
            // Clone the query to avoid modifying the original user-visible query
            $finalQuery = clone $query;
            $finalQuery->setString($this->getNormalizedQueryString($query));
        }
        $string = $finalQuery->getString() ?: '*:*';

        // Highlighting is enabled if we have a field list set.
        $highlight = !empty($this->fieldsToHighlight);

        if ($handler = $this->getSearchHandler($finalQuery->getHandler(), $string)) {
            if (!$handler->hasExtendedDismax()
                && $this->getLuceneHelper()->containsAdvancedLuceneSyntax($string)
            ) {
                $string = $this->createAdvancedInnerSearchString($string, $handler);
                if ($handler->hasDismax()) {
                    $oldString = $string;
                    $string = $handler->createBoostQueryString($string);

                    // If a boost was added, we don't want to highlight based on
                    // the boost query, so we should use the non-boosted version:
                    if ($highlight && $oldString != $string) {
                        $params->set('hl.q', $oldString);
                    }
                }
            } elseif ($handler->hasDismax()) {
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
        // Set an appropriate highlight field list when applicable:
        if ($highlight) {
            $filter = $handler ? $handler->getAllFields() : [];
            $params->add('hl.fl', $this->getFieldsToHighlight($filter));
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
     *
     * @deprecated
     */
    public function setCreateHighlightingQuery($enable)
    {
        // This is deprecated, but use it to manipulate the highlighted field
        // list for backward compatibility.
        $this->fieldsToHighlight = $enable ? '*' : '';
    }

    /**
     * Get list of fields to highlight, filtered by array.
     *
     * @param array $filter Field list to use as a filter.
     *
     * @return string
     */
    protected function getFieldsToHighlight(array $filter = [])
    {
        // No filter? Return unmodified default:
        if (empty($filter)) {
            return $this->fieldsToHighlight;
        }
        // Account for possibility of comma OR space delimiters:
        $fields = array_map('trim', preg_split('/[, ]/', $this->fieldsToHighlight));
        // Wildcard in field list? Return filter as-is; otherwise, use intersection.
        $list = in_array('*', $fields) ? $filter : array_intersect($fields, $filter);
        return implode(',', $list);
    }

    /**
     * Set list of fields to highlight, if any (or '*' for all). Set to an
     * empty string (the default) to completely disable highlighting-related
     * functionality.
     *
     * @param string $list Highlighting field list
     *
     * @return QueryBuilder
     */
    public function setFieldsToHighlight($list)
    {
        $this->fieldsToHighlight = $list;
        return $this;
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
            $reduced = array_filter(
                $reduced,
                function ($s) {
                    return '' !== $s;
                }
            );
            if ($reduced) {
                $searchString .= sprintf(
                    '(%s)', implode(" {$component->getOperator()} ", $reduced)
                );
            }
        } else {
            $searchString = $this->getNormalizedQueryString($component);
            $searchHandler = $this->getSearchHandler(
                $component->getHandler(),
                $searchString
            );
            if ($searchHandler && '' !== $searchString) {
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

        if (null === $string) {
            return '';
        }
        if ($advanced && $handler) {
            return $handler->createAdvancedQueryString($string);
        } elseif ($handler) {
            return $handler->createSimpleQueryString($string);
        } else {
            return $string;
        }
    }

    /**
     * If the query ends in a non-escaped question mark, the user may not really
     * intend to use the question mark as a wildcard -- let's account for that
     * possibility.
     *
     * @param string $string Search query to adjust
     *
     * @return string
     */
    protected function fixTrailingQuestionMarks($string)
    {
        // Treat colon and whitespace as word separators -- in either case, we
        // should add parentheses for accuracy.
        $multiword = preg_match('/[^\s][\s:]+[^\s]/', $string);
        $callback = function ($matches) use ($multiword) {
            // Make sure all question marks are properly escaped (first unescape
            // any that are already escaped to prevent double-escapes, then escape
            // all of them):
            $s = $matches[1];
            $escaped = str_replace('?', '\?', str_replace('\?', '?', $s));
            $s = "($s) OR ($escaped)";
            if ($multiword) {
                $s = "($s) ";
            }
            return $s;
        };
        // Use a lookahead to skip matches found within quoted phrases.
        $lookahead = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';
        $string = preg_replace_callback(
            '/([^\s:()]+\?)(\s|$)' . $lookahead . '/', $callback, $string
        );
        return rtrim($string);
    }

    /**
     * Given a Query object, return a fully normalized version of the query string.
     *
     * @param Query $query Query object
     *
     * @return string
     */
    protected function getNormalizedQueryString($query)
    {
        return $this->fixTrailingQuestionMarks(
            $this->getLuceneHelper()->normalizeSearchString(
                $query->getString()
            )
        );
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

        return $handler
            ? $handler->createAdvancedQueryString($string) : $string;
    }
}
