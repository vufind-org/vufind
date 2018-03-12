<?php
/**
 * VuFind Configuration Base Provider
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
 * PHP version 5.6
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

use VuFind\Config\Manager;
use Zend\Config\Config;
use Zend\Config\Reader\Ini as IniReader;

/**
 * VuFind Configuration Base Provider
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Base extends Glob
{
    const FLAG_FLAT_INI = 1;
    const FLAG_PARENT_CONFIG = 2;
    const FLAG_PARENT_YAML = 4;

    protected $flags;

    /**
     * @var IniReader
     */
    protected $iniReader;

    public function __construct($pattern, $base, $flags = 0)
    {
        parent::__construct($pattern, $base);
        $this->flags = $flags;
        $this->iniReader = Manager::getIniReader();
    }

    protected function load($path)
    {
        $iniSeparator = $this->iniReader->getNestSeparator();
        if ($this->flags & static::FLAG_FLAT_INI) {
            $this->iniReader->setNestSeparator(chr(0));
        }

        $data = parent::load($path);

        if ($this->flags & static::FLAG_PARENT_CONFIG) {
            $data = $this->mergeParentConfig($data, $path);
        }

        if ($this->flags & static::FLAG_PARENT_YAML) {
            $data = $this->mergeParentYaml($data);
        }

        $this->iniReader->setNestSeparator($iniSeparator);

        return $data;
    }

    protected function mergeParentConfig(array $childData, $childPath)
    {
        $child = new Config($childData, true);
        $settings = $child->Parent_Config ?: new Config([]);
        $parentPath = $settings->relative_path
            ? dirname($childPath) . '/' . $settings->relative_path
            : $settings->path;

        $parent = $parentPath ? $this->load($parentPath) : new Config([], true);

        $overrideSections = $settings->override_full_sections;
        $overrideSections = $overrideSections
            ? explode(',', str_replace(' ', '', $overrideSections)) : [];

        foreach ($child as $section => $contents) {
            // Check if arrays in the current config file should be merged with
            // preceding arrays from config files defined as Parent_Config.
            $mergeArraySettings = !empty($settings->merge_array_settings);

            // Omit Parent_Config from the returned configuration; it is only
            // needed during loading, and its presence will cause problems in
            // config files that iterate through all of the sections (e.g.
            // combined.ini, permissions.ini).
            if ($section === 'Parent_Config') {
                continue;
            }
            if (in_array($section, $overrideSections)
                || !isset($parent->$section)
            ) {
                $parent->$section = $child->$section;
            } else {
                foreach (array_keys($contents->toArray()) as $key) {
                    // If a key is defined as key[] in the config file the key
                    // remains a Zend\Config\Config object. If the current
                    // section is not configured as an override section we try to
                    // merge the key[] values instead of overwriting them.
                    if (is_object($parent->$section->$key)
                        && is_object($child->$section->$key)
                        && $mergeArraySettings
                    ) {
                        $parent->$section->$key = array_merge(
                            $parent->$section->$key->toArray(),
                            $child->$section->$key->toArray()
                        );
                    } else {
                        $parent->$section->$key = $child->$section->$key;
                    }
                }
            }
        }
        return $parent->toArray();
    }

    protected function mergeParentYaml(array $child)
    {
        if (!isset($child['@parent_yaml'])) {
            return $child;
        }
        $parent = $this->load($child['@parent_yaml']);
        unset($child['@parent_yaml']);
        return array_replace($parent, $child);
    }
}