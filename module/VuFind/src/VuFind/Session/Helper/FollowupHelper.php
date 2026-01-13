<?php

/**
 * VuFind Session Helper - Followup
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2025.
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
 * @package  Session_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Session\Helper;

use Laminas\Session\Container;
use Laminas\Uri\Http;
use VuFind\Http\ServerUrlHelper;

/**
 * Session helper to deal with login followup; responsible for remembering URLs
 * before login and then redirecting the user to the appropriate place afterwards.
 *
 * @category VuFind
 * @package  Session_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FollowupHelper
{
    /**
     * Constructor
     *
     * @param Container       $session         Session container
     * @param ServerUrlHelper $serverUrlHelper Server URL helper
     */
    public function __construct(protected Container $session, protected ServerUrlHelper $serverUrlHelper)
    {
    }

    /**
     * Clear an element of the stored followup information.
     *
     * @param string $key Element to clear.
     *
     * @return bool       True if cleared, false if never set.
     */
    public function clear(string $key)
    {
        if (isset($this->session->$key)) {
            unset($this->session->$key);
            return true;
        }
        return false;
    }

    /**
     * Retrieve the stored followup information.
     *
     * @param string $key     Element to retrieve and clear (null for entire
     * \Laminas\Session\Container object)
     * @param mixed  $default Default value to return if no stored value found
     * (ignored when $key is null)
     *
     * @return mixed
     */
    public function retrieve(?string $key = null, $default = null)
    {
        if (null === $key) {
            return $this->session;
        }
        return $this->session->$key ?? $default;
    }

    /**
     * Retrieve and then clear a particular followup element.
     *
     * @param string $key     Element to retrieve and clear.
     * @param mixed  $default Default value to return if no stored value found
     *
     * @return mixed
     */
    public function retrieveAndClear(string $key, $default = null)
    {
        $value = $this->retrieve($key, $default);
        $this->clear($key);
        return $value;
    }

    /**
     * Store the current URL (and optional additional information) in the session
     * for use following a successful login.
     *
     * @param array  $extras      Associative array of extra fields to store.
     * @param string $overrideUrl URL to store in place of current server URL (null
     * for no override)
     *
     * @return void
     */
    public function store(array $extras = [], ?string $overrideUrl = null)
    {
        // Store the current URL:
        $url = new Http(
            !empty($overrideUrl)
            ? $overrideUrl : $this->serverUrlHelper->getCurrentUrl()
        );
        $query = $url->getQueryAsArray();
        unset($query['lightboxParent']);
        $url->setQuery($query);
        $this->session->url = $url->toString();

        // Store the extra parameters:
        foreach ($extras as $key => $value) {
            $this->session->$key = $value;
        }
    }
}
