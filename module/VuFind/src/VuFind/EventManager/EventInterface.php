<?php

/**
 * Event interface.
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
 * Event interface.
 *
 * @category VuFind
 * @package  EventManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface EventInterface extends \Laminas\EventManager\EventInterface
{
    /**
     * Get event name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set event name.
     *
     * @param string $name Name
     *
     * @return static
     */
    public function setName($name);

    /**
     * Get request.
     *
     * @return ?ServerRequestInterface
     */
    public function getRequest(): ?ServerRequestInterface;

    /**
     * Set request.
     *
     * @param ?ServerRequestInterface $request Request
     *
     * @return static
     */
    public function setRequest(?ServerRequestInterface $request): static;

    /**
     * Get response.
     *
     * @return ?ResponseInterface
     */
    public function getResponse(): ?ResponseInterface;

    /**
     * Set response.
     *
     * @param ?ResponseInterface $response Response
     *
     * @return static
     */
    public function setResponse(?ResponseInterface $response): static;

    /**
     * Get route match.
     *
     * @return ?RouteMatchInterface
     */
    public function getRouteMatch(): ?RouteMatchInterface;

    /**
     * Set route match.
     *
     * @param ?RouteMatchInterface $routeMatch Route match
     *
     * @return static
     */
    public function setRouteMatch(?RouteMatchInterface $routeMatch): static;

    /**
     * Get parameters.
     *
     * @return array
     */
    public function getParams(): array;

    /**
     * Set parameters.
     *
     * @param array $params New parameters
     *
     * @return static
     */
    public function setParams($params);
}
