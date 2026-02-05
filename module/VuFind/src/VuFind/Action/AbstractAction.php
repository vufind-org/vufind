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
        return $this->request->getAttribute('route-match')->getParam($param) ?? $default;
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
        return $response->withStatus(302)
            ->withHeader('Location', $url);
    }

    /**
     * Generate a URL based on a route.
     *
     * @param string $route   Route name
     * @param array  $params  Parameters to use in url generation, if any
     * @param array  $options Route-specific options to use in url generation, if any.
     *
     * @return string
     *
     * @todo Maybe use RouteHelper from https://github.com/vufind-org/vufind/pull/5049
     */
    public function getRouteUrl(string $route, array $params = [], array $options = []): string
    {
        $router = $this->request->getAttribute('router');
        $options['name'] = $route;
        return $router->assemble($params, $options);
    }
}
