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

use VuFind\Config\Filter\FlatIni;
use VuFind\Config\Filter\ParentConfig;
use VuFind\Config\Filter\ParentYaml;

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
class Classic
{
    /**
     * Base directories to look for configuration files
     *
     * @var array
     */
    protected $baseDirs;

    public function __construct(...$baseDirs)
    {
        $this->baseDirs = $baseDirs;
    }

    /**
     * Provides all the configuration data contained in INI, YAML and JSON files
     * located at the usual places.
     *
     * @return array
     */
    public function __invoke() : array
    {
        $list = array_map([$this, 'load'], $this->baseDirs);
        $result = array_replace_recursive(...$list);
        return $result;
    }

    protected function load($baseDir)
    {
        return array_replace_recursive(...[
            $this->loadIni($baseDir),
            $this->loadJson($baseDir),
            $this->loadYaml($baseDir)
        ]);
    }

    protected function loadIni(string $baseDir)
    {
        $pattern = "**/*.ini";
        $flatIniFilter = new FlatIni;
        $parentConfigFilter = new ParentConfig;
        $provider = new Base($baseDir, $pattern);
        $provider->getFilterChain()->attach($flatIniFilter, 20000);
        $provider->getFilterChain()->attach($parentConfigFilter, 0);
        return $provider();
    }

    protected function loadYaml($baseDir)
    {
        $pattern = "**/*.yaml";
        $parentYamlFilter = new ParentYaml;
        $provider = new Base($baseDir, $pattern);
        $provider->getFilterChain()->attach($parentYamlFilter, 0);
        return $provider();
    }

    protected function loadJson($baseDir)
    {
        $pattern = "**/*.json";
        $provider = new Base($baseDir, $pattern);
        return $provider();
    }
}
