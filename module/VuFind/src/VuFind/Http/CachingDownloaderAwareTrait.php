<?php
/**
 * Lightweight caching downloader aware marker trait.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Http;

/**
 * Lightweight caching downloader aware marker trait.
 *
 * @category VuFind
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait CachingDownloaderAwareTrait
{
    /**
     * Cache ID. This can be overridden by child classes if they want to use
     * a separate cache.
     *
     * @var string
     */
    protected $downloaderCacheId = 'downloader';

    /**
     * Client options. This can be overridden, e.g. to set a specific
     * user-agent.
     *
     * @var array
     */
    protected $downloaderClientOptions = [];

    /**
     * Caching downloader
     *
     * @var CachingDownloader
     */
    protected $cachingDownloader = null;

    /**
     * Set caching downloader
     *
     * @param $cachingDownloader CachingDownloader
     *
     * @return void
     */
    public function setCachingDownloader(CachingDownloader $cachingDownloader)
    {
        $this->cachingDownloader = $cachingDownloader;
        $this->cachingDownloader->setCacheId($this->downloaderCacheId);
        $this->cachingDownloader->setClientOptions($this->downloaderClientOptions);
    }
}
