<?php

/**
 * Default text normalizer for spellcheck text replacement.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Normalizer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Normalizer;

use function in_array;

/**
 * Default text normalizer for spellcheck text replacement.
 *
 * @category VuFind
 * @package  Normalizer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DefaultSpellingNormalizer
{
    /**
     * Apply normalization to a string.
     *
     * @param string $text String to normalize.
     *
     * @return string
     */
    public function __invoke($text)
    {
        // The input to the function may be a Solr query with Boolean operators
        // in it; we want to be careful not to turn this into something invalid.
        $stripped = $this->stripDiacritics($text);
        $booleans = ['AND', 'OR', 'NOT'];
        $words = [];
        foreach (preg_split('/\s+/', $stripped) as $word) {
            $words[] = in_array($word, $booleans) ? $word : mb_strtolower($word, 'UTF-8');
        }
        return implode(' ', $words);
    }

    /**
     * Remove diacritics (accents, umlauts, etc.) from a string
     *
     * @param string $string The text where we would like to remove diacritics
     *
     * @return string The input text with diacritics removed
     */
    protected function stripDiacritics($string)
    {
        // See http://userguide.icu-project.org/transforms/general for
        // an explanation of this.
        $transliterator = \Transliterator::createFromRules(
            ':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;',
            \Transliterator::FORWARD
        );
        return $transliterator->transliterate($string);
    }
}
