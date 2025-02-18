<?php

/**
 * VuFind SearchHandler.
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

namespace VuFindSearch\Backend\Solr;

use function chr;
use function in_array;
use function intval;
use function sprintf;

/**
 * VuFind SearchHandler.
 *
 * The SearchHandler implements the rule-based translation of a user search
 * query to a SOLR query string.
 *
 * @category VuFind
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SearchHandler
{
    /**
     * Known configuration keys.
     *
     * @var array
     */
    protected static $configKeys = [
        'CustomMunge', 'DismaxFields', 'DismaxHandler', 'QueryFields',
        'DismaxParams', 'FilterQuery', 'DismaxMunge',
    ];

    /**
     * Known boolean operators.
     *
     * @var array
     */
    protected static $booleanOperators = ['AND', 'OR', 'NOT'];

    /**
     * Search handler specification.
     *
     * @var array
     */
    protected $specs;

    /**
     * Constructor.
     *
     * @param array  $spec                 Search handler specification
     * @param string $defaultDismaxHandler Default dismax handler (if no
     * DismaxHandler set in specs).
     *
     * @return void
     */
    public function __construct(array $spec, $defaultDismaxHandler = 'dismax')
    {
        foreach (self::$configKeys as $key) {
            $this->specs[$key] = $spec[$key] ?? [];
        }
        // Set dismax handler to default if not specified:
        if (empty($this->specs['DismaxHandler'])) {
            $this->specs['DismaxHandler'] = $defaultDismaxHandler;
        }
        // Set default mm handler if necessary:
        $this->setDefaultMustMatch();
    }

    /// Public API

    /**
     * Return an advanced query string.
     *
     * An advanced query string is a query string based on a search string w/
     * lucene syntax features.
     *
     * @param string $search Search string
     *
     * @return string
     *
     * @see \VuFind\Service\Solr\LuceneSyntaxHelper::containsAdvancedLuceneSyntax()
     */
    public function createAdvancedQueryString($search)
    {
        return $this->createQueryString($search, true);
    }

    /**
     * Return a simple query string.
     *
     * @param string $search Search string
     *
     * @return string
     *
     * @see \VuFind\Service\Solr\SearchHandler::createAdvancedQueryString()
     */
    public function createSimpleQueryString($search)
    {
        return $this->createQueryString($search, false);
    }

    /**
     * Apply standard pre-processing to the query string.
     *
     * @param string $search Search string
     *
     * @return string
     */
    public function preprocessQueryString($search)
    {
        // Apply Dismax munging, if required:
        if ($this->hasDismax()) {
            return $this->dismaxMunge($search);
        }
        return $search;
    }

    /**
     * Return an advanced query string for specified search string.
     *
     * @param string $search Search string
     *
     * @return string
     */
    public function createBoostQueryString($search)
    {
        $boostQuery = [];
        if ($this->hasDismax()) {
            foreach ($this->getDismaxParams() as $param) {
                [$name, $value] = $param;
                if ($name === 'bq') {
                    $boostQuery[] = $value;
                } elseif ($name === 'bf') {
                    // BF parameter may contain multiple space-separated functions
                    // with individual boosts. We need to parse this into _val_
                    // query components:
                    foreach (explode(' ', $value) as $boostFunction) {
                        if ($boostFunction) {
                            $parts = explode('^', $boostFunction, 2);
                            $boostQuery[] = sprintf(
                                '_val_:"%s"%s',
                                addcslashes($parts[0], '"'),
                                isset($parts[1]) ? "^{$parts[1]}" : ''
                            );
                        }
                    }
                }
            }
        }
        if ($boostQuery) {
            return sprintf(
                '(%s) AND (*:* OR %s)',
                $search,
                implode(' OR ', $boostQuery)
            );
        } else {
            return $search;
        }
    }

    /**
     * Return true if the handler defines Dismax fields.
     *
     * @return bool
     */
    public function hasDismax()
    {
        return !empty($this->specs['DismaxFields']);
    }

    /**
     * Get the name of the Dismax handler to be used with this search.
     *
     * @return string
     */
    public function getDismaxHandler()
    {
        return $this->specs['DismaxHandler'];
    }

    /**
     * Return true if the handler supports Extended Dismax.
     *
     * @return bool
     */
    public function hasExtendedDismax()
    {
        return $this->hasDismax() && ('edismax' == $this->getDismaxHandler());
    }

    /**
     * Get a list of all Solr fields searched by this handler.
     *
     * @return array
     */
    public function getAllFields()
    {
        // If we have non-Dismax rules, the keys are the field names.
        $queryFields = array_keys($this->mungeRules());

        // If we have Dismax fields, we need to strip off boost values.
        $callback = function ($f) {
            return current(explode('^', $f));
        };
        $dismaxFields = array_map($callback, $this->getDismaxFields());

        return array_unique(array_merge($queryFields, $dismaxFields));
    }

    /**
     * Return defined dismax fields.
     *
     * @return array
     */
    public function getDismaxFields()
    {
        return $this->specs['DismaxFields'];
    }

    /**
     * Return defined dismax parameters.
     *
     * @return array
     */
    public function getDismaxParams()
    {
        return $this->specs['DismaxParams'];
    }

    /**
     * Return the filter query.
     *
     * @return string
     */
    public function getFilterQuery()
    {
        return empty($this->specs['FilterQuery'])
            ? null : $this->specs['FilterQuery'];
    }

    /**
     * Return true if handler defines a filter query.
     *
     * @return bool
     */
    public function hasFilterQuery()
    {
        return (bool)$this->specs['FilterQuery'];
    }

    /**
     * Serialize handler specs as array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->specs;
    }

    /// Internal API

    /**
     * Support method for constructor: if no mm is provided, set a reasonable
     * default based on the selected Dismax handler.
     *
     * @return void
     */
    protected function setDefaultMustMatch()
    {
        // Initialize parameter array if absent:
        if (!isset($this->specs['DismaxParams'])) {
            $this->specs['DismaxParams'] = [];
        }
        // Add mm if applicable:
        if ($this->hasDismax()) {
            // Our default mm depends on whether we're using dismax or edismax;
            // for dismax, we want 100% matches, because we always want to
            // simulate "AND" behavior by default (any "OR" searches will get
            // rerouted to Lucene queries). For edismax, boolean operators are
            // accounted for, and with an mm of 100%, OR searches will always
            // fail. We can use 0% here, because the default q.op of AND will
            // make AND searches work correctly even without a high mm value.
            $default = $this->hasExtendedDismax() ? '0%' : '100%';

            // Now if the configuration has no explicit mm value, let's push in
            // our default:
            $foundSetting = false;
            foreach ($this->specs['DismaxParams'] as $current) {
                if ($current[0] == 'mm') {
                    $foundSetting = true;
                    break;
                }
            }
            if (!$foundSetting) {
                $this->specs['DismaxParams'][] = ['mm', $default];
            }
        }
    }

    /**
     * Return a Dismax subquery for specified search string.
     *
     * @param string $search Search string
     *
     * @return string
     */
    protected function dismaxSubquery($search)
    {
        $dismaxParams = [];
        foreach ($this->specs['DismaxParams'] as $param) {
            $dismaxParams[] = sprintf(
                "%s='%s'",
                $param[0],
                addcslashes($param[1], "'")
            );
        }
        $dismaxQuery = sprintf(
            '{!%s qf="%s" %s}%s',
            $this->getDismaxHandler(),
            implode(' ', $this->specs['DismaxFields']),
            implode(' ', $dismaxParams),
            $search
        );
        return sprintf('_query_:"%s"', addslashes($dismaxQuery));
    }

    /**
     * Return the munge values for specified search string.
     *
     * If optional argument $tokenize is true tokenize the search string.
     *
     * @param string $search   Search string
     * @param bool   $tokenize Tokenize the search string?
     *
     * @return string
     */
    protected function mungeValues($search, $tokenize = true)
    {
        if ($tokenize) {
            $tokens = $this->tokenize($search);
            $mungeValues = [
                'onephrase' => sprintf(
                    '"%s"',
                    str_replace('"', '', implode(' ', $tokens))
                ),
                'and' => implode(' AND ', $tokens),
                'or'  => implode(' OR ', $tokens),
                'identity' => $search,
            ];
        } else {
            $mungeValues = [
                'and' => $search,
                'or'  => $search,
            ];
            // If we're skipping tokenization, we just want to pass $lookfor through
            // unmodified (it's probably an advanced search that won't benefit from
            // tokenization). We'll just set all possible values to the same thing,
            // except that we'll try to do the "one phrase" in quotes if possible.
            // IMPORTANT: If we detect a boolean NOT, we MUST omit the quotes. We
            // also omit quotes if the phrase is already quoted or if there is no
            // whitespace (in which case phrase searching is pointless and might
            // interfere with wildcard behavior):
            if (
                strstr($search, '"') || strstr($search, ' NOT ')
                || !preg_match('/\s/', $search)
            ) {
                $mungeValues['onephrase'] = $search;
            } else {
                $mungeValues['onephrase'] = sprintf('"%s"', $search);
            }
        }

        $mungeValues['identity'] = $search;

        foreach ($this->specs['CustomMunge'] as $mungeName => $mungeOps) {
            $mungeValues[$mungeName] = $search;
            foreach ($mungeOps as $operation) {
                $mungeValues[$mungeName]
                    = $this->customMunge($mungeValues[$mungeName], $operation);
            }
        }
        return $mungeValues;
    }

    /**
     * Apply custom search string munging to a Dismax query.
     *
     * @param string $search searchstring
     *
     * @return string
     */
    protected function dismaxMunge($search)
    {
        foreach ($this->specs['DismaxMunge'] as $operation) {
            $search = $this->customMunge($search, $operation);
        }
        return $search;
    }

    /**
     * Apply a munge operation to a search string.
     *
     * @param string $string    string to munge
     * @param array  $operation munge operation
     *
     * @return string
     */
    protected function customMunge($string, $operation)
    {
        switch ($operation[0]) {
            case 'append':
                $string .= $operation[1];
                break;
            case 'lowercase':
                $string = strtolower($string);
                break;
            case 'preg_replace':
                $string = preg_replace(
                    $operation[1],
                    $operation[2],
                    $string
                );
                break;
            case 'prepend':
                $string = $operation[1] . $string;
                break;
            case 'ucfirst':
                $string = ucfirst($string);
                break;
            case 'uppercase':
                $string = strtoupper($string);
                break;
            default:
                throw new \InvalidArgumentException(
                    sprintf('Unknown munge operation: %s', $operation[0])
                );
        }
        return $string;
    }

    /**
     * Return query string for specified search string.
     *
     * If optional argument $advanced is true the search string contains
     * advanced lucene query syntax.
     *
     * @param string $search   Search string
     * @param bool   $advanced Is the search an advanced search string?
     *
     * @return string
     */
    protected function createQueryString($search, $advanced = false)
    {
        // If this is a basic query and we have Dismax settings (or if we have
        // Extended Dismax available), let's build a Dismax subquery to avoid
        // some of the ugly side effects of our Lucene query generation logic.
        if (($this->hasExtendedDismax() || !$advanced) && $this->hasDismax()) {
            $query = $this->dismaxSubquery(
                $this->dismaxMunge($search)
            );
        } else {
            $mungeRules  = $this->mungeRules();
            // Do not munge w/o rules
            if ($mungeRules) {
                $mungeValues = $this->mungeValues($search, !$advanced);
                $query       = $this->munge($mungeRules, $mungeValues);
            } else {
                $query = $search;
            }
        }
        if ($this->hasFilterQuery()) {
            $query = sprintf('(%s) AND (%s)', $query, $this->getFilterQuery());
        }
        return "($query)";
    }

    /**
     * Return array of munge rules.
     *
     * @todo Maybe rename?
     *
     * @return array
     */
    protected function mungeRules()
    {
        return $this->specs['QueryFields'];
    }

    /**
     * Return modified search string after applying the transformation rules.
     *
     * @param array  $mungeRules  Munge rules
     * @param array  $mungeValues Munge values
     * @param string $joiner      Joiner of subqueries
     *
     * @return string
     */
    protected function munge(array $mungeRules, array $mungeValues, $joiner = 'OR')
    {
        $clauses = [];
        foreach ($mungeRules as $field => $clausearray) {
            if (is_numeric($field)) {
                // shift off the join string and weight
                $sw = array_shift($clausearray);
                $internalJoin = ' ' . $sw[0] . ' ';
                // Build it up recursively
                $sstring = '(' .
                    $this->munge($clausearray, $mungeValues, $internalJoin) .
                    ')';
                // ...and add a weight if we have one
                $weight = intval($sw[1] ?? 0);
                if ($weight > 0) {
                    $sstring .= '^' . $weight;
                }
                // push it onto the stack of clauses
                $clauses[] = $sstring;
            } else {
                // Otherwise, we've got a (list of) [munge, weight] pairs to deal
                // with
                foreach ($clausearray as $spec) {
                    // build a string like title:("one two")
                    $sstring = $field . ':(' . $mungeValues[$spec[0]] . ')';
                    // Add the weight if we have one. Yes, I know, it's redundant
                    // code.
                    $weight = intval($spec[1] ?? 0);
                    if ($weight > 0) {
                        $sstring .= '^' . $weight;
                    }
                    // ..and push it on the stack of clauses
                    $clauses[] = $sstring;
                }
            }
        }

        // Join it all together
        return implode(' ' . $joiner . ' ', $clauses);
    }

    /**
     * Tokenize the search string.
     *
     * @param string $string Search string
     *
     * @return array
     */
    protected function tokenize($string)
    {
        // First replace escaped quotes with a non-printable character that will
        // never be found in user input (ASCII 26, "substitute"). Next use a regex
        // to split on whitespace and quoted phrases. Finally, swap the "substitute"
        // characters back to escaped quotes. This allows for a simpler regex.
        $string = str_replace('\\"', chr(26), $string);
        preg_match_all('/[^\s"]+|"([^"]*)"/', $string, $phrases);
        $callback = function ($str) {
            return str_replace(chr(26), '\\"', $str);
        };
        $phrases = array_map($callback, $phrases[0]);

        $tokens  = [];
        $token   = [];

        reset($phrases);
        while (current($phrases) !== false) {
            $token[] = current($phrases);
            $next    = next($phrases);
            if (in_array($next, self::$booleanOperators)) {
                $token[] = $next;
                if (next($phrases) === false) {
                    $tokens[] = implode(' ', $token);
                }
            } else {
                $tokens[] = implode(' ', $token);
                $token = [];
            }
        }

        return $tokens;
    }
}
