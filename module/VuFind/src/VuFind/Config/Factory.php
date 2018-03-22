<?php
/**
 * VuFind Configuration Factory
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

namespace VuFind\Config;

use Symfony\Component\Yaml\Yaml as YamlParser;
use Zend\Config\Factory as Base;
use Zend\Config\Reader\Ini as IniReader;
use Zend\Config\Reader\Yaml as YamlReader;

/**
 * VuFind Configuration Factory
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Factory extends Base
{
    /**
     * A reference to the reader used for INI configuration files.
     *
     * @var IniReader
     */
    protected static $iniReader;

    /**
     * Initializes the factory.
     *
     * @return void
     */
    public static function init()
    {
        static::$iniReader = new IniReader;
        $yamlReader = new YamlReader([YamlParser::class, 'parse']);
        static::registerReader('ini', static::$iniReader);
        static::registerReader('yaml', $yamlReader);
    }

    /**
     * Get the reference to {@see Factory::$iniReader}.
     *
     * @return IniReader
     */
    public static function getIniReader(): IniReader
    {
        return static::$iniReader;
    }
}
