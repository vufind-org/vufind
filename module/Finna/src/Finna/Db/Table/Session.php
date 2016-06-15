<?php
/**
 * Table Definition for session
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
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
     * Errors that are retried if encountered
     *
     * @var array
     */
    protected $retryErrors = [
        "Commands out of sync; you can't run this command now",
        'Deadlock found when trying to get lock; try restarting transaction'
    ];

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
        $try = 1;
        while (true) {
            try {
                $result = parent::getBySessionId($sid, $create);
                if ($try > 1) {
                    error_log("getBySessionId succeeded on attempt $try");
                }
                return $result;
            } catch (\Exception $e) {
                if ($try <= 5 && in_array($e->getMessage(), $this->retryErrors)) {
                    usleep(150000);
                    ++$try;
                    continue;
                }
                error_log("getBySessionId failed even after retries");
                throw $e;
            }
        }
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
        $try = 1;
        while (true) {
            try {
                parent::writeSession($sid, $data);
                if ($try > 1) {
                    error_log("writeSession succeeded on attempt $try");
                }
                return;
            } catch (\Exception $e) {
                if ($try <= 5 && in_array($e->getMessage(), $this->retryErrors)) {
                    usleep(150000);
                    ++$try;
                    continue;
                }
                error_log("writeSession failed even after retries");
                throw $e;
            }
        }
    }
}
