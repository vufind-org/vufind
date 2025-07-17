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
        $writer = new ConfigWriter(
            $outfile,
            $config,
            $comments
        );
        if (!$writer->save()) {
            throw new FileAccessException(
                "Error: Problem writing to {$outfile}."
            );
        }
    }

    /**
     * Read the specified file and return an associative array of this format
     * containing all comments extracted from the file:
     *
     * [
     *   'sections' => array
     *     'section_name_1' => array
     *       'before' => string ("Comments found at the beginning of this section")
     *       'inline' => string ("Comments found at the end of the section's line")
     *       'settings' => array
     *         'setting_name_1' => array
     *           'before' => string ("Comments found before this setting")
     *           'inline' => string ("Comments found at the end of setting's line")
     *           ...
     *         'setting_name_n' => array (same keys as setting_name_1)
     *        ...
     *      'section_name_n' => array (same keys as section_name_1)
     *   'after' => string ("Comments found at the very end of the file")
     * ]
     *
     * @param string $filename Name of ini file to read.
     *
     * @return array           Associative array as described above.
     */
    protected function extractComments($filename)
    {
        $lines = file($filename);

        // Initialize our return value:
        $retVal = ['sections' => []];

        // Initialize variables for tracking status during parsing:
        $section = $comments = '';

        foreach ($lines as $line) {
            // To avoid redundant processing, create a trimmed version of the current
            // line:
            $trimmed = trim($line);

            // Is the current line a comment?  If so, add to the current comments
            // string. Note that we treat blank lines as comments.
            if ('' === $trimmed || str_starts_with($trimmed, ';')) {
                $comments .= $line;
            } elseif (
                str_starts_with($trimmed, '[')
                && ($closeBracket = strpos($trimmed, ']')) > 1
            ) {
                // Is the current line the start of a section? If so, create the
                // appropriate section of the return value:
                $section = substr($trimmed, 1, $closeBracket - 1);
                if ('' !== $section) {
                    // Grab comments at the end of the line, if any:
                    if (($semicolon = strpos($trimmed, ';')) !== false) {
                        $inline = trim(substr($trimmed, $semicolon));
                    } else {
                        $inline = '';
                    }
                    $retVal['sections'][$section] = [
                        'before' => $comments,
                        'inline' => $inline,
                        'settings' => []];
                    $comments = '';
                }
            } elseif (($equals = strpos($trimmed, '=')) !== false) {
                // Is the current line a setting?  If so, add to the return value:
                $set = trim(substr($trimmed, 0, $equals));
                $set = trim(str_replace('[]', '', $set));
                if ('' !== $section && '' !== $set) {
                    // Grab comments at the end of the line, if any:
                    if (($semicolon = strpos($trimmed, ';')) !== false) {
                        $inline = trim(substr($trimmed, $semicolon));
                    } else {
                        $inline = '';
                    }
                    // Currently, this data structure doesn't support arrays very
                    // well, since it can't distinguish which line of the array
                    // corresponds with which comments. For now, we just append all
                    // the preceding and inline comments together for arrays.  Since
                    // we rarely use arrays in the config.ini file, this isn't a big
                    // concern, but we should improve it if we ever need to.
                    if (!isset($retVal['sections'][$section]['settings'][$set])) {
                        $retVal['sections'][$section]['settings'][$set]
                            = ['before' => $comments, 'inline' => $inline];
                    } else {
                        $retVal['sections'][$section]['settings'][$set]['before']
                            .= $comments;
                        $retVal['sections'][$section]['settings'][$set]['inline']
                            .= "\n" . $inline;
                    }
                    $comments = '';
                }
            }
        }

        // Store any leftover comments following the last setting:
        $retVal['after'] = $comments;

        return $retVal;
    }
}
