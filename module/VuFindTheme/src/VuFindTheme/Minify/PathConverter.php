<?php

/**
 * CSS path converter extension
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindTheme\Minify;

/**
 * CSS path converter extension
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PathConverter extends \MatthiasMullie\PathConverter\Converter
{
    /**
     * Normalize path.
     *
     * @param string $path Path
     *
     * @return string
     */
    protected function normalize($path)
    {
        $path = parent::normalize($path);

        $path = str_replace('/local/cache/public', '/cache', $path);

        return $path;
    }

    /**
     * Convert paths relative to the themes directory.
     *
     * Takes advantage of the fact that we know the themes directory will be
     * '../themes' relative to the cache directory. This allows path resolution to
     * work regardless of whether there are symlinked directories or other
     * differences between the actual file system path and the path used to access
     * the theme files.
     *
     * @param string $path The relative path that needs to be converted
     *
     * @return string The new relative path
     */
    public function convert($path)
    {
        $path = $this->from . '/' . $path;
        $path = preg_replace('/.*?\/themes\//', '../themes/', $path);

        // Remove .. parts in the middle of the resulting path:
        $parts = explode('/', $path);
        $result = [];
        $last = '';
        foreach ($parts as $part) {
            if ('' !== $last && '..' !== $last && '..' === $part) {
                array_pop($result);
                continue;
            }
            $last = $part;
            $result[] = $part;
        }

        return implode('/', $result);
    }
}
