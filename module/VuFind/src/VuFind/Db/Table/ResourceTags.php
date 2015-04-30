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
        $retVal = ['present' => [], 'missing' => []];

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
                [
                    'resource_id' => new Expression(
                        'DISTINCT(?)', ['resource_tags.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ), '*'
                ]
            );
            $select->join(
                ['t' => 'tags'], 'resource_tags.tag_id = t.id', []
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
     * @param bool $extended Include extended (unique/anonymous) stats.
     *
     * @return array
     */
    public function getStatistics($extended = false)
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
        $stats = (array)$result->current();
        if ($extended) {
            $stats['unique'] = count($this->getUniqueTags());
            $stats['anonymous'] = count($this->getAnonymousCount());
        }
        return $stats;
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param string|array $resource ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param string       $user     ID of user removing links
     * @param string       $list     ID of list to unlink (null for ALL matching
     * tags, 'none' for tags not in a list, true for tags only found in a list)
     * @param string       $tag      ID of tag to unlink (null for ALL matching
     * tags)
     *
     * @return void
     */
    public function destroyLinks($resource, $user, $list = null, $tag = null)
    {
        $callback = function ($select) use ($resource, $user, $list, $tag) {
            $select->where->equalTo('user_id', $user);
            if (!is_null($resource)) {
                if (!is_array($resource)) {
                    $resource = [$resource];
                }
                $select->where->in('resource_id', $resource);
            }
            if (!is_null($list)) {
                if (true === $list) {
                    // special case -- if $list is set to boolean true, we
                    // want to only delete tags that are associated with lists.
                    $select->where->isNotNull('list_id');
                } elseif ('none' === $list) {
                    // special case -- if $list is set to the string "none", we
                    // want to delete tags that are not associated with lists.
                    $select->where->isNull('list_id');
                } else {
                    $select->where->equalTo('list_id', $list);
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
            $ids = [];
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
        $this->update(['user_id' => $id], $callback);
    }

    /**
     * Gets unique resources from the table
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getUniqueResources(
        $userId = null, $resourceId = null, $tagId = null
    ) {
        $callback = function ($select) use ($userId, $resourceId, $tagId) {
            $select->join(
                ['r' => 'resource'],
                'resource_tags.resource_id = r.id',
                ["title" => "title"]
            );
            if (!is_null($userId)) {
                $select->where->equalTo('resource_tags.user_id', $userId);
            }
            if (!is_null($resourceId)) {
                $select->where->equalTo('resource_tags.resource_id', $resourceId);
            }
            if (!is_null($tagId)) {
                $select->where->equalTo('resource_tags.tag_id', $tagId);
            }
            $select->group(["resource_id"]);
            $select->order(["title"]);
        };
        return $this->select($callback);
    }

    /**
     * Gets unique tags from the table
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getUniqueTags($userId = null, $resourceId = null, $tagId = null)
    {

        $callback = function ($select) use ($userId, $resourceId, $tagId) {
            $select->join(
                ['t' => 'tags'],
                'resource_tags.tag_id = t.id',
                ["tag" => "tag"]
            );
            if (!is_null($userId)) {
                $select->where->equalTo('resource_tags.user_id', $userId);
            }
            if (!is_null($resourceId)) {
                $select->where->equalTo('resource_tags.resource_id', $resourceId);
            }
            if (!is_null($tagId)) {
                $select->where->equalTo('resource_tags.tag_id', $tagId);
            }
            $select->group(["tag_id"]);
            $select->order(["tag"]);
        };
        return $this->select($callback);
    }

    /**
     * Gets unique users from the table
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getUniqueUsers($userId = null, $resourceId = null, $tagId = null)
    {
        $callback = function ($select) use ($userId, $resourceId, $tagId) {
            $select->join(
                ['u' => 'user'],
                'resource_tags.user_id = u.id',
                ["username" => "username"]
            );
            if (!is_null($userId)) {
                $select->where->equalTo('resource_tags.user_id', $userId);
            }
            if (!is_null($resourceId)) {
                $select->where->equalTo('resource_tags.resource_id', $resourceId);
            }
            if (!is_null($tagId)) {
                $select->where->equalTo('resource_tags.tag_id', $tagId);
            }
            $select->group(["user_id"]);
            $select->order(["username"]);
        };
        return $this->select($callback);
    }

    /**
     * Get Resource Tags
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     * @param string $order      The order in which to return the data
     * @param string $page       The page number to select
     * @param string $limit      The number of items to fetch
     *
     * @return \Zend\Paginator\Paginator
     */
    public function getResourceTags(
        $userId = null, $resourceId = null, $tagId = null,
        $order = null, $page = null, $limit = 20
    ) {
        $order = (null !== $order)
            ? [$order]
            : ["username", "tag", "title"];

        $sql = $this->getSql();
        $select = $sql->select();
        $select->join(
            ['t' => 'tags'],
            'resource_tags.tag_id = t.id',
            ["tag" => "tag"]
        );
        $select->join(
            ['u' => 'user'],
            'resource_tags.user_id = u.id',
            ["username" => "username"]
        );
        $select->join(
            ['r' => 'resource'],
            'resource_tags.resource_id = r.id',
            ["title" => "title"]
        );
        if (null !== $userId) {
            $select->where->equalTo('resource_tags.user_id', $userId);
        }
        if (null !== $resourceId) {
            $select->where->equalTo('resource_tags.resource_id', $resourceId);
        }
        if (null !== $tagId) {
            $select->where->equalTo('resource_tags.tag_id', $tagId);
        }
        $select->order($order);

        if (null !== $page) {
            $select->limit($limit);
            $select->offset($limit * ($page - 1));
        }

        $adapter = new \Zend\Paginator\Adapter\DbSelect($select, $sql);
        $paginator = new \Zend\Paginator\Paginator($adapter);
        $paginator->setItemCountPerPage($limit);
        if (null !== $page) {
            $paginator->setCurrentPageNumber($page);
        }
        return $paginator;
    }

    /**
     * Delete a group of tags.
     *
     * @param array $ids IDs of tags to delete.
     *
     * @return int       Count of $ids
     */
    public function deleteByIdArray($ids)
    {
        // Do nothing if we have no IDs to delete!
        if (empty($ids)) {
            return;
        }

        $callback = function ($select) use ($ids) {
            $select->where->in('id', $ids);
        };
        $this->delete($callback);
        return count($ids);
    }
}
