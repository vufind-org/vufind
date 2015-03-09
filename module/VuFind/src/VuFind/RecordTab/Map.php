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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Map tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
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
     * Constructor
     *
     * @param bool $enabled Is this module enabled in the configuration?
     */
    public function __construct($enabled = true)
    {
        $this->enabled = $enabled;
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
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        if (!$this->enabled) {
            return false;
        }
        $longLat = $this->getRecordDriver()->tryMethod('getLongLat');
        return !empty($longLat);
    }
}