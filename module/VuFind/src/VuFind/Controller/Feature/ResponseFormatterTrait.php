<?php

/**
 * VuFind Action Feature Trait - HTTP response formatting support methods
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use Laminas\Http\Response;

/**
 * VuFind Action Feature Trait - HTTP response formatting support methods
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ResponseFormatterTrait
{
    /**
     * Get a JSON response from an array of data
     *
     * @param array $data       Data to encode
     * @param int   $statusCode HTTP status code
     *
     * @return Response
     */
    protected function getJsonResponse(array $data, int $statusCode = 200): Response
    {
        $response = new Response();
        $response->setStatusCode($statusCode);
        $response->getHeaders()->addHeaderLine('Content-type', 'application/json');
        $response->setContent(json_encode($data));
        return $response;
    }

    /**
     * Add CORS headers to a response.
     *
     * @param Response $response         Response
     * @param array    $allowedMethods   Allowed HTTP methods
     * @param array    $allowedHeaders   Allowed HTTP headers
     * @param string   $allowedOrigin    Allowed origin (see
     * https://developer.mozilla.org/
     * en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Origin for details)
     * @param bool     $allowCredentials Whether credentials are allowed
     * @param int      $maxAge           Maximum time in seconds the information
     * from a preflight request can be cached
     *
     * @return void
     */
    protected function addCorsHeaders(
        Response $response,
        array $allowedMethods = ['GET', 'POST', 'OPTIONS'],
        array $allowedHeaders = [],
        string $allowedOrigin = '*',
        bool $allowCredentials = false,
        int $maxAge = 86400
    ): void {
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Access-Control-Allow-Methods',
            implode(', ', $allowedMethods)
        );
        if ($allowedHeaders) {
            $headers->addHeaderLine(
                'Access-Control-Allow-Headers',
                implode(', ', $allowedHeaders)
            );
        }
        $headers->addHeaderLine(
            "Access-Control-Allow-Origin: $allowedOrigin"
        );
        if ('*' !== $allowedOrigin) {
            $headers->addHeaderLine('Vary: Origin');
        }
        if ($allowCredentials) {
            // Note: true is the only valid value; false must not be used.
            $headers->addHeaderLine(
                'Access-Control-Allow-Credentials: true'
            );
        }
        $headers->addHeaderLine('Access-Control-Max-Age', $maxAge);
    }
}
