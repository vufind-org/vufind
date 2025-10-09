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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Content
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Demo extends \VuFind\Content\AbstractCover
{
    /**
     * Constructor
     *
     * @param ThemeInfo $themeInfo Theme info
     * @param string    $baseUrl   VuFind's base URL
     */
    public function __construct(protected ThemeInfo $themeInfo, protected string $baseUrl)
    {
        $this->directUrls = true;
        $this->mandatoryBacklinkLocations = ['core'];
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
     * @param string $key  If backlink functionality should be used
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        $cover = $this->getCover($ids);
        if ($path = $cover['file'] ?? null) {
            return 'file://' . $path;
        }
        return false;
    }

    /**
     * Get cover metadata for a particular API key and set of IDs (or empty array).
     *
     * @param ?string $key  If backlink functionality should be used
     * @param string  $size Size of image to load (small/medium/large)
     * @param array   $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object, 'issn' pointing to a string and 'oclc' pointing
     * to an OCLC number string)
     *
     * @return array Array with keys: url, backlink_url, backlink_text
     */
    public function getMetadata(?string $key, string $size, array $ids)
    {
        $cover = $this->getCover($ids);
        $path = $cover['relativeFile'] ?? null;
        if (empty($path)) {
            return [];
        }
        $res = [
            'url' => $this->baseUrl . 'themes/' . $cover['theme'] . '/' . $path,
        ];
        if ($key === 'true') {
            $res['backlink_url'] = 'https://vufind.org';
            $res['backlink_text'] = 'vufind.org';
        }
        return $res;
    }

    /**
     * Selects demo covers or no cover based on the $ids array and returns location information.
     *
     * @param array $ids Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return array
     */
    protected function getCover($ids)
    {
        $covers = $this->themeInfo->findInThemes('images/demo-cover-*');
        // selects either one of the available demo covers or no image
        // evenly distributed based on the checksum of the ids.
        $coverNum = crc32($ids['recordid'] ?? serialize($ids)) % (count($covers) + 1);
        return $covers[$coverNum] ?? [];
    }
}
