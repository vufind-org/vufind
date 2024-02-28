<?php

/**
 * Orb cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Frédéric Demians <f.demians@tamil.fr>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

/**
 * Orb cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Frédéric Demians <f.demians@tamil.fr>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:content_provider_components
 */
class Orb extends \VuFind\Content\AbstractCover implements \VuFind\Http\CachingDownloaderAwareInterface
{
    use \VuFind\Http\CachingDownloaderAwareTrait;

    /**
     * Base URL for Orb API
     *
     * @var string
     */
    protected $url;

    /**
     * API user for Orb
     *
     * @var string
     */
    protected $apiUser;

    /**
     * API key for Orb
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Constructor
     *
     * @param string $url     Base URL for Orb
     * @param string $apiUser API key for Orb
     * @param string $apiKey  API key for Orb
     */
    public function __construct($url, $apiUser, $apiKey)
    {
        $this->url = $url;
        $this->apiUser = $apiUser;
        $this->apiKey = $apiKey;
        $this->supportsIsbn = $this->cacheAllowed = true;
        $this->cacheOptionsSection = 'OrbCover';
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
        if (!isset($ids['isbn'])) {
            return false;
        }
        $ean = $ids['isbn']->get13();

        $url = 'https://' . $this->apiUser . ':' . $this->apiKey . '@' .
               $this->url . '/products?eans=' . $ean . '&sort=ean_asc';

        if (!isset($this->cachingDownloader)) {
            throw new \Exception('CachingDownloader initialization failed.');
        }
        $json = $this->cachingDownloader->downloadJson($url);
        $imageVersion = $size == 'small' ? 'thumbnail' : 'original';
        foreach ($json->data as $title) {
            if (
                $title->ean13 == $ean
                && isset($title->images->front->$imageVersion->src)
            ) {
                return $title->images->front->$imageVersion->src;
            }
        }
        return false;
    }
}
