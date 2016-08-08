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
     * Google Maps API key.
     *
     * @var string
     */
    protected $googleMapApiKey = null;

    /**
     * Constructor
     *
     * @param string $mapType Map provider (valid options: 'google' or 'openlayers';
     * null to disable this feature)
     * @param array  $options Additional settings
     */
    public function __construct($mapType = null, $options = [])
    {
        switch (trim(strtolower($mapType))) {
            case 'google':
                // Confirm API key, then fall through to 'openlayers' case for
                // other standard behavior:
                if (empty($options['googleMapApiKey'])) {
                    throw new \Exception('Google API key must be set in config.ini');
                }
                $this->googleMapApiKey = $options['googleMapApiKey'];
                $this->mapType = trim(strtolower($mapType));
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
        // No, Google script magic required
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
     * Get the JSON needed to display the record on a Google map.
     *
     * @return string
     */
    public function getGoogleMapMarker()
    {
        $longLat = $this->getRecordDriver()->tryMethod('getLongLat');
        if (empty($longLat)) {
            return json_encode([]);
        }
        $longLat = explode(',', $longLat);
        $markers = [
            [
                'title' => (string) $this->getRecordDriver()->getBreadcrumb(),
                'lon' => $longLat[0],
                'lat' => $longLat[1]
            ]
        ];
        return json_encode($markers);
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
        if ($this->mapType == 'google') {
            $longLat = $this->getRecordDriver()->tryMethod('getLongLat');
            return !empty($longLat);
        }
        return false;
    }
}
