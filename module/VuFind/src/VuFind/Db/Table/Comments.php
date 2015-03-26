<?php
/**
 * Table Definition for comments
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2012.
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
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;
use Zend\Db\Sql\Expression;

/**
 * Table Definition for comments
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Comments extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('comments', 'VuFind\Db\Row\Comments');
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return array|\Zend\Db\ResultSet\AbstractResultSet
     */
    public function getForResource($id, $source = 'VuFind')
    {
        $resourceTable = $this->getDbTable('Resource');
        $resource = $resourceTable->findResource($id, $source, false);
        if (empty($resource)) {
            return [];
        }

        $callback = function ($select) use ($resource) {
            $select->columns(['*']);
            $select->join(
                ['u' => 'user'], 'u.id = comments.user_id',
                ['firstname', 'lastname']
            );
            $select->where->equalTo('comments.resource_id',  $resource->id);
            $select->order('comments.created');
        };

        return $this->select($callback);
    }

    /**
     * Delete a comment if the owner is logged in.  Returns true on success.
     *
     * @param int                 $id   ID of row to delete
     * @param \VuFind\Db\Row\User $user Logged in user object
     *
     * @return bool
     */
    public function deleteIfOwnedByUser($id, $user)
    {
        // User must be object with ID:
        if (!is_object($user) || !isset($user->id)) {
            return false;
        }

        // Comment row must exist:
        $matches = $this->select(['id' => $id]);
        if (count($matches) == 0 || !($row = $matches->current())) {
            return false;
        }

        // Row must be owned by user:
        if ($row->user_id != $user->id) {
            return false;
        }

        // If we got this far, everything is okay:
        $row->delete();
        return true;
    }

    /**
     * Get statistics on use of comments.
     *
     * @return array
     */
    public function getStatistics()
    {
        $select = $this->sql->select();
        $select->columns(
            [
                'users' => new Expression(
                    'COUNT(DISTINCT(?))', ['user_id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
                'resources' => new Expression(
                    'COUNT(DISTINCT(?))', ['resource_id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
                'total' => new Expression('COUNT(*)')
            ]
        );
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        return (array)$result->current();
    }
}
