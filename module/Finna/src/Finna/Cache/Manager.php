<?php

/**
 * Finna Cache Manager
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2024.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Cache;

use Laminas\Cache\Service\StorageAdapterFactory;

/**
 * Finna Cache Manager
 *
 * @category VuFind
 * @package  Cache
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager extends \VuFind\Cache\Manager
{
    /**
     * Cache configuration.
     *
     * Following settings are supported:
     *
     *   cliOverride   Set to false to not allow cache directory override in CLI mode (optional, enabled by default)
     *   directory     Cache directory (required)
     *   options       Array of cache options (optional, e.g. disabled, ttl)
     *
     * @var array
     */
    protected $finnaCacheSpecs = [
        'codesets' => [
            'directory' => 'codesets',
            'options' => [
                // Code sets cache should live for as long as possible.
                // Refreshing of the cache is based on a separate setting to safeguard
                // against API unavailability or errors.
                'ttl' => 0,
            ],
            'persistent' => true,
        ],
        'description' => [
            'directory' => 'descriptions',
            'persistent' => true,
        ],
        'feed' => [
            'directory' => 'feeds',
            'persistent' => true,
        ],
        'organisation-info' => [
            'directory' => 'organisation-infos',
            'persistent' => true,
        ],
        'stylesheet' => [
            'directory' => 'stylesheets',
        ],
    ];

    /**
     * Constructor
     *
     * @param array                 $config  Main VuFind configuration
     * @param StorageAdapterFactory $factory Cache storage adapter factory
     */
    public function __construct(
        array $config,
        StorageAdapterFactory $factory
    ) {
        $this->cacheSpecs = array_merge($this->cacheSpecs, $this->finnaCacheSpecs);
        parent::__construct($config, $factory);
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
        $this->createNoCache($cacheName);
        return $cacheName;
    }
}
