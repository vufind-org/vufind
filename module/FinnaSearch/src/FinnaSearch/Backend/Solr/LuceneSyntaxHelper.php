<?php

/**
 * Lucene query syntax helper class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace FinnaSearch\Backend\Solr;

use VuFindCode\ISBN;
use VuFindSearch\Backend\Exception\BackendException;

/**
 * Lucene query syntax helper class.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class LuceneSyntaxHelper extends \VuFindSearch\Backend\Solr\LuceneSyntaxHelper
{
    /**
     * Unicode normalization form
     *
     * @var string
     */
    protected $unicodeNormalizationForm;

    /**
     * Search filters
     *
     * @var string
     */
    protected $searchFilters;

    /**
     * Maximum number of words in search query for spellcheck to be used
     */
    protected $maxSpellcheckWords;

    /**
     * Constructor.
     *
     * @param bool|string $csBools                  Case sensitive Booleans setting
     * @param bool        $csRanges                 Case sensitive ranges setting
     * @param string      $unicodeNormalizationForm UNICODE normalization form
     * @param array       $searchFilters            Regexp filters defined invalid
     * searches
     * @param int         $maxSpellcheckWords       Max number of words in query for
     * spellcheck to be used
     */
    public function __construct(
        $csBools = true, $csRanges = true, $unicodeNormalizationForm = 'NFKC',
        $searchFilters = [], $maxSpellcheckWords = 5
    ) {
        parent::__construct($csBools, $csRanges);
        $this->unicodeNormalizationForm = $unicodeNormalizationForm;
        $this->searchFilters = $searchFilters;
        $this->maxSpellcheckWords = $maxSpellcheckWords;
    }

    /**
     * Return normalized input string.
     *
     * @param string $searchString Input search string
     *
     * @return string
     */
    public function normalizeSearchString($searchString)
    {
        $searchString = parent::normalizeSearchString($searchString);
        $searchString = $this->normalizeUnicodeForm($searchString);
        $searchString = $this->normalizeISBN($searchString);

        foreach ($this->searchFilters as $i => $filter) {
            if (preg_match("/$filter/", $searchString)) {
                throw new BackendException(
                    "Search string '$searchString' matched filter '$filter'"
                );
            }
        }

        return $searchString;
    }

    /**
     * Perform final normalizations to a search string
     *
     * @param string $searchString Input search string
     *
     * @return string
     */
    public function finalizeSearchString($searchString)
    {
        return $this->normalizeUnquotedMinuses($searchString);
    }

    /**
     * Check if passed string is an ISBN and convert to ISBN-13
     *
     * @param string $searchString The query string
     *
     * @return valid ISBN-13 or the original string
     */
    protected function normalizeISBN($searchString)
    {
        if (!ISBN::isValidISBN10($searchString)) {
            return $searchString;
        }

        $isbn = new ISBN($searchString);
        return $isbn->get13();
    }

    /**
     * Normalize UNICODE form
     *
     * @param string $searchString Input search string
     *
     * @return string
     */
    protected function normalizeUnicodeForm($searchString)
    {
        switch ($this->unicodeNormalizationForm) {
        case 'NFC':
            return \Normalizer::normalize($searchString, \Normalizer::FORM_C);
        case 'NFD':
            return \Normalizer::normalize($searchString, \Normalizer::FORM_D);
        case 'NFKC':
            return \Normalizer::normalize($searchString, \Normalizer::FORM_KC);
        case 'NFKD':
            return \Normalizer::normalize($searchString, \Normalizer::FORM_KD);
        }

        return $searchString;
    }

    /**
     * Escape unquoted minus sign and convert occurrences of
     * unquoted exclamation + minus sign to minus.
     *
     * @param string $searchString Input search string
     *
     * @return string
     */
    protected function normalizeUnquotedMinuses($searchString)
    {
        $result = '';
        $inQuotes = false;
        $prev = '';
        $prev2 = '';
        foreach (str_split($searchString) as $c) {
            if ($c == '"') {
                $inQuotes = !$inQuotes;
            }
            if (!$inQuotes && '-' === $c) {
                if ('!' === $prev && "\\" !== $prev2) {
                    $result = substr($result, 0, -1) . '-';
                } elseif (' ' === $prev) {
                    $result .= "\\-";
                } else {
                    $result .= '-';
                }
            } else {
                $result .= $c;
            }
            $prev2 = $prev;
            $prev = $c;
        }

        return $result;
    }

    /**
     * Extract search terms from a query string for spell checking.
     *
     * This will only handle the most often used simple cases.
     *
     * @param string $query Query string
     *
     * @return string
     */
    public function extractSearchTerms($query)
    {
        $result = parent::extractsearchTerms($query);
        $result = $this->normalizeWildcards($result);
        return str_word_count($result) <= $this->maxSpellcheckWords
            ? $result : '';
    }

    /**
     * Normalize wildcards in a query.
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeWildcards($input)
    {
        $result = parent::normalizeWildcards($input);

        // Remove wildcards from beginning and end of string and when not part of a
        // range ([* TO *]) or an any value field (field:*)
        $result = preg_replace_callback(
            '/(^|[:\(]|[^\w\[\*]+?)([\*\?]+)($|[^\]\*])/u',
            function ($matches) {
                if (':' === $matches[1] && '*' === $matches[2]
                    && ('' === $matches[3] || strncmp(' ', $matches[3], 1) === 0)
                ) {
                    return ':*' . $matches[3];
                }
                return $matches[1] . $matches[3];
            },
            $result
        );

        /* TODO: will we need this?
        // Remove wildcards when preceded by only one or two characters
        $words = preg_split('/\s+/', $result);
        $fixed = [];
        foreach ($words as $word) {
            $word = preg_replace('/^(\w{1,2})[\*\?]+/u', '$1', $word);
            $fixed[] = $word;
        }
        $result = implode(' ', $fixed);
        */

        return $result;
    }
}
