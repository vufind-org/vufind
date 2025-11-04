<?php

/**
 * VuFind YAML Configuration Reader
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @package  Config
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Config;

/**
 * VuFind YAML Configuration Reader
 *
 * @category VuFind
 * @package  Config
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class YamlReader extends \VuFind\Config\YamlReader
{
    /**
     * Return a Finna configuration (Finna default or view specific)
     *
     * @param string  $filename        Config file name
     * @param boolean $localDir        Config directory (local/finna or local/vufind)
     * @param boolean $ignoreFileCache Read from file even if config has been cached.
     *
     * @return array
     */
    public function getFinna(
        $filename,
        $localDir = 'local/vufind',
        $ignoreFileCache = false
    ) {
        $key = "$localDir/$filename";

        if ($ignoreFileCache || !isset($this->files[$key])) {
            $cache = (null !== $this->cacheManager)
                ? $this->cacheManager->getCache($this->cacheName) : false;

            // Determine full configuration file path:
            $fullpath
                = $this->pathResolver->getLocalConfigPath($filename, $localDir);

            // Generate cache key:
            $cacheKey = $filename . '-'
                . ($fullpath ? filemtime($fullpath) : 0)
                . '-' . $localDir;

            $cacheKey = md5($cacheKey);

            $results = $this->parseYaml($fullpath);
            $this->files[$key] = $results;

            if ($cache !== false) {
                $cache->setItem($cacheKey, $results);
            }
        }

        return $this->files[$key];
    }
}
