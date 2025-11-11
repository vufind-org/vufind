<?php

/**
 * External data download feature trait
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Controller\Feature;

use GuzzleHttp\Psr7\Response;
use Laminas\Http\Headers;
use VuFind\Http\CachingDownloader;

/**
 * External data download feature trait
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait DownloadTrait
{
    /**
     * Download an image using CachingDownloader
     *
     * @param string $url Image URL
     *
     * @return array Associative array with keys contentType and content, or empty
     * array on failure
     */
    protected function downloadData(string $url): array
    {
        $downloader = $this->serviceLocator->get(CachingDownloader::class);
        try {
            return $downloader->download(
                $url,
                [],
                function (Response $response) {
                    $contentType = '';
                    if ($header = $response->getHeader('Content-Type')) {
                        $contentType = reset($header);
                    }
                    return [
                        'contentType' => $contentType,
                        'content' => $response->getBody(),
                    ];
                }
            );
        } catch (\VuFind\Exception\HttpDownloadException $e) {
            return [];
        }
    }

    /**
     * Set headers for browsers to cache the response
     *
     * @param Headers $headers Headers
     * @param ?int    $ttl     Caching time (Time To Live) in seconds
     *
     * @return void
     */
    protected function setCachingHeaders(Headers $headers, ?int $ttl = null): void
    {
        // Send proper caching headers so that the user's browser is able to cache
        // the content. Default TTL set at 14 days.

        $ttl ??= (60 * 60 * 24 * 14); // 14 days
        $headers->addHeaderLine('Cache-Control', "maxage=$ttl");
        $headers->addHeaderLine('Pragma', 'public');
        $headers->addHeaderLine(
            'Expires',
            gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT'
        );
    }

    /**
     * Check if the content type is an image
     *
     * @param string $contentType Content type
     *
     * @return bool
     */
    protected function isImageContentType(string $contentType): bool
    {
        return strncmp($contentType, 'image/', 6) === 0;
    }
}
