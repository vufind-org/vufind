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
     * Constructor.
     *
     * @param Closure $serverUrlHelper Server URL helper function
     *
     * @return void
     */
    public function __construct(protected Closure $serverUrlHelper)
    {
    }

    /**
     * Return the base URL of the current host.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return ($this->serverUrlHelper)(null);
    }

    /**
     * Return the fully qualified request URL.
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        return ($this->serverUrlHelper)(true);
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
        return ($this->serverUrlHelper)($path);
    }
}
