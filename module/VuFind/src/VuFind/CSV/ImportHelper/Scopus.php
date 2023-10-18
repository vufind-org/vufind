<?php

/**
 * Helpers for Scopus CSV import example.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  CSV
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */

namespace VuFind\CSV\ImportHelper;

use function strlen;

/**
 * Helpers for Scopus CSV import example.
 *
 * @category VuFind
 * @package  CSV
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class Scopus
{
    /**
     * Is the provided text the abbreviation part of a name string?
     *
     * @param string $text Text to check
     *
     * @return bool
     */
    protected static function isNameAbbreviation(string $text): bool
    {
        // A single character is very likely an abbreviation:
        if (strlen($text) === 1) {
            return true;
        }
        // A set of initials, possibly hyphen or space separated, is very likely
        // an abbreviation:
        return preg_match('/^(.\.[- ]*)+$/', $text);
    }

    /**
     * Given a string of multiple last name/initial pairs, split it into an array
     * of name strings.
     *
     * @param string $names     Names to split
     * @param bool   $firstOnly Set to true to return just the first extracted value
     *
     * @return string[]
     */
    public static function splitNames(string $names, bool $firstOnly = false): array
    {
        $parts = explode(', ', $names);
        $result = [];
        while (!empty($parts)) {
            $next = array_shift($parts);
            // Look ahead: if the text element is a set of initials, it's part of
            // the current name.
            if (static::isNameAbbreviation($parts[0] ?? '')) {
                $next .= ', ' . array_shift($parts);
            }
            $result[] = $next;
            if ($firstOnly) {
                return $result;
            }
        }
        return $result;
    }
}
