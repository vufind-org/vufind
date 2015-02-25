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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Db\Table;
use VuFind\Exception\LoginRequired as LoginRequiredException,
    VuFind\Exception\RecordMissing as RecordMissingException,
    Zend\Db\Sql\Expression;

/**
 * Table Definition for user_list
 *
 * @category VuFind2
 * @package  Db_Table
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
     * @param \VuFind\Db\Row\UserList|bool $user User object representing owner of
     * new list (or false if not logged in)
     *
     * @return \VuFind\Db\Row\UserList
     * @throws LoginRequiredException
     */
    public function getNew($user)
    {
        if (!$user) {
            throw new LoginRequiredException('Log in to create lists.');
        }

        $row = $this->createRow();
        $row->created = date('Y-m-d H:i:s');    // force creation date
        $row->user_id = $user->id;
        return $row;
    }

    /**
     * Retrieve a list object.
     *
     * @param int $id Numeric ID for existing list.
     *
     * @return \VuFind\Db\Row\UserList
     * @throws RecordMissingException
     */
    public function getExisting($id)
    {
        $result = $this->select(['id' => $id])->current();
        if (empty($result)) {
            throw new RecordMissingException('Cannot load list ' . $id);
        }
        return $result;
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
        // Set up base query:
        $callback = function ($select) use ($resourceId, $source, $userId) {
            $select->columns(
                [
                    new Expression(
                        'DISTINCT(?)', ['user_list.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ), '*'
                ]
            );
            $select->join(
                ['ur' => 'user_resource'], 'ur.list_id = user_list.id',
                []
            );
            $select->join(
                ['r' => 'resource'], 'r.id = ur.resource_id', []
            );
            $select->where->equalTo('r.source', $source)
                ->equalTo('r.record_id', $resourceId);
            $select->order(['title']);

            if (!is_null($userId)) {
                $select->where->equalTo('ur.user_id', $userId);
            }
        };
        return $this->select($callback);
    }
}
