<?php

/**
 * XSLT importer support methods for work key generation.
 *
 * PHP version 8
 *
 * Copyright (c) Demian Katz 2020.
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
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing Wiki
 */

namespace VuFind\XSLT\Import;

use DOMDocument;
use Normalizer;

use function in_array;

/**
 * XSLT importer support methods for work key generation.
 *
 * @category VuFind
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing Wiki
 */
class VuFindWorkKeys
{
    /**
     * Get all work identification keys for the record.
     *
     * @param Iterable $uniformTitles       Uniform title(s) for the work
     * @param Iterable $titles              Other title(s) for the work
     * @param Iterable $trimmedTitles       Title(s) with leading articles, etc.,
     * removed
     * @param Iterable $authors             Author(s) for the work
     * @param string   $includeRegEx        Regular expression defining characters to
     * keep
     * @param string   $excludeRegEx        Regular expression defining characters to
     * remove
     * @param string   $transliteratorRules Optional ICU transliteration rules to be
     * applied before the include and exclude regex's. See
     * https://unicode-org.github.io/icu/userguide/transforms/general/
     * #icu-transliterators for more information on the transliteration rules.
     *
     * @return DOMDocument
     */
    public static function getWorkKeys(
        $uniformTitles,
        $titles,
        $trimmedTitles,
        $authors,
        $includeRegEx = '',
        $excludeRegEx = '',
        $transliteratorRules = ''
    ) {
        $transliterator = $transliteratorRules
            ? \Transliterator::createFromRules(
                $transliteratorRules,
                \Transliterator::FORWARD
            ) : null;

        $dom = new DOMDocument('1.0', 'utf-8');

        $uniformTitles = is_iterable($uniformTitles)
            ? $uniformTitles : (array)$uniformTitles;
        foreach ($uniformTitles as $uniformTitle) {
            $normalizedTitle = self::normalize(
                $uniformTitle,
                $includeRegEx,
                $excludeRegEx,
                $transliterator
            );
            if (!empty($normalizedTitle)) {
                $element = $dom->createElement('workKey', 'UT ' . $normalizedTitle);
                $dom->appendChild($element);
            }
        }

        // Exit early if there are no authors, since we can't make author/title keys:
        $authors = is_iterable($authors) ? $authors : (array)$authors;
        if (empty($authors)) {
            return $dom;
        }
        $titles = $titles instanceof \Traversable
            ? iterator_to_array($titles) : (array)$titles;
        $trimmedTitles = $trimmedTitles instanceof \Traversable
            ? iterator_to_array($trimmedTitles) : (array)$trimmedTitles;
        $normalizedTitles = [];
        foreach (array_merge($titles, $trimmedTitles) as $title) {
            $normalizedTitle = self::normalize(
                $title,
                $includeRegEx,
                $excludeRegEx,
                $transliterator
            );
            if (
                empty($normalizedTitle)                          // skip empties
                || in_array($normalizedTitle, $normalizedTitles) // avoid dupes
            ) {
                continue;
            }
            $normalizedTitles[] = $normalizedTitle;
            foreach ($authors as $author) {
                $normalizedAuthor = self::normalize(
                    $author,
                    $includeRegEx,
                    $excludeRegEx,
                    $transliterator
                );
                if (!empty($author)) {
                    $key = 'AT ' . $normalizedAuthor . ' ' . $normalizedTitle;
                    $element = $dom->createElement('workKey', $key);
                    $dom->appendChild($element);
                }
            }
        }

        return $dom;
    }

    /**
     * Force a value to a string, even if it's a DOMElement.
     *
     * @param string|DOMElement $string String to normalize
     *
     * @return string
     */
    protected static function deDom($string): string
    {
        return $string->textContent ?? (string)$string;
    }

    /**
     * Create a key string.
     *
     * @param string|DOMElement $rawString      String to normalize
     * @param string            $includeRegEx   Regular expression defining
     * characters to keep
     * @param string            $excludeRegEx   Regular expression defining
     * characters to remove
     * @param \Transliterator   $transliterator Transliterator
     *
     * @return string
     */
    protected static function normalize(
        $rawString,
        $includeRegEx,
        $excludeRegEx,
        $transliterator
    ) {
        // Handle strings and/or DOM elements:
        $string = self::deDom($rawString);
        $normalized = $transliterator ? $transliterator->transliterate($string)
            : Normalizer::normalize($string, Normalizer::FORM_KC);
        if (!empty($includeRegEx)) {
            preg_match_all($includeRegEx, $normalized, $matches);
            $normalized = implode($matches[0] ?? []);
        }
        if (!empty($excludeRegEx)) {
            $normalized = preg_replace($excludeRegEx, '', $normalized);
        }
        return mb_substr(mb_strtolower($normalized, 'UTF-8'), 0, 255, 'UTF-8');
    }
}
