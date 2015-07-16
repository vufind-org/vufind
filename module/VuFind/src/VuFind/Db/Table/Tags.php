<?php
/**
 * Table Definition for tags
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
use Zend\Db\Sql\Expression, Zend\Db\Sql\Select;

/**
 * Table Definition for tags
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Tags extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('tags', 'VuFind\Db\Row\Tags');
    }

    /**
     * Get the row associated with a specific tag string.
     *
     * @param string $tag    Tag to look up.
     * @param bool   $create Should we create the row if it does not exist?
     *
     * @return \VuFind\Db\Row\Tags|null Matching row if found or created, null
     * otherwise.
     */
    public function getByText($tag, $create = true)
    {
        $result = $this->select(['tag' => $tag])->current();
        if (empty($result) && $create) {
            $result = $this->createRow();
            $result->tag = $tag;
            $result->save();
        }
        return $result;
    }

    /**
     * Get the tags the match a string
     *
     * @param string $text  Tag to look up.
     * @param string $sort  Sort/search parameter
     * @param int    $limit Maximum number of tags
     *
     * @return array Array of \VuFind\Db\Row\Tags objects
     */
    public function matchText($text, $sort = 'alphabetical', $limit = 100)
    {
        $callback = function ($select) use ($text) {
            $select->where->literal('lower(tag) like lower(?)', [$text . '%']);
        };
        return $this->getTagList($sort, $limit, $callback);
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id          Record ID to look up
     * @param string $source      Source of record to look up
     * @param int    $limit       Max. number of tags to return (0 = no limit)
     * @param int    $list        ID of list to load tags from (null for no
     * restriction,  true for on ANY list, false for on NO list)
     * @param int    $user        ID of user to load tags from (null for all users)
     * @param string $sort        Sort type ('count' or 'tag')
     * @param int    $userToCheck ID of user to check for ownership (this will
     * not filter the result list, but rows owned by this user will have an is_me
     * column set to 1)
     *
     * @return array
     */
    public function getForResource($id, $source = 'VuFind', $limit = 0,
        $list = null, $user = null, $sort = 'count', $userToCheck = null
    ) {
        return $this->select(
            function ($select) use (
                $id, $source, $limit, $list, $user, $sort, $userToCheck
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
                            'is_me' => new Expression(
                                'MAX(?)', ['subq.is_me'],
                                [Expression::TYPE_IDENTIFIER]
                            )
                        ],
                        Select::JOIN_LEFT
                    );
                }
                // SELECT (do not add table prefixes)
                $select->columns(
                    [
                        'id', 'tag',
                        'cnt' => new Expression(
                            'COUNT(DISTINCT(?))', ["rt.user_id"],
                            [Expression::TYPE_IDENTIFIER]
                        )
                    ]
                );
                $select->join(
                    ['rt' => 'resource_tags'], 'rt.tag_id = tags.id', []
                );
                $select->join(
                    ['r' => 'resource'], 'rt.resource_id = r.id', []
                );
                $select->where(['r.record_id' => $id, 'r.source' => $source]);
                $select->group(['tags.id', 'tag']);

                if ($sort == 'count') {
                    $select->order(['cnt DESC', 'tags.tag']);
                } else if ($sort == 'tag') {
                    $select->order(['tags.tag']);
                }

                if ($limit > 0) {
                    $select->limit($limit);
                }
                if ($list === true) {
                    $select->where->isNotNull('rt.list_id');
                } else if ($list === false) {
                    $select->where->isNull('rt.list_id');
                } else if (!is_null($list)) {
                    $select->where->equalTo('rt.list_id', $list);
                }
                if (!is_null($user)) {
                    $select->where->equalTo('rt.user_id', $user);
                }
            }
        );
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
        $sub->columns(['tag_id', 'is_me' => new Expression("1")])
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
                    'user_id' => $userToCheck
                ]
            );
        return $sub;
    }

    /**
     * Get a list of tags based on a sort method ($sort)
     *
     * @param string   $sort        Sort/search parameter
     * @param int      $limit       Maximum number of tags (default = 100,
     * < 1 = no limit)
     * @param callback $extra_where Extra code to modify $select (null for none)
     *
     * @return array Tag details.
     */
    public function getTagList($sort, $limit = 100, $extra_where = null)
    {
        $callback = function ($select) use ($sort, $limit, $extra_where) {
            $select->columns(
                [
                    'id', 'tag',
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))', ['resource_tags.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'posted' => new Expression(
                        'MAX(?)', ['resource_tags.posted'],
                        [Expression::TYPE_IDENTIFIER]
                    )
                ]
            );
            $select->join(
                'resource_tags', 'tags.id = resource_tags.tag_id', []
            );
            if (is_callable($extra_where)) {
                $extra_where($select);
            }
            $select->group('tags.tag');
            switch ($sort) {
            case 'alphabetical':
                $select->order(['tags.tag', 'cnt DESC']);
                break;
            case 'popularity':
                $select->order(['cnt DESC', 'tags.tag']);
                break;
            case 'recent':
                $select->order(
                    ['posted DESC', 'cnt DESC', 'tags.tag']
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
                'cnt' => $t->cnt
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
     * have introduced problems).
     *
     * @return mixed
     */
    public function getDuplicates()
    {
        $callback = function ($select) {
            $select->columns(
                [
                    'tag',
                    'cnt' => new Expression(
                        'COUNT(?)', ['tag'], [Expression::TYPE_IDENTIFIER]
                    ),
                    'id' => new Expression(
                        'MIN(?)', ['id'], [Expression::TYPE_IDENTIFIER]
                    )
                ]
            );
            $select->group('tag');
            $select->having->greaterThan('cnt', 1);
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
        $result = $table->select(['tag_id' => $source]);

        foreach ($result as $current) {
            // Move the link to the target ID:
            $table->createLink(
                $current->resource_id, $target, $current->user_id,
                $current->list_id, $current->posted
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
     * @param string $tag Tag to deduplicate.
     *
     * @return void
     */
    protected function fixDuplicateTag($tag)
    {
        // Make sure this really is a duplicate.
        $result = $this->select(['tag' => $tag]);
        if (count($result) < 2) {
            return;
        }

        $first = $result->current();
        foreach ($result as $current) {
            $this->mergeTags($first->id, $current->id);
        }
    }

    /**
     * Repair duplicate tags in the database (if any).
     *
     * @return void
     */
    public function fixDuplicateTags()
    {
        foreach ($this->getDuplicates() as $dupe) {
            $this->fixDuplicateTag($dupe->tag);
        }
    }
}
