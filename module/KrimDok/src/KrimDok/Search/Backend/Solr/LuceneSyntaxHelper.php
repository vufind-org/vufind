<?php

/**
 * Lucene query syntax helper class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace IxTheo\Search\Backend\Solr;

/**
 * Lucene query syntax helper class.
 *
 * @category VuFind
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LuceneSyntaxHelper extends \VuFindSearch\Backend\Solr\LuceneSyntaxHelper
{
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
        $result = [];
        $inQuotes = false;
        $collected = '';
        $discardParens = 0;
        // Discard fuzziness and proximity indicators
        $query = preg_replace('/\~[^\s]*/', '', $query);
        $query = preg_replace('/\^[^\s]*/', '', $query);
        $lastCh = '';
        foreach (str_split($query) as $ch) {
            // Handle quotes (everything in quotes is considered part of search
            // terms)
            if ($ch == '"' && $lastCh != '\\') {
                $inQuotes = !$inQuotes;
            }
            if (!$inQuotes) {
                // Discard closing parenthesis for previously discarded opening ones
                // to keep balance
                if ($ch == ')' && $discardParens > 0) {
                    --$discardParens;
                    continue;
                }
                // Flush to result array on word break
                if ($ch == ' ' && $collected !== '') {
                    $result[] = $collected;
                    $collected = '';
                    continue;
                }
                // If we encounter ':', discard preceding string as it's a field name
                if ($ch == ':') {
                    // Take into account any opening parenthesis we discard here
                    $discardParens += substr_count($collected, '(');
                    $collected = '';
                    continue;
                }
            }
            $collected .= $ch;
            $lastCh = $ch;
        }
        // Flush final collected string
        if ($collected !== '') {
            $result[] = $collected;
        }
        // Discard any preceding pluses or minuses
        $result = array_map(
            function ($s) {
                return ltrim($s, '+-');
            },
            $result
        );
        return implode(' ', $result);
    }


    /**
     * Normalize braces/brackets in a query.
     *
     * IMPORTANT: This should only be called on a string that has already been
     * cleaned up by normalizeBoosts().
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeBracesAndBrackets($input)
    {

        // Special handling for multiLanguageSupport
	$skip_pattern = '/{.*\!.*multiLanguageQueryParser.*\}/';

        if (preg_match($skip_pattern, $input))
            return $input;


        // Remove unwanted brackets/braces that are not part of range queries.
        // This is a bit of a shell game -- first we replace valid brackets and
        // braces with tokens that cannot possibly already be in the query (due
        // to the work of normalizeBoosts()).  Next, we remove all remaining
        // invalid brackets/braces, and transform our tokens back into valid ones.
        // Obviously, the order of the patterns/merges array is critically
        // important to get this right!!

        $patterns = [
            // STEP 1 -- escape valid brackets/braces
            '/\[([^\[\]\s]+\s+TO\s+[^\[\]\s]+)\]/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            '/\{([^\{\}\s]+\s+TO\s+[^\{\}\s]+)\}/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            // STEP 2 -- destroy remaining brackets/braces
            '/[\[\]\{\}]/',
            // STEP 3 -- unescape valid brackets/braces
            '/\^\^lbrack\^\^/', '/\^\^rbrack\^\^/',
            '/\^\^lbrace\^\^/', '/\^\^rbrace\^\^/'];
        $matches = [
            // STEP 0 -- keep local parameters
            //'\1',
            // STEP 1 -- escape valid brackets/braces
            '^^lbrack^^$1^^rbrack^^', '^^lbrace^^$1^^rbrace^^',
            // STEP 2 -- destroy remaining brackets/braces
            '',
            // STEP 3 -- unescape valid brackets/braces
            '[', ']', '{', '}'];
        return preg_replace($patterns, $matches, $input);
    }

}
