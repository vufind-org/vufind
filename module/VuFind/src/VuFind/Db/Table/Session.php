<?php
/**
 * Table Definition for session
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Db\Table;
use VuFind\Exception\SessionExpired as SessionExpiredException;
use Zend\Db\Sql\Expression;

/**
 * Table Definition for session
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Session extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('session', 'VuFind\Db\Row\Session');
    }

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
        $row = $this->select(['session_id' => $sid])->current();
        if ($create && empty($row)) {
            $row = $this->createRow();
            $row->session_id = $sid;
            $row->created = date('Y-m-d H:i:s');
        }
        return $row;
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
        $s = $this->getBySessionId($sid);

        // enforce lifetime of this session data
        if (!empty($s->last_used) && $s->last_used + $lifetime <= time()) {
            throw new SessionExpiredException('Session expired!');
        }

        // if we got this far, session is good -- update last access time, save
        // changes, and return data.
        $s->last_used = time();
        $s->save();
        return empty($s->data) ? '' : $s->data;
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
        $s = $this->getBySessionId($sid);
        $s->last_used = time();
        $s->data = $data;
        $s->save();
    }

    /**
     * Destroy data for the given session ID.
     *
     * @param string $sid Session ID to erase
     *
     * @return void
     */
    public function destroySession($sid)
    {
        $s = $this->getBySessionId($sid, false);
        if (!empty($s)) {
            $s->delete();
        }
    }

    /**
     * Garbage collect expired sessions.
     *
     * @param int $sess_maxlifetime Maximum session lifetime.
     *
     * @return void
     */
    public function garbageCollect($sess_maxlifetime)
    {
        $callback = function ($select) use ($sess_maxlifetime) {
            $select->where
                ->lessThan('last_used', time() - intval($sess_maxlifetime));
        };
        $this->delete($callback);
    }

    /**
     * Delete expired sessions. Allows setting of 'from' and 'to' ID's so that rows
     * can be deleted in small batches.
     *
     * @param int $daysOld Age in days of an "expired" session.
     * @param int $idFrom  Lowest id of rows to delete.
     * @param int $idTo    Highest id of rows to delete.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired($daysOld = 2, $idFrom = null, $idTo = null)
    {
        $expireDate = time() - $daysOld * 24 * 60 * 60;
        $callback = function ($select) use ($expireDate, $idFrom, $idTo) {
            $where = $select->where->lessThan('last_used', $expireDate);
            if (null !== $idFrom) {
                $where->and->greaterThanOrEqualTo('id', $idFrom);
            }
            if (null !== $idTo) {
                $where->and->lessThanOrEqualTo('id', $idTo);
            }
        };
        return $this->delete($callback);
    }

    /**
     * Get the lowest id and highest id for expired sessions.
     *
     * @param int $daysOld Age in days of an "expired" session.
     *
     * @return array|bool Array of lowest id and highest id or false if no expired
     * records found
     */
    public function getExpiredIdRange($daysOld = 2)
    {
        $expireDate = time() - $daysOld * 24 * 60 * 60;
        $callback = function ($select) use ($expireDate) {
            $select->where->lessThan('last_used', $expireDate);
        };
        $select = $this->getSql()->select();
        $select->columns(
            [
                'id' => new Expression('1'), // required for TableGateway
                'minId' => new Expression('MIN(id)'),
                'maxId' => new Expression('MAX(id)'),
            ]
        );
        $select->where($callback);
        $result = $this->selectWith($select)->current();
        if (null === $result->minId) {
            return false;
        }
        return [$result->minId, $result->maxId];
    }

    /**
     * Get a query representing expired sessions (this can be passed
     * to select() or delete() for further processing).
     *
     * @param int $daysOld Age in days of an "expired" session.
     *
     * @return function
     */
    public function getExpiredQuery($daysOld = 2)
    {
        // Determine the expiration date:
        $expireDate = time() - $daysOld * 24 * 60 * 60;
        $callback = function ($select) use ($expireDate) {
            $select->where->lessThan('last_used', $expireDate);
        };
        return $callback;
    }
}
