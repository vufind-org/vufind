<?php

/**
 * XSLT importer support methods for geographic indexing.
 *
 * PHP version 8
 *
 * Copyright (c) Demian Katz 2019.
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

use function call_user_func;
use function count;
use function sprintf;

/**
 * XSLT importer support methods for geographic indexing.
 *
 * @category VuFind
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing Wiki
 */
class VuFindGeo
{
    /**
     * Method for logging errors (overridable for testing purposes)
     *
     * @var callable
     */
    public static $logMethod = 'error_log';

    /**
     * Log an error message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected static function logError($msg)
    {
        call_user_func(static::$logMethod, $msg);
    }

    /**
     * Parse a dc:coverage string into a key/value array.
     *
     * @param string $coverage Raw dc:coverage string.
     *
     * @return array
     */
    protected static function parseCoverage($coverage)
    {
        $parts = array_map('trim', explode(';', $coverage));
        $parsed = [];
        foreach ($parts as $part) {
            $chunks = array_map('trim', explode('=', $part, 2));
            if (count($chunks) == 2) {
                [$key, $value] = $chunks;
                $parsed[$key] = $value;
            }
        }
        return $parsed;
    }

    /**
     * Return true if the coordinate set is complete and numeric.
     *
     * @param array $coords Output of parseCoverage() in need of validation
     *
     * @return bool
     */
    protected static function validateNumericCoordinates($coords)
    {
        if (
            !is_numeric($coords['westlimit'] ?? 'NaN')
            || !is_numeric($coords['eastlimit'] ?? 'NaN')
            || !is_numeric($coords['northlimit'] ?? 'NaN')
            || !is_numeric($coords['southlimit'] ?? 'NaN')
        ) {
            static::logError('Missing or non-numeric coordinate value.');
            return false;
        }
        return true;
    }

    /**
     * Check decimal degree coordinates to make sure they do not form a line at the
     * poles.
     *
     * @param array $coords Output of parseCoverage() in need of validation
     *
     * @return bool
     */
    protected static function validateLines($coords)
    {
        if (
            $coords['westlimit'] != $coords['eastlimit']
            && $coords['northlimit'] == $coords['southlimit']
            && abs($coords['northlimit']) == 90
        ) {
            static::logError('Coordinates form a line at the pole');
            return false;
        }
        return true;
    }

    /**
     * Check decimal degree coordinates to make sure they are within map extent.
     *
     * @param array $coords Output of parseCoverage() in need of validation
     *
     * @return bool
     */
    protected static function validateExtent($coords)
    {
        if (
            abs($coords['northlimit']) > 90
            || abs($coords['southlimit']) > 90
            || abs($coords['eastlimit']) > 180
            || abs($coords['westlimit']) > 180
        ) {
            static::logError('Coordinates exceed map extent.');
            return false;
        }
        return true;
    }

    /**
     * Check decimal degree coordinates to make sure that north is not less than
     * south.
     *
     * @param array $coords Output of parseCoverage() in need of validation
     *
     * @return bool
     */
    protected static function validateNorthSouth($coords)
    {
        if ($coords['northlimit'] < $coords['southlimit']) {
            static::logError('North < South.');
            return false;
        }
        return true;
    }

    /**
     * Check decimal degree coordinates to make sure that east is not less than west.
     *
     * @param array $coords Output of parseCoverage() in need of validation
     *
     * @return bool
     */
    protected static function validateEastWest($coords)
    {
        $east = $coords['eastlimit'];
        $west = $coords['westlimit'];
        if ($east < $west) {
            // Convert to 360 degree grid
            if ($east <= 0) {
                $east += 360;
            }
            if ($west < 0) {
                $west += 360;
            }
            // Check again
            if ($east < $west) {
                static::logError('East < West.');
                return false;
            }
        }
        return true;
    }

    /**
     * Check decimal degree coordinates to make sure they are not too close.
     * Coordinates too close will cause Solr to run out of memory during indexing.
     *
     * @param array $coords Output of parseCoverage() in need of validation
     *
     * @return bool
     */
    protected static function validateCoordinateDistance($coords)
    {
        $distEW = $coords['eastlimit'] - $coords['westlimit'];
        $distNS = $coords['northlimit'] - $coords['southlimit'];
        if (
            ($coords['northlimit'] == -90 || $coords['southlimit'] == -90)
            && ($distNS > 0 && $distNS < 0.167)
        ) {
            static::logError(
                'Coordinates < 0.167 degrees from South Pole. Coordinate Distance: '
                . round($distNS, 2)
            );
            return false;
        }

        if (
            ($coords['westlimit'] == 0 || $coords['eastlimit'] == 0)
            && ($distEW > -2 && $distEW < 0)
        ) {
            static::logError(
                'Coordinates within 2 degrees of Prime Meridian. '
                . 'Coordinate Distance: ' . round($distEW, 2)
            );
            return false;
        }
        return true;
    }

    /**
     * Return true if the coordinate set is valid for inclusion in VuFind's index.
     *
     * @param array $coords Output of parseCoverage() in need of validation
     *
     * @return bool
     */
    protected static function validateCoverageCoordinates($coords)
    {
        return static::validateNumericCoordinates($coords)
            && static::validateLines($coords)
            && static::validateExtent($coords)
            && static::validateNorthSouth($coords)
            && static::validateEastWest($coords)
            && static::validateCoordinateDistance($coords);
    }

    /**
     * Format valid coordinates for indexing into Solr; return empty string if
     * coordinates are invalid.
     *
     * @param string $coverage Raw dc:coverage string.
     *
     * @return string
     */
    public static function getAllCoordinatesFromCoverage($coverage)
    {
        $coords = static::parseCoverage($coverage);
        return static::validateCoverageCoordinates($coords)
            ? sprintf(
                'ENVELOPE(%s,%s,%s,%s)',
                $coords['westlimit'],
                $coords['eastlimit'],
                $coords['northlimit'],
                $coords['southlimit']
            ) : null;
    }

    /**
     * Format valid coordinates for user display; return empty string if
     * coordinates are invalid.
     *
     * @param string $coverage Raw dc:coverage string.
     *
     * @return string
     */
    public static function getDisplayCoordinatesFromCoverage($coverage)
    {
        $coords = static::parseCoverage($coverage);
        return static::validateCoverageCoordinates($coords)
            ? sprintf(
                '%s %s %s %s',
                $coords['westlimit'],
                $coords['eastlimit'],
                $coords['northlimit'],
                $coords['southlimit']
            ) : null;
    }

    /**
     * Extract a label from a dc:coverage string.
     *
     * @param string $coverage Raw dc:coverage string.
     *
     * @return string
     */
    public static function getLabelFromCoverage($coverage)
    {
        $coords = static::parseCoverage($coverage);
        return $coords['name'] ?? '';
    }
}
