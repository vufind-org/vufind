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
     * What type of map interface should be used?
     *
     * @var string
     */
    protected $mapType = null;

    /**
     * Should we display coordinates as part of labels?
     *
     * @var bool
     */
    protected $displayCoords = false;

    /**
     * Map labels setting from config.ini.
     *
     * @var string
     */
    protected $mapLabels = null;

    /**
     * Display graticule / map lat long grid?
     *
     * @var bool
     */
    protected $graticule = false;

    /**
     * Constructor
     *
     * @param string $mapType Map provider (valid options: 'openlayers';
     * null to disable this feature)
     * @param array  $options Additional settings
     */
    public function __construct($mapType = null, $options = [])
    {
        switch (trim(strtolower($mapType))) {
        case 'openlayers':
            $this->mapType = trim(strtolower($mapType));
            $legalOptions = ['displayCoords', 'mapLabels', 'graticule'];
            foreach ($legalOptions as $option) {
                if (isset($options[$option])) {
                    $this->$option = $options[$option];
                }
            }
            break;
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
     * Get the map graticule setting.
     *
     * @return string
     */
    public function getMapGraticule()
    {
        return $this->graticule;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        if ($this->mapType == 'openlayers') {
            $geocoords = $this->getRecordDriver()->tryMethod('getGeoLocation');
            return !empty($geocoords);
        }
        return false;
    }

    /**
     * Get the bbox-geo coordinates.
     *
     * @return array
     */
    public function getGeoLocationCoords()
    {
        $geoCoords = $this->getRecordDriver()->tryMethod('getGeoLocation');
        if (empty($geoCoords)) {
            return [];
        }
        $coordarray = [];
        /* Extract coordinates from long_lat field */
        foreach ($geoCoords as $value) {
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
        $mapLabelData = explode(':', $this->mapLabels);
        if ($mapLabelData[0] == 'driver') {
            $labels = $this->getRecordDriver()->tryMethod('getCoordinateLabels');
            return $labels;
        }
        if ($mapLabelData[0] == 'file') {
            $coords = $this->getRecordDriver()->tryMethod('getDisplayCoordinates');
            /* read lookup file into array */
            $label_lookup = [];
            $file = \VuFind\Config\Locator::getConfigPath($mapLabelData[1]);
            if (file_exists($file)) {
                $fp = fopen($file, 'r');
                while (($line = fgetcsv($fp, 0, "\t")) !== false) {
                    if (count($line) > 1) {
                        $label_lookup[$line[0]] = $line[1];
                    }
                }
                fclose($fp);
            }
            $labels = [];
            if (null !== $coords) {
                foreach ($coords as $val) {
                    /* Collapse spaces to make combined coordinate string to match
                        against lookup table coordinate */
                    $coordmatch = implode('', explode(' ', $val));
                    /* See if coordinate string matches lookup
                        table coordinates and if so return label */
                    $labelname = isset($label_lookup[$coordmatch])
                        ? $label_lookup[$coordmatch] : '';
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
        $geoCoords = $this->getGeoLocationCoords();
        if (empty($geoCoords)) {
            return [];
        }
        $mapTabData = [];
        $mapDisplayCoords = [];
        $mapDisplayLabels = [];
        if ($this->displayCoords) {
             $mapDisplayCoords = $this->getDisplayCoords();
        }
        if (isset($this->mapLabels)) {
            $mapDisplayLabels = $this->getMapLabels();
        }
        // Pass coordinates, display coordinates, and labels
        foreach (array_keys($geoCoords) as $key) {
            $mapCoords = '';
            $mapLabel = '';
            if ($this->displayCoords) {
                $mapCoords = $mapDisplayCoords[$key];
            }
            if (isset($this->mapLabels)) {
                $mapLabel = $mapDisplayLabels[$key];
            }
            array_push(
                $mapTabData, [
                    $geoCoords[$key][0], $geoCoords[$key][1],
                    $geoCoords[$key][2], $geoCoords[$key][3],
                    $geoCoords[$key][4], $mapLabel, $mapCoords
                    ]
            );
        }
        return $mapTabData;
    }
}
