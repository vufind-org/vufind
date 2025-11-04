<?php

/**
 * Helper class for restricted Solr R2 search.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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

namespace Finna\View\Helper\Root;

/**
 * Helper class for restricted Solr R2 search.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @deprecated This only exists for backward compatibility with existing templates.
 */
class R2 extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Check if R2 is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return false;
    }

    /**
     * Check if user is authenticated to use R2.
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return false;
    }

    /**
     * Check if user is registered to REMS during this session.
     *
     * @param bool $checkEntitlements Also check entitlements?
     *
     * @return bool
     */
    public function isRegistered($checkEntitlements = false)
    {
        return false;
    }

    /**
     * Check if user is has access to R2
     *
     * @param bool $ignoreCache Ignore cache?
     *
     * @return bool
     */
    public function hasUserAccess($ignoreCache = true)
    {
        return false;
    }

    /**
     * Render R2 registration info. Returns HTML.
     *
     * @param AbstractBase $driver Record driver
     * @param array        $params Parameters
     *
     * @return null|string
     */
    public function registeredInfo($driver, $params = null)
    {
        return null;
    }

    /**
     * Render R2 registration prompt. Returns HTML.
     *
     * @param AbstractBase $driver Record driver
     * @param array        $params Parameters
     *
     * @return string|null
     */
    public function register($driver, $params = null)
    {
        return null;
    }
}
