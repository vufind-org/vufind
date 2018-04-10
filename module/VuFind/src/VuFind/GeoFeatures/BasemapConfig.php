<?php
/**
 * Basemap Configuration Module
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
 * Basemap Configuration Class
 *
 * @category VuFind
 * @package  GeoFeatures
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class BasemapConfig extends AbstractConfig
{
    /**
     * Request origin
     *
     * @var string
     */
    protected $requestOrigin;

    /**
     * Set default options
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'basemap_url' => 'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png',
            'basemap_attribution' => '<a href="https://wikimediafoundation.org/wiki/
        Maps_Terms_of_Use">Wikimedia</a> | © <a href="https://www.openstreetmap.org/
        copyright">OpenStreetMap</a>'
        ];
    }

    /**
     * Get the basemap configuration settings.
     *
     * @param string $origin Origin of request MapTab or MapSelection
     *
     * @return array
     */
    public function getBasemap($origin)
    {
        $validFields = ['basemap_url', 'basemap_attribution'];
        $options = [];
        if ($origin == 'MapSelection') {
            $options = $this->getMapSelectionBasemap($validFields);
        } elseif ($origin == 'MapTab') {
            $options = $this->getMapTabBasemap($validFields);
        }
        if (empty($options)) {
            // Check geofeatures [Basemap]
            $options = $this->getOptions('geofeatures', 'Basemap', $validFields);
        }
        // Fill in any missing defaults:
        return $options + $this->getDefaultOptions();
    }

    /**
     * Get the basemap configuration settings for MapSelection.
     *
     * @param array $validFields Configuration parameters
     *
     * @return array
     */
    protected function getMapSelectionBasemap($validFields)
    {
        // Check geofeatures.ini [MapSelection]
        $options = $this->getOptions('geofeatures', 'MapSelection', $validFields);

        if (empty($options)) {
            // Check legacy configuration
            $options = $this->getOptions('searches', 'MapSelection', $validFields);
        }
        return $options;
    }

    /**
     * Get the basemap configuration settings for MapTab.
     *
     * @param array $validFields Configuration parameters
     *
     * @return array
     */
    protected function getMapTabBasemap($validFields)
    {
        // Check geofeatures.ini [MapTab]
        $options = $this->getOptions('geofeatures', 'MapTab', $validFields);

        if (empty($options)) {
            // Check legacy configuration
            $options = $this->getOptions('config', 'Content', $validFields);
        }
        return $options;
    }
}
