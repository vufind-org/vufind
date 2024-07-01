<?php

/**
 * Table Definition for resource_tags
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

use DateTime;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceTagsServiceInterface;

use function count;
use function in_array;
use function is_array;

/**
 * Table Definition for resource_tags
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResourceTags extends Gateway implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param Adapter       $adapter       Database adapter
     * @param PluginManager $tm            Table manager
     * @param array         $cfg           Laminas configuration
     * @param RowGateway    $rowObj        Row prototype object (null for default)
     * @param bool          $caseSensitive Are tags case sensitive?
     * @param string        $table         Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        protected $caseSensitive = false,
        $table = 'resource_tags'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
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
     *
     * @deprecated Use ResourceTagsServiceInterface::createLink()
     */
    public function createLink(
        $resource,
        $tag,
        $user = null,
        $list = null,
        $posted = null
    ) {
        $this->getDbService(ResourceTagsServiceInterface::class)->createLink(
            $resource,
            $tag,
            $user,
            $list,
            $posted ? DateTime::createFromFormat('Y-m-d H:i:s', $posted) : null
        );
    }

    /**
     * Check whether or not the specified tags are present in the table.
     *
     * @param array $ids IDs to check.
     *
     * @return array     Associative array with two keys: present and missing
     *
     * @deprecated
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
     * @param string $tag           Tag to match
     * @param string $userId        ID of user owning favorite list
     * @param string $listId        ID of list to retrieve (null for all favorites)
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getResourcesForTag($tag, $userId, $listId = null, $caseSensitive = null)
    {
        $callback = function ($select) use ($tag, $userId, $listId, $caseSensitive) {
            $select->columns(
                [
                    'resource_id' => new Expression(
                        'DISTINCT(?)',
                        ['resource_tags.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ), Select::SQL_STAR,
                ]
            );
            $select->join(
                ['t' => 'tags'],
                'resource_tags.tag_id = t.id',
                []
            );
            if ($caseSensitive ?? $this->caseSensitive) {
                $select->where->equalTo('t.tag', $tag);
            } else {
                $select->where->literal('lower(t.tag) = lower(?)', [$tag]);
            }
            $select->where->equalTo('resource_tags.user_id', $userId);
            if (null !== $listId) {
                $select->where->equalTo('resource_tags.list_id', $listId);
            }
        };

        return $this->select($callback);
    }

    /**
     * Get lists associated with a particular tag.
     *
     * @param string|array|null $tag           Tag to match (null for all)
     * @param string|array|null $listId        List ID to retrieve (null for all)
     * @param bool              $publicOnly    Whether to return only public lists
     * @param bool              $andTags       Use AND operator when filtering by tag.
     * @param ?bool             $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getListsForTag(
        $tag,
        $listId = null,
        $publicOnly = true,
        $andTags = true,
        $caseSensitive = null
    ) {
        $tag = (array)($tag ?? []);
        $listId = $listId ? (array)$listId : null;

        $callback = function ($select) use (
            $tag,
            $listId,
            $publicOnly,
            $andTags,
            $caseSensitive
        ) {
            $select->columns(
                ['id' => new Expression('min(resource_tags.id)'), 'list_id']
            );

            $select->join(
                ['t' => 'tags'],
                'resource_tags.tag_id = t.id',
                []
            );
            $select->join(
                ['l' => 'user_list'],
                'resource_tags.list_id = l.id',
                []
            );

            // Discard tags assigned to a user resource.
            $select->where->isNull('resource_id');

            // Restrict to tags by list owner
            $select->where->and->equalTo(
                'resource_tags.user_id',
                new Expression('l.user_id')
            );

            if ($listId) {
                $select->where->and->in('resource_tags.list_id', $listId);
            }
            if ($publicOnly) {
                $select->where->and->equalTo('public', 1);
            }
            if ($tag) {
                if ($caseSensitive ?? $this->caseSensitive) {
                    $select->where->and->in('t.tag', $tag);
                } else {
                    $lowerTags = array_map(
                        function ($t) {
                            return new Expression(
                                'lower(?)',
                                [$t],
                                [Expression::TYPE_VALUE]
                            );
                        },
                        $tag
                    );
                    $select->where->and->in(
                        new Expression('lower(t.tag)'),
                        $lowerTags
                    );
                }
            }
            $select->group('resource_tags.list_id');

            if ($tag && $andTags) {
                // Use AND operator for tags
                $select->having->literal(
                    'count(distinct(resource_tags.tag_id)) = ?',
                    count(array_unique($tag))
                );
            }
            $select->order('resource_tags.list_id');
        };

        return $this->select($callback);
    }

    /**
     * Get statistics on use of tags.
     *
     * @param bool  $extended          Include extended (unique/anonymous) stats.
     * @param ?bool $caseSensitiveTags Should we treat tags as case-sensitive? (null for configured behavior)
     *
     * @return array
     */
    public function getStatistics($extended = false, $caseSensitiveTags = null)
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
        $stats = (array)$result->current();
        if ($extended) {
            $stats['unique'] = count($this->getUniqueTags(caseSensitive: $caseSensitiveTags));
            $stats['anonymous'] = $this->getAnonymousCount();
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
     * @param string|array $tag      ID or array of IDs of tag(s) to unlink (null
     * for ALL matching tags)
     *
     * @return void
     *
     * @deprecated Use ResourceTagsServiceInterface::destroyResourceTagsLinksForUser() or
     * ResourceTagsServiceInterface::destroyNonListResourceTagsLinksForUser() or
     * ResourceTagsServiceInterface::destroyAllListResourceTagsLinksForUser()
     */
    public function destroyResourceLinks($resource, $user, $list = null, $tag = null)
    {
        $callback = function ($select) use ($resource, $user, $list, $tag) {
            $select->where->equalTo('user_id', $user);
            if (null !== $resource) {
                $select->where->in('resource_id', (array)$resource);
            }
            if (null !== $list) {
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
            if (null !== $tag) {
                if (is_array($tag)) {
                    $select->where->in('tag_id', $tag);
                } else {
                    $select->where->equalTo('tag_id', $tag);
                }
            }
        };
        $this->processDestroyLinks($callback);
    }

    /**
     * Unlink rows for the specified user list.
     *
     * @param string       $list ID of list to unlink
     * @param string       $user ID of user removing links
     * @param string|array $tag  ID or array of IDs of tag(s) to unlink (null
     * for ALL matching tags)
     *
     * @return void
     *
     * @deprecated Use ResourceTagsServiceInterface::destroyUserListLinks()
     */
    public function destroyListLinks($list, $user, $tag = null)
    {
        $callback = function ($select) use ($user, $list, $tag) {
            $select->where->equalTo('user_id', $user);
            // retrieve tags assigned to a user list
            // and filter out user resource tags
            // (resource_id is NULL for list tags).
            $select->where->isNull('resource_id');
            $select->where->equalTo('list_id', $list);

            if (null !== $tag) {
                if (is_array($tag)) {
                    $select->where->in('tag_id', $tag);
                } else {
                    $select->where->equalTo('tag_id', $tag);
                }
            }
        };
        $this->processDestroyLinks($callback);
    }

    /**
     * Process link rows marked to be destroyed.
     *
     * @param Object $callback Callback function for selecting deleted rows.
     *
     * @return void
     *
     * @deprecated
     */
    protected function processDestroyLinks($callback)
    {
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
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getUniqueResources(
        $userId = null,
        $resourceId = null,
        $tagId = null
    ) {
        $callback = function ($select) use ($userId, $resourceId, $tagId) {
            $select->columns(
                [
                    'resource_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'tag_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.tag_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'list_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.list_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'user_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.user_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                ['r' => 'resource'],
                'resource_tags.resource_id = r.id',
                ['title' => 'title']
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
            $select->group(['resource_id', 'title']);
            $select->order(['title']);
        };
        return $this->select($callback);
    }

    /**
     * Gets unique tags from the table
     *
     * @param string $userId        ID of user
     * @param string $resourceId    ID of the resource
     * @param string $tagId         ID of the tag
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getUniqueTags($userId = null, $resourceId = null, $tagId = null, $caseSensitive = null)
    {
        $callback = function ($select) use ($userId, $resourceId, $tagId, $caseSensitive) {
            $select->columns(
                [
                    'resource_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'tag_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.tag_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'list_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.list_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'user_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.user_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                ['t' => 'tags'],
                'resource_tags.tag_id = t.id',
                [
                    'tag' => ($caseSensitive ?? $this->caseSensitive) ? 'tag' : new Expression('lower(tag)'),
                ]
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
            $select->group(['tag_id', 'tag']);
            $select->order([new Expression('lower(tag)'), 'tag']);
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
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getUniqueUsers($userId = null, $resourceId = null, $tagId = null)
    {
        $callback = function ($select) use ($userId, $resourceId, $tagId) {
            $select->columns(
                [
                    'resource_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'tag_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.tag_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'list_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.list_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'user_id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.user_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'id' => new Expression(
                        'MAX(?)',
                        ['resource_tags.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                ['u' => 'user'],
                'resource_tags.user_id = u.id',
                ['username' => 'username']
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
            $select->group(['user_id', 'username']);
            $select->order(['username']);
        };
        return $this->select($callback);
    }

    /**
     * Given an array for sorting database results, make sure the tag field is
     * sorted in a case-insensitive fashion.
     *
     * @param array $order Order settings
     *
     * @return array
     */
    protected function formatTagOrder($order)
    {
        if (empty($order)) {
            return $order;
        }
        $newOrder = [];
        foreach ((array)$order as $current) {
            $newOrder[] = $current == 'tag'
                ? new Expression('lower(tag)') : $current;
        }
        return $newOrder;
    }

    /**
     * Get Resource Tags
     *
     * @param string $userId        ID of user
     * @param string $resourceId    ID of the resource
     * @param string $tagId         ID of the tag
     * @param string $order         The order in which to return the data
     * @param string $page          The page number to select
     * @param string $limit         The number of items to fetch
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return \Laminas\Paginator\Paginator
     */
    public function getResourceTags(
        $userId = null,
        $resourceId = null,
        $tagId = null,
        $order = null,
        $page = null,
        $limit = 20,
        $caseSensitive = null
    ) {
        $order = (null !== $order)
            ? [$order]
            : ['username', 'tag', 'title'];

        $sql = $this->getSql();
        $select = $sql->select();
        $select->join(
            ['t' => 'tags'],
            'resource_tags.tag_id = t.id',
            [
                'tag' => ($caseSensitive ?? $this->caseSensitive) ? 'tag' : new Expression('lower(tag)'),
            ]
        );
        $select->join(
            ['u' => 'user'],
            'resource_tags.user_id = u.id',
            ['username' => 'username']
        );
        $select->join(
            ['r' => 'resource'],
            'resource_tags.resource_id = r.id',
            ['title' => 'title']
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
        $select->order($this->formatTagOrder($order));

        if (null !== $page) {
            $select->limit($limit);
            $select->offset($limit * ($page - 1));
        }

        $adapter = new \Laminas\Paginator\Adapter\LaminasDb\DbSelect($select, $sql);
        $paginator = new \Laminas\Paginator\Paginator($adapter);
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

    /**
     * Get a list of duplicate rows (this sometimes happens after merging IDs,
     * for example after a Summon resource ID changes).
     *
     * @return mixed
     */
    public function getDuplicates()
    {
        $callback = function ($select) {
            $select->columns(
                [
                    'resource_id' => new Expression(
                        'MIN(?)',
                        ['resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'tag_id' => new Expression(
                        'MIN(?)',
                        ['tag_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'list_id' => new Expression(
                        'MIN(?)',
                        ['list_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'user_id' => new Expression(
                        'MIN(?)',
                        ['user_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'cnt' => new Expression(
                        'COUNT(?)',
                        ['resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'id' => new Expression(
                        'MIN(?)',
                        ['id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->group(['resource_id', 'tag_id', 'list_id', 'user_id']);
            $select->having('COUNT(resource_id) > 1');
        };
        return $this->select($callback);
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate()
    {
        foreach ($this->getDuplicates() as $dupe) {
            $callback = function ($select) use ($dupe) {
                // match on all relevant IDs in duplicate group
                $select->where(
                    [
                        'resource_id' => $dupe['resource_id'],
                        'tag_id' => $dupe['tag_id'],
                        'list_id' => $dupe['list_id'],
                        'user_id' => $dupe['user_id'],
                    ]
                );
                // getDuplicates returns the minimum id in the set, so we want to
                // delete all of the duplicates with a higher id value.
                $select->where->greaterThan('id', $dupe['id']);
            };
            $this->delete($callback);
        }
    }
}
