<?php
/**
 * Map tab
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Map tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class Map extends AbstractBase
{
    /**
     * Is this module enabled in the configuration?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * What type of map interface should be used?
     *
     * @var string
     */
    protected $mapType;

        /**
     * Map display coordinates setting from config.ini.
     *
     * @var string
     */
    protected $displayCoords;

    /**
     * Map labels setting from config.ini.
     *
     * @var string
     */
    protected $mapLabels;

    /**
     * Map labels location setting from config.ini.
     *
     * @var string
     */
    protected $mapLabelsLookup;

    /**
     * Google Maps API key.
     *
     * @var string
     */
    protected $googleMapApiKey;

    /**
     * Constructor
     *
     * @param array $options from config.ini file
     */
    public function __construct($options)
    {
        if ($options[0] == 'openlayers' || $options[0] == 'google') {
            $this->enabled = true;
            $this->mapType = $options[0];

            if (isset($options[0]) == true) {
                $this->enabled = $options[0];
            }
            if (isset($options[1])) {
                $this->displayCoords = $options[1];
            }
            if (isset($options[2])) {
                $this->mapLabels = $options[2];
            }
            if (isset($options[3])) {
                $this->mapLabelsLookup = $options[3];
            }
            if (isset($options[4])) {
                $this->googleMapApiKey = $options[4];
            }
        }
    }
    
    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // No, magic required
        return false;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Map View';
    }

    /**
     * Get the map type for determining template to use.
     *
     * @return string
     */
    public function getMapType()
    {
        return $this->mapType;
    }

    /**
     * Get the Google Maps API key.
     *
     * @return string
     */
    public function getGoogleMapApiKey()
    {
        return $this->googleMapApiKey;
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
        if ($this->mapType == 'openlayers') {
            $geocoords = $this->getRecordDriver()->tryMethod('getBbox');
            return !empty($geocoords);
        }
        if ($this->mapType == 'google') {
            $longLat = $this->getRecordDriver()->tryMethod('getLongLat');
            return !empty($longLat);
        }
    }

    /**
     * Get the JSON needed to display the record on a Google map.
     *
     * @return string
     */
    public function getGoogleMapMarker()
    {
        $longLat = $this->getRecordDriver()->tryMethod('getLongLat');
        $mapDisplayLabels = $this->getMapLabels();
        if (empty($longLat)) {
            return json_encode([]);
        }
        $markers = [];
        foreach ($longLat as $key => $value) {
            $coordval = explode(',', $value);
            $label = isset($mapDisplayLabels[$key])
                ? $mapDisplayLabels[$key] : '';
            $markers[] = [
                [
                    'title' => $label,
                    'lon' => $coordval[0],
                    'lat' => $coordval[1]
                ]
            ];
        }
        return json_encode($markers);
    }

    /**
     * Get the bbox-geo coordinates.
     *
     * @return array
     */
    public function getBboxCoords()
    {
        $geoCoords = $this->getRecordDriver()->tryMethod('getBbox');
        if (empty($geoCoords)) {
            return [];
        }
        $coordarray = [];
        /* Extract coordinates from bbox_geo field */
        foreach ($geoCoords as $key => $value) {
            $match = [];
            if (preg_match('/ENVELOPE\((.*),(.*),(.*),(.*)\)/', $value, $match)) {
                $lonW = (float)$match[1];
                $lonE = (float)$match[2];
                $latN = (float)$match[3];
                $latS = (float)$match[4];
                // Display as point or polygon? 
                if (($lonE == $lonW) && ($latN == $latS)) {
                    $shape = 2;
                } else {
                    $shape = 4;
                }
                // Coordinates ordered for ol3 display as WSEN
                array_push($coordarray, [$lonW, $latS, $lonE, $latN, $shape]);
            }
        }
        return $coordarray;
    }
     
    /**
     * Get the map display coordinates.
     *
     * @return array
     */
    public function getDisplayCoords()
    {
        $label_coords = [];
        $coords = $this->getRecordDriver()->tryMethod('getDisplayCoordinates');
        foreach ($coords as $val) {
            $coord = explode(' ', $val);
            $labelW = $coord[0];
            $labelE = $coord[1];
            $labelN = $coord[2];
            $labelS = $coord[3];
            /* Create coordinate label for map display */
            if (($labelW == $labelE) && ($labelN == $labelS)) {
                $labelcoord = $labelS . ' ' . $labelE;
            } else {
                /* Coordinate order is min to max on lat and long axes */
                $labelcoord = $labelS . ' ' . $labelN . ' ' .
                $labelW . ' ' . $labelE;
            }
            array_push($label_coords, $labelcoord);
        }
        return $label_coords;
    }
    
    /**
     * Get the map labels.
     *
     * @return array
     */
    public function getMapLabels()
    {
        $labels = [];
        if ($this->mapLabels == 'long_lat_label') {
            $labels = $this->getRecordDriver()->tryMethod('getCoordinateLabels');
            return $labels;
        }
        if ($this->mapLabels == 'file') {
            $coords = $this->getRecordDriver()->tryMethod('getDisplayCoords');
            /* read lookup file into array */
            $label_lookup = [];
            $file = \VuFind\Config\Locator::getConfigPath($this->mapLabelsLookup);
            if (file_exists($file)) {
                 $fp = fopen($file, 'r');
                while (($line = fgetcsv($fp, 0, "\t")) !== false) {
                    if ($line) {
                        $label_lookup[] = $line;
                    }
                }
                 fclose($fp);
            }
            $labels = [];
            if (null !== $coords) {
                foreach ($coords as $val) {
                    $coord = explode(' ', $val);
                    $labelW = $coord[0];
                    $labelE = $coord[1];
                    $labelN = $coord[2];
                    $labelS = $coord[3];
                    /* Make combined coordinate string to match 
                        against lookup table coordinate */
                    $coordmatch = $labelW . $labelE . $labelN . $labelS;
                    /* See if coordinate string matches lookup 
                        table coordinates and if so return label */
                    $labelname = [];
                    foreach ($label_lookup as $data) {
                        if ($data[0] == $coordmatch) {
                            $labelname = $data[1];
                        }
                    }
                    array_push($labels, $labelname);
                }
            }
            return $labels;
        }
    }
    
    /**
     * Construct the map coordinates and labels array.
     *
     * @return array
     */
    public function getMapTabData()
    {
        $geoCoords = $this->getBboxCoords();
        if (empty($geoCoords)) {
            return [];
        }
        $mapTabData = [];
        $mapDisplayCoords = [];
        $mapDisplayLabels = [];
        if ($this->displayCoords == true) {
             $mapDisplayCoords = $this->getDisplayCoords();
        }
        if (isset($this->mapLabels)) {
            $mapDisplayLabels = $this->getMapLabels();
        }
        if (!empty($mapDisplayLabels) && !empty($mapDisplayCoords)) {
            // Pass coordinates, display coordinates, and labels
            foreach ($geoCoords as $key => $value) {
                array_push(
                    $mapTabData, [
                        $geoCoords[$key][0], $geoCoords[$key][1],
                        $geoCoords[$key][2], $geoCoords[$key][3],
                        $geoCoords[$key][4], 'cl',
                        $mapDisplayCoords[$key],$mapDisplayLabels[$key]
                        ]
                );
            }
        }
        if (!empty($mapDisplayLabels) && empty($mapDisplayCoords)) {
            // Pass coordinates and labels
            foreach ($geoCoords as $key => $value) {
                array_push(
                    $mapTabData, [
                        $geoCoords[$key][0], $geoCoords[$key][1],
                        $geoCoords[$key][2], $geoCoords[$key][3],
                        $geoCoords[$key][4], 'l', $mapDisplayLabels[$key]
                        ]
                );
            }
        }
        if (empty($mapDisplayLabels) && !empty($mapDisplayCoords)) {
            // Pass coordinates and display coordinates
            foreach ($geoCoords as $key => $value) {
                array_push(
                    $mapTabData, [
                        $geoCoords[$key][0], $geoCoords[$key][1],
                        $geoCoords[$key][2], $geoCoords[$key][3],
                        $geoCoords[$key][4], 'c', $mapDisplayCoords[$key]
                        ]
                );
            }
        }
        if (empty($mapDisplayLabels) && empty($mapDisplayCoords)) {
            // Pass only coordinates
            foreach ($geoCoords as $key => $value) {
                array_push(
                    $mapTabData, [
                        $geoCoords[$key][0], $geoCoords[$key][1],
                        $geoCoords[$key][2], $geoCoords[$key][3],
                        $geoCoords[$key][4], 'n'
                        ]
                );
            }
        }
        return $mapTabData;
    }
}
