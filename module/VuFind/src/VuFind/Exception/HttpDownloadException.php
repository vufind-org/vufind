<?php

/**
 * HTTP download exception
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Exception;

/**
 * "Format Unavailable" Exception
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HttpDownloadException extends \Exception implements HttpStatusInterface
{
    /**
     * Constructor
     *
     * @param string      $message         Exception message
     * @param string      $url             URL we tried to download
     * @param ?int        $statusCode      HTTP status code
     * @param ?array      $responseHeaders HTTP response headers
     * @param ?string     $responseBody    HTTP response body
     * @param ?\Throwable $previous        Previous exception
     */
    public function __construct(
        string $message,
        protected string $url,
        protected ?int $statusCode = null,
        protected ?array $responseHeaders = null,
        protected ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get HTTP status associated with this exception.
     *
     * @return ?int
     */
    public function getHttpStatus(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get HTTP response body.
     *
     * @return ?string
     */
    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Get HTTP response headers.
     *
     * @return ?array
     */
    public function getResponseHeaders(): ?array
    {
        return $this->responseHeaders;
    }

    /**
     * Get URL we tried to download.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
