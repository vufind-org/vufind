<?php
/**
 * Table Definition for user_list
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Db\Table;

/**
 * Table Definition for user_list
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class UserList extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('user_list', 'VuFind\Db\Row\UserList');
    }

    /**
     * Create a new list object.
     *
     * @param VuFind_Model_Db_UserListRow $user User object representing owner of
     * new list
     *
     * @return VuFind_Model_Db_UserListRow
     */
    public static function getNew($user)
    {
        /* TODO
        if (!$user) {
            throw new VF_Exception_LoginRequired('Log in to create lists.');
        }

        $table = new VuFind_Model_Db_UserList();
        $row = $table->createRow();
        $row->user_id = $user->id;
        return $row;
         */
    }

    /**
     * Retrieve a list object.
     *
     * @param int $id Numeric ID for existing list.
     *
     * @return VuFind_Model_Db_UserListRow
     * @throws VF_Exception_RecordMissing
     */
    public static function getExisting($id)
    {
        /* TODO
        $table = new VuFind_Model_Db_UserList();
        $result = $table->find($id)->current();
        if (is_null($result)) {
            throw new VF_Exception_RecordMissing('Cannot load list ' . $id);
        }
        return $result;
         */
    }

    /**
     * Get lists containing a specific user_resource
     *
     * @param string $resourceId ID of record being checked.
     * @param string $source     Source of record to look up
     * @param int    $userId     Optional user ID (to limit results to a particular
     * user).
     *
     * @return array
     */
    public function getListsContainingResource($resourceId, $source = 'VuFind',
        $userId = null
    ) {
        /* TODO
        // Set up base query:
        $select = $this->select();
        $select->setIntegrityCheck(false)   // allow join
            ->distinct()
            ->from(array('ul' => $this->_name), 'ul.*')
            ->join(
                array('ur' => 'user_resource'),
                'ur.list_id = ul.id',
                array()
            )
            ->join(
                array('r' => 'resource'), 'r.id = ur.resource_id',
                array()
            )
            ->where('r.source = ?', $source)
            ->where('r.record_id = ?', $resourceId)
            ->order(array('ul.title'));

        if (!is_null($userId)) {
            $select->where('ur.user_id = ?', $userId);
        }

        return $this->fetchAll($select);
         */
    }
}
