<?php

/**
 * Url view helper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Router\RouteMatchInterface;
use RuntimeException;
use VuFind\Application;
use VuFind\Http\RouteHelper;
use VuFind\ServiceManager\Factory\Autowire;

use function func_num_args;
use function is_bool;

/**
 * Url view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Url
{
    /**
     * Constructor.
     *
     * @param Application $application Application
     * @param RouteHelper $routeHelper Route helper
     */
    #[Autowire]
    public function __construct(
        protected Application $application,
        protected RouteHelper $routeHelper,
    ) {
    }

    /**
     * Generates a url given the name of a route.
     *
     * @param string $name               Name of the route
     * @param array  $params             Parameters for the link
     * @param array  $options            Options for the route (only 'query' => [...] is supported)
     * @param bool   $reuseMatchedParams Whether to reuse matched parameters
     *
     * @see \Laminas\Router\RouteInterface::assemble()
     *
     * @throws RuntimeException If no RouteMatch was provided, or if RouteMatch is missing the route name
     *
     * @return static|string This if no params were passed, or URL for the link href attribute
     */
    public function __invoke(
        ?string $name = null,
        array $params = [],
        array $options = [],
        bool $reuseMatchedParams = false
    ): static|string {
        // If argument list is empty, return object for method access:
        if (func_num_args() === 0) {
            return $this;
        }

        if (3 === func_num_args() && is_bool($options)) {
            $reuseMatchedParams = $options;
            $options            = [];
        }

        if ($name === null) {
            if (null === ($routeMatch = $this->getRouteMatch())) {
                throw new RuntimeException('No route-match in request');
            }

            $name = $routeMatch->getMatchedRouteName();

            if ($name === '') {
                throw new RuntimeException('RouteMatch does not contain a matched route name');
            }
        }

        if ($reuseMatchedParams && null !== ($routeMatch = $this->getRouteMatch())) {
            $routeMatchParams = $routeMatch->getParams();
            $params = array_merge($routeMatchParams, $params);
        }

        return $this->routeHelper->getUrlFromRoute($name, $params, $options['query'] ?? []);
    }

    /**
     * Get URL with current GET parameters and add one.
     *
     * @param array $params             Key-paired parameters
     * @param bool  $reuseMatchedParams Whether to reuse matched parameters
     *
     * @return string
     */
    public function addQueryParameters(array $params, bool $reuseMatchedParams = true): string
    {
        $requestQuery = $this->application->getRequest()?->getQueryParams() ?? [];
        $options = [
            'query' => array_merge($requestQuery, $params),
        ];
        // If we don't have a route match, direct any url's to default route:
        $routeName = $this->getRouteMatch() ? null : 'default';
        return ($this)($routeName, [], $options, $reuseMatchedParams);
    }

    /**
     * Get route match from application, if available.
     *
     * @return ?RouteMatchInterface
     */
    protected function getRouteMatch(): ?RouteMatchInterface
    {
        return $this->application->getRequest()?->getAttribute('route-match');
    }
}
