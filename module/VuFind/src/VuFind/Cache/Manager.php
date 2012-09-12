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
use VuFind\Config\Reader as ConfigReader, Zend\Cache\StorageFactory;

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
    protected $cacheSettings = array();

    /**
     * Actual cache objects generated from settings.
     *
     * @var array
     */
    protected $caches = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        // Get base cache directory.
        $cacheBase = $this->getCacheDir();

        // Set up basic object cache:
        $this->createFileCache('object', $cacheBase . 'objects');

        // Set up language cache:
        $this->createFileCache('language', $cacheBase . 'languages');

        // Set up search specs cache based on config settings:
        $config = ConfigReader::getConfig('searches');
        $cacheSetting = isset($config->Cache->type) ? $config->Cache->type : false;
        switch ($cacheSetting) {
        case 'APC':
            $this->createAPCCache('searchspecs');
            break;
        case 'File':
            $this->createFileCache(
                'searchspecs', $cacheBase . 'searchspecs'
            );
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
            $this->caches[$key] = StorageFactory::factory(
                $this->cacheSettings[$key]
            );
        }
        return $this->caches[$key];
    }

    /**
     * Get the path to the directory containing VuFind's cache data.
     *
     * @return string
     */
    public function getCacheDir()
    {
        if (strlen(LOCAL_OVERRIDE_DIR) > 0) {
            return LOCAL_OVERRIDE_DIR . '/cache/';
        }
        return APPLICATION_PATH . '/data/cache/';
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
     * Add a file cache to the manager and ensure that necessary directory exists.
     *
     * @param string $cacheName Name of new cache to create
     * @param string $dirName   Directory to use for storage
     *
     * @return void
     */
    protected function createFileCache($cacheName, $dirName)
    {
        if (!is_dir($dirName)) {
            if (!@mkdir($dirName)) {
                $this->directoryCreationError = true;
            }
        }
        $this->cacheSettings[$cacheName] = array(
            'adapter' => array(
                'name' => 'filesystem',
                'options' => array('cache_dir' => $dirName)
            ),
            'plugins' => array('serializer')
        );
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
        $this->cacheSettings[$cacheName] = array(
            'adapter' => 'APC',
            'plugins' => array('serializer')
        );
    }
}