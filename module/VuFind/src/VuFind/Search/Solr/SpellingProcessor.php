<?php

/**
 * Solr spelling processor.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Solr;

use Laminas\Config\Config;
use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;
use VuFindSearch\Query\AbstractQuery;

use function count;
use function in_array;
use function is_array;
use function strlen;

/**
 * Solr spelling processor.
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SpellingProcessor
{
    /**
     * Spelling limit
     *
     * @var int
     */
    protected $spellingLimit;

    /**
     * Spell check words with numbers in them?
     *
     * @var bool
     */
    protected $spellSkipNumeric;

    /**
     * Offer expansions on terms as well as basic replacements?
     *
     * @var bool
     */
    protected $expand;

    /**
     * Show the full modified search phrase on screen rather then just the suggested
     * word?
     *
     * @var bool
     */
    protected $phrase;

    /**
     * Callback for normalizing text.
     *
     * @var callable
     */
    protected $normalizer;

    /**
     * Constructor
     *
     * @param Config   $config     Spelling configuration (optional)
     * @param callable $normalizer Callback for normalization of text (optional).
     */
    public function __construct($config = null, $normalizer = null)
    {
        $this->spellingLimit = $config->limit ?? 3;
        $this->spellSkipNumeric = $config->skip_numeric ?? true;
        $this->expand = $config->expand ?? true;
        $this->phrase = $config->phrase ?? false;
        $this->normalizer = $normalizer;
    }

    /**
     * Are we skipping numeric words?
     *
     * @return bool
     */
    public function shouldSkipNumericSpelling()
    {
        return $this->spellSkipNumeric;
    }

    /**
     * Get the spelling limit.
     *
     * @return int
     */
    public function getSpellingLimit()
    {
        return $this->spellingLimit;
    }

    /**
     * Input Tokenizer - Specifically for spelling purposes
     *
     * Because of its focus on spelling, these tokens are unsuitable
     * for actual searching. They are stripping important search data
     * such as joins and groups, simply because they don't need to be
     * spellchecked.
     *
     * @param string $input Query to tokenize
     *
     * @return array        Tokenized array
     */
    public function tokenize($input)
    {
        // Exclusion list of useless tokens:
        $joins = ['AND', 'OR', 'NOT'];

        // Strip out parentheses -- irrelevant for tokenization:
        $paren = ['(' => ' ', ')' => ' '];
        $input = trim(strtr($input, $paren));

        // Base of this algorithm comes straight from PHP doc example by
        // benighted at gmail dot com: http://php.net/manual/en/function.strtok.php
        $tokens = [];
        $token = strtok($input, " \t");
        while ($token !== false) {
            // find double quoted tokens
            if (str_starts_with($token, '"') && !str_ends_with($token, '"')) {
                $token .= ' ' . strtok('"') . '"';
            }
            // skip boolean operators
            if (!in_array($token, $joins)) {
                $tokens[] = $token;
            }
            $token = strtok(" \t");
        }

        // If the last token ends in a double quote but the input string does not,
        // the tokenization process added the quote, which will break spelling
        // replacements. We need to strip it back off again:
        $last = count($tokens) > 0 ? $tokens[count($tokens) - 1] : null;
        if ($last && str_ends_with($last, '"') && !str_ends_with($input, '"')) {
            $tokens[count($tokens) - 1] = substr($last, 0, strlen($last) - 1);
        }
        return $tokens;
    }

    /**
     * Get raw spelling suggestions for a query.
     *
     * @param Spellcheck    $spellcheck Complete spellcheck information
     * @param AbstractQuery $query      Query for which info should be retrieved
     *
     * @return array
     * @throws \Exception
     */
    public function getSuggestions(Spellcheck $spellcheck, AbstractQuery $query)
    {
        $allSuggestions = [];
        foreach ($spellcheck as $term => $info) {
            if (
                !$this->shouldSkipTerm($query, $term, false)
                && ($suggestions = $this->formatAndFilterSuggestions($query, $info))
            ) {
                $allSuggestions[$term] = [
                    'freq' => $info['origFreq'],
                    'suggestions' => $suggestions,
                ];
            }
        }
        // Fail over to secondary suggestions if primary failed:
        if (empty($allSuggestions) && ($secondary = $spellcheck->getSecondary())) {
            return $this->getSuggestions($secondary, $query);
        }
        return $allSuggestions;
    }

    /**
     * Support method for getSuggestions()
     *
     * @param AbstractQuery $query Query for which info should be retrieved
     * @param array         $info  Spelling suggestion information
     *
     * @return array
     * @throws \Exception
     */
    protected function formatAndFilterSuggestions($query, $info)
    {
        // Validate response format
        if (isset($info['suggestion'][0]) && !is_array($info['suggestion'][0])) {
            throw new \Exception(
                'Unexpected suggestion format; spellcheck.extendedResults'
                . ' must be set to true.'
            );
        }
        $limit = $this->getSpellingLimit();
        $suggestions = [];
        foreach ($info['suggestion'] as $suggestion) {
            if (count($suggestions) >= $limit) {
                break;
            }
            $word = $suggestion['word'];
            if (!$this->shouldSkipTerm($query, $word, true)) {
                $suggestions[$word] = $suggestion['freq'];
            }
        }
        return $suggestions;
    }

    /**
     * Should we skip the specified term?
     *
     * @param AbstractQuery $query         Query for which info should be retrieved
     * @param string        $term          Term to check
     * @param bool          $queryContains Should we skip the term if it is found
     * in the query (true), or should we skip the term if it is NOT found in the
     * query (false)?
     *
     * @return bool
     */
    protected function shouldSkipTerm($query, $term, $queryContains)
    {
        // If term is numeric and we're in "skip numeric" mode, we should skip it:
        if ($this->shouldSkipNumericSpelling() && is_numeric($term)) {
            return true;
        }
        // We should also skip terms already contained within the query:
        return $queryContains == $query->containsTerm($term, $this->normalizer);
    }

    /**
     * Process spelling suggestions.
     *
     * @param array  $suggestions Raw suggestions from getSuggestions()
     * @param string $query       Spelling query
     * @param Params $params      Params helper object
     *
     * @return array
     */
    public function processSuggestions($suggestions, $query, Params $params)
    {
        $returnArray = [];
        foreach ($suggestions as $term => $details) {
            // Find out if our suggestion is part of a token
            $inToken = false;
            $targetTerm = '';
            foreach ($this->tokenize($query) as $token) {
                // Is the term part of the current token?
                if (str_contains($token, (string)$term)) {
                    $inToken = true;
                    // We need to replace the whole token
                    $targetTerm = $token;
                    // Go and replace this token
                    $returnArray = $this->doSingleReplace(
                        $term,
                        $targetTerm,
                        $inToken,
                        $details,
                        $returnArray,
                        $params
                    );
                }
            }
            // If no tokens were found, just look for the suggestion 'as is'
            if ($targetTerm == '') {
                $targetTerm = $term;
                $returnArray = $this->doSingleReplace(
                    $term,
                    $targetTerm,
                    $inToken,
                    $details,
                    $returnArray,
                    $params
                );
            }
        }
        return $returnArray;
    }

    /**
     * Process one instance of a spelling replacement and modify the return
     *   data structure with the details of what was done.
     *
     * @param string $term        The actually term we're replacing
     * @param string $targetTerm  The term above, or the token it is inside
     * @param bool   $inToken     Flag for whether the token or term is used
     * @param array  $details     The spelling suggestions
     * @param array  $returnArray Return data structure so far
     * @param Params $params      Params helper object
     *
     * @return array              $returnArray modified
     */
    protected function doSingleReplace(
        $term,
        $targetTerm,
        $inToken,
        $details,
        $returnArray,
        Params $params
    ) {
        $returnArray[$targetTerm]['freq'] = $details['freq'];
        foreach ($details['suggestions'] as $word => $freq) {
            // If the suggested word is part of a token, we need to make sure we
            // replace the whole token:
            $replacement = $inToken ? str_replace($term, $word, $targetTerm) : $word;

            //  Do we need to show the whole, modified query?
            $label = $this->phrase
                ? $params->getDisplayQueryWithReplacedTerm(
                    $targetTerm,
                    $replacement
                ) : $replacement;

            // Basic spelling suggestion data
            $returnArray[$targetTerm]['suggestions'][$label] = [
                'freq' => $freq,
                'new_term' => $replacement,
            ];

            // Only generate expansions if enabled in config
            if ($this->expand) {
                // Parentheses differ for shingles
                $replacement = (strstr($targetTerm, ' ') !== false)
                    ? "(($targetTerm) OR ($replacement))"
                    : "($targetTerm OR $replacement)";
                $returnArray[$targetTerm]['suggestions'][$label]['expand_term']
                    = $replacement;
            }
        }

        return $returnArray;
    }
}
