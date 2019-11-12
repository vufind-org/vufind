<?php
/**
 * XSLT importer support methods for geographic indexing.
 *
 * PHP version 7
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
                list($key, $value) = $chunks;
                $parsed[$key] = $value;
            }
        }
        return $parsed;
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
        return isset($coords['westlimit'])
            && isset($coords['eastlimit'])
            && isset($coords['northlimit'])
            && isset($coords['southlimit']);
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
                $coords['westlimit'], $coords['eastlimit'],
                $coords['northlimit'], $coords['southlimit']
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
                $coords['westlimit'], $coords['eastlimit'],
                $coords['northlimit'], $coords['southlimit']
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
