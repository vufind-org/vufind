<?php
/**
 * Map tab
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace Finna\RecordTab;

/**
 * Map tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class Map extends \VuFind\RecordTab\Map
{
    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // Yes, no magic required
        return true;
    }

    /**
     * Get all map markers (points, polygons etc.)
     *
     * @return string
     */
    public function getMapMarkers()
    {
        $locations = $this->getRecordDriver()->tryMethod('getGeoLocations');
        if (empty($locations)) {
            return json_encode([]);
        }
        $markers = [];
        $center = $this->getRecordDriver()->tryMethod('getGeoCenter');
        foreach ($locations as $i => $location) {
            if (strstr($location, 'EMPTY') !== false) {
                continue;
            }
            $marker = $this->locationToMarker($location);
            $marker['title'] = (string)$this->getRecordDriver()->getBreadcrumb();
            if ($i == 0 && $center) {
                $marker['center'] = $center;
            }
            $markers[] = $marker;
        }
        return json_encode($markers);
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $locations = $this->getRecordDriver()->tryMethod('getGeoLocations');
        if (empty($locations)) {
            return false;
        }
        foreach ($locations as $location) {
            if (strstr($location, 'EMPTY') !== false) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * Convert WKT envelope to array
     *
     * @param string $envelope WKT envelope
     *
     * @return array Results
     */
    protected function envelopeToArray($envelope)
    {
        $array = [];
        $envelope = preg_replace('/.*\((.+)\).*/', '\\1', $envelope);
        list($minX, $maxX, $maxY, $minY) = explode(',', trim($envelope));
        return [
            [(float)$minY, (float)$minX],
            [(float)$minY, (float)$maxX],
            [(float)$maxY, (float)$maxX],
            [(float)$maxY, (float)$minX],
            [(float)$minY, (float)$minX]
        ];
    }

    /**
     * Convert WKT to array (support function for getGoogleMapMarker)
     *
     * @param string $location Well Known Text, envelope or simple point
     *
     * @return array A marker
     */
    protected function locationToMarker($location)
    {
        $wktTypes = [
            'coords', 'multicoords', 'linestring',
            'multilinestring', 'polygon', 'multipolygon', 'geometrycollection'
        ];

        $p = strpos($location, '(');
        $type = strtolower(trim(substr($location, 0, $p)));

        if ($p > 0 && in_array($type, $wktTypes)) {
            return ['wkt' => $location];
        }

        if ($type == 'point' || $type == 'multipoint') {
            $isPoint = preg_match_all(
                '/\((.+)\s+?(.+)\)/', $location, $matches, PREG_SET_ORDER
            );
            if ($isPoint) {
                $results = [];
                foreach ($matches as $match) {
                    $results[] = [
                        'lon' => (float)$match[1],
                        'lat' => (float)$match[2]
                    ];
                }
                return [
                    'points' => $results
                ];
            }
            return null;
        }

        if ($type == 'envelope') {
            return [
                'polygon' => [
                    $this->envelopeToArray($location)
                ]
            ];
        }

        $coordinates = explode(' ', $location);
        if (count($coordinates) > 2) {
            $polygon = [];
            // Assume rectangle
            $lon = (float)$coordinates[0];
            $lat = (float)$coordinates[1];
            $lon2 = (float)$coordinates[2];
            $lat2 = (float)$coordinates[3];
            $polygon[] = [$lat, $lon];
            $polygon[] = [$lat, $lon2];
            $polygon[] = [$lat2, $lon2];
            $polygon[] = [$lat2, $lon];
            $polygon[] = [$lat, $lon];
            return [
                'polygon' => [$polygon]
            ];
        }
        return [
            'points' => [
                [
                    'lon' => $coordinates[0],
                    'lat' => $coordinates[1]
                ]
            ]
        ];
    }
}
