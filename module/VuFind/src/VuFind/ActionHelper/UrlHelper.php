<?php

/**
 * Action helper for URL-related functionality.
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

use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Action helper for URL-related functionality.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class UrlHelper implements HelperInterface
{
    /**
     * Constructor.
     *
     * @param RouteHelper     $routeHelper     Route helper
     * @param ServerUrlHelper $serverUrlHelper Server URL helper
     */
    #[Autowire()]
    public function __construct(
        protected RouteHelper $routeHelper,
        protected ServerUrlHelper $serverUrlHelper,
    ) {
    }

    /**
     * Is the provided URL local to this instance?
     *
     * @param string $url URL to check
     *
     * @return bool
     */
    public function isLocalUrl(string $url): bool
    {
        $baseUrlNorm = $this->normalizeUrlForComparison(
            $this->serverUrlHelper->getUrlForPath(
                $this->routeHelper->getUrlFromRoute('home')
            )
        );
        return str_starts_with($this->normalizeUrlForComparison($url), $baseUrlNorm);
    }

    /**
     * Normalize a URL so that inconsistencies in protocol and trailing slashes do not break comparisons.
     *
     * @param string $url URL to normalize
     *
     * @return string
     */
    public function normalizeUrlForComparison(string $url): string
    {
        $parts = explode('://', $url, 2);
        return trim(end($parts), '/');
    }
}
