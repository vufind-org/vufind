<?php

/**
 * VuFind main application.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind;

use Laminas\EventManager\EventManagerInterface;
use Laminas\Router\Http\HttpRouteMatch;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\ActionDispatcher;
use VuFind\EventManager\Event;
use VuFind\EventManager\EventInterface;
use VuFind\Http\RequestHelper;
use VuFind\Http\ResponseHelper;
use VuFind\ServiceManager\Factory\Autowire;

use function strlen;

/**
 * VuFind main application.
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Application
{
    /**
     * Request.
     *
     * @var ?ServerRequestInterface
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * Response.
     *
     * @var ?ResponseInterface
     */
    protected ?ResponseInterface $response = null;

    /**
     * Constructor.
     *
     * @param ContainerInterface    $container        Service container
     * @param EventManagerInterface $eventManager     Event manager
     * @param ActionDispatcher      $actionDispatcher Action dispatcher
     * @param RequestHelper         $requestHelper    Request helper
     * @param ResponseHelper        $responseHelper   Response helper
     */
    #[Autowire]
    public function __construct(
        protected ContainerInterface $container,
        protected EventManagerInterface $eventManager,
        protected ActionDispatcher $actionDispatcher,
        protected RequestHelper $requestHelper,
        protected ResponseHelper $responseHelper,
    ) {
    }

    /**
     * Run the application.
     *
     * @return void
     */
    public function run(): void
    {
        $this->request = $this->requestHelper->createServerRequest();
        $this->response = $this->responseHelper->createResponse();

        // Trigger bootstrap event:
        $event = (new Event())
            ->setRequest($this->request)
            ->setResponse($this->response);
        $result = $this->triggerEvent($event, Event::EVENT_BOOTSTRAP)->last();
        // Update with any changes from event listeners:
        if ($result instanceof ResponseInterface) {
            $this->response = $result;
        }

        // Try to find a route match for the request:
        $basePath = $this->requestHelper->getBasePath();
        $routeMatch = $this->container->get('HttpRouter')->match($this->request, strlen($basePath));

        // Trigger route event:
        $event->setRouteMatch($routeMatch);
        $result = $this->triggerEvent($event, Event::EVENT_ROUTE)->last();
        if ($result instanceof HttpRouteMatch) {
            $routeMatch = $result;
        }

        $this->request = $this->request
            ->withAttribute('route-match', $routeMatch)
            ->withAttribute('view-model', $this->createViewModel());

        // Trigger dispatch or dispatch error event:
        $event->setRequest($this->request);
        $result = $this->triggerEvent($event, $routeMatch ? Event::EVENT_DISPATCH : Event::EVENT_DISPATCH_ERROR)
            ->last();
        // Update with any changes from event listeners (if the result is a response, the actual action won't be
        // dispatched):
        if ($result instanceof ResponseInterface) {
            $this->response = $result;
        } else {
            $this->response = $event->getResponse();
            $this->response = $this->actionDispatcher->dispatch($this->request, $this->response);
        }

        // Trigger finish event:
        $event->setResponse($this->response);
        $result = $this->eventManager->trigger(Event::EVENT_FINISH)->last();
        // Update with any changes from event listeners:
        if ($result instanceof ResponseInterface) {
            $this->response = $result;
        }

        $this->responseHelper->outputResponse($this->response);
    }

    /**
     * Get the request.
     *
     * @return ?ServerRequestInterface
     */
    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Get the response.
     *
     * @return ?ResponseInterface
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Create a view model.
     *
     * @return ModelInterface
     */
    protected function createViewModel(): ModelInterface
    {
        return new ViewModel();
    }

    /**
     * Trigger an event.
     *
     * @param EventInterface $event     Event prototype
     * @param string         $eventName Event name
     *
     * @return mixed
     */
    protected function triggerEvent(EventInterface $event, string $eventName): mixed
    {
        // Callback that stops event propagation on results:
        $callback = static function ($result): bool {
            return $result instanceof ResponseInterface;
        };

        $event->setName($eventName);
        $event->stopPropagation(false);
        $this->eventManager->setEventPrototype($event);
        return $this->eventManager->triggerEventUntil($callback, $event);
    }
}
