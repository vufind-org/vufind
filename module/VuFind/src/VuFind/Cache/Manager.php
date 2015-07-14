<?php
/**
 * VuFind Cache Manager
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Cache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Cache;
use Zend\Cache\StorageFactory, Zend\Config\Config;

/**
 * VuFind Cache Manager
 *
 * Creates file and APC caches
 *
 * @category VuFind2
 * @package  Cache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Manager
{
    /**
     * Default configuration settings.
     *
     * @var array
     */
    protected $defaults;

    /**
     * Was there a problem building cache directories?
     *
     * @var bool
     */
    protected $directoryCreationError = false;

    /**
     * Settings used to generate cache objects.
     *
     * @var array
     */
    protected $cacheSettings = [];

    /**
     * Actual cache objects generated from settings.
     *
     * @var array
     */
    protected $caches = [];

    /**
     * Constructor
     *
     * @param Config $config       Main VuFind configuration
     * @param Config $searchConfig Search configuration
     */
    public function __construct(Config $config, Config $searchConfig)
    {
        // $config and $config->Cache are Zend\Config\Config objects
        // $cache is created immutable, so get the array, it will be modified
        // downstream.
        // Zend\Config\Config can be created mutable or cloned and merged, useful
        // for future cache-specific overrides.
        $cacheConfig = isset($config->Cache) ? $config->Cache : false;
        $this->defaults = $cacheConfig ? $cacheConfig->toArray() : false;

        // Get base cache directory.
        $cacheBase = $this->getCacheDir();

        // Set up standard file-based caches:
        foreach (['config', 'cover', 'language', 'object'] as $cache) {
            $this->createFileCache($cache, $cacheBase . $cache . 's');
        }

        // Set up search specs cache based on config settings:
        $searchCacheType = isset($searchConfig->Cache->type)
            ? $searchConfig->Cache->type : false;
        switch ($searchCacheType) {
        case 'APC':
            $this->createAPCCache('searchspecs');
            break;
        case 'File':
            $this->createFileCache(
                'searchspecs', $cacheBase . 'searchspecs'
            );
            break;
        case false:
            $this->createNoCache('searchspecs');
            break;
        }
    }

    /**
     * Retrieve the specified cache object.
     *
     * @param string $key Key identifying the requested cache.
     *
     * @return object
     */
    public function getCache($key)
    {
        if (!isset($this->caches[$key])) {
            if (!isset($this->cacheSettings[$key])) {
                throw new \Exception('Requested unknown cache: ' . $key);
            }
            // Special case for "no-cache" caches:
            if ($this->cacheSettings[$key] === false) {
                $this->caches[$key]
                    = new \VuFind\Cache\Storage\Adapter\NoCacheAdapter();
            } else {
                $this->caches[$key] = StorageFactory::factory(
                    $this->cacheSettings[$key]
                );
            }
        }
        return $this->caches[$key];
    }

    /**
     * Get the path to the directory containing VuFind's cache data.
     *
     * @param bool $allowCliOverride If true, use a different cache subdirectory
     * for CLI mode; otherwise, share the web directories.
     *
     * @return string
     */
    public function getCacheDir($allowCliOverride = true)
    {
        if ($this->defaults && isset($this->defaults['cache_dir'])) {
            $dir = $this->defaults['cache_dir'];
            // ensure trailing slash:
            if (substr($dir, -1) != '/') {
                $dir .= '/';
            }
        } else if (strlen(LOCAL_OVERRIDE_DIR) > 0) {
            $dir = LOCAL_OVERRIDE_DIR . '/cache/';
        } else {
            $dir = APPLICATION_PATH . '/data/cache/';
        }

        // Use separate cache dir in CLI mode to avoid permission issues:
        if ($allowCliOverride && PHP_SAPI == 'cli') {
            $dir .= 'cli/';
        }

        return $dir;
    }

    /**
     * Get the names of all available caches.
     *
     * @return array
     */
    public function getCacheList()
    {
        return array_keys($this->cacheSettings);
    }

    /**
     * Check if there have been problems creating directories.
     *
     * @return bool
     */
    public function hasDirectoryCreationError()
    {
        return $this->directoryCreationError;
    }

    /**
     * Create a new file cache for the given theme name if neccessary. Return
     * the name of the cache.
     *
     * @param string $themeName Name of the theme
     *
     * @return string
     */
    public function addLanguageCacheForTheme($themeName)
    {
        $cacheName = 'languages-' . $themeName;
        $this->createFileCache(
            $cacheName,
            $this->getCacheDir() . 'languages/' . $themeName
        );
        return $cacheName;
    }

    /**
     * Create a "no-cache" setting.
     *
     * @param string $cacheName Name of "no cache" to create
     *
     * @return void
     */
    protected function createNoCache($cacheName)
    {
        $this->cacheSettings[$cacheName] = false;
    }

    /**
     * Add a file cache to the manager and ensure that necessary directory exists.
     *
     * @param string $cacheName Name of new cache to create
     * @param string $dirName   Directory to use for storage
     *
     * @return void
     */
    protected function createFileCache($cacheName, $dirName)
    {
        $opts = $this->defaults;    // copy defaults -- we'll modify them below
        if (!is_dir($dirName)) {
            if (isset($opts['umask'])) {
                // convert umask from string
                $umask = octdec($opts['umask']);
                // validate
                if ($umask & 0700) {
                    throw new \Exception(
                        'Invalid umask: ' . $opts['umask']
                        . '; need permission to execute, read and write by owner'
                    );
                }
                umask($umask);
            }
            if (isset($opts['dir_permission'])) {
                $dir_perm = octdec($opts['dir_permission']);
            } else {
                // 0777 is chmod default, use if dir_permission is not explicitly set
                $dir_perm = 0777;
            }
            // Make sure cache parent directory and directory itself exist:
            $parentDir = dirname($dirName);
            if (!is_dir($parentDir) && !@mkdir($parentDir, $dir_perm)) {
                $this->directoryCreationError = true;
            }
            if (!@mkdir($dirName, $dir_perm)) {
                $this->directoryCreationError = true;
            }
        }
        if (empty($opts)) {
            $opts = ['cache_dir' => $dirName];
        } elseif (is_array($opts)) {
            // If cache_dir was set in config.ini, the cache-specific name should
            // have been appended to the path to create the value $dirName.
            $opts['cache_dir'] = $dirName;
        } else {
            // Dryrot
            throw new \Exception('$opts is neither array nor false');
        }
        $this->cacheSettings[$cacheName] = [
            'adapter' => ['name' => 'filesystem', 'options' => $opts],
            'plugins' => ['serializer']
        ];
    }

    /**
     * Add an APC cache to the manager.
     *
     * @param string $cacheName Name of new cache to create
     *
     * @return void
     */
    protected function createAPCCache($cacheName)
    {
        $this->cacheSettings[$cacheName] = [
            'adapter' => 'APC',
            'plugins' => ['serializer']
        ];
    }
}