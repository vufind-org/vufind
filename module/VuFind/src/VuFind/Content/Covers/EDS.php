<?php

/**
 * EDS cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University Board of Trustees 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Content
 * @author   Megan Schanz <schamzme@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use Laminas\Cache\Storage\StorageInterface;

/**
 * EDS cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Megan Schanz <schamzme@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EDS extends \VuFind\Content\AbstractCover implements
    \Psr\Log\LoggerAwareInterface,
    \VuFind\Http\CachingDownloaderAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\Http\CachingDownloaderAwareTrait;
    use \VuFind\Cache\CacheTrait;

    /**
     * Constructor.
     *
     * @param StorageInterface $cache Cache
     */
    public function __construct(StorageInterface $cache)
    {
        $this->supportsRecordid = $this->cacheAllowed = true;
        $this->setCacheStorage($cache);
    }

    /**
     * Set the key to store in the cache to share between EDS cover loader and record driver.
     *
     * @param string $key Key to put in the cache
     *
     * @return string The determined key
     */
    protected function getCacheKey($key = '')
    {
        return 'EDS_Shared_' . md5($key);
    }

    /**
     * Determine if this handler supports the provided identifiers.
     *
     * In this case, we look for the recordid and source keys
     * that are required for proxying the image and making sure the source
     * is EDS.
     *
     * @param array $ids Array of identifiers (recordid, isbn, etc.)
     *
     * @return bool
     */
    public function supports($ids)
    {
        return isset($ids['recordid'])
            && isset($ids['source'])
            && $ids['source'] === 'EDS';
    }

    /**
     * Get an image URL for the specific record.
     *
     * This is the primary method used by the Cover Loader manager.
     *
     * @param string $key  Cover provider key (e.g. 'eds')
     * @param string $size Size of image requested
     * @param array  $ids  Array of identifiers
     *
     * @return string|bool   URL of the image or false if unavailable
     */
    public function getUrl($key, $size, $ids)
    {
        $recordId = $ids['recordid'] ?? '';
        $url = $this->getCachedData($recordId);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $this->debug('Returning Cover image URL: ' . $url);
            return $url;
        }

        return false;
    }
}
