<?php

/**
 * Container for session settings, allowing those settings to be configured
 * "just in case" they are needed, without invoking the heavy weight of
 * instantiating the session itself. See \VuFind\Session\ManagerFactory for
 * details on the use of this object.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Session;

use Laminas\Session\SessionManager;

use function is_callable;

/**
 * Container for session settings, allowing those settings to be configured
 * "just in case" they are needed, without invoking the heavy weight of
 * instantiating the session itself. See \VuFind\Session\ManagerFactory for
 * details on the use of this object.
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Settings
{
    /**
     * Have session writes been disabled?
     *
     * @var bool
     */
    protected $disableWrite = false;

    /**
     * Session manager (if instantiated)
     *
     * @var SessionManager
     */
    protected $manager = null;

    /**
     * Disable session writes after this point in time.
     *
     * @return void
     */
    public function disableWrite()
    {
        // Set the flag
        $this->disableWrite = true;

        // If the session manager is already instantiated, close it!
        if (null !== $this->manager) {
            // Try to disable writes so that writeClose() below doesn't actually
            // write anything:
            $saveHandler = $this->manager->getSaveHandler();
            if (is_callable([$saveHandler, 'disableWrites'])) {
                $saveHandler->disableWrites();
            }
            // Close the session:
            $this->manager->writeClose();
        }
    }

    /**
     * Have session writes been disabled?
     *
     * @return bool
     */
    public function isWriteDisabled()
    {
        return $this->disableWrite;
    }

    /**
     * Set a session manager instance.
     *
     * @param SessionManager $sessionManager Session manager
     *
     * @return Settings
     */
    public function setSessionManager(SessionManager $sessionManager)
    {
        $this->manager = $sessionManager;
        return $this;
    }
}
