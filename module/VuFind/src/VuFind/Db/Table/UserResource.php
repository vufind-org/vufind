<?php
/**
 * Table Definition for user_resource
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
use Zend\Db\Sql\Expression;

/**
 * Table Definition for user_resource
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class UserResource extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('user_resource', 'VuFind\Db\Row\UserResource');
    }

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param string $resourceId ID of record being checked.
     * @param string $source     Source of record to look up
     * @param int    $listId     Optional list ID (to limit results to a particular
     * list).
     * @param int    $userId     Optional user ID (to limit results to a particular
     * user).
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getSavedData($resourceId, $source = 'VuFind', $listId = null,
        $userId = null
    ) {
        $callback = function ($select) use ($resourceId, $source, $listId, $userId) {
            $select->columns(
                [
                    new Expression(
                        'DISTINCT(?)', ['user_resource.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ), '*'
                ]
            );
            $select->join(
                ['r' => 'resource'], 'r.id = user_resource.resource_id',
                []
            );
            $select->join(
                ['ul' => 'user_list'],
                'user_resource.list_id = ul.id',
                ['list_title' => 'title', 'list_id' => 'id']
            );
            $select->where->equalTo('r.source', $source)
                ->equalTo('r.record_id', $resourceId);

            if (!is_null($userId)) {
                $select->where->equalTo('user_resource.user_id', $userId);
            }
            if (!is_null($listId)) {
                $select->where->equalTo('user_resource.list_id', $listId);
            }
        };
        return $this->select($callback);
    }

    /**
     * Create link if one does not exist; update notes if one does.
     *
     * @param string $resource_id ID of resource to link up
     * @param string $user_id     ID of user creating link
     * @param string $list_id     ID of list to link up
     * @param string $notes       Notes to associate with link
     *
     * @return void
     */
    public function createOrUpdateLink($resource_id, $user_id, $list_id,
        $notes = ''
    ) {
        $params = [
            'resource_id' => $resource_id, 'list_id' => $list_id,
            'user_id' => $user_id
        ];
        $result = $this->select($params)->current();

        // Only create row if it does not already exist:
        if (empty($result)) {
            $result = $this->createRow();
            $result->resource_id = $resource_id;
            $result->list_id = $list_id;
            $result->user_id = $user_id;
        }

        // Update the notes:
        $result->notes = $notes;
        $result->save();
    }

    /**
     * Unlink rows for the specified resource.  This will also automatically remove
     * any tags associated with the relationship.
     *
     * @param string|array $resource_id ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param string       $user_id     ID of user removing links
     * @param string       $list_id     ID of list to unlink
     * (null for ALL matching lists, with the destruction of all tags associated
     * with the $resource_id value; true for ALL matching lists, but retaining
     * any tags associated with the $resource_id independently of lists)
     *
     * @return void
     */
    public function destroyLinks($resource_id, $user_id, $list_id = null)
    {
        // Remove any tags associated with the links we are removing; we don't
        // want to leave orphaned tags in the resource_tags table after we have
        // cleared out favorites in user_resource!
        $resourceTags = $this->getDbTable('ResourceTags');
        $resourceTags->destroyLinks($resource_id, $user_id, $list_id);

        // Now build the where clause to figure out which rows to remove:
        $callback = function ($select) use ($resource_id, $user_id, $list_id) {
            $select->where->equalTo('user_id', $user_id);
            if (null !== $resource_id) {
                if (!is_array($resource_id)) {
                    $resource_id = [$resource_id];
                }
                $select->where->in('resource_id', $resource_id);
            }
            // null or true values of $list_id have different meanings in the
            // context of the $resourceTags->destroyLinks() call above, since
            // some tags have a null $list_id value. In the case of user_resource
            // rows, however, every row has a non-null $list_id value, so the
            // two cases are equivalent and may be handled identically.
            if (null !== $list_id && true !== $list_id) {
                $select->where->equalTo('list_id', $list_id);
            }
        };

        // Delete the rows:
        $this->delete($callback);
    }

    /**
     * Get statistics on use of lists.
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
                'lists' => new Expression(
                    'COUNT(DISTINCT(?))', ['list_id'],
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
