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
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Db\Table;

/**
 * Table Definition for user_resource
 *
 * @category VuFind2
 * @package  DB_Models
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
        parent::__construct('user_resource');
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
     * @return Zend_Db_Table_Rowset
     */
    public function getSavedData($resourceId, $source = 'VuFind', $listId = null,
        $userId = null
    ) {
        /* TODO
        // Set up base query:
        $select = $this->select();
        $select->setIntegrityCheck(false)   // allow join
            ->distinct()
            ->from(array('ur' => $this->_name), 'ur.*')
            ->join(
                array('r' => 'resource'), 'r.id = ur.resource_id',
                array()
            )
            ->join(
                array('ul' => 'user_list'),
                'ur.list_id = ul.id',
                array('list_title' => 'ul.title', 'list_id' => 'ul.id')
            )
            ->where('r.source = ?', $source)
            ->where('r.record_id = ?', $resourceId);

        if (!is_null($userId)) {
            $select->where('ur.user_id = ?', $userId);
        }
        if (!is_null($listId)) {
            $select->where('ur.list_id = ?', $listId);
        }

        return $this->fetchAll($select);
         */
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
        /* TODO
        $select = $this->select();
        $select->where('resource_id = ?', $resource_id)
            ->where('list_id = ?', $list_id)
            ->where('user_id = ?', $user_id);
        $result = $this->fetchRow($select);

        // Only create row if it does not already exist:
        if (is_null($result)) {
            $result = $this->createRow();
            $result->resource_id = $resource_id;
            $result->list_id = $list_id;
            $result->user_id = $user_id;
        }

        // Update the notes:
        $result->notes = $notes;
        $result->save();
         */
    }

    /**
     * Unlink rows for the specified resource.  This will also automatically remove
     * any tags associated with the relationship.
     *
     * @param string|array $resource_id ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param string       $user_id     ID of user removing links
     * @param string       $list_id     ID of list to unlink
     *                                  (null for ALL matching lists)
     *
     * @return void
     */
    public function destroyLinks($resource_id, $user_id, $list_id = null)
    {
        /* TODO
        // Remove any tags associated with the links we are removing; we don't
        // want to leave orphaned tags in the resource_tags table after we have
        // cleared out favorites in user_resource!
        $resourceTags = new VuFind_Model_Db_ResourceTags();
        $resourceTags->destroyLinks($resource_id, $user_id, $list_id);

        // Now build the where clause to figure out which rows to remove:
        $db = $this->getAdapter();

        $where = $db->quoteInto('user_id = ?', $user_id);

        if (!is_null($resource_id)) {
            if (is_array($resource_id)) {
                $resourceSQL = array();
                foreach ($resource_id as $current) {
                    $resourceSQL[] = $db->quoteInto('resource_id = ?', $current);
                }
                $where .= ' AND (' . implode(' OR ', $resourceSQL) . ')';
            } else {
                $where .= $db->quoteInto(' AND resource_id = ?', $resource_id);
            }
        }
        if (!is_null($list_id)) {
            $where .= $db->quoteInto(' AND list_id = ?', $list_id);
        }

        // Delete the rows:
        $this->delete($where);
         */
    }
}
