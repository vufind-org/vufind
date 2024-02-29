<?php

/**
 * VuFind YAML Configuration Reader
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use Symfony\Component\Yaml\Yaml;

use function array_key_exists;
use function dirname;
use function is_array;

/**
 * VuFind YAML Configuration Reader
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class YamlReader
{
    use \VuFind\Feature\MergeRecursiveTrait;

    /**
     * Cache directory name
     *
     * @var string
     */
    protected $cacheName = 'yaml';

    /**
     * Cache manager
     *
     * @var \VuFind\Cache\Manager
     */
    protected $cacheManager;

    /**
     * Config file path resolver
     *
     * @var PathResolver
     */
    protected $pathResolver;

    /**
     * Cache of loaded files.
     *
     * @var array
     */
    protected $files = [];

    /**
     * Constructor
     *
     * @param \VuFind\Cache\Manager $cacheManager Cache manager (optional)
     * @param PathResolver          $pathResolver Config file path resolver
     * (optional; defaults to \VuFind\Config\Locator)
     */
    public function __construct(
        \VuFind\Cache\Manager $cacheManager = null,
        PathResolver $pathResolver = null
    ) {
        $this->cacheManager = $cacheManager;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Return a configuration
     *
     * @param string  $filename       Config file name
     * @param boolean $useLocalConfig Use local configuration if available
     * @param boolean $forceReload    Reload even if config has been internally
     * cached in the class.
     *
     * @return array
     */
    public function get($filename, $useLocalConfig = true, $forceReload = false)
    {
        // Load data if it is not already in the object's cache (note that, because
        // the disk-based cache is keyed based on modification time, we don't need
        // to pass $forceReload down another level to load an updated file if
        // something has changed -- it's enough to force a cache recheck).
        if ($forceReload || !isset($this->files[$filename])) {
            if ($this->pathResolver) {
                $localConfigPath = $useLocalConfig
                    ? $this->pathResolver->getLocalConfigPath($filename)
                    : null;
            } else {
                $localConfigPath = $useLocalConfig
                    ? Locator::getLocalConfigPath($filename)
                    : null;
            }
            $baseConfigPath = $this->pathResolver
                ? $this->pathResolver->getBaseConfigPath($filename)
                : Locator::getBaseConfigPath($filename);
            $this->files[$filename] = $this->getFromPaths(
                $baseConfigPath,
                $localConfigPath
            );
        }

        return $this->files[$filename];
    }

    /**
     * Given core and local filenames, retrieve the configuration data.
     *
     * @param string $defaultFile Full path to file containing default YAML
     * @param string $customFile  Full path to file containing local customizations
     * (may be null if no local file exists).
     *
     * @return array
     */
    protected function getFromPaths($defaultFile, $customFile = null)
    {
        // Connect to the cache:
        $cache = (null !== $this->cacheManager)
            ? $this->cacheManager->getCache($this->cacheName) : false;

        // Generate cache key:
        $cacheKey = $defaultFile . '-'
            . (file_exists($defaultFile) ? filemtime($defaultFile) : 0);
        if (!empty($customFile)) {
            $cacheKey .= '-local-' . filemtime($customFile);
        }
        $cacheKey = md5($cacheKey);

        // Generate data if not found in cache:
        if ($cache === false || !($results = $cache->getItem($cacheKey))) {
            $results = $this->parseYaml($customFile, $defaultFile);
            if ($cache !== false) {
                $cache->setItem($cacheKey, $results);
            }
        }

        return $results;
    }

    /**
     * Process a YAML file (and its parent, if necessary).
     *
     * @param string $file          YAML file to load (will evaluate to null
     * if file does not exist).
     * @param string $defaultParent Parent YAML file from which $file should
     * inherit (unless overridden by a specific directive in $file). None by
     * default.
     *
     * @return array
     */
    protected function parseYaml($file, $defaultParent = null)
    {
        // First load current file:
        $results = (!empty($file) && file_exists($file))
            ? Yaml::parse(file_get_contents($file)) : [];

        // Override default parent with explicitly-defined parent, if present:
        if (isset($results['@parent_yaml'])) {
            // First try parent as absolute path, then as relative:
            $defaultParent = file_exists($results['@parent_yaml'])
                ? $results['@parent_yaml']
                : dirname($file) . '/' . $results['@parent_yaml'];
            if (!file_exists($defaultParent)) {
                $defaultParent = null;
                error_log('Cannot find parent file: ' . $results['@parent_yaml']);
            }
            // Swallow the directive after processing it:
            unset($results['@parent_yaml']);
        }
        // Check for sections to merge instead of overriding:
        $mergedSections = [];
        if (isset($results['@merge_sections'])) {
            $mergedSections = $results['@merge_sections'];
            // Swallow the directive after processing it:
            unset($results['@merge_sections']);
        }

        // Now load in merged or missing sections from parent, if applicable:
        if (null !== $defaultParent) {
            $parentSections = $this->parseYaml($defaultParent);
            // Process merged sections:
            foreach ($mergedSections as $path) {
                $parentElem
                    = $this->getArrayElemRefByPath($parentSections, $path);
                if (is_array($parentElem)) {
                    $resultElemRef
                        = &$this->getArrayElemRefByPath($results, $path, true);
                    $resultElemRef
                        = $this->mergeRecursive($parentElem, $resultElemRef);
                    unset($parentElem);
                    unset($resultElemRef);
                }
            }
            // Add missing sections:
            foreach ($parentSections as $section => $contents) {
                if (!isset($results[$section])) {
                    $results[$section] = $contents;
                }
            }
        }

        return $results;
    }

    /**
     * Return array element reference by path
     *
     * @param array $arr    Array to access
     * @param array $path   Path to retrieve
     * @param bool  $create Whether to create the path if it doesn't exist. Default
     * is false.
     *
     * @return mixed
     */
    protected function &getArrayElemRefByPath(
        array &$arr,
        array $path,
        bool $create = false
    ) {
        $result = &$arr;
        foreach ($path as $pathPart) {
            if (!array_key_exists($pathPart, $result)) {
                if (!$create) {
                    return null;
                }
                $result[$pathPart] = [];
            }
            $result = &$result[$pathPart];
        }
        return $result;
    }
}
