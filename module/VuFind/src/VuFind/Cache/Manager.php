<?php

/**
 * VuFind Cache Manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007
 * Copyright (C) Leipzig University Library <info@ub.uni-leipzig.de> 2018
 * Copyright (C) The National Library of Finland 2024
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
 * @package  Cache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Cache;

use Laminas\Cache\Service\StorageAdapterFactory;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Config\Config;

use function dirname;
use function is_array;
use function strlen;

/**
 * VuFind Cache Manager
 *
 * Creates caches based on configuration
 *
 * @category VuFind
 * @package  Cache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
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
     * @var StorageInterface[]
     */
    protected $caches = [];

    /**
     * Factory for creating storage adapters.
     *
     * @var StorageAdapterFactory
     */
    protected $factory;

    /**
     * Cache configuration.
     *
     * Following settings are supported:
     *
     *   cliOverride   Set to false to not allow cache directory override in CLI mode (optional, true by default)
     *   directory     Cache directory (required)
     *   options       Array of cache options (optional, e.g. disabled, ttl)
     *   persistent    Set to true to disable clearing of the cache by default with the admin API clearCache command
     *                 (optional, false by default)
     *
     * @var array
     */
    protected $cacheSpecs = [
        'browscap' => [
            'cliOverride' => false,
            'directory' => 'browscap',
            'options' => [
                'ttl' => 0, // no expiration - cache is updated with console util/browscap
                'keyPattern' => '/^[a-z0-9_\+\-\.]*$/Di',
            ],
            'persistent' => true,
        ],
        'config' => [
            'directory' => 'configs',
        ],
        'cover' => [
            'directory' => 'covers',
            'persistent' => true,
        ],
        'language' => [
            'directory' => 'languages',
        ],
        'object' => [
            'directory' => 'objects',
        ],
        'public' => [
            'directory' => 'public',
        ],
        'searchspecs' => [
            'directory' => 'searchspecs',
        ],
        'yaml' => [
            'directory' => 'yamls',
        ],
    ];

    /**
     * Constructor
     *
     * @param Config                $config       Main VuFind configuration
     * @param Config                $searchConfig Search configuration
     * @param StorageAdapterFactory $factory      Cache storage adapter factory
     */
    public function __construct(
        Config $config,
        Config $searchConfig,
        StorageAdapterFactory $factory
    ) {
        $this->factory = $factory;

        // $config and $config->Cache are Laminas\Config\Config objects
        // $cache is created immutable, so get the array, it will be modified
        // downstream.
        $this->defaults = $config->Cache?->toArray() ?? [];

        // Configure search specs cache based on config settings:
        $searchCacheType = $searchConfig->Cache->type ?? false;
        switch ($searchCacheType) {
            case 'File':
                // Default
                break;
            case false:
                $this->cacheSpecs['searchspecs']['options']['disabled'] = true;
                break;
            default:
                throw new \Exception("Unsupported cache setting: $searchCacheType");
        }
    }

    /**
     * Retrieve the specified cache object.
     *
     * @param string      $name      Name of the requested cache.
     * @param string|null $namespace Optional namespace to use. Defaults to the
     * value of $name.
     *
     * @return StorageInterface
     * @throws \Exception
     */
    public function getCache($name, $namespace = null)
    {
        $this->ensureFileCache($name);
        $namespace ??= $name;
        $key = "$name:$namespace";

        if (!isset($this->caches[$key])) {
            if (!isset($this->cacheSettings[$name])) {
                throw new \Exception('Requested unknown cache: ' . $name);
            }
            $settings = $this->cacheSettings[$name];
            $settings['options']['namespace'] = $namespace;
            $this->caches[$key]
                = $this->factory->createFromArrayConfiguration($settings);
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
        if (isset($this->defaults['cache_dir'])) {
            // cache_dir setting in config.ini is obsolete
            throw new \Exception(
                'Obsolete cache_dir setting found in config.ini - please use '
                . 'Apache environment variable VUFIND_CACHE_DIR in '
                . 'httpd-vufind.conf instead.'
            );
        }

        if (strlen(LOCAL_CACHE_DIR) > 0) {
            $dir = LOCAL_CACHE_DIR . '/';
        } elseif (strlen(LOCAL_OVERRIDE_DIR) > 0) {
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
        return array_unique(
            [
                ...array_keys($this->cacheSpecs),
                ...array_keys($this->cacheSettings),
            ]
        );
    }

    /**
     * Get the names of all non-persistent caches (ones that can be cleared).
     *
     * @return array
     */
    public function getNonPersistentCacheList(): array
    {
        $result = [];
        foreach ($this->getCacheList() as $cache) {
            if (!($this->cacheSpecs[$cache]['persistent'] ?? false)) {
                $result[] = $cache;
            }
        }
        return $result;
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
     * Create a downloader-specific file cache.
     *
     * @param string $downloaderName Name of the downloader.
     * @param array  $opts           Cache options.
     *
     * @return string
     */
    public function addDownloaderCache($downloaderName, $opts = [])
    {
        $cacheName = 'downloader-' . $downloaderName;
        $this->createFileCache(
            $cacheName,
            $this->getCacheDir(),
            $opts
        );
        return $cacheName;
    }

    /**
     * Create a new file cache for the given theme name if necessary. Return
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
     * Ensure that a file cache is properly set up
     *
     * @param string $name Cache name
     *
     * @return void
     */
    protected function ensureFileCache(string $name): void
    {
        // Use $this->cacheSettings to determine if $this->createFileCache() has been called yet:
        if (!isset($this->cacheSettings[$name]) && $config = $this->cacheSpecs[$name] ?? null) {
            $base = $this->getCacheDir($config['cliOverride'] ?? true);
            $this->createFileCache($name, $base . $config['directory'], $config['options'] ?? []);
        }
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
        $this->cacheSettings[$cacheName] = [
            'adapter' => \Laminas\Cache\Storage\Adapter\BlackHole::class,
            'options' => [],
        ];
    }

    /**
     * Add a file cache to the manager and ensure that necessary directory exists.
     *
     * @param string $cacheName    Name of new cache to create
     * @param string $dirName      Directory to use for storage
     * @param array  $overrideOpts Options to override default values.
     *
     * @return void
     */
    protected function createFileCache($cacheName, $dirName, $overrideOpts = [])
    {
        $opts = array_merge($this->defaults, $overrideOpts);
        if ($opts['disabled'] ?? false) {
            $this->createNoCache($cacheName);
            return;
        } else {
            // Laminas does not support "disabled = false"; unset to avoid error.
            unset($opts['disabled']);
        }

        if (!is_dir($dirName)) {
            if (isset($opts['umask'])) {
                // convert umask from string
                $umask = octdec($opts['umask']);
                // validate
                if ($umask & 0o700) {
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
                $dir_perm = 0o777;
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
            // If VUFIND_CACHE_DIR was set in the environment, the cache-specific
            // name should have been appended to it to create the value $dirName.
            $opts['cache_dir'] = $dirName;
        } else {
            // Dryrot
            throw new \Exception('$opts is neither array nor false');
        }
        $this->cacheSettings[$cacheName] = [
            'adapter' => \Laminas\Cache\Storage\Adapter\Filesystem::class,
            'options' => $opts,
            'plugins' => [
                ['name' => 'serializer'],
            ],
        ];
    }
}
