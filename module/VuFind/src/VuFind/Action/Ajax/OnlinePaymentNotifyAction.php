<?php

/**
 * Handle online payment notification callback.
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
use VuFind\Action\AjaxResponseTrait;
use VuFind\AjaxHandler\PluginManager as AjaxPluginManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Handle online payment notification callback.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OnlinePaymentNotifyAction extends AbstractAction implements TranslatorAwareInterface
{
    use AjaxResponseTrait;
    // For AjaxResponseTrait:
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param AjaxPluginManager $ajaxManager AJAX Handler Plugin Manager
     */
    #[Autowire()]
    public function __construct(
        AjaxPluginManager $ajaxManager
    ) {
        parent::__construct();
        $this->ajaxManager = $ajaxManager;
    }

    /**
     * Make an AJAX call with a JSON-formatted response.
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
        return $this->callAjaxMethod($request, $response, 'onlinePaymentNotify', 'text/html');
    }
}
