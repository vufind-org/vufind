<?php

/**
 * Yaml config handler.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2022.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Handler;

use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Exception\FileAccess as FileAccessException;

use function array_key_exists;

/**
 * Yaml config handler.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Yaml extends AbstractBase
{
    use \VuFind\Feature\MergeRecursiveTrait;

    /**
     * Parses the configuration in a config location.
     *
     * @param ConfigLocationInterface $configLocation     Config location
     * @param bool                    $handleParentConfig If parent configuration should be handled
     *
     * @return array
     */
    public function parseConfig(ConfigLocationInterface $configLocation, bool $handleParentConfig = true): array
    {
        $path = $configLocation->getPath();

        try {
            $data = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($path));
        } catch (\Exception) {
            throw new FileAccessException('Could not read yaml file ' . $path);
        }

        $config = [];

        if ($handleParentConfig) {
            $parentPath = $data['@parent_yaml'] ?? false;
            unset($data['@parent_yaml']);
            if ($parentPath !== false) {
                // Get file on absolute path
                $parentConfigLocation = $this->getParentLocationOnPath($configLocation, $parentPath);
                if ($parentConfigLocation === null) {
                    // If config does not exist on absolute path get it on relative path
                    $parentConfigLocation = $this->getParentLocationOnPath(
                        $configLocation,
                        pathinfo($configLocation->getPath(), PATHINFO_DIRNAME)
                        . DIRECTORY_SEPARATOR . $parentPath
                    );
                }
                if ($parentConfigLocation === null) {
                    error_log('Cannot find parent file: ' . $parentPath);
                } else {
                    $parentConfigLocation->setSubsection($configLocation->getSubsection());
                    $config['parentLocation'] = $parentConfigLocation;
                }
            }
            $parentConfigName = $data['@parent_config_name'] ?? false;
            unset($data['@parent_config_name']);
            if ($parentConfigName !== false) {
                $config['parentConfigName'] = $parentConfigName;
            }

            $mergedSections = $data['@merge_sections'] ?? [];
            unset($data['@merge_sections']);

            $config['mergeCallback'] = $this->getMergeCallback($mergedSections);
        }

        $config['data'] = $data;

        return $config;
    }

    /**
     * Return a method that specifies how to merge parent configuration.
     *
     * @param array $mergedSections Array with sections that should be merged
     *
     * @return callable
     */
    protected function getMergeCallback(array $mergedSections): callable
    {
        return function ($parentConfig, $childConfig) use ($mergedSections) {
            // Process merged sections:
            foreach ($mergedSections as $path) {
                $this->mergeOnPath($parentConfig, $childConfig, $path);
            }
            // Add missing sections:
            foreach ($parentConfig as $section => $contents) {
                if (!isset($childConfig[$section])) {
                    $childConfig[$section] = $contents;
                }
            }
            return $childConfig;
        };
    }

    /**
     * Merge configs on the given path.
     *
     * @param array $parentConfig Reference to parent config
     * @param array $childConfig  Reference to child config
     * @param array $path         Path to merge
     *
     * @return void
     */
    protected function mergeOnPath(
        array &$parentConfig,
        array &$childConfig,
        array $path
    ) {
        foreach ($path as $pathPart) {
            if (!array_key_exists($pathPart, $childConfig) || !array_key_exists($pathPart, $parentConfig)) {
                $childConfig[$pathPart] = array_merge($parentConfig[$pathPart] ?? [], $childConfig[$pathPart] ?? []);
                return;
            }

            $childConfig = &$childConfig[$pathPart];
            $parentConfig = &$parentConfig[$pathPart];
        }

        $childConfig = $this->mergeRecursive($parentConfig, $childConfig);
    }
}
