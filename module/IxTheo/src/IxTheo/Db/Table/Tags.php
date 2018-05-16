<?php
/**
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) Universitätsbibliothek Tübingen 2018
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace IxTheo\Db\Table;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\Tags as VuFindTags;
use VuFind\Db\Table\Resource as Resource;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;

class Tags extends VuFindTags
{

//    protected $filtered_resource_tags = 'filtered_resource_tags';
    protected $filtered_resource_tags = 'resource_tags';

    /**
     * Constructor
     *
     * @param Adapter       $adapter       Database adapter
     * @param PluginManager $tm            Table manager
     * @param array         $cfg           Zend Framework configuration
     * @param RowGateway    $rowObj        Row prototype object (null for default)
     * @param bool          $caseSensitive Are tags case sensitive?
     * @param string        $table         Name of database table to interface with
     */
    public function __construct(Adapter $adapter, \VuFind\Db\Table\PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $caseSensitive = false, $table = 'tags'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }


    protected function determineFilteredResourceTable() {


    }


    /**
     * Get the tags that match a string
     *
     * @param string $text  Tag to look up.
     * @param string $sort  Sort/search parameter
     * @param int    $limit Maximum number of tags
     *
     * @return array Array of \VuFind\Db\Row\Tags objects
     */


    public function getTagList($sort, $limit = 100, $extra_where = null)
    {
        $callback = function ($select) use ($sort, $limit, $extra_where) {
            $filtered_resource_tags = $this->filtered_resource_tags;
            $select->columns(
                [
                    'id',
                    'tag' => $this->caseSensitive
                        ? 'tag' : new Expression('lower(tag)'),
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))', [$filtered_resource_tags . '.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'posted' => new Expression(
                        'MAX(?)', [$filtered_resource_tags . '.posted'],
                        [Expression::TYPE_IDENTIFIER]
                    )
                ]
            );
            $select->join(
                $filtered_resource_tags, 'tags.id = ' . $filtered_resource_tags . '.tag_id', []
            );
            if (is_callable($extra_where)) {
                $extra_where($select);
            }
            $select->group(['tags.id', 'tags.tag']);
            switch ($sort) {
            case 'alphabetical':
                $select->order([new Expression('lower(tags.tag)'), 'cnt DESC']);
                break;
               $select->order(['cnt DESC', new Expression('lower(tags.tag)')]);
                break;
            case 'recent':
                $select->order(
                    ['posted DESC', 'cnt DESC', new Expression('lower(tags.tag)')]
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


    public function resourceSearch($q, $source = null, $sort = null,
        $offset = 0, $limit = null, $fuzzy = true
    ) {
        $cb = function ($select) use ($q, $source, $sort, $offset, $limit, $fuzzy) {
            $select->columns(
                [
                    new Expression(
                        'DISTINCT(?)', ['resource.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                ['rt' => $this->filtered_resource_tags],
                'tags.id = rt.tag_id',
                []
            );
            $select->join(
                ['resource' => 'resource'],
                'rt.resource_id = resource.id',
                '*'
            );
            if ($fuzzy) {
                $select->where->literal('lower(tags.tag) like lower(?)', [$q]);
            } else if (!$this->caseSensitive) {
                $select->where->literal('lower(tags.tag) = lower(?)', [$q]);
            } else {
                $select->where->equalTo('tags.tag', $q);
            }

            if (!empty($source)) {
                $select->where->equalTo('source', $source);
            }

            if (!empty($sort)) {
               Resource::applySort($select, $sort);
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
}
