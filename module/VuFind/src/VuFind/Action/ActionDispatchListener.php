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

use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Response as LaminasResponse;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\Uri\Http;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Copyright (C) The National Library of Finland 2026.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ActionDispatchListener
{
    /**
     * Constructor.
     *
     * @param PluginManager $actionPluginManager Action plugin manager
     */
    public function __construct(
        protected PluginManager $actionPluginManager,
    ) {
    }

    /**
     * Attach listeners to an event manager
     *
     * @param EventManagerInterface $eventManager Event manager
     * @param int                   $priority     Event priority
     *
     * @return void
     */
    public function attach(EventManagerInterface $eventManager, int $priority = 1): void
    {
        // Attach with higher priority than default to override Laminas' default dispatch handler:
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 2);
    }

    /**
     * Listener for the dispatch event.
     *
     * @param MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        if (null !== $e->getResult()) {
            return;
        }

        $route = $e->getRouteMatch();
        $idParts = [];
        if ($controllerName = $route->getParam('controller')) {
            $idParts[] = $controllerName;
        }
        if ($actionName = $route->getParam('action')) {
            $idParts[] = $actionName;
        }
        $id = strtolower(implode('/', $idParts));
        if (!$this->actionPluginManager->has($id)) {
            return;
        }
        $action = $this->actionPluginManager->get($id);

        $request = Psr7ServerRequest::fromLaminas($e->getRequest())
            ->withAttribute('action-id', $id)
            ->withAttribute('route-match', $e->getRouteMatch())
            ->withAttribute('view-model', $e->getViewModel());
        foreach ($route->getParams() as $routeParam => $value) {
            $request = $request->withAttribute($routeParam, $value);
        }
        $laminasResponse = $e->getApplication()->getResponse();
        $response = Psr7Response::fromLaminas($laminasResponse);
        try {
            $result = $action($request, $response);
            $this->updateLaminasResponse($laminasResponse, $result);
            $e->setResult($laminasResponse);
        } catch (Throwable $ex) {
            $e->setName(MvcEvent::EVENT_DISPATCH_ERROR);
            $e->setError(Application::ERROR_EXCEPTION);
            $e->setController($id);
            $e->setControllerClass($action::class);
            $e->setParam('exception', $ex);

            if (!$return = $e->getApplication()->getEventManager()->triggerEvent($e)->last()) {
                $return = $e->getResult();
            }
            $e->setResult($return);
        }
        return $e->getResult();
    }

    /**
     * Update Laminas response from PSR-7 response.
     *
     * @param LaminasResponse   $laminasResponse Laminas response
     * @param ResponseInterface $psr7Response    PSR-7 response
     *
     * @return void
     */
    protected function updateLaminasResponse(LaminasResponse $laminasResponse, ResponseInterface $psr7Response): void
    {
        $laminasResponse->setVersion($psr7Response->getProtocolVersion());
        $laminasResponse->setStatusCode($psr7Response->getStatusCode());
        $laminasResponse->setReasonPhrase($psr7Response->getReasonPhrase());
        $laminasHeaders = $laminasResponse->getHeaders();
        $laminasHeaders->clearHeaders();
        foreach ($psr7Response->getHeaders() as $name => $value) {
            $laminasHeaders->addHeaderLine($name, $value);
        }

        $uri = $psr7Response->getBody()->getMetadata('uri');
        if ($uri === Psr7Response::URI_TEMP || $uri === Psr7Response::URI_MEMORY) {
            $laminasResponse->setContent((string)$psr7Response->getBody());
        } else {
            $laminasResponse->setContent(file_get_contents($uri));
        }
    }
}
