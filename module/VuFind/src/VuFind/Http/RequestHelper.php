<?php

/**
 * Request helper.
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

use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function dirname;
use function strlen;

/**
 * Request helper.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RequestHelper
{
    /**
     * Base path of the request.
     *
     * @var ?string
     */
    protected ?string $basePath = null;

    /**
     * Constructor.
     *
     * @param ?array $serverContext Server context (like the $_SERVER superglobal that is used as context by default)
     */
    #[Autowire]
    public function __construct(
        protected ?array $serverContext = null,
    ) {
    }

    /**
     * Set server context.
     *
     * @param array $context New server context
     *
     * @return static
     */
    public function setServerContext(array $context): static
    {
        $this->serverContext = $context;
        return $this;
    }

    /**
     * Get the base path of the request.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        if (null === $this->basePath) {
            $this->basePath = $this->detectBasePath();
        }
        return $this->basePath;
    }

    /**
     * Create request from globals.
     *
     * @return ServerRequestInterface
     */
    public function createServerRequest(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }

    /**
     * Auto-detect the base path from the request environment.
     *
     * Uses a variety of criteria in order to detect the base URL of the request (i.e., anything additional to the
     * document root).
     *
     * Adapted from Laminas Request class.
     *
     * @return string
     */
    protected function detectBasePath(): string
    {
        $serverParams = $this->getServerContext();
        $filename = $serverParams['SCRIPT_FILENAME'] ?? '';
        $scriptName = $serverParams['SCRIPT_NAME'] ?? null;
        $phpSelf = $serverParams['PHP_SELF'] ?? null;
        $origScriptName = $serverParams['ORIG_SCRIPT_NAME'] ?? null;

        if ($scriptName !== null && basename($scriptName) === $filename) {
            $baseUrl = $scriptName;
        } elseif ($phpSelf !== null && basename($phpSelf) === $filename) {
            $baseUrl = $phpSelf;
        } elseif ($origScriptName !== null && basename($origScriptName) === $filename) {
            // 1and1 shared hosting compatibility.
            $baseUrl = $origScriptName;
        } else {
            // Backtrack up the SCRIPT_FILENAME to find the portion matching PHP_SELF:
            $baseUrl  = '/';
            $basename = basename($filename);
            if ($basename) {
                $path     = $phpSelf ? trim($phpSelf, '/') : '';
                $basePos  = strpos($path, $basename) ?: 0;
                $baseUrl .= substr($path, 0, $basePos) . $basename;
            }
        }

        // If the baseUrl is empty, then simply return an empty string:
        if (!$baseUrl) {
            return '';
        }

        // Does the base URL have anything in common with the request URI?
        $requestUri = $this->detectRequestUri($serverParams);

        // Full base URL matches.
        if (str_starts_with($requestUri, $baseUrl)) {
            return $baseUrl;
        }

        // Directory portion of base path matches.
        $baseDir = str_replace('\\', '/', dirname($baseUrl));
        if (str_starts_with($requestUri, $baseDir)) {
            return $baseDir;
        }

        $truncatedRequestUri = $requestUri;

        if (false !== ($pos = strpos($requestUri, '?'))) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl);

        // No match whatsoever
        if (empty($basename) || !str_contains($truncatedRequestUri, $basename)) {
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename out of the base path. $pos !== 0 makes sure
        // it is not matching a value from PATH_INFO or QUERY_STRING.
        if (
            strlen($requestUri) >= strlen($baseUrl)
            && (false !== ($pos = strpos($requestUri, $baseUrl)) && $pos !== 0)
        ) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return $baseUrl;
    }

    /**
     * Detect the base URI for the request.
     *
     * Looks at a variety of criteria in order to attempt to autodetect a base
     * URI, including rewrite URIs, proxy URIs, etc.
     *
     * Adapted from Laminas Request class.
     *
     * @param array $serverParams Server parameters
     *
     * @return string
     */
    protected function detectRequestUri(array $serverParams): string
    {
        // HTTP proxy requests setup request URI with scheme and host [and port] + the URL path, only use URL path.
        if ($requestUri = $serverParams['REQUEST_URI'] ?? null) {
            return preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
        }

        // No REQUEST_URI, so try ORIG_PATH_INFO:
        $origPathInfo = $serverParams['ORIG_PATH_INFO'] ?? null;
        if ($origPathInfo !== null) {
            $queryString = $serverParams['QUERY_STRING'] ?? null;
            if ($queryString !== '') {
                $origPathInfo .= '?' . $queryString;
            }
            return $origPathInfo;
        }

        return '/';
    }

    /**
     * Get server context.
     *
     * @return array
     */
    protected function getServerContext(): array
    {
        return $this->serverContext ?? $_SERVER;
    }
}
