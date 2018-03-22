<?php
/**
 * VuFind Configuration Provider ParentIni Filter
 *
 * Copyright (C) 2010 Villanova University,
 *               2018 Leipzig University Library <info@ub.uni-leipzig.de>
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Provider\Filter;

use VuFind\Config\Factory;
use Zend\Config\Config;
use Zend\EventManager\Filter\FilterIterator as Chain;

/**
 * VuFind Configuration Provider ParentIni Filter
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ParentIni
{
    /**
     * Invokes this filter.
     *
     * @param mixed $context Reference to filter context.
     * @param array $items   List of items to be filtered.
     * @param Chain $chain   The remaining filter chain.
     *
     * @return array
     */
    public function __invoke($context, array $items, Chain $chain): array
    {
        $result = array_map([$this, 'process'], $items);

        return $chain->isEmpty() ? $result
            : $chain->next($context, $result, $chain);
    }

    /**
     * Processes a single item.
     *
     * @param array $item The item to be processed.
     *
     * @return array
     */
    protected function process(array $item)
    {
        if ($item['ext'] !== 'ini') {
            return $item;
        }
        $data = $this->mergeParent($item['path'], $item['data']);

        return array_merge($item, compact('data'));
    }

    /**
     * Recursively merges in parent configuration data declared with
     * <code>Parent_Config</code> and associated directives.
     *
     * @param string $childPath The path to the processed configuration data.
     * @param array  $childData The processed configuration data optionally
     *                          containing a <code>Parent_Config</code>
     *                          directive.
     *
     * @return array
     */
    protected function mergeParent(string $childPath, array $childData): array
    {
        $child = new Config($childData, true);
        $settings = $child->Parent_Config ?: new Config([]);
        $parentPath = $settings->relative_path
            ? dirname($childPath).'/'.$settings->relative_path
            : $settings->path;

        if (!$parentPath) {
            return $childData;
        }

        $parent = new Config(Factory::fromFile($parentPath), true);

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

        return $this->mergeParent($parentPath, $parent->toArray());
    }
}
