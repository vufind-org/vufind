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
 * @package  Db_Table
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
 * @package  Db_Table
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
     * @param string $resource ID of resource to link up
     * @param string $tag      ID of tag to link up
     * @param string $user     ID of user creating link (optional but recommended)
     * @param string $list     ID of list to link up (optional)
     * @param string $posted   Posted date (optional -- omit for current)
     *
     * @return void
     */
    public function createLink($resource, $tag, $user = null, $list = null,
        $posted = null
    ) {
        $callback = function ($select) use ($resource, $tag, $user, $list) {
            $select->where->equalTo('resource_id', $resource)
                ->equalTo('tag_id', $tag);
            if (!is_null($list)) {
                $select->where->equalTo('list_id', $list);
            } else {
                $select->where->isNull('list_id');
            }
            if (!is_null($user)) {
                $select->where->equalTo('user_id', $user);
            } else {
                $select->where->isNull('user_id');
            }
        };
        $result = $this->select($callback)->current();

        // Only create row if it does not already exist:
        if (empty($result)) {
            $result = $this->createRow();
            $result->resource_id = $resource;
            $result->tag_id = $tag;
            if (!is_null($list)) {
                $result->list_id = $list;
            }
            if (!is_null($user)) {
                $result->user_id = $user;
            }
            if (!is_null($posted)) {
                $result->posted = $posted;
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
        // Set up return arrays:
        $retVal = array('present' => array(), 'missing' => array());

        // Look up IDs in the table:
        $callback = function ($select) use ($ids) {
            $select->where->in('tag_id', $ids);
        };
        $results = $this->select($callback);

        // Record all IDs that are present:
        foreach ($results as $current) {
            $retVal['present'][] = $current->tag_id;
        }
        $retVal['present'] = array_unique($retVal['present']);

        // Detect missing IDs:
        foreach ($ids as $current) {
            if (!in_array($current, $retVal['present'])) {
                $retVal['missing'][] = $current;
            }
        }

        // Send back the results:
        return $retVal;
    }

    /**
     * Get resources associated with a particular tag.
     *
     * @param string $tag    Tag to match
     * @param string $userId ID of user owning favorite list
     * @param string $listId ID of list to retrieve (null for all favorites)
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getResourcesForTag($tag, $userId, $listId = null)
    {
        $callback = function ($select) use ($tag, $userId, $listId) {
            $select->columns(
                array(
                    'resource_id' => new Expression(
                        'DISTINCT(?)', array('resource_tags.resource_id'),
                        array(Expression::TYPE_IDENTIFIER)
                    ), '*'
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
     * Get statistics on use of tags.
     *
     * @return array
     */
    public function getStatistics()
    {
        $select = $this->sql->select();
        $select->columns(
            array(
                'users' => new Expression(
                    'COUNT(DISTINCT(?))', array('user_id'),
                    array(Expression::TYPE_IDENTIFIER)
                ),
                'resources' => new Expression(
                    'COUNT(DISTINCT(?))', array('resource_id'),
                    array(Expression::TYPE_IDENTIFIER)
                ),
                'total' => new Expression('COUNT(*)')
            )
        );
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        return (array)$result->current();
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param string|array $resource ID (or array of IDs) of resource(s) to
     *                                  unlink (null for ALL matching resources)
     * @param string       $user     ID of user removing links
     * @param string       $list     ID of list to unlink (null for ALL matching
     *                                  lists, 'none' for tags not in a list)
     * @param string       $tag      ID of tag to unlink (null for ALL matching
     *                                  tags)
     *
     * @return void
     */
    public function destroyLinks($resource, $user, $list = null, $tag = null)
    {
        $callback = function ($select) use ($resource, $user, $list, $tag) {
            $select->where->equalTo('user_id', $user);
            if (!is_null($resource)) {
                if (!is_array($resource)) {
                    $resource = array($resource);
                }
                $select->where->in('resource_id', $resource);
            }
            if (!is_null($list)) {
                if ($list != 'none') {
                    $select->where->equalTo('list_id', $list);
                } else {
                    // special case -- if $list is set to the string "none", we
                    // want to delete tags that are not associated with lists.
                    $select->where->isNull('list_id');
                }
            }
            if (!is_null($tag)) {
                $select->where->equalTo('tag_id', $tag);
            }
        };


        // Get a list of all tag IDs being deleted; we'll use these for
        // orphan-checking:
        $potentialOrphans = $this->select($callback);

        // Now delete the unwanted rows:
        $this->delete($callback);

        // Check for orphans:
        if (count($potentialOrphans) > 0) {
            $ids = array();
            foreach ($potentialOrphans as $current) {
                $ids[] = $current->tag_id;
            }
            $checkResults = $this->checkForTags(array_unique($ids));
            if (count($checkResults['missing']) > 0) {
                $tagTable = $this->getDbTable('Tags');
                $tagTable->deleteByIdArray($checkResults['missing']);
            }
        }
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
