<?php

/**
 * Action helper for redirecting requests.
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

use Psr\Http\Message\ResponseInterface;
use VuFind\Http\RouteHelper;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Action helper for redirecting requests.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class RedirectHelper implements HelperInterface
{
    /**
     * Constructor
     *
     * @param RouteHelper $routeHelper Route helper
     */
    #[Autowire()]
    public function __construct(
        protected RouteHelper $routeHelper,
    ) {
    }

    /**
     * Get a redirect response for a URL.
     *
     * @param ResponseInterface $response Response
     * @param string            $url      URL
     *
     * @return ResponseInterface
     */
    public function redirectToUrl(
        ResponseInterface $response,
        string $url
    ): ResponseInterface {
        return $response->withStatus(302)
            ->withHeader('Location', $url);
    }

    /**
     * Get a redirect response for a route.
     *
     * @param ResponseInterface $response    Response
     * @param string            $name        Name of the route
     * @param array             $routeParams Path parameters
     * @param array             $queryParams Query parameters
     *
     * @return ResponseInterface
     */
    public function redirectToRoute(
        ResponseInterface $response,
        string $name,
        array $routeParams = [],
        array $queryParams = []
    ): ResponseInterface {
        $url = $this->routeHelper->getUrlFromRoute($name, $routeParams, $queryParams);
        return $this->redirectToUrl($response, $url);
    }
}
