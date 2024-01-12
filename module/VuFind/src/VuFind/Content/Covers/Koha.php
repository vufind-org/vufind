<?php

/**
 * Koha cover content loader.
 *
 * PHP version 8
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use function in_array;

/**
 * Koha cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:content_provider_components
 */
class Koha extends \VuFind\Content\AbstractCover
{
    /**
     * Base URL for Koha covers
     *
     * @var string
     */
    protected $url;

    /**
     * List of sizes for which we should return the thumbnail. Since Koha only has
     * two sizes, this helps us control mapping between VuFind and Koha sizes.
     *
     * @var string[]
     */
    protected $thumbnailSizes = ['small', 'medium'];

    /**
     * Constructor
     *
     * @param string $url Base URL for Koha covers
     */
    public function __construct(string $url)
    {
        $this->url = $url;
        $this->supportsRecordid = true;
        $this->cacheAllowed = true;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        // We can only do Koha lookups by bib ID:
        if (!isset($ids['recordid'])) {
            return false;
        }
        $url = $this->url . '?';
        if (in_array($size, $this->thumbnailSizes)) {
            $url .= 'thumbnail=1&';
        }
        $url .= 'biblionumber=' . urlencode($ids['recordid']);
        return $url;
    }
}
