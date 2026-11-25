<?php

/**
 * Action dispatch listener.
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
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action;

use GuzzleHttp\Psr7\Response;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use VuFind\Action\Error\ExceptionAction;
use VuFind\Action\Error\NotFoundAction;
use VuFind\Action\Error\PermissionDeniedAction;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Exception\Forbidden;
use VuFind\Http\RouteHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

/**
 * Action dispatcher.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ActionDispatcher
{
    /**
     * Constructor.
     *
     * @param PluginManager         $actionPluginManager       Action plugin manager
     * @param HelperPluginManager   $actionHelperPluginManager Action helper plugin manager
     * @param RouteHelper           $routeHelper               Route helper
     * @param GlobalsContainer      $globalsContainer          Global data container
     * @param ActionConfigManager   $actionConfigManager       Action configuration manager
     * @param EventManagerInterface $eventManager              Event manager
     * @param array                 $config                    VuFind configuration
     * @param array                 $redirectConfig            Redirects from legacy actions to current ones
     */
    public function __construct(
        protected PluginManager $actionPluginManager,
        protected HelperPluginManager $actionHelperPluginManager,
        protected RouteHelper $routeHelper,
        protected GlobalsContainer $globalsContainer,
        protected ActionConfigManager $actionConfigManager,
        protected EventManagerInterface $eventManager,
        #[Autowire(config: 'config')]
        protected array $config,
        #[Autowire(service: 'Config', path: 'vufind/action_redirects')]
        protected array $redirectConfig,
    ) {
    }

    /**
     * Dispatch a request.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function dispatch(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($redirectResponse = $this->getRedirectResponse($request, $response)) {
            return $redirectResponse;
        }

        $id = null;
        if ($routeMatch = $request->getAttribute('route-match')) {
            $id = $this->actionPluginManager
                ->getActionHandlerName($routeMatch->getParam('controller'), $routeMatch->getParam('action'));
        }

        $action = $id
            ? $this->actionPluginManager->get($id)
            : $this->actionPluginManager->get(NotFoundAction::class);
        $this->actionConfigManager->applyActionConfig($action, $routeMatch);

        $request = $request
            ->withAttribute('action-id', $id)
            ->withAttribute('route-helper', $this->routeHelper);
        try {
            return $action($request, $response);
        } catch (Throwable $ex) {
            return $this->getExceptionResponse($request, $response, $ex);
        }
    }

    /**
     * Return an exception response.
     *
     * @param ServerRequestInterface $request   Request
     * @param ResponseInterface      $response  Response
     * @param Throwable              $exception Exeption
     *
     * @return ResponseInterface
     */
    protected function getExceptionResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Throwable $exception
    ): ResponseInterface {
        if ($exception instanceof Forbidden) {
            $actionId = 'error/permissiondenied';
            $actionClass = PermissionDeniedAction::class;
        } else {
            $actionId = 'error/exception';
            $actionClass = ExceptionAction::class;
        }
        $action = $this->actionPluginManager->get($actionClass);
        $this->actionConfigManager->applyActionConfig($action, $request->getAttribute('route-match'));
        $request = $request
            ->withAttribute('exception', $exception)
            ->withAttribute('action-id', $actionId);
        return $action($request, $response);
    }

    /**
     * Return a redirect for actions that should be redirected.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function getRedirectResponse(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        // Only support GET requests:
        if ($request->getMethod() !== 'GET') {
            return null;
        }

        $route = $request->getAttribute('route-match');

        $redirectParts = [];
        if ($controller = $route?->getParam('controller')) {
            $redirectParts[] = $controller;
        }
        if ($action = $route?->getParam('action')) {
            $redirectParts[] = $action;
        }
        $actionId = strtolower(implode('/', $redirectParts));
        if ($redirectRoute = $this->redirectConfig[$actionId] ?? null) {
            $response = $this->actionHelperPluginManager->get(RedirectHelper::class)->redirectToRoute(
                $response,
                $redirectRoute,
                $route->getParams(),
                $request->getQueryParams()
            );
            return $response;
        }
        return null;
    }
}
