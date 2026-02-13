<?php

/**
 * Abstract base class for actions.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Helper\AbstractHelper;
use VuFind\Action\Helper\PluginManager as HelperPluginManager;
use VuFind\Action\Helper\RedirectHelper;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractAction implements ActionInterface
{
    /**
     * Current request
     *
     * @var ?ServerRequestInterface $request
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * Current response
     *
     * @var ?ResponseInterface $response
     */
    protected ?ResponseInterface $response = null;

    /**
     * Constructor.
     *
     * @param HelperPluginManager $helperPluginManager Helper plugin manager
     */
    #[Autowire()]
    public function __construct(
        protected HelperPluginManager $helperPluginManager,
    ) {
    }

    /**
     * Invoke the action.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->request = $request;
        $this->response = $response;
        return $this->action($request, $response);
    }

    /**
     * Perform the action.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    abstract protected function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface;

    /**
     * Get a parameter from POST fields or query string.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getPostOrQueryParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->getPostParam($param)
            ?? $this->getQueryParam($param)
            ?? $default;
    }

    /**
     * Get a parameter from POST fields.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getPostParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->request->getParsedBody()[$param] ?? $default;
    }

    /**
     * Get a parameter from query string.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getQueryParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->request->getQueryParams()[$param] ?? $default;
    }

    /**
     * Get a parameter from route match.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getRouteParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->request->getAttribute('route-match')?->getParam($param) ?? $default;
    }

    /**
     * Get a helper plugin.
     *
     * @param class-string<T> $name Name of plugin
     *
     * @template T
     *
     * @return T
     */
    protected function getHelper(string $name): AbstractHelper
    {
        return $this->helperPluginManager->get($name);
    }

    /**
     * Get a 302 redirect response.
     *
     * @param ResponseInterface $response Response
     * @param string            $url      Target URL
     *
     * @return ResponseInterface
     */
    protected function getRedirectResponse(ResponseInterface $response, string $url): ResponseInterface
    {
        return $this->getHelper(RedirectHelper::class)->redirectToUrl($url, $response);
    }

    /**
     * Generate a URL given the name of a route.
     *
     * @param string $name        Name of the route
     * @param array  $routeParams Path parameters
     * @param array  $queryParams Query parameters
     *
     * @see \Laminas\Router\RouteInterface::assemble()
     *
     * @throws \Laminas\View\Exception\RuntimeException If no RouteStackInterface was provided
     * @throws \Laminas\View\Exception\RuntimeException If no RouteMatch was provided
     * @throws \Laminas\View\Exception\RuntimeException If RouteMatch didn't contain a matched route name
     * @throws \Laminas\View\Exception\InvalidArgumentException If the params object was not an array or Traversable
     * object.
     *
     * @return string Url For the link href attribute
     */
    public function getUrlFromRoute(
        string $name,
        array $routeParams = [],
        array $queryParams = []
    ): string {
        $routeHelper = $this->request->getAttribute('route-helper');
        return $routeHelper->getUrlFromRoute($name, $routeParams, $queryParams);
    }
}
