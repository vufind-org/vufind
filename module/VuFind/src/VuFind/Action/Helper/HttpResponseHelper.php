<?php

/**
 * HTTP response action helper
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
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action\Helper;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response action helper
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class HttpResponseHelper
{
    /**
     * Constructor
     *
     * @param ResponseFactoryInterface $factory HTTP response factory
     */
    public function __construct(protected ResponseFactoryInterface $factory)
    {
    }

    /**
     * Get a "not found" HTTP response.
     *
     * @return ResponseInterface
     */
    public function getNotFoundResponse(): ResponseInterface
    {
        return $this->factory->createResponse(404);
    }

    /**
     * Get a redirect HTTP response
     *
     * @param string $url Target URL
     *
     * @return ResponseInterface
     */
    public function redirectToUrl(string $url): ResponseInterface
    {
        return $this->factory->createResponse(302)->withHeader('Location', $url);
    }
}
