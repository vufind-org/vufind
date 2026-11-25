<?php

/**
 * Server URL helper.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Http\ServerUrlHelper;

/**
 * Server URL helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ServerUrl
{
    /**
     * Constructor.
     *
     * @param ServerUrlHelper $serverUrlHelper Server URL helper
     */
    public function __construct(
        protected ServerUrlHelper $serverUrlHelper,
    ) {
    }

    /**
     * Return a server URL.
     *
     * @param string|true|null $requestUri If true, the request URI found in $_SERVER will be appended as a path. If a
     * string is given, it will be appended as a path. If null, no path is appended.
     *
     * @return string
     */
    public function __invoke(string|true|null $requestUri = null): mixed
    {
        return match ($requestUri) {
            true => $this->serverUrlHelper->getCurrentUrl(),
            null => $this->serverUrlHelper->getBaseUrl(),
            default => $this->serverUrlHelper->getUrlForPath($requestUri)
        };
    }
}
