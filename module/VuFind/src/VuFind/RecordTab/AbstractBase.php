<?php

/**
 * Record tab abstract base class
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use LmcRbacMvc\Service\AuthorizationServiceAwareInterface;
use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;

/**
 * Record tab abstract base class
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
abstract class AbstractBase implements
    TabInterface,
    AuthorizationServiceAwareInterface
{
    use AuthorizationServiceAwareTrait;

    /**
     * Permission that must be granted to access this module (null for no
     * restriction)
     *
     * @var string
     */
    protected $accessPermission = null;

    /**
     * Record driver associated with the tab
     *
     * @var ?\VuFind\RecordDriver\AbstractBase
     */
    protected $driver = null;

    /**
     * User request associated with the tab (false for none)
     *
     * @var \Laminas\Http\Request|bool
     */
    protected $request = false;

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        // If accessPermission is set, check for authorization to enable tab
        if (!empty($this->accessPermission)) {
            $auth = $this->getAuthorizationService();
            if (!$auth) {
                throw new \Exception('Authorization service missing');
            }
            return $auth->isGranted($this->accessPermission);
        }

        return true;
    }

    /**
     * Is this tab initially visible?
     *
     * @return bool
     */
    public function isVisible()
    {
        // Assume visible by default; subclasses may add rules.
        return true;
    }

    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // Assume we can load by AJAX; subclasses may add rules.
        return true;
    }

    /**
     * Set the record driver
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return AbstractBase
     */
    public function setRecordDriver(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Get the record driver
     *
     * @return \VuFind\RecordDriver\AbstractBase
     * @throws \Exception
     */
    protected function getRecordDriver()
    {
        if (null === $this->driver) {
            throw new \Exception('Record driver not set.');
        }
        return $this->driver;
    }

    /**
     * Set the user request
     *
     * @param \Laminas\Http\Request $request Request
     *
     * @return AbstractBase
     */
    public function setRequest(\Laminas\Http\Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get the user request (or false if unavailable)
     *
     * @return \Laminas\Http\Request|bool
     */
    protected function getRequest()
    {
        return $this->request;
    }
}
