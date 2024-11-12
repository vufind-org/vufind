<?php

/**
 * Demo cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @package  Content
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use VuFindTheme\ThemeInfo;

use function count;

/**
 * Demo cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Demo extends \VuFind\Content\AbstractCover
{
    /**
     * Constructor
     *
     * @param ThemeInfo $themeInfo Theme info
     */
    public function __construct(protected ThemeInfo $themeInfo)
    {
    }

    /**
     * Does this plugin support the provided ID array?
     *
     * @param array $ids IDs that will later be sent to load() -- see below.
     *
     * @return bool
     */
    public function supports($ids)
    {
        // We won't know what we need until we parse the path string; accept
        // everything at this stage:
        return true;
    }

    /**
     * Get image location from local file storage.
     *
     * @param string $key  local file directory path
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        $covers = $this->themeInfo->findInThemes('images/demo-cover-*');
        // selects either one of the available demo covers or no image
        // evenly distributed based on the checksum of the ids.
        $coverNum = crc32(serialize($ids)) % (count($covers) + 1);
        if ($path = $covers[$coverNum]['file'] ?? null) {
            return 'file://' . $path;
        }
        return false;
    }
}
