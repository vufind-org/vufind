<?php
/**
 * Map tab
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
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
        foreach ($locations as $location) {
            $marker = $this->wktToMarker($location);
            $marker['title'] = (string)$this->getRecordDriver()->getBreadcrumb();
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
        if (!$this->enabled) {
            return false;
        }
        $locations = $this->getRecordDriver()->tryMethod('getGeoLocations');
        return !empty($locations);
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
        list($minX, $maxX, $maxY, $minY) = explode(' ', trim($envelope));
        // Workaround for jquery geo issue preventing polygon with longitude
        // -180.0 from being displayed (https://github.com/AppGeo/geo/issues/128)
        if ((float)$minX === -180) {
            $minX = -179.9999999;
        }
        return [
            [(float)$minX, (float)$minY],
            [(float)$minX, (float)$maxY],
            [(float)$maxX, (float)$maxY],
            [(float)$maxX, (float)$minY],
            [(float)$minX, (float)$minY]
        ];
    }

    /**
     * Convert WKT polygon to array
     *
     * @param string $polygon WKT polygon
     *
     * @return array Results
     */
    protected function polygonToArray($polygon)
    {
        $array = [];
        $polygon = preg_replace('/.*\((.+)\).*/', '\\1', $polygon);
        foreach (explode(',', $polygon) as $point) {
            list($lon, $lat) = explode(' ', trim($point), 2);
            // Workaround for jquery geo issue preventing polygon with longitude
            // -180.0 from being displayed (https://github.com/AppGeo/geo/issues/128)
            if ((float)$lon === -180) {
                $lon = -179.9999999;
            }
            $array[] = [(float)$lon, (float)$lat];
        }
        return $array;
    }

    /**
     * Convert WKT to array (support function for getGoogleMapMarker)
     *
     * @param string $wkt Well Known Text
     *
     * @return array A marker with title and other attributes
     */
    protected function wktToMarker($wkt)
    {
        if (strtolower(substr($wkt, 0, 5)) == 'point') {
            if (preg_match('/\((.+)\s+(.+)\)/', $wkt, $matches)) {
                return [
                    'lon' => (float)$matches[1],
                    'lat' => (float)$matches[2]
                ];
            }
            return null;
        } elseif (strtolower(substr($wkt, 0, 7)) == 'polygon') {
            if (preg_match('/\((\(.+\))\s*,\s*(\(.+\))\)/', $wkt, $matches)) {
                return [
                    'polygon' => [
                        $this->polygonToArray($matches[1]),
                        $this->polygonToArray($matches[2])
                    ]
                ];
            } else {
                $wkt = preg_replace('/.*\((.+)\).*/', '\\1', $wkt);
                return [
                    'polygon' => [
                        $this->polygonToArray($wkt)
                    ]
                ];
            }
        } elseif (strtolower(substr($wkt, 0, 12)) == 'multipolygon') {
            preg_match_all('/(\(\(.+?\)\))/', $wkt, $matches);
            $polygons = [];
            foreach ($matches[1] as $polygon) {
                if (preg_match('/\((\(.+\))\s*,\s*(\(.+\))\)/', $polygon, $parts)) {
                    $polygons[] = [
                        $this->polygonToArray($parts[1]),
                        $this->polygonToArray($parts[2])
                    ];
                } else {
                    $polygon = preg_replace('/.*\((.+)\).*/', '\\1', $polygon);
                    $polygons[] = [
                        $this->polygonToArray($polygon)
                    ];
                }
            }
            return [
                'multipolygon' => $polygons
            ];
        } elseif (strtolower(substr($wkt, 0, 8)) == 'envelope') {
            return [
                'polygon' => [
                    $this->envelopeToArray($wkt)
                ]
            ];
        }

        $coordinates = explode(' ', $wkt);
        if (count($coordinates) > 2) {
            $polygon = [];
            // Assume rectangle
            $lon = (float)$coordinates[0];
            $lat = (float)$coordinates[1];
            $lon2 = (float)$coordinates[2];
            $lat2 = (float)$coordinates[3];
            $polygon[] = [$lon, $lat];
            $polygon[] = [$lon2, $lat];
            $polygon[] = [$lon2, $lat2];
            $polygon[] = [$lon, $lat2];
            $polygon[] = [$lon, $lat];
            return [
                'polygon' => [$polygon]
            ];
        }
        return [
            'lon' => $coordinates[0],
            'lat' => $coordinates[1]
        ];
    }
}
