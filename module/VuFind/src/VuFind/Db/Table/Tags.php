<?php

/**
 * Table Definition for tags
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

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Select;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\TagServiceInterface;

use function count;
use function is_callable;

/**
 * Table Definition for tags
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Tags extends Gateway implements DbServiceAwareInterface
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
        $table = 'tags'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get the row associated with a specific tag string.
     *
     * @param string $tag           Tag to look up.
     * @param bool   $create        Should we create the row if it does not exist?
     * @param bool   $firstOnly     Should we return the first matching row (true)
     * or the entire result set (in case of multiple matches)?
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return mixed Matching row/result set if found or created, null otherwise.
     */
    public function getByText($tag, $create = true, $firstOnly = true, $caseSensitive = null)
    {
        $cs = $caseSensitive ?? $this->caseSensitive;
        $result = $this->getDbService(TagServiceInterface::class)->getTagsByText($tag, $cs);
        if (count($result) == 0 && $create) {
            $row = $this->createRow();
            $row->tag = $cs ? $tag : mb_strtolower($tag, 'UTF8');
            $row->save();
            return $firstOnly ? $row : [$row];
        }
        return $firstOnly ? $result[0] ?? null : $result;
    }

    /**
     * Get the tags that match a string
     *
     * @param string $text          Tag to look up.
     * @param string $sort          Sort/search parameter
     * @param int    $limit         Maximum number of tags
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return array Array of \VuFind\Db\Row\Tags objects
     */
    public function matchText($text, $sort = 'alphabetical', $limit = 100, $caseSensitive = null)
    {
        $callback = function ($select) use ($text) {
            $select->where->literal('lower(tag) like lower(?)', [$text . '%']);
            // Discard tags assigned to a user list.
            $select->where->isNotNull('resource_tags.resource_id');
        };
        return $this->getTagList($sort, $limit, $callback, $caseSensitive);
    }

    /**
     * Get all resources associated with the provided tag query.
     *
     * @param string $q             Search query
     * @param string $source        Record source (optional limiter)
     * @param string $sort          Resource field to sort on (optional)
     * @param int    $offset        Offset for results
     * @param int    $limit         Limit for results (null for none)
     * @param bool   $fuzzy         Are we doing an exact or fuzzy search?
     * @param ?bool  $caseSensitive Should search be case sensitive? (null to use configured default)
     *
     * @return array
     */
    public function resourceSearch(
        $q,
        $source = null,
        $sort = null,
        $offset = 0,
        $limit = null,
        $fuzzy = true,
        $caseSensitive = null
    ) {
        $cb = function ($select) use ($q, $source, $sort, $offset, $limit, $fuzzy, $caseSensitive) {
            $columns = [
                new Expression(
                    'DISTINCT(?)',
                    ['resource.id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
            ];
            $select->columns($columns);
            $select->join(
                ['rt' => 'resource_tags'],
                'tags.id = rt.tag_id',
                []
            );
            $select->join(
                ['resource' => 'resource'],
                'rt.resource_id = resource.id',
                Select::SQL_STAR
            );
            if ($fuzzy) {
                $select->where->literal('lower(tags.tag) like lower(?)', [$q]);
            } elseif (!($caseSensitive ?? $this->caseSensitive)) {
                $select->where->literal('lower(tags.tag) = lower(?)', [$q]);
            } else {
                $select->where->equalTo('tags.tag', $q);
            }
            // Discard tags assigned to a user list.
            $select->where->isNotNull('rt.resource_id');

            if (!empty($source)) {
                $select->where->equalTo('source', $source);
            }

            if (!empty($sort)) {
                Resource::applySort($select, $sort, 'resource', $columns);
            }

            if ($offset > 0) {
                $select->offset($offset);
            }
            if (null !== $limit) {
                $select->limit($limit);
            }
        };

        return $this->select($cb);
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id            Record ID to look up
     * @param string $source        Source of record to look up
     * @param int    $limit         Max. number of tags to return (0 = no limit)
     * @param int    $list          ID of list to load tags from (null for no
     * restriction, true for on ANY list, false for on NO list)
     * @param int    $user          ID of user to load tags from (null for all users)
     * @param string $sort          Sort type ('count' or 'tag')
     * @param int    $userToCheck   ID of user to check for ownership (this will
     * not filter the result list, but rows owned by this user will have an is_me
     * column set to 1)
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return array
     */
    public function getForResource(
        $id,
        $source = DEFAULT_SEARCH_BACKEND,
        $limit = 0,
        $list = null,
        $user = null,
        $sort = 'count',
        $userToCheck = null,
        $caseSensitive = null
    ) {
        return $this->select(
            function ($select) use (
                $id,
                $source,
                $limit,
                $list,
                $user,
                $sort,
                $userToCheck,
                $caseSensitive
            ) {
                // If we're looking for ownership, create sub query to merge in
                // an "is_me" flag value if the selected resource is tagged by
                // the specified user.
                if (!empty($userToCheck)) {
                    $subq = $this->getIsMeSubquery($id, $source, $userToCheck);
                    $select->join(
                        ['subq' => $subq],
                        'tags.id = subq.tag_id',
                        [
                            // is_me will either be null (not owned) or the ID
                            // of the tag (owned by the current user).
                            'is_me' => new Expression(
                                'MAX(?)',
                                ['subq.tag_id'],
                                [Expression::TYPE_IDENTIFIER]
                            ),
                        ],
                        Select::JOIN_LEFT
                    );
                }
                // SELECT (do not add table prefixes)
                $select->columns(
                    [
                        'id',
                        'tag' => ($caseSensitive ?? $this->caseSensitive)
                            ? 'tag' : new Expression('lower(tag)'),
                        'cnt' => new Expression(
                            'COUNT(DISTINCT(?))',
                            ['rt.user_id'],
                            [Expression::TYPE_IDENTIFIER]
                        ),
                    ]
                );
                $select->join(
                    ['rt' => 'resource_tags'],
                    'rt.tag_id = tags.id',
                    []
                );
                $select->join(
                    ['r' => 'resource'],
                    'rt.resource_id = r.id',
                    []
                );
                $select->where(['r.record_id' => $id, 'r.source' => $source]);
                $select->group(['tags.id', 'tag']);

                if ($sort == 'count') {
                    $select->order(['cnt DESC', new Expression('lower(tags.tag)')]);
                } elseif ($sort == 'tag') {
                    $select->order([new Expression('lower(tags.tag)')]);
                }

                if ($limit > 0) {
                    $select->limit($limit);
                }
                if ($list === true) {
                    $select->where->isNotNull('rt.list_id');
                } elseif ($list === false) {
                    $select->where->isNull('rt.list_id');
                } elseif (null !== $list) {
                    $select->where->equalTo('rt.list_id', $list);
                }
                if (null !== $user) {
                    $select->where->equalTo('rt.user_id', $user);
                }
            }
        );
    }

    /**
     * Get a list of all tags generated by the user in favorites lists. Note that
     * the returned list WILL NOT include tags attached to records that are not
     * saved in favorites lists.
     *
     * @param string $userId        User ID to look up.
     * @param string $resourceId    Filter for tags tied to a specific resource (null for no filter).
     * @param int    $listId        Filter for tags tied to a specific list (null for no filter).
     * @param string $source        Filter for tags tied to a specific record source (null for no filter).
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getListTagsForUser(
        $userId,
        $resourceId = null,
        $listId = null,
        $source = null,
        $caseSensitive = null
    ) {
        $callback = function ($select) use ($userId, $resourceId, $listId, $source, $caseSensitive) {
            $select->columns(
                [
                    'id' => new Expression(
                        'min(?)',
                        ['tags.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'tag' => ($caseSensitive ?? $this->caseSensitive)
                        ? 'tag' : new Expression('lower(tag)'),
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))',
                        ['rt.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                ['rt' => 'resource_tags'],
                'tags.id = rt.tag_id',
                []
            );
            $select->join(
                ['r' => 'resource'],
                'rt.resource_id = r.id',
                []
            );
            $select->join(
                ['ur' => 'user_resource'],
                'r.id = ur.resource_id',
                []
            );
            $select->group(['tag'])->order([new Expression('lower(tag)')]);

            $select->where->equalTo('ur.user_id', $userId)
                ->equalTo('rt.user_id', $userId)
                ->equalTo(
                    'ur.list_id',
                    'rt.list_id',
                    Predicate::TYPE_IDENTIFIER,
                    Predicate::TYPE_IDENTIFIER
                );

            if (null !== $source) {
                $select->where->equalTo('r.source', $source);
            }

            if (null !== $resourceId) {
                $select->where->equalTo('r.record_id', $resourceId);
            }
            if (null !== $listId) {
                $select->where->equalTo('rt.list_id', $listId);
            }
        };
        return $this->select($callback);
    }

    /**
     * Get tags assigned to a user list.
     *
     * @param int   $listId        List ID
     * @param ?int  $userId        User ID to look up (null for no filter).
     * @param ?bool $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getForList($listId, $userId = null, $caseSensitive = null)
    {
        $callback = function ($select) use ($listId, $userId, $caseSensitive) {
            $select->columns(
                [
                    'id' => new Expression(
                        'min(?)',
                        ['tags.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'tag' => ($caseSensitive ?? $this->caseSensitive)
                        ? 'tag' : new Expression('lower(tag)'),
                ]
            );
            $select->join(
                ['rt' => 'resource_tags'],
                'tags.id = rt.tag_id',
                []
            );
            $select->where->equalTo('rt.list_id', $listId);
            $select->where->isNull('rt.resource_id');
            if ($userId) {
                $select->where->equalTo('rt.user_id', $userId);
            }
            $select->group(['tag'])->order([new Expression('lower(tag)')]);
        };
        return $this->select($callback);
    }

    /**
     * Get a subquery used for flagging tag ownership (see getForResource).
     *
     * @param string $id          Record ID to look up
     * @param string $source      Source of record to look up
     * @param int    $userToCheck ID of user to check for ownership
     *
     * @return Select
     */
    protected function getIsMeSubquery($id, $source, $userToCheck)
    {
        $sub = new Select('resource_tags');
        $sub->columns(['tag_id'])
            ->join(
                // Convert record_id to resource_id
                ['r' => 'resource'],
                'resource_id = r.id',
                []
            )
            ->where(
                [
                    'r.record_id' => $id,
                    'r.source' => $source,
                    'user_id' => $userToCheck,
                ]
            );
        return $sub;
    }

    /**
     * Get a list of tags based on a sort method ($sort)
     *
     * @param string   $sort          Sort/search parameter
     * @param int      $limit         Maximum number of tags (default = 100, < 1 = no limit)
     * @param callback $extra_where   Extra code to modify $select (null for none)
     * @param ?bool    $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return array Tag details.
     */
    public function getTagList($sort, $limit = 100, $extra_where = null, $caseSensitive = null)
    {
        $callback = function ($select) use ($sort, $limit, $extra_where, $caseSensitive) {
            $select->columns(
                [
                    'id',
                    'tag' => ($caseSensitive ?? $this->caseSensitive)
                        ? 'tag' : new Expression('lower(tag)'),
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))',
                        ['resource_tags.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'posted' => new Expression(
                        'MAX(?)',
                        ['resource_tags.posted'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                'resource_tags',
                'tags.id = resource_tags.tag_id',
                []
            );
            if (is_callable($extra_where)) {
                $extra_where($select);
            }
            $select->group(['tags.id', 'tags.tag']);
            switch ($sort) {
                case 'alphabetical':
                    $select->order([new Expression('lower(tags.tag)'), 'cnt DESC']);
                    break;
                case 'popularity':
                    $select->order(['cnt DESC', new Expression('lower(tags.tag)')]);
                    break;
                case 'recent':
                    $select->order(
                        [
                            'posted DESC',
                            'cnt DESC',
                            new Expression('lower(tags.tag)'),
                        ]
                    );
                    break;
            }
            // Limit the size of our results
            if ($limit > 0) {
                $select->limit($limit);
            }
        };

        $tagList = [];
        foreach ($this->select($callback) as $t) {
            $tagList[] = [
                'tag' => $t->tag,
                'cnt' => $t->cnt,
            ];
        }
        return $tagList;
    }

    /**
     * Delete a group of tags.
     *
     * @param array $ids IDs of tags to delete.
     *
     * @return void
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
    }

    /**
     * Get a list of duplicate tags (this should never happen, but past bugs
     * and the introduction of case-insensitive tags have introduced problems).
     *
     * @param ?bool $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return mixed
     */
    public function getDuplicates($caseSensitive = null)
    {
        $callback = function ($select) use ($caseSensitive) {
            $select->columns(
                [
                    'tag' => new Expression(
                        'MIN(?)',
                        ['tag'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'cnt' => new Expression(
                        'COUNT(?)',
                        ['tag'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'id' => new Expression(
                        'MIN(?)',
                        ['id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->group(
                ($caseSensitive ?? $this->caseSensitive) ? 'tag' : new Expression('lower(tag)')
            );
            $select->having('COUNT(tag) > 1');
        };
        return $this->select($callback);
    }

    /**
     * Support method for fixDuplicateTag() -- merge $source into $target.
     *
     * @param string $target Target ID
     * @param string $source Source ID
     *
     * @return void
     */
    protected function mergeTags($target, $source)
    {
        // Don't merge a tag with itself!
        if ($target === $source) {
            return;
        }
        $table = $this->getDbTable('ResourceTags');
        $resourceTagsService = $this->getDbService(ResourceTagsServiceInterface::class);
        $result = $table->select(['tag_id' => $source]);

        foreach ($result as $current) {
            // Move the link to the target ID:
            $resourceTagsService->createLink(
                $current->resource_id,
                $target,
                $current->user_id,
                $current->list_id,
                $current->getPosted()
            );

            // Remove the duplicate link:
            $table->delete($current->toArray());
        }

        // Remove the source tag:
        $this->delete(['id' => $source]);
    }

    /**
     * Support method for fixDuplicateTags()
     *
     * @param string $tag           Tag to deduplicate.
     * @param ?bool  $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return void
     */
    protected function fixDuplicateTag($tag, $caseSensitive = null)
    {
        // Make sure this really is a duplicate.
        $result = $this->getDbService(TagServiceInterface::class)
            ->getTagsByText($tag, $caseSensitive ?? $this->caseSensitive);
        if (count($result) < 2) {
            return;
        }

        $first = $result[0];
        foreach ($result as $current) {
            $this->mergeTags($first->getId(), $current->getId());
        }
    }

    /**
     * Repair duplicate tags in the database (if any).
     *
     * @param ?bool $caseSensitive Should tags be case sensitive? (null to use configured default)
     *
     * @return void
     */
    public function fixDuplicateTags($caseSensitive = null)
    {
        foreach ($this->getDuplicates($caseSensitive) as $dupe) {
            $this->fixDuplicateTag($dupe->tag, $caseSensitive);
        }
    }
}
