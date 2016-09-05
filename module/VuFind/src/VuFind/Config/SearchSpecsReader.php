<?php
/**
 * VuFind SearchSpecs Configuration Reader
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Config;
use Symfony\Component\Yaml\Yaml;

/**
 * VuFind SearchSpecs Configuration Reader
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SearchSpecsReader
{
    /**
     * Cache manager
     *
     * @var \VuFind\Cache\Manager
     */
    protected $cacheManager;

    /**
     * Cache of loaded search specs.
     *
     * @var array
     */
    protected $searchSpecs = [];

    /**
     * Constructor
     *
     * @param \VuFind\Cache\Manager $cacheManager Cache manager (optional)
     */
    public function __construct(\VuFind\Cache\Manager $cacheManager = null)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Return search specs
     *
     * @param string $filename config file name
     *
     * @return array
     */
    public function get($filename)
    {
        // Load data if it is not already in the object's cache:
        if (!isset($this->searchSpecs[$filename])) {
            $this->searchSpecs[$filename] = $this->getFromPaths(
                Locator::getBaseConfigPath($filename),
                Locator::getLocalConfigPath($filename)
            );
        }

        return $this->searchSpecs[$filename];
    }

    /**
     * Given core and local filenames, retrieve the searchspecs data.
     *
     * @param string $defaultFile Full path to file containing default YAML
     * @param string $customFile  Full path to file containing local customizations
     * (may be null if no local file exists).
     *
     * @return array
     */
    protected function getFromPaths($defaultFile, $customFile = null)
    {
        // Connect to searchspecs cache:
        $cache = (null !== $this->cacheManager)
            ? $this->cacheManager->getCache('searchspecs') : false;

        // Generate cache key:
        $cacheKey = basename($defaultFile) . '-'
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
     * @param string $file          YAML file to load (will evaluate to empty array
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
            $defaultParent = $results['@parent_yaml'];
            // Swallow the directive after processing it:
            unset($results['@parent_yaml']);
        }

        // Now load in missing sections from parent, if applicable:
        if (null !== $defaultParent) {
            foreach ($this->parseYaml($defaultParent) as $section => $contents) {
                if (!isset($results[$section])) {
                    $results[$section] = $contents;
                }
            }
        }

        return $results;
    }
}
