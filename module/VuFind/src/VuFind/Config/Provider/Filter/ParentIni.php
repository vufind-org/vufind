<?php

namespace VuFind\Config\Provider\Filter;

use VuFind\Config\Factory;
use Zend\Config\Config;
use Zend\EventManager\Filter\FilterIterator as Chain;

class ParentIni
{
    public function __invoke($provider, array $items, Chain $chain): array
    {
        $result = array_map([$this, 'process'], $items);
        return $chain->isEmpty() ? $result
            : $chain->next($provider, $result, $chain);
    }

    protected function process(array $item)
    {
        if ($item['ext'] !== 'ini') {
            return $item;
        }
        $data = $this->mergeParent($item['path'], $item['data']);
        return array_merge($item, compact('data'));
    }

    /**
     * Recursively merges parent configurations declared with «Parent_Config»
     * and associated directives.
     *
     * @param array  $childData
     * @param string $childPath
     *
     * @return array
     */
    protected function mergeParent(string $childPath, array $childData): array
    {
        $child = new Config($childData, true);
        $settings = $child->Parent_Config ?: new Config([]);
        $parentPath = $settings->relative_path
            ? dirname($childPath) . '/' . $settings->relative_path
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