<?php

/**
 * Map Tab Configuration Module
 *
 * PHP version 8
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
 * @package  GeoFeatures
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\GeoFeatures;

/**
 * MapTab Configuration Class
 *
 * @category VuFind
 * @package  GeoFeatures
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class MapTabConfig extends AbstractConfig
{
    /**
     * Set default options
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'recordMap' => false,
            'displayCoords' => false,
            'mapLabels' => null,
            'graticule' => false,
        ];
    }

    /**
     * Get the map tab configuration settings.
     *
     * @return array
     */
    public function getMapTabOptions()
    {
        $validFields = ['displayCoords', 'mapLabels', 'graticule', 'recordMap'];
        // Check geofeatures.ini
        $options = $this->getOptions('geofeatures', 'MapTab', $validFields);
        // Check legacy configuration
        if (empty($options)) {
            $options = $this->getOptions('config', 'Content', $validFields);
        }
        // Fill in any missing defaults:
        return $options + $this->getDefaultOptions();
    }
}
