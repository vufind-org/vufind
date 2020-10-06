<?php
/**
 * Table Definition for session
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;

/**
 * Table Definition for session
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Session extends \VuFind\Db\Table\Session
{
    /**
     * Retrieve an object from the database based on session ID; create a new
     * row if no existing match is found.
     *
     * @param string $sid    Session ID to retrieve
     * @param bool   $create Should we create rows that don't already exist?
     *
     * @return \VuFind\Db\Row\Session
     */
    public function getBySessionId($sid, $create = true)
    {
        return $this->tryWithRetry('parent::getBySessionId', [$sid, $create]);
    }

    /**
     * Retrieve data for the given session ID.
     *
     * @param string $sid      Session ID to retrieve
     * @param int    $lifetime Session lifetime (in seconds)
     *
     * @throws SessionExpiredException
     * @return string     Session data
     */
    public function readSession($sid, $lifetime)
    {
        return $this->tryWithRetry('parent::readSession', [$sid, $lifetime]);
    }

    /**
     * Store data for the given session ID.
     *
     * @param string $sid  Session ID to retrieve
     * @param string $data Data to store
     *
     * @return void
     */
    public function writeSession($sid, $data)
    {
        $this->tryWithRetry('parent::writeSession', [$sid, $data]);
    }

    /**
     * Try to call a method and retry if it fails
     *
     * @param string $method Method name
     * @param array  $params Method parameters
     *
     * @throws \Exception
     * @return mixed
     */
    protected function tryWithRetry($method, $params)
    {
        $try = 1;
        while (true) {
            try {
                $result = call_user_func_array($method, $params);
                return $result;
            } catch (\VuFind\Exception\SessionExpired $e) {
                throw $e;
            } catch (\Exception $e) {
                if ($try <= 5) {
                    usleep($try * 100000);
                    ++$try;
                    // Reset connection before retrying
                    $this->getAdapter()->getDriver()->getConnection()->disconnect();
                    $this->getAdapter()->getDriver()->getConnection()->connect();
                    continue;
                }
                throw $e;
            }
        }
    }
}
