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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
        $path = $configLocation->getPath();
        $data = parse_ini_file($path, true);
        if ($data === false) {
            throw new FileAccessException('Could not read ini file ' . $path);
        }

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
                $config['parentLocation'] = $this->getParentLocationOnPath($configLocation, $parentPath);
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

        // If target file already exists, back it up:
        $outfile = $destinationLocation->getPath();
        $this->backupFile($outfile);

        $comments = [];
        if ($baseLocation !== null) {
            $comments = $this->extractComments($baseLocation->getPath());
        }
        $writer = $this->getConfigWriter($outfile, $config, $comments);
        if (!$writer->save()) {
            throw new FileAccessException(
                "Error: Problem writing to {$outfile}."
            );
        }
    }

    /**
     * Get writer object.
     *
     * @param string $outfile  Path to output file
     * @param array  $config   Configuration to write
     * @param array  $comments Comments
     *
     * @return ConfigWriter
     */
    protected function getConfigWriter(string $outfile, array $config, array $comments): ConfigWriter
    {
        return new ConfigWriter(
            $outfile,
            $config,
            $comments
        );
    }

    /**
     * Extract comments of a file.
     *
     * @param string $filename Name of ini file to read.
     *
     * @return array
     */
    protected function extractComments($filename)
    {
        return ConfigWriter::extractComments($filename);
    }
}
