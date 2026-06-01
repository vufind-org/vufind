<?php

/**
 * Ini config handler.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Handler;

use VuFind\Config\Feature\ExplodeSettingTrait;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\Writer as ConfigWriter;
use VuFind\Exception\ConfigException;
use VuFind\Exception\FileAccess as FileAccessException;

use function in_array;
use function is_array;
use function is_string;

/**
 * Ini config handler.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Ini extends AbstractBase
{
    use ExplodeSettingTrait;

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
        $data = $this->loadFromFile($configLocation->getPath());

        $config = [];

        if ($handleParentConfig) {
            $parentConfig = $data['Parent_Config'] ?? [];
            unset($data['Parent_Config']);
            $parentPath = null;
            if (isset($parentConfig['path'])) {
                $parentPath = $parentConfig['path'];
            } elseif (isset($parentConfig['relative_path'])) {
                $parentPath = pathinfo($configLocation->getPath(), PATHINFO_DIRNAME)
                    . DIRECTORY_SEPARATOR
                    . $parentConfig['relative_path'];
            }

            if ($parentPath !== null) {
                $parentConfigLocation = $this->getParentLocationOnPath($configLocation, $parentPath);
                if ($parentConfigLocation === null) {
                    throw new FileAccessException("Error: $parentPath does not exist.");
                }
                $parentConfigLocation->setSubsection($configLocation->getSubsection());
                $config['parentLocation'] = $parentConfigLocation;
            } elseif ($parentConfig['use_parent_dir'] ?? false) {
                $config['parentLocation'] = $configLocation->getDirLocationsParent();
            }

            $overrideSections = $this->explodeListSetting($parentConfig['override_full_sections'] ?? '');
            $config['mergeCallback'] = $this->getMergeCallback(
                $overrideSections,
                $parentConfig['merge_array_settings'] ?? false
            );
        }

        $config['data'] = $data;

        return $config;
    }

    /**
     * Load from file.
     *
     * @param string $path Config file path
     *
     * @return array
     */
    protected function loadFromFile(string $path): array
    {
        $data = parse_ini_file($path, true);
        if ($data === false) {
            throw new FileAccessException('Could not read ini file ' . $path);
        }
        return $this->handleIncludeStatements($data, pathinfo($path, PATHINFO_DIRNAME));
    }

    /**
     * Handle include statements.
     *
     * @param array  $data     Config data
     * @param string $basePath Config file base path
     *
     * @return array
     */
    protected function handleIncludeStatements(array $data, string $basePath): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->handleIncludeStatements($value, $basePath);
            } elseif ($key === '@include') {
                $included = $this->loadFromFile($basePath . DIRECTORY_SEPARATOR . $value);
                unset($data['@include']);
                $data = array_replace_recursive($data, $included);
            }
        }
        return $data;
    }

    /**
     * Return a method that specifies how to merge parent configuration.
     *
     * @param array $overrideFullSections Array with sections that should not be merged
     * @param bool  $mergeArraySettings   If arrays should be merged
     *
     * @return callable
     */
    protected function getMergeCallback(array $overrideFullSections, bool $mergeArraySettings): callable
    {
        return function ($parentConfig, $childConfig) use ($overrideFullSections, $mergeArraySettings) {
            foreach ($childConfig as $section => $childSection) {
                if (
                    in_array($section, $overrideFullSections)
                    || !isset($parentConfig[$section])
                ) {
                    $parentConfig[$section] = $childSection;
                } else {
                    foreach (array_keys($childSection) as $key) {
                        // If the current section is not configured as an override section
                        // we try to merge the key[] values instead of overwriting them.
                        if (
                            is_array($parentConfig[$section][$key] ?? null)
                            && is_array($childSection[$key])
                            && $mergeArraySettings
                        ) {
                            $parentConfig[$section][$key] = array_merge(
                                $parentConfig[$section][$key],
                                $childSection[$key]
                            );
                        } else {
                            $parentConfig[$section][$key] = $childSection[$key];
                        }
                    }
                }
            }
            return $parentConfig;
        };
    }

    /**
     * Write configuration to a specific location.
     *
     * @param ConfigLocationInterface  $destinationLocation Destination location for the config
     * @param array|string             $config              Config to write
     * @param ?ConfigLocationInterface $baseLocation        Location of a base configuration that can provide additional
     * structure (e.g. comments)
     *
     * @return void
     */
    public function writeConfig(
        ConfigLocationInterface $destinationLocation,
        array|string $config,
        ?ConfigLocationInterface $baseLocation
    ): void {
        if (!is_array($config)) {
            throw new ConfigException('Ini handler can only write array config.');
        }

        $this->checkIfConfigIsWritable($destinationLocation);

        // If target file already exists, back it up:
        $outfile = $destinationLocation->getPath();
        $this->backupFile($outfile);

        if ($baseLocation !== null && file_exists($baseLocation->getPath())) {
            // Copy from base to provide structure
            if (!copy($baseLocation->getPath(), $outfile)) {
                throw new FileAccessException(
                    "Error: Problem copying to {$outfile}."
                );
            }
        }

        if (file_exists($outfile) && $currentConfig = parse_ini_file($outfile, true)) {
            // If file already exists, only update changed lines
            $writer = new ConfigWriter(
                $outfile
            );
            // override current config with new config
            foreach ($config as $section => $sectionConfig) {
                foreach ($sectionConfig as $setting => $value) {
                    if (!isset($currentConfig[$section][$setting]) || $currentConfig[$section][$setting] !== $value) {
                        $writer->set($section, $setting, $value);
                    }
                    unset($currentConfig[$section][$setting]);
                }
            }
            // remove current config not included in new config
            foreach ($currentConfig as $section => $sectionConfig) {
                foreach ($sectionConfig as $setting => $value) {
                    $writer->clear($section, $setting);
                }
            }
        } else {
            // If no file exists yet, create it from scratch
            $writer = new ConfigWriter(
                $outfile,
                $config,
            );
        }
        if (!$writer->save()) {
            throw new FileAccessException(
                "Error: Problem writing to {$outfile}."
            );
        }
    }

    /**
     * Check if a config location is writable. Otherwise, throw an exception.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return void
     */
    protected function checkIfConfigIsWritable(ConfigLocationInterface $configLocation): void
    {
        if (!file_exists($configLocation->getPath())) {
            return;
        }

        $config = parse_ini_file($configLocation->getPath(), true);

        foreach ($config as $section => $sectionConfig) {
            if ($section === '@include') {
                throw new ConfigException('Can not write INI configuration with @include statement.');
            }
            if (is_string($sectionConfig) && str_starts_with($sectionConfig, 'include::')) {
                throw new ConfigException('Can not write INI configuration with include:: statement.');
            }
            foreach ((array)$sectionConfig as $setting => $value) {
                if ($section === 'Parent_Config') {
                    throw new ConfigException('Can not write INI configuration with inheritance.');
                }
                if ($setting === '@include') {
                    throw new ConfigException('Can not write INI configuration with @include statement.');
                }
                if (is_string($value) && str_starts_with($value, 'include::')) {
                    throw new ConfigException('Can not write INI configuration with include:: statement.');
                }
            }
        }
    }
}
