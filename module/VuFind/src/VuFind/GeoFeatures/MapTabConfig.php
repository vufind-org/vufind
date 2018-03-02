<?php
/**
 * Map Tab Configuration Module
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
 * MapTab Configuration Class
 *
 * @category VuFind
 * @package  GeoFeatures
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class MapTabConfig
{
    /**
     * What mapping platform should be used?
     *
     * @var string
     */
    protected $geoPlatform = null;

    /**
     * Should we display coordinates as part of labels?
     *
     * @var bool
     */
    protected $displayCoords = false;

    /**
     * Where should map labels be read from?
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
     * Get the map tab configuration settings.
     *
     * @return array
     */
    public function getMapTabOptions()
    {
        // First check legacy location:
        $validFields = ['displayCoords', 'mapLabels', 'graticule', 'recordMap'];
        $options = $this->getOptions('config', 'Content', $validFields);
        // Check geofeatures.ini [MapTab] section next:
        if (empty($options)) {
            $validFields = ['displayCoords', 'mapLabels', 'graticule'];
            $options = $this->getOptions('geofeatures', 'MapTab', $validFields);
            $geoPlatform = $this->getOptions(
                'geofeatures', 'GeoPlatform',
                ['geoPlatform']
            );
            $options['geoPlatform']=$geoPlatform['geoPlatform'];
        }
        if (empty($options)) {
            // use defaults
            $fields = ['displayCoords', 'mapLabels', 'graticule', 'geoPlatform'];
            foreach ($fields as $field) {
                $options[$field] = $this->$field;
            }
        }
        return $options;
    }

    /**
     * Convert a config object to an options array; return empty array if
     * configuration is missing or incomplete.
     *
     * @param string $configName   Name of config file to read
     * @param string $section      Name of section to read
     * @param array  $validOptions List of valid fields to read
     *
     * @return array
     */
    protected function getOptions($configName, $section, $validOptions)
    {
        $config = $this->configLoader->get($configName);
        $options = [];
        $fields = $validOptions;
        foreach ($fields as $field) {
            if (isset($config->$section->$field)) {
                $options[$field] = $config->$section->$field;
            }
        }
        return $options;
    }
}
