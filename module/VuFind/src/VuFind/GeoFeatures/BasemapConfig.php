<?php

/**
 * Basemap Configuration Module
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
     * Valid options to retrieve from configuration
     *
     * @var string[]
     */
    protected $options = ['basemap_url', 'basemap_attribution'];

    /**
     * Set default options
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'basemap_url' => 'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png',
            'basemap_attribution' => '<a href="https://wikimediafoundation.org/'
                . 'wiki/Maps_Terms_of_Use">Wikimedia</a> | &copy; <a '
                . 'href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
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
        $options = [];
        if ($origin == 'MapSelection') {
            $options = $this->getMapSelectionBasemap();
        } elseif ($origin == 'MapTab') {
            $options = $this->getMapTabBasemap();
        }
        // Fail over to geofeatures.ini [Basemap] if no other settings found:
        if (empty($options)) {
            $options = $this->getOptions('geofeatures', 'Basemap', $this->options);
        }
        // Fill in any missing defaults:
        return $options + $this->getDefaultOptions();
    }

    /**
     * Get the basemap configuration settings for MapSelection.
     *
     * @return array
     */
    protected function getMapSelectionBasemap()
    {
        // Check geofeatures.ini [MapSelection]
        $options = $this->getOptions('geofeatures', 'MapSelection', $this->options);

        // Fail over to legacy configuration if empty
        return empty($options)
            ? $this->getOptions('searches', 'MapSelection', $this->options)
            : $options;
    }

    /**
     * Get the basemap configuration settings for MapTab.
     *
     * @return array
     */
    protected function getMapTabBasemap()
    {
        // Check geofeatures.ini [MapTab]
        $options = $this->getOptions('geofeatures', 'MapTab', $this->options);

        // Fail over to legacy configuration if empty
        return empty($options)
            ? $this->getOptions('config', 'Content', $this->options)
            : $options;
    }
}
