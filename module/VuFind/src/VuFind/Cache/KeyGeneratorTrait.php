<?php

/**
 * VuFind Cache Key Generator Trait
 *
 * PHP version 8
 *
 * Copyright (C) Leipzig University Library 2016.
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
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:caching
 */

namespace VuFind\Cache;

use function get_class;

/**
 * VuFind Cache Key Generator Trait
 *
 * Provides functions for generating uniform cache keys.
 *
 * @category VuFind
 * @package  Cache
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:caching
 */
trait KeyGeneratorTrait
{
    /**
     * Method to ensure uniform cache keys for cached VuFind objects.
     *
     * @param string|null $suffix Optional suffix that will get appended to the
     * object class name calling getCacheKey()
     *
     * @return string
     */
    protected function getCacheKey($suffix = null)
    {
        // Build the raw key combining the calling classname with an optional suffix
        $key = get_class($this) . (!empty($suffix) ? '_' . $suffix : '');

        // Test the build key
        if (
            $this->cache
            && ($keyPattern = $this->cache->getOptions()->getKeyPattern())
            && !preg_match($keyPattern, $key)
        ) {
            // The key violates the currently set StorageAdapter key_pattern. Our
            // best guess is to remove any characters that do not match the only
            // default key_pattern for Laminas\Cache\StorageAdapters: the filesystem
            // adapter (default key_pattern "/^[a-z0-9_\+\-]*$/Di").
            // Any other custom pattern is assumed as less restrictive, thus the
            // transformed key should match the custom pattern.
            $key = preg_replace(
                "/([^a-z0-9_\+\-])+/Di",
                '',
                $key
            );
        }

        // If we are in production mode reduce the rawkey to md5 hash which will
        // keep the key length at handy 32hex
        return APPLICATION_ENV == 'production' ? md5($key) : $key;
    }
}
