<?php
/**
 * Table Definition for resource
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
use VuFind\Record, Zend\Db\Sql\Expression;

/**
 * Table Definition for resource
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Resource extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('resource', 'VuFind\Db\Row\Resource');
    }

    /**
     * Look up a row for the specified resource.
     *
     * @param string                            $id     Record ID to look up
     * @param string                            $source Source of record to look up
     * @param bool                              $create If true, create the row if it
     * does not
     * yet exist.
     * @param \VuFind\RecordDriver\AbstractBase $driver A record driver for the
     * resource being created (optional -- improves efficiency if provided, but will
     * be auto-loaded as needed if left null).
     *
     * @return Zend_Db_Table_Row|null Matching row if found or created, null
     * otherwise.
     */
    public function findResource($id, $source = 'VuFind', $create = true,
        $driver = null
    ) {
        if (empty($id)) {
            throw new \Exception('Resource ID cannot be empty');
        }
        $select = $this->select(array('record_id' => $id, 'source' => $source));
        $result = $select->current();

        // Create row if it does not already exist and creation is enabled:
        if (empty($result) && $create) {
            $result = $this->createRow();
            $result->record_id = $id;
            $result->source = $source;

            // Load record if it was not provided:
            if (is_null($driver)) {
                $driver = Record::load($id, $source);
            }

            // Load metadata into the database for sorting/failback purposes:
            $result->assignMetadata($driver);

            // Save the new row.
            $result->save();
        }
        return $result;
    }

    /**
     * Look up a rowset for a set of specified resources.
     *
     * @param array  $ids    Array of IDs
     * @param string $source Source of records to look up
     *
     * @return Zend_Db_Table_Rowset
     */
    public function findResources($ids, $source = 'VuFind')
    {
        $callback = function ($select) use ($ids, $source) {
            $select->where->in('record_id', $ids);
            $select->where->equalTo('source', $source);
        };
        return $this->select($callback);
    }

    /**
     * Get a set of records from the requested favorite list.
     *
     * @param string $userId ID of user owning favorite list
     * @param string $listId ID of list to retrieve (null for all favorites)
     * @param array  $tags   Tags to use for limiting results
     * @param string $sort   Resource table field to use for sorting (null for
     * no particular sort).
     * @param int    $offset Offset for results
     * @param int    $limit  Limit for results (null for none)
     *
     * @return Zend_Db_Table_Rowset
     */
    public function getFavorites($userId, $listId = null, $tags = array(),
        $sort = null, $offset = 0, $limit = null
    ) {
        // Set up base query:
        $callback = function ($select) use ($userId, $listId, $tags, $sort, $offset,
            $limit
        ) {
            $select->columns(
                array(
                    new Expression(
                        'DISTINCT(?)', array('resource.id'),
                        array(Expression::TYPE_IDENTIFIER)
                    ), '*'
                )
            );
            $select->join(
                array('ur' => 'user_resource'), 'resource.id = ur.resource_id',
                array()
            );
            $select->where->equalTo('ur.user_id', $userId);

            // Adjust for list if necessary:
            if (!is_null($listId)) {
                $select->where->equalTo('ur.list_id', $listId);
            }

            if ($offset > 0) {
                $select->offset($offset);
            }
            if (!is_null($limit)) {
                $select->limit($limit);
            }

            // Adjust for tags if necessary:
            if (!empty($tags)) {
                $linkingTable = new ResourceTags();
                foreach ($tags as $tag) {
                    $matches = $linkingTable
                        ->getResourcesForTag($tag, $userId, $listId)->toArray();
                    $getId = function ($i) {
                        return $i['resource_id'];
                    };
                    $select->where->in('resource.id', array_map($getId, $matches));
                }
            }

            // Apply sorting, if necessary:
            if (!empty($sort)) {
                Resource::applySort($select, $sort);
            }
        };

        return $this->select($callback);
    }

    /**
     * Get a set of records that do not have metadata stored in the resource
     * table.
     *
     * @return Zend_Db_Table_Rowset
     */
    public function findMissingMetadata()
    {
        $callback = function ($select) {
            $select->where->equalTo('title', '')
                ->OR->isNull('author')
                ->OR->isNull('year');
        };
        return $this->select($callback);
    }

    /**
     * Apply a sort parameter to a query on the resource table.
     *
     * @param Zend_Db_Select $query Query to modify
     * @param string         $sort  Field to use for sorting (may include 'desc'
     * qualifier)
     * @param string         $alias Alias to the resource table (defaults to
     * 'resource')
     *
     * @return void
     */
    public static function applySort($query, $sort, $alias = 'resource')
    {
        // Apply sorting, if necessary:
        $legalSorts = array(
            'title', 'title desc', 'author', 'author desc', 'year', 'year desc'
        );
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            // Strip off 'desc' to obtain the raw field name -- we'll need it
            // to sort null values to the bottom:
            $parts = explode(' ', $sort);
            $rawField = trim($parts[0]);

            // Start building the list of sort fields:
            $order = array();

            // The title field can't be null, so don't bother with the extra
            // isnull() sort in that case.
            if (strtolower($rawField) != 'title') {
                /* TODO -- uncomment when order() method supports Expressions
                $order[] = new Expression(
                    'isnull(?)', array($alias . '.' . $rawField),
                    array(Expression::TYPE_IDENTIFIER)
                );
                  */
            }

            // Apply the user-specified sort:
            $order[] = $alias . '.' . $sort;

            // Inject the sort preferences into the query object:
            $query->order($order);
        }
    }
}
