<?php

/**
 * Table Definition for comments
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceServiceInterface;

use function count;
use function is_object;

/**
 * Table Definition for comments
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Comments extends Gateway implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'comments'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return array|\Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getForResource($id, $source = DEFAULT_SEARCH_BACKEND)
    {
        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        $resource = $resourceService->getResourceByRecordId($id, $source);
        if (!$resource) {
            return [];
        }

        $callback = function ($select) use ($resource) {
            $select->columns([Select::SQL_STAR]);
            $select->join(
                ['u' => 'user'],
                'u.id = comments.user_id',
                ['firstname', 'lastname'],
                $select::JOIN_LEFT
            );
            $select->where->equalTo('comments.resource_id', $resource->id);
            $select->order('comments.created');
        };

        return $this->select($callback);
    }

    /**
     * Delete a comment if the owner is logged in. Returns true on success.
     *
     * @param int                 $id   ID of row to delete
     * @param UserEntityInterface $user Logged in user object
     *
     * @return bool
     */
    public function deleteIfOwnedByUser($id, $user)
    {
        // User must be object with ID:
        if (!is_object($user) || !($userId = $user->getId())) {
            return false;
        }

        // Comment row must exist:
        $matches = $this->select(['id' => $id]);
        if (count($matches) == 0 || !($row = $matches->current())) {
            return false;
        }

        // Row must be owned by user:
        if ($row->user_id != $userId) {
            return false;
        }

        // If we got this far, everything is okay:
        $row->delete();
        return true;
    }

    /**
     * Deletes all comments by a user.
     *
     * @param \VuFind\Db\Row\User $user User object
     *
     * @return void
     */
    public function deleteByUser($user)
    {
        $this->delete(['user_id' => $user->id]);
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
                    'COUNT(DISTINCT(?))',
                    ['user_id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
                'resources' => new Expression(
                    'COUNT(DISTINCT(?))',
                    ['resource_id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
                'total' => new Expression('COUNT(*)'),
            ]
        );
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        return (array)$result->current();
    }
}
