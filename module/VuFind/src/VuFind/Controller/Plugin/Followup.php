<?php

/**
 * VuFind Action Helper - Followup
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Session\Container;
use Laminas\Uri\Http;

/**
 * Action helper to deal with login followup; responsible for remembering URLs
 * before login and then redirecting the user to the appropriate place afterwards.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Followup extends AbstractPlugin
{
    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param Container $session Session container
     */
    public function __construct(Container $session)
    {
        $this->session = $session;
    }

    /**
     * Clear an element of the stored followup information.
     *
     * @param string $key Element to clear.
     *
     * @return bool       True if cleared, false if never set.
     */
    public function clear($key)
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
    public function retrieve($key = null, $default = null)
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
    public function retrieveAndClear($key, $default = null)
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
    public function store($extras = [], $overrideUrl = null)
    {
        // Store the current URL:
        $url = new Http(
            !empty($overrideUrl)
            ? $overrideUrl : $this->getController()->getServerUrl()
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
