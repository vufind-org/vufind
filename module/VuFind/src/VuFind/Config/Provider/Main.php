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
    const CORE_DIR = APPLICATION_PATH . '/config/vufind';
    const LOCAL_DIR = LOCAL_OVERRIDE_DIR . '/config/vufind';

    /**
     * @var string
     */
    protected $coreDir;

    /**
     * @var string
     */
    protected $localDir;

    public function __construct($corePath = '', $localPath = '')
    {
        $this->coreDir = $corePath ?: self::CORE_DIR;
        $this->localDir = $localPath ?: self::LOCAL_DIR;
    }

    /**
     * Provides all the configuration data contained in INI, YAML and JSON files
     * located at the usual places.
     *
     * @return array
     */
    public function __invoke() : array
    {
        $iniGlob = "**/*.ini";
        $iniFlags = Base::FLAG_FLAT_INI | Base::FLAG_PARENT_CONFIG;

        $yamlGlob = "**/*.y{,a}ml";
        $yamlFlags = Base::FLAG_PARENT_YAML;

        $jsonGlob = "**/*.json";

        $list = array_map('call_user_func', [
            new ArrayProvider([ConfigAggregator::ENABLE_CACHE => true]),
            new Base($this->coreDir, $iniGlob, $iniFlags),
            new Base($this->localDir, $iniGlob, $iniFlags),
            new Base($this->coreDir, $yamlGlob, $yamlFlags),
            new Base($this->localDir, $yamlGlob, $yamlFlags),
            new Base($this->coreDir, $jsonGlob),
            new Base($this->localDir, $jsonGlob),
        ]);

        return array_replace_recursive(...$list);
    }
}
