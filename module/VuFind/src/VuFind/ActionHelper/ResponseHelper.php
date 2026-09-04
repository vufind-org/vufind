<?php

/**
 * Action helper for generating responses.
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\ActionHelper;

use Psr\Http\Message\ResponseInterface;
use VuFind\Http\HttpStatus;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Action helper for generating responses.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ResponseHelper implements HelperInterface, TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Construct an HTTP 205 (refresh) response. Useful for reporting success in the lightbox without actually rendering
     * content.
     *
     * @param ResponseInterface $response Response
     * @param bool              $forceGet If true, sends a custom header indicating that the page should be reloaded
     * with a GET request. This can be useful when it is known that the current page only receives transient params in
     * a POST request (such as canceling of holds).
     *
     * @return ResponseInterface
     */
    public function getRefreshResponse(ResponseInterface $response, bool $forceGet = false): ResponseInterface
    {
        $response = $response->withStatus(205);
        if ($forceGet) {
            $response = $response->withHeader('X-VuFind-Refresh-Method', 'GET');
        }
        return $response;
    }

    /**
     * Get a response with the AJAX output data.
     *
     * @param ResponseInterface $response     Response
     * @param string            $type         Content type to output
     * @param mixed             $data         The response data
     * @param ?int              $httpCode     A custom HTTP Status Code or null for default (200)
     * @param bool              $allowCaching Allow the response to be cached (defaults to false)?
     * @param int               $jsonFlags    Additional flags for json_encode
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public function getAjaxResponse(
        ResponseInterface $response,
        string $type,
        mixed $data,
        ?int $httpCode = null,
        bool $allowCaching = false,
        int $jsonFlags = 0
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', $type);
        if (!$allowCaching) {
            $response = $response->withHeader('Cache-Control', 'no-cache, must-revalidate')
                ->withHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        }
        if ($httpCode !== null) {
            $response = $response->withStatus($httpCode);
        }
        $response->getBody()->write($this->formatContent($type, $data, $httpCode, $jsonFlags));
        return $response;
    }

    /**
     * Get a response with JSON output data.
     *
     * @param ResponseInterface $response     Response
     * @param mixed             $data         The response data
     * @param ?int              $httpCode     A custom HTTP Status Code or null for default (200)
     * @param bool              $allowCaching Allow the response to be cached (defaults to false)?
     * @param int               $jsonFlags    Additional flags for json_encode
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public function getJsonResponse(
        ResponseInterface $response,
        mixed $data,
        ?int $httpCode = null,
        bool $allowCaching = false,
        int $jsonFlags = 0
    ): ResponseInterface {
        return $this->getAjaxResponse(
            $response,
            'application/json',
            $data,
            $httpCode,
            $allowCaching,
            $jsonFlags
        );
    }

    /**
     * Turn an exception into error response.
     *
     * @param ResponseInterface $response Response
     * @param string            $type     Content type to output
     * @param \Exception        $e        Exception to output
     *
     * @return ResponseInterface
     */
    public function getExceptionResponse(ResponseInterface $response, string $type, \Exception $e): ResponseInterface
    {
        $errorMsg = $this->translate('An error has occurred');
        $debugMsg = ('development' == APPLICATION_ENV) ? ': ' . (string)$e : '';
        return $this->getAjaxResponse(
            $response,
            $type,
            $errorMsg . $debugMsg,
            HttpStatus::ERROR
        );
    }

    /**
     * Format the content of a response based on the response type.
     *
     * @param string $type      Content-type of output
     * @param mixed  $data      The response data
     * @param ?int   $httpCode  A custom HTTP Status Code
     * @param int    $jsonFlags Additional flags for json_encode
     *
     * @return string
     * @throws \Exception
     */
    protected function formatContent(
        string $type,
        mixed $data,
        ?int $httpCode,
        int $jsonFlags = 0
    ): string {
        switch ($type) {
            case 'application/javascript':
            case 'application/json':
                return json_encode($data, $jsonFlags);
            case 'text/plain':
                return ((null !== $httpCode && $httpCode >= 400) ? 'ERROR ' : 'OK ') . $data;
            case 'text/html':
                return $data ?: '';
            default:
                throw new \Exception("Unsupported content type: $type");
        }
    }
}
