<?php
/**
 * Table Definition for resource
 *
 * PHP version 7
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
namespace Finna\Db\Table;

use Laminas\Db\Sql\Expression;

/**
 * Table Definition for resource
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Resource extends \VuFind\Db\Table\Resource
{
    /**
     * Get a set of records from the requested favorite list.
     *
     * @param string $user   ID of user owning favorite list
     * @param string $list   ID of list to retrieve (null for all favorites)
     * @param array  $tags   Tags to use for limiting results
     * @param string $sort   Resource table field to use for sorting (null for
     * no particular sort).
     * @param int    $offset Offset for results
     * @param int    $limit  Limit for results (null for none)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     * @todo   Refactor to avoid duplication
     */
    public function getFavorites($user, $list = null, $tags = [],
        $sort = null, $offset = 0, $limit = null
    ) {
        // Set up base query:
        $obj = & $this;
        return $this->select(
            function ($s) use ($user, $list, $tags, $sort, $offset, $limit, $obj) {
                $s->columns(
                    [
                        new Expression(
                            'DISTINCT(?)', ['resource.id'],
                            [Expression::TYPE_IDENTIFIER]
                        ), '*'
                    ]
                );
                $urColumns = $list === null ?
                    [
                    'id' => new Expression(
                        'MAX(?)', ['ur.id'],
                        [Expression::TYPE_IDENTIFIER]
                    )
                    ]
                    : [
                    'id' => new Expression(
                        'MAX(?)', ['ur.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'finna_custom_order_index' => new Expression(
                        'MAX(?)', ['ur.finna_custom_order_index'],
                        [Expression::TYPE_IDENTIFIER]
                    )
                    ];
                $s->join(
                    ['ur' => 'user_resource'], 'resource.id = ur.resource_id',
                    $urColumns
                );
                $s->where->equalTo('ur.user_id', $user);

                // Adjust for list if necessary:
                if (null !== $list) {
                    $s->where->equalTo('ur.list_id', $list);
                }

                if ($offset > 0) {
                    $s->offset($offset);
                }
                if (null !== $limit) {
                    $s->limit($limit);
                }

                // Adjust for tags if necessary:
                if (!empty($tags)) {
                    $linkingTable = $obj->getDbTable('ResourceTags');
                    foreach ($tags as $tag) {
                        $matches = $linkingTable
                            ->getResourcesForTag($tag, $user, $list)->toArray();
                        $getId = function ($i) {
                            return $i['resource_id'];
                        };
                        $s->where->in('resource.id', array_map($getId, $matches));
                    }
                }
                $s->group('resource.id');
                if (!empty($sort)) {
                    Resource::applySort($s, $sort);
                }
            }
        );
    }

    /**
     * Apply a sort parameter to a query on the resource table.
     *
     * @param \Laminas\Db\Sql\Select $query Query to modify
     * @param string                 $sort  Field to use for sorting (may include
     * 'desc' qualifier)
     * @param string                 $alias Alias to the resource table (defaults to
     * 'resource')
     *
     * @return void
     */
    public static function applySort($query, $sort, $alias = 'resource')
    {
        // Apply sorting, if necessary:
        $legalSorts = [
            'title', 'title desc', 'author', 'author desc', 'year', 'year desc',
            'id', 'id desc', 'custom_order'
        ];
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            if ('custom_order' === $sort) {
                $sort = 'finna_custom_order_index';
            }
            // Strip off 'desc' to obtain the raw field name -- we'll need it
            // to sort null values to the bottom:
            $parts = explode(' ', $sort);
            $rawField = trim($parts[0]);

            // Start building the list of sort fields:
            $order = [];

            // The title field or id can't be null, so don't bother with the extra
            // isnull() sort in that case. In finna_custom_order_index it's fine for
            // it to be null.
            $special = in_array(
                strtolower($rawField), ['title', 'id', 'finna_custom_order_index']
            );
            if (!$special) {
                $order[] = new Expression(
                    'isnull(?)', [$alias . '.' . $rawField],
                    [Expression::TYPE_IDENTIFIER]
                );
            }

            // Apply the user-specified sort:
            if ('id' === $rawField || 'finna_custom_order_index' === $rawField) {
                $desc = $parts[1] ?? '';
                $order[] = new Expression(
                    'MAX(?)' . $desc, ['ur.' . $rawField],
                    [Expression::TYPE_IDENTIFIER]
                );
            } else {
                $order[] = $alias . '.' . $sort;
            }

            // Inject the sort preferences into the query object:
            $query->order($order);
        }
    }
}
