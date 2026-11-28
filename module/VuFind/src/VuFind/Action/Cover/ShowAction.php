<?php

/**
 * Show cover image action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Cover;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;

/**
 * Show cover image action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ShowAction extends AbstractCoverAction
{
    /**
     * Display a cover image.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->getSessionSettings()->disableWrite(); // avoid session write timing bug

        // Special case: proxy a full URL:
        $url = $this->getQueryParam('proxy');
        if ($url && $this->proxyAllowedForUrl($url)) {
            try {
                $image = $this->proxy->fetch($url);
                $contentType = $image?->getHeaders()?->get('content-type')?->getFieldValue() ?? '';
                if ($this->isValidProxyImageContentType($contentType)) {
                    return $this->displayImage(
                        $response,
                        $contentType,
                        $image->getContent()
                    );
                }
            } catch (\Exception $e) {
                // If an exception occurs, drop through to the standard case
                // to display an image unavailable graphic.
            }
        }

        // Default case -- use image loader:
        $this->loader->loadImage($this->getImageParams());
        return $this->displayImage($response);
    }

    /**
     * Is the provided URL included on the configured allow list?
     *
     * @param string $url URL to check
     *
     * @return bool
     */
    protected function proxyAllowedForUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        foreach ((array)($this->config['Content']['coverproxyAllowedHosts'] ?? []) as $regEx) {
            if (preg_match($regEx, $host)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is the content type allowed by the cover proxy?
     *
     * @param string $contentType Type to check
     *
     * @return bool
     */
    protected function isValidProxyImageContentType(string $contentType): bool
    {
        $validTypes = $this->config['Content']['coverproxyAllowedTypes']
            ?? ['image/gif', 'image/jpeg', 'image/png'];
        return in_array(strtolower($contentType), array_map('strtolower', $validTypes));
    }

    /**
     * Convert image parameters into an array for use by the image loader.
     *
     * @return array
     */
    protected function getImageParams(): array
    {
        $isbns = null;
        // Legacy support for "isn", "isbn" param which has been superseded by isbns:
        foreach (['isbns', 'isbn', 'isn'] as $identification) {
            if ($isbns = $this->getQueryParam($identification)) {
                break;
            }
        }
        return [
            'isbns' => $isbns ? (array)$isbns : null,
            'size' => $this->getQueryParam('size'),
            'type' => $this->getQueryParam('contenttype'),
            'title' => $this->getQueryParam('title'),
            'author' => $this->getQueryParam('author'),
            'callnumber' => $this->getQueryParam('callnumber'),
            'issn' => $this->getQueryParam('issn'),
            'oclc' => $this->getQueryParam('oclc'),
            'upc' => $this->getQueryParam('upc'),
            'recordid' => $this->getQueryParam('recordid'),
            'source' => $this->getQueryParam('source'),
            'nbn' => $this->getQueryParam('nbn'),
            'ismn' => $this->getQueryParam('ismn'),
        ];
    }
}
