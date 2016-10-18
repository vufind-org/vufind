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
    use ExpirationTrait;

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
     * Update the select statement to find records to delete.
     *
     * @param Select $select  Select clause
     * @param int    $daysOld Age in days of an "expired" record.
     * @param int    $idFrom  Lowest id of rows to delete.
     * @param int    $idTo    Highest id of rows to delete.
     *
     * @return void
     */
    protected function expirationCallback($select, $daysOld, $idFrom = null,
        $idTo = null
    ) {
        $expireDate = date('Y-m-d', time() - $daysOld * 24 * 60 * 60);
        $where = $select->where->lessThan('created', $expireDate);
        if (null !== $idFrom) {
            $where->and->greaterThanOrEqualTo('id', $idFrom);
        }
        if (null !== $idTo) {
            $where->and->lessThanOrEqualTo('id', $idTo);
        }
    }
}
