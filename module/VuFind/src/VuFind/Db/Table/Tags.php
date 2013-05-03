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
use Zend\Db\Sql\Expression;

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
        $result = $this->select(array('tag' => $tag))->current();
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
            $select->where->literal('lower(tag) like lower(?)', array($text . '%'));
        };
        return $this->getTagList($sort, $limit, $callback);
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param int    $limit  Max. number of tags to return (0 = no limit)
     * @param int    $list   ID of list to load tags from (null for no restriction,
     * true for on ANY list, false for on NO list)
     * @param int    $user   ID of user to load tags from (null for all users)
     * @param string $sort   Sort type ('count' or 'tag')
     *
     * @return array
     */
    public function getForResource($id, $source = 'VuFind', $limit = 0,
        $list = null, $user = null, $sort = 'count'
    ) {
        return $this->select(
            function ($select) use ($id, $source, $limit, $list, $user, $sort) {
                $select->columns(
                    array(
                        'id', 'tag',
                        'cnt' => new Expression(
                            'COUNT(?)', array('tags.tag'),
                            array(Expression::TYPE_IDENTIFIER)
                        )
                    )
                );
                $select->join(
                    array('rt' => 'resource_tags'), 'tags.id = rt.tag_id', array()
                );
                $select->join(
                    array('r' => 'resource'), 'rt.resource_id = r.id', array()
                );
                $select->where->equalTo('r.record_id', $id)
                    ->equalTo('r.source', $source);
                $select->group(array('id', 'tag'));

                if ($sort == 'count') {
                    $select->order(array('cnt DESC', 'tags.tag'));
                } else if ($sort == 'tag') {
                    $select->order(array('tags.tag'));
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
     * Get a list of tags based on a sort method ($sort)
     *
     * @param string   $sort        Sort/search parameter
     * @param int      $limit       Maximum number of tags
     * @param callback $extra_where Extra code to modify $select (null for none)
     *
     * @return array Tag details.
     */
    public function getTagList($sort, $limit = 100, $extra_where = null)
    {
        $callback = function ($select) use ($sort, $limit, $extra_where) {
            $select->columns(
                array(
                    'id', 'tag',
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))', array('resource_tags.resource_id'),
                        array(Expression::TYPE_IDENTIFIER)
                    ),
                    'posted' => new Expression(
                        'MAX(?)', array('resource_tags.posted'),
                        array(Expression::TYPE_IDENTIFIER)
                    )
                )
            );
            $select->join(
                'resource_tags', 'tags.id = resource_tags.tag_id', array()
            );
            if (is_callable($extra_where)) {
                $extra_where($select);
            }
            $select->group('tags.tag');
            switch ($sort) {
            case 'alphabetical':
                $select->order(array('tags.tag', 'cnt DESC'));
                break;
            case 'popularity':
                $select->order(array('cnt DESC', 'tags.tag'));
                break;
            case 'recent':
                $select->order(
                    array('posted DESC', 'cnt DESC', 'tags.tag')
                );
                break;
            }
            // Limit the size of our results based on the ini browse limit setting
            //$select->limit($limit);
        };

        $tagList = array();
        foreach ($this->select($callback) as $t) {
            $tagList[] = array(
                'tag' => $t->tag,
                'cnt' => $t->cnt
            );
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
                array(
                    'tag',
                    'cnt' => new Expression(
                        'COUNT(?)', array('tag'), array(Expression::TYPE_IDENTIFIER)
                    ),
                    'id' => new Expression(
                        'MIN(?)', array('id'), array(Expression::TYPE_IDENTIFIER)
                    )
                )
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
        $result = $table->select(array('tag_id' => $source));

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
        $this->delete(array('id' => $source));
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
        $result = $this->select(array('tag' => $tag));
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
