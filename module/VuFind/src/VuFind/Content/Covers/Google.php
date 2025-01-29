<?php

/**
 * Google cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

use VuFind\Exception\HttpDownloadException;
use VuFindCode\ISBN;

/**
 * Google cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Google extends \VuFind\Content\AbstractCover implements \VuFind\Http\CachingDownloaderAwareInterface
{
    use \VuFind\Http\CachingDownloaderAwareTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->supportsIsbn = $this->supportsOclc = true;
        $this->cacheOptionsSection = 'GoogleCover';
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool URL of the image, or false if no valid image is found
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        // Get an array of ISBNs; note that (array) casting won't have the desired
        // effect here, so we need to explicitly check for ISBN objects:
        $isbns = $ids['isbns'] ?? $ids['isbn'] ?? [];
        $isbns = $isbns instanceof ISBN ? [$isbns] : $isbns;

        // Initialize the identifiers list from our ISBNs; add OCLC number if available:
        $identifiers = array_map(fn ($isbn) => "ISBN:{$isbn->get13()}", $isbns);
        if (isset($ids['oclc'])) {
            $identifiers[] = "OCLC:{$ids['oclc']}";
        }

        if (empty($identifiers)) {
            return false;
        }

        // Construct the request URL and make the HTTP request, using a single URL with all identifiers
        $url = 'https://books.google.com/books?jscmd=viewapi&bibkeys='
            . urlencode(implode(',', $identifiers)) . '&callback=addTheCover';

        $decodeCallback = function (\Laminas\Http\Response $response, $url) {
            if (
                !preg_match(
                    '/^[^{]*({.*})[^}]*$/',
                    $response->getBody(),
                    $matches
                )
            ) {
                throw new HttpDownloadException(
                    'Invalid response body (raw)',
                    $url,
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody()
                );
            }

            // convert \x26 or \u0026 to &
            $json = json_decode(
                str_replace(['\\x26', '\\u0026'], '&', $matches[1]),
                true
            );

            if ($json === null) {
                throw new HttpDownloadException(
                    'Invalid response body (json)',
                    $url,
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody()
                );
            }

            return $json;
        };

        if (!isset($this->cachingDownloader)) {
            throw new \Exception('CachingDownloader initialization failed.');
        }

        $json = $this->cachingDownloader->download($url, [], $decodeCallback);
        // find the first thumbnail URL and process it:
        foreach ((array)$json as $current) {
            if (isset($current['thumbnail_url'])) {
                $imageUrl = $current['thumbnail_url'];
                if ($size == 'medium' || $size == 'large') {
                    $imageUrl = str_replace('zoom=5', 'zoom=1', $imageUrl);
                }
                return $imageUrl;
            }
        }
        return false;
    }
}
