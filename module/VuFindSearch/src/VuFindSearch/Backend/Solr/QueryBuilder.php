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
class QueryBuilder
{

    /**
     * Regular expression matching a SOLR range.
     *
     * @var string
     */
    const SOLR_RANGE_RE = '/(\[.+\s+TO\s+.+\])|(\{.+\s+TO\s+.+\})/';

    /**
     * Lookahead that detects whether or not we are inside quotes.
     *
     * @var string
     */
    protected static $insideQuotes = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';

    /**
     * Search specs.
     *
     * @var array
     */
    protected $specs;

    /**
     * Force ranges to uppercase?
     *
     * @var boolean
     */
    public $caseSensitiveRanges = true;

    /**
     * Force boolean operators to uppercase?
     *
     * @var boolean
     */
    public $caseSensitiveBooleans = true;

    /**
     * Constructor.
     *
     * @param array $specs Search handler specifications
     *
     * @return void
     */
    public function __construct(array $specs = array())
    {
        $this->setSpecs($specs);
    }

    /// Public API

    /**
     * Return SOLR search parameters based on a user query and params.
     *
     * @param AbstractQuery $query User query
     *
     * @return ParamBag
     *
     * @todo Review usage of filters
     * @todo Review usage of facets
     * @todo Implement hightlighting
     */
    public function build(AbstractQuery $query)
    {

        if ($query instanceOf QueryGroup) {
            $query = $this->reduceQueryGroup($query);
        } else {
            $query->setString($this->normalizeSearchString($query->getString()));
        }

        $string  = $query->getString() ?: '*:*';
        $handler = $this->getSearchHandler($query->getHandler());

        $params  = new ParamBag();

        if ($this->containsAdvancedLuceneSyntax($string)) {

            if ($handler) {
                if ($params->get('hl') && array_intersect($params->get('hl'), array('true', 'on'))) {
                    $params->set('hl.q', $this->createAdvancedInnerSearchString($string, $handler));
                }
                $string = $this->createAdvancedInnerSearchString($string, $handler);
                if ($handler->hasDismax()) {
                    $string = $handler->createBoostQueryString($string);
                }
            }
        } else {
            if ($handler && $handler->hasDismax()) {
                $params->set('qf', implode(' ', $handler->getDismaxFields()));
                $params->set('qt', 'dismax');
                foreach ($handler->getDismaxParams() as $param) {
                    foreach ($param as $pair) {
                        $params->add(reset($pair), next($pair));
                    }
                }
                if ($handler->hasFilterQuery()) {
                    $params->add('fq', $handler->getFilterQuery());
                }
            } else {
                if ($handler) {
                    $string = $handler->createSimpleQueryString($string);
                }
            }
        }
        $params->set('q', $string);

        return $params;
    }

    /**
     * Return true if the search string contains advanced Lucene syntax.
     *
     * @param string $searchString Search string
     *
     * @return boolean
     *
     * @todo Maybe factor out to dedicated UserQueryAnalyzer
     */
    public function containsAdvancedLuceneSyntax($searchString)
    {
        // Check for various conditions that flag an advanced Lucene query:
        if ($searchString == '*:*') {
            return true;
        }

        // The following conditions do not apply to text inside quoted strings,
        // so let's just strip all quoted strings out of the query to simplify
        // detection.  We'll replace quoted phrases with a dummy keyword so quote
        // removal doesn't interfere with the field specifier check below.
        $searchString = preg_replace('/"[^"]*"/', 'quoted', $searchString);

        // Check for field specifiers:
        if (preg_match("/[^\s]\:[^\s]/", $searchString)) {
            return true;
        }

        // Check for parentheses and range operators:
        if (strstr($searchString, '(') && strstr($searchString, ')')) {
            return true;
        }
        $rangeReg = self::SOLR_RANGE_RE;
        if (!$this->caseSensitiveRanges) {
            $rangeReg .= "i";
        }
        if (preg_match($rangeReg, $searchString)) {
            return true;
        }

        // Build a regular expression to detect booleans -- AND/OR/NOT surrounded
        // by whitespace, or NOT leading the query and followed by whitespace.
        $boolReg = '/((\s+(AND|OR|NOT)\s+)|^NOT\s+)/';
        if (!$this->caseSensitiveBooleans) {
            $boolReg .= "i";
        }
        if (preg_match($boolReg, $searchString)) {
            return true;
        }

        // Check for wildcards and fuzzy matches:
        if (strstr($searchString, '*') || strstr($searchString, '?') || strstr($searchString, '~')) {
            return true;
        }

        // Check for boosts:
        if (preg_match('/[\^][0-9]+/', $searchString)) {
            return true;
        }

        return false;
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
            $this->specs[strtolower($handler)] = new SearchHandler($spec);
        }
    }

    /**
     * Return search specs.
     *
     * @return array
     */
    public function getSpecs()
    {
        $specs = array();
        foreach ($specs as $handler => $spec) {
            $specs[$handler] = $spec->toArray();
        }
        return $specs;
    }

    /// Internal API

    /**
     * Return named search handler.
     *
     * @param string $handler Search handler name
     *
     * @return SearchHandler|null
     */
    protected function getSearchHandler($handler)
    {
        $handler = $handler ? strtolower($handler) : $handler;
        if ($handler && isset($this->specs[$handler])) {
            return $this->specs[$handler];
        } else {
            return null;
        }
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
     *
     */
    protected function reduceQueryGroupComponents(AbstractQuery $component)
    {
        if ($component instanceOf QueryGroup) {
            $reduced = array_map(array($this, 'reduceQueryGroupComponents'), $component->getQueries());
            $searchString = $component->isNegated() ? 'NOT ' : '';
            $searchString .= sprintf('(%s)', implode(" {$component->getOperator()} ", $reduced));
        } else {
            $searchString  = $this->normalizeSearchString($component->getString());
            $searchHandler = $this->getSearchHandler($component->getHandler());
            if ($searchHandler) {
                $searchString = $this->createSearchString($searchString, $searchHandler);
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
     *
     */
    protected function createSearchString($string, SearchHandler $handler = null)
    {
        $advanced = $this->containsAdvancedLuceneSyntax($string);

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
     *
     */
    protected function createAdvancedInnerSearchString($string, SearchHandler $handler)
    {
        // Special case -- if the user wants all records but the current handler
        // has a filter query, apply the filter query:
        if (trim($string) === '*:*' && $handler && $handler->hasFilterQuery()) {
            return $handler->getFilterQuery();
        }

        // Strip out any colons that are NOT part of a field specification:
        $string = preg_replace('/(\:\s+|\s+:)/', ' ', $string);

        // If the query already includes field specifications, we can't easily
        // apply it to other fields through our defined handlers, so we'll leave
        // it as-is:
        if (strstr($string, ':')) {
            return $string;
        }

        // Convert empty queries to return all values in a field:
        if (empty($string)) {
            $string = '[* TO *]';
        }

        // If the query ends in a question mark, the user may not really intend to
        // use the question mark as a wildcard -- let's account for that possibility
        if (substr($string, -1) == '?') {
            $string = "({$string}) OR (" . substr($string, 0, strlen($string) - 1) . ")";
        }

        return $handler ? $handler->createAdvancedQueryString($string, false) : $string;
    }

    /**
     * Return normalized input string.
     *
     * @param string $searchString Input search string
     *
     * @return string
     */
    protected function normalizeSearchString($searchString)
    {
        $searchString = $this->prepareForLuceneSyntax($searchString);

        // Force boolean operators to uppercase if we are in a
        // case-insensitive mode:
        if (!$this->caseSensitiveBooleans) {
            $searchString = $this->capitalizeBooleans($searchString);
        }
        // Adjust range operators if we are in a case-insensitive mode:
        if (!$this->caseSensitiveRanges) {
            $searchString = $this->capitalizeRanges($searchString);
        }
        return $searchString;
    }

    /**
     * Prepare input to be used in a SOLR query.
     *
     * Handles certain cases where the input might conflict with Lucene
     * syntax rules.
     *
     * @param string $input Input string
     *
     * @return string
     *
     * @todo Check if it is safe to assume $input to be an UTF-8 encoded string.
     */
    protected function prepareForLuceneSyntax($input)
    {
        // Normalize fancy quotes:
        $quotes = array(
            "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
            "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
            "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
            "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
            "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
            "\xE2\x80\x9B" => "'", // ? (U+201B) in UTF-8
            "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
            "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
            "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
            "\xE2\x80\x9F" => '"', // ? (U+201F) in UTF-8
            "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
            "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
        );
        $input = strtr($input, $quotes);

        // If the user has entered a lone BOOLEAN operator, convert it to lowercase
        // so it is treated as a word (otherwise it will trigger a fatal error):
        switch(trim($input)) {
        case 'OR':
            return 'or';
        case 'AND':
            return 'and';
        case 'NOT':
            return 'not';
        }

        // If the string consists only of control characters and/or BOOLEANs with no
        // other input, wipe it out entirely to prevent weird errors:
        $operators = array('AND', 'OR', 'NOT', '+', '-', '"', '&', '|');
        if (trim(str_replace($operators, '', $input)) == '') {
            return '';
        }

        // Translate "all records" search into a blank string
        if (trim($input) == '*:*') {
            return '';
        }

        // Ensure wildcards are not at beginning of input
        if ((substr($input, 0, 1) == '*') || (substr($input, 0, 1) == '?')) {
            $input = substr($input, 1);
        }

        // Ensure all parens match
        //   Better: Remove all parens if they are not balanced
        //     -- dmaus, 2012-11-11
        $start = preg_match_all('/\(/', $input, $tmp);
        $end = preg_match_all('/\)/', $input, $tmp);
        if ($start != $end) {
            $input = str_replace(array('(', ')'), '', $input);
        }

        // Ensure ^ is used properly
        //   Better: Remove all ^ if not followed by digits
        //     -- dmaus, 2012-11-11
        $cnt = preg_match_all('/\^/', $input, $tmp);
        $matches = preg_match_all('/.+\^[0-9]/', $input, $tmp);
        if (($cnt) && ($cnt !== $matches)) {
            $input = str_replace('^', '', $input);
        }

        // Remove unwanted brackets/braces that are not part of range queries.
        // This is a bit of a shell game -- first we replace valid brackets and
        // braces with tokens that cannot possibly already be in the query (due
        // to ^ normalization in the step above).  Next, we remove all remaining
        // invalid brackets/braces, and transform our tokens back into valid ones.
        // Obviously, the order of the patterns/merges array is critically
        // important to get this right!!
        $patterns = array(
            // STEP 1 -- escape valid brackets/braces
            '/\[([^\[\]\s]+\s+TO\s+[^\[\]\s]+)\]/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            '/\{([^\{\}\s]+\s+TO\s+[^\{\}\s]+)\}/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            // STEP 2 -- destroy remaining brackets/braces
            '/[\[\]\{\}]/',
            // STEP 3 -- unescape valid brackets/braces
            '/\^\^lbrack\^\^/', '/\^\^rbrack\^\^/',
            '/\^\^lbrace\^\^/', '/\^\^rbrace\^\^/');
        $matches = array(
            // STEP 1 -- escape valid brackets/braces
            '^^lbrack^^$1^^rbrack^^', '^^lbrace^^$1^^rbrace^^',
            // STEP 2 -- destroy remaining brackets/braces
            '',
            // STEP 3 -- unescape valid brackets/braces
            '[', ']', '{', '}');
        $input = preg_replace($patterns, $matches, $input);

        // Freestanding hyphens can cause problems:
        $lookahead = self::$insideQuotes;
        $input = preg_replace('/\s+-\s+' . $lookahead . '/', ' ', $input);

        // Remove empty parentheses outside of quotation marks -- these will
        // cause a fatal Solr error and should be ignored.
        $parenRegex = '/\(\s*\)' . $lookahead . '/';
        while (preg_match($parenRegex, $input)) {
            $input = preg_replace($parenRegex, '', $input);
        }

        return $input;
    }

    /**
     * Capitalize boolean operators.
     *
     * @param string $string Search string
     *
     * @return string
     */
    public function capitalizeBooleans($string)
    {
        // Load the "inside quotes" lookahead so we can use it to prevent
        // switching case of Boolean reserved words inside quotes, since
        // that can cause problems in case-sensitive fields when the reserved
        // words are actually used as search terms.
        $lookahead = self::$insideQuotes;
        $regs = array("/\s+AND\s+{$lookahead}/i", "/\s+OR\s+{$lookahead}/i",
                "/(\s+NOT\s+|^NOT\s+){$lookahead}/i", "/\(NOT\s+{$lookahead}/i");
        $replace = array(' AND ', ' OR ', ' NOT ', '(NOT ');
        return trim(preg_replace($regs, $replace, $string));
    }

    /**
     * Capitalize range operator.
     *
     * @param string $string Search string
     *
     * @return string
     */
    public function capitalizeRanges($string)
    {
        // Load the "inside quotes" lookahead so we can use it to prevent
        // switching case of ranges inside quotes, since that can cause
        // problems in case-sensitive fields when the reserved words are
        // actually used as search terms.
        $lookahead = self::$insideQuotes;
        $regs = array("/(\[)([^\]]+)\s+TO\s+([^\]]+)(\]){$lookahead}/i",
            "/(\{)([^}]+)\s+TO\s+([^}]+)(\}){$lookahead}/i");
        $callback = array($this, 'capitalizeRangesCallback');
        return trim(preg_replace_callback($regs, $callback, $string));
    }

    /**
     * Callback helper function.
     *
     * @param array $match Matches as of preg_replace_callback()
     *
     * @return string
     *
     * @see self::capitalizeRanges
     *
     * @todo Check possible problem with umlauts/non-ASCII word characters
     */
    protected function capitalizeRangesCallback($match)
    {
        // Extract the relevant parts of the expression:
        $open = $match[1];         // opening symbol
        $close = $match[4];        // closing symbol
        $start = $match[2];        // start of range
        $end = $match[3];          // end of range

        // Is this a case-sensitive range?
        if (strtoupper($start) != strtolower($start)
            || strtoupper($end) != strtolower($end)
        ) {
            // Build a lowercase version of the range:
            $lower = $open . trim(strtolower($start)) . ' TO ' .
                trim(strtolower($end)) . $close;
            // Build a uppercase version of the range:
            $upper = $open . trim(strtoupper($start)) . ' TO ' .
                trim(strtoupper($end)) . $close;

            // Special case: don't create illegal timestamps!
            $timestamp = '/[0-9]{4}-[0-9]{2}-[0-9]{2}t[0-9]{2}:[0-9]{2}:[0-9]{2}z/i';
            if (preg_match($timestamp, $start) || preg_match($timestamp, $end)) {
                return $upper;
            }

            // Accept results matching either range:
            return '(' . $lower . ' OR ' . $upper . ')';
        } else {
            // Simpler case -- case insensitive (probably numeric) range:
            return $open . trim($start) . ' TO ' . trim($end) . $close;
        }
    }

    /**
     * Return from and to values of SOLR range.
     *
     * Returns false if the search string does not contain a range. Otherwise
     * it returns an associative array with the keys `from', and `to'
     * referring to the lower and upper bound of the range.
     *
     * @param string $string Search string
     *
     * @return array|false
     *
     * @todo This function seems to be unused
     */
    public function parseRange($string)
    {
        $regEx = '/\[([^\]]+)\s+TO\s+([^\]]+)\]/';
        if (!preg_match($regEx, $string, $matches)) {
            return false;
        }
        return array('from' => trim($matches[1]), 'to' => trim($matches[2]));
    }

}