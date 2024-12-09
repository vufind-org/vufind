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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Exception;

use Laminas\Http\Headers;

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
     * URL we tried to download.
     *
     * @var string
     */
    protected $url;

    /**
     * HTTP status associated with this exception.
     *
     * @var ?int
     */
    protected $statusCode;

    /**
     * HTTP response headers associated with this exception.
     *
     * @var ?Headers
     */
    protected $responseHeaders;

    /**
     * HTTP response body associated with this exception.
     *
     * @var ?string
     */
    protected $responseBody;

    /**
     * Constructor
     *
     * @param string          $message         Exception message
     * @param string          $url             URL we tried to download
     * @param int|null        $statusCode      HTTP status code
     * @param Headers|null    $responseHeaders HTTP response headers
     * @param string|null     $responseBody    HTTP response body
     * @param \Throwable|null $previous        Previous exception
     */
    public function __construct(
        string $message,
        string $url,
        ?int $statusCode = null,
        ?Headers $responseHeaders = null,
        ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        $this->url = $url;
        $this->statusCode = $statusCode;
        $this->responseHeaders = $responseHeaders;
        $this->responseBody = $responseBody;
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
     * @return ?Headers
     */
    public function getResponseHeaders(): ?Headers
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
