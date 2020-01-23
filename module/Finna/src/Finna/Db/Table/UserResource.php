<?php
/**
 * Table Definition for user_resource
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2019.
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
 * Table Definition for user_resource
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserResource extends \VuFind\Db\Table\UserResource
{
    /**
     * Create link if one does not exist; update notes if one does.
     *
     * @param string $resource_id ID of resource to link up
     * @param string $user_id     ID of user creating link
     * @param string $list_id     ID of list to link up
     * @param string $notes       Notes to associate with link
     * @param int    $order       Custom order index for the resource in list
     *
     * @return void
     */
    public function createOrUpdateLink($resource_id, $user_id, $list_id,
        $notes = '', $order = null
    ) {
        $row = parent::createOrUpdateLink($resource_id, $user_id, $list_id, $notes);
        $row->finna_custom_order_index = $order;
        $row->save();
        $this->updateListDate($list_id, $user_id);
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
        parent::destroyLinks($resource_id, $user_id, $list_id);
        if (null !== $list_id && true !== $list_id) {
            $this->updateListDate($list_id, $user_id);
        }
    }

    /**
     * Add custom favorite list order
     *
     * @param int   $userId       User id
     * @param int   $listId       List id
     * @param array $resourceList Ordered List of Resources
     *
     * @return boolean
     */
    public function saveCustomFavoriteOrder($userId, $listId, $resourceList)
    {
        $resourceIndex = array_flip(array_values($resourceList));

        $callback = function ($select) use ($listId, $userId) {
            $select->join(
                ['r' => 'resource'],
                'r.id = user_resource.resource_id',
                ['record_id']
            );
            $select->where->equalTo('list_id', $listId);
            $select->where->equalTo('user_id', $userId);
        };

        foreach ($this->select($callback) as $row) {
            $rowToUpdate = $this->select(
                [
                    'user_id' => $userId,
                    'list_id' => $listId,
                    'resource_id' => $row->resource_id
                ]
            )->current();
            if ($rowToUpdate) {
                $rowToUpdate->finna_custom_order_index
                    = isset($resourceIndex[$row->record_id])
                    ? $resourceIndex[$row->record_id] : 0;
                $rowToUpdate->save();
            }
        }
        return true;
    }

    /**
     * Check if custom favorite order is used in a list
     *
     * @param int $listId List id
     *
     * @return bool
     */
    public function isCustomOrderAvailable($listId)
    {
        $callback = function ($select) use ($listId) {
            $select->where->equalTo('list_id', $listId);
            $select->join(
                ['r' => 'resource'],
                'user_resource.resource_id = r.id',
                ['record_id']
            );
            $select->where->isNotNull('finna_custom_order_index');
        };
        return $this->select($callback)->count() > 0;
    }

    /**
     * Get next available custom order index
     *
     * @param int $listId List id
     *
     * @return int Results next available index or zero if custom order is not
     *             used or list is empty
     */
    public function getNextAvailableCustomOrderIndex($listId)
    {
        $callback = function ($select) use ($listId) {
            $select->where->equalTo('list_id', $listId);
            $select->where->isNotNull('finna_custom_order_index');
            $select->order('finna_custom_order_index DESC');
        };
        $result = $this->select($callback);
        if ($result->count() > 0) {
            return $result->current()->finna_custom_order_index + 1;
        }
        return 0;
    }

    /**
     * Update the date of a list
     *
     * @param string $listId ID of list to unlink
     * @param string $userId ID of user removing links
     *
     * @return void
     */
    protected function updateListDate($listId, $userId)
    {
        $userTable = $this->getDbTable('User');
        $user = $userTable->select(['id' => $userId])->current();
        if (empty($user)) {
            return;
        }
        $listTable = $this->getDbTable('UserList');
        $list = $listTable->getExisting($listId);
        if (empty($list->title)) {
            // Save throws an exception unless the list has a title
            $list->title = '-';
        }
        $list->save($user);
    }
}
