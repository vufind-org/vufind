<?php

/**
 * Abstract base class for Ajax actions.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Ajax;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\AjaxHandler\PluginManager as AjaxPluginManager;
use VuFind\Http\HttpStatus;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for Ajax actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractAjaxAction extends AbstractAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param AjaxPluginManager $ajaxManager AJAX Handler Plugin Manager
     */
    #[Autowire]
    public function __construct(
        protected AjaxPluginManager $ajaxManager
    ) {
        parent::__construct();
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
        $responseHelper = $this->getHelper(ResponseHelper::class);
        if ($this->ajaxManager->has($method)) {
            try {
                $handler = $this->ajaxManager->get($method);
                $result = $handler->handleRequest($request);
                $data = $result[0];
                $httpStatus = $result[1] ?? null;
                return $responseHelper->getAjaxResponse(
                    $response,
                    $type,
                    'application/json' === $type ? compact('data') : $data,
                    $httpStatus,
                );
            } catch (\Exception $e) {
                return $responseHelper->getExceptionResponse($response, $type, $e);
            }
        }

        // If we got this far, we can't handle the requested method:
        $data = $this->translate('Invalid Method');
        return $responseHelper->getAjaxResponse(
            $response,
            $type,
            'application/json' === $type ? compact('data') : $data,
            HttpStatus::BAD_REQUEST
        );
    }
}
