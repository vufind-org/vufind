<?php

/**
 * Server URL Helper class.
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
use Laminas\View\Helper\ServerUrl;

/**
 * Server URL Helper class.  Wrapper around Laminas ServerUrlHelper.
 *
 * @category VuFind
 * @package  Http
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ServerUrlHelper
{
    /**
     * Server URL helper.
     *
     * @var ?ServerUrl
     */
    protected ?ServerUrl $serverUrlHelper = null;

    /**
     * Constructor.
     *
     * @param Closure $serverUrlHelperFactory Server URL helper factory callback
     *
     * @return void
     */
    public function __construct(protected Closure $serverUrlHelperFactory)
    {
    }

    /**
     * Return the base URL of the current host.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return ($this->getServerUrlHelper())(null);
    }

    /**
     * Return the fully qualified request URL.
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        return ($this->getServerUrlHelper())(true);
    }

    /**
     * Return the fully qualified URL for the given path on the current host.
     *
     * @param string $path A path beginning at the root, with starting slash.
     *
     * @return string
     */
    public function getUrlForPath(string $path): string
    {
        return ($this->getServerUrlHelper())($path);
    }

    /**
     * Get ServerUrl helper.
     *
     * @return ServerUrl
     */
    protected function getServerUrlHelper(): ServerUrl
    {
        if (null === $this->serverUrlHelper) {
            $this->serverUrlHelper = ($this->serverUrlHelperFactory)();
        }
        return $this->serverUrlHelper;
    }
}
