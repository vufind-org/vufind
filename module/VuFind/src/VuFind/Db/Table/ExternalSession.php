<?php
/**
 * Table Definition for external_session
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
use Zend\Db\Sql\Expression;

/**
 * Table Definition for external_session
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExternalSession extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('external_session', 'VuFind\Db\Row\ExternalSession');
    }

    /**
     * Add a mapping between local and external session id's
     *
     * @param string $localSessionId    Local (VuFind) session id
     * @param string $externalSessionId External session id
     *
     * @return void
     */
    public function addSessionMapping($localSessionId, $externalSessionId)
    {
        $this->destroySession($localSessionId);
        $row = $this->createRow();
        $row->session_id = $localSessionId;
        $row->external_session_id = $externalSessionId;
        $row->created = date('Y-m-d H:i:s');
        $row->save();
    }

    /**
     * Retrieve an object from the database based on an external session ID
     *
     * @param string $sid External session ID to retrieve
     *
     * @return \VuFind\Db\Row\ExternalSession
     */
    public function getByExternalSessionId($sid)
    {
        return $this->select(['external_session_id' => $sid])->current();
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
        $this->delete(['session_id' => $sid]);
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
            $select->where->lessThan('created', $expireDate);
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
}
