<?php

/**
 * Action helper for forwarding requests.
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

use Laminas\Router\RouteMatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\PluginManager as ActionPluginManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Action helper for forwarding requests.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ForwardHelper implements HelperInterface
{
    /**
     * Constructor.
     *
     * @param ActionPluginManager $actionPluginManager Action plugin manager
     */
    #[Autowire]
    public function __construct(
        protected ActionPluginManager $actionPluginManager,
    ) {
    }

    /**
     * Forward the request to another action.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string                 $actionId Action ('category/action')
     *
     * @return ResponseInterface
     */
    public function forwardTo(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $actionId
    ): ResponseInterface {
        if (!$this->actionPluginManager->has($actionId)) {
            throw new \InvalidArgumentException("Unknown action '$actionId'");
        }
        $action = $this->actionPluginManager->get($actionId);
        return $action(
            $request->withAttribute('action-id', $actionId),
            $response
        );
    }

    /**
     * Forward the request to the confirmation action.
     *
     * @param ServerRequestInterface $request   Request
     * @param ResponseInterface      $response  Response
     * @param string                 $title     Title of confirm dialog
     * @param string                 $yesTarget Form target for "confirm" action
     * @param string                 $noTarget  Form target for "cancel" action
     * @param string|array           $messages  Info messages for confirm dialog
     * @param array                  $extras    Extra details to include in form
     *
     * @return ResponseInterface
     */
    public function forwardToConfirm(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $title,
        string $yesTarget,
        string $noTarget,
        string|array $messages = [],
        array $extras = []
    ): ResponseInterface {
        $action = $this->actionPluginManager->get('confirm/confirm');
        $routeMatch = new RouteMatch(
            [
                'data' => [
                    'title' => $title,
                    'confirm' => $yesTarget,
                    'cancel' => $noTarget,
                    'messages' => (array)$messages,
                    'extras' => $extras,
                ],
            ]
        );
        $routeMatch->setMatchedRouteName('Confirm/Confirm');
        return $action(
            $request->withAttribute('action-id', 'confirm/confirm')
                ->withAttribute('route-match', $routeMatch),
            $response
        );
    }
}
