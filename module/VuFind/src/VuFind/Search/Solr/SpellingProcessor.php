<?php
/**
 * Solr spelling processor.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Solr;
use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;
use VuFindSearch\Query\AbstractQuery;
use Zend\Config\Config;

/**
 * Solr spelling processor.
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class SpellingProcessor
{
    /**
     * Spelling limit
     *
     * @var int
     */
    protected $spellingLimit = 3;

    /**
     * Spell check words with numbers in them?
     *
     * @var bool
     */
    protected $spellSkipNumeric = true;

    /**
     * Offer expansions on terms as well as basic replacements?
     *
     * @var bool
     */
    protected $expand = true;

    /**
     * Show the full modified search phrase on screen rather then just the suggested
     * word?
     *
     * @var bool
     */
    protected $phrase = false;

    /**
     * Constructor
     *
     * @param Config $config Spelling configuration (optional)
     */
    public function __construct($config = null)
    {
        if (isset($config->limit)) {
            $this->spellingLimit = $config->limit;
        }
        if (isset($config->skip_numeric)) {
            $this->spellSkipNumeric = $config->skip_numeric;
        }
        if (isset($config->expand)) {
            $this->expand = $config->expand;
        }
        if (isset($config->phrase)) {
            $this->phrase = $config->phrase;
        }
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
     * Get raw spelling suggestions for a query.
     *
     * @param Spellcheck    $spellcheck Complete spellcheck information
     * @param AbstractQuery $query      Query for which info should be retrieved
     *
     * @return array
     */
    public function getSuggestions(Spellcheck $spellcheck, AbstractQuery $query)
    {
        $allSuggestions = array();
        foreach ($spellcheck as $term => $info) {
            if ($this->shouldSkipNumericSpelling() && is_numeric($term)) {
                continue;
            }
            // Term is not part of the query
            if (!$query->containsTerm($term)) {
                continue;
            }
            // Filter out suggestions that are already part of the query
            $suggestionLimit = $this->getSpellingLimit();
            $suggestions     = array();
            foreach ($info['suggestion'] as $suggestion) {
                if (count($suggestions) >= $suggestionLimit) {
                    break;
                }
                $word = $suggestion['word'];
                if (!$query->containsTerm($word)) {
                    // Note: !a || !b eq !(a && b)
                    if (!is_numeric($word) || !$this->shouldSkipNumericSpelling()) {
                        $suggestions[$word] = $suggestion['freq'];
                    }
                }
            }
            if ($suggestions) {
                $allSuggestions[$term] = array(
                    'freq' => $info['origFreq'],
                    'suggestions' => $suggestions
                );
            }
        }
        // Fail over to secondary suggestions if primary failed:
        if (empty($allSuggestions) && ($secondary = $spellcheck->getSecondary())) {
            return $this->getSuggestions($secondary, $query);
        }
        return $allSuggestions;
    }

    /**
     * Process spelling suggestions.
     *
     * @param array  $suggestions Raw suggestions from getSuggestions()
     * @param array  $tokens      Tokenized spelling query
     * @param Params $params      Params helper object
     *
     * @return array
     */
    public function processSuggestions($suggestions, $tokens, Params $params)
    {
        $returnArray = array();
        foreach ($suggestions as $term => $details) {
            // Find out if our suggestion is part of a token
            $inToken = false;
            $targetTerm = "";
            foreach ($tokens as $token) {
                // TODO - Do we need stricter matching here, similar to that in
                // \VuFindSearch\Query\Query::replaceTerm()?
                if (stripos($token, $term) !== false) {
                    $inToken = true;
                    // We need to replace the whole token
                    $targetTerm = $token;
                    // Go and replace this token
                    $returnArray = $this->doSingleReplace(
                        $term, $targetTerm, $inToken, $details, $returnArray, $params
                    );
                }
            }
            // If no tokens were found, just look for the suggestion 'as is'
            if ($targetTerm == "") {
                $targetTerm = $term;
                $returnArray = $this->doSingleReplace(
                    $term, $targetTerm, $inToken, $details, $returnArray, $params
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
    protected function doSingleReplace($term, $targetTerm, $inToken, $details,
        $returnArray, Params $params
    ) {
        $returnArray[$targetTerm]['freq'] = $details['freq'];
        foreach ($details['suggestions'] as $word => $freq) {
            // If the suggested word is part of a token
            if ($inToken) {
                // We need to make sure we replace the whole token
                $replacement = str_replace($term, $word, $targetTerm);
            } else {
                $replacement = $word;
            }
            //  Do we need to show the whole, modified query?
            if ($this->phrase) {
                $label = $params->getDisplayQueryWithReplacedTerm(
                    $targetTerm, $replacement
                );
            } else {
                $label = $replacement;
            }
            // Basic spelling suggestion data
            $returnArray[$targetTerm]['suggestions'][$label] = array(
                'freq' => $freq,
                'new_term' => $replacement
            );

            // Only generate expansions if enabled in config
            if ($this->expand) {
                // Parentheses differ for shingles
                if (strstr($targetTerm, " ") !== false) {
                    $replacement = "(($targetTerm) OR ($replacement))";
                } else {
                    $replacement = "($targetTerm OR $replacement)";
                }
                $returnArray[$targetTerm]['suggestions'][$label]['expand_term']
                    = $replacement;
            }
        }

        return $returnArray;
    }
}