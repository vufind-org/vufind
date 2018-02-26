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

use Zend\Config\Config;

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
        $options = [];
        if ($origin == 'MapSelection') {
            $options = $this->getMapSelectionBasemap();
        } elseif ($origin == 'MapTab') {
            $options = $this->getMapTabBasemap();
        }
        // Check geofeatures.ini [Basemaps] section as a fallback:
        if (empty($options)) {
            $options = $this->getOptions('geofeatures', 'Basemaps');
        }
        // Fill array with defaults if nothing else worked:
        if (empty($options)) {
            $options['basemap_url'] = $this->basemapUrl;
            $options['basemap_attribution'] = $this->basemapAttribution;
        }
        return $options;
    }

    /**
     * Get the basemap configuration settings for MapSelection.
     *
     * @return array
     */
    protected function getMapSelectionBasemap()
    {
        // First check legacy location:
        $options = $this->getOptions('searches', 'MapSelection');
        // Check geofeatures.ini [MapSelection] section next:
        if (empty($options)) {
            $options = $this->getOptions('geofeatures', 'MapSelection');
        }
        return $options;
    }

    /**
     * Get the basemap configuration settings for MapTab.
     *
     * @return array
     */
    protected function getMapTabBasemap()
    {
        // First check legacy location:
        $options = $this->getOptions('config', 'Content');
        // Check geofeatures.ini [MapTab] section next:
        if (empty($options)) {
            $options = $this->getOptions('geofeatures', 'MapTab');
        }
        return $options;
    }

    /**
     * Convert a config object to an options array; return empty array if
     * configuration is missing or incomplete.
     *
     * @param string $configName Name of config file to read
     * @param string $section    Name of section to read
     *
     * @return array
     */
    protected function getOptions($configName, $section)
    {
        $config = $this->configLoader->get($configName);
        $options = [];
        $fields = ['basemap_url', 'basemap_attribution'];
        foreach ($fields as $field) {
            if (isset($config->$section->$field)) {
                $options[$field] = $config->$section->$field;
            }
        }
        // If basemap_url is not set, options array is invalid!
        return isset($options['basemap_url']) ? $options : [];
    }
}
