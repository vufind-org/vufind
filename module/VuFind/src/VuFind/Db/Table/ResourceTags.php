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

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use VuFind\Db\Row\RowGateway;

/**
 * Table Definition for resource_tags
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResourceTags extends Gateway
{
    /**
     * Are tags case sensitive?
     *
     * @var bool
     */
    protected $caseSensitive;

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
        $caseSensitive = false,
        $table = 'resource_tags'
    ) {
        $this->caseSensitive = $caseSensitive;
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
     */
    public function createLink(
        $resource,
        $tag,
        $user = null,
        $list = null,
        $posted = null
    ) {
        $callback = function ($select) use ($resource, $tag, $user, $list) {
            $select->where->equalTo('resource_id', $resource)
                ->equalTo('tag_id', $tag);
            if (null !== $list) {
                $select->where->equalTo('list_id', $list);
            } else {
                $select->where->isNull('list_id');
            }
            if (null !== $user) {
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
            if (null !== $list) {
                $result->list_id = $list;
            }
            if (null !== $user) {
                $result->user_id = $user;
            }
            if (null !== $posted) {
                $result->posted = $posted;
            }
            $result->save();
        }
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
}
