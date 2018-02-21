<?php
/**
 * Basemap Configuration Module
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
class BasemapConfig
{
    /**
     * Basemap tileserver URL
     * Default is the wikimedia osm-intl map
     *
     * @var string
     */
    protected $basemapUrl = "https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png";

    /**
     * Basemap attribution
     *
     * @var string
     */
    protected $basemapAttribution = '<a href="https://wikimediafoundation.org/wiki/
        Maps_Terms_of_Use">Wikimedia</a> | © <a href="https://www.openstreetmap.org/
        copyright">OpenStreetMap</a>';

    /**
     * Request origin
     *
     * @var string
     */
    protected $requestOrigin;

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
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
        $basemapUrl = $this->basemapUrl;
        $basemapAttribution = $this->basemapAttribution;
        $options = [];
        $optionFields = ['basemap_url', 'basemap_attribution'];

        $geofeatures = $this->configLoader->get('geofeatures');

        if ($origin == "MapSelection") {
            $options = $this->getMapSelectionBasemap($origin);
        }
        if ($origin == "MapTab") {
            $options = $this->getMapTabBasemap($origin);
        }
        if (empty($options)) {
            // Check geofeatures.ini [Basemaps] section
            foreach ($optionFields as $field) {
                if (isset($geofeatures->Basemaps->$field)) {
                    $options[$field] = $geofeatures->Basemaps->$field;
                }
                // If basemap_url is not set, clear $options array.
                if (!isset($options['basemap_url'])) {
                    $options = [];
                }
            }
        }
        if (empty($options)) {
            // Fill array with defaults
            $options['basemap_url'] = $basemapUrl;
            $options['basemap_attribution'] = $basemapAttribution;
        }
        return $options;
    }

    /**
     * Get the basemap configuration settings for MapSelection.
     *
     * @param string $origin Origin of request MapTab or MapSelection
     *
     * @return array
     */
    public function getMapSelectionBasemap($origin)
    {
        $options = [];
        $optionFields = ['basemap_url', 'basemap_attribution'];
        $searches = $this->configLoader->get('searches');
        $geofeatures = $this->configLoader->get('geofeatures');

        // Check searches.ini [MapSelection] section
        foreach ($optionFields as $field) {
            if (isset($searches->MapSelection->$field)) {
                $options[$field] = $searches->MapSelection->$field;
            }
            // If basemap_url is not set, clear $options array.
            if (!isset($options['basemap_url'])) {
                $options = [];
            }
        }
        if (empty($options)) {
            // Check geofeatures.ini [MapSelection] section
            foreach ($optionFields as $field) {
                if (isset($geofeatures->MapSelection->$field)) {
                    $options[$field] = $geofeatures->MapSelection->$field;
                }
                // If basemap_url is not set, clear $options array.
                if (!isset($options['basemap_url'])) {
                    $options = [];
                }
            }
        }
        return $options;
    }

    /**
     * Get the basemap configuration settings for MapTab.
     *
     * @param string $origin Origin of request MapTab or MapSelection
     *
     * @return array
     */
    public function getMapTabBasemap($origin)
    {
        $options = [];
        $optionFields = ['basemap_url', 'basemap_attribution'];
        $config = $this->configLoader->get('config');
        $geofeatures = $this->configLoader->get('geofeatures');

        // Check config.ini [Content] section
        foreach ($optionFields as $field) {
            if (isset($config->Content->$field)) {
                $options[$field] = $config->Content->$field;
            }
            // If basemap_url is not set, clear $options array.
            if (!isset($options['basemap_url'])) {
                $options = [];
            }
        }
        if (empty($options)) {
            // Check geofeatures.ini [MapTab] section
            foreach ($optionFields as $field) {
                if (isset($geofeatures->MapTab->$field)) {
                    $options[$field] = $geofeatures->MapTab->$field;
                }
                // If basemap_url is not set, clear $options array.
                if (!isset($options['basemap_url'])) {
                    $options = [];
                }
            }
        }
        return $options;
    }
}
