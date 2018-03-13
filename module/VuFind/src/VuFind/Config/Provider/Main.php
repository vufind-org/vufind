<?php
/**
 * VuFind Configuration Main Provider
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software Foundation,
 * Inc. 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config\Provider;

use Zend\ConfigAggregator\ArrayProvider;
use Zend\ConfigAggregator\ConfigAggregator;

/**
 * VuFind Configuration Main Provider
 *
 * Provides VuFind configuration data
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Main
{
    const APP_CONFIG_PATH = APPLICATION_PATH . '/config/vufind/';
    const LCO_CONFIG_PATH = LOCAL_OVERRIDE_DIR . '/config/vufind/';

    /**
     * Provides all the configuration data contained in INI, YAML and JSON files
     * located at the usual places.
     *
     * @return array
     */
    public function __invoke() : array
    {
        $iniGlob = "**/*.ini";
        $yamlGlob = "**/*.y{,a}ml";
        $jsonGlob = "**/*.json";
        $iniFlags = Base::FLAG_FLAT_INI | Base::FLAG_PARENT_CONFIG;
        $ymlFlags = Base::FLAG_PARENT_YAML;

        $list = array_map('call_user_func', [
            new ArrayProvider([ConfigAggregator::ENABLE_CACHE => true]),
            new Base($iniGlob, static::APP_CONFIG_PATH, $iniFlags),
            new Base($iniGlob, static::LCO_CONFIG_PATH, $iniFlags),
            new Base($yamlGlob, static::APP_CONFIG_PATH, $ymlFlags),
            new Base($yamlGlob, static::LCO_CONFIG_PATH, $ymlFlags),
            new Base($jsonGlob, static::APP_CONFIG_PATH),
            new Base($jsonGlob, static::LCO_CONFIG_PATH),
        ]);

        return array_replace_recursive(...$list);
    }
}
