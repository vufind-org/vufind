<?php
/**
 * Solr Utility Functions
 *
 * PHP version 5
 *
 * Copyright (C) Andrew Nagy 2009.
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
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind\Solr;

/**
 * Solr Utility Functions
 *
 * This class is designed to hold Solr-related support methods that may
 * be called statically.  This allows sharing of some Solr-related logic
 * between the Solr and Summon classes.
 *
 * @category VuFind2
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class Utils
{
    // This lookahead detects whether or not we are inside quotes; it
    // may be shared by multiple methods.
    protected static $insideQuotes = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';

    /**
     * Capitalize boolean operators in a query string to allow case-insensitivity.
     *
     * @param string $query The query to capitalize.
     *
     * @return string       The capitalized query.
     */
    public static function capitalizeBooleans($query)
    {
        // Load the "inside quotes" lookahead so we can use it to prevent
        // switching case of Boolean reserved words inside quotes, since
        // that can cause problems in case-sensitive fields when the reserved
        // words are actually used as search terms.
        $lookahead = self::$insideQuotes;
        $regs = array("/\s+AND\s+{$lookahead}/i", "/\s+OR\s+{$lookahead}/i",
                "/(\s+NOT\s+|^NOT\s+){$lookahead}/i", "/\(NOT\s+{$lookahead}/i");
        $replace = array(' AND ', ' OR ', ' NOT ', '(NOT ');
        return trim(preg_replace($regs, $replace, $query));
    }

    /**
     * Make ranges case-insensitive in a query string.
     *
     * @param string $query The query to update.
     *
     * @return string       The query with case-insensitive ranges.
     */
    public static function capitalizeRanges($query)
    {
        // Load the "inside quotes" lookahead so we can use it to prevent
        // switching case of ranges inside quotes, since that can cause
        // problems in case-sensitive fields when the reserved words are
        // actually used as search terms.
        $lookahead = self::$insideQuotes;
        $regs = array("/(\[)([^\]]+)\s+TO\s+([^\]]+)(\]){$lookahead}/i",
            "/(\{)([^}]+)\s+TO\s+([^}]+)(\}){$lookahead}/i");
        $callback = array(get_called_class(), 'capitalizeRangesCallback');
        return trim(preg_replace_callback($regs, $callback, $query));
    }

    /**
     * Support method for capitalizeRanges -- process a single match found by
     * preg_replace_callback.
     *
     * @param array $in Array of matches.
     *
     * @return string   Processed result.
     */
    public static function capitalizeRangesCallback($in)
    {
        // Extract the relevant parts of the expression:
        $open = $in[1];         // opening symbol
        $close = $in[4];        // closing symbol
        $start = $in[2];        // start of range
        $end = $in[3];          // end of range

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
     * Parse "from" and "to" values out of a range query (or return false if the
     * query is not a range).
     *
     * @param string $query Solr query to parse.
     *
     * @return array|bool   Array with 'from' and 'to' values extracted from range
     * or false if the provided query is not a range.
     */
    public static function parseRange($query)
    {
        $regEx = '/\[([^\]]+)\s+TO\s+([^\]]+)\]/';
        if (!preg_match($regEx, $query, $matches)) {
            return false;
        }
        return array('from' => trim($matches[1]), 'to' => trim($matches[2]));
    }
}
