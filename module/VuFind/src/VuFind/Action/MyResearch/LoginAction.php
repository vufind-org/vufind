<?php

/**
 * Login action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\MyResearch;

use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Action\Helper\ForwardHelper;
use VuFind\Action\Helper\PluginManager as HelperPluginManager;
use VuFind\Auth\Manager as AuthManager;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Login action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LoginAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor
     *
     * @param AuthManager $authManager Authentication manager
     */
    public function __construct(
        protected AuthManager $authManager,
    ) {
        parent::__construct();
    }

    /**
     * Display login.
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
        $postParams = $request->getParsedBody();
        // If this authentication method doesn't use a VuFind-generated login
        // form, force it through:
        if ($this->authManager->hasSessionInitiator()) {
            // Don't get stuck in an infinite loop -- if processLogin is already
            // set, it probably means Home action is forwarding back here to
            // report an error!
            //
            // Also don't attempt to process a login that hasn't happened yet;
            // if we've just been forced here from another page, we need the user
            // to click the session initiator link before anything can happen.
            if (
                !($postParams['processLogin'] ?? false)
                && !($postParams['forcingLogin'] ?? false)
            ) {
                $postParams['processLogin'] = true;
                return $this->getHelper(ForwardHelper::class)->forwardTo(
                    $request->withParsedBody($postParams),
                    $response,
                    'MyResearch/Home'
                );
            }
        }

        return $this->renderTemplate($request, $response, ['request' => new Parameters($postParams)]);
    }
}
