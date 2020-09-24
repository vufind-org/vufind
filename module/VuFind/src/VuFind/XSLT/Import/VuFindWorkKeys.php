<?php
/**
 * XSLT importer support methods for work key generation.
 *
 * PHP version 7
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
     * @param Iterable $uniformTitles Uniform title(s) for the work
     * @param Iterable $titles        Other title(s) for the work
     * @param Iterable $authors       Author(s) for the work
     * @param string   $includeRegEx  Regular expression defining characters to keep
     * @param string   $excludeRegEx  Regular expression defining characters to
     * remove
     *
     * @return DOMDocument
     */
    public static function getWorkKeys($uniformTitles, $titles, $authors,
        $includeRegEx = '', $excludeRegEx = ''
    ) {
        $dom = new DOMDocument('1.0', 'utf-8');

        $uniformTitles = is_iterable($uniformTitles)
            ? $uniformTitles : (array)$uniformTitles;
        foreach ($uniformTitles as $uniformTitle) {
            $normalizedTitle
                = self::normalize($uniformTitle, $includeRegEx, $excludeRegEx);
            if (!empty($normalizedTitle)) {
                $element = $dom->createElement('workKey', 'UT ' . $normalizedTitle);
                $dom->appendChild($element);
            }
        }

        $titles = is_iterable($titles) ? $titles : (array)$titles;
        $authors = is_iterable($titles) ? $authors : (array)$titles;
        foreach ($titles as $title) {
            $normalizedTitle
                = self::normalize($title, $includeRegEx, $excludeRegEx);
            if (empty($normalizedTitle)) {
                continue;
            }
            foreach ($authors as $author) {
                $normalizedAuthor
                    = self::normalize($author, $includeRegEx, $excludeRegEx);
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
     * Create a key string.
     *
     * @param string $string       String to normalize
     * @param string $includeRegEx Regular expression defining characters to keep
     * @param string $excludeRegEx Regular expression defining characters to remove
     *
     * @return string
     */
    protected static function normalize($string, $includeRegEx = '',
        $excludeRegEx = ''
    ) {
        $normalized = Normalizer::normalize(
            // Handle strings and/or DOM elements:
            $string->textContent ?? (string)$string,
            Normalizer::FORM_KC
        );
        if (!empty($includeRegEx)) {
            preg_match_all($includeRegEx, $normalized, $matches);
            $normalized = implode($matches[0] ?? []);
        }
        if (!empty($excludeRegEx)) {
            $normalized = preg_replace($excludeRegEx, '', $normalized);
        }
        return strtolower($normalized);
    }
}
