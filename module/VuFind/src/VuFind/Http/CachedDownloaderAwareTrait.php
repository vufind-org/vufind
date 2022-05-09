<?php
/**
 * Lightweight cached downloader aware marker trait.
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
 * Lightweight cached downloader aware marker trait.
 *
 * @category VuFind
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait CachedDownloaderAwareTrait
{
    /**
     * Cache ID. This can be overridden by child classes if we want to use
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
     * Cached downloader
     *
     * @var CachedDownloader
     */
    protected $cachedDownloader = null;

    /**
     * Set cached downloader
     *
     * @param $cachedDownloader CachedDownloader
     *
     * @return void
     */
    public function setCachedDownloader(CachedDownloader $cachedDownloader)
    {
        $this->cachedDownloader = $cachedDownloader;
        $this->cachedDownloader->setCacheId($this->downloaderCacheId);
        $this->cachedDownloader->setClientOptions($this->downloaderClientOptions);
    }
}
