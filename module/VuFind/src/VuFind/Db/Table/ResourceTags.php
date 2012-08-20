<?php
/**
 * Table Definition for resource_tags
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
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;
use Zend\Db\Sql\Expression;

/**
 * Table Definition for resource_tags
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ResourceTags extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('resource_tags', 'VuFind\Db\Row\ResourceTags');
    }

    /**
     * Look up a row for the specified resource.
     *
     * @param string $resource_id ID of resource to link up
     * @param string $tag_id      ID of tag to link up
     * @param string $user_id     ID of user creating link (optional but recommended)
     * @param string $list_id     ID of list to link up (optional)
     *
     * @return void
     */
    public function createLink($resource_id, $tag_id, $user_id = null,
        $list_id = null
    ) {
        $callback = function ($select) use ($resource_id, $tag_id, $user_id,
            $list_id
        ) {
            $select->where->equalTo('resource_id', $resource_id)
                ->equalTo('tag_id', $tag_id);
            if (!is_null($list_id)) {
                $select->where->equalTo('list_id', $list_id);
            } else {
                $select->where->isNull('list_id');
            }
            if (!is_null($user_id)) {
                $select->where->equalTo('user_id', $user_id);
            } else {
                $select->where->isNull('user_id');
            }
        };
        $result = $this->select($callback)->current();

        // Only create row if it does not already exist:
        if (empty($result)) {
            $result = $this->createRow();
            $result->resource_id = $resource_id;
            $result->tag_id = $tag_id;
            if (!is_null($list_id)) {
                $result->list_id = $list_id;
            }
            if (!is_null($user_id)) {
                $result->user_id = $user_id;
            }
            $result->save();
        }
    }

    /**
     * Check whether or not the specified tags are present in the table.
     *
     * @param array $ids IDs to check.
     *
     * @return array     Associative array with two keys: present and missing
     */
    public function checkForTags($ids)
    {
        /* TODO
        // Set up return arrays:
        $retVal = array('present' => array(), 'missing' => array());

        // Look up IDs in the table:
        $select = $this->select()->distinct()->from($this->_name, 'tag_id');
        foreach ($ids as $current) {
            $select->orWhere('tag_id = ?', $current);
        }
        $results = $this->fetchAll($select);

        // Record all IDs that are present:
        foreach ($results as $current) {
            $retVal['present'][] = $current->tag_id;
        }

        // Detect missing IDs:
        foreach ($ids as $current) {
            if (!in_array($current, $retVal['present'])) {
                $retVal['missing'][] = $current;
            }
        }

        // Send back the results:
        return $retVal;
         */
    }

    /**
     * Get resources associated with a particular tag.
     *
     * @param string $tag    Tag to match
     * @param string $userId ID of user owning favorite list
     * @param string $listId ID of list to retrieve (null for all favorites)
     *
     * @return Zend_Db_Table_Rowset
     */
    public function getResourcesForTag($tag, $userId, $listId = null)
    {
        $callback = function ($select) use ($tag, $userId, $listId) {
            $select->columns(
                array(
                    'resource_id' => new Expression(
                        'DISTINCT(?)', array('resource_tags.resource_id'),
                        array(Expression::TYPE_IDENTIFIER)
                    )
                )
            );
            $select->join(
                array('t' => 'tags'), 'resource_tags.tag_id = t.id', array()
            );
            $select->where->equalTo('t.tag', $tag)
                ->where->equalTo('resource_tags.user_id', $userId);
            if (!is_null($listId)) {
                $select->where->equalTo('resource_tags.list_id', $listId);
            }
        };

        return $this->select($callback);
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param string|array $resource_id ID (or array of IDs) of resource(s) to
     *                                  unlink (null for ALL matching resources)
     * @param string       $user_id     ID of user removing links
     * @param string       $list_id     ID of list to unlink (null for ALL matching
     *                                  lists, 'none' for tags not in a list)
     * @param string       $tag_id      ID of tag to unlink (null for ALL matching
     *                                  tags)
     *
     * @return void
     */
    public function destroyLinks($resource_id, $user_id, $list_id = null,
        $tag_id = null
    ) {
        /* TODO
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
            if ($list_id != 'none') {
                $where .= $db->quoteInto(' AND list_id = ?', $list_id);
            } else {
                // special case -- if $list_id is set to the string "none", we
                // want to delete tags that are not associated with lists.
                $where .= ' AND list_id is null';
            }
        }
        if (!is_null($tag_id)) {
            $where .= $db->quoteInto(' AND tag_id = ?', $tag_id);
        }

        // Get a list of all tag IDs being deleted; we'll use these for
        // orphan-checking:
        $select = $this->select()->distinct()->from($this->_name, 'tag_id')
            ->where($where);
        $potentialOrphans = $this->fetchAll($select);

        // Now delete the unwanted rows:
        $this->delete($where);

        // Check for orphans:
        if (count($potentialOrphans) > 0) {
            $ids = array();
            foreach ($potentialOrphans as $current) {
                $ids[] = $current->tag_id;
            }
            $checkResults = $this->checkForTags($ids);
            if (count($checkResults['missing']) > 0) {
                $tagTable = new VuFind_Model_Db_Tags();
                $tagTable->deleteByIdArray($checkResults['missing']);
            }
        }
         */
    }

    /**
     * Get count of anonymous tags
     *
     * @return int count
     */
    public function getAnonymousCount()
    {
        $callback = function ($select) {
            $select->where->isNull('user_id');
        };
        return count($this->select($callback));
    }

    /**
     * Assign anonymous tags to the specified user ID.
     *
     * @param int $id User ID to own anonymous tags.
     *
     * @return void
     */
    public function assignAnonymousTags($id)
    {
        $callback = function ($select) {
            $select->where->isNull('user_id');
        };
        $this->update(array('user_id' => $id), $callback);
    }
}
