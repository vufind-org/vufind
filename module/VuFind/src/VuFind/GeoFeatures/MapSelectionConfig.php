<?php
/**
 * MapSelection Configuration Module
 *
 * PHP version 7 
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
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\GeoFeatures;

/**
 * MapSelection Configuration Class
 *
 * @category VuFind
 * @package  GeoFeatures
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class MapSelectionConfig extends AbstractConfig
{
    /**
     * Default coordinates. Order is WENS
     *
     * @var string
     */
    protected $defaultCoords = "-95, 30, 72, 15";

    /**
     * Height of search map pane
     *
     * @var string
     */
    protected $height = 320;

    /**
     * Get the map tab configuration settings.
     *
     * @return array
     */
    public function getMapSelectionOptions()
    {
        $validFields = ['default_coordinates', 'height'];
        $options = [];
        // Check geofeatures.ini
        $options = $this->getOptions('geofeatures', 'MapSelection', $validFields);

        if (empty($options)) {
            // Check legacy configuration
            $options = $this->getOptions('searches', 'MapSelection', $validFields);
        }
        if (empty($options)) {
            // use defaults
            foreach ($validFields as $field) {
                $options[$field] = $this->$field;
            }
        }
        return $options;
    }
}
