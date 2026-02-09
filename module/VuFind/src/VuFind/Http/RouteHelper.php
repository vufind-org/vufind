<?php

/**
 * Route Helper class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Http
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind\Http;

use Closure;
use Laminas\View\Helper\Url;

/**
 * Route Helper class.  Wrapper around Laminas UrlHelper.
 *
 * @category VuFind
 * @package  Http
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RouteHelper
{
    /**
     * URL helper
     *
     * @var ?Url
     */
    protected ?Url $urlHelper = null;

    /**
     * Constructor.
     *
     * @param Closure $urlHelperFactory URL helper factory callback
     *
     * @return void
     */
    public function __construct(protected Closure $urlHelperFactory)
    {
    }

    /**
     * Generates a url given the name of a route.
     *
     * @param string $name        Name of the route
     * @param array  $routeParams Path parameters
     * @param array  $queryParams Query parameters
     *
     * @see \Laminas\Router\RouteInterface::assemble()
     *
     * @throws \Laminas\View\Exception\RuntimeException If no RouteStackInterface was provided
     * @throws \Laminas\View\Exception\RuntimeException If no RouteMatch was provided
     * @throws \Laminas\View\Exception\RuntimeException If RouteMatch didn't contain a matched
     * route name
     * @throws \Laminas\View\Exception\InvalidArgumentException If the params object was not an
     * array or Traversable object.
     *
     * @return string Url For the link href attribute
     */
    public function getUrlFromRoute(
        string $name,
        array $routeParams = [],
        array $queryParams = []
    ): string {
        // Path normalization can cause problems with IDs containing escaped slashes, so let's always disable it:
        $routeOptions = ['normalize_path' => false] + ($queryParams ? ['query' => $queryParams] : []);
        return ($this->getUrlHelper())($name, $routeParams, $routeOptions);
    }

    /**
     * Get URL helper.
     *
     * @return Url
     */
    protected function getUrlHelper(): Url
    {
        if (null === $this->urlHelper) {
            $this->urlHelper = ($this->urlHelperFactory)();
        }
        return $this->urlHelper;
    }
}
