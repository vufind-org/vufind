<?php

/**
 * Event.
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
 * @package  EventManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\EventManager;

use Laminas\Router\RouteMatchInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Event.
 *
 * @category VuFind
 * @package  EventManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Event implements EventInterface
{
    /**
     * Bootstrap event.
     *
     * @var string
     */
    public const EVENT_BOOTSTRAP = 'bootstrap';

    /**
     * Route event.
     *
     * @var string
     */
    public const EVENT_ROUTE = 'route';

    /**
     * Dispatch event.
     *
     * @var string
     */
    public const EVENT_DISPATCH = 'dispatch';

    /**
     * Dispatch error event.
     *
     * @var string
     */
    public const EVENT_DISPATCH_ERROR = 'dispatch.error';

    /**
     * Finish event.
     *
     * @var string
     */
    public const EVENT_FINISH = 'finish';

    /**
     * Event name.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Propagation stopped flag.
     *
     * @var bool
     */
    protected bool $propagationStopped = false;

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
     * Route match.
     *
     * @var ?RouteMatchInterface
     */
    protected ?RouteMatchInterface $routeMatch = null;

    /**
     * Parameters.
     *
     * @var array
     */
    protected array $params = [];

    /**
     * Event target.
     *
     * @var object|string|null
     */
    protected object|string|null $target = null;

    /**
     * Get event name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set event name.
     *
     * @param string $name Name
     *
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get propagation stopped flag.
     *
     * @return bool
     */
    public function propagationIsStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Set propagation stopped flag.
     *
     * @param bool $flag New value
     *
     * @return static
     */
    public function stopPropagation($flag = true)
    {
        $this->propagationStopped = $flag;
        return $this;
    }

    /**
     * Get request.
     *
     * @return ?ServerRequestInterface
     */
    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Set request.
     *
     * @param ?ServerRequestInterface $request Request
     *
     * @return static
     */
    public function setRequest(?ServerRequestInterface $request): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get response.
     *
     * @return ?ResponseInterface
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Set response.
     *
     * @param ?ResponseInterface $response Response
     *
     * @return static
     */
    public function setResponse(?ResponseInterface $response): static
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get route match.
     *
     * @return ?RouteMatchInterface
     */
    public function getRouteMatch(): ?RouteMatchInterface
    {
        return $this->routeMatch;
    }

    /**
     * Set route match.
     *
     * @param ?RouteMatchInterface $routeMatch Route match
     *
     * @return static
     */
    public function setRouteMatch(?RouteMatchInterface $routeMatch): static
    {
        $this->routeMatch = $routeMatch;
        return $this;
    }

    /**
     * Get parameters.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Set parameters.
     *
     * @param array $params New parameters
     *
     * @return static
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Get a single parameter by name.
     *
     * @param string|int $name    Parameter name
     * @param mixed      $default Default value to return if parameter does not exist
     *
     * @return mixed
     */
    public function getParam($name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Set a single parameter by key.
     *
     * @param string|int $name  Parameter name
     * @param mixed      $value Parameter value
     *
     * @return void
     */
    public function setParam($name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * Get target/context from which event was triggered.
     *
     * @return object|string|null
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Set the event target/context.
     *
     * @param object|string|null $target Event targer
     *
     * @return void
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }
}
