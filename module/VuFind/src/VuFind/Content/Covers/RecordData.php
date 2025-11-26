<?php

/**
 * RecordData cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2025.
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
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use VuFind\Record\Loader as RecordLoader;

/**
 * RecordData cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordData extends \VuFind\Content\AbstractCover
{
    /**
     * Constructor.
     *
     * @param RecordLoader $recordLoader Record loader
     */
    public function __construct(protected RecordLoader $recordLoader)
    {
        $this->cacheAllowed = true;
        $this->supportsRecordid = true;
    }

    /**
     * Get image URL for a particular recordId (or false if not found).
     *
     * @param string $key  API key, unused
     * @param string $size Size of image to load (small/medium/large), unused
     * @param array  $ids  Associative array of identifiers
     *
     * @return string|bool URL of the image, or false if no valid image is found
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        $recordId = $ids['recordid'];
        $source = $ids['source'] ?? DEFAULT_SEARCH_BACKEND;
        $driver = $this->recordLoader->load($recordId, $source);
        $marc = $driver->getMarcReader();
        $fields856 = $marc->getFields('856');
        foreach ($fields856 as $field) {
            $description = $marc->getSubfield($field, '3');
            if (stripos($description, 'cover') !== false) {
                $url = $marc->getSubfield($field, 'u');
                return $url;
            }
        }
        return false;
    }
}
