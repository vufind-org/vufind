<?php
/**
 * Database session handler
 *
 * PHP version 5
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
namespace VuFind\Session;
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
     * @param string $sess_id The session ID to read
     *
     * @return string
     */
    public function read($sess_id)
    {
        // Try to read the session, but destroy it if it has expired:
        try {
            return $this->getTable('Session')
                ->readSession($sess_id, $this->lifetime);
        } catch (SessionExpiredException $e) {
            $this->destroy($sess_id);
            return;
        }
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * @param string $sess_id The session ID to destroy
     *
     * @return bool
     */
    public function destroy($sess_id)
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sess_id);

        // Now do database-specific destruction:
        $this->getTable('Session')->destroySession($sess_id);

        return true;
    }

    /**
     * The garbage collector, this is executed when the session garbage collector
     * is executed and takes the max session lifetime as its only parameter.
     *
     * @param int $sess_maxlifetime Maximum session lifetime.
     *
     * @return bool
     */
    public function gc($sess_maxlifetime)
    {
        $this->getTable('Session')->garbageCollect($sess_maxlifetime);
        return true;
    }

    /**
     * A function that is called internally when session data is to be saved.
     *
     * @param string $sess_id The current session ID
     * @param string $data    The session data to write
     *
     * @return bool
     */
    protected function saveSession($sess_id, $data)
    {
        $this->getTable('Session')->writeSession($sess_id, $data);
        return true;
    }
}
