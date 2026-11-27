<?php

/**
 * Response helper.
 *
 * PHP version 8
 *
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
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Http;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Response helper.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResponseHelper
{
    /**
     * Response stream chunk size.
     *
     * @var int
     */
    public const CHUNK_SIZE = 8192;

    /**
     * Create a default response.
     *
     * @return ResponseInterface
     */
    public function createResponse(): ResponseInterface
    {
        return new Response();
    }

    /**
     * Output a response.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    public function outputResponse(ResponseInterface $response): void
    {
        $this->outputHeaders($response);
        $this->outputBody($response);
    }

    /**
     * Output response headers.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function outputHeaders(ResponseInterface $response): void
    {
        // Don't do anything if headers have already been sent:
        if (headers_sent()) {
            return;
        }

        $status = 'HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode();
        if ($reason = $response->getReasonPhrase()) {
            $status .= " $reason";
        }

        header($status);

        foreach ($response->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                header("$header: $value");
            }
        }
    }

    /**
     * Output response body.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function outputBody(ResponseInterface $response): void
    {
        ob_end_flush();
        $body = $response->getBody();
        $body->rewind();
        while (!$body->eof()) {
            echo $body->read(self::CHUNK_SIZE);
        }
    }
}
