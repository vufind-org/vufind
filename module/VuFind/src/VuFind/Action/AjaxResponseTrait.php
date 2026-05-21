<?php

/**
 * Trait to allow AJAX response generation.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\AjaxHandler\AjaxHandlerInterface;
use VuFind\AjaxHandler\PluginManager;

/**
 * Trait to allow AJAX response generation.
 *
 * Dependencies:
 * - \VuFind\I18n\Translator\TranslatorAwareTrait
 * - Injection of $this->ajaxManager (for some functionality)
 *
 * @category VuFind
 * @package  Action
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait AjaxResponseTrait
{
    /**
     * AJAX Handler plugin manager.
     *
     * @var PluginManager
     */
    protected $ajaxManager = null;

    /**
     * Format the content of the AJAX response based on the response type.
     *
     * @param string $type     Content-type of output
     * @param mixed  $data     The response data
     * @param int    $httpCode A custom HTTP Status Code
     *
     * @return string
     * @throws \Exception
     */
    protected function formatContent($type, $data, $httpCode)
    {
        switch ($type) {
            case 'application/javascript':
            case 'application/json':
                return json_encode(compact('data'));
            case 'text/plain':
                return ((null !== $httpCode && $httpCode >= 400) ? 'ERROR ' : 'OK ')
                    . $data;
            case 'text/html':
                return $data ?: '';
            default:
                throw new \Exception("Unsupported content type: $type");
        }
    }

    /**
     * Get a response with the AJAX output data.
     *
     * @param ResponseInterface $response Response
     * @param string            $type     Content type to output
     * @param mixed             $data     The response data
     * @param ?int              $httpCode A custom HTTP Status Code or null for default (200)
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function getAjaxResponse(
        ResponseInterface $response,
        string $type,
        mixed $data,
        ?int $httpCode = null
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', $type)
            ->withHeader('Cache-Control', 'no-cache, must-revalidate')
            ->withHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        if ($httpCode !== null) {
            $response = $response->withStatus($httpCode);
        }
        $response->getBody()->write($this->formatContent($type, $data, $httpCode));
        return $response;
    }

    /**
     * Turn an exception into error response.
     *
     * @param ResponseInterface $response Response
     * @param string            $type     Content type to output
     * @param \Exception        $e        Exception to output.
     *
     * @return ResponseInterface
     */
    protected function getExceptionResponse(ResponseInterface $response, string $type, \Exception $e): ResponseInterface
    {
        $debugMsg = ('development' == APPLICATION_ENV)
            ? ': ' . (string)$e : '';
        return $this->getAjaxResponse(
            $response,
            $type,
            $this->translate('An error has occurred') . $debugMsg,
            AjaxHandlerInterface::STATUS_HTTP_ERROR
        );
    }

    /**
     * Call an AJAX method and turn the result into a response.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string                 $method   AJAX method to call
     * @param string                 $type     Content type to output
     *
     * @return ResponseInterface
     */
    protected function callAjaxMethod(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $method,
        string $type = 'application/json'
    ): ResponseInterface {
        // Check the AJAX handler plugin manager for the method.
        if (!$this->ajaxManager) {
            throw new \Exception('AJAX Handler Plugin Manager missing.');
        }
        if ($this->ajaxManager->has($method)) {
            try {
                $handler = $this->ajaxManager->get($method);
                return $this->getAjaxResponse(
                    $response,
                    $type,
                    ...$handler->handleRequest($request, $response)
                );
            } catch (\Exception $e) {
                return $this->getExceptionResponse($response, $type, $e);
            }
        }

        // If we got this far, we can't handle the requested method:
        return $this->getAjaxResponse(
            $response,
            $type,
            $this->translate('Invalid Method'),
            AjaxHandlerInterface::STATUS_HTTP_BAD_REQUEST
        );
    }
}
