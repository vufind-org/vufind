<?php
/**
 * Solr Utility Functions
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Solr;

/**
 * Solr Utility Functions
 *
 * This class is designed to hold Solr-related support methods that may
 * be called statically.  This allows sharing of some Solr-related logic
 * between the Solr and Summon classes.
 *
 * @category VuFind
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Utils
{
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
        return ['from' => trim($matches[1]), 'to' => trim($matches[2])];
    }

    /**
     * Convert a raw string date (as, for example, from a MARC record) into a legal
     * Solr date string. Return null if conversion is impossible.
     *
     * @param string $date Date to convert.
     *
     * @return string|null
     */
    public static function sanitizeDate($date)
    {
        // Strip brackets; we'll assume guesses are correct.
        $date = str_replace(['[', ']'], '', $date);

        // Special case -- first four characters are not a year:
        if (!preg_match('/^[0-9]{4}/', $date)) {
            // 'n.d.' means no date known -- give up!
            if (preg_match('/^n\.?\s*d\.?$/', $date)) {
                return null;
            }

            // Check for month/year or month-year formats:
            if (preg_match('/([0-9])(-|\/)([0-9]{4})/', $date, $matches)
                || preg_match('/([0-9]{2})(-|\/)([0-9]{4})/', $date, $matches)
            ) {
                $month = $matches[1];
                $year = $matches[3];
                $date = "$year-$month";
            } else {
                // strtotime can only handle a limited range of dates; let's extract
                // a year from the string and temporarily replace it with a known
                // good year; we'll swap it back after the conversion.
                $year = preg_match('/[0-9]{4}/', $date, $matches)
                    ? $matches[0] : false;
                if ($year) {
                    $date = str_replace($year, '1999', $date);
                }
                $time = @strtotime($date);
                if ($time) {
                    $date = @date("Y-m-d", $time);
                    if ($year) {
                        $date = str_replace('1999', $year, $date);
                    }
                } elseif ($year) {
                    // If the best we can do is extract a 4-digit year, that's better
                    // than nothing....
                    $date = $year;
                } else {
                    return null;
                }
            }
        }

        // If we've gotten this far, we at least know that we have a valid year.
        $year = substr($date, 0, 4);

        // Let's get rid of punctuation and normalize separators:
        $date = str_replace(['.', ' ', '?'], '', $date);
        $date = str_replace(['/', '--', '-0'], '-', $date);

        // If multiple dates are &'ed together, take just the first:
        [$date] = explode('&', $date);

        // Default to January 1 if no month/day present:
        if (strlen($date) < 5) {
            $month = $day = '01';
        } else {
            // If we have year + month, parse that out:
            if (strlen($date) < 8) {
                $day = '01';
                if (preg_match('/^[0-9]{4}-([0-9]{1,2})/', $date, $matches)) {
                    $month = str_pad($matches[1], 2, "0", STR_PAD_LEFT);
                } else {
                    $month = '01';
                }
            } else {
                // If we have year + month + day, parse that out:
                $ymdRegex = '/^[0-9]{4}-([0-9]{1,2})-([0-9]{1,2})/';
                if (preg_match($ymdRegex, $date, $matches)) {
                    $month = str_pad($matches[1], 2, "0", STR_PAD_LEFT);
                    $day = str_pad($matches[2], 2, "0", STR_PAD_LEFT);
                } else {
                    $month = $day = '01';
                }
            }
        }

        // Make sure month/day/year combination is legal. Make it legal if it isn't.
        if (!checkdate($month, $day, $year)) {
            $day = '01';
            if (!checkdate($month, $day, $year)) {
                $month = '01';
            }
        }

        return "{$year}-{$month}-{$day}T00:00:00Z";
    }
}
