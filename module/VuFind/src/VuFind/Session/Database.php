<?php

/**
 * Database session handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\Session;

use VuFind\Db\Service\SessionServiceInterface;
use VuFind\Exception\SessionExpired as SessionExpiredException;

/**
 * Database session handler
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class Database extends AbstractBase
{
    /**
     * Read function must return string value always to make save handler work as
     * expected. Return empty string if there is no data to read.
     *
     * @param string $sessId The session ID to read
     *
     * @return string
     */
    public function read($sessId): string
    {
        // Try to read the session, but destroy it if it has expired:
        try {
            return $this->getSessionService()->readSession($sessId, $this->lifetime);
        } catch (SessionExpiredException $e) {
            $this->destroy($sessId);
            return '';
        }
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * @param string $sessId The session ID to destroy
     *
     * @return bool
     */
    public function destroy($sessId): bool
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sessId);

        // Now do database-specific destruction:
        $this->getSessionService()->destroySession($sessId);

        return true;
    }

    /**
     * The garbage collector, this is executed when the session garbage collector
     * is executed and takes the max session lifetime as its only parameter.
     *
     * @param int $sessMaxLifetime Maximum session lifetime.
     *
     * @return int|false
     */
    public function gc($sessMaxLifetime): int|false
    {
        return $this->getSessionService()->garbageCollect($sessMaxLifetime);
    }

    /**
     * A function that is called internally when session data is to be saved.
     *
     * @param string $sessId The current session ID
     * @param string $data   The session data to write
     *
     * @return bool
     */
    protected function saveSession($sessId, $data): bool
    {
        return $this->getSessionService()->writeSession($sessId, $data);
    }

    /**
     * Get a session service object
     *
     * @return SessionServiceInterface
     */
    protected function getSessionService(): SessionServiceInterface
    {
        return $this->getDbService(SessionServiceInterface::class);
    }
}
