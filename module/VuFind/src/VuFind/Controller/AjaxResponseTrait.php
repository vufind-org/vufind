<?php

/**
 * Trait to allow AJAX response generation.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller;

use VuFind\AjaxHandler\AjaxHandlerInterface as Ajax;
use VuFind\AjaxHandler\PluginManager;

/**
 * Trait to allow AJAX response generation.
 *
 * Dependencies:
 * - \VuFind\I18n\Translator\TranslatorAwareTrait
 * - Injection of $this->ajaxManager (for some functionality)
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait AjaxResponseTrait
{
    /**
     * AJAX Handler plugin manager
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
     * Send output data and exit.
     *
     * @param string $type     Content type to output
     * @param mixed  $data     The response data
     * @param int    $httpCode A custom HTTP Status Code
     *
     * @return \Laminas\Http\Response
     * @throws \Exception
     */
    protected function getAjaxResponse($type, $data, $httpCode = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', $type);
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        $response->setContent($this->formatContent($type, $data, $httpCode));
        return $response;
    }

    /**
     * Turn an exception into error response.
     *
     * @param string     $type Content type to output
     * @param \Exception $e    Exception to output.
     *
     * @return \Laminas\Http\Response
     */
    protected function getExceptionResponse($type, \Exception $e)
    {
        $debugMsg = ('development' == APPLICATION_ENV)
            ? ': ' . $e->getMessage() : '';
        return $this->getAjaxResponse(
            $type,
            $this->translate('An error has occurred') . $debugMsg,
            Ajax::STATUS_HTTP_ERROR
        );
    }

    /**
     * Call an AJAX method and turn the result into a response.
     *
     * @param string $method AJAX method to call
     * @param string $type   Content type to output
     *
     * @return \Laminas\Http\Response
     */
    protected function callAjaxMethod($method, $type = 'application/json')
    {
        // Check the AJAX handler plugin manager for the method.
        if (!$this->ajaxManager) {
            throw new \Exception('AJAX Handler Plugin Manager missing.');
        }
        if ($this->ajaxManager->has($method)) {
            try {
                $handler = $this->ajaxManager->get($method);
                return $this->getAjaxResponse(
                    $type,
                    ...$handler->handleRequest($this->params())
                );
            } catch (\Exception $e) {
                return $this->getExceptionResponse($type, $e);
            }
        }

        // If we got this far, we can't handle the requested method:
        return $this->getAjaxResponse(
            $type,
            $this->translate('Invalid Method'),
            Ajax::STATUS_HTTP_BAD_REQUEST
        );
    }
}
