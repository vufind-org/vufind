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
 * @package  DB_Models
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
 * @package  DB_Models
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
     * @return Zend_Db_Table_Row|null Matching row if found or created, null
     * otherwise.
     */
    public function getByText($tag, $create = true)
    {
        $result = $this->select(array('tag' => $tag))->current();
        if (is_null($result) && $create) {
            $result
                = clone($this->getResultSetPrototype()->getArrayObjectPrototype());
            $result->tag = $tag;
            $result->save();
        }
        return $result;
    }

    /**
     * Get the tags the match a string
     *
     * @param string $text Tag to look up.
     *
     * @return array of Zend_Db_Table_Row
     */
    public function matchText($text)
    {
        $callback = function ($select) use ($text) {
            $select->where->literal('lower(tag) like lower(?)', array($text . '%'));
            $select->order('tag');
        };
        return $this->select($callback);
    }

    /**
     * Get count of anonymous tags
     *
     * @return int count
     */
    public static function getAnonymousCount()
    {
        /* TODO
        $resourceTagTable = new VuFind_Model_Db_ResourceTags();
        $select = $resourceTagTable
            ->select()
            ->from(
                array('resource_tags'),
                array('cnt' => 'COUNT(*)')
            )
            ->where('user_id IS NULL');
        $count = $resourceTagTable->fetchRow($select);
        return $count['cnt'];
         */
    }

    /**
     * Get count of usage for a tag by id
     *
     * @param int $tag_id tag id
     *
     * @return int count
     */
    public function getCount($tag_id)
    {
        $resourceTagTable = new ResourceTags();
        $callback = function ($select) use ($tag_id) {
            $select->columns(
                array(
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))', array('resource_id'),
                        array(Expression::TYPE_IDENTIFIER)
                    )
                )
            );
            $select->where->equalTo('tag_id', $tag_id);
        };
        $count = $resourceTagTable->select($callback)->current();
        return isset($count['cnt']) ? $count['cnt'] : 0;
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id      Record ID to look up
     * @param string $source  Source of record to look up
     * @param int    $limit   Max. number of tags to return (0 = no limit)
     * @param int    $list_id ID of list to load tags from (null for no restriction,
     * true for on ANY list, false for on NO list)
     * @param int    $user_id ID of user to load tags from (null for all users)
     * @param string $sort    Sort type ('count' or 'tag')
     *
     * @return array
     */
    public function getForResource($id, $source = 'VuFind', $limit = 0,
        $list_id = null, $user_id = null, $sort = 'count'
    ) {
        $callback = function ($select) use ($id, $source, $limit, $list_id, $user_id,
            $sort
        ) {
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
            if ($list_id === true) {
                $select->where->isNotNull('rt.list_id');
            } else if ($list_id === false) {
                $select->where->isNull('rt.list_id');
            } else if (!is_null($list_id)) {
                $select->where->equalTo('rt.list_id', $list_id);
            }
            if (!is_null($user_id)) {
                $select->where->equalTo('rt.user_id', $user_id);
            }
        };

        return $this->select($callback);
    }

    /**
     * Get a list of tags based on a sort method ($sort)
     *
     * @param string $sort  Sort/search parameter
     * @param int    $limit Maximum number of tags
     *
     * @return array Tag details.
     */
    public function getTagList($sort, $limit = 100)
    {
        $callback = function($select) use ($sort, $limit) {
            $select->columns(
                array(
                    'tag',
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
            if (strlen($extra_where) > 0) {
                $select->where($extra_where);
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
        /* TODO
        // Do nothing if we have no IDs to delete!
        if (empty($ids)) {
            return;
        }

        $db = $this->getAdapter();

        $clauses = array();
        foreach ($ids as $current) {
            $clauses[] = $db->quoteInto('id = ?', $current);
        }

        $this->delete(implode(' OR ', $clauses));
         */
    }
}
